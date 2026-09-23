<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'Листовки', 'kind' => Source::KIND_FLYER],
            ['name' => '2GIS', 'kind' => Source::KIND_FLYER],
            ['name' => 'Google', 'kind' => Source::KIND_FLYER],
            ['name' => 'Рекомендация', 'kind' => Source::KIND_FLYER],
            ['name' => 'Повторное обращение', 'kind' => Source::KIND_FLYER],
            ['name' => 'Instagram', 'kind' => Source::KIND_FLYER],
            ['name' => 'Сайт', 'kind' => Source::KIND_FLYER],
            ['name' => 'OLX', 'kind' => Source::KIND_FLYER],
        ];

        foreach ($sources as $item) {
            Source::updateOrCreate(
                ['source_name' => $item['name']],
                ['is_active' => true, 'source_kind' => $item['kind']]
            );
        }
    }
}
