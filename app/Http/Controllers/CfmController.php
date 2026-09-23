<?php

namespace App\Http\Controllers;

use App\Models\CfmOperation;
use App\Models\CfmCategory;
use App\Models\City;
use App\Services\CfmService;
use App\Services\ReportCsvExporter;
use App\Services\ReportXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CfmController extends Controller
{
    public function __construct(
        private CfmService $cfmService,
        private ReportCsvExporter $csvExporter,
        private ReportXlsxExporter $xlsxExporter,
    ) {}

    private function assertCanModifyCfm(): void
    {
        $user = auth()->user();
        if ($user->isDeveloper()) {
            return;
        }
        if ($user->hasAnyRole(['general_director', 'call_center', 'senior_dispatcher'])) {
            abort(403, 'У вас только право просмотра кассы');
        }
    }

    /** Правка уже открытой операции, в т.ч. после переоткрытия гендиректором. */
    private function assertCanEditOpenCfm(): void
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['developer', 'branch_head', 'regional_director', 'senior_manager', 'general_director'])) {
            return;
        }
        abort(403, 'Нет права править кассовую операцию');
    }

    /** Регдир / директор филиала / ст. менеджер / разработчик — спец. разделы кассы. */
    private function assertCanCreateDirectorPayment(): void
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['developer', 'branch_head', 'regional_director', 'senior_manager'])) {
            return;
        }
        abort(403, 'Нет доступа к этому разделу кассы');
    }

    /** Диспетчеры и городские роли — возврат клиенту. */
    private function assertCanCreateClientRefund(): void
    {
        $user = auth()->user();
        if ($user->hasAnyRole([
            'developer',
            'call_center',
            'senior_dispatcher',
            'branch_head',
            'regional_director',
            'senior_manager',
        ])) {
            return;
        }
        abort(403, 'Нет доступа к возвратам клиентам');
    }

    private function assertCategoryAllowedForUser(int $cfmCatId): void
    {
        $user = auth()->user();
        $allowedIds = $this->cfmService->getAvailableCategories($user)->pluck('cfm_cat_id')->all();
        if (! in_array($cfmCatId, $allowedIds, true)) {
            throw ValidationException::withMessages([
                'cfm_cat_id' => 'Эта статья кассы недоступна для вашей роли',
            ]);
        }
    }
    
    /**
     * Список операций
     */
    public function index(Request $request)
    {
        $this->cfmService->ensureDefaultCategories();

        $user = auth()->user();
        
        $query = CfmOperation::with(['category', 'city', 'createdBy', 'closedBy', 'relatedOrder', 'relatedCity'])
            ->orderByDesc('cfm_created_at');
        
        $cityIds = $user->cityIdsForScope();
        if ($cityIds !== null) {
            $query->whereIn('city_id', $cityIds);
        }
        
        // Фильтр: проведённые вами (по умолчанию выключен)
        // Параметр передаётся как '1' или '0'
        $closedByMe = $request->get('closed_by_me', '0');
        if ($closedByMe === '1') {
            $query->where('cfm_closed_by', $user->user_id);
        }
        
        // Фильтр: скрыть автоматические операции (по умолчанию выключен)
        // Автоматические операции имеют related_order_id IS NOT NULL
        // Ручные операции имеют related_order_id IS NULL
        $hideAuto = $request->get('hide_auto', '0');
        if ($hideAuto === '1') {
            $query->whereNull('related_order_id');
        }
        
        // Применение фильтров
        if ($request->filled('cfm_id')) {
            $query->where('cfm_id', $request->cfm_id);
        }
        if ($request->filled('city_id')) {
            $query->whereIn('city_id', (array) $request->city_id);
        }
        if ($request->filled('cfm_cat_id')) {
            $query->whereIn('cfm_cat_id', (array) $request->cfm_cat_id);
        }
        if ($request->filled('cfm_cat_group')) {
            $groups = array_values(array_filter((array) $request->cfm_cat_group));
            if ($groups !== []) {
                $query->whereHas('category', fn ($q) => $q->whereIn('cfm_cat_group', $groups));
            }
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if (!is_string($dateFrom) || trim($dateFrom) === '') {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (!is_string($dateTo) || trim($dateTo) === '') {
            $dateTo = now()->endOfMonth()->format('Y-m-d');
        }
        $query->where('cfm_created_at', '>=', $dateFrom);
        $query->where('cfm_created_at', '<=', $dateTo . ' 23:59:59');

        if ($request->filled('closed_from')) {
            $query->where('cfm_closed_at', '>=', $request->closed_from);
        }
        if ($request->filled('closed_to')) {
            $query->where('cfm_closed_at', '<=', $request->closed_to . ' 23:59:59');
        }

        if ($request->filled('status')) {
            if ($request->status === 'closed') {
                $query->whereNotNull('cfm_closed_at');
            } else {
                $query->whereNull('cfm_closed_at');
            }
        }
        
        // Сортировка
        $sortField = $request->get('sort', 'cfm_created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['cfm_id', 'cfm_created_at', 'cfm_closed_at', 'amount_cfm'];
        if (in_array($sortField, $allowedSorts)) {
            $query->reorder($sortField, $sortDir);
        }
        
        $operations = $query->paginate(50)->withQueryString();
        
        // Данные для фильтров
        $cities = $user->accessibleCities()->get();
        $categories = CfmCategory::where('is_visible', true)->orderBy('cfm_cat_name')->get();
        
        return view('cfm.index', compact('operations', 'cities', 'categories', 'dateFrom', 'dateTo'));
    }
    
    /**
     * Форма создания
     */
    public function create(Request $request)
    {
        $this->cfmService->ensureDefaultCategories();

        $user = auth()->user();
        $presetType = $request->get('type');

        if ($this->cfmService->isClientRefundType($presetType)) {
            $this->assertCanCreateClientRefund();

            $cities = $user->accessibleCities()->get();
            $presetCategory = CfmCategory::where('cfm_cat_name', $this->cfmService->clientRefundCategoryName())->firstOrFail();
            $orderId = $request->integer('order_id') ?: null;

            return view('cfm.create-refund', [
                'cities' => $cities,
                'presetCategory' => $presetCategory,
                'pageTitle' => 'Возврат клиенту',
                'prefillOrderId' => $orderId,
                'suggestUrl' => route('cfm.refund-suggest'),
            ]);
        }

        if ($this->cfmService->isDirectorPaymentType($presetType)) {
            $this->assertCanCreateDirectorPayment();

            $cities = $user->accessibleCities()->get();
            $categoryName = $this->cfmService->directorCategoryName($presetType);
            $presetCategory = CfmCategory::where('cfm_cat_name', $categoryName)->firstOrFail();
            $payers = $this->cfmService->directorPayers($user);
            $recipient = $this->cfmService->directorRecipient($presetType);
            $requiresPayer = in_array($presetType, ['party_payment', 'partner_expense', 'level_expense', 'incassation'], true);
            $requiresExternalRef = $presetType === 'level_expense';
            $typeLabels = [
                'party_payment' => 'Оплата партов',
                'partner_expense' => 'Расход партнерам',
                'level_expense' => 'Расход Уровень',
                'incassation' => 'Инкассация',
                'incas_commission' => 'Комиссия инкаса',
                'director_salary' => 'Выплата ЗП директора',
            ];

            if ($presetType === 'director_salary') {
                $salaryService = app(\App\Services\DirectorSalaryService::class);
                $calcId = $request->integer('salary_calculation_id') ?: null;
                $calc = $calcId
                    ? \App\Models\SalaryCalculation::with(['city', 'recipient'])->findOrFail($calcId)
                    : null;
                if ($calc) {
                    $salaryService->assertCanView($user, $calc);
                }
                $balances = $calc ? $salaryService->balances($calc) : null;

                return view('cfm.create-salary', [
                    'cities' => $cities,
                    'presetType' => $presetType,
                    'presetCategory' => $presetCategory,
                    'pageTitle' => $typeLabels[$presetType],
                    'calculation' => $calc,
                    'balances' => $balances,
                    'prefillCityId' => $request->integer('city_id') ?: $calc?->city_id,
                    'relatedUserId' => $request->integer('related_user_id') ?: $calc?->recipient_user_id,
                ]);
            }

            return view('cfm.create-director', [
                'cities' => $cities,
                'presetType' => $presetType,
                'presetCategory' => $presetCategory,
                'payers' => $payers,
                'recipient' => $recipient,
                'requiresPayer' => $requiresPayer,
                'requiresExternalRef' => $requiresExternalRef,
                'pageTitle' => $typeLabels[$presetType] ?? $categoryName,
            ]);
        }

        $this->assertCanModifyCfm();

        $cities = $user->accessibleCities()->get();

        // Категории с учётом видимости по ролям
        $categories = $this->cfmService->getAvailableCategories($user);

        $presetCategory = null;
        if ($presetType) {
            $presetCategories = $this->cfmService->getCategoriesByType($presetType);
            $presetCategory = $presetCategories->first();
        }

        $directorSalaryCategoryId = $categories
            ->firstWhere('cfm_cat_name', \App\Services\DirectorSalaryService::PAYMENT_CATEGORY)
            ?->cfm_cat_id;

        return view('cfm.create', compact(
            'cities',
            'categories',
            'presetType',
            'presetCategory',
            'directorSalaryCategoryId',
        ));
    }

    /**
     * Подсказка разбивки возврата по заказу (AJAX).
     */
    public function refundSuggest(Request $request)
    {
        $this->assertCanCreateClientRefund();

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,order_id',
            'total' => 'nullable|integer|min:0',
        ]);

        $order = \App\Models\Order::with(['master', 'address.city'])->findOrFail($validated['order_id']);
        $user = auth()->user();
        if (! $user->hasRole('developer') && ! $user->hasAccessToCity((int) ($order->address?->city_id ?? 0))) {
            abort(403, 'Нет доступа к заказу этого города');
        }

        $total = (int) ($validated['total'] ?? max(0, (int) $order->amount_paid - (int) $order->amount_comp));

        return response()->json($this->cfmService->suggestClientRefundSplit($order, $total));
    }

    /**
     * Сохранение операции
     */
    public function store(Request $request)
    {
        $presetType = $request->input('director_type');

        if ($this->cfmService->isClientRefundType($presetType)) {
            $this->assertCanCreateClientRefund();

            $validated = $request->validate([
                'related_order_id' => 'required|integer|exists:orders,order_id',
                'city_id' => 'required|exists:cities,city_id',
                'amount_from_cash' => 'required|integer|min:0',
                'amount_from_master' => 'required|integer|min:0',
                'cfm_adds' => 'required|string|max:1000',
                'documents' => 'nullable|array',
                'documents.*' => [
                    'file',
                    'max:10240',
                    'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,odt,txt,csv',
                ],
            ], [
                'related_order_id.required' => 'Укажите номер заказа',
                'amount_from_cash.required' => 'Укажите сумму с кассы',
                'amount_from_master.required' => 'Укажите сумму с мастера',
                'cfm_adds.required' => 'Укажите описание операции',
                'documents.*.max' => 'Размер каждого файла не должен превышать 10 МБ',
                'documents.*.mimes' => 'Неподдерживаемый формат файла. Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv',
            ]);

            if ((int) $validated['amount_from_cash'] + (int) $validated['amount_from_master'] < 1) {
                throw ValidationException::withMessages([
                    'amount_from_cash' => 'Сумма возврата должна быть больше нуля (касса и/или мастер)',
                ]);
            }

            $order = \App\Models\Order::with(['master', 'address'])->findOrFail($validated['related_order_id']);
            $user = auth()->user();
            $orderCityId = (int) ($order->address?->city_id ?? 0);
            if (! $user->hasRole('developer') && ! $user->hasAccessToCity($orderCityId)) {
                abort(403, 'Нет доступа к заказу этого города');
            }
            if ((int) $validated['city_id'] !== $orderCityId && $orderCityId > 0) {
                // Город кассы берём из заказа — защита от ошибки
                $validated['city_id'] = $orderCityId;
            }
            if ($orderCityId < 1) {
                throw ValidationException::withMessages([
                    'related_order_id' => 'У заказа не указан город — возврат невозможен',
                ]);
            }

            $documentsRaw = $request->file('documents', []);
            $documents = is_array($documentsRaw) ? $documentsRaw : array_filter([$documentsRaw]);

            try {
                $operation = $this->cfmService->createClientRefund([
                    'city_id' => $validated['city_id'],
                    'related_order_id' => $validated['related_order_id'],
                    'amount_from_cash' => (int) $validated['amount_from_cash'],
                    'amount_from_master' => (int) $validated['amount_from_master'],
                    'cfm_adds' => $validated['cfm_adds'] ?? null,
                    'related_user_id' => $order->master_id,
                    'master_name' => $order->master?->user_name,
                ], auth()->id(), $documents);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            return redirect()->route('cfm.show', $operation->cfm_id)
                ->with('success', 'Возврат проведён: касса −'.number_format((int) $validated['amount_from_cash'], 0, ',', ' ').' ₽');
        }

        if ($this->cfmService->isDirectorPaymentType($presetType)) {
            $this->assertCanCreateDirectorPayment();

            $categoryName = $this->cfmService->directorCategoryName($presetType);
            $category = CfmCategory::where('cfm_cat_name', $categoryName)->firstOrFail();
            $requiresPayer = in_array($presetType, ['party_payment', 'partner_expense', 'level_expense', 'incassation'], true);
            $requiresExternalRef = $presetType === 'level_expense';
            $recipient = $this->cfmService->directorRecipient($presetType);
            $payers = $this->cfmService->directorPayers(auth()->user());

            $rules = [
                'city_id' => 'required|exists:cities,city_id',
                'amount_cfm' => 'required|integer|min:1',
                'cfm_adds' => 'required|string|max:1000',
                'documents' => 'nullable|array',
                'documents.*' => [
                    'file',
                    'max:10240',
                    'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,odt,txt,csv',
                ],
            ];

            if ($presetType === 'director_salary') {
                $rules['salary_calculation_id'] = 'required|exists:salary_calculations,salary_calculation_id';
                $rules['related_user_id'] = 'nullable|exists:users,user_id';
                $rules['auto_close'] = 'nullable|boolean';
            }

            if ($requiresPayer) {
                $rules['cfm_payer'] = ['required', 'string', 'max:255', \Illuminate\Validation\Rule::in($payers)];
            }
            if ($requiresExternalRef) {
                $rules['external_cfm_ref'] = 'required|string|max:64';
            }

            $validated = $request->validate($rules, [
                'cfm_adds.required' => 'Укажите описание операции',
                'cfm_payer.required' => 'Выберите плательщика',
                'external_cfm_ref.required' => 'Укажите номер кассовой операции из базы Уровня',
                'amount_cfm.integer' => 'Укажите сумму в целых рублях',
                'salary_calculation_id.required' => 'Укажите расчётный месяц зарплаты',
                'documents.*.max' => 'Размер каждого файла не должен превышать 10 МБ',
                'documents.*.mimes' => 'Неподдерживаемый формат файла. Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv',
            ]);

            $validated['cfm_cat_id'] = $category->cfm_cat_id;
            $validated['cfm_recipient'] = $recipient;
            if (! $requiresPayer) {
                $validated['cfm_payer'] = null;
            }
            if (! $requiresExternalRef) {
                $validated['external_cfm_ref'] = null;
            }

            if ($presetType === 'director_salary') {
                $calc = \App\Models\SalaryCalculation::findOrFail((int) $validated['salary_calculation_id']);
                app(\App\Services\DirectorSalaryService::class)->assertCanView(auth()->user(), $calc);
                if ((int) $validated['city_id'] !== (int) $calc->city_id) {
                    throw ValidationException::withMessages([
                        'city_id' => 'Город должен совпадать с расчётом ЗП',
                    ]);
                }
                $validated['related_user_id'] = $validated['related_user_id'] ?? $calc->recipient_user_id;
                if (empty($validated['cfm_adds'])) {
                    $validated['cfm_adds'] = 'Дир ЗП '.$calc->period_month->format('m.Y');
                }
            }

            $documentsRaw = $request->file('documents', []);
            $documents = is_array($documentsRaw) ? $documentsRaw : array_filter([$documentsRaw]);

            try {
                $operation = $this->cfmService->create($validated, auth()->id(), $documents);
                if ($presetType === 'director_salary' && $request->boolean('auto_close', true)) {
                    $operation = $this->cfmService->close($operation->fresh(), auth()->id());
                }
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            return redirect()->route('cfm.show', $operation->cfm_id)
                ->with('success', $presetType === 'director_salary' && $operation->cfm_closed_at
                    ? 'Выплата ЗП проведена, операция №'.$operation->cfm_id
                    : 'Операция создана');
        }

        $this->assertCanModifyCfm();
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,city_id',
            'cfm_cat_id' => 'required|exists:cfm_categories,cfm_cat_id',
            'amount_cfm' => 'required|integer|min:1',
            'cfm_adds' => 'required|string|max:1000',
            'cfm_subcat' => 'nullable|string|max:100',
            'target_city_id' => 'nullable|exists:cities,city_id|different:city_id',
            'documents' => 'nullable|array',
            'documents.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,odt,txt,csv',
            ],
        ], [
            'cfm_adds.required' => 'Укажите описание операции',
            'documents.*.max' => 'Размер каждого файла не должен превышать 10 МБ',
            'documents.*.mimes' => 'Неподдерживаемый формат файла. Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv',
        ]);

        $this->assertCategoryAllowedForUser((int) $validated['cfm_cat_id']);

        $validated['is_transfer'] = $request->boolean('is_transfer');

        $documentsRaw = $request->file('documents', []);
        $documents = is_array($documentsRaw) ? $documentsRaw : array_filter([$documentsRaw]);

        try {
            $operation = $this->cfmService->create($validated, auth()->id(), $documents);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('cfm.show', $operation->cfm_id)
            ->with('success', 'Операция создана');
    }
    
    /**
     * Просмотр/редактирование операции
     */
    public function show(int $cfm_id)
    {
        $user = auth()->user();
        
        $operation = CfmOperation::with(['category', 'city.parentCity', 'createdBy', 'closedBy', 'relatedOrder', 'relatedCity', 'documents'])
            ->findOrFail($cfm_id);
        
        // Проверка доступа к городу
        if (!$user->hasRole('developer') && !$user->hasAccessToCity($operation->city_id)) {
            abort(403);
        }
        
        $cities = $user->accessibleCities()->get();

        $cfmDisplayTimezone = $operation->city->city_timezone ?? config('app.timezone');
        $cfmCreatedAtForCity = $operation->cfm_created_at->clone()->timezone($cfmDisplayTimezone);
        $cfmClosedAtForCity = $operation->cfm_closed_at?->clone()->timezone($cfmDisplayTimezone);

        $canReopen = $this->cfmService->userCanReopen($user, $operation);
        $canAttachDocuments = $this->cfmService->userCanAttachDocuments($user);
        $canDeleteDocuments = $this->cfmService->userCanDeleteDocuments($user, $operation);
        $canEditCfm = ! $operation->cfm_closed_at
            && $user->hasAnyRole(['developer', 'branch_head', 'regional_director', 'senior_manager', 'general_director']);
        $isClientRefund = $this->cfmService->isClientRefundOperation($operation);

        $salaryPayoutHint = null;
        if (app(\App\Services\DirectorSalaryService::class)->isPaymentCategory($operation->category)) {
            try {
                $salaryPayoutHint = app(\App\Services\DirectorSalaryService::class)
                    ->payoutCapSnapshot((int) $operation->city_id, $user, null, (int) $operation->cfm_id);
            } catch (\Throwable) {
                $salaryPayoutHint = null;
            }
        }

        $overdraftLimit = max(0, (int) config('cfm.overdraft_limit', 2000));
        $cityCashBalance = $this->cfmService->closedBalanceForCity((int) $operation->city_id);
        $cityCashKind = $operation->city?->isSatellite() ? 'спутник' : 'город';
        $cityCashName = $operation->city?->displayName() ?: ('город #'.$operation->city_id);
        $cityCashProjected = $cityCashBalance - (int) $operation->amount_cfm;
        $cityCashWouldBreach = $operation->category?->cfm_cat_group === 'outflows'
            && (int) $operation->amount_cfm > 0
            && $cityCashProjected < -$overdraftLimit;

        return view('cfm.show', compact(
            'operation',
            'cities',
            'cfmCreatedAtForCity',
            'cfmClosedAtForCity',
            'canReopen',
            'canAttachDocuments',
            'canDeleteDocuments',
            'canEditCfm',
            'isClientRefund',
            'salaryPayoutHint',
            'overdraftLimit',
            'cityCashBalance',
            'cityCashKind',
            'cityCashName',
            'cityCashProjected',
            'cityCashWouldBreach',
        ));
    }
    
    /**
     * Обновление операции
     */
    public function update(Request $request, int $cfm_id)
    {
        $this->assertCanEditOpenCfm();
        $operation = CfmOperation::with('category')->findOrFail($cfm_id);
        $user = auth()->user();
        if (! $user->hasRole('developer') && ! $user->hasAccessToCity((int) $operation->city_id)) {
            abort(403);
        }

        // Проведённые операции редактировать нельзя
        if ($operation->cfm_closed_at) {
            return back()->with('error', 'Невозможно редактировать проведённую операцию');
        }
        
        // Операции "Поступление с Заказов" редактировать нельзя
        if ($operation->category->cfm_cat_name === 'Поступление с Заказов') {
            return back()->with('error', 'Операции "Поступление с Заказов" не подлежат редактированию');
        }

        if ($this->cfmService->isClientRefundOperation($operation)) {
            $validated = $request->validate([
                'amount_from_cash' => 'required|integer|min:0',
                'amount_from_master' => 'required|integer|min:0',
                'cfm_adds' => 'required|string|max:1000',
                'city_id' => 'required|exists:cities,city_id',
            ], [
                'amount_from_cash.required' => 'Укажите сумму с кассы',
                'amount_from_master.required' => 'Укажите сумму с мастера',
                'cfm_adds.required' => 'Укажите описание операции',
            ]);
            if ((int) $validated['amount_from_cash'] + (int) $validated['amount_from_master'] < 1) {
                throw ValidationException::withMessages([
                    'amount_from_cash' => 'Сумма возврата должна быть больше нуля (касса и/или мастер)',
                ]);
            }
            $payload = [
                'amount_cfm' => (int) $validated['amount_from_cash'],
                'amount_from_master' => (int) $validated['amount_from_master'],
                'cfm_adds' => $validated['cfm_adds'],
                'city_id' => $validated['city_id'],
            ];
        } else {
            $validated = $request->validate([
                'amount_cfm' => 'required|integer|min:1',
                'cfm_adds' => 'required|string|max:1000',
                'city_id' => 'required|exists:cities,city_id',
            ], [
                'cfm_adds.required' => 'Укажите описание операции',
            ]);
            $payload = $validated;
        }
        
        try {
            $this->cfmService->update($operation, $payload);
            return back()->with('success', 'Операция обновлена');
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Нельзя сохранить операцию';

            return back()->with('error', $msg)->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    
    /**
     * Проведение операции
     */
    public function close(int $cfm_id)
    {
        $this->assertCanEditOpenCfm();

        $operation = CfmOperation::findOrFail($cfm_id);
        $user = auth()->user();
        if (! $user->hasRole('developer') && ! $user->hasAccessToCity((int) $operation->city_id)) {
            abort(403);
        }
        
        if ($operation->cfm_closed_at) {
            return back()->with('error', 'Операция уже проведена');
        }
        
        try {
            $this->cfmService->close($operation, auth()->id());
            return back()->with('success', 'Операция проведена');
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?: 'Нельзя провести операцию';

            return back()->with('error', $msg)->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    
    /**
     * Переоткрытие операции (гендиректор и разработчик, только текущий месяц)
     */
    public function reopen(int $cfm_id)
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(CfmService::REOPEN_ROLES)) {
            abort(403, 'Переоткрыть операцию могут только генеральный директор и разработчик');
        }

        $operation = CfmOperation::findOrFail($cfm_id);

        if (! $user->hasRole('developer') && ! $user->hasAccessToCity($operation->city_id)) {
            abort(403);
        }

        if (! $operation->cfm_closed_at) {
            return back()->with('error', 'Операция не проведена');
        }

        if (! $this->cfmService->isClosedInCurrentMonth($operation)) {
            return back()->with('error', 'Переоткрыть можно только операции текущего месяца');
        }

        try {
            $this->cfmService->reopen($operation);
            return back()->with('success', 'Операция переоткрыта');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    
    /**
     * Сводный отчёт
     */
    public function summary(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\View\View
    {
        $user = auth()->user();
        
        // Получаем все активные города с доступом
        $allCities = $user->accessibleCities()->get();
        
        // Фильтруем: обычные города и УК
        $regularCities = $allCities->where('city_type', 'city');
        $mcCity = $allCities->firstWhere('city_type', 'mc');
        
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        if (! is_string($dateFrom) || trim($dateFrom) === '') {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! is_string($dateTo) || trim($dateTo) === '') {
            $dateTo = now()->format('Y-m-d');
        }
        
        // Отчёт по обычным городам
        $cityIds = $regularCities->pluck('city_id')->toArray();
        $report = $this->cfmService->getSummaryReport($cityIds, $dateFrom, $dateTo, $regularCities);
        
        // Остаток УК
        $mcBalance = null;
        if ($mcCity) {
            $mcReport = $this->cfmService->getSummaryReport([$mcCity->city_id], $dateFrom, $dateTo);
            $mcBalance = $mcReport['cities'][$mcCity->city_id]['balance'] ?? 0;
        }

        $export = $request->get('export');
        if (in_array($export, ['xlsx', 'csv'], true)) {
            return $this->exportSummary($report, $dateFrom, $dateTo, $export);
        }

        return view('cfm.summary', compact('report', 'dateFrom', 'dateTo', 'mcCity', 'mcBalance'));
    }

    /**
     * @param  array{cities: array<int, array<string, mixed>>, totals: array<string, mixed>}  $report
     */
    private function exportSummary(array $report, ?string $dateFrom, ?string $dateTo, string $format): \Symfony\Component\HttpFoundation\Response
    {
        $headers = [
            'Город',
            'Приход с Заказов',
            'Зарплата промоутеров',
            'Инкас',
            'Расход объявления',
            'Общий расход',
            'Остаток',
        ];

        $sort = request('sort', 'city_name');
        $dir = request('dir', 'asc');
        $sortedCities = collect($report['cities'])->sortBy(function ($city) use ($sort) {
            return $city[$sort] ?? 0;
        }, SORT_REGULAR, $dir === 'desc')->values();

        $rows = [];
        $totals = $report['totals'] ?? [];
        $rows[] = [
            'ИТОГО',
            (int) ($totals['order_income'] ?? 0),
            (int) ($totals['promo_salary'] ?? 0),
            (int) ($totals['incas'] ?? 0),
            (int) ($totals['ads_expense'] ?? 0),
            (int) ($totals['total_outflows'] ?? 0),
            (int) ($totals['balance'] ?? 0),
        ];

        foreach ($sortedCities as $cityData) {
            $rows[] = [
                $cityData['city_name'],
                (int) ($cityData['order_income'] ?? 0),
                (int) ($cityData['promo_salary'] ?? 0),
                (int) ($cityData['incas'] ?? 0),
                (int) ($cityData['ads_expense'] ?? 0),
                (int) ($cityData['total_outflows'] ?? 0),
                (int) ($cityData['balance'] ?? 0),
            ];
        }

        $from = $dateFrom ?: 'all';
        $to = $dateTo ?: 'all';

        if ($format === 'xlsx') {
            return $this->xlsxExporter->download('report-cfm-summary.xlsx', $headers, $rows, [
                'sheet_name' => 'Сводка ДДС',
            ]);
        }

        return $this->csvExporter->download(
            "report-cfm-summary_{$from}_{$to}.csv",
            $headers,
            $rows,
        );
    }
    
    /**
     * Отчёт по городу
     */
    public function cityReport(Request $request, int $city_id)
    {
        $city = City::findOrFail($city_id);
        
        // Проверка доступа
        $user = auth()->user();
        if ($city->city_type === 'mc') {
            if (!$user->hasAnyRole(['developer', 'general_director'])) {
                abort(403);
            }
        } elseif (!$user->hasRole('developer') && !$user->hasAccessToCity($city_id)) {
            abort(403);
        }
        
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        $report = $this->cfmService->getCityReport($city_id, $dateFrom, $dateTo);
        
        return view('cfm.city-report', compact('city', 'report', 'dateFrom', 'dateTo'));
    }

    /**
     * Отчёт «Оплата заявок» по городам.
     */
    public function orderPayments(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['developer', 'general_director', 'senior_dispatcher'])) {
            abort(403);
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        if (! is_string($dateFrom) || trim($dateFrom) === '') {
            $dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if (! is_string($dateTo) || trim($dateTo) === '') {
            $dateTo = now()->format('Y-m-d');
        }
        $cityId = $request->integer('city_id') ?: null;

        $cities = $user->hasRole('developer')
            ? City::query()->where('city_type', 'city')->orderBy('city_name')->get()
            : $user->accessibleCities()->where('city_type', 'city')->orderBy('city_name')->get();

        $reports = [];
        $targetCities = $cityId
            ? $cities->where('city_id', $cityId)
            : $cities;

        foreach ($targetCities as $city) {
            if (! $user->hasRole('developer') && ! $user->hasAccessToCity((int) $city->city_id)) {
                continue;
            }
            $reports[] = [
                'city' => $city,
                'report' => $this->cfmService->getOrderPaymentsReport((int) $city->city_id, $dateFrom, $dateTo),
            ];
        }

        return view('cfm.order-payments', compact('reports', 'cities', 'dateFrom', 'dateTo', 'cityId'));
    }
    
    /**
     * Редактор статей ДДС (только для разработчика)
     */
    public function editor()
    {
        $categories = CfmCategory::withCount('operations')
            ->orderBy('cfm_cat_group')
            ->orderBy('cfm_cat_activities')
            ->orderBy('cfm_cat_name')
            ->get();
        
        return view('cfm.editor', compact('categories'));
    }
    
    /**
     * Обновление категории (только для разработчика)
     */
    public function updateCategory(Request $request, int $cfm_cat_id)
    {
        $category = CfmCategory::findOrFail($cfm_cat_id);
        
        $validated = $request->validate([
            'cfm_cat_name' => 'required|string|max:100',
            'cfm_cat_group' => 'required|in:inflows,outflows',
            'cfm_cat_activities' => 'required|in:operating,investing,financing,technical',
            'cfm_cat_adds' => 'nullable|string|max:500',
            'subcategories' => 'nullable|string|max:500',
            'available_for_city' => 'boolean',
            'available_for_mc' => 'boolean',
            'available_for_df' => 'boolean',
            'is_visible' => 'boolean',
        ]);
        
        // Конвертируем подкатегории в JSON
        if (!empty($validated['subcategories'])) {
            $subcats = array_map('trim', explode(',', $validated['subcategories']));
            $validated['subcategories'] = json_encode(array_filter($subcats));
        } else {
            $validated['subcategories'] = null;
        }
        
        // Обработка чекбоксов
        $validated['available_for_city'] = $request->has('available_for_city');
        $validated['available_for_mc'] = $request->has('available_for_mc');
        $validated['available_for_df'] = $request->has('available_for_df');
        $validated['is_visible'] = $request->has('is_visible');
        
        $category->update($validated);
        
        return back()->with('success', 'Статья обновлена');
    }
    
    /**
     * Создание новой категории (только для разработчика)
     */
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'cfm_cat_name' => 'required|string|max:100|unique:cfm_categories,cfm_cat_name',
            'cfm_cat_group' => 'required|in:inflows,outflows',
            'cfm_cat_activities' => 'required|in:operating,investing,financing,technical',
            'cfm_cat_adds' => 'nullable|string|max:500',
            'subcategories' => 'nullable|string|max:500',
            'available_for_city' => 'boolean',
            'available_for_mc' => 'boolean',
            'available_for_df' => 'boolean',
        ]);
        
        // Конвертируем подкатегории в JSON
        if (!empty($validated['subcategories'])) {
            $subcats = array_map('trim', explode(',', $validated['subcategories']));
            $validated['subcategories'] = json_encode(array_filter($subcats));
        } else {
            $validated['subcategories'] = null;
        }
        
        // Обработка чекбоксов
        $validated['available_for_city'] = $request->has('available_for_city');
        $validated['available_for_mc'] = $request->has('available_for_mc');
        $validated['available_for_df'] = $request->has('available_for_df');
        $validated['is_visible'] = true;
        $validated['is_auto'] = false;
        
        CfmCategory::create($validated);
        
        return back()->with('success', 'Статья создана');
    }
    
    /**
     * Удаление категории (только для разработчика)
     */
    public function deleteCategory(int $cfm_cat_id)
    {
        $category = CfmCategory::withCount('operations')->findOrFail($cfm_cat_id);
        
        // Нельзя удалять автоматические категории
        if ($category->is_auto) {
            return back()->with('error', 'Нельзя удалить автоматическую статью');
        }
        
        // Нельзя удалять категории с операциями
        if ($category->operations_count > 0) {
            return back()->with('error', "Нельзя удалить статью «{$category->cfm_cat_name}» — есть {$category->operations_count} операций");
        }
        
        $category->delete();
        
        return back()->with('success', 'Статья удалена');
    }
}
