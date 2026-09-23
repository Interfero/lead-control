<?php

namespace App\Http\Controllers;

use App\Models\PromMeeting;
use App\Models\PromAppointment;
use App\Models\City;
use App\Models\District;
use App\Models\Route;
use App\Models\RouteMaket;
use App\Services\PromJournalService;
use Illuminate\Http\Request;

class PromJournalController extends Controller
{
    public function __construct(
        private PromJournalService $journalService
    ) {}
    
    /**
     * Журнал — вкладка Встречи
     */
    public function meetings(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'status' => $request->input('status'),
            'city_id' => $request->input('city_id'),
        ];
        
        // Быстрые фильтры
        if ($request->input('quick') === 'today') {
            $filters['date_from'] = now()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        } elseif ($request->input('quick') === 'tomorrow') {
            $filters['date_from'] = now()->addDay()->format('Y-m-d');
            $filters['date_to'] = now()->addDay()->format('Y-m-d');
        }
        
        $meetings = $this->journalService->getMeetings($filters);
        $cities = City::where('is_active', true)->get();
        $statuses = PromMeeting::getStatusLabels();
        
        return view('prom.journal.meetings', compact('meetings', 'cities', 'statuses', 'filters'));
    }
    
    /**
     * Журнал — вкладка Записи
     */
    public function appointments(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'status' => $request->input('status'),
            'city_id' => $request->input('city_id'),
        ];
        
        // Быстрые фильтры
        if ($request->input('quick') === 'today') {
            $filters['date_from'] = now()->format('Y-m-d');
            $filters['date_to'] = now()->format('Y-m-d');
        } elseif ($request->input('quick') === 'tomorrow') {
            $filters['date_from'] = now()->addDay()->format('Y-m-d');
            $filters['date_to'] = now()->addDay()->format('Y-m-d');
        }
        
        $appointments = $this->journalService->getAppointments($filters);
        $cities = City::where('is_active', true)->get();
        $districts = District::where('is_active', true)->get()->groupBy('city_id');
        $statuses = PromAppointment::getStatusLabels();
        
        return view('prom.journal.appointments', compact('appointments', 'cities', 'districts', 'statuses', 'filters'));
    }
    
    /**
     * Форма создания встречи
     */
    public function createMeeting()
    {
        $cities = City::where('is_active', true)->get();
        $routes = Route::where('is_active', true)->get();
        $makets = RouteMaket::where('is_active', true)->get();
        $statuses = PromMeeting::getStatusLabels();
        
        return view('prom.journal.create-meeting', compact('cities', 'routes', 'makets', 'statuses'));
    }
    
    /**
     * Сохранить встречу
     */
    public function storeMeeting(Request $request)
    {
        $validated = $request->validate([
            'meeting_date' => 'required|date',
            'meeting_time' => 'required|date_format:H:i',
            'city_id' => 'required|exists:cities,city_id',
            'person_name' => 'required|string|max:255',
            'person_phone' => 'nullable|string|size:10',
            'person_telegram' => 'nullable|string|max:100',
            'person_age' => 'nullable|integer|min:14|max:99',
            'person_address' => 'nullable|string|max:500',
            'is_interview' => 'required|in:yes,no',
            'route_id' => 'nullable|exists:routes,route_id',
            'maket_id' => 'nullable|exists:route_makets,maket_id',
            'leaflets_issued' => 'nullable|integer|min:0',
            'person_source' => 'nullable|in:head_hunter,olx,recommendation',
            'meeting_status' => 'required|in:' . implode(',', array_keys(PromMeeting::getStatusLabels())),
            'meeting_comment' => 'nullable|string|max:1000',
        ]);
        
        $validated['is_interview'] = $validated['is_interview'] === 'yes';
        
        $validated['meeting_datetime'] = $validated['meeting_date'] . ' ' . $validated['meeting_time'];
        
        $this->journalService->createMeeting($validated, auth()->user());
        
        return redirect()->route('prom.journal.meetings')
            ->with('success', 'Встреча создана');
    }
    
    /**
     * Форма редактирования встречи
     */
    public function editMeeting(PromMeeting $meeting)
    {
        $cities = City::where('is_active', true)->get();
        $routes = Route::where('is_active', true)->get();
        $makets = RouteMaket::where('is_active', true)->get();
        $statuses = PromMeeting::getStatusLabels();
        
        return view('prom.journal.edit-meeting', compact('meeting', 'cities', 'routes', 'makets', 'statuses'));
    }
    
    /**
     * Обновить встречу
     */
    public function updateMeeting(Request $request, PromMeeting $meeting)
    {
        $validated = $request->validate([
            'meeting_date' => 'required|date',
            'meeting_time' => 'required|date_format:H:i',
            'city_id' => 'required|exists:cities,city_id',
            'person_name' => 'required|string|max:255',
            'person_phone' => 'nullable|string|size:10',
            'person_telegram' => 'nullable|string|max:100',
            'person_age' => 'nullable|integer|min:14|max:99',
            'person_address' => 'nullable|string|max:500',
            'is_interview' => 'required|in:yes,no',
            'route_id' => 'nullable|exists:routes,route_id',
            'maket_id' => 'nullable|exists:route_makets,maket_id',
            'leaflets_issued' => 'nullable|integer|min:0',
            'person_source' => 'nullable|in:head_hunter,olx,recommendation',
            'meeting_status' => 'required|in:' . implode(',', array_keys(PromMeeting::getStatusLabels())),
            'meeting_comment' => 'nullable|string|max:1000',
        ]);
        
        $validated['is_interview'] = $validated['is_interview'] === 'yes';
        
        $validated['meeting_datetime'] = $validated['meeting_date'] . ' ' . $validated['meeting_time'];
        
        $this->journalService->updateMeeting($meeting, $validated);
        
        return redirect()->route('prom.journal.meetings')
            ->with('success', 'Встреча обновлена');
    }
    
    /**
     * Форма создания записи
     */
    public function createAppointment()
    {
        $cities = City::where('is_active', true)->get();
        $districts = District::where('is_active', true)->get()->groupBy('city_id');
        $statuses = PromAppointment::getStatusLabels();
        
        return view('prom.journal.create-appointment', compact('cities', 'districts', 'statuses'));
    }
    
    /**
     * Сохранить запись
     */
    public function storeAppointment(Request $request)
    {
        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'city_id' => 'required|exists:cities,city_id',
            'person_name' => 'required|string|max:255',
            'is_interview' => 'required|in:yes,no',
            'person_phone' => 'nullable|string|size:10',
            'person_telegram' => 'nullable|string|max:100',
            'district_id' => 'nullable|exists:districts,district_id',
            'leaflets_to_prepare' => 'nullable|integer|min:0',
            'appointment_status' => 'required|in:' . implode(',', array_keys(PromAppointment::getStatusLabels())),
            'appointment_comment' => 'nullable|string|max:1000',
        ]);
        
        $validated['appointment_datetime'] = $validated['appointment_date'] . ' ' . $validated['appointment_time'];
        
        $this->journalService->createAppointment($validated, auth()->user());
        
        return redirect()->route('prom.journal.appointments')
            ->with('success', 'Запись создана');
    }
    
    /**
     * Форма редактирования записи
     */
    public function editAppointment(PromAppointment $appointment)
    {
        $cities = City::where('is_active', true)->get();
        $districts = District::where('is_active', true)->get()->groupBy('city_id');
        $statuses = PromAppointment::getStatusLabels();
        
        return view('prom.journal.edit-appointment', compact('appointment', 'cities', 'districts', 'statuses'));
    }
    
    /**
     * Обновить запись
     */
    public function updateAppointment(Request $request, PromAppointment $appointment)
    {
        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'city_id' => 'required|exists:cities,city_id',
            'person_name' => 'required|string|max:255',
            'is_interview' => 'required|in:yes,no',
            'person_phone' => 'nullable|string|size:10',
            'person_telegram' => 'nullable|string|max:100',
            'district_id' => 'nullable|exists:districts,district_id',
            'leaflets_to_prepare' => 'nullable|integer|min:0',
            'appointment_status' => 'required|in:' . implode(',', array_keys(PromAppointment::getStatusLabels())),
            'appointment_comment' => 'nullable|string|max:1000',
        ]);
        
        $validated['appointment_datetime'] = $validated['appointment_date'] . ' ' . $validated['appointment_time'];
        $validated['is_interview'] = $validated['is_interview'] === 'yes';
        
        $this->journalService->updateAppointment($appointment, $validated);
        
        return redirect()->route('prom.journal.appointments')
            ->with('success', 'Запись обновлена');
    }
    
    /**
     * Удалить встречу
     */
    public function destroyMeeting(PromMeeting $meeting)
    {
        $meeting->delete();
        
        return redirect()->route('prom.journal.meetings')
            ->with('success', 'Встреча удалена');
    }
    
    /**
     * Удалить запись
     */
    public function destroyAppointment(PromAppointment $appointment)
    {
        $appointment->delete();
        
        return redirect()->route('prom.journal.appointments')
            ->with('success', 'Запись удалена');
    }
}
