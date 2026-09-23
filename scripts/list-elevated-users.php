<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

User::query()
    ->whereHas('roles', fn ($q) => $q->whereIn('role_code', ['general_director', 'developer']))
    ->where('is_active', true)
    ->orderBy('user_id')
    ->get(['user_id', 'user_name', 'email'])
    ->each(fn ($u) => print("{$u->user_id} | {$u->user_name} | {$u->email}\n"));
