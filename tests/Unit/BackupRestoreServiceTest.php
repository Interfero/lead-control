<?php

namespace Tests\Unit;

use App\Services\BackupEncryptionService;
use App\Services\BackupRestoreService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRestoreServiceTest extends TestCase
{
    public function test_encrypt_then_verify_roundtrip_sqlite(): void
    {
        if (! extension_loaded('openssl')) {
            $this->markTestSkipped('openssl required');
        }

        $tmp = storage_path('framework/testing/backups-'.uniqid('', true));
        File::ensureDirectoryExists($tmp, 0755);

        config([
            'backup.path' => $tmp.'/archives',
            'backup.keys_path' => $tmp.'/keys',
            'backup.aegis_cli' => '',
            'backup.openssl_gost_conf' => '',
        ]);

        $dbFile = $tmp.'/test.sqlite';
        file_put_contents($dbFile, 'SQLITE roundtrip '.random_bytes(8));
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $dbFile,
        ]);

        $encrypt = app(BackupEncryptionService::class);
        $created = $encrypt->run('unit-test');
        $this->assertTrue($created['ok']);

        $verify = app(BackupRestoreService::class);
        $result = $verify->verify($created['dir'], false);
        $this->assertTrue($result['ok']);
        $this->assertGreaterThan(0, $result['gzip_bytes']);

        File::deleteDirectory($tmp);
    }
}
