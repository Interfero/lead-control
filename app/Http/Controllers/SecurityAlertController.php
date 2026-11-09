<?php

namespace App\Http\Controllers;

use App\Models\SecurityAlert;

class SecurityAlertController extends Controller
{
    /** Отметить уведомление прочитанным (баннер скрывается для всех руководителей). */
    public function seen(int $alert_id)
    {
        $alert = SecurityAlert::query()->findOrFail($alert_id);
        if ($alert->seen_at === null) {
            $alert->update(['seen_at' => now(), 'seen_by' => auth()->id()]);
        }

        return back();
    }
}
