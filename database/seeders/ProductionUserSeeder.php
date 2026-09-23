<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Production сидер для пользователей.
 * 
 * Создаёт:
 * - Директора (branch_head) для каждого города
 * - Старшего менеджера (senior_manager) для каждого города
 * - Одного разработчика (developer) с доступом ко всем городам
 * 
 * ВАЖНО: Пароли генерируются случайно и выводятся в консоль.
 * Сохраните их или сбросьте через механизм восстановления пароля.
 */
class ProductionUserSeeder extends Seeder
{
    /**
     * Сгенерированные учётные данные для вывода
     */
    private array $credentials = [];
    
    public function run(): void
    {
        // Получаем роли
        $branchHeadRole = Role::where('role_code', 'branch_head')->first();
        $seniorManagerRole = Role::where('role_code', 'senior_manager')->first();
        $developerRole = Role::where('role_code', 'developer')->first();
        
        if (!$branchHeadRole || !$seniorManagerRole || !$developerRole) {
            $this->command->error('Роли не найдены! Сначала запустите RoleSeeder.');
            return;
        }
        
        // Получаем все активные города (кроме УК)
        $cities = City::where('is_active', true)
            ->where('city_type', 'city')
            ->get();
        
        if ($cities->isEmpty()) {
            $this->command->error('Города не найдены! Сначала запустите ProductionCitySeeder.');
            return;
        }
        
        // Создаём разработчика (один на всю систему)
        $this->createDeveloper($developerRole, $cities);
        
        // Создаём директора и старшего менеджера для каждого города
        foreach ($cities as $city) {
            $this->createBranchHead($city, $branchHeadRole);
            $this->createSeniorManager($city, $seniorManagerRole);
        }
        
        // Выводим учётные данные
        $this->outputCredentials();
    }
    
    /**
     * Создать разработчика
     */
    private function createDeveloper(Role $role, $cities): void
    {
        $email = 'developer@leadcontrol.ru';
        $password = $this->generateSecurePassword();
        
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'user_name' => 'Разработчик',
                'password' => Hash::make($password),
                'is_active' => true,
                'user_hired_at' => now(),
            ]
        );
        
        // Привязываем роль
        $user->roles()->syncWithoutDetaching([$role->role_id]);
        
        // Привязываем все города
        $user->cities()->syncWithoutDetaching($cities->pluck('city_id')->toArray());
        
        $this->credentials[] = [
            'role' => 'Разработчик',
            'city' => 'Все города',
            'email' => $email,
            'password' => $password,
        ];
    }
    
    /**
     * Создать директора филиала
     */
    private function createBranchHead(City $city, Role $role): void
    {
        $email = $this->generateEmail($city, 'director');
        $password = $this->generateSecurePassword();
        
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'user_name' => 'Директор ' . $city->city_name,
                'password' => Hash::make($password),
                'is_active' => true,
                'user_hired_at' => now(),
            ]
        );
        
        // Привязываем роль
        $user->roles()->syncWithoutDetaching([$role->role_id]);
        
        // Привязываем город
        $user->cities()->syncWithoutDetaching([$city->city_id]);
        
        $this->credentials[] = [
            'role' => 'Директор',
            'city' => $city->city_name,
            'email' => $email,
            'password' => $password,
        ];
    }
    
    /**
     * Создать старшего менеджера
     */
    private function createSeniorManager(City $city, Role $role): void
    {
        $email = $this->generateEmail($city, 'manager');
        $password = $this->generateSecurePassword();
        
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'user_name' => 'Старший менеджер ' . $city->city_name,
                'password' => Hash::make($password),
                'is_active' => true,
                'user_hired_at' => now(),
            ]
        );
        
        // Привязываем роль
        $user->roles()->syncWithoutDetaching([$role->role_id]);
        
        // Привязываем город
        $user->cities()->syncWithoutDetaching([$city->city_id]);
        
        $this->credentials[] = [
            'role' => 'Старший менеджер',
            'city' => $city->city_name,
            'email' => $email,
            'password' => $password,
        ];
    }
    
    /**
     * Генерация email на основе города
     */
    private function generateEmail(City $city, string $prefix): string
    {
        // Транслитерация названия города
        $citySlug = $this->transliterate($city->city_name);
        $citySlug = Str::slug($citySlug, '_');
        
        return "{$prefix}_{$citySlug}@leadcontrol.ru";
    }
    
    /**
     * Генерация безопасного пароля
     */
    private function generateSecurePassword(): string
    {
        return Str::random(12);
    }
    
    /**
     * Транслитерация кириллицы в латиницу
     */
    private function transliterate(string $text): string
    {
        $converter = [
            'а' => 'a',   'б' => 'b',   'в' => 'v',   'г' => 'g',   'д' => 'd',
            'е' => 'e',   'ё' => 'e',   'ж' => 'zh',  'з' => 'z',   'и' => 'i',
            'й' => 'y',   'к' => 'k',   'л' => 'l',   'м' => 'm',   'н' => 'n',
            'о' => 'o',   'п' => 'p',   'р' => 'r',   'с' => 's',   'т' => 't',
            'у' => 'u',   'ф' => 'f',   'х' => 'h',   'ц' => 'ts',  'ч' => 'ch',
            'ш' => 'sh',  'щ' => 'sch', 'ъ' => '',    'ы' => 'y',   'ь' => '',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
            'А' => 'A',   'Б' => 'B',   'В' => 'V',   'Г' => 'G',   'Д' => 'D',
            'Е' => 'E',   'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',   'И' => 'I',
            'Й' => 'Y',   'К' => 'K',   'Л' => 'L',   'М' => 'M',   'Н' => 'N',
            'О' => 'O',   'П' => 'P',   'Р' => 'R',   'С' => 'S',   'Т' => 'T',
            'У' => 'U',   'Ф' => 'F',   'Х' => 'H',   'Ц' => 'Ts',  'Ч' => 'Ch',
            'Ш' => 'Sh',  'Щ' => 'Sch', 'Ъ' => '',    'Ы' => 'Y',   'Ь' => '',
            'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
        ];
        
        return strtr($text, $converter);
    }
    
    /**
     * Вывод учётных данных в консоль
     */
    private function outputCredentials(): void
    {
        $this->command->newLine();
        $this->command->info('╔════════════════════════════════════════════════════════════════════════════╗');
        $this->command->info('║                    СОЗДАННЫЕ УЧЁТНЫЕ ДАННЫЕ                                ║');
        $this->command->info('║                    СОХРАНИТЕ ИХ В БЕЗОПАСНОМ МЕСТЕ!                        ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════════════════╝');
        $this->command->newLine();
        
        $headers = ['Роль', 'Город', 'Email', 'Пароль'];
        $this->command->table($headers, $this->credentials);
        
        $this->command->newLine();
        $this->command->warn('⚠️  Рекомендуется сменить пароли после первого входа!');
        $this->command->info('Всего создано пользователей: ' . count($this->credentials));
    }
}
