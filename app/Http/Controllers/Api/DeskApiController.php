<?php

namespace App\Http\Controllers\Api;

use App\Models\City;
use App\Models\Order;
use App\Models\User;
use App\Services\Api\OrderLeadPresenter;
use App\Services\OrgTreeService;
use App\Support\OrderLeadSource;
use App\Support\OrderAddressReveal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Внутренний API для отдельного приложения «Единое окно» (lead-desk).
 * Auth: Bearer DESK_API_TOKEN + опционально X-Desk-User-Id (контекст пользователя).
 */
class DeskApiController extends Controller
{
    private const CLOSED = ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'];

    /**
     * Логин хаба: проверка email/password, список доступных проектов.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->with('roles')->where('email', $email)->first();

        if (! $user || ! $user->is_active || $user->is_blacklisted) {
            throw ValidationException::withMessages(['email' => ['Неверный email или пароль']]);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Неверный email или пароль']]);
        }

        if ($user->requiresTwoFactor() && $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Для этого пользователя включена 2FA. Временно войдите через CRM: /crm/login',
                'needs_2fa' => true,
            ], 403);
        }

        return response()->json([
            'user' => $this->serializeUser($user),
            'projects' => $this->projectsFor($user),
        ]);
    }

    /**
     * Выпуск SSO-кода для входа в CRM (или desk) из хаба.
     */
    public function issueSso(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'target' => 'required|in:crm,desk',
        ]);

        $user = User::query()->with('roles')->find($validated['user_id']);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $code = Str::random(64);
        $key = $validated['target'] === 'crm' ? 'hub_sso:' : 'desk_sso:';
        Cache::put($key.$code, [
            'user_id' => $user->user_id,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(2));

        $hub = rtrim((string) config('services.hub.public_url'), '/');
        $redirect = $validated['target'] === 'crm'
            ? url('/crm/sso/callback?code='.urlencode($code))
            : $hub.'/sso/callback?code='.urlencode($code);

        // url() с APP_URL=.../crm даст .../crm/crm/sso — собираем явно
        if ($validated['target'] === 'crm') {
            $redirect = rtrim((string) config('app.url'), '/').'/crm/sso/callback?code='.urlencode($code);
        }

        return response()->json([
            'code' => $code,
            'redirect' => $redirect,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function exchangeSso(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:128',
        ]);

        $payload = Cache::pull('desk_sso:'.$validated['code']);
        if (! is_array($payload) || empty($payload['user_id'])) {
            return response()->json(['message' => 'Invalid or expired SSO code'], 422);
        }

        $user = User::query()->with('roles')->find($payload['user_id']);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) {
            return response()->json(['message' => 'X-Desk-User-Id required'], 400);
        }

        return response()->json(['user' => $this->serializeUser($user)]);
    }

    public function statuses(OrderLeadPresenter $presenter): JsonResponse
    {
        return response()->json([
            // legacy map code → label (Desk / старые клиенты)
            'statuses' => Order::getStatusLabels(),
            'catalog' => $presenter->statusCatalog(),
        ]);
    }

    /**
     * Оргдерево: города → рег / дир филиала / мастера.
     * Без X-Desk-User-Id — вся компания; с заголовком — города в скоупе пользователя.
     */
    public function orgTree(Request $request, OrgTreeService $orgTree): JsonResponse
    {
        return response()->json($orgTree->tree($this->resolveUser($request)));
    }

    public function cities(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        $query = City::query()->where('is_active', true)->orderBy('city_name');

        if ($user) {
            $ids = $user->cityIdsForOrdersFilter();
            if (is_array($ids)) {
                if ($ids === []) {
                    return response()->json(['cities' => []]);
                }
                $query->whereIn('city_id', $ids);
            }
        }

        $cities = $query->get(['city_id', 'city_name', 'city_timezone'])->map(fn (City $c) => [
            'id' => $c->city_id,
            'name' => $c->city_name,
            'timezone' => $c->city_timezone,
        ]);

        return response()->json(['cities' => $cities]);
    }

    /**
     * Upsert мастеров из КП: сопоставление по kp_employee_id (иначе email).
     */
    public function mastersUpsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => 'nullable|integer|exists:cities,city_id',
            'masters' => 'required|array|min:1',
            'masters.*.kp_employee_id' => 'required',
            'masters.*.name' => 'required|string|max:255',
            'masters.*.email' => 'nullable|string|max:255',
            'masters.*.passport' => 'nullable|string|max:64',
            'masters.*.status' => 'nullable|string|max:64',
            'masters.*.is_active' => 'nullable|boolean',
            'masters.*.city_ids' => 'nullable|array',
            'masters.*.city_ids.*' => 'integer',
            'masters.*.city_names' => 'nullable|array',
            'masters.*.city_names.*' => 'string|max:255',
            'deactivate_missing' => 'sometimes|boolean',
        ]);

        $result = app(\App\Services\KpMasterSyncService::class)->upsertMany(
            $validated['masters'],
            isset($validated['city_id']) ? (int) $validated['city_id'] : null,
            (bool) ($validated['deactivate_missing'] ?? false)
        );

        return response()->json($result);
    }

    public function orders(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        $lite = $request->boolean('lite');

        // lite — для фонового sync Единого окна: без documents/masters N+1
        $with = $lite
            ? ['address.city', 'master', 'persons.phones', 'source']
            : ['address.city', 'master', 'persons.phones', 'documents', 'source'];

        $query = Order::query()->with($with);

        // Активные всегда + недавно закрытые (Готов/отмены), чтобы desk видел смену статуса и времени
        $query->where(function ($q) {
            $q->whereNotIn('order_status', self::CLOSED)
                ->orWhere('order_closed_at', '>=', now()->subDays(14));
        });

        if ($request->boolean('full')) {
            // без доп. ограничения по дате создания
        } elseif ($request->filled('updated_after')) {
            // инкремент: все открытые (статус/время могли смениться) + закрытые после метки
            $after = Carbon::parse($request->input('updated_after'));
            $query->where(function ($q) use ($after) {
                $q->whereNotIn('order_status', self::CLOSED)
                    ->orWhere('order_closed_at', '>=', $after)
                    ->orWhere('order_created_at', '>=', $after)
                    ->orWhere('datetime_order', '>=', $after);
            });
        } else {
            $query->where(function ($q) {
                $q->where('order_created_at', '>=', now()->subDays(30))
                    ->orWhere('order_closed_at', '>=', now()->subDays(14));
            });
        }

        if ($user && ! $request->boolean('all_cities') && ! $request->boolean('all_branches')) {
            $ids = $user->cityIdsForOrdersFilter();
            if ($ids === []) {
                return response()->json(['orders' => []]);
            }
            if (is_array($ids)) {
                $query->whereHas('address', fn ($q) => $q->whereIn('city_id', $ids));
            }
        }

        if ($request->filled('city_id')) {
            $cityId = (int) $request->city_id;
            $query->whereHas('address', fn ($q) => $q->where('city_id', $cityId));
        }

        $orders = $query->orderByDesc('order_created_at')->limit(5000)->get()
            ->map(fn (Order $o) => $this->serializeOrder($o, $lite));

        return response()->json(['orders' => $orders]);
    }

    public function orderShow(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()
            ->with(['address.city', 'master', 'persons.phones', 'documents', 'source'])
            ->find($orderId);

        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['order' => $this->serializeOrder($order)]);
    }

    public function orderStatusShow(Request $request, int $orderId, OrderLeadPresenter $presenter): JsonResponse
    {
        $order = Order::query()->with('address')->find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($presenter->presentStatus($order));
    }

    public function orderStatusUpdate(Request $request, int $orderId, OrderLeadPresenter $presenter): JsonResponse
    {
        $order = Order::query()->with('address')->find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($order->isClosedStatus()) {
            return response()->json(['message' => 'Order already closed'], 422);
        }

        $allowed = array_keys(Order::getStatusLabels());
        $validated = $request->validate([
            'order_status' => ['required_without:raw_status', 'nullable', 'string', Rule::in($allowed)],
            'raw_status' => ['required_without:order_status', 'nullable', 'string', Rule::in($allowed)],
        ]);

        $status = $validated['order_status'] ?? $validated['raw_status'];
        $order->order_status = $status;
        $order->save();

        return response()->json($presenter->presentStatus($order->fresh('address')));
    }

    public function orderUpdate(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()->find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($order->isClosedStatus()) {
            return response()->json(['message' => 'Order already closed'], 422);
        }

        $validated = $request->validate([
            'raw_status' => 'nullable|string|max:64',
            'paid_amount' => 'nullable|integer|min:0',
            'parts_amount' => 'nullable|integer|min:0',
            'comment' => 'nullable|string|max:2000',
            'master_id' => 'nullable|integer',
            'master_external_id' => 'nullable|string|max:64',
        ]);

        $fill = [];
        if (! empty($validated['raw_status'])) {
            $fill['order_status'] = $validated['raw_status'];
        }
        if (array_key_exists('paid_amount', $validated) && $validated['paid_amount'] !== null) {
            $fill['amount_paid'] = (int) $validated['paid_amount'];
        }
        if (array_key_exists('parts_amount', $validated) && $validated['parts_amount'] !== null) {
            $fill['amount_comp'] = (int) $validated['parts_amount'];
        }
        $masterId = $validated['master_id'] ?? $validated['master_external_id'] ?? null;
        if ($masterId !== null && $masterId !== '') {
            $fill['master_id'] = (int) $masterId > 0 ? (int) $masterId : null;
        }
        if (! empty($validated['comment'])) {
            $existing = trim((string) ($order->city_adds ?? ''));
            $stamp = now()->format('d.m.Y H:i');
            $name = $user?->user_name ?? 'desk';
            $line = "[{$stamp} {$name}] ".$validated['comment'];
            $fill['city_adds'] = $existing === '' ? $line : $existing."\n".$line;
        }

        if ($fill !== []) {
            $order->fill($fill);
            $order->save();
        }

        $order->load(['address.city', 'master', 'persons.phones', 'documents']);

        return response()->json(['order' => $this->serializeOrder($order)]);
    }

    public function orderClose(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()->with(['address.city', 'master', 'documents'])->find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($order->isClosedStatus()) {
            return response()->json(['ok' => true, 'order_id' => $order->order_id]);
        }

        $userId = $user?->user_id;
        if (! $userId) {
            return response()->json(['message' => 'Нужен контекст пользователя для проведения'], 422);
        }

        $check = app(\App\Services\OrderService::class)->canComplete($order);
        if (! ($check['can'] ?? false)) {
            return response()->json([
                'message' => implode('. ', $check['errors'] ?? ['Нельзя провести заказ']),
            ], 422);
        }

        try {
            app(\App\Services\OrderService::class)->complete($order, (int) $userId);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->load(['address.city', 'master', 'persons.phones', 'documents']);
        $extras = app(\App\Services\OrderService::class)->deskSerializationExtras($order);

        return response()->json([
            'ok' => true,
            'order_id' => $order->order_id,
            'order' => $this->serializeOrder($order),
            'calculation' => $extras['calculation'] ?? null,
        ]);
    }

    public function orderDocumentUpload(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()->find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($order->isClosedStatus()) {
            return response()->json(['message' => 'Заказ уже закрыт'], 422);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', \App\Models\Document::orderPhotoMimeValidationRule()],
            'category' => 'required|in:contract,receipts,parts_photos,storage_receipt',
        ]);

        $file = $request->file('file');
        if (! $file || ! \App\Models\Document::isAllowedOrderPhoto($file)) {
            return response()->json(['message' => 'Можно загружать только фото'], 422);
        }

        $category = $validated['category'];
        $existingCount = \App\Models\Document::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->order_id)
            ->where('document_category', $category)
            ->count();
        if ($existingCount >= 20) {
            return response()->json(['message' => 'Максимум 20 файлов в категории'], 422);
        }

        $folder = "documents/orders/{$order->order_id}/{$category}";
        $path = $file->store($folder, 'local');
        $uploadedBy = $user?->user_id ?: (int) $order->order_created_by;
        if ($uploadedBy <= 0) {
            $uploadedBy = (int) User::query()->orderBy('user_id')->value('user_id');
        }
        if ($uploadedBy <= 0) {
            return response()->json(['message' => 'Не удалось определить автора загрузки'], 422);
        }

        $doc = new \App\Models\Document([
            'documentable_type' => Order::class,
            'documentable_id' => $order->order_id,
            'document_category' => $category,
            'file_name' => basename(str_replace("\0", '', $file->getClientOriginalName())) ?: 'file',
            'file_path' => $path,
            'file_mime' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
        $doc->uploaded_by = $uploadedBy;
        $doc->save();

        return response()->json([
            'ok' => true,
            'document' => [
                'id' => (string) $doc->document_id,
                'name' => $doc->file_name,
                'category' => $doc->document_category,
            ],
        ]);
    }

    public function orderDocumentShow(Request $request, int $orderId, int $documentId)
    {
        $order = Order::query()->find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $doc = \App\Models\Document::query()
            ->where('document_id', $documentId)
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->order_id)
            ->first();
        if (! $doc || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($doc->file_path)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->response(
            $doc->file_path,
            $doc->file_name,
            ['Content-Type' => $doc->file_mime ?: 'application/octet-stream']
        );
    }

    public function orderDocumentDelete(Request $request, int $orderId, int $documentId): JsonResponse
    {
        $order = Order::query()->find($orderId);
        if (! $order) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $user = $this->resolveUser($request);
        if ($user && ! $this->userCanSeeOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($order->isClosedStatus()) {
            return response()->json(['message' => 'Заказ уже закрыт'], 422);
        }

        $doc = \App\Models\Document::query()
            ->where('document_id', $documentId)
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->order_id)
            ->first();
        if (! $doc) {
            return response()->json(['message' => 'Not found'], 404);
        }
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($doc->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($doc->file_path);
        }
        $doc->delete();

        return response()->json(['ok' => true]);
    }

    private function resolveUser(Request $request): ?User
    {
        $id = (int) $request->header('X-Desk-User-Id', 0);
        if ($id <= 0) {
            return null;
        }

        return User::query()->with('roles')->find($id);
    }

    private function userCanSeeOrder(User $user, Order $order): bool
    {
        $cityId = $order->address?->city_id ?? $order->address()->value('city_id');
        if (! $cityId) {
            return $user->hasRole('developer') || $user->hasRole('general_director');
        }

        return $user->hasAccessToCity((int) $cityId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $cityIds = $user->cityIdsForOrdersFilter();

        $citiesQuery = City::query()
            ->where('is_active', true)
            ->orderBy('city_name');
        if ($cityIds !== null) {
            $citiesQuery->whereIn('city_id', $cityIds === [] ? [-1] : $cityIds);
        }
        $cityModels = $citiesQuery->get(['city_id', 'city_name', 'parent_city_id']);
        $parentNames = City::query()
            ->whereIn('city_id', $cityModels->pluck('parent_city_id')->filter()->unique()->all())
            ->pluck('city_name', 'city_id');

        $cities = $cityModels->map(function (City $c) use ($parentNames) {
            $isSatellite = $c->parent_city_id !== null;
            $parentName = $isSatellite ? ($parentNames[$c->parent_city_id] ?? null) : null;
            $label = $c->city_name;
            if ($isSatellite && $parentName && ! $c->nameIncludesParent((string) $parentName)) {
                $label = $c->city_name.' (спутник '.$parentName.')';
            }

            return [
                'id' => (int) $c->city_id,
                'name' => $c->city_name,
                'label' => $label,
                'parent_id' => $c->parent_city_id ? (int) $c->parent_city_id : null,
                'is_satellite' => $isSatellite,
            ];
        })->values()->all();

        return [
            'id' => $user->user_id,
            'name' => $user->user_name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('role_code')->values()->all(),
            'city_ids' => $cityIds, // null = all
            'cities' => $cities,
            'can_close' => $user->hasAnyRole([
                'developer', 'senior_dispatcher', 'senior_manager', 'manager', 'branch_head', 'regional_director', 'general_director',
            ]),
        ];
    }

    /**
     * @return list<array{code: string, name: string, path: string}>
     */
    private function projectsFor(User $user): array
    {
        $projects = [];

        if ($user->hasAnyRole([
            'developer', 'call_center', 'senior_dispatcher', 'senior_manager', 'manager',
            'tech_director', 'branch_head', 'regional_director', 'general_director',
        ])) {
            $projects[] = [
                'code' => 'desk',
                'name' => 'Единое окно заказов',
                'path' => '/desk',
            ];
            $projects[] = [
                'code' => 'crm',
                'name' => 'CRM Lead Control',
                'path' => '/crm',
            ];
        }

        return $projects;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, bool $lite = false): array
    {
        $city = $order->address?->city;
        $person = $order->persons->first();
        $phone = $person?->phones->first()?->phone_number;

        $addressParts = [];
        if ($city?->city_name) {
            $addressParts[] = 'г. '.$city->city_name;
        }
        if ($order->address) {
            $streetOnly = OrderAddressReveal::streetLine($order->address);
            if ($streetOnly !== '') {
                $addressParts[] = $streetOnly;
            }
        }

        $docs = [];
        if (! $lite) {
            $docs = $order->documents->map(fn ($d) => [
                'id' => (string) $d->document_id,
                'name' => $d->file_name ?? ('doc-'.$d->document_id),
                'category' => (string) ($d->document_category ?? 'contract'),
            ])->values()->all();
        }

        $comments = collect([
            $order->order_adds ? 'Проблема: '.$order->order_adds : null,
            $order->shift_adds ? 'Переносы: '.$order->shift_adds : null,
            $order->city_adds ? 'Филиал: '.$order->city_adds : null,
        ])->filter()->implode("\n");

        $cityId = $order->address?->city_id;
        $masters = [];
        if (! $lite && $cityId) {
            $masters = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('role_code', 'master'))
                ->whereHas('cities', fn ($q) => $q->where('cities.city_id', $cityId))
                ->orderBy('user_name')
                ->get(['user_id', 'user_name', 'kp_employee_id'])
                ->map(fn ($m) => [
                    'id' => (string) $m->user_id,
                    'name' => $m->user_name,
                    'kp_employee_id' => $m->kp_employee_id ? (string) $m->kp_employee_id : null,
                ])
                ->values()
                ->all();

            // текущий мастер, если уже не в списке (другой город / неактивен)
            if ($order->master_id && ! collect($masters)->contains(fn ($m) => (string) $m['id'] === (string) $order->master_id)) {
                array_unshift($masters, [
                    'id' => (string) $order->master_id,
                    'name' => $order->master?->user_name ?: ('#'.$order->master_id),
                ]);
            }
        }

        $payload = [
            'order_id' => (int) $order->order_id,
            'external_id' => (string) $order->order_id,
            'source' => OrderLeadSource::LC,
            // РК: имя без «· Парты»; ссылка — только для парт-источников с URL
            'rk' => $order->source?->deskRkName() ?: null,
            'rk_url' => $order->source?->deskRkUrl(),
            'marketing_source' => $order->source?->deskRkName() ?: null,
            'city_id' => $cityId,
            'city_name' => $city?->city_name,
            'raw_status' => (string) $order->order_status,
            'order_status' => (string) $order->order_status,
            'status_label' => Order::getStatusLabels()[$order->order_status] ?? (string) $order->order_status,
            'client_name' => $person?->person_name,
            'phone' => $phone,
            'address' => implode(', ', array_filter($addressParts)),
            'address_office' => OrderAddressReveal::officeTextFromAddress($order->address),
            'description' => $order->order_adds,
            'master_id' => $order->master_id ? (string) $order->master_id : null,
            'master_external_id' => $order->master_id ? (string) $order->master_id : null,
            'master_name' => $order->master?->user_name,
            'masters' => $masters,
            'total_amount' => $order->amount_paid !== null
                ? max(0, (int) $order->amount_paid - (int) ($order->amount_comp ?? 0))
                : null,
            'paid_amount' => $order->amount_paid,
            'parts_amount' => $order->amount_comp,
            'created_at_local' => optional($order->order_created_at)?->toDateTimeString(),
            'call_at_local' => optional($order->datetime_order)?->toDateTimeString(),
            'timezone' => $city?->city_timezone,
            'updated_at_local' => optional($order->order_closed_at ?? $order->order_created_at)?->toDateTimeString(),
            'order_type' => match ($order->order_type) {
                'repeat' => 'repeat',
                'warranty' => 'warranty',
                default => 'first',
            },
            'comments' => $comments,
            'documents' => $docs,
            'priority' => match ($order->order_status) {
                'callback', 'not_processed' => 10,
                'pending', 'on_way' => 20,
                'in_progress', 'in_progress_sd' => 30,
                'review' => 40,
                default => 50,
            },
            'row_highlight' => null,
            'is_closed' => in_array($order->order_status, self::CLOSED, true),
        ];

        // lite: флаги без расчёта ЗП (дорого на тысячах строк)
        if ($lite) {
            $order->loadMissing(['address.city.parentCity', 'source']);
            $payload['order_core'] = $order->order_core;
            $payload['is_noncore'] = $order->order_core === 'non_core';
            $payload['is_long_trip'] = (bool) $order->is_long_trip;
            $payload['is_satellite'] = $order->isSatelliteCityOrder();
            $payload['is_partner_order'] = $order->isPartnerOrder();
            $payload['partner_user_id'] = $order->partner_user_id ? (int) $order->partner_user_id : null;
            $payload['order_core_label'] = Order::orderCoreLabel($order->order_core);
        } else {
            $payload = array_merge($payload, app(\App\Services\OrderService::class)->deskSerializationExtras($order));
        }

        $payload['hash'] = hash('sha256', json_encode([
            $payload['raw_status'],
            $payload['paid_amount'],
            $payload['parts_amount'],
            $payload['master_name'],
            $payload['comments'],
            $payload['call_at_local'],
            $payload['order_core'] ?? null,
            $payload['is_noncore'] ?? false,
            $payload['is_long_trip'] ?? false,
            $payload['is_satellite'] ?? false,
            $payload['is_partner_order'] ?? false,
            count($docs),
        ], JSON_UNESCAPED_UNICODE));

        return $payload;
    }
}
