<?php

/**
 * Удаление учёток по user_id. Использование на сервере:
 * php scripts/delete-users.php 105 1 --reassign-to=USER_ID
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$args = array_slice($argv, 1);
$reassignTo = null;
$ids = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--reassign-to=')) {
        $reassignTo = (int) substr($arg, strlen('--reassign-to='));
    } elseif (ctype_digit($arg)) {
        $ids[] = (int) $arg;
    }
}

if ($ids === []) {
    fwrite(STDERR, "Usage: php scripts/delete-users.php ID [ID2 ...] [--reassign-to=USER_ID]\n");
    exit(1);
}

// Сначала удаляем «младшие» id, кроме 1 — 1 удаляем последним
usort($ids, fn ($a, $b) => $a === 1 ? 1 : ($b === 1 ? -1 : $b <=> $a));

foreach ($ids as $uid) {
    $user = User::with('roles')->find($uid);
    if (! $user) {
        echo "Skip: user #{$uid} not found\n";
        continue;
    }

    $fallback = $reassignTo;
    if ($fallback === null || $fallback === $uid) {
        $fallback = (int) (User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'general_director'))
            ->where('user_id', '!=', $uid)
            ->where('is_active', true)
            ->orderBy('user_id')
            ->value('user_id') ?? 0);
    }

    if ($fallback < 1) {
        $fallback = (int) (User::query()
            ->where('user_id', '!=', $uid)
            ->where('is_active', true)
            ->orderBy('user_id')
            ->value('user_id') ?? 0);
    }

    if ($fallback < 1 && DB::table('orders')->where('order_created_by', $uid)->exists()) {
        fwrite(STDERR, "Cannot delete user #{$uid}: no fallback for order_created_by\n");
        exit(1);
    }

    echo "Deleting #{$uid} ({$user->user_name}), reassign orders to #{$fallback}\n";

    DB::transaction(function () use ($uid, $fallback) {
        DB::table('master_schedules')->where('user_id', $uid)->delete();
        DB::table('order_city_views')->where('user_id', $uid)->delete();
        DB::table('order_activity_logs')->where('user_id', $uid)->delete();

        DB::table('orders')->where('master_id', $uid)->update(['master_id' => null]);
        DB::table('orders')->where('master_handed_over_by', $uid)->update(['master_handed_over_by' => null]);

        if ($fallback > 0) {
            DB::table('orders')->where('order_created_by', $uid)->update(['order_created_by' => $fallback]);
            DB::table('orders')->where('order_closed_by', $uid)->update(['order_closed_by' => $fallback]);
            DB::table('cfm_operations')->where('cfm_created_by', $uid)->update(['cfm_created_by' => $fallback]);
            DB::table('cfm_operations')->where('cfm_closed_by', $uid)->update(['cfm_closed_by' => $fallback]);
        } else {
            DB::table('orders')->where('order_closed_by', $uid)->update(['order_closed_by' => null]);
            DB::table('cfm_operations')->where('cfm_closed_by', $uid)->update(['cfm_closed_by' => null]);
        }

        DB::table('complaints')->where('complaint_created_by', $uid)->update(['complaint_created_by' => null]);
        DB::table('complaints')->where('complaint_closed_by', $uid)->update(['complaint_closed_by' => null]);
        DB::table('calls')->where('operator_id', $uid)->update(['operator_id' => null]);
        DB::table('documents')->where('verified_by', $uid)->update(['verified_by' => null]);
        if ($fallback > 0) {
            DB::table('documents')->where('uploaded_by', $uid)->update(['uploaded_by' => $fallback]);
            DB::table('knowledge_articles')->where('created_by', $uid)->update(['created_by' => $fallback]);
            DB::table('knowledge_articles')->where('updated_by', $uid)->update(['updated_by' => $fallback]);
        }
        DB::table('interviews')->where('user_id', $uid)->delete();
        DB::table('interviews')->where('manager_user_id', $uid)->update(['manager_user_id' => null]);
        DB::table('comments')->where('created_by', $uid)->delete();
        DB::table('comments')
            ->where('commentable_type', User::class)
            ->where('commentable_id', $uid)
            ->delete();
        DB::table('reviews')->where('created_by', $uid)->update(['created_by' => null]);
        DB::table('users')->where('blacklisted_by', $uid)->update(['blacklisted_by' => null]);

        DB::table('user_cities')->where('user_id', $uid)->delete();
        DB::table('user_roles')->where('user_id', $uid)->delete();
        DB::table('sessions')->where('user_id', $uid)->delete();

        DB::table('documents')
            ->where('documentable_type', User::class)
            ->where('documentable_id', $uid)
            ->delete();

        DB::table('users')->where('user_id', $uid)->delete();
    });

    echo "  Deleted user #{$uid}\n";
}

echo "Done.\n";
