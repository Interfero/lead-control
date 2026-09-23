<?php

namespace App\Console\Commands;

use App\Services\BackupRestoreService;
use App\Services\OpsAlertService;
use Illuminate\Console\Command;

class BackupVerifyCommand extends Command
{
    protected $signature = 'backup:verify
        {--label= : Каталог бэкапа под BACKUP_PATH}
        {--dir= : Полный путь к каталогу бэкапа}
        {--keep : Оставить database.sql.gz.restored после проверки}';

    protected $description = 'Проверка бэкапа: подпись манифеста, decrypt, gzip -t';

    public function handle(BackupRestoreService $restore, OpsAlertService $alerts): int
    {
        $dir = $this->option('dir');
        if (! is_string($dir) || $dir === '') {
            $label = $this->option('label');
            if (is_string($label) && $label !== '') {
                $root = rtrim((string) config('backup.path'), '/\\');
                $dir = $root.DIRECTORY_SEPARATOR.$label;
            } else {
                $dir = $restore->latestBackupDir();
            }
        }

        if ($dir === null || $dir === '') {
            $this->error('No backup directory found. Use --label or --dir.');

            return self::FAILURE;
        }

        $this->info('backup:verify dir='.$dir);

        try {
            $result = $restore->verify($dir, (bool) $this->option('keep'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $alerts->notify('[Lead Control] backup:verify FAILED', $dir."\n".$e->getMessage());

            return self::FAILURE;
        }

        $this->info('ok cipher='.$result['cipher_algo'].' sign='.$result['sign_scheme']);
        $this->line('gzip_bytes='.$result['gzip_bytes']);
        if ($result['sql_gz_path']) {
            $this->line('restored='.$result['sql_gz_path']);
        }

        return self::SUCCESS;
    }
}
