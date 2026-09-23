<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Call;
use App\Models\PersonPhone;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для обработки webhook от Mango Office
 * и управления историей звонков
 */
class MangoCallService
{
    public function __construct(
        private SourceAttributionService $sourceAttribution,
    ) {}

    /**
     * Обработка входящего webhook от Mango Office
     * 
     * Примеры payload:
     * - Входящий звонок: {'event': 'call', 'direction': 'in', 'from': '9001234567', 'to': '123', 'call_id': '...'}
     * - Результат: {'event': 'call_result', 'call_id': '...', 'status': 'Success', 'duration': 120}
     * - Запись: {'event': 'record', 'call_id': '...', 'record_url': 'https://...'}
     */
    public function handleWebhook(array $payload): void
    {
        // Декодируем json, если он передан как строка
        if (isset($payload['json']) && is_string($payload['json'])) {
            $payload = json_decode($payload['json'], true) ?? $payload;
        }

        $event = $payload['event'] ?? $payload['command'] ?? null;

        Log::channel('single')->info('Mango webhook processing', [
            'event' => $event,
            'mango_call_id' => $payload['call_id'] ?? $payload['entry_id'] ?? null,
        ]);

        match ($event) {
            'call' => $this->handleCallEvent($payload),
            'call_result', 'summary' => $this->handleCallResult($payload),
            'record', 'recording' => $this->handleRecording($payload),
            default => Log::channel('single')->info('Unknown Mango event', ['event' => $event]),
        };
    }

    /**
     * Обработка события нового звонка (входящего или исходящего)
     */
    private function handleCallEvent(array $payload): void
    {
        $mangoCallId = $payload['call_id'] ?? $payload['entry_id'] ?? null;
        
        if (!$mangoCallId) {
            Log::channel('single')->warning('Mango call event without call_id');
            return;
        }

        // Определяем направление и номер телефона
        $direction = $this->parseDirection($payload);
        $phone = $this->extractPhone($payload, $direction);
        $internalNumber = $payload['to_number'] ?? $payload['extension'] ?? $payload['to'] ?? null;

        if (!$phone) {
            Log::channel('single')->warning('Mango call event without phone number', [
                'mango_call_id' => $mangoCallId,
            ]);
            return;
        }

        // Нормализуем телефон (10 цифр)
        $normalizedPhone = $this->normalizePhone($phone);

        // Ищем персону по номеру телефона
        $personId = $this->findPersonByPhone($normalizedPhone);

        // Для входящего: линия, на которую позвонили (to) → РК по source_phone / алиас / .env fallback
        $sourceId = null;
        $attributionVia = null;
        if ($direction === 'in') {
            $lineCalled = (string) ($payload['to_number'] ?? $payload['to'] ?? '');
            $resolved = $this->sourceAttribution->resolveForLine($lineCalled);
            $sourceId = $resolved['source_id'];
            $attributionVia = $resolved['via'];
        }

        // Создаём или обновляем запись о звонке
        $call = Call::updateOrCreate(
            ['mango_call_id' => $mangoCallId],
            [
                'person_id' => $personId,
                'phone' => $normalizedPhone,
                'direction' => $direction,
                'status' => 'initiated',
                'internal_number' => $internalNumber,
                'source_id' => $sourceId,
            ]
        );

        Log::channel('single')->info('Call created/updated', [
            'call_id' => $call->call_id,
            'mango_call_id' => $mangoCallId,
            'person_id' => $personId,
            'source_id' => $sourceId,
            'attribution_via' => $attributionVia,
        ]);
    }

