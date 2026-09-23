<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

foreach ([1, 105] as $id) {
    $u = User::with('roles')->find($id);
    if (! $u) {
        echo "{$id} NOT FOUND\n";
        continue;
    }
    echo "{$u->user_id} | {$u->user_name} | {$u->email} | roles: ".$u->roles->pluck('role_code')->join(',')."\n";
    echo "  orders created: ".DB::table('orders')->where('order_created_by', $id)->count()."\n";
    echo "  orders closed: ".DB::table('orders')->where('order_closed_by', $id)->count()."\n";
    echo "  orders master: ".DB::table('orders')->where('master_id', $id)->count()."\n";
}
