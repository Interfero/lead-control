<?php

namespace App\Console\Commands;

use Database\Seeders\CfmCategorySeeder;
use Illuminate\Console\Command;

class SyncCfmCategoriesCommand extends Command
{
    protected $signature = 'cfm:sync-categories';

    protected $description = 'Синхронизировать статьи ДДС из CfmCategorySeeder';

    public function handle(): int
    {
        $this->info('Синхронизация статей кассы…');
        (new CfmCategorySeeder)->run();
        $this->info('Готово. Новые статьи появятся в Касса → Расход.');

        return self::SUCCESS;
    }
}
