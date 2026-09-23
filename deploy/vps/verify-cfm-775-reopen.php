<?php

use App\Models\CfmOperation;
use App\Models\User;
use App\Services\CfmService;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$op = CfmOperation::query()->find(775);
$svc = app(CfmService::class);

if (! $op) {
    echo "missing_op\n";
    exit(1);
}

$gd = User::query()->whereHas('roles', fn ($q) => $q->where('role_code', 'general_director'))->first();
$dev = User::query()->whereHas('roles', fn ($q) => $q->where('role_code', 'developer'))->first();
$head = User::query()->whereHas('roles', fn ($q) => $q->where('role_code', 'branch_head'))->first();

echo 'closed_month='.($op->cfm_closed_at ? $op->cfm_closed_at->timezone(config('app.timezone'))->format('Y-m') : 'open')."\n";
echo 'current_month='.($svc->isClosedInCurrentMonth($op) ? 'yes' : 'no')."\n";
echo 'gd_reopen='.(($gd && $svc->userCanReopen($gd, $op)) ? 'yes' : 'no')."\n";
echo 'dev_reopen='.(($dev && $svc->userCanReopen($dev, $op)) ? 'yes' : 'no')."\n";
echo 'head_reopen='.(($head && $svc->userCanReopen($head, $op)) ? 'yes' : 'no')."\n";
echo 'gd_attach='.(($gd && $svc->userCanAttachDocuments($gd)) ? 'yes' : 'no')."\n";
echo 'head_attach='.(($head && $svc->userCanAttachDocuments($head)) ? 'yes' : 'no')."\n";
echo 'head_delete='.(($head && $svc->userCanDeleteDocuments($head, $op)) ? 'yes' : 'no')."\n";
