<?php

namespace App\Services;

use App\Models\City;
use App\Models\FlyerMaket;
use App\Models\Source;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SourceImportService
{
  public function __construct(
        private PartnerApiService $partnerApi,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importFromCsv(UploadedFile $file, bool $syncSuperpart = true): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            $result['errors'][] = 'Не удалось открыть файл';

            return $result;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            $result['errors'][] = 'Файл пуст';

            return $result;
        }

        $firstLine = $this->stripBom($firstLine);
        $delimiter = $this->detectDelimiter($firstLine);
        $headers = str_getcsv($firstLine, $delimiter);
        $map = $this->mapHeaders($headers);

        if (! isset($map['source_name'])) {
            fclose($handle);
            $result['errors'][] = 'Не найдена колонка «Название» (или source_name)';

            return $result;
        }

        $citiesByName = City::query()->pluck('city_id', 'city_name');
        $maketsByName = FlyerMaket::query()->pluck('flyer_maket_id', 'flyer_maket_name');
        $lineNo = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNo++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            try {
                $data = $this->parseRow($row, $map, $citiesByName, $maketsByName);
            } catch (\InvalidArgumentException $e) {
                $result['errors'][] = "Строка {$lineNo}: {$e->getMessage()}";
                $result['skipped']++;

                continue;
            }

            $source = null;
            if (! empty($data['source_phone'])) {
                $source = Source::query()->where('source_phone', $data['source_phone'])->first();
            }
            if (! $source) {
                $source = Source::query()
                    ->where('source_name', $data['source_name'])
                    ->when($data['city_id'], fn ($q) => $q->where('city_id', $data['city_id']))
                    ->first();
            }

            if ($source) {
                $source->update($data);
                $result['updated']++;
            } else {
                $source = Source::create($data);
                $result['created']++;
            }

            if ($syncSuperpart && ($source->available_for_superpart || $source->source_kind === Source::KIND_PARTY)) {
                \App\Jobs\NotifySuperpartSourceUpsertJob::dispatch((int) $source->source_id);
            }
        }

        fclose($handle);

        return $result;
    }

    /**
     * @return list<list<string>>
     */
    public function templateRows(): array
    {
        return [
            ['Название', 'Телефон', 'Формат', 'Город', 'Тип', 'URL', 'Ссылка', 'SuperPart', 'Активен', 'Макет'],
            ['2GIS Москва', '74951234567', 'online', 'Москва', 'flyer', '', '0', '0', '1', ''],
            ['Листовки Ярославль', '74852345678', 'offline', 'Ярославль', 'flyer', '', '0', '0', '1', 'Макет А'],
            ['Партнерка Сервис+', '', 'offline', 'Краснодар', 'party', '', '0', '1', '1', ''],
            ['Партнёр SuperPart', '', 'online', '', 'party', 'https://partner.example/order', '1', '1', '1', ''],
        ];
    }

    private function stripBom(string $line): string
    {
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            return substr($line, 3);
        }

        return $line;
    }

    private function detectDelimiter(string $line): string
    {
        $semicolon = substr_count($line, ';');
        $comma = substr_count($line, ',');

        return $semicolon >= $comma ? ';' : ',';
    }

    /**
     * @param  list<string|null>  $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $aliases = [
            'source_name' => ['название', 'name', 'source_name', 'источник'],
            'source_phone' => ['телефон', 'phone', 'source_phone'],
            'source_format' => ['формат', 'format', 'source_format'],
            'city' => ['город', 'city', 'city_name'],
            'source_kind' => ['тип', 'kind', 'source_kind', 'категория'],
            'source_url' => ['url', 'ссылка_url', 'source_url', 'link'],
            'use_source_url' => ['ссылка', 'use_url', 'use_source_url', 'url_flag', 'есть_ссылка'],
            'available_for_superpart' => ['superpart', 'sp', 'available_for_superpart', 'парты', 'парт'],
            'is_active' => ['активен', 'active', 'is_active'],
            'flyer_maket' => ['макет', 'maket', 'flyer_maket', 'flyer_maket_name'],
        ];

        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = Str::lower(trim((string) $header));
            foreach ($aliases as $field => $names) {
                if (in_array($normalized, $names, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $map
     * @param  \Illuminate\Support\Collection<string, int>  $citiesByName
     * @param  \Illuminate\Support\Collection<string, int>  $maketsByName
     * @return array<string, mixed>
     */
    private function parseRow(array $row, array $map, $citiesByName, $maketsByName): array
    {
        $name = trim((string) $this->cell($row, $map, 'source_name'));
        if ($name === '') {
            throw new \InvalidArgumentException('пустое название');
        }

        $kind = $this->parseKind((string) $this->cell($row, $map, 'source_kind'));
        $useUrl = $this->parseBool((string) $this->cell($row, $map, 'use_source_url'));
        $url = trim((string) $this->cell($row, $map, 'source_url'));

        if ($kind === Source::KIND_PARTY && $useUrl && $url === '') {
            throw new \InvalidArgumentException('для парты с галочкой «Ссылка» нужен URL');
        }

        if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('некорректный URL');
        }

        $cityName = trim((string) $this->cell($row, $map, 'city'));
        $cityId = null;
        if ($cityName !== '') {
            $cityId = $citiesByName[$cityName] ?? null;
            if (! $cityId) {
                throw new \InvalidArgumentException("город «{$cityName}» не найден");
            }
        }

        $maketName = trim((string) $this->cell($row, $map, 'flyer_maket'));
        $maketId = null;
        if ($maketName !== '') {
            $maketId = $maketsByName[$maketName] ?? null;
            if (! $maketId) {
                throw new \InvalidArgumentException("макет «{$maketName}» не найден");
            }
        }

        $availableForSp = $this->parseBool((string) $this->cell($row, $map, 'available_for_superpart'));
        if ($kind === Source::KIND_PARTY) {
            $availableForSp = true;
        } elseif ($kind === Source::KIND_FLYER) {
            $availableForSp = false;
        }

        return [
            'source_name' => $name,
            'source_phone' => $this->nullablePhone((string) $this->cell($row, $map, 'source_phone')),
            'source_format' => $this->parseFormat((string) $this->cell($row, $map, 'source_format')),
            'city_id' => $cityId,
            'flyer_maket_id' => $maketId,
            'source_kind' => $kind,
            'source_url' => $kind === Source::KIND_PARTY && $useUrl ? $url : null,
            'use_source_url' => $kind === Source::KIND_PARTY && $useUrl,
            'available_for_superpart' => $availableForSp,
            'is_active' => $this->parseBool((string) $this->cell($row, $map, 'is_active'), true),
        ];
    }

    /**
     * @param  list<string|null>  $row
     */
    private function cell(array $row, array $map, string $field): ?string
    {
        if (! isset($map[$field])) {
            return null;
        }

        return $row[$map[$field]] ?? null;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseKind(string $value): string
    {
        $normalized = Str::lower(trim($value));
        if ($normalized === '' || in_array($normalized, ['flyer', 'листовки', 'листовка', 'листовоч', 'leaflet', 'rk', 'рк', 'реклама', 'advert'], true)) {
            return Source::KIND_FLYER;
        }
        if (in_array($normalized, ['party', 'парты', 'парта', 'superpart', 'super part', 'partner_flyer', 'партнерки', 'партнерка', 'партнёрка', 'партнерк', 'partner_flyers'], true)) {
            return Source::KIND_PARTY;
        }

        throw new \InvalidArgumentException("неизвестный тип «{$value}» (flyer, party)");
    }

    private function parseFormat(string $value): ?string
    {
        $normalized = Str::lower(trim($value));
        if ($normalized === '') {
            return null;
        }
        if (in_array($normalized, ['online', 'онлайн', 'on'], true)) {
            return Source::FORMAT_ONLINE;
        }
        if (in_array($normalized, ['offline', 'офлайн', 'off'], true)) {
            return Source::FORMAT_OFFLINE;
        }

        throw new \InvalidArgumentException("неизвестный формат «{$value}»");
    }

    private function parseBool(string $value, bool $default = false): bool
    {
        $normalized = Str::lower(trim($value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'да', 'y', 'on'], true);
    }

    private function nullablePhone(string $value): ?string
    {
        $normalized = \App\Helpers\PhoneHelper::normalize($value);

        return $normalized !== '' ? $normalized : null;
    }
}