    /**
     * Обработка результата завершённого звонка
     */
    private function handleCallResult(array $payload): void
    {
        $mangoCallId = $payload['call_id'] ?? $payload['entry_id'] ?? null;
        
        if (!$mangoCallId) {
            return;
        }

        $call = Call::where('mango_call_id', $mangoCallId)->first();
        
        if (!$call) {
            // Создаём запись, если не нашли (звонок мог быть пропущен)
            $direction = $this->parseDirection($payload);
            $phone = $this->extractPhone($payload, $direction);
            
            if ($phone) {
                $normalizedPhone = $this->normalizePhone($phone);
                $personId = $this->findPersonByPhone($normalizedPhone);
                $sourceId = null;
                if ($direction === 'in') {
                    $lineCalled = (string) ($payload['to_number'] ?? $payload['to'] ?? '');
                    $sourceId = $this->sourceAttribution->getSourceIdForLine($lineCalled);
                }

                $call = Call::create([
                    'mango_call_id' => $mangoCallId,
                    'person_id' => $personId,
                    'phone' => $normalizedPhone,
                    'direction' => $direction,
                    'status' => $this->parseStatus($payload),
                    'duration' => $payload['duration'] ?? $payload['talk_duration'] ?? null,
                    'source_id' => $sourceId,
                ]);
            }
            return;
        }

        // Обновляем существующую запись
        $call->update([
            'status' => $this->parseStatus($payload),
            'duration' => $payload['duration'] ?? $payload['talk_duration'] ?? $call->duration,
        ]);

        Log::channel('single')->info('Call result updated', [
            'call_id' => $call->call_id,
            'status' => $call->status,
            'duration' => $call->duration,
        ]);
    }

    /**
     * Обработка события записи разговора
     */
    private function handleRecording(array $payload): void
    {
        $mangoCallId = $payload['call_id'] ?? $payload['entry_id'] ?? null;
        $recordUrl = $payload['record_url'] ?? $payload['recording_url'] ?? $payload['url'] ?? null;
        
        if (!$mangoCallId || !$recordUrl) {
            return;
        }

        $call = Call::where('mango_call_id', $mangoCallId)->first();
        
        if ($call) {
            $call->update(['record_url' => $recordUrl]);
            
            Log::channel('single')->info('Call record URL updated', [
                'call_id' => $call->call_id,
            ]);
        }
    }

    /**
     * Поиск персоны по номеру телефона
     */
    public function findPersonByPhone(string $phone): ?int
    {
        $personPhone = PersonPhone::where('phone_number', $phone)->first();
        
        return $personPhone?->person_id;
    }

    /**
     * Нормализация номера телефона (оставляем 10 цифр)
     */
    private function normalizePhone(string $phone): string
    {
        return PhoneHelper::normalize($phone);
    }

    /**
     * Определение направления звонка
     */
    private function parseDirection(array $payload): string
    {
        $direction = $payload['direction'] ?? $payload['call_direction'] ?? null;
        
        if ($direction) {
            return match (strtolower($direction)) {
                'in', 'inbound', 'incoming' => 'in',
                'out', 'outbound', 'outgoing' => 'out',
                default => $direction,
            };
        }
        
        // Определяем по наличию полей
        if (isset($payload['from_number']) && !isset($payload['to_number'])) {
            return 'in';
        }
        
        return 'in'; // По умолчанию входящий
    }

    /**
     * Извлечение номера телефона клиента из payload
     */
    private function extractPhone(array $payload, string $direction): ?string
    {
        // Для входящего звонка — номер клиента в from
        if ($direction === 'in') {
            return $payload['from'] ?? $payload['from_number'] ?? $payload['caller_id'] ?? null;
        }
        
        // Для исходящего — номер клиента в to
        return $payload['to'] ?? $payload['to_number'] ?? $payload['called_number'] ?? null;
    }

    /**
     * Парсинг статуса звонка из Mango
     */
    private function parseStatus(array $payload): string
    {
        $status = $payload['status'] ?? $payload['call_status'] ?? $payload['result'] ?? 'unknown';
        
        return match (strtolower($status)) {
            'success', 'answer', 'answered' => 'result',
            'busy' => 'busy',
            'noanswer', 'no_answer', 'no answer' => 'no_answer',
            'cancel', 'cancelled' => 'missed',
            'failed', 'fail' => 'failed',
            default => strtolower($status),
        };
    }

    /**
     * Получить историю звонков для персоны
     */
    public function getCallsForPerson(int $personId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return Call::where('person_id', $personId)
            ->orderBy('call_created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Получить звонки без привязки к персоне (для возможного ручного связывания)
     */
    public function getUnlinkedCalls(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return Call::whereNull('person_id')
            ->orderBy('call_created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
