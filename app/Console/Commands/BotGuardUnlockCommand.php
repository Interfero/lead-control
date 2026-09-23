<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BotGuardService;
use Illuminate\Console\Command;

class BotGuardUnlockCommand extends Command
{
    protected $signature = 'bot-guard:unlock {user : user_id или email}';

    protected $description = 'Снять временную блокировку BotGuard с пользователя';

    public function handle(BotGuardService $botGuard): int
    {
        $ref = (string) $this->argument('user');
        $user = ctype_digit($ref)
            ? User::query()->find((int) $ref)
            : User::query()->where('email', strtolower($ref))->first();

        if (! $user) {
            $this->error('Пользователь не найден');

            return self::FAILURE;
        }

        $botGuard->unlock((int) $user->user_id);
        $this->info("Unlocked user_id={$user->user_id} ({$user->email})");

        return self::SUCCESS;
    }
}
