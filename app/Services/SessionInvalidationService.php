<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Принудительный выход: снос DB-сессий + remember-token.
 */
class SessionInvalidationService
{
    public function invalidateUser(User $user): int
    {
        $user->forceFill(['remember_token' => null])->save();

        if (! Schema::hasTable('sessions')) {
            return 0;
        }

        $query = DB::table('sessions');
        if (Schema::hasColumn('sessions', 'user_id')) {
            return $query->where('user_id', $user->user_id)->delete();
        }

        // Fallback: payload содержит сериализованный login_* id (редко на старых схемах)
        return $query->where('payload', 'like', '%"'.$user->user_id.'"%')->delete();
    }
}
