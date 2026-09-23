<?php

use App\Models\CfmOperation;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach ([775, 793] as $id) {
    $o = CfmOperation::query()->with(['category', 'createdBy', 'closedBy'])->find($id);
    if (! $o) {
        echo "missing {$id}\n";
        continue;
    }
    echo json_encode([
        'id' => $o->cfm_id,
        'cash' => $o->amount_cfm,
        'master' => $o->amount_from_master,
        'created' => (string) $o->cfm_created_at,
        'closed' => $o->cfm_closed_at ? (string) $o->cfm_closed_at : null,
        'created_by' => $o->cfm_created_by,
        'closed_by' => $o->cfm_closed_by,
        'order' => $o->related_order_id,
        'adds' => $o->cfm_adds,
        'updated_at' => (string) $o->updated_at,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
}
