<?php

namespace App\Services;

use App\Models\PromMeeting;
use App\Models\PromAppointment;
use App\Models\Promoter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PromJournalService
{
    /**
     * Получить встречи с фильтрацией
     */
    public function getMeetings(array $filters = [])
    {
        $query = PromMeeting::with(['city', 'route', 'maket', 'promoter', 'creator'])
            ->orderBy('meeting_datetime', 'desc');
        
        // Фильтр по датам
        if (!empty($filters['date_from'])) {
            $query->whereDate('meeting_datetime', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('meeting_datetime', '<=', $filters['date_to']);
        }
        
        // Фильтр по умолчанию: сегодня и завтра
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $query->whereDate('meeting_datetime', '>=', now()->startOfDay())
                  ->whereDate('meeting_datetime', '<=', now()->addDay()->endOfDay());
        }
        
        // Фильтр по статусу (поддержка массива)
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->whereIn('meeting_status', $statuses);
        }
        
        // Фильтр по городу (поддержка массива)
        if (!empty($filters['city_id'])) {
            $cityIds = is_array($filters['city_id']) ? $filters['city_id'] : [$filters['city_id']];
            $query->whereIn('city_id', $cityIds);
        }
        
        return $query->paginate(50);
    }
    
    /**
     * Получить записи с фильтрацией
     */
    public function getAppointments(array $filters = [])
    {
        $query = PromAppointment::with(['city', 'district', 'creator'])
            ->orderBy('appointment_datetime', 'desc');
        
        // Фильтр по датам
        if (!empty($filters['date_from'])) {
            $query->whereDate('appointment_datetime', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('appointment_datetime', '<=', $filters['date_to']);
        }
        
        // Фильтр по умолчанию: сегодня и завтра
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $query->whereDate('appointment_datetime', '>=', now()->startOfDay())
                  ->whereDate('appointment_datetime', '<=', now()->addDay()->endOfDay());
        }
        
        // Фильтр по статусу (поддержка массива)
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->whereIn('appointment_status', $statuses);
        }
        
        // Фильтр по городу (поддержка массива)
        if (!empty($filters['city_id'])) {
            $cityIds = is_array($filters['city_id']) ? $filters['city_id'] : [$filters['city_id']];
            $query->whereIn('city_id', $cityIds);
        }
        
        return $query->paginate(50);
    }
    
    /**
     * Создать встречу
     */
    public function createMeeting(array $data, User $creator): PromMeeting
    {
        $meeting = new PromMeeting([
            'meeting_datetime' => $data['meeting_datetime'],
            'city_id' => $data['city_id'],
            'person_phone' => $data['person_phone'] ?? null,
            'person_telegram' => $data['person_telegram'] ?? null,
            'person_age' => $data['person_age'] ?? null,
            'person_name' => $data['person_name'],
            'person_address' => $data['person_address'] ?? null,
            'is_interview' => $data['is_interview'] ?? false,
            'route_id' => $data['route_id'] ?? null,
            'maket_id' => $data['maket_id'] ?? null,
            'leaflets_issued' => $data['leaflets_issued'] ?? 0,
            'person_source' => $data['person_source'] ?? null,
            'meeting_status' => $data['meeting_status'] ?? PromMeeting::STATUS_IN_PROGRESS,
            'meeting_comment' => $data['meeting_comment'] ?? null,
        ]);
        $meeting->created_by = $creator->user_id;
        $meeting->save();
        
        // Если это собеседование — создаём промоутера
        if ($meeting->is_interview && !$meeting->isFiredStatus()) {
            $promoter = $this->createPromoterFromMeeting($meeting, $data['city_id'], $creator);
            $meeting->update(['promoter_id' => $promoter->promoter_id]);
        }
        
        return $meeting;
    }
    
    /**
     * Обновить встречу
     */
    public function updateMeeting(PromMeeting $meeting, array $data): PromMeeting
    {
        $oldStatus = $meeting->meeting_status;
        
        $meeting->update([
            'meeting_datetime' => $data['meeting_datetime'] ?? $meeting->meeting_datetime,
            'city_id' => $data['city_id'] ?? $meeting->city_id,
            'person_phone' => $data['person_phone'] ?? $meeting->person_phone,
            'person_telegram' => $data['person_telegram'] ?? $meeting->person_telegram,
            'person_age' => $data['person_age'] ?? $meeting->person_age,
            'person_name' => $data['person_name'] ?? $meeting->person_name,
            'person_address' => $data['person_address'] ?? $meeting->person_address,
            'is_interview' => $data['is_interview'] ?? $meeting->is_interview,
            'route_id' => $data['route_id'] ?? $meeting->route_id,
            'maket_id' => $data['maket_id'] ?? $meeting->maket_id,
            'leaflets_issued' => $data['leaflets_issued'] ?? $meeting->leaflets_issued,
            'person_source' => $data['person_source'] ?? $meeting->person_source,
            'meeting_status' => $data['meeting_status'] ?? $meeting->meeting_status,
            'meeting_comment' => $data['meeting_comment'] ?? $meeting->meeting_comment,
        ]);
        
        // Если статус изменился на "уволен" — увольняем промоутера
        if ($meeting->promoter_id && $meeting->isFiredStatus() && !in_array($oldStatus, PromMeeting::FIRED_STATUSES)) {
            $meeting->promoter->fire();
        }
        
        return $meeting->fresh();
    }
    
    /**
     * Создать запись
     */
    public function createAppointment(array $data, User $creator): PromAppointment
    {
        $appointment = new PromAppointment([
            'appointment_datetime' => $data['appointment_datetime'],
            'city_id' => $data['city_id'],
            'is_interview' => $data['is_interview'] ?? false,
            'person_phone' => $data['person_phone'] ?? null,
            'person_telegram' => $data['person_telegram'] ?? null,
            'person_name' => $data['person_name'],
            'district_id' => $data['district_id'] ?? null,
            'leaflets_to_prepare' => $data['leaflets_to_prepare'] ?? 0,
            'appointment_status' => $data['appointment_status'] ?? PromAppointment::STATUS_SCHEDULED,
            'appointment_comment' => $data['appointment_comment'] ?? null,
        ]);
        $appointment->created_by = $creator->user_id;
        $appointment->save();
        
        return $appointment;
    }
    
    /**
     * Обновить запись
     */
    public function updateAppointment(PromAppointment $appointment, array $data): PromAppointment
    {
        $appointment->update([
            'appointment_datetime' => $data['appointment_datetime'] ?? $appointment->appointment_datetime,
            'city_id' => $data['city_id'] ?? $appointment->city_id,
            'is_interview' => $data['is_interview'] ?? $appointment->is_interview,
            'person_phone' => $data['person_phone'] ?? $appointment->person_phone,
            'person_telegram' => $data['person_telegram'] ?? $appointment->person_telegram,
            'person_name' => $data['person_name'] ?? $appointment->person_name,
            'district_id' => $data['district_id'] ?? $appointment->district_id,
            'leaflets_to_prepare' => $data['leaflets_to_prepare'] ?? $appointment->leaflets_to_prepare,
            'appointment_status' => $data['appointment_status'] ?? $appointment->appointment_status,
            'appointment_comment' => $data['appointment_comment'] ?? $appointment->appointment_comment,
        ]);
        
        return $appointment->fresh();
    }
    
    /**
     * Создать промоутера на основе встречи
     */
    private function createPromoterFromMeeting(PromMeeting $meeting, int $cityId, User $creator): Promoter
    {
        $promoter = new Promoter([
            'city_id' => $cityId,
            'promoter_name' => $meeting->person_name,
            'promoter_phone' => $meeting->person_phone,
            'promoter_telegram' => $meeting->person_telegram,
            'promoter_age' => $meeting->person_age,
            'promoter_address' => $meeting->person_address,
            'promoter_status' => Promoter::STATUS_ACTIVE,
            'hired_at' => now(),
            'source_id' => $meeting->source_id ?? null,
        ]);
        $promoter->created_by = $creator->user_id;
        $promoter->save();
        
        return $promoter;
    }
    
    /**
     * Получить статистику за период
     */
    public function getStatistics(Carbon $from, Carbon $to, ?int $cityId = null): array
    {
        $meetingsQuery = PromMeeting::whereBetween('meeting_datetime', [$from, $to]);
        $appointmentsQuery = PromAppointment::whereBetween('appointment_datetime', [$from, $to]);
        
        if ($cityId) {
            $meetingsQuery->where('city_id', $cityId);
            $appointmentsQuery->where('city_id', $cityId);
        }
        
        return [
            'total_meetings' => (clone $meetingsQuery)->count(),
            'interviews' => (clone $meetingsQuery)->where('is_interview', true)->count(),
            'completed' => (clone $meetingsQuery)->where('meeting_status', PromMeeting::STATUS_COMPLETED)->count(),
            'total_appointments' => (clone $appointmentsQuery)->count(),
            'attended' => (clone $appointmentsQuery)->where('appointment_status', PromAppointment::STATUS_ATTENDED)->count(),
        ];
    }
}
