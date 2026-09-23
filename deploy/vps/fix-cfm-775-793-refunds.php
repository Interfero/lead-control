<?php

use App\Models\CfmOperation;
use App\Services\CfmService;
use App\Services\ReportCityDailyService;
use Illuminate\Support\Carbon;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fixes = [
    775 => ['cash' => 900, 'master' => 600, 'closed_at' => '2026-09-10 10:24:18'],
    793 => ['cash' => 750, 'master' => 250, 'closed_at' => '2026-09-12 13:55:23'],
];

$svc = app(CfmService::class);
$stale = app(ReportCityDailyService::class);

foreach ($fixes as $id => $fix) {
    $op = CfmOperation::query()->with(['category', 'relatedUser'])->find($id);
    if (! $op) {
        echo "missing {$id}\n";
        continue;
    }

    $cityId = (int) $op->city_id;
    $oldClosed = $op->cfm_closed_at;
    $closedBy = $op->cfm_closed_by ?: $op->cfm_created_by;
    $restoreClosed = Carbon::parse($fix['closed_at']);

    $op->cfm_closed_at = null;
    $op->cfm_closed_by = null;
    $op->save();

    $updated = $svc->update($op->fresh(['category', 'relatedUser']), [
        'amount_cfm' => $fix['cash'],
        'amount_from_master' => $fix['master'],
        'cfm_adds' => $op->cfm_adds,
        'city_id' => $op->city_id,
    ]);

    $updated->cfm_closed_at = $restoreClosed;
    $updated->cfm_closed_by = $closedBy;
    $updated->save();

    foreach (array_filter([$oldClosed, $restoreClosed, $updated->cfm_created_at]) as $day) {
        try {
            $stale->markStaleForCityDate($cityId, $day);
        } catch (Throwable) {
        }
    }

    $fresh = $updated->fresh();
    echo 'id='.$fresh->cfm_id
        .' cash='.$fresh->amount_cfm
        .' master='.$fresh->amount_from_master
        .' closed='.($fresh->cfm_closed_at ? $fresh->cfm_closed_at->format('Y-m-d H:i:s') : 'open')
        ."\n";
}
