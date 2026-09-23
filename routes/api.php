<?php

use App\Http\Controllers\Api\IntegrationPingController;
use App\Http\Controllers\Api\GmController;
use App\Http\Controllers\Api\MangoWebhookController;
use App\Http\Controllers\Api\PartnerOrderController;
use App\Http\Controllers\Api\PartnerReferenceController;
use App\Http\Middleware\VerifyGmApiBearer;
use App\Http\Middleware\VerifySuperPartApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Маршруты для внешних интеграций (без CSRF-защиты)
|
*/

/*
|--------------------------------------------------------------------------
| Mango Office Webhook
|--------------------------------------------------------------------------
|
| Endpoint для приёма webhook от Mango Office (IP-телефония).
| В настройках Mango указать URL: https://your-domain.com/api/mango/webhook
|
*/
Route::post('/mango/webhook', MangoWebhookController::class)->name('mango.webhook');

/*
|--------------------------------------------------------------------------
| SuperPart (партнёрский портал)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')
    ->middleware(['throttle:superpart-api', VerifySuperPartApi::class])
    ->group(function () {
        Route::get('/ping', IntegrationPingController::class)->name('api.v1.ping');
        Route::get('/reference/cities', [PartnerReferenceController::class, 'cities'])->name('api.v1.reference.cities');
        Route::get('/reference/work-types', [PartnerReferenceController::class, 'workTypes'])->name('api.v1.reference.work-types');
        Route::get('/reference/sources', [PartnerReferenceController::class, 'sources'])->name('api.v1.reference.sources');
        Route::post('/reference/sources', [PartnerReferenceController::class, 'storeSource'])->name('api.v1.reference.sources.store');
        Route::patch('/reference/sources/{source_id}', [PartnerReferenceController::class, 'updateSourcePartner'])
            ->whereNumber('source_id')
            ->name('api.v1.reference.sources.update-partner');
        Route::delete('/reference/sources/by-local/{superpart_local_source_id}', [PartnerReferenceController::class, 'destroySourceByLocalId'])
            ->whereNumber('superpart_local_source_id')
            ->name('api.v1.reference.sources.destroy-by-local');
        Route::delete('/reference/sources/{source_id}', [PartnerReferenceController::class, 'destroySource'])
            ->whereNumber('source_id')
            ->name('api.v1.reference.sources.destroy');
        Route::get('/superpart/orders/changes', \App\Http\Controllers\Api\SuperpartOrdersChangesController::class)
            ->name('api.v1.superpart.orders.changes');
        Route::get('/superpart/orders/full', \App\Http\Controllers\Api\SuperpartOrdersFullController::class)
            ->name('api.v1.superpart.orders.full');
        Route::get('/partner-orders/statuses', [PartnerOrderController::class, 'statuses'])
            ->name('api.v1.partner-orders.statuses');
        Route::get('/partner-orders', [PartnerOrderController::class, 'index'])
            ->name('api.v1.partner-orders.index');
        Route::post('/partner-orders', [PartnerOrderController::class, 'store'])->name('api.v1.partner-orders.store');
        Route::get('/partner-orders/{order_id}/status', [PartnerOrderController::class, 'statusShow'])
            ->whereNumber('order_id')
            ->name('api.v1.partner-orders.status.show');
        Route::patch('/partner-orders/{order_id}/status', [PartnerOrderController::class, 'statusUpdate'])
            ->whereNumber('order_id')
            ->name('api.v1.partner-orders.status.update');
        Route::get('/partner-orders/{order_id}', [PartnerOrderController::class, 'show'])
            ->whereNumber('order_id')
            ->name('api.v1.partner-orders.show');
    });

Route::prefix('v1')->group(function () {
    Route::get('/health', [GmController::class, 'health'])->name('api.v1.health');
    Route::get('/ops/health', \App\Http\Controllers\Api\OpsHealthController::class)->name('api.v1.ops.health');

    Route::prefix('desk')
        ->middleware(['throttle:120,1', 'desk.api'])
        ->group(function () {
            Route::post('/auth/login', [\App\Http\Controllers\Api\DeskApiController::class, 'login'])
                ->name('api.v1.desk.auth.login');
            Route::post('/sso/issue', [\App\Http\Controllers\Api\DeskApiController::class, 'issueSso'])
                ->name('api.v1.desk.sso.issue');
            Route::post('/sso/exchange', [\App\Http\Controllers\Api\DeskApiController::class, 'exchangeSso'])
                ->name('api.v1.desk.sso.exchange');
            Route::get('/users/me', [\App\Http\Controllers\Api\DeskApiController::class, 'me'])
                ->name('api.v1.desk.users.me');
            Route::get('/statuses', [\App\Http\Controllers\Api\DeskApiController::class, 'statuses'])
                ->name('api.v1.desk.statuses');
            Route::get('/cities', [\App\Http\Controllers\Api\DeskApiController::class, 'cities'])
                ->name('api.v1.desk.cities');
            Route::get('/org/tree', [\App\Http\Controllers\Api\DeskApiController::class, 'orgTree'])
                ->name('api.v1.desk.org.tree');
            Route::post('/masters/upsert', [\App\Http\Controllers\Api\DeskApiController::class, 'mastersUpsert'])
                ->name('api.v1.desk.masters.upsert');
            Route::get('/orders', [\App\Http\Controllers\Api\DeskApiController::class, 'orders'])
                ->name('api.v1.desk.orders.index');
            Route::get('/orders/{orderId}/status', [\App\Http\Controllers\Api\DeskApiController::class, 'orderStatusShow'])
                ->whereNumber('orderId')->name('api.v1.desk.orders.status.show');
            Route::patch('/orders/{orderId}/status', [\App\Http\Controllers\Api\DeskApiController::class, 'orderStatusUpdate'])
                ->whereNumber('orderId')->name('api.v1.desk.orders.status.update');
            Route::get('/orders/{orderId}', [\App\Http\Controllers\Api\DeskApiController::class, 'orderShow'])
                ->whereNumber('orderId')->name('api.v1.desk.orders.show');
            Route::patch('/orders/{orderId}', [\App\Http\Controllers\Api\DeskApiController::class, 'orderUpdate'])
                ->whereNumber('orderId')->name('api.v1.desk.orders.update');
            Route::post('/orders/{orderId}/close', [\App\Http\Controllers\Api\DeskApiController::class, 'orderClose'])
                ->whereNumber('orderId')->name('api.v1.desk.orders.close');
            Route::post('/orders/{orderId}/documents', [\App\Http\Controllers\Api\DeskApiController::class, 'orderDocumentUpload'])
                ->whereNumber('orderId')->name('api.v1.desk.orders.documents.upload');
            Route::get('/orders/{orderId}/documents/{documentId}', [\App\Http\Controllers\Api\DeskApiController::class, 'orderDocumentShow'])
                ->whereNumber(['orderId', 'documentId'])->name('api.v1.desk.orders.documents.show');
            Route::delete('/orders/{orderId}/documents/{documentId}', [\App\Http\Controllers\Api\DeskApiController::class, 'orderDocumentDelete'])
                ->whereNumber(['orderId', 'documentId'])->name('api.v1.desk.orders.documents.delete');
        });

    Route::prefix('gm')
        ->middleware(['throttle:60,1', VerifyGmApiBearer::class])
        ->group(function () {
            Route::get('/users/me', [GmController::class, 'me'])->name('api.v1.gm.users.me');
            Route::get('/users/me/metrics', [GmController::class, 'metrics'])->name('api.v1.gm.users.me.metrics');
            Route::get('/users/me/rating-history', [GmController::class, 'ratingHistory'])->name('api.v1.gm.users.me.rating-history');
            Route::get('/users/me/reviews', [GmController::class, 'reviews'])->name('api.v1.gm.users.me.reviews');
            Route::get('/users/me/reviews/negative-open', [GmController::class, 'negativeOpenReviews'])->name('api.v1.gm.users.me.reviews.negative-open');
            Route::get('/users/me/messenger', [GmController::class, 'messenger'])->name('api.v1.gm.users.me.messenger');
            Route::post('/users/sync', [GmController::class, 'syncUsers'])->name('api.v1.gm.users.sync');
            Route::post('/users/verify-password', [GmController::class, 'verifyPassword'])
                ->middleware('throttle:gm-verify-password')
                ->name('api.v1.gm.users.verify-password');
            Route::post('/users/change-password', [GmController::class, 'changePassword'])
                ->middleware('throttle:gm-change-password')
                ->name('api.v1.gm.users.change-password');
            Route::get('/users/{userId}/password-status', [GmController::class, 'passwordStatus'])
                ->whereNumber('userId')
                ->name('api.v1.gm.users.password-status');

            // orderId: LC — прежний числовой ID, Единый хаб — canonical «kp-<external_id>»
            $orderIdPattern = '[0-9]+|kp\-[0-9]+';

            Route::get('/orders', [GmController::class, 'orders'])->name('api.v1.gm.orders.index');
            Route::get('/orders/sources', [GmController::class, 'sources'])->name('api.v1.gm.orders.sources');
            Route::get('/orders/{orderId}', [GmController::class, 'orderShow'])->where('orderId', $orderIdPattern)->name('api.v1.gm.orders.show');
            Route::post('/orders/{orderId}/accept', [GmController::class, 'acceptOrder'])->where('orderId', $orderIdPattern)->name('api.v1.gm.orders.accept');
            Route::post('/orders/{orderId}/in-progress', [GmController::class, 'markOrderInProgress'])->where('orderId', $orderIdPattern)->name('api.v1.gm.orders.in-progress');
            Route::post('/orders/{orderId}/review', [GmController::class, 'markOrderReview'])->where('orderId', $orderIdPattern)->name('api.v1.gm.orders.review');
            Route::post('/orders/{orderId}/sd', [GmController::class, 'moveOrderToSd'])->where('orderId', $orderIdPattern)->name('api.v1.gm.orders.sd');
            Route::get('/orders/{orderId}/documents', [GmController::class, 'orderDocuments'])->where('orderId', $orderIdPattern)->name('api.v1.gm.orders.documents');
            Route::post('/orders/{orderId}/documents', [GmController::class, 'uploadOrderDocument'])->where('orderId', $orderIdPattern)->name('api.v1.gm.orders.documents.upload');

            Route::get('/guild-applications', [GmController::class, 'guildApplications'])->name('api.v1.gm.guild-applications');
            Route::get('/org/tree', [GmController::class, 'orgTree'])->name('api.v1.gm.org.tree');
            Route::get('/branch/roster', [GmController::class, 'branchRoster'])->name('api.v1.gm.branch.roster');
            Route::get('/branch/stats', [GmController::class, 'branchStats'])->name('api.v1.gm.branch.stats');
        });
});
