<?php

namespace App\Services;

use App\Contracts\AtsProviderInterface;
use App\Helpers\PhoneHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Реализация провайдера АТС для Mango Office.
 * Используется для инициации исходящих звонков из CRM.
 */
class MangoApiService implements AtsProviderInterface
{
    private string $apiUrl;
    private string $apiKey;
    private string $apiSalt;

    public function __construct()
    {
        $this->apiUrl = config('services.mango.api_url', 'https://app.mango-office.ru/vpbx');
        $this->apiKey = config('services.mango.api_key', '');
        $this->apiSalt = config('services.mango.api_salt', '');
    }

    /**
     * Инициация исходящего звонка
     * Mango соединит оператора с клиентом
     * 
     * @param string $fromExtension Внутренний номер оператора (например, 101)
     * @param string $toNumber Номер телефона клиента (10 цифр)
     * @return array Результат запроса
     */
    public function initiateCall(string $fromExtension, string $toNumber): array
    {
        $command = 'commands/callback';
        
        // Нормализуем номер телефона клиента
        $toNumber = $this->normalizePhone($toNumber);
        
        $json = json_encode([
            'command_id' => $this->generateCommandId(),
            'from' => [
                'extension' => $fromExtension,
            ],
            'to_number' => $this->formatPhoneForApi($toNumber),
        ]);

        $sign = $this->generateSign($json);

        try {
            $response = Http::asForm()->post("{$this->apiUrl}/{$command}", [
                'vpbx_api_key' => $this->apiKey,
                'sign' => $sign,
                'json' => $json,
            ]);

            $result = $response->json() ?? [];

            Log::channel('single')->info('Mango API call initiated', [
                'from_extension' => $fromExtension,
                'to_number' => $toNumber,
                'response' => $result,
                'status_code' => $response->status(),
            ]);

            return [
                'success' => $response->successful() && ($result['result'] ?? null) === 1000,
                'data' => $result,
                'error' => $result['message'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::channel('single')->error('Mango API call failed', [
                'from_extension' => $fromExtension,
                'to_number' => $toNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Получить список записей разговоров
     * 
     * @param string|null $callId ID звонка в Mango
     * @param string|null $dateFrom Начальная дата (Y-m-d)
     * @param string|null $dateTo Конечная дата (Y-m-d)
     * @return array
     */
    public function getRecordings(?string $callId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $command = 'queries/recording/post';
        
        $params = [];
        
        if ($callId) {
            $params['recording_id'] = $callId;
        }
        
        if ($dateFrom) {
            $params['date_from'] = strtotime($dateFrom);
        }
        
        if ($dateTo) {
            $params['date_to'] = strtotime($dateTo);
        }

        $json = json_encode($params);
        $sign = $this->generateSign($json);

        try {
            $response = Http::asForm()->post("{$this->apiUrl}/{$command}", [
                'vpbx_api_key' => $this->apiKey,
                'sign' => $sign,
                'json' => $json,
            ]);

            return [
                'success' => $response->successful(),
                'data' => $response->json() ?? [],
            ];
        } catch (\Exception $e) {
            Log::channel('single')->error('Mango API get recordings failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Получить информацию о текущих звонках
     * 
     * @return array
     */
    public function getActiveCalls(): array
    {
        $command = 'queries/realtime_calls/get';
        
        $json = json_encode([]);
        $sign = $this->generateSign($json);

        try {
            $response = Http::asForm()->post("{$this->apiUrl}/{$command}", [
                'vpbx_api_key' => $this->apiKey,
                'sign' => $sign,
                'json' => $json,
            ]);

            return [
                'success' => $response->successful(),
                'data' => $response->json() ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Проверка доступности API Mango
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSalt);
    }

    /**
     * Генерация подписи запроса
     * sign = sha256(api_key + json + api_salt)
     */
    private function generateSign(string $json): string
    {
        return hash('sha256', $this->apiKey . $json . $this->apiSalt);
    }

    /**
     * Генерация уникального ID команды
     */
    private function generateCommandId(): string
    {
        return uniqid('crm_', true);
    }

    /**
     * Нормализация номера телефона (10 цифр)
     */
    private function normalizePhone(string $phone): string
    {
        return PhoneHelper::normalize($phone);
    }

    /**
     * Форматирование номера для API (добавляем 7)
     */
    private function formatPhoneForApi(string $phone): string
    {
        if (strlen($phone) === 10) {
            return '7' . $phone;
        }
        
        return $phone;
    }
}
