<?php

namespace App\Services;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Импорт мастеров из kp-lead-centre в CRM.
 * Сопоставление по kp_employee_id (основное), иначе по email.
 */
class KpMasterSyncService
{
    /**
     * @param  list<array{
     *   kp_employee_id:int|string,
     *   name:string,
     *   email?:?string,
     *   passport?:?string,
     *   city_ids?:list<int>,
     *   city_names?:list<string>,
     *   status?:?string,
     *   is_active?:bool
     * }>  $masters
     * @return array{created:int,updated:int,linked:int,skipped:int,deactivated:int,errors:list<string>}
     */
    public function upsertMany(array $masters, ?int $fallbackCityId = null, bool $deactivateMissing = false): array
    {
        $role = Role::query()->where('role_code', 'master')->first();
        if (! $role) {
            return [
                'created' => 0,
                'updated' => 0,
                'linked' => 0,
                'skipped' => count($masters),
                'deactivated' => 0,
                'errors' => ['Роль master не найдена в CRM'],
            ];
        }

        $created = 0;
        $updated = 0;
        $linked = 0;
        $skipped = 0;
        $errors = [];

        foreach ($masters as $row) {
            try {
                $result = $this->upsertOne($row, $role, $fallbackCityId);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                } elseif ($result === 'linked') {
                    $linked++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $skipped++;
                $kpId = (string) ($row['kp_employee_id'] ?? '?');
                $errors[] = '#'.$kpId.': '.$e->getMessage();
            }
        }

        $deactivated = 0;
        if ($deactivateMissing && $fallbackCityId && $masters !== []) {
            $seenKpIds = [];
            foreach ($masters as $row) {
                $kpId = (int) ($row['kp_employee_id'] ?? 0);
                if ($kpId > 0) {
                    $seenKpIds[] = $kpId;
                }
            }
            $deactivated = $this->deactivateMissingForCity($seenKpIds, $fallbackCityId);
        }

        return compact('created', 'updated', 'linked', 'skipped', 'deactivated', 'errors');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function upsertOne(array $row, Role $role, ?int $fallbackCityId): string
    {
        $kpId = (int) ($row['kp_employee_id'] ?? 0);
        if ($kpId <= 0) {
            throw new \InvalidArgumentException('Пустой kp_employee_id');
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Пустое ФИО');
        }

        $email = $this->normalizeEmail($row['email'] ?? null, $kpId);
        $passport = $this->normalizePassport($row['passport'] ?? null);
        $isActive = array_key_exists('is_active', $row)
            ? (bool) $row['is_active']
            : $this->statusIsActive((string) ($row['status'] ?? 'Активный'));

        $cityIds = $this->resolveCityIds($row, $fallbackCityId);
        if ($cityIds === []) {
            throw new \RuntimeException('Не удалось определить город CRM для мастера');
        }

        /** @var User|null $user */
        $user = User::query()->where('kp_employee_id', $kpId)->first();
        $linked = false;

        if (! $user && $email !== '') {
            $byEmail = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
            if ($byEmail) {
                if ($byEmail->kp_employee_id && (int) $byEmail->kp_employee_id !== $kpId) {
                    throw new \RuntimeException('Email уже занят другим kp_employee_id='.$byEmail->kp_employee_id);
                }
                $user = $byEmail;
                $linked = true;
            }
        }

        $isNew = false;
        if (! $user) {
            $isNew = true;
            $user = new User();
            $user->email = $email;
            $user->password = Str::password(12); // cast hashed
            $user->must_change_password = true;
            $user->password_set_at = now();
            $user->user_note = 'Импорт из КП (kp_employee_id='.$kpId.')';
        }

        if ($user->is_blacklisted) {
            throw new \RuntimeException('В чёрном списке CRM — пропуск');
        }

        $user->kp_employee_id = $kpId;
        $user->user_name = $name;
        if ($passport !== null && $passport !== '') {
            $user->user_passport = $passport;
        }
        // email меняем только если пустой / служебный kp*
        if ($isNew || $this->isGeneratedKpEmail((string) $user->email)) {
            $user->email = $email;
        }

        $user->is_active = $isActive;
        if ($isActive) {
            $user->user_fired_at = null;
        } elseif ($user->user_fired_at === null) {
            $user->user_fired_at = now();
        }

        DB::transaction(function () use ($user, $role, $cityIds) {
            $user->save();
            if (! $user->roles()->where('roles.role_id', $role->role_id)->exists()) {
                $user->roles()->syncWithoutDetaching([$role->role_id]);
            }
            $user->cities()->syncWithoutDetaching($cityIds);
        });

        if ($isNew) {
            return 'created';
        }
        if ($linked) {
            return 'linked';
        }

        return 'updated';
    }

    /**
     * Снять с филиала тех, кого больше нет в выгрузке КП.
     * Без городов — помечаем уволенным. Своих мастеров CRM (без kp_employee_id) не трогаем.
     *
     * @param  list<int>  $seenKpIds
     */
    protected function deactivateMissingForCity(array $seenKpIds, int $cityId): int
    {
        $seenKpIds = array_values(array_unique(array_filter($seenKpIds)));
        if ($seenKpIds === [] || $cityId <= 0) {
            return 0;
        }

        $users = User::query()
            ->whereNotNull('kp_employee_id')
            ->where('kp_employee_id', '>', 0)
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'master'))
            ->whereHas('cities', fn ($q) => $q->where('cities.city_id', $cityId))
            ->get();

        $deactivated = 0;
        foreach ($users as $user) {
            if (in_array((int) $user->kp_employee_id, $seenKpIds, true)) {
                continue;
            }

            $user->cities()->detach($cityId);
            if ($user->cities()->count() > 0) {
                continue;
            }

            $user->is_active = false;
            if ($user->user_fired_at === null) {
                $user->user_fired_at = now();
            }
            $user->save();
            $deactivated++;
        }

        return $deactivated;
    }

    protected function normalizeEmail(mixed $email, int $kpId): string
    {
        $email = strtolower(trim((string) ($email ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return 'kp'.$kpId.'@kp-import.local';
    }

    protected function isGeneratedKpEmail(string $email): bool
    {
        return (bool) preg_match('/^kp\d+@kp-import\.local$/i', $email);
    }

    protected function normalizePassport(mixed $passport): ?string
    {
        $p = trim((string) ($passport ?? ''));
        if ($p === '' || $p === '—') {
            return null;
        }

        return mb_substr($p, 0, 20);
    }

    protected function statusIsActive(string $status): bool
    {
        $s = mb_strtolower(trim($status));

        return $s === '' || str_contains($s, 'актив');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<int>
     */
    protected function resolveCityIds(array $row, ?int $fallbackCityId): array
    {
        $ids = [];
        foreach ((array) ($row['city_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach ((array) ($row['city_names'] ?? []) as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            // «Рязань +Коломна (Рязань)» → отдельные куски
            $parts = preg_split('/\s*\+\s*|\s*,\s*/u', $name) ?: [$name];
            foreach ($parts as $part) {
                $part = trim($part);
                $part = preg_replace('/\s*\([^)]*\)\s*$/u', '', $part) ?? $part;
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $city = City::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($part) {
                        $q->where('city_name', $part)
                            ->orWhere('city_name', 'like', $part.'%');
                    })
                    ->orderByRaw('CASE WHEN city_name = ? THEN 0 ELSE 1 END', [$part])
                    ->first();
                if ($city) {
                    $ids[] = (int) $city->city_id;
                }
            }
        }

        if ($fallbackCityId) {
            $ids[] = $fallbackCityId;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
