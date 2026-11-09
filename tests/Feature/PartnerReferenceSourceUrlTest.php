<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifySuperPartApi;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PartnerReferenceSourceUrlTest extends TestCase
{
    use RefreshDatabase;

    private const AVITO = 'https://www.avito.ru/brands/i123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([VerifySuperPartApi::class, ThrottleRequests::class]);
    }

    private function makeSource(): Source
    {
        return Source::query()->create([
            'source_name' => 'Партнёр',
            'source_kind' => Source::KIND_PARTY,
            'superpart_local_source_id' => 501,
            'superpart_partner_id' => 8,
            'is_active' => true,
            'available_for_superpart' => true,
        ]);
    }

    public function test_patch_sets_source_url(): void
    {
        $source = $this->makeSource();

        $this->patchJson('/api/v1/reference/sources/'.$source->source_id, [
            'source_name' => 'Партнёр',
            'source_url' => self::AVITO,
            'use_source_url' => true,
        ])->assertOk();

        $fresh = $source->fresh();
        $this->assertTrue($fresh->use_source_url);
        $this->assertSame(self::AVITO, $fresh->source_url);
        $this->assertSame(self::AVITO, $fresh->superpartOrderLink());
    }

    public function test_patch_without_url_fields_keeps_existing_link(): void
    {
        $source = $this->makeSource();
        $source->forceFill(['source_url' => self::AVITO, 'use_source_url' => true])->save();

        $this->patchJson('/api/v1/reference/sources/'.$source->source_id, ['source_name' => 'Новое имя'])->assertOk();

        $this->assertSame(self::AVITO, $source->fresh()->source_url);
    }

    public function test_patch_clears_link_when_superpart_sends_empty(): void
    {
        $source = $this->makeSource();
        $source->forceFill(['source_url' => self::AVITO, 'use_source_url' => true])->save();

        $this->patchJson('/api/v1/reference/sources/'.$source->source_id, [
            'source_url' => null,
            'use_source_url' => false,
        ])->assertOk();

        $fresh = $source->fresh();
        $this->assertFalse((bool) $fresh->use_source_url);
        $this->assertNull($fresh->source_url);
    }
}
