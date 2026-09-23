<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\User;
use App\Services\HrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Массовая выдача временных паролей активным мастерам (для входа в GM).
 *
 * Login (канон): email целиком или локальная часть до @.
 * Пароль хранится только как bcrypt-хеш; plaintext пишется только в CSV/консоль один раз.
 */
class IssueMasterPasswordsCommand extends Command
{
    protected $signature = 'masters:issue-passwords
        {--city= : city_id филиала}
        {--user= : один user_id}
        {--all : все активные мастера}
        {--force : перезаписать уже заданный пароль}
        {--only-empty : только если password пустой или must_change ещё не сбрасывали}
        {--csv= : путь к CSV (login,email,user_id,password,city)}
        {--dry-run : только показать кого затронет}';

    protected $description = 'Выдать временные пароли мастерам (must_change_password=1) для входа в GM';

    public function handle(HrService $hr): int
    {
        if (! $this->option('all') && ! $this->option('city') && ! $this->option('user')) {
            $this->error('Укажите --all, --city=ID или --user=ID');

            return self::FAILURE;
        }

        $query = User::query()
            ->with(['cities', 'roles'])
            ->where('is_active', true)
            ->where('is_blacklisted', false)
            ->whereHas('roles', fn ($q) => $q->whereIn('role_code', ['master', 'senior_master']));

        if ($this->option('user')) {
            $query->where('user_id', (int) $this->option('user'));
        }

        if ($this->option('city')) {
            $cityId = (int) $this->option('city');
            if (! City::query()->where('city_id', $cityId)->exists()) {
                $this->error("Город #{$cityId} не найден");

                return self::FAILURE;
            }
            $ids = City::operationGroupIds($cityId);
            $query->whereHas('cities', fn ($q) => $q->whereIn('cities.city_id', $ids));
        }

        $users = $query->orderBy('user_id')->get();
        if ($users->isEmpty()) {
            $this->warn('Нет подходящих мастеров');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');
        $rows = [];
        $issued = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $email = strtolower(trim((string) $user->email));
            if ($email === '') {
                $this->warn("skip #{$user->user_id}: нет email (login для GM)");
                $skipped++;
                continue;
            }

            $hasHash = is_string($user->password) && $user->password !== '';

            if ($this->option('only-empty') && $hasHash) {
                $skipped++;
                continue;
            }

            // Без --force не трогаем «стабильный» пароль (хеш есть и смена не требуется).
            if (! $force && $hasHash && ! (bool) $user->must_change_password) {
                $skipped++;
                continue;
            }

            $plain = $hr->generatePassword(10);
            $login = $this->loginFromEmail($email);
            $cityName = $user->cities->first()?->city_name ?? '';

            if ($dry) {
                $this->line("[dry] #{$user->user_id} {$email} → (новый пароль)");
                $issued++;
                continue;
            }

            $user->forceFill([
                'password' => $plain, // cast 'hashed'
                'must_change_password' => true,
                'password_set_at' => now(),
            ])->save();

            $rows[] = [
                'user_id' => $user->user_id,
                'login' => $login,
                'email' => $email,
                'password' => $plain,
                'city' => $cityName,
                'must_change_password' => 1,
            ];
            $issued++;
            $this->line("OK #{$user->user_id} {$login} / {$email}");
        }

        if ($dry) {
            $this->info("dry-run: would issue={$issued} skipped={$skipped}");

            return self::SUCCESS;
        }

        $csvPath = $this->option('csv');
        if ($csvPath && $rows !== []) {
            $fp = fopen($csvPath, 'w');
            if ($fp === false) {
                $this->error("Не удалось открыть CSV: {$csvPath}");

                return self::FAILURE;
            }
            fputcsv($fp, ['user_id', 'login', 'email', 'password', 'city', 'must_change_password']);
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);
            $this->info("CSV: {$csvPath}");
        }

        $this->info("issued={$issued} skipped={$skipped}");
        $this->comment('Login для GM: email целиком ИЛИ локальная часть до @. Передайте CSV ответственному; plaintext больше нигде не хранится.');

        return self::SUCCESS;
    }

    private function loginFromEmail(string $email): string
    {
        $pos = strpos($email, '@');

        return $pos === false ? $email : substr($email, 0, $pos);
    }
}
