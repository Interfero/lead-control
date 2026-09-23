<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\HubActionNotSupportedException;
use App\Exceptions\HubOrderConflictException;
use App\Exceptions\HubSourceUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\GmApiService;
use App\Services\GmUserSyncService;
use App\Services\OrgTreeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class GmController extends Controller
{
    public function __construct(
        private GmApiService $gmApiService,
        private GmUserSyncService $gmUserSyncService
    ) {}

    public function health(): JsonResponse
    {
        return response()->json($this->gmApiService->health());
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json($this->gmApiService->profile($user));
    }

    public function metrics(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json($this->gmApiService->metrics($user));
    }

    public function ratingHistory(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json($this->gmApiService->ratingHistory());
    }

    public function orders(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->gmApiService->orders($user, $filters));
    }

    /**
     * Состояние источников Единого хаба (подключение + время синхронизации по городам).
     */
    public function sources(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json($this->gmApiService->sourcesStatus($user));
    }

    public function orderShow(Request $request, string $orderId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $payload = $this->gmApiService->orderDetails($user, $orderId);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order not found.', 404);
        } catch (HubSourceUnavailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 503, [], $exception->errorCode());
        }

        return response()->json($payload);
    }

    public function orderDocuments(Request $request, string $orderId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $payload = $this->gmApiService->orderDocuments($user, $orderId);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order not found.', 404);
        } catch (HubSourceUnavailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 503, [], $exception->errorCode());
        }

        return response()->json($payload);
    }

    public function uploadOrderDocument(Request $request, string $orderId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $payload = $this->gmApiService->uploadOrderDocument($user, $orderId, $request);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order not found.', 404);
        } catch (HubActionNotSupportedException $exception) {
            return $this->sourceActionResponse($exception);
        }

        return response()->json($payload);
    }

    public function acceptOrder(Request $request, string $orderId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $payload = $this->gmApiService->acceptOrder($user, $orderId);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order not found.', 404);
        } catch (HubActionNotSupportedException $exception) {
            return $this->sourceActionResponse($exception);
        } catch (HubOrderConflictException $exception) {
            return $this->hubConflictResponse($exception);
        } catch (HubSourceUnavailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 503, [], $exception->errorCode());
        } catch (ConflictHttpException $exception) {
            return $this->errorResponse(
                'Заявка уже закрыта или в финальном статусе.',
                409,
                [],
                'order_already_final',
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json($payload);
    }

    public function markOrderInProgress(Request $request, string $orderId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $payload = $this->gmApiService->moveOrderInProgress($user, $orderId);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order not found.', 404);
        } catch (HubActionNotSupportedException $exception) {
            return $this->sourceActionResponse($exception);
        } catch (HubOrderConflictException $exception) {
            return $this->hubConflictResponse($exception);
        } catch (HubSourceUnavailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 503, [], $exception->errorCode());
        } catch (ConflictHttpException $exception) {
            return $this->errorResponse(
                'Заявка уже закрыта или в финальном статусе.',
                409,
                [],
                'order_already_final',
            );
        } catch (ValidationException $exception) {
            $code = isset($exception->errors()['status']) ? 'invalid_status_transition' : null;

            return $this->errorResponse(
                $exception->getMessage(),
                422,
                $exception->errors(),
                $code,
            );
        }

        return response()->json($payload);
    }

    public function markOrderReview(Request $request, string $orderId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $request->validate([
            'amountPaidRub' => ['required', 'integer', 'min:0'],
            'amountCompRub' => ['required', 'integer', 'min:0'],
            'masterComment' => ['required', 'string', 'min:1'],
        ]);

        try {
            $payload = $this->gmApiService->markOrderReview($user, $orderId, $validated);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order not found.', 404);
        } catch (HubActionNotSupportedException $exception) {
            return $this->sourceActionResponse($exception);
        } catch (ConflictHttpException $exception) {
            $code = $exception->getMessage() === 'Order status cannot be saved.'
                ? 'order_status_rejected'
                : 'order_already_final';

            return $this->errorResponse($exception->getMessage(), 409, [], $code);
        } catch (ValidationException $exception) {
            $code = isset($exception->errors()['status']) ? 'invalid_status_transition' : null;

            return $this->errorResponse(
                $exception->getMessage(),
                422,
                $exception->errors(),
                $code,
            );
        }

        return response()->json($payload);
    }

    public function moveOrderToSd(Request $request, string $orderId): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $request->validate([
            'masterComment' => ['required', 'string', 'min:1'],
        ]);

        try {
            $payload = $this->gmApiService->moveOrderToSd($user, $orderId, $validated);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order not found.', 404);
        } catch (HubActionNotSupportedException $exception) {
            return $this->sourceActionResponse($exception);
        }

        return response()->json($payload);
    }

    /**
     * Заявка внешнего источника: действие недоступно в LC — 409, но не 500.
     */
    private function sourceActionResponse(HubActionNotSupportedException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->errorCode(),
            'source' => $exception->source,
            'action' => $exception->action,
        ], 409);
    }

    /**
     * Конфликт записи/перехода по KP-заявке — отдельный code + понятное сообщение.
     */
    private function hubConflictResponse(HubOrderConflictException $exception): JsonResponse
    {
        $payload = [
            'message' => $exception->getMessage(),
            'code' => $exception->errorCode,
        ];
        if ($exception->source !== null) {
            $payload['source'] = $exception->source;
        }
        if ($exception->action !== null) {
            $payload['action'] = $exception->action;
        }

        return response()->json($payload, 409);
    }

    public function reviews(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'sentiment' => ['nullable', 'string', 'in:all,positive,neutral,negative'],
            'page' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->gmApiService->reviews($user, $filters));
    }

    public function negativeOpenReviews(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json($this->gmApiService->negativeOpenReviews($user));
    }

    public function guildApplications(): JsonResponse
    {
        return response()->json($this->gmApiService->guildApplications());
    }

    public function orgTree(Request $request, OrgTreeService $orgTree): JsonResponse
    {
        $user = $this->resolveOptionalUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json($orgTree->tree($user));
    }

    public function branchRoster(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $cityId = $this->optionalCityId($request);

        try {
            return response()->json($this->gmApiService->branchRoster($user, $cityId));
        } catch (ModelNotFoundException) {
            return $this->errorResponse('City not found.', 404);
        } catch (AuthorizationException) {
            return $this->errorResponse('No access to this city.', 403);
        }
    }

    public function branchStats(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $cityId = $this->optionalCityId($request);

        try {
            return response()->json($this->gmApiService->branchStats($user, $cityId));
        } catch (ModelNotFoundException) {
            return $this->errorResponse('City not found.', 404);
        } catch (AuthorizationException) {
            return $this->errorResponse('No access to this city.', 403);
        }
    }

    public function messenger(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json($this->gmApiService->messenger($user));
    }

    public function syncUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'all' => ['nullable', 'boolean'],
            'includeInactive' => ['nullable', 'boolean'],
            'userIds' => ['nullable', 'array'],
            'userIds.*' => ['integer', 'min:1'],
        ]);

        $query = User::query()->with(['roles', 'cities'])->orderBy('user_id');

        if (! ($validated['includeInactive'] ?? false)) {
            $query->where('is_active', true);
        }

        if (($validated['all'] ?? false) !== true) {
            $userIds = $validated['userIds'] ?? [];
            if ($userIds === []) {
                return response()->json([
                    'message' => 'Pass all=true or userIds[].',
                ], 422);
            }

            $query->whereIn('user_id', $userIds);
        }

        $result = $this->gmUserSyncService->syncMany($query->get());

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 207);
    }

    /**
     * Проверка логина/пароля сотрудника LC для входа в GM (server-to-server).
     */
    public function verifyPassword(Request $request): JsonResponse
    {
        $login = trim((string) ($request->input('login', $request->input('email', ''))));
        $password = (string) $request->input('password', '');

        if ($login === '' || $password === '') {
            return response()->json([
                'ok' => false,
                'error' => 'validation_error',
            ], 422);
        }

        $result = $this->gmApiService->verifyPassword($login, $password);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * Смена пароля мастера из GM (server-to-server). GM передаёт userId из сессии после lc-login.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $userId = $request->input('userId');
        $login = trim((string) $request->input('login', ''));
        $currentPassword = (string) $request->input('currentPassword', '');
        $newPassword = (string) $request->input('newPassword', '');

        $hasUserId = is_numeric($userId) && (int) $userId > 0;
        if (! $hasUserId && $login === '') {
            return response()->json([
                'ok' => false,
                'error' => 'validation_error',
                'message' => 'Укажите userId или login',
            ], 422);
        }

        if ($currentPassword === '') {
            return response()->json([
                'ok' => false,
                'error' => 'validation_error',
                'message' => 'Текущий пароль обязателен',
            ], 422);
        }

        if ($newPassword === '') {
            return response()->json([
                'ok' => false,
                'error' => 'validation_error',
                'message' => 'Новый пароль обязателен',
            ], 422);
        }

        if (strlen($newPassword) < 8) {
            return response()->json([
                'ok' => false,
                'error' => 'validation_error',
                'message' => 'Новый пароль должен содержать минимум 8 символов',
            ], 422);
        }

        $result = $this->gmApiService->changePassword(
            $hasUserId ? (int) $userId : null,
            $login !== '' ? $login : null,
            $currentPassword,
            $newPassword,
        );

        return response()->json($result['body'], $result['status']);
    }

    /**
     * Статус пароля мастера для баннера «нужно сменить пароль» в GM.
     */
    public function passwordStatus(int $userId): JsonResponse
    {
        $result = $this->gmApiService->passwordStatus($userId);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @return int|null
     */
    private function optionalCityId(Request $request): ?int
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return isset($validated['city_id']) ? (int) $validated['city_id'] : null;
    }

    private function resolveUser(Request $request): User|JsonResponse
    {
        try {
            return $this->gmApiService->resolveUser($request);
        } catch (ValidationException $exception) {
            return $this->errorResponse('Invalid GM user identity payload.', 400, $exception->errors());
        } catch (ModelNotFoundException) {
            return $this->errorResponse('User not found.', 404);
        }
    }

    /**
     * Identity опционален: без X-GM-* / query — полное дерево (чаты, server-to-server).
     */
    private function resolveOptionalUser(Request $request): User|JsonResponse|null
    {
        $userId = trim((string) ($request->header('X-GM-User-Id', $request->query('userId', ''))));
        $login = trim((string) ($request->header('X-GM-Login', $request->query('login', ''))));
        $email = trim((string) ($request->header('X-GM-Email', $request->query('email', ''))));

        if ($userId === '' && $login === '' && $email === '') {
            return null;
        }

        return $this->resolveUser($request);
    }

    private function errorResponse(string $message, int $status, array $errors = [], ?string $code = null): JsonResponse
    {
        $payload = ['message' => $message];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
