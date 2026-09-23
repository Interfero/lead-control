<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Collection;

class ComplaintService
{
    /**
     * Получить список претензий с фильтрами
     */
    public function getComplaints(User $user, array $filters = [])
    {
        $query = Complaint::with(['person', 'order', 'city', 'createdBy', 'closedBy']);

        $cityIds = $user->cityIdsForScope();
        if ($cityIds !== null) {
            $query->whereIn('city_id', $cityIds);
        }

        // Фильтр по статусу
        if (!empty($filters['status'])) {
            $query->whereIn('complaint_status', (array) $filters['status']);
        }

        // Фильтр по типу (один или несколько)
        if (!empty($filters['type'])) {
            $query->whereIn('complaint_type', (array) $filters['type']);
        }

        // Фильтр по городу
        if (!empty($filters['city_id'])) {
            $query->whereIn('city_id', (array) $filters['city_id']);
        }

        if (!empty($filters['search_id'])) {
            $query->where('complaint_id', (int) $filters['search_id']);
        }

        if (!empty($filters['search_order'])) {
            $query->where('order_id', (int) $filters['search_order']);
        }

        // Фильтр по дате
        if (!empty($filters['date_from'])) {
            $query->where('complaint_created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('complaint_created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Поиск по ID заказа или имени клиента
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('complaint_id', $search)
                  ->orWhereHas('person', fn($pq) => $pq->where('person_name', 'LIKE', "%{$search}%"))
                  ->orWhereHas('order', fn($oq) => $oq->where('order_id', $search));
            });
        }

        return $query->orderByDesc('complaint_created_at')->paginate(50);
    }

    /**
     * Создать претензию
     */
    public function create(array $data, int $createdBy): Complaint
    {
        return Complaint::create([
            'person_id' => $data['person_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'complaint_type' => $data['complaint_type'],
            'complaint_text' => $data['complaint_text'],
            'complaint_status' => Complaint::STATUS_NEW,
            'complaint_created_at' => now(),
            'complaint_created_by' => $createdBy,
        ]);
    }

    /**
     * Обновить претензию
     */
    public function update(Complaint $complaint, array $data): Complaint
    {
        $complaint->update([
            'complaint_type' => $data['complaint_type'] ?? $complaint->complaint_type,
            'complaint_text' => $data['complaint_text'] ?? $complaint->complaint_text,
            'complaint_status' => $data['complaint_status'] ?? $complaint->complaint_status,
            'complaint_result' => $data['complaint_result'] ?? $complaint->complaint_result,
        ]);

        return $complaint;
    }

    /**
     * Закрыть претензию (решена / отклонена)
     */
    public function close(Complaint $complaint, string $status, string $result, int $closedBy): Complaint
    {
        $complaint->update([
            'complaint_status' => $status,
            'complaint_result' => $result,
            'complaint_closed_at' => now(),
            'complaint_closed_by' => $closedBy,
        ]);

        return $complaint;
    }

    /**
     * Статистика по претензиям для отчёта
     */
    public function getReport(User $user, array $filters = []): Collection
    {
        $query = Complaint::query();

        $cityIds = $user->cityIdsForScope();
        if ($cityIds !== null) {
            $query->whereIn('city_id', $cityIds);
        }

        if (!empty($filters['date_from'])) {
            $query->where('complaint_created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('complaint_created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query->with('city')
            ->get()
            ->groupBy('city_id')
            ->map(function ($complaints, $cityId) {
                $city = $complaints->first()->city;
                return [
                    'city_name' => $city?->city_name ?? 'Без города',
                    'total' => $complaints->count(),
                    'new' => $complaints->where('complaint_status', Complaint::STATUS_NEW)->count(),
                    'in_progress' => $complaints->where('complaint_status', Complaint::STATUS_IN_PROGRESS)->count(),
                    'resolved' => $complaints->where('complaint_status', Complaint::STATUS_RESOLVED)->count(),
                    'rejected' => $complaints->where('complaint_status', Complaint::STATUS_REJECTED)->count(),
                    'by_type' => collect(Complaint::TYPES)->mapWithKeys(function ($label, $type) use ($complaints) {
                        return [$type => $complaints->where('complaint_type', $type)->count()];
                    }),
                ];
            });
    }
}
