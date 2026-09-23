<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Публичные маршруты (без авторизации)
|--------------------------------------------------------------------------
*/

// Редирект с /crm на заказы или хаб-логин
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('orders.index')
        : redirect()->away(rtrim((string) config('services.hub.public_url'), '/').'/login');
});

// Авторизация (fallback; основной вход — хаб)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('crm.login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('crm.login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// SSO из хаба проектов
Route::get('/sso/callback', [\App\Http\Controllers\HubSsoController::class, 'callback'])->name('hub.sso.callback');

Route::middleware(['auth'])->group(function () {
    Route::get('/two-factor/challenge', [\App\Http\Controllers\Auth\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [\App\Http\Controllers\Auth\TwoFactorController::class, 'verifyChallenge'])->name('two-factor.challenge.verify');
    Route::get('/two-factor/setup', [\App\Http\Controllers\Auth\TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [\App\Http\Controllers\Auth\TwoFactorController::class, 'confirmSetup'])->name('two-factor.setup.confirm');
});

/*
|--------------------------------------------------------------------------
| Защищённые маршруты (требуют авторизации)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf-token');

    Route::get('/notifications/unseen-city-orders-count', [\App\Http\Controllers\OrderNotificationController::class, 'unseenCityOrdersCount'])
        ->name('notifications.unseen-city-orders-count');

    Route::get('/notifications/unseen-complaints-count', [\App\Http\Controllers\OrderNotificationController::class, 'unseenComplaintsCount'])
        ->name('notifications.unseen-complaints-count');

    Route::get('/notifications/due-callbacks-count', [\App\Http\Controllers\OrderNotificationController::class, 'dueCallbacksCount'])
        ->name('notifications.due-callbacks-count')
        ->middleware('role:call_center,senior_dispatcher,developer');

    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user && $user->hasAnyRole(['call_center', 'senior_dispatcher'])) {
            return redirect()->route('dispatcher.dashboard');
        }

        return redirect()->route('orders.index');
    });

    Route::get('/dispatcher/dashboard', [\App\Http\Controllers\DispatcherDashboardController::class, 'index'])
        ->name('dispatcher.dashboard')
        ->middleware('role:developer,call_center,senior_dispatcher,regional_director,general_director');

    // SSO-вход в отдельное Единое окно (lead-desk) — совместимость
    Route::get('/desk-sso', [\App\Http\Controllers\DeskSsoController::class, 'redirect'])
        ->name('desk.sso')
        ->middleware('role:developer,call_center,senior_dispatcher,senior_manager,tech_director,branch_head,regional_director,general_director');

    // Старый встроенный /crm/desk → наружу на /desk
    Route::get('/desk/{any?}', function () {
        $hub = rtrim((string) config('services.hub.public_url'), '/');

        return redirect()->away($hub.'/desk');
    })->where('any', '.*')->name('desk.index');

    // Заказы
    Route::prefix('orders')->name('orders.')->group(function () {
        // Создание заказа (КЦ и разработчик) — до /{order_id} чтобы не перехватывался wildcard
        Route::get('/create/{person_id}', [\App\Http\Controllers\OrderController::class, 'create'])->name('create')
            ->middleware('role:developer,call_center,senior_dispatcher')
            ->whereNumber('person_id');
        Route::post('/', [\App\Http\Controllers\OrderController::class, 'store'])->name('store')
            ->middleware('role:developer,call_center,senior_dispatcher');

        // Список заказов, просмотр и данные заказа
        Route::middleware('role:developer,call_center,senior_dispatcher,senior_manager,tech_director,branch_head,regional_director,general_director')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\OrderController::class, 'index'])->name('index');
                Route::get('/{order_id}', [\App\Http\Controllers\OrderController::class, 'show'])->name('show')
                    ->middleware('throttle:client-cards')
                    ->whereNumber('order_id');
                Route::get('/{order_id}/data', [\App\Http\Controllers\OrderController::class, 'getData'])->name('data')
                    ->middleware('throttle:client-cards')
                    ->whereNumber('order_id');
            });

        // Редактирование (тех. директор и ген. директор не могут, КЦ может частично)
        Route::put('/{order_id}', [\App\Http\Controllers\OrderController::class, 'update'])->name('update')
            ->middleware('role:developer,senior_dispatcher,senior_manager,branch_head,regional_director,call_center,general_director')
            ->whereNumber('order_id');

        // Проведение
        Route::post('/{order_id}/complete', [\App\Http\Controllers\OrderController::class, 'complete'])->name('complete')
            ->middleware('role:developer,senior_dispatcher,senior_manager,branch_head,regional_director,general_director')
            ->whereNumber('order_id');

        // Открытие проведённого заказа
        Route::post('/{order_id}/reopen', [\App\Http\Controllers\OrderController::class, 'reopen'])->name('reopen')
            ->middleware('role:developer,regional_director,senior_dispatcher,general_director')
            ->whereNumber('order_id');

        // Документы заказа (тех. директор и ген. директор только просматривают)
        Route::post('/{order_id}/documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('orders.documents.store')
            ->middleware('role:developer,senior_dispatcher,senior_manager,branch_head,regional_director,call_center')
            ->whereNumber('order_id');
    });

    // Закрепление времени работы города (директора видят все города)
    Route::prefix('city-open-times')->name('city-open-times.')
        ->middleware('role:developer,branch_head,regional_director,general_director,tech_director')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\CityOpenTimeController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\CityOpenTimeController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\CityOpenTimeController::class, 'store'])->name('store');
            Route::get('/{cityOpenTime}/edit', [\App\Http\Controllers\CityOpenTimeController::class, 'edit'])->name('edit')->whereNumber('cityOpenTime');
            Route::put('/{cityOpenTime}', [\App\Http\Controllers\CityOpenTimeController::class, 'update'])->name('update')->whereNumber('cityOpenTime');
            Route::delete('/{cityOpenTime}', [\App\Http\Controllers\CityOpenTimeController::class, 'destroy'])->name('destroy')->whereNumber('cityOpenTime');
        });

    // Документы (общие операции)
    Route::prefix('documents')->name('documents.')
        ->middleware('auth')
        ->group(function () {
            Route::get('/{document_id}', [\App\Http\Controllers\DocumentController::class, 'show'])->name('show');
            Route::get('/{document_id}/download', [\App\Http\Controllers\DocumentController::class, 'download'])->name('download');
            Route::delete('/{document_id}', [\App\Http\Controllers\DocumentController::class, 'destroy'])->name('destroy');
        });

    // Раскрытие телефона (роли с доступом к карточке заказа/жалобы, не только КЦ)
    Route::get('/persons/phones/{phone_id}/reveal', [\App\Http\Controllers\PersonPhoneController::class, 'reveal'])
        ->name('persons.phones.reveal')
        ->middleware([
            'role:developer,call_center,senior_dispatcher,senior_manager,tech_director,branch_head,regional_director,general_director',
            'throttle:persons-search',
        ])
        ->whereNumber('phone_id');

    // Персоны (доступ: developer, call_center, старший диспетчер)
    Route::prefix('persons')->name('persons.')
        ->middleware('role:developer,call_center,senior_dispatcher')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\PersonController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\PersonController::class, 'create'])->name('create');
            Route::get('/search', [\App\Http\Controllers\PersonController::class, 'search'])->name('search')
                ->middleware('throttle:persons-search');
            Route::post('/', [\App\Http\Controllers\PersonController::class, 'store'])->name('store');
            Route::get('/{person_id}', [\App\Http\Controllers\PersonController::class, 'show'])->name('show')
                ->middleware('throttle:client-cards');
            Route::put('/{person_id}', [\App\Http\Controllers\PersonController::class, 'update'])->name('update');

            // Время города
            Route::get('/city/{city_id}/time', [\App\Http\Controllers\PersonController::class, 'getCityTime'])->name('city.time');

            // Телефоны
            Route::post('/{person_id}/phones', [\App\Http\Controllers\PersonPhoneController::class, 'store'])->name('phones.store');
            Route::put('/phones/{phone_id}', [\App\Http\Controllers\PersonPhoneController::class, 'update'])->name('phones.update');

            // Адреса
            Route::post('/{person_id}/addresses', [\App\Http\Controllers\AddressController::class, 'store'])->name('addresses.store');
            Route::get('/addresses/{address_id}/data', [\App\Http\Controllers\AddressController::class, 'show'])->name('addresses.show');
            Route::put('/addresses/{address_id}', [\App\Http\Controllers\AddressController::class, 'update'])->name('addresses.update');

            // Звонки (интеграция с Mango Office)
            Route::post('/{person_id}/call', [\App\Http\Controllers\PersonController::class, 'initiateCall'])->name('call');
        });

    // Касса (диспетчеры и ген.директор — только просмотр)
    Route::prefix('cfm')->name('cfm.')
        ->middleware('role:developer,branch_head,regional_director,general_director,senior_manager,call_center,senior_dispatcher')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\CfmController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\CfmController::class, 'create'])->name('create')
                ->middleware('role:developer,branch_head,regional_director,senior_manager,call_center,senior_dispatcher');
            Route::post('/', [\App\Http\Controllers\CfmController::class, 'store'])->name('store')
                ->middleware('role:developer,branch_head,regional_director,senior_manager,call_center,senior_dispatcher');
            Route::get('/refund-suggest', [\App\Http\Controllers\CfmController::class, 'refundSuggest'])->name('refund-suggest')
                ->middleware('role:developer,branch_head,regional_director,senior_manager,call_center,senior_dispatcher');
            Route::get('/summary', [\App\Http\Controllers\CfmController::class, 'summary'])->name('summary');
            Route::get('/summary/{city_id}', [\App\Http\Controllers\CfmController::class, 'cityReport'])->name('summary.city');
            Route::get('/salary', [\App\Http\Controllers\DirectorSalaryController::class, 'index'])->name('salary.index')
                ->middleware('role:developer,branch_head,regional_director,general_director,senior_manager');
            Route::get('/salary/payout-cap', [\App\Http\Controllers\DirectorSalaryController::class, 'payoutCap'])->name('salary.payout-cap')
                ->middleware('role:developer,branch_head,regional_director,general_director,senior_manager');
            Route::get('/salary/policies', [\App\Http\Controllers\DirectorSalaryController::class, 'policies'])->name('salary.policies')
                ->middleware('role:developer,general_director');
            Route::post('/salary/policies', [\App\Http\Controllers\DirectorSalaryController::class, 'savePolicies'])->name('salary.policies.save')
                ->middleware('role:developer,general_director');
            Route::post('/salary/{salary_calculation_id}/recalc', [\App\Http\Controllers\DirectorSalaryController::class, 'recalc'])->name('salary.recalc')
                ->middleware('role:developer,general_director');
            Route::get('/order-payments', [\App\Http\Controllers\CfmController::class, 'orderPayments'])->name('order-payments')
                ->middleware('role:developer,general_director,senior_dispatcher');

            Route::prefix('masters/settlement')->name('master-settlement.')
                ->middleware('role:developer,branch_head,regional_director,general_director,senior_manager')
                ->group(function () {
                    Route::get('/', [\App\Http\Controllers\MasterSettlementController::class, 'index'])->name('index');
                    Route::post('/process', [\App\Http\Controllers\MasterSettlementController::class, 'processMasters'])->name('process-masters');
                    Route::get('/{user_id}', [\App\Http\Controllers\MasterSettlementController::class, 'show'])->name('show');
                    Route::post('/{user_id}/process', [\App\Http\Controllers\MasterSettlementController::class, 'processMaster'])->name('process');
                });

            // Редактор статей (разработчик и ген. директор)
            Route::get('/editor', [\App\Http\Controllers\CfmController::class, 'editor'])->name('editor')
                ->middleware('role:developer,general_director');
            Route::post('/editor/{cfm_cat_id}', [\App\Http\Controllers\CfmController::class, 'updateCategory'])->name('editor.update')
                ->middleware('role:developer,general_director');
            Route::post('/editor', [\App\Http\Controllers\CfmController::class, 'storeCategory'])->name('editor.store')
                ->middleware('role:developer,general_director');
            Route::delete('/editor/{cfm_cat_id}', [\App\Http\Controllers\CfmController::class, 'deleteCategory'])->name('editor.delete')
                ->middleware('role:developer,general_director');

            Route::get('/{cfm_id}', [\App\Http\Controllers\CfmController::class, 'show'])->name('show');
            Route::put('/{cfm_id}', [\App\Http\Controllers\CfmController::class, 'update'])->name('update')
                ->middleware('role:developer,branch_head,regional_director,senior_manager,general_director');
            Route::post('/{cfm_id}/close', [\App\Http\Controllers\CfmController::class, 'close'])->name('close')
                ->middleware('role:developer,branch_head,regional_director,senior_manager,general_director');
            Route::post('/{cfm_id}/reopen', [\App\Http\Controllers\CfmController::class, 'reopen'])->name('reopen')
                ->middleware('role:developer,general_director');

            // Документы кассовой операции — все с доступом к кассе, в том числе после проведения
            Route::post('/{cfm_id}/documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('cfm.documents.store')
                ->middleware('role:developer,branch_head,regional_director,general_director,senior_manager,call_center,senior_dispatcher');
        });

    // Управление (только developer и general_director)
    Route::prefix('management')->name('management.')
        ->middleware('role:developer,general_director')
        ->group(function () {
            Route::prefix('cities')->name('cities.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Management\CityManagementController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\Management\CityManagementController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Management\CityManagementController::class, 'store'])->name('store');
                Route::get('/{city}/edit', [\App\Http\Controllers\Management\CityManagementController::class, 'edit'])->name('edit');
                Route::put('/{city}', [\App\Http\Controllers\Management\CityManagementController::class, 'update'])->name('update');
            });

            Route::prefix('flyer-makets')->name('flyer-makets.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Management\FlyerMaketController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\Management\FlyerMaketController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\Management\FlyerMaketController::class, 'store'])->name('store');
                Route::get('/{flyer_maket}/edit', [\App\Http\Controllers\Management\FlyerMaketController::class, 'edit'])->name('edit');
                Route::put('/{flyer_maket}', [\App\Http\Controllers\Management\FlyerMaketController::class, 'update'])->name('update');
            });
        });

    // Источники заказов (старший диспетчер — без остального раздела «Управление»)
    Route::prefix('management/sources')->name('management.sources.')
        ->middleware('role:developer,general_director,senior_dispatcher')
        ->group(function () {
            Route::get('/import/template', [\App\Http\Controllers\Management\SourceManagementController::class, 'importTemplate'])->name('import.template');
            Route::get('/import', [\App\Http\Controllers\Management\SourceManagementController::class, 'importForm'])->name('import');
            Route::post('/import', [\App\Http\Controllers\Management\SourceManagementController::class, 'import'])->name('import.store');
            Route::get('/', [\App\Http\Controllers\Management\SourceManagementController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Management\SourceManagementController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Management\SourceManagementController::class, 'store'])->name('store');
            Route::get('/{source}/edit', [\App\Http\Controllers\Management\SourceManagementController::class, 'edit'])->name('edit');
            Route::put('/{source}', [\App\Http\Controllers\Management\SourceManagementController::class, 'update'])->name('update');
            Route::delete('/{source}', [\App\Http\Controllers\Management\SourceManagementController::class, 'destroy'])->name('destroy');
        });

    // Сотрудники (HR)
    Route::prefix('hr')->name('hr.')->group(function () {
        // Список сотрудников (просмотр)
        Route::get('/', [\App\Http\Controllers\HrController::class, 'index'])->name('index')
            ->middleware('role:senior_manager,tech_director,branch_head,regional_director,general_director,developer,senior_dispatcher');
        // Создание сотрудника
        Route::get('/create', [\App\Http\Controllers\HrController::class, 'create'])->name('create')
            ->middleware('role:branch_head,regional_director,general_director,developer');
        Route::get('/{user_id}/data', [\App\Http\Controllers\HrController::class, 'show'])->name('show')
            ->middleware('role:senior_manager,tech_director,branch_head,regional_director,general_director,developer,senior_dispatcher');
        Route::post('/', [\App\Http\Controllers\HrController::class, 'store'])->name('store')
            ->middleware('role:branch_head,regional_director,general_director,developer');
        // Редактирование сотрудника
        Route::get('/{user_id}/edit', [\App\Http\Controllers\HrController::class, 'edit'])->name('edit')
            ->middleware('role:senior_manager,tech_director,branch_head,regional_director,general_director,developer,senior_dispatcher');
        Route::put('/{user_id}', [\App\Http\Controllers\HrController::class, 'update'])->name('update')
            ->middleware('role:branch_head,regional_director,general_director,developer');
        Route::post('/{user_id}/fire', [\App\Http\Controllers\HrController::class, 'fire'])->name('fire')
            ->middleware('role:branch_head,regional_director,general_director,developer');
        Route::post('/{user_id}/restore', [\App\Http\Controllers\HrController::class, 'restore'])->name('restore')
            ->middleware('role:branch_head,regional_director,general_director,developer');
        Route::post('/{user_id}/reset-password', [\App\Http\Controllers\HrController::class, 'resetPassword'])->name('reset-password')
            ->middleware('role:branch_head,regional_director,general_director,developer');

        // Рейтинг мастеров
        Route::get('/masters', [\App\Http\Controllers\HrController::class, 'masters'])->name('masters')
            ->middleware('role:senior_manager,tech_director,branch_head,regional_director,general_director,developer');

        // График мастеров
        Route::get('/roster', [\App\Http\Controllers\HrController::class, 'roster'])->name('roster')
            ->middleware('role:senior_manager,branch_head,regional_director,general_director,developer');
        Route::post('/roster', [\App\Http\Controllers\HrController::class, 'updateRoster'])->name('roster.update')
            ->middleware('role:senior_manager,branch_head,regional_director,developer');

        // Документы сотрудника (gen_dir может добавлять)
        Route::post('/{user_id}/documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('hr.documents.store')
            ->middleware('role:branch_head,regional_director,general_director,developer');

        // Верификация документов сотрудника
        Route::post('/documents/{document_id}/verify', [\App\Http\Controllers\HrController::class, 'verifyDocument'])->name('documents.verify')
            ->middleware('role:branch_head,regional_director,developer');

        // Комментарии к сотруднику
        Route::post('/{user_id}/comments', [\App\Http\Controllers\HrController::class, 'storeComment'])->name('comments.store')
            ->middleware('role:senior_manager,tech_director,branch_head,regional_director,general_director,developer');
        Route::delete('/comments/{comment_id}', [\App\Http\Controllers\HrController::class, 'destroyComment'])->name('comments.destroy')
            ->middleware('role:developer');

        // Чёрный список: только API (увольнение с ЧС, проверка паспорта) — отдельная страница убрана
        Route::post('/{user_id}/blacklist', [\App\Http\Controllers\HrController::class, 'addToBlacklist'])->name('blacklist.add')
            ->middleware('role:branch_head,regional_director,general_director,developer');
        Route::delete('/{user_id}/blacklist', [\App\Http\Controllers\HrController::class, 'removeFromBlacklist'])->name('blacklist.remove')
            ->middleware('role:branch_head,regional_director,general_director,developer');
        Route::get('/check-passport', [\App\Http\Controllers\HrController::class, 'checkPassport'])->name('check-passport')
            ->middleware('role:branch_head,regional_director,general_director,developer');
    });

    // Отчёты
    Route::prefix('reports')->name('reports.')
        ->middleware('role:developer,branch_head,regional_director,general_director')
        ->group(function () {
            Route::get('/orders', [\App\Http\Controllers\ReportController::class, 'orders'])->name('orders');
            Route::get('/cancellations', [\App\Http\Controllers\ReportController::class, 'cancellations'])->name('cancellations');
            Route::get('/partners', [\App\Http\Controllers\ReportController::class, 'partners'])->name('partners');
            Route::get('/closed-orders', [\App\Http\Controllers\ReportController::class, 'closedOrders'])->name('closed-orders');
            Route::get('/by-city', [\App\Http\Controllers\ReportController::class, 'byCity'])->name('by-city');
            Route::get('/city-employee', [\App\Http\Controllers\ReportController::class, 'cityEmployee'])->name('city-employee');
            Route::get('/masters-turnover', [\App\Http\Controllers\ReportController::class, 'mastersTurnover'])->name('masters-turnover');
            Route::get('/monthly-table', [\App\Http\Controllers\ReportController::class, 'monthlyTable'])->name('monthly-table');
            Route::get('/exports/{exportId}', [\App\Http\Controllers\ReportExportController::class, 'download'])
                ->name('exports.download')
                ->whereUuid('exportId');
        });

    // Обратная связь (ОКК) — диспетчеры и разработчик
    Route::prefix('complaints')->name('complaints.')
        ->middleware('role:developer,call_center,senior_dispatcher')
        ->group(function () {
            Route::get('/reviews', [\App\Http\Controllers\ReviewController::class, 'index'])->name('reviews');
            Route::get('/reviews/create', [\App\Http\Controllers\ReviewController::class, 'create'])->name('reviews.create');
            Route::get('/reviews/image/{path}', [\App\Http\Controllers\ReviewController::class, 'showImage'])->name('reviews.image')->where('path', '.*');
            Route::get('/reviews/{review}', [\App\Http\Controllers\ReviewController::class, 'show'])
                ->name('reviews.show')
                ->whereNumber('review');
            Route::post('/reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
        });

    // Претензии (ОКК)
    Route::prefix('complaints')->name('complaints.')
        ->middleware('role:developer,call_center,senior_dispatcher,senior_manager,branch_head,regional_director,general_director')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\ComplaintController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\ComplaintController::class, 'create'])->name('create')
                ->middleware('role:developer,call_center');
            Route::post('/', [\App\Http\Controllers\ComplaintController::class, 'store'])->name('store')
                ->middleware('role:developer,call_center');
            Route::get('/report', [\App\Http\Controllers\ComplaintController::class, 'report'])->name('report');
            Route::get('/{complaint_id}', [\App\Http\Controllers\ComplaintController::class, 'show'])->name('show');
            Route::put('/{complaint_id}', [\App\Http\Controllers\ComplaintController::class, 'update'])->name('update')
                ->middleware('role:developer,call_center,senior_manager,branch_head,regional_director');
            Route::post('/{complaint_id}/comments', [\App\Http\Controllers\ComplaintController::class, 'storeComment'])->name('comments.store')
                ->middleware('role:developer,call_center,senior_manager,branch_head,regional_director');
        });

    // Реклама (доступ: developer, ad_manager, senior_manager, branch_head, regional_director)
    Route::prefix('prom')->middleware(['auth', 'role:ad_manager,senior_manager,branch_head,regional_director,developer'])->group(function () {

        // Журнал — Встречи
        Route::get('/journal', fn () => redirect()->route('prom.journal.meetings'))->name('prom.journal');
        Route::get('/journal/meetings', [\App\Http\Controllers\PromJournalController::class, 'meetings'])->name('prom.journal.meetings');
        Route::get('/journal/meetings/create', [\App\Http\Controllers\PromJournalController::class, 'createMeeting'])->name('prom.meetings.create');
        Route::post('/journal/meetings', [\App\Http\Controllers\PromJournalController::class, 'storeMeeting'])->name('prom.meetings.store');
        Route::get('/journal/meetings/{meeting}/edit', [\App\Http\Controllers\PromJournalController::class, 'editMeeting'])->name('prom.meetings.edit');
        Route::put('/journal/meetings/{meeting}', [\App\Http\Controllers\PromJournalController::class, 'updateMeeting'])->name('prom.meetings.update');
        Route::delete('/journal/meetings/{meeting}', [\App\Http\Controllers\PromJournalController::class, 'destroyMeeting'])->name('prom.meetings.destroy');

        // Журнал — Записи
        Route::get('/journal/appointments', [\App\Http\Controllers\PromJournalController::class, 'appointments'])->name('prom.journal.appointments');
        Route::get('/journal/appointments/create', [\App\Http\Controllers\PromJournalController::class, 'createAppointment'])->name('prom.appointments.create');
        Route::post('/journal/appointments', [\App\Http\Controllers\PromJournalController::class, 'storeAppointment'])->name('prom.appointments.store');
        Route::get('/journal/appointments/{appointment}/edit', [\App\Http\Controllers\PromJournalController::class, 'editAppointment'])->name('prom.appointments.edit');
        Route::put('/journal/appointments/{appointment}', [\App\Http\Controllers\PromJournalController::class, 'updateAppointment'])->name('prom.appointments.update');
        Route::delete('/journal/appointments/{appointment}', [\App\Http\Controllers\PromJournalController::class, 'destroyAppointment'])->name('prom.appointments.destroy');

        // Маршруты рекламы
        Route::get('/routes', [\App\Http\Controllers\PromRoutesController::class, 'index'])->name('prom.routes');
        Route::post('/routes', [\App\Http\Controllers\PromRoutesController::class, 'store'])->name('prom.routes.store');
        Route::put('/routes/{route}', [\App\Http\Controllers\PromRoutesController::class, 'update'])->name('prom.routes.update');
        Route::get('/routes/{route}/history', [\App\Http\Controllers\PromRoutesController::class, 'history'])->name('prom.routes.history');
        Route::delete('/routes/{route}', [\App\Http\Controllers\PromRoutesController::class, 'destroy'])->name('prom.routes.destroy');

        // Разноска
        Route::get('/actions', [\App\Http\Controllers\PromActionsController::class, 'index'])->name('prom.actions');
        Route::post('/actions', [\App\Http\Controllers\PromActionsController::class, 'store'])->name('prom.actions.store');
        Route::get('/actions/{action}/edit', [\App\Http\Controllers\PromActionsController::class, 'edit'])->name('prom.actions.edit');
        Route::put('/actions/{action}', [\App\Http\Controllers\PromActionsController::class, 'update'])->name('prom.actions.update');
        Route::delete('/actions/{action}', [\App\Http\Controllers\PromActionsController::class, 'destroy'])->name('prom.actions.destroy');

        // Оплата промоутеров
        Route::get('/pays', [\App\Http\Controllers\PromPaymentsController::class, 'index'])->name('prom.payments');
        Route::get('/pays/create', [\App\Http\Controllers\PromPaymentsController::class, 'create'])->name('prom.payments.create');
        Route::post('/pays/preview', [\App\Http\Controllers\PromPaymentsController::class, 'preview'])->name('prom.payments.preview');
        Route::get('/pays/last-requisites/{promoterId}', [\App\Http\Controllers\PromPaymentsController::class, 'lastRequisites'])->name('prom.payments.last-requisites');
        Route::post('/pays', [\App\Http\Controllers\PromPaymentsController::class, 'store'])->name('prom.payments.store');
        Route::get('/pays/{payment}/copy-text', [\App\Http\Controllers\PromPaymentsController::class, 'copyText'])->name('prom.payments.copy-text');
        Route::get('/pays/{payment}', [\App\Http\Controllers\PromPaymentsController::class, 'show'])->name('prom.payments.show');
        Route::put('/pays/{payment}', [\App\Http\Controllers\PromPaymentsController::class, 'update'])->name('prom.payments.update');
        Route::delete('/pays/{payment}', [\App\Http\Controllers\PromPaymentsController::class, 'destroy'])->name('prom.payments.destroy');

    });

    // База знаний (доступ: все авторизованные пользователи)
    Route::prefix('information')->name('information.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KnowledgeController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\KnowledgeController::class, 'create'])->name('create')
            ->middleware('role:regional_director,general_director,developer');
        Route::post('/', [\App\Http\Controllers\KnowledgeController::class, 'store'])->name('store')
            ->middleware('role:regional_director,general_director,developer');
        Route::get('/{article_id}', [\App\Http\Controllers\KnowledgeController::class, 'show'])->name('show');
        Route::get('/{article_id}/edit', [\App\Http\Controllers\KnowledgeController::class, 'edit'])->name('edit')
            ->middleware('role:regional_director,general_director,developer');
        Route::put('/{article_id}', [\App\Http\Controllers\KnowledgeController::class, 'update'])->name('update')
            ->middleware('role:regional_director,general_director,developer');
        Route::delete('/{article_id}', [\App\Http\Controllers\KnowledgeController::class, 'destroy'])->name('destroy')
            ->middleware('role:regional_director,developer');
    });

    // Настройки (доступны всем авторизованным пользователям)
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::patch('/settings/theme', [\App\Http\Controllers\SettingsController::class, 'updateTheme'])->name('settings.theme');

    Route::view('/ui-kit', 'ui-kit.index')
        ->name('ui-kit')
        ->middleware('role:developer');

});
