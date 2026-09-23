<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Ночной бэкап БД: gzip → AEGIS-256 (или AES-GCM) → манифест + ГОСТ-подпись.
 * См. docs/ADR-crypto-backup.md
 */
class BackupEncryptionService
{
    private const AAD = 'lead-control-backup-v1';

    /**
     * @return array{ok: bool, dir: string, cipher_algo: string, sign_scheme: string, size_bytes: int}
     */
    public function run(?string $label = null): array
    {
        $stamp = now()->format('Y-m-d_His');
        $label = $label ?: $stamp;
        $backupRoot = rtrim((string) config('backup.path'), '/\\');
        $keysPath = rtrim((string) config('backup.keys_path'), '/\\');
        $dir = $backupRoot.DIRECTORY_SEPARATOR.$label;

        File::ensureDirectoryExists($backupRoot, 0750);
        File::ensureDirectoryExists($keysPath, 0700);
        File::ensureDirectoryExists($dir, 0750);

        $sqlGz = $dir.DIRECTORY_SEPARATOR.'database.sql.gz';
        $encPath = $dir.DIRECTORY_SEPARATOR.'database.sql.gz.enc';
        $noncePath = $dir.DIRECTORY_SEPARATOR.'aead.nonce';
        $manifestPath = $dir.DIRECTORY_SEPARATOR.'manifest.json';
        $sigPath = $dir.DIRECTORY_SEPARATOR.'manifest.sig.json';

        $this->dumpDatabaseGzip($sqlGz);
        $plainSize = (int) filesize($sqlGz);

        $cipherAlgo = $this->encryptFile($sqlGz, $encPath, $noncePath, $keysPath);
        @unlink($sqlGz);

        $manifest = [
            'app' => config('app.name'),
            'env' => config('app.env'),
            'created_at' => now()->toIso8601String(),
            'label' => $label,
            'cipher_algo' => $cipherAlgo,
            'aad' => self::AAD,
            'ciphertext_sha256' => hash_file('sha256', $encPath),
            'plaintext_gzip_bytes' => $plainSize,
            'ciphertext_bytes' => (int) filesize($encPath),
            'db_database' => config('database.connections.mysql.database'),
        ];
        file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");

        $signScheme = $this->signManifest($manifestPath, $sigPath, $keysPath);

        $this->pruneOldBackups($backupRoot, (int) config('backup.retain_days', 30));

        Log::channel('single')->info('backup:encrypt ok', [
            'dir' => $dir,
            'cipher_algo' => $cipherAlgo,
            'sign_scheme' => $signScheme,
            'size' => (int) filesize($encPath),
        ]);

        return [
            'ok' => true,
            'dir' => $dir,
            'cipher_algo' => $cipherAlgo,
            'sign_scheme' => $signScheme,
            'size_bytes' => (int) filesize($encPath),
        ];
    }

    private function dumpDatabaseGzip(string $sqlGz): void
    {
        $connection = config('database.default');
        if ($connection !== 'mysql') {
            // sqlite / testing: copy empty placeholder
            $db = config('database.connections.'.$connection.'.database');
            if (is_string($db) && is_file($db)) {
                $gz = gzopen($sqlGz, 'wb9');
                if ($gz === false) {
                    throw new RuntimeException('gzopen failed');
                }
                gzwrite($gz, file_get_contents($db) ?: '');
                gzclose($gz);

                return;
            }
            throw new RuntimeException('backup:encrypt supports mysql (or sqlite file) only, got '.$connection);
        }

        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (string) config('database.connections.mysql.port', '3306');
        $db = (string) config('database.connections.mysql.database');
        $user = (string) config('database.connections.mysql.username');
        $pass = (string) config('database.connections.mysql.password');
        $mysqldump = (string) config('backup.mysqldump', 'mysqldump');

        $tmpCnf = tempnam(sys_get_temp_dir(), 'mycnf');
        if ($tmpCnf === false) {
            throw new RuntimeException('tempnam failed');
        }
        file_put_contents($tmpCnf, "[client]\nuser={$user}\npassword=\"{$pass}\"\nhost={$host}\nport={$port}\n");
        chmod($tmpCnf, 0600);

        $cmd = sprintf(
            '%s --defaults-extra-file=%s --single-transaction --quick --routines --triggers %s | gzip -c > %s',
            escapeshellcmd($mysqldump),
            escapeshellarg($tmpCnf),
            escapeshellarg($db),
            escapeshellarg($sqlGz)
        );

        $output = [];
        $code = 0;
        exec($cmd.' 2>&1', $output, $code);
        @unlink($tmpCnf);

        if ($code !== 0 || ! is_file($sqlGz) || filesize($sqlGz) === 0) {
            throw new RuntimeException('mysqldump failed: '.implode("\n", $output));
        }
    }

