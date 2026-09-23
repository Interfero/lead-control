<?php

namespace Tests\Unit;

use App\Models\ReportCityDaily;
use App\Services\ReportCityDailyService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportCityDailyStaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('report_city_daily');
        Schema::create('report_city_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->date('report_date');
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('closed_total')->default(0);
            $table->unsignedInteger('closed_our')->default(0);
            $table->unsignedInteger('closed_partner')->default(0);
            $table->bigInteger('turnover')->default(0);
            $table->bigInteger('net')->default(0);
            $table->bigInteger('parts')->default(0);
            $table->unsignedInteger('complaints_count')->default(0);
            $table->bigInteger('promo_pay')->default(0);
            $table->unsignedInteger('warranty_closed')->default(0);
            $table->unsignedInteger('repeat_closed')->default(0);
            $table->unsignedInteger('non_core_closed')->default(0);
            $table->unsignedInteger('refusals')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->boolean('stale')->default(false);
            $table->timestamp('rebuilt_at')->nullable();
            $table->timestamps();
            $table->unique(['city_id', 'report_date']);
        });
    }

    public function test_mark_stale_for_city_date_is_idempotent(): void
    {
        $svc = app(ReportCityDailyService::class);
        $day = Carbon::parse('2026-07-15');

        $svc->markStaleForCityDate(7, $day);
        $svc->markStaleForCityDate(7, $day);

        $this->assertSame(1, ReportCityDaily::query()->count());
        $row = ReportCityDaily::query()->first();
        $this->assertTrue((bool) $row->stale);
        $this->assertSame(7, (int) $row->city_id);
        $this->assertSame('2026-07-15', $row->report_date->toDateString());
    }
}
