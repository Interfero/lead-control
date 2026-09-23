<?php

namespace App\Http\Controllers;

use App\Models\PersonPhone;
use App\Models\Person;
use App\Models\User;
use App\Support\PersonClientValidation;
use Illuminate\Http\Request;

class PersonPhoneController extends Controller
{
    /**
     * Проверка доступа пользователя к персоне через города её адресов
     */
    private function checkPersonAccess(User $user, Person $person): bool
    {
        if ($user->hasRole('developer') || $user->hasAllCitiesAccess()) {
            return true;
        }

        $personCityIds = $person->addresses()->pluck('city_id')->unique()->toArray();

        if (empty($personCityIds)) {
            return true;
        }

        foreach ($personCityIds as $cityId) {
            if ($user->hasAccessToCity((int) $cityId)) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Добавление нового телефона
     */
    public function store(Request $request, int $person_id)
    {
        $person = Person::findOrFail($person_id);
        $user = $request->user();
        
        // Проверка доступа к персоне
        if (!$this->checkPersonAccess($user, $person)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этой персоне'
            ], 403);
        }
        
        $validated = $request->validate([
            'phone_number' => ['required', 'string', PersonClientValidation::PHONE_DIGITS_10],
            'phone_adds' => ['nullable', 'string', 'max:255', PersonClientValidation::ADDRESS_FRAGMENT],
        ]);
        
        $phone = $person->phones()->create($validated);
        
        return response()->json([
            'success' => true,
            'phone' => $phone,
            'message' => 'Телефон добавлен'
        ]);
    }
    
    /**
     * Редактирование телефона
     */
    public function update(Request $request, int $phone_id)
    {
        $phone = PersonPhone::with('person')->findOrFail($phone_id);
        $user = $request->user();
        
        // Проверка доступа к персоне
        if (!$this->checkPersonAccess($user, $phone->person)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этой персоне'
            ], 403);
        }
        
        $validated = $request->validate([
            'phone_number' => ['required', 'string', PersonClientValidation::PHONE_DIGITS_10],
            'phone_adds' => ['nullable', 'string', 'max:255', PersonClientValidation::ADDRESS_FRAGMENT],
        ]);
        
        $phone->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Телефон обновлён'
        ]);
    }

    /**
     * Раскрыть полный номер по клику (для ролей без мгновенного доступа).
     */
    public function reveal(Request $request, int $phone_id)
    {
        $phone = PersonPhone::with(['person.addresses'])->findOrFail($phone_id);
        $user = $request->user();

        if (! $this->checkPersonAccess($user, $phone->person)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этой персоне',
            ], 403);
        }

        if (! config('privacy.phone_reveal_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Раскрытие номеров отключено',
            ], 403);
        }

        app(\App\Services\SecurityAuditService::class)->log(
            'phone_reveal',
            $user,
            'person_phone',
            (string) $phone->phone_id,
            [
                'person_id' => $phone->person_id,
            ],
            $request
        );

        return response()->json([
            'success' => true,
            'phone_id' => $phone->phone_id,
            'phone_number' => $phone->phone_number,
            'formatted' => $phone->formatted_phone,
            'phone_adds' => $phone->phone_adds,
        ]);
    }
}