    private function encryptFile(string $plainPath, string $encPath, string $noncePath, string $keysPath): string
    {
        $cli = $this->resolveAegisCli();
        $keyPath = $keysPath.DIRECTORY_SEPARATOR.'aead.key';

        if ($cli !== null) {
            if (! is_file($keyPath)) {
                file_put_contents($keyPath, random_bytes(32));
                chmod($keyPath, 0600);
            }
            $nonce = random_bytes(32);
            file_put_contents($noncePath, $nonce);
            chmod($noncePath, 0600);

            $cmd = sprintf(
                '%s encrypt %s %s %s %s 2>&1',
                escapeshellarg($cli),
                escapeshellarg($plainPath),
                escapeshellarg($encPath),
                escapeshellarg($keyPath),
                escapeshellarg($noncePath)
            );
            $out = shell_exec($cmd);
            if (! is_file($encPath) || filesize($encPath) === 0) {
                throw new RuntimeException('aegis256-file encrypt failed: '.$out);
            }

            return 'aegis-256-libaegis-cli';
        }

        if (function_exists('sodium_crypto_aead_aegis256_encrypt')) {
            if (! is_file($keyPath) || filesize($keyPath) !== SODIUM_CRYPTO_AEAD_AEGIS256_KEYBYTES) {
                file_put_contents($keyPath, sodium_crypto_aead_aegis256_keygen());
                chmod($keyPath, 0600);
            }
            $key = file_get_contents($keyPath);
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AEGIS256_NPUBBYTES);
            file_put_contents($noncePath, $nonce);
            $plain = file_get_contents($plainPath);
            $cipher = sodium_crypto_aead_aegis256_encrypt($plain, self::AAD, $nonce, $key);
            file_put_contents($encPath, $cipher);

            return 'aegis-256-php-sodium';
        }

        // Fallback AES-256-GCM (ADR)
        if (! is_file($keyPath) || filesize($keyPath) !== 32) {
            file_put_contents($keyPath, random_bytes(32));
            chmod($keyPath, 0600);
        }
        $key = file_get_contents($keyPath);
        $nonce = random_bytes(12);
        file_put_contents($noncePath, $nonce);
        $tag = '';
        $plain = file_get_contents($plainPath);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD, 16);
        if ($cipher === false) {
            throw new RuntimeException('openssl_encrypt failed');
        }
        file_put_contents($encPath, $cipher.$tag);

        return 'aes-256-gcm';
    }

    private function signManifest(string $manifestPath, string $sigPath, string $keysPath): string
    {
        $gostConf = (string) config('backup.openssl_gost_conf');
        $envPrefix = is_file($gostConf) ? 'OPENSSL_CONF='.escapeshellarg($gostConf).' ' : '';
        $keyFile = $keysPath.DIRECTORY_SEPARATOR.'gost.key';
        $pubFile = $keysPath.DIRECTORY_SEPARATOR.'gost.pub';

        if ($envPrefix !== '') {
            if (! is_file($keyFile)) {
                $gen = shell_exec($envPrefix.'openssl genpkey -algorithm gost2012_256 -pkeyopt paramset:A -out '.escapeshellarg($keyFile).' 2>&1');
                if (! is_file($keyFile) || filesize($keyFile) === 0) {
                    Log::warning('backup GOST keygen failed, using HMAC', ['out' => $gen]);
                } else {
                    chmod($keyFile, 0600);
                    shell_exec($envPrefix.'openssl pkey -in '.escapeshellarg($keyFile).' -pubout -out '.escapeshellarg($pubFile).' 2>&1');
                    chmod($pubFile, 0640);
                }
            }
        }

        if (is_file($keyFile) && $envPrefix !== '') {
            $sigBin = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lc-manifest-'.bin2hex(random_bytes(4)).'.sig.bin';
            shell_exec($envPrefix.'openssl dgst -md_gost12_256 -sign '.escapeshellarg($keyFile).' -out '.escapeshellarg($sigBin).' '.escapeshellarg($manifestPath).' 2>&1');
            $verify = '';
            if (is_file($pubFile)) {
                $verify = (string) shell_exec($envPrefix.'openssl dgst -md_gost12_256 -verify '.escapeshellarg($pubFile).' -signature '.escapeshellarg($sigBin).' '.escapeshellarg($manifestPath).' 2>&1');
            }
            if (is_file($sigBin) && filesize($sigBin) > 0 && str_contains(strtolower($verify), 'verified')) {
                file_put_contents($sigPath, json_encode([
                    'scheme' => 'gost3410-2012-256+streebog256',
                    'signature_b64' => base64_encode((string) file_get_contents($sigBin)),
                    'verify' => trim($verify),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
                @unlink($sigBin);

                return 'gost3410-2012-256';
            }
            @unlink($sigBin);
        }

        $hmacKeyPath = $keysPath.DIRECTORY_SEPARATOR.'manifest.hmac.key';
        if (! is_file($hmacKeyPath)) {
            file_put_contents($hmacKeyPath, random_bytes(32));
            chmod($hmacKeyPath, 0600);
        }
        $mac = hash_hmac('sha256', (string) file_get_contents($manifestPath), (string) file_get_contents($hmacKeyPath), true);
        file_put_contents($sigPath, json_encode([
            'scheme' => 'hmac-sha256-TEMPORARY',
            'signature_b64' => base64_encode($mac),
        ], JSON_PRETTY_PRINT)."\n");

        return 'hmac-sha256-TEMPORARY';
    }

    private function resolveAegisCli(): ?string
    {
        $configured = trim((string) config('backup.aegis_cli', 'aegis256-file'));
        if ($configured === '') {
            return null;
        }
        if (is_file($configured) && is_executable($configured)) {
            return $configured;
        }
        $which = trim((string) shell_exec('command -v '.escapeshellarg($configured).' 2>/dev/null'));

        return $which !== '' ? $which : null;
    }

    private function pruneOldBackups(string $backupRoot, int $retainDays): void
    {
        if ($retainDays < 1 || ! is_dir($backupRoot)) {
            return;
        }
        $cutoff = now()->subDays($retainDays)->getTimestamp();
        foreach (File::directories($backupRoot) as $dir) {
            if (filemtime($dir) !== false && filemtime($dir) < $cutoff) {
                File::deleteDirectory($dir);
            }
        }
    }
}
