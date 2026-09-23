<?php

namespace App\Console\Commands;

use App\Services\BackupEncryptionService;
use App\Services\OpsAlertService;
use Illuminate\Console\Command;

class BackupEncryptCommand extends Command
{
    protected $signature = 'backup:encrypt {--label= : Имя каталога бэкапа}';

    protected $description = 'Дамп БД → gzip → AEGIS-256 (или AES-GCM) → манифест + ГОСТ-подпись';

    public function handle(BackupEncryptionService $backup, OpsAlertService $alerts): int
    {
        $this->info('backup:encrypt starting…');

        try {
            $result = $backup->run($this->option('label') ?: null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $alerts->notify('[Lead Control] backup:encrypt FAILED', $e->getMessage());

            return self::FAILURE;
        }

        $this->info('ok cipher='.$result['cipher_algo'].' sign='.$result['sign_scheme']);
        $this->line('dir='.$result['dir']);
        $this->line('size='.$result['size_bytes'].' bytes');

        return self::SUCCESS;
    }
}
