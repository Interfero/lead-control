<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MangoCallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Контроллер для приёма webhook от Mango Office
 */
class MangoWebhookController extends Controller
{
    public function __construct(
        private MangoCallService $mangoCallService
    ) {}

    /**
     * Приём webhook от Mango Office.
     * В настройках Mango указать URL: https://your-domain.com/api/mango/webhook
     * 
     * Типы событий от Mango:
     * - call (входящий/исходящий звонок)
     * - call_result (результат завершённого звонка)
     * - record (запись разговора готова)
     */
    public function __invoke(Request $request): Response
    {
        Log::channel('single')->info('Mango webhook received', [
            'ip' => $request->ip(),
            'has_json' => $request->filled('json'),
            'has_sign' => $request->filled('sign'),
        ]);

        // Проверка подписи запроса (в production без секрета запросы отклоняются)
        if (!$this->verifySignature($request)) {
            Log::channel('single')->warning('Mango webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);
            // Возвращаем 200 OK даже при ошибке подписи, чтобы Mango не повторял запросы
            return response()->noContent();
        }

        try {
            // Обработка webhook через сервис
            $this->mangoCallService->handleWebhook($request->all());
        } catch (\Exception $e) {
            Log::channel('single')->error('Mango webhook processing error', [
                'error' => $e->getMessage(),
            ]);
        }

        // Всегда возвращаем 200 OK, чтобы Mango не повторял запрос
        return response()->noContent();
    }

    /**
     * Проверка подписи запроса от Mango
     * Mango отправляет sign = sha256(api_key + json + api_salt)
     */
    private function verifySignature(Request $request): bool
    {
        $webhookSecret = config('services.mango.webhook_secret');

        // Локально/тест: без секрета подпись не проверяем. Production: пока секрет не задан — вебхук отключён.
        if (empty($webhookSecret)) {
            return ! app()->environment('production');
        }

        $sign = $request->input('sign');
        $json = $request->input('json');
        $apiKey = config('services.mango.api_key');
        $apiSalt = config('services.mango.api_salt');

        if (empty($sign) || empty($json)) {
            return false;
        }

        $expectedSign = hash('sha256', $apiKey . $json . $apiSalt);
        
        return hash_equals($expectedSign, $sign);
    }
}
