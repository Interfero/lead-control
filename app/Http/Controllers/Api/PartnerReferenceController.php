<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Order;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerReferenceController extends Controller
{
    public function cities(): JsonResponse
    {
        $cities = City::query()
            ->where('is_active', true)
            ->orderBy('city_name')
            ->get(['city_id', 'city_name', 'city_type', 'city_timezone']);

        return response()->json(['data' => $cities]);
    }

    public function workTypes(): JsonResponse
    {
        $orderCore = [
            ['code' => 'core', 'label' => 'Профильный'],
            ['code' => 'non_core', 'label' => 'Непрофильный'],
            ['code' => 'other', 'label' => 'Прочий'],
        ];

        $equipment = [];
        foreach (Order::EQUIPMENT_TYPES as $code => $label) {
            $equipment[] = [
                'code' => $code,
                'label' => $label,
                'order_core' => in_array($code, Order::CORE_EQUIPMENT, true) ? 'core' : 'non_core',
            ];
        }

        return response()->json([
            'order_core' => $orderCore,
            'equipment_types' => $equipment,
        ]);
    }

    public function sources(): JsonResponse
    {
        $sources = Source::query()
            ->where('is_active', true)
            ->where('available_for_superpart', true)
            ->with(['city:city_id,city_name'])
            ->orderBy('source_name')
            ->get();

        $data = $sources->map(fn (Source $s) => [
            'source_id' => $s->source_id,
            'source_name' => $s->source_name,
            'city_id' => $s->city_id,
            'city_name' => $s->city?->city_name,
            'superpart_partner_id' => $s->superpart_partner_id,
            'superpart_local_source_id' => $s->superpart_local_source_id,
            'source_kind' => $s->source_kind,
            'source_format' => $s->source_format,
            'available_for_superpart' => (bool) $s->available_for_superpart,
            'source_url' => $s->use_source_url ? $s->source_url : null,
            'use_source_url' => $s->use_source_url,
        ]);

        return response()->json(['data' => $data]);
    }

    public function storeSource(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_name' => ['required', 'string', 'max:255'],
            'superpart_partner_id' => ['nullable', 'integer', 'min:1'],
            'superpart_local_source_id' => ['required', 'integer', 'min:1'],
            'source_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'use_source_url' => ['sometimes', 'boolean'],
            'available_for_superpart' => ['sometimes', 'boolean'],
        ]);

        $partnerId = array_key_exists('superpart_partner_id', $validated)
            ? ($validated['superpart_partner_id'] !== null ? (int) $validated['superpart_partner_id'] : null)
            : null;

        $existing = Source::query()
            ->where('superpart_local_source_id', $validated['superpart_local_source_id'])
            ->first();

        if ($existing) {
            $existing->source_name = $validated['source_name'];
            $existing->superpart_partner_id = $partnerId;
            $existing->source_kind = Source::KIND_PARTY;
            $existing->is_active = true;
            $existing->available_for_superpart = array_key_exists('available_for_superpart', $validated)
                ? (bool) $validated['available_for_superpart']
                : true;
            $this->applyPartyUrlFields($existing, $validated);
            $existing->save();

            return response()->json([
                'source_id' => $existing->source_id,
                'source_name' => $existing->source_name,
                'superpart_partner_id' => $existing->superpart_partner_id,
                'superpart_local_source_id' => $existing->superpart_local_source_id,
            ]);
        }

        $source = Source::create([
            'source_name' => $validated['source_name'],
            'superpart_partner_id' => $partnerId,
            'superpart_local_source_id' => $validated['superpart_local_source_id'],
            'source_kind' => Source::KIND_PARTY,
            'is_active' => true,
            'available_for_superpart' => array_key_exists('available_for_superpart', $validated)
                ? (bool) $validated['available_for_superpart']
                : true,
        ] + $this->partyUrlPayload($validated));

        return response()->json([
            'source_id' => $source->source_id,
            'source_name' => $source->source_name,
            'superpart_partner_id' => $source->superpart_partner_id,
            'superpart_local_source_id' => $source->superpart_local_source_id,
        ], 201);
    }

    public function updateSourcePartner(Request $request, int $source_id): JsonResponse
    {
        $validated = $request->validate([
            'superpart_partner_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'source_name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'available_for_superpart' => ['sometimes', 'boolean'],
            'deleted' => ['sometimes', 'boolean'],
        ]);

        $source = Source::query()->find($source_id);
        if (! $source) {
            return response()->json(['message' => 'Источник не найден'], 404);
        }

        if ($request->boolean('deleted')) {
            return $this->removeSourceFromPartner($request, $source);
        }

        // Authenticated SuperPart API: всегда держим источник в каталоге при смене доступа.
        $source->available_for_superpart = true;

        if (array_key_exists('available_for_superpart', $validated)) {
            $source->available_for_superpart = (bool) $validated['available_for_superpart'];
        }

        // Тело может прийти как JSON: подстрахуемся raw body, если input пуст.
        $partnerProvided = array_key_exists('superpart_partner_id', $validated)
            || $request->exists('superpart_partner_id');
        $partnerValue = $validated['superpart_partner_id'] ?? $request->input('superpart_partner_id');

        if (! $partnerProvided) {
            $raw = json_decode($request->getContent() ?: '', true);
            if (is_array($raw) && array_key_exists('superpart_partner_id', $raw)) {
                $partnerProvided = true;
                $partnerValue = $raw['superpart_partner_id'];
            }
            if (is_array($raw) && array_key_exists('available_for_superpart', $raw)) {
                $source->available_for_superpart = (bool) $raw['available_for_superpart'];
            }
        }

        if ($partnerProvided) {
            $source->superpart_partner_id = $partnerValue !== null ? (int) $partnerValue : null;
        }

        if (array_key_exists('source_name', $validated) && $validated['source_name'] !== '') {
            $source->source_name = $validated['source_name'];
        }

        if (array_key_exists('is_active', $validated)) {
            $source->is_active = (bool) $validated['is_active'];
        }

        $source->save();

        return response()->json([
            'source_id' => $source->source_id,
            'source_name' => $source->source_name,
            'superpart_partner_id' => $source->superpart_partner_id,
            'superpart_local_source_id' => $source->superpart_local_source_id,
            'available_for_superpart' => (bool) $source->available_for_superpart,
        ]);
    }

    public function destroySource(Request $request, int $source_id): JsonResponse
    {
        return $this->removeSourceFromPartner($request, Source::query()->find($source_id));
    }

    public function destroySourceByLocalId(Request $request, int $superpart_local_source_id): JsonResponse
    {
        $source = Source::query()
            ->where('superpart_local_source_id', $superpart_local_source_id)
            ->first();

        return $this->removeSourceFromPartner($request, $source);
    }

    private function removeSourceFromPartner(Request $request, ?Source $source): JsonResponse
    {
        if (! $source) {
            return response()->json(['message' => 'Источник не найден'], 404);
        }

        if ($source->orders()->exists()) {
            $source->update([
                'is_active' => false,
                'available_for_superpart' => false,
            ]);

            return response()->json([
                'source_id' => $source->source_id,
                'deactivated' => true,
                'deleted' => false,
                'message' => 'Источник деактивирован (есть связанные заказы)',
            ]);
        }

        $sourceId = (int) $source->source_id;
        DB::unprepared('UPDATE calls SET source_id = NULL WHERE source_id = '.$sourceId);
        DB::unprepared('DELETE FROM sources WHERE source_id = '.$sourceId);

        return response()->json([
            'source_id' => $sourceId,
            'deactivated' => false,
            'deleted' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyPartyUrlFields(Source $source, array $validated): void
    {
        $payload = $this->partyUrlPayload($validated);
        $source->use_source_url = $payload['use_source_url'];
        $source->source_url = $payload['source_url'];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{use_source_url: bool, source_url: ?string}
     */
    private function partyUrlPayload(array $validated): array
    {
        $useUrl = (bool) ($validated['use_source_url'] ?? false);
        $url = isset($validated['source_url']) ? trim((string) $validated['source_url']) : '';

        return [
            'use_source_url' => $useUrl && $url !== '',
            'source_url' => $useUrl && $url !== '' ? $url : null,
        ];
    }
}
