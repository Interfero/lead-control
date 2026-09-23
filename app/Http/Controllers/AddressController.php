<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Person;
use App\Models\User;
use App\Support\PersonClientValidation;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Проверка доступа пользователя к городу адреса
     */
    private function checkCityAccess(User $user, int $cityId): bool
    {
        return $user->hasAccessToCity($cityId);
    }
    
    /**
     * Добавление нового адреса
     */
    public function store(Request $request, int $person_id)
    {
        $person = Person::findOrFail($person_id);
        $user = $request->user();
        
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,city_id',
            'street' => ['nullable', 'string', 'max:255', PersonClientValidation::ADDRESS_FRAGMENT],
            'house' => ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT],
            'flat' => ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT],
            'address_adds' => ['nullable', 'string', 'max:' . PersonClientValidation::ADDRESS_ADDS_MAX, PersonClientValidation::ADDRESS_FRAGMENT],
        ]);
        
        // Проверка доступа к городу
        if (!$this->checkCityAccess($user, $validated['city_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этому городу'
            ], 403);
        }
        
        $address = $person->addresses()->create($validated);
        $address->load('city');
        
        return response()->json([
            'success' => true,
            'address' => $address,
            'message' => 'Адрес добавлен'
        ]);
    }
    
    /**
     * Получение данных адреса
     */
    public function show(Request $request, int $address_id)
    {
        $address = Address::with('city')->findOrFail($address_id);
        $user = $request->user();
        
        // Проверка доступа к городу адреса
        if (!$this->checkCityAccess($user, $address->city_id)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этому адресу'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'address' => $address
        ]);
    }
    
    /**
     * Редактирование адреса
     */
    public function update(Request $request, int $address_id)
    {
        $address = Address::findOrFail($address_id);
        $user = $request->user();
        
        // Проверка доступа к городу текущего адреса
        if (!$this->checkCityAccess($user, $address->city_id)) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этому адресу'
            ], 403);
        }
        
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,city_id',
            'street' => ['nullable', 'string', 'max:255', PersonClientValidation::ADDRESS_FRAGMENT],
            'house' => ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT],
            'flat' => ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT],
            'address_adds' => ['nullable', 'string', 'max:' . PersonClientValidation::ADDRESS_ADDS_MAX, PersonClientValidation::ADDRESS_FRAGMENT],
        ]);
        
        // Проверка доступа к новому городу (если меняется)
        if ($validated['city_id'] != $address->city_id && !$this->checkCityAccess($user, $validated['city_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этому городу'
            ], 403);
        }
        
        $address->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Адрес обновлён'
        ]);
    }
}
