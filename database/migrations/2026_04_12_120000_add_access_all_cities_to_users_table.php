<?php

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('access_all_cities')->default(false)->after('theme');
        });

        $callCenterRoleId = DB::table('roles')->where('role_code', 'call_center')->value('role_id');
        if ($callCenterRoleId) {
            $ccUserIds = DB::table('user_roles')->where('role_id', $callCenterRoleId)->pluck('user_id');
            if ($ccUserIds->isNotEmpty()) {
                DB::table('users')->whereIn('user_id', $ccUserIds)->update(['access_all_cities' => true]);
                DB::table('user_cities')->whereIn('user_id', $ccUserIds)->delete();
            }
        }

        $activeCityIds = City::query()->where('is_active', true)->orderBy('city_id')->pluck('city_id')->values();
        if ($activeCityIds->isEmpty()) {
            return;
        }
        $activeSorted = $activeCityIds->sort()->values()->all();

        User::query()->chunkById(100, function ($users) use ($activeSorted) {
            foreach ($users as $user) {
                $userCityIds = $user->cities()->orderBy('cities.city_id')->pluck('cities.city_id')->values()->sort()->values()->all();
                if ($userCityIds === $activeSorted) {
                    DB::table('users')->where('user_id', $user->user_id)->update(['access_all_cities' => true]);
                }
            }
        }, 'user_id');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('access_all_cities');
        });
    }
};
