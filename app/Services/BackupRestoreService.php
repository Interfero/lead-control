<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Проверка бэкапа: подпись манифеста → decrypt → gzip test (ТЗ §12.B restore smoke).
 */
class BackupRestoreService
{
    private const AAD = 'lead-control-backup-v1';

    /**
     * @return array{
     *     ok: bool,
     *     dir: string,
     *     cipher_algo: string,
     *     sign_scheme: string,
     *     gzip_bytes: int,
     *     sql_gz_path: string|null
     * }
     */
    public function verify(string $backupDir, bool $keepPlainGzip = false): array
    {
        $backupDir = rtrim($backupDir, '/\\');
        if (! is_dir($backupDir)) {
            throw new RuntimeException('backup dir not found: '.$backupDir);
        }

        $manifestPath = $backupDir.DIRECTORY_SEPARATOR.'manifest.json';
        $sigPath = $backupDir.DIRECTORY_SEPARATOR.'manifest.sig.json';
        $encPath = $backupDir.DIRECTORY_SEPARATOR.'database.sql.gz.enc';
        $noncePath = $backupDir.DIRECTORY_SEPARATOR.'aead.nonce';

        if (! is_file($manifestPath) || ! is_file($encPath) || ! is_file($noncePath)) {
            throw new RuntimeException('missing manifest, enc or nonce in '.$backupDir);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            throw new RuntimeException('invalid manifest.json');
        }

        $signScheme = $this->verifyManifestSignature($manifestPath, $sigPath);

        $expectedCipherHash = (string) ($manifest['ciphertext_sha256'] ?? '');
        if ($expectedCipherHash !== '' && ! hash_equals($expectedCipherHash, hash_file('sha256', $encPath))) {
            throw new RuntimeException('ciphertext sha256 mismatch');
        }

        $keysPath = rtrim((string) config('backup.keys_path'), '/\\');
        $cipherAlgo = (string) ($manifest['cipher_algo'] ?? '');
        $sqlGz = $backupDir.DIRECTORY_SEPARATOR.'database.sql.gz.restored';

        $this->decryptFile($encPath, $sqlGz, $noncePath, $keysPath, $cipherAlgo);

        $gzipBytes = (int) filesize($sqlGz);
        $expectedPlain = (int) ($manifest['plaintext_gzip_bytes'] ?? 0);
        if ($expectedPlain > 0 && $gzipBytes !== $expectedPlain) {
            @unlink($sqlGz);
            throw new RuntimeException("gzip size mismatch: got {$gzipBytes}, expected {$expectedPlain}");
        }

        $this->assertGzipValid($sqlGz);

        if (! $keepPlainGzip) {
            @unlink($sqlGz);
            $sqlGz = null;
        }

        Log::channel('single')->info('backup:verify ok', [
            'dir' => $backupDir,
            'cipher_algo' => $cipherAlgo,
            'sign_scheme' => $signScheme,
            'gzip_bytes' => $gzipBytes,
        ]);

        return [
            'ok' => true,
            'dir' => $backupDir,
            'cipher_algo' => $cipherAlgo,
            'sign_scheme' => $signScheme,
            'gzip_bytes' => $gzipBytes,
            'sql_gz_path' => $sqlGz,
        ];
    }

