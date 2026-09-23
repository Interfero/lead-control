<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\City;
use App\Models\Comment;
use App\Models\Document;
use App\Services\GmUserSyncService;
use App\Services\HrService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class HrController extends Controller
{
    /** Корпоративный суффикс email сотрудников (после @). */
    public const EMPLOYEE_EMAIL_SUFFIX = '@lc.ru';

    /** Ранее использовавшийся домен — только для снятия суффикса при отображении и нормализации ввода. */
    private const LEGACY_EMPLOYEE_EMAIL_SUFFIX = '@level.ion';

    public function __construct(
        private HrService $hrService,
        private GmUserSyncService $gmUserSyncService
    ) {}
    
    /**
     * Список сотрудников
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $this->assertCanAccessHrList($user);
        
        $filters = $request->only(['search', 'search_phone', 'search_note', 'search_id']);
        $filters['role'] = array_values(array_filter((array) $request->input('role', [])));
        $cid = $request->input('city_id', []);
        $filters['city_id'] = is_array($cid) ? array_values(array_filter($cid)) : (filled($cid) ? [$cid] : []);
        // Явно из query: чекбокс без value в некоторых браузерах шлёт "on"
        $filters['show_fired'] = $request->has('show_fired')
            && filter_var($request->input('show_fired'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
        $filters['show_blacklisted'] = $request->has('show_blacklisted')
            && filter_var($request->input('show_blacklisted'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
        $employees = $this->hrService->getEmployeesForUser($user, $filters);
        
        // Получаем только видимые роли (исключаем скрытые)
        $hiddenRoles = $this->hrService->getHiddenRolesForUser($user);
        $roles = Role::where('is_active', true)
            ->whereNotIn('role_code', $hiddenRoles)
            ->orderBy('role_name')
            ->get();

        $cities = $user->hasAnyRole(['developer', 'general_director'])
            || $this->hrService->isCallCenterSupervisor($user)
            ? City::where('is_active', true)->orderBy('city_name')->get()
            : $user->cities;
        
        return view('hr.index', compact('employees', 'roles', 'cities', 'filters'));
    }
    
    /**
     * Форма создания сотрудника
     */
    public function create()
    {
        $user = auth()->user();
        $this->hrService->ensureBuiltinRoles();

        $hiddenRoles = $this->hrService->getHiddenRolesForUser($user);
        $roles = Role::where('is_active', true)
            ->whereNotIn('role_code', $hiddenRoles)
            ->orderBy('role_name')
            ->get();

        $creatableRoleCodes = $this->hrService->getCreatableRolesForUser($user);
        $creatableRoles = $user->hasAnyRole(['developer', 'general_director'])
            ? $roles
            : $roles->filter(fn($r) => in_array($r->role_code, $creatableRoleCodes))->values();

        $cities = $user->hasAnyRole(['developer', 'general_director'])
            ? City::where('is_active', true)->orderBy('city_name')->get()
            : $user->cities;

        $canSetAccessAllCities = $user->hasAnyRole(['developer', 'general_director']);

        return view('hr.create', compact('creatableRoles', 'cities', 'canSetAccessAllCities'));
    }

    /**
     * Форма редактирования / карточка сотрудника
     */
    public function edit(int $user_id)
    {
        $user = auth()->user();
        $this->hrService->ensureBuiltinRoles();
        $employee = User::with(['roles', 'cities', 'documents.verifier', 'comments.author'])->findOrFail($user_id);
        $this->assertCanViewEmployee($user, $employee);

        $hiddenRoles = $this->hrService->getHiddenRolesForUser($user);
        $roles = Role::where('is_active', true)
            ->whereNotIn('role_code', $hiddenRoles)
            ->orderBy('role_name')
            ->get();

        $creatableRoleCodes = $this->hrService->getCreatableRolesForUser($user);
        $creatableRoles = $user->hasAnyRole(['developer', 'general_director'])
            ? $roles
            : $roles->filter(fn($r) => in_array($r->role_code, $creatableRoleCodes))->values();

        $cities = $user->hasAnyRole(['developer', 'general_director'])
            ? City::where('is_active', true)->orderBy('city_name')->get()
            : $user->cities;

        $employeeCityIds = $employee->cities->pluck('city_id')->toArray();
        $documents = $employee->documents;
        $comments = $employee->comments->sortByDesc('created_at');

        $isHrReadOnly = ($employee->hasRole('developer')
                && ! $user->hasAnyRole(['developer', 'general_director']))
            || $this->hrService->isCallCenterSupervisor($user);
        $isHrRoleLocked = false;

        $canSetAccessAllCities = $user->hasAnyRole(['developer', 'general_director']);

        return view('hr.edit', compact('employee', 'creatableRoles', 'cities', 'employeeCityIds', 'documents', 'comments', 'isHrReadOnly', 'isHrRoleLocked', 'canSetAccessAllCities'));
    }
    
    /**
     * Данные сотрудника (AJAX)
     */
    public function show(int $user_id)
    {
        $viewer = auth()->user();
        $employee = User::with(['roles', 'cities', 'documents.verifier', 'blacklistedByUser', 'comments.author'])->findOrFail($user_id);
        $this->assertCanViewEmployee($viewer, $employee);
        
        return response()->json([
            'user_id' => $employee->user_id,
            'user_name' => $employee->user_name,
            'email' => $employee->email,
            'email_local' => self::employeeEmailLocalPart($employee->email),
            'email_suffix' => self::EMPLOYEE_EMAIL_SUFFIX,
            'user_phone' => $employee->user_phone,
            'user_passport' => $employee->user_passport,
            'user_inn' => $employee->user_inn,
            'user_birth_date' => $employee->user_birth_date?->format('Y-m-d'),
            'user_hired_at' => $employee->user_hired_at?->format('Y-m-d'),
            'user_fired_at' => $employee->user_fired_at?->format('Y-m-d'),
            'user_note' => $employee->user_note,
            'is_active' => $employee->is_active,
            'is_blacklisted' => $employee->is_blacklisted,
            'blacklist_reason' => $employee->blacklist_reason,
            'blacklisted_at' => $employee->blacklisted_at?->format('d.m.Y'),
            'role_id' => $employee->roles->first()?->role_id,
            'city_ids' => $employee->cities->pluck('city_id')->toArray(),
            'access_all_cities' => (bool) $employee->access_all_cities,
            'documents' => $employee->documents->map(function($d) {
                return [
                    'document_id' => $d->document_id,
                    'file_name' => $d->file_name,
                    'human_size' => $d->human_size ?? '0 B',
                    'file_mime' => $d->file_mime ?? 'application/octet-stream',
                    'is_verified' => (bool) $d->is_verified,
                    'verified_by_name' => $d->verifier?->user_name,
                    'verified_at' => $d->verified_at?->format('d.m.Y H:i'),
                ];
            })->values()->toArray(),
            'comments' => $employee->comments->sortByDesc('created_at')->map(function($c) {
                return [
                    'comment_id' => $c->comment_id,
                    'comment_text' => $c->comment_text,
                    'author_name' => $c->author?->user_name ?? 'Неизвестно',
                    'created_at' => $c->created_at?->format('d.m.Y H:i'),
                ];
            })->values()->toArray(),
        ]);
    }

    /**
     * Создание сотрудника (при создании обязателен хотя бы один документ — передать files[] в запросе)
     */
    public function store(Request $request)
    {
        $this->mergeNormalizedEmployeeEmailFromInput($request, null);

        $validated = $request->validate([
            'user_name' => 'required|string|max:255',
            'email' => 'required|email:filter',
            'user_phone' => 'nullable|string|size:10',
            'user_passport' => 'nullable|string|max:20',
            'user_inn' => 'nullable|string|regex:/^\d{10}(\d{2})?$/',
            'role_id' => 'required|exists:roles,role_id',
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'exists:cities,city_id',
            'access_all_cities' => 'sometimes|boolean',
            'user_birth_date' => 'nullable|date',
            'user_note' => 'nullable|string|max:1000',
        ], [
            'email.email' => 'Логин email: только латиница, цифры и символы . _ + - (без пробелов и кириллицы).',
            'email.required' => 'Укажите логин email',
        ]);

        $targetRole = Role::findOrFail($validated['role_id']);
        $isDispatcherRole = in_array($targetRole->role_code, ['call_center', 'senior_dispatcher'], true);
        $canSetAllFlag = auth()->user()->hasAnyRole(['developer', 'general_director']);
        if ($isDispatcherRole) {
            $accessAllCities = true;
        } elseif ($canSetAllFlag) {
            $accessAllCities = $request->boolean('access_all_cities');
        } else {
            $accessAllCities = false;
        }
        if (! $accessAllCities) {
            if (empty($validated['city_ids']) || count($validated['city_ids']) < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Выберите хотя бы один город',
                ], 422);
            }
        }

        // Проверка чёрного списка перед созданием
        $blacklistMessage = $this->hrService->isBlacklistedForCreate($validated);
        if ($blacklistMessage !== null) {
            return response()->json([
                'success' => false,
                'message' => $blacklistMessage,
            ], 422);
        }

        // Проверка уникальности email (после проверки ЧС, чтобы ЧС-сообщение было приоритетнее)
        if (\App\Models\User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Сотрудник с таким email уже существует',
            ], 422);
        }

        // При создании обязателен хотя бы один документ
        $files = $request->file('files');
        if (!$files || (is_array($files) && count($files) === 0)) {
            return response()->json([
                'success' => false,
                'message' => 'При создании сотрудника необходимо загрузить хотя бы один документ',
            ], 422);
        }
        if (!is_array($files)) {
            $files = [$files];
        }

        // Проверяем, может ли текущий пользователь создать сотрудника с этой ролью
        if (! $this->hrService->canCreateWithRole(auth()->user(), $targetRole->role_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав для создания сотрудника с ролью «' . $targetRole->role_name . '»',
            ], 403);
        }

        $password = $this->hrService->generatePassword();
        $isMasterRole = in_array($targetRole->role_code, ['master', 'senior_master'], true);

        $user = User::create([
            'user_name' => $validated['user_name'],
            'email' => $validated['email'],
            'password' => $password, // cast hashed
            'must_change_password' => $isMasterRole,
            'password_set_at' => now(),
            'user_phone' => $validated['user_phone'] ?? null,
            'user_passport' => $validated['user_passport'] ?? null,
            'user_inn' => $validated['user_inn'] ?? null,
            'user_birth_date' => $validated['user_birth_date'] ?? null,
            'user_note' => $validated['user_note'] ?? null,
            'user_hired_at' => now(),
            'is_active' => true,
            'access_all_cities' => $accessAllCities,
        ]);

        $user->roles()->attach($validated['role_id']);
        if ($accessAllCities) {
            $user->cities()->sync([]);
        } else {
            $user->cities()->sync($validated['city_ids']);
        }

        // Сохранение загруженных документов
        $allowedMimes = [
            'image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.text', 'text/plain', 'text/csv', 'application/csv',
        ];
        $folder = "documents/hr/{$user->user_id}";
        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }
            $mime = $file->getMimeType();
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($mime, $allowedMimes) && !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'txt', 'csv'])) {
                continue;
            }
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs($folder, $fileName, 'local');
            $doc = new Document([
                'documentable_type' => User::class,
                'documentable_id' => $user->user_id,
                'document_category' => 'general',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_mime' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $doc->uploaded_by = auth()->id();
            $doc->save();
        }

        $gmSync = $this->syncEmployeeWithGm($user->fresh(['roles', 'cities']));

        return response()->json([
            'success' => true,
            'message' => "Сотрудник создан. Пароль: {$password}",
            'password' => $password,
            'gm_sync' => $gmSync,
            'user_id' => $user->user_id,
        ]);
    }
    
    /**
     * Обновление сотрудника
     */
    public function update(Request $request, int $user_id)
    {
        $employee = User::with('roles')->findOrFail($user_id);
        $this->ensureCanEditEmployeeAccount($employee);

        $this->mergeNormalizedEmployeeEmailFromInput($request, $employee->email);

        $authUser = auth()->user();
        $isRoleLocked = $this->isGeneralDirectorRoleLocked($employee);

        $emailRules = ['nullable', 'email:filter'];
        if ($request->filled('email')) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($user_id, 'user_id');
        }

        $emailMessages = [
            'email.email' => 'Логин email: только латиница, цифры и символы . _ + - (без пробелов и кириллицы).',
            'email.unique' => 'Такой email уже занят другим сотрудником.',
        ];

        if ($isRoleLocked) {
            $validated = $request->validate([
                'user_name' => 'required|string|max:255',
                'email' => $emailRules,
                'user_phone' => 'nullable|string|size:10',
                'user_passport' => 'nullable|string|max:20',
                'user_inn' => 'nullable|string|regex:/^\d{10}(\d{2})?$/',
                'user_birth_date' => 'nullable|date',
                'user_note' => 'nullable|string|max:1000',
                'is_active' => 'boolean',
                'city_ids' => 'nullable|array',
                'city_ids.*' => 'exists:cities,city_id',
                'access_all_cities' => 'sometimes|boolean',
            ], $emailMessages);

            $canSetAllFlag = $authUser->hasAnyRole(['developer', 'general_director']);
            if ($employee->hasAnyRole(['call_center', 'senior_dispatcher'])) {
                $accessAllCities = true;
            } elseif ($canSetAllFlag) {
                $accessAllCities = $request->boolean('access_all_cities');
            } else {
                $accessAllCities = (bool) $employee->access_all_cities;
            }

            if (! $accessAllCities) {
                $cityIds = $validated['city_ids'] ?? [];
                if (count($cityIds) < 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Выберите хотя бы один город',
                    ], 422);
                }
            }

            $employee->update([
                'user_name' => $validated['user_name'],
                'email' => $validated['email'] ?? null,
                'user_phone' => $validated['user_phone'] ?? null,
                'user_passport' => $validated['user_passport'] ?? null,
                'user_inn' => $validated['user_inn'] ?? null,
                'user_birth_date' => $validated['user_birth_date'] ?? null,
                'user_note' => $validated['user_note'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'access_all_cities' => $accessAllCities,
            ]);

            if ($accessAllCities) {
                $employee->cities()->sync([]);
            } else {
                $employee->cities()->sync($validated['city_ids']);
            }

            return response()->json(['success' => true, 'message' => 'Сохранено']);
        }

        $validated = $request->validate([
            'user_name' => 'required|string|max:255',
            'email' => $emailRules,
            'user_phone' => 'nullable|string|size:10',
            'user_passport' => 'nullable|string|max:20',
            'user_inn' => 'nullable|string|regex:/^\d{10}(\d{2})?$/',
            'role_id' => 'required|exists:roles,role_id',
            'city_ids' => 'nullable|array',
            'city_ids.*' => 'exists:cities,city_id',
            'access_all_cities' => 'sometimes|boolean',
            'user_birth_date' => 'nullable|date',
            'user_note' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ], $emailMessages);

        $targetRole = Role::findOrFail($validated['role_id']);
        $isDispatcherRole = in_array($targetRole->role_code, ['call_center', 'senior_dispatcher'], true);
        $canSetAllFlag = auth()->user()->hasAnyRole(['developer', 'general_director']);
        $hadDispatcherRole = $employee->hasAnyRole(['call_center', 'senior_dispatcher']);

        if ($isDispatcherRole) {
            $accessAllCities = true;
        } elseif ($canSetAllFlag) {
            $accessAllCities = $request->boolean('access_all_cities');
        } elseif ($hadDispatcherRole && ! $isDispatcherRole) {
            $accessAllCities = false;
        } else {
            $accessAllCities = (bool) $employee->access_all_cities;
        }

        if (! $accessAllCities) {
            $cityIds = $validated['city_ids'] ?? [];
            if (count($cityIds) < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Выберите хотя бы один город',
                ], 422);
            }
        }

        $employee->update([
            'user_name' => $validated['user_name'],
            'email' => $validated['email'] ?? null,
            'user_phone' => $validated['user_phone'] ?? null,
            'user_passport' => $validated['user_passport'] ?? null,
            'user_inn' => $validated['user_inn'] ?? null,
            'user_birth_date' => $validated['user_birth_date'] ?? null,
            'user_note' => $validated['user_note'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'access_all_cities' => $accessAllCities,
        ]);

        $employee->roles()->sync([$validated['role_id']]);
        if ($accessAllCities) {
            $employee->cities()->sync([]);
        } else {
            $employee->cities()->sync($validated['city_ids']);
        }

        $this->syncEmployeeWithGm($employee->fresh(['roles', 'cities']));

        return response()->json(['success' => true, 'message' => 'Сохранено']);
    }
    
    /**
     * Увольнение сотрудника
     */
    public function fire(int $user_id)
    {
        $employee = User::with('roles')->findOrFail($user_id);
        $this->ensureCanEditEmployeeAccount($employee);

        $employee->update([
            'user_fired_at' => now(),
            'is_active' => false,
        ]);

        app(\App\Services\SessionInvalidationService::class)->invalidateUser($employee->fresh());
        app(\App\Services\SecurityAuditService::class)->log(
            'user_fired_sessions_killed',
            auth()->user(),
            'user',
            (string) $employee->user_id,
            null,
            request()
        );

        $this->syncEmployeeWithGm($employee->fresh(['roles', 'cities']));
        
        return response()->json(['success' => true, 'message' => 'Сотрудник уволен']);
    }
    
    /**
     * Восстановление сотрудника
     */
    public function restore(int $user_id)
    {
        $employee = User::with('roles')->findOrFail($user_id);
        $this->ensureCanEditEmployeeAccount($employee);

        $employee->update([
            'user_fired_at' => null,
            'is_active' => true,
        ]);

        $this->syncEmployeeWithGm($employee->fresh(['roles', 'cities']));
        
        return response()->json(['success' => true, 'message' => 'Сотрудник восстановлен']);
    }
    
    /**
     * Сброс пароля (только regional_director для своего филиала, не себе; general_director для всех)
     */
    public function resetPassword(int $user_id)
    {
        $currentUser = auth()->user();
        $employee = User::with(['cities', 'roles'])->findOrFail($user_id);

        $isMasterTarget = $employee->roles->contains(
            fn ($role) => in_array($role->role_code, ['master', 'senior_master'], true)
        );

        if ($currentUser->hasRole('general_director')) {
            // Ген. директор — любому, включая мастеров (для входа в GM).
        } elseif ($currentUser->hasRole('developer')) {
            $this->ensureCanEditEmployeeAccount($employee);
        } elseif ($currentUser->hasRole('regional_director')) {
            if ((int) $user_id === (int) $currentUser->user_id) {
                abort(403, 'Региональный директор не может сбросить пароль себе');
            }
            $allowedTargetRoles = ['senior_manager', 'branch_head', 'tech_director', 'master', 'senior_master'];
            if (! $employee->roles->contains(fn ($role) => in_array($role->role_code, $allowedTargetRoles, true))) {
                abort(403, 'Региональный директор может сбросить пароль только менеджеру, руководителю филиала, техническому директору или мастеру своего города');
            }
            $employeeCityIds = $employee->cities->pluck('city_id')->toArray();
            $userCityIds = $currentUser->cities->pluck('city_id')->toArray();
            if (empty(array_intersect($employeeCityIds, $userCityIds))) {
                abort(403, 'Нет доступа к сотрудникам этого филиала');
            }
        } elseif ($currentUser->hasRole('branch_head') && $isMasterTarget) {
            // Руководитель филиала — только мастерам своих городов (выдача пароля для GM).
            $employeeCityIds = $employee->cities->pluck('city_id')->toArray();
            $userCityIds = $currentUser->cities->pluck('city_id')->toArray();
            if (empty(array_intersect($employeeCityIds, $userCityIds))) {
                abort(403, 'Нет доступа к мастерам этого филиала');
            }
        } else {
            abort(403, 'Сброс пароля недоступен для вашей роли');
        }

        $password = $this->hrService->generatePassword(10);

        $employee->forceFill([
            'password' => $password, // cast hashed
            'must_change_password' => true,
            'password_set_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => "Новый пароль: {$password}".($isMasterTarget ? ' (временный, нужна смена)' : ''),
            'password' => $password,
            'must_change_password' => true,
            'login' => strstr((string) $employee->email, '@', true) ?: $employee->email,
        ]);
    }
    
    /**
     * Рейтинг мастеров
     */
    public function masters(Request $request)
    {
        $user = auth()->user();
        
        // Получаем города пользователя или все города для разработчика
        $cityIds = [];
        if (! $user->hasAnyRole(['developer', 'general_director'])) {
            $cityIds = $user->cities->pluck('city_id')->toArray();
        }
        
        // Фильтр по городам из запроса
        if ($request->filled('city_id')) {
            $requestCityIds = is_array($request->city_id) ? $request->city_id : [$request->city_id];
            $cityIds = empty($cityIds) ? $requestCityIds : array_intersect($cityIds, $requestCityIds);
        }
        
        // Обработка быстрых периодов
        $period = $request->get('period');
        $dateFrom = null;
        $dateTo = null;
        
        if ($period === 'today') {
            $dateFrom = $dateTo = Carbon::today();
        } elseif ($period === 'yesterday') {
            $dateFrom = $dateTo = Carbon::yesterday();
        } elseif ($period === 'week') {
            $dateFrom = Carbon::now()->startOfWeek();
            $dateTo = Carbon::now()->endOfWeek();
        } elseif ($period === 'month') {
            $dateFrom = Carbon::now()->startOfMonth();
            $dateTo = Carbon::now()->endOfMonth();
        } else {
            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from')) : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to')) : null;
        }
        
        $showUnder10 = $request->boolean('show_under_10');
        $rating = $this->hrService->getMastersRating($cityIds, $dateFrom, $dateTo, $showUnder10);
        
        // Сортировка
        $sortBy = $request->get('sort_by', 'completed_sum');
        $sortDir = $request->get('sort_dir', 'desc');
        
        if ($sortBy && $rating->count() > 0) {
            $rating = $sortDir === 'asc' 
                ? $rating->sortBy($sortBy)->values()
                : $rating->sortByDesc($sortBy)->values();
        }
        
        // Сводки по городам если включено
        $citySummaries = [];
        if ($request->get('show_city_summary')) {
            $summaryCityIds = !empty($cityIds) 
                ? $cityIds 
                : City::where('is_active', true)->where('city_type', 'city')->pluck('city_id')->toArray();
            
            foreach ($summaryCityIds as $cId) {
                $citySummaries[$cId] = $this->hrService->getCitySummary($cId, $dateFrom, $dateTo);
            }
        }
        
        // Настраиваемые колонки
        $columns = $request->get('columns', ['completed_count', 'completed_sum']);
        if (!is_array($columns)) {
            $columns = explode(',', $columns);
        }
        
        $cities = $user->hasAnyRole(['developer', 'general_director'])
            ? City::where('is_active', true)->where('city_type', 'city')->orderBy('city_name')->get()
            : $user->cities;
        
        return view('hr.masters', compact('rating', 'columns', 'cities', 'dateFrom', 'dateTo', 'citySummaries', 'sortBy', 'sortDir'));
    }
    
    /**
     * График мастеров
     */
    public function roster(Request $request)
    {
        $user = auth()->user();
        
        $cityIds = $user->hasAnyRole(['developer', 'general_director'])
            ? City::where('city_type', 'city')->pluck('city_id')->toArray()
            : $user->cities->pluck('city_id')->toArray();
        
        // Фильтр по городам
        if ($request->filled('city_id')) {
            $requestCityIds = is_array($request->city_id) ? $request->city_id : [$request->city_id];
            $cityIds = array_intersect($cityIds, $requestCityIds);
        }
        
        // Начало недели (понедельник)
        $startDate = $request->get('week') 
            ? Carbon::parse($request->get('week'))->startOfWeek() 
            : Carbon::now()->startOfWeek();
        
        $schedule = $this->hrService->getMastersSchedule($cityIds, $startDate);
        
        $cities = $user->hasAnyRole(['developer', 'general_director'])
            ? City::where('is_active', true)->where('city_type', 'city')->orderBy('city_name')->get()
            : $user->cities;
        
        return view('hr.roster', compact('schedule', 'startDate', 'cities'));
    }
    
    /**
     * Обновление графика (AJAX)
     */
    public function updateRoster(Request $request)
    {
        if (auth()->user()->hasRole('general_director')) {
            abort(403, 'Генеральный директор не может редактировать график мастеров');
        }
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,user_id',
            'date' => 'required|date',
            'is_working' => 'required',
            'note' => 'nullable|string|max:255',
        ]);
        
        // Преобразуем is_working в boolean
        $isWorking = filter_var($validated['is_working'], FILTER_VALIDATE_BOOLEAN);
        
        try {
            $this->hrService->updateSchedule(
                (int) $validated['user_id'],
                $validated['date'],
                $isWorking,
                $validated['note'] ?? null
            );
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Ошибка обновления графика: ' . $e->getMessage(), [
                'user_id' => $validated['user_id'],
                'date' => $validated['date'],
                'is_working' => $isWorking,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сохранения: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Страница чёрного списка
     */
    public function blacklist(Request $request)
    {
        $query = User::where('is_blacklisted', true)
            ->with(['blacklistedByUser', 'cities', 'roles']);
        
        // Поиск по паспорту
        if ($request->filled('passport')) {
            $query->where('user_passport', 'like', '%' . $request->passport . '%');
        }
        
        // Поиск по имени
        if ($request->filled('search')) {
            $query->where('user_name', 'like', '%' . $request->search . '%');
        }
        
        $blacklisted = $query->orderByDesc('blacklisted_at')->paginate(50);
        
        return view('hr.blacklist', compact('blacklisted'));
    }
    
    /**
     * Добавить в чёрный список
     */
    public function addToBlacklist(Request $request, int $user_id)
    {
        $user = User::with('roles')->findOrFail($user_id);
        $this->ensureCanEditEmployeeAccount($user);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        
        $user->update([
            'is_blacklisted' => true,
            'blacklist_reason' => $validated['reason'],
            'blacklisted_at' => now(),
            'blacklisted_by' => auth()->id(),
            'is_active' => false,
        ]);

        app(\App\Services\SessionInvalidationService::class)->invalidateUser($user->fresh());
        
        return response()->json(['success' => true, 'message' => 'Сотрудник добавлен в чёрный список']);
    }
    
    /**
     * Убрать из чёрного списка
     */
    public function removeFromBlacklist(int $user_id)
    {
        $user = User::findOrFail($user_id);

        if (!$user->is_blacklisted) {
            return response()->json([
                'success' => false,
                'message' => 'Сотрудник не в чёрном списке',
            ], 422);
        }

        $auth = auth()->user();
        $isElevated = $auth->hasAnyRole(['developer', 'general_director']);
        $isAuthor = $user->blacklisted_by !== null
            && (int) $user->blacklisted_by === (int) $auth->user_id;

        if (!$isElevated && !$isAuthor) {
            return response()->json([
                'success' => false,
                'message' => 'Удалить из чёрного списка может только тот, кто добавил сотрудника, либо генеральный директор или разработчик',
            ], 403);
        }

        $user->update([
            'is_blacklisted' => false,
            'blacklist_reason' => null,
            'blacklisted_at' => null,
            'blacklisted_by' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Сотрудник удалён из чёрного списка']);
    }
    
    /**
     * Проверка паспорта (AJAX)
     */
    public function checkPassport(Request $request)
    {
        $passport = $request->get('passport');
        if (!$passport) {
            return response()->json(['blacklisted' => false]);
        }
        
        $blacklisted = User::isPassportBlacklisted($passport);
        
        if ($blacklisted) {
            return response()->json([
                'blacklisted' => true,
                'user_name' => $blacklisted->user_name,
                'reason' => $blacklisted->blacklist_reason,
                'blacklisted_at' => $blacklisted->blacklisted_at->format('d.m.Y'),
            ]);
        }
        
        return response()->json(['blacklisted' => false]);
    }
    
    /**
     * Добавление комментария к сотруднику
     */
    public function storeComment(Request $request, int $user_id)
    {
        $employee = User::with(['roles', 'cities'])->findOrFail($user_id);
        $this->assertCanViewEmployee(auth()->user(), $employee);
        
        $validated = $request->validate([
            'comment_text' => 'required|string|max:2000',
        ]);
        
        $comment = new Comment([
            'commentable_type' => User::class,
            'commentable_id' => $employee->user_id,
            'comment_text' => $validated['comment_text'],
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
        $comment->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Комментарий добавлен',
            'comment' => [
                'comment_id' => $comment->comment_id,
                'comment_text' => $comment->comment_text,
                'author_name' => auth()->user()->user_name,
                'created_at' => $comment->created_at->format('d.m.Y H:i'),
            ],
        ]);
    }
    
    /**
     * Удаление комментария (только для разработчика)
     */
    public function destroyComment(int $comment_id)
    {
        if (!auth()->user()->hasRole('developer')) {
            abort(403, 'Удаление комментариев доступно только разработчику');
        }
        
        $comment = Comment::findOrFail($comment_id);
        $comment->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Комментарий удалён',
        ]);
    }
    
    /**
     * Верификация документа сотрудника (галочка «Проверено»)
     */
    public function verifyDocument(Request $request, int $document_id)
    {
        $document = Document::findOrFail($document_id);
        
        // Проверяем что документ принадлежит сотруднику
        if ($document->documentable_type !== User::class) {
            return response()->json(['success' => false, 'message' => 'Документ не относится к сотруднику'], 400);
        }
        
        $isVerified = $request->boolean('is_verified');
        
        $document->update([
            'is_verified' => $isVerified,
            'verified_by' => $isVerified ? auth()->id() : null,
            'verified_at' => $isVerified ? now() : null,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => $isVerified ? 'Документ подтверждён' : 'Подтверждение снято',
            'is_verified' => $document->is_verified,
            'verified_by_name' => $isVerified ? auth()->user()->user_name : null,
            'verified_at' => $document->verified_at?->format('d.m.Y H:i'),
        ]);
    }

    /**
     * Локальная часть email для поля ввода (снимает @lc.ru и устаревший @level.ion).
     */
    public static function employeeEmailLocalPart(?string $email): string
    {
        if ($email === null || $email === '') {
            return '';
        }
        $suffix = preg_quote(self::EMPLOYEE_EMAIL_SUFFIX, '/');
        $legacy = preg_quote(self::LEGACY_EMPLOYEE_EMAIL_SUFFIX, '/');
        $out = preg_replace('/(?:' . $suffix . '|' . $legacy . ')$/iu', '', $email);

        return is_string($out) ? $out : $email;
    }

    /**
     * Подменяет request «email» на «локаль» + EMPLOYEE_EMAIL_SUFFIX (всегда @lc.ru).
     * Локальная часть — только латиница/цифры (иначе Laravel email падает с validation.email).
     */
    private function mergeNormalizedEmployeeEmailFromInput(Request $request, ?string $reuseWhenInvalid): void
    {
        $emailIn = $request->input('email');
        if (! is_string($emailIn)) {
            return;
        }
        $trimmed = trim($emailIn);
        $suffix = self::EMPLOYEE_EMAIL_SUFFIX;
        if ($trimmed !== '') {
            if (str_contains($trimmed, '@')) {
                $local = trim(explode('@', $trimmed, 2)[0]);
            } else {
                $local = $trimmed;
            }
            $local = strtolower(preg_replace('/[^a-zA-Z0-9._+-]/', '', $local) ?? '');
            $trimmed = $local !== '' ? $local.$suffix : '';
        }
        if ($trimmed === '' || $trimmed === $suffix) {
            if ($reuseWhenInvalid !== null) {
                $sanitizedReuse = $this->sanitizeEmployeeEmail($reuseWhenInvalid);
                if ($sanitizedReuse !== null) {
                    $request->merge(['email' => $sanitizedReuse]);
                } else {
                    $request->merge(['email' => null]);
                }
            }

            return;
        }
        $request->merge(['email' => $trimmed]);
    }

    private function sanitizeEmployeeEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }
        $trimmed = trim($email);
        $local = str_contains($trimmed, '@') ? explode('@', $trimmed, 2)[0] : $trimmed;
        $local = strtolower(preg_replace('/[^a-zA-Z0-9._+-]/', '', $local) ?? '');
        if ($local === '') {
            return null;
        }

        return $local.self::EMPLOYEE_EMAIL_SUFFIX;
    }

    private function assertCanAccessHrList(User $viewer): void
    {
        if ($viewer->hasRole('call_center')
            && ! $this->hrService->isCallCenterSupervisor($viewer)
            && ! $viewer->hasAnyRole([
                'developer', 'general_director', 'senior_manager',
                'tech_director', 'branch_head', 'regional_director',
            ])) {
            abort(403, 'Раздел сотрудников недоступен для диспетчеров КЦ');
        }

        if ($viewer->hasRole('senior_dispatcher')
            && ! $this->hrService->isCallCenterSupervisor($viewer)
            && ! $viewer->hasAnyRole([
                'developer', 'general_director', 'senior_manager',
                'tech_director', 'branch_head', 'regional_director',
            ])) {
            abort(403, 'Нет доступа к списку сотрудников');
        }
    }

    private function assertCanViewEmployee(User $viewer, User $employee): void
    {
        $this->assertCanAccessHrList($viewer);

        if (! $this->hrService->canViewEmployee($viewer, $employee)) {
            abort(403, 'Нет доступа к этой учётной записи');
        }
    }

    private function ensureCanEditEmployeeAccount(User $employee): void
    {
        $viewer = auth()->user();
        $this->assertCanViewEmployee($viewer, $employee);

        // Старший диспетчер (Иса) — только просмотр учёток КЦ
        if ($this->hrService->isCallCenterSupervisor($viewer)
            && ! $viewer->hasAnyRole(['developer', 'general_director'])) {
            abort(403, 'Редактирование учёток КЦ недоступно');
        }

        if ($employee->hasRole('developer') && ! $viewer->hasAnyRole(['developer', 'general_director'])) {
            abort(403, 'Нельзя редактировать учётную запись разработчика');
        }
    }

    private function isGeneralDirectorRoleLocked(User $employee): bool
    {
        return false;
    }

    private function syncEmployeeWithGm(User $employee): array
    {
        try {
            \App\Jobs\SyncUserToGmJob::dispatch((int) $employee->user_id);

            return [
                'ok' => true,
                'queued' => true,
                'reason' => null,
                'status' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('GM user sync dispatch failed.', [
                'user_id' => $employee->user_id,
                'email' => $employee->email,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason' => $e->getMessage(),
                'status' => null,
            ];
        }
    }
}
