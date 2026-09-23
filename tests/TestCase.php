<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $this->guardAgainstNonTestDatabases($app);

        return $app;
    }

    /**
     * Тесты мигрируют и дропают таблицы. Закэшированный конфиг (bootstrap/cache/config.php)
     * перекрывает env из phpunit.xml, поэтому без этой проверки прогон на боевом хосте
     * выполнит DDL по рабочей базе. Падаем до первого запроса к БД.
     */
    private function guardAgainstNonTestDatabases($app): void
    {
        $connections = array_unique(array_filter([
            (string) $app['config']->get('database.default'),
            'hub',
        ]));

        foreach ($connections as $name) {
            $config = $app['config']->get('database.connections.'.$name);
            if (! is_array($config)) {
                continue;
            }

            // sqlite (:memory: или файл в репозитории) боевыми данными не является
            if ((string) ($config['driver'] ?? '') === 'sqlite') {
                continue;
            }

            $database = strtolower((string) ($config['database'] ?? ''));
            if ($database === '' || str_contains($database, 'test')) {
                continue;
            }

            throw new \RuntimeException(
                'Отказ запускать тесты: соединение "'.$name.'" указывает на базу "'.$database.'", '
                .'в имени которой нет "test". Тесты выполняют DDL и уничтожат данные. '
                .'Обычно причина — закэшированный конфиг: php artisan config:clear.'
            );
        }
    }
}