    /**
     * Последний каталог бэкапа по mtime в BACKUP_PATH.
     */
    public function latestBackupDir(): ?string
    {
        $backupRoot = rtrim((string) config('backup.path'), '/\\');
        if (! is_dir($backupRoot)) {
            return null;
        }
        $dirs = File::directories($backupRoot);
        if ($dirs === []) {
            return null;
        }
        usort($dirs, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        return $dirs[0];
    }

    private function verifyManifestSignature(string $manifestPath, string $sigPath): string
    {
        if (! is_file($sigPath)) {
            throw new RuntimeException('missing manifest.sig.json');
        }
        $sig = json_decode((string) file_get_contents($sigPath), true);
        if (! is_array($sig)) {
            throw new RuntimeException('invalid manifest.sig.json');
        }
        $scheme = (string) ($sig['scheme'] ?? '');

        $keysPath = rtrim((string) config('backup.keys_path'), '/\\');
        $gostConf = (string) config('backup.openssl_gost_conf');
        $envPrefix = is_file($gostConf) ? 'OPENSSL_CONF='.escapeshellarg($gostConf).' ' : '';

        if (str_starts_with($scheme, 'gost') && $envPrefix !== '') {
            $pubFile = $keysPath.DIRECTORY_SEPARATOR.'gost.pub';
            $sigB64 = (string) ($sig['signature_b64'] ?? '');
            if (! is_file($pubFile) || $sigB64 === '') {
                throw new RuntimeException('GOST sig present but gost.pub missing');
            }
            $sigBin = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lc-verify-'.bin2hex(random_bytes(4)).'.sig.bin';
            file_put_contents($sigBin, base64_decode($sigB64, true) ?: '');
            $verify = (string) shell_exec($envPrefix.'openssl dgst -md_gost12_256 -verify '.escapeshellarg($pubFile)
                .' -signature '.escapeshellarg($sigBin).' '.escapeshellarg($manifestPath).' 2>&1');
            @unlink($sigBin);
            if (! str_contains(strtolower($verify), 'verified')) {
                throw new RuntimeException('GOST manifest verify failed: '.trim($verify));
            }

            return 'gost3410-2012-256';
        }

        if ($scheme === 'hmac-sha256-TEMPORARY') {
            $hmacKeyPath = $keysPath.DIRECTORY_SEPARATOR.'manifest.hmac.key';
            $sigB64 = (string) ($sig['signature_b64'] ?? '');
            if (! is_file($hmacKeyPath) || $sigB64 === '') {
                throw new RuntimeException('HMAC sig verify failed: missing key or signature');
            }
            $expected = base64_decode($sigB64, true);
            $actual = hash_hmac('sha256', (string) file_get_contents($manifestPath), (string) file_get_contents($hmacKeyPath), true);
            if ($expected === false || ! hash_equals($expected, $actual)) {
                throw new RuntimeException('HMAC manifest verify failed');
            }

            return 'hmac-sha256-TEMPORARY';
        }

        throw new RuntimeException('unknown sign scheme: '.$scheme);
    }

    private function decryptFile(string $encPath, string $outPath, string $noncePath, string $keysPath, string $cipherAlgo): void
    {
        $keyPath = $keysPath.DIRECTORY_SEPARATOR.'aead.key';
        if (! is_file($keyPath)) {
            throw new RuntimeException('aead.key not found in '.$keysPath);
        }

        if (str_starts_with($cipherAlgo, 'aegis-256-libaegis')) {
            $cli = $this->resolveAegisCli();
            if ($cli === null) {
                throw new RuntimeException('aegis256-file CLI required for this backup');
            }
            $cmd = sprintf(
                '%s decrypt %s %s %s %s 2>&1',
                escapeshellarg($cli),
                escapeshellarg($encPath),
                escapeshellarg($outPath),
                escapeshellarg($keyPath),
                escapeshellarg($noncePath)
            );
            $out = shell_exec($cmd);
            if (! is_file($outPath) || filesize($outPath) === 0) {
                throw new RuntimeException('aegis256-file decrypt failed: '.$out);
            }

            return;
        }

        $cipher = (string) file_get_contents($encPath);
        $key = (string) file_get_contents($keyPath);
        $nonce = (string) file_get_contents($noncePath);

        if ($cipherAlgo === 'aegis-256-php-sodium') {
            $plain = sodium_crypto_aead_aegis256_decrypt($cipher, self::AAD, $nonce, $key);
        } elseif ($cipherAlgo === 'aes-256-gcm') {
            $tag = substr($cipher, -16);
            $ct = substr($cipher, 0, -16);
            $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD);
        } else {
            throw new RuntimeException('unsupported cipher_algo: '.$cipherAlgo);
        }

        if ($plain === false || $plain === '') {
            throw new RuntimeException('decrypt failed for '.$cipherAlgo);
        }
        file_put_contents($outPath, $plain);
    }

    private function assertGzipValid(string $sqlGz): void
    {
        $output = [];
        $code = 0;
        $cmd = 'gzip -t '.escapeshellarg($sqlGz).' 2>&1';
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('gzip -t failed: '.implode("\n", $output));
        }
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
}
