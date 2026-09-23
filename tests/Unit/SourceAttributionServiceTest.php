<?php

namespace Tests\Unit;

use App\Models\Source;
use App\Models\SourcePhoneAlias;
use App\Services\SourceAttributionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourceAttributionServiceTest extends TestCase
{
    private SourceAttributionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        config(['services.mango.line_sources' => []]);
        $this->svc = app(SourceAttributionService::class);
    }

    public function test_resolves_by_source_phone(): void
    {
        Source::query()->create([
            'source_name' => 'Листовка Тест',
            'source_phone' => '9049532139',
            'source_format' => 'offline',
            'source_kind' => 'flyer',
            'is_active' => true,
        ]);

        $r = $this->svc->resolveForLine('+7 904 953-21-39');
        $this->assertSame('source_phone', $r['via']);
        $this->assertNotNull($r['source_id']);
    }

    public function test_resolves_via_alias(): void
    {
        $source = Source::query()->create([
            'source_name' => 'Партнёр',
            'source_phone' => null,
            'source_format' => 'online',
            'source_kind' => 'party',
            'is_active' => true,
        ]);

        SourcePhoneAlias::query()->create([
            'source_id' => $source->source_id,
            'phone' => '9300366894',
        ]);

        $r = $this->svc->resolveForLine('79300366894');
        $this->assertSame('alias', $r['via']);
        $this->assertSame((int) $source->source_id, (int) $r['source_id']);
    }

    public function test_env_fallback_with_warning_path(): void
    {
        $source = Source::query()->create([
            'source_name' => 'Env Fallback Target',
            'source_phone' => '9111111111',
            'source_format' => 'offline',
            'source_kind' => 'flyer',
            'is_active' => true,
        ]);

        config(['services.mango.line_sources' => [
            '101' => (int) $source->source_id,
        ]]);

        $r = $this->svc->resolveForLine('101');
        $this->assertSame('env_fallback', $r['via']);
        $this->assertSame((int) $source->source_id, (int) $r['source_id']);
    }

    public function test_gap_stats_excludes_superpart_party_from_did_blocker(): void
    {
        Source::query()->create([
            'source_name' => 'Листовка без телефона',
            'source_phone' => null,
            'source_kind' => 'flyer',
            'is_active' => true,
        ]);
        Source::query()->create([
            'source_name' => 'SuperPart парт',
            'source_phone' => null,
            'source_kind' => 'party',
            'is_active' => true,
            'available_for_superpart' => true,
            'superpart_local_source_id' => 99,
        ]);

        $stats = $this->svc->gapStats();
        $this->assertSame(1, $stats['without_phone']);
        $this->assertSame(1, $stats['without_phone_superpart_party']);
        $this->assertSame(2, $stats['without_phone_all']);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('source_phone_aliases');
        Schema::dropIfExists('sources');

        Schema::create('sources', function (Blueprint $table) {
            $table->id('source_id');
            $table->string('source_name');
            $table->string('source_phone')->nullable();
            $table->string('source_format')->nullable();
            $table->string('source_kind')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('available_for_superpart')->default(false);
            $table->unsignedBigInteger('superpart_local_source_id')->nullable();
            $table->unsignedBigInteger('superpart_partner_id')->nullable();
            $table->timestamps();
        });

        Schema::create('source_phone_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('phone');
            $table->timestamps();
        });
    }
}
