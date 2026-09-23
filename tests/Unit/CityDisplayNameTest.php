<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\CityReportService;
use Tests\TestCase;

class CityDisplayNameTest extends TestCase
{
    public function test_satellite_with_parent_already_in_name_is_not_doubled(): void
    {
        $parent = new City(['city_name' => 'Сочи']);
        $sat = new City(['city_name' => 'Адлер (Сочи)', 'parent_city_id' => 1]);
        $sat->setRelation('parentCity', $parent);

        $this->assertTrue($sat->nameIncludesParent());
        $this->assertSame('Адлер (Сочи)', $sat->displayName());
        $this->assertSame('Адлер (Сочи)', app(CityReportService::class)->displayCityName($sat));
    }

    public function test_plain_satellite_gets_parent_suffix_once(): void
    {
        $parent = new City(['city_name' => 'Нижний Новгород']);
        $sat = new City(['city_name' => 'Дзержинск', 'parent_city_id' => 1]);
        $sat->setRelation('parentCity', $parent);

        $this->assertFalse($sat->nameIncludesParent());
        $this->assertSame('Дзержинск (Нижний Новгород)', $sat->displayName());
    }

    public function test_msk_suffix_is_not_treated_as_missing_parent(): void
    {
        $parent = new City(['city_name' => 'Подольск']);
        $sat = new City(['city_name' => 'Коммунарка (Подольск) (МСК)', 'parent_city_id' => 1]);
        $sat->setRelation('parentCity', $parent);

        $this->assertTrue($sat->nameIncludesParent());
        $this->assertSame('Коммунарка (Подольск) (МСК)', $sat->displayName());
    }

    public function test_name_includes_parent_from_string_without_relation(): void
    {
        $sat = new City(['city_name' => 'Адлер (Сочи)', 'parent_city_id' => 54]);

        $this->assertTrue($sat->nameIncludesParent('Сочи'));
        $this->assertFalse($sat->nameIncludesParent('Краснодар'));
    }
}
