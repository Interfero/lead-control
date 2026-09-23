<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Минимальные внешние алерты §6.5 (Telegram, опционально).
 */
class OpsAlertService
{
    public function notify(string $subject, string $body): void
    {
        $token = trim((string) config('ops.alert.telegram_bot_token'));
        $chatId = trim((string) config('ops.alert.telegram_chat_id'));

        if ($token !== '' && $chatId !== '') {
            $text = $subject."\n\n".$body;
            if (strlen($text) > 4000) {
                $text = substr($text, 0, 3997).'...';
            }
            try {
                $response = Http::timeout(10)->post(
                    'https://api.telegram.org/bot'.$token.'/sendMessage',
                    ['chat_id' => $chatId, 'text' => $text]
                );
                if (! $response->successful()) {
                    Log::warning('ops alert telegram failed', ['status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('ops alert telegram exception', ['error' => $e->getMessage()]);
            }
        }

        Log::channel('single')->warning('ops alert', ['subject' => $subject, 'body' => $body]);
    }
}
