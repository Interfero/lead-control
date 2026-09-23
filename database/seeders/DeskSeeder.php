<?php

namespace Database\Seeders;

use App\Models\CrmConnection;
use App\Models\DeskStat;
use App\Models\DeskStatusMapping;
use Illuminate\Database\Seeder;

class DeskSeeder extends Seeder
{
    public function run(): void
    {
        $crm1 = CrmConnection::query()->updateOrCreate(
            ['type' => 'crm1_eloquent'],
            [
                'name' => 'Lead Control',
                'base_url' => config('app.url'),
                'login' => null,
                'password' => null,
                'status' => 'active',
                'sync_interval' => 60,
                'timeout' => 5,
                'config' => ['source' => 'eloquent'],
                'last_error' => null,
            ]
        );

        CrmConnection::query()->firstOrCreate(
            ['type' => 'crm2_http'],
            [
                'name' => 'kp-lead-centre (скоро)',
                'base_url' => null,
                'login' => null,
                'password' => null,
                'status' => 'inactive',
                'sync_interval' => 60,
                'timeout' => 15,
                'config' => null,
            ]
        );

        $mappings = [
            ['crm_status_code' => 'callback', 'unified_status' => 'new', 'is_closed' => false, 'display_order' => 10],
            ['crm_status_code' => 'not_processed', 'unified_status' => 'new', 'is_closed' => false, 'display_order' => 20],
            ['crm_status_code' => 'pending', 'unified_status' => 'new', 'is_closed' => false, 'display_order' => 30],
            ['crm_status_code' => 'on_way', 'unified_status' => 'on_way', 'is_closed' => false, 'display_order' => 40],
            ['crm_status_code' => 'in_progress', 'unified_status' => 'in_progress', 'is_closed' => false, 'display_order' => 50],
            ['crm_status_code' => 'in_progress_sd', 'unified_status' => 'in_progress', 'is_closed' => false, 'display_order' => 60],
            ['crm_status_code' => 'review', 'unified_status' => 'ready', 'is_closed' => false, 'display_order' => 70],
            ['crm_status_code' => 'completed', 'unified_status' => 'closed', 'is_closed' => true, 'display_order' => 100],
            ['crm_status_code' => 'cancelled_cc', 'unified_status' => 'closed', 'is_closed' => true, 'display_order' => 110],
            ['crm_status_code' => 'cancelled_city', 'unified_status' => 'closed', 'is_closed' => true, 'display_order' => 120],
            ['crm_status_code' => 'rejected', 'unified_status' => 'closed', 'is_closed' => true, 'display_order' => 130],
        ];

        foreach ($mappings as $row) {
            DeskStatusMapping::query()->updateOrCreate(
                [
                    'crm_id' => $crm1->id,
                    'crm_status_code' => $row['crm_status_code'],
                ],
                [
                    'unified_status' => $row['unified_status'],
                    'is_closed' => $row['is_closed'],
                    'display_order' => $row['display_order'],
                ]
            );
        }

        DeskStat::query()->firstOrCreate(['key' => 'closed_via_desk'], ['value' => 0]);
    }
}
