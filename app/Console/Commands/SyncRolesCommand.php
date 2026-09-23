<?php

namespace App\Console\Commands;

use App\Services\HrService;
use App\Services\OrderActivityLogService;
use Illuminate\Console\Command;

class SyncRolesCommand extends Command
{
    protected $signature = 'roles:sync';

    protected $description = 'Создать/обновить служебные роли и таблицу истории заказов';

    public function handle(HrService $hrService, OrderActivityLogService $activityLog): int
    {
        $this->info('Синхронизация ролей…');
        $hrService->ensureBuiltinRoles();
        $this->info('Таблица order_activity_logs…');
        $activityLog->ensureTable();
        $this->info('Готово.');

        return self::SUCCESS;
    }
}
