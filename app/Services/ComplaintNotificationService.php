<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintView;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Оповещения филиала о новых претензиях (как непросмотренные заявки в OrderCityViewService).
 */
class ComplaintNotificationService
{
    public const NOTIFY_ROLES = ['senior_manager', 'branch_head'];

    public function recordFirstView(Complaint $complaint, User $user): void
    {
        if (! $user->hasAnyRole(self::NOTIFY_ROLES)) {
            return;
        }

        if (! $user->hasAccessToCity($complaint->city_id)) {
            return;
        }

        ComplaintView::firstOrCreate(
            [
                'complaint_id' => $complaint->complaint_id,
                'user_id' => $user->user_id,
            ],
            ['viewed_at' => now()]
        );
    }

    public function baseOpenComplaintsQuery(User $user, ?array $listFilters = null): Builder
    {
        $query = Complaint::query()
            ->whereIn('complaint_status', [Complaint::STATUS_NEW, Complaint::STATUS_IN_PROGRESS]);

        $cityIds = $user->cityIdsForScope();
        if ($cityIds === null) {
            // null — доступ ко всем городам
        } elseif ($cityIds === []) {
            $query->whereRaw('0 = 1');
        } else {
            $query->whereIn('city_id', $cityIds);
        }

        if ($listFilters !== null) {
            if (! empty($listFilters['date_from'])) {
                $query->where('complaint_created_at', '>=', $listFilters['date_from']);
            }
            if (! empty($listFilters['date_to'])) {
                $query->where('complaint_created_at', '<=', $listFilters['date_to'].' 23:59:59');
            }
            if (! empty($listFilters['city_id'])) {
                $query->whereIn('city_id', (array) $listFilters['city_id']);
            }
        }

        return $query;
    }

    /**
     * Фильтры списка претензий по умолчанию для филиала (как в ComplaintController@index).
     *
     * @return array{date_from: ?string, date_to: ?string}
     */
    public function defaultListFiltersForUser(User $user): array
    {
        if ($user->hasAnyRole(self::NOTIFY_ROLES)) {
            return ['date_from' => null, 'date_to' => null];
        }

        return [
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->endOfMonth()->format('Y-m-d'),
        ];
    }

    public function countUnseen(User $user): int
    {
        if (! $user->hasAnyRole(self::NOTIFY_ROLES)) {
            return 0;
        }

        return (int) $this->baseOpenComplaintsQuery($user, $this->defaultListFiltersForUser($user))
            ->whereDoesntHave('complaintViews', function ($q) use ($user) {
                $q->where('user_id', $user->user_id);
            })
            ->count();
    }

    /**
     * @return array<int, int>
     */
    public function getUnseenIds(User $user): array
    {
        if (! $user->hasAnyRole(self::NOTIFY_ROLES)) {
            return [];
        }

        return $this->baseOpenComplaintsQuery($user, $this->defaultListFiltersForUser($user))
            ->whereDoesntHave('complaintViews', function ($q) use ($user) {
                $q->where('user_id', $user->user_id);
            })
            ->orderByDesc('complaint_id')
            ->limit(500)
            ->pluck('complaint_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
