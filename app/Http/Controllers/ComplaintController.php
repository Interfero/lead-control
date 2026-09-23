<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\City;
use App\Models\Person;
use App\Models\Order;
use App\Models\User;
use App\Services\ComplaintNotificationService;
use App\Services\ComplaintService;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function __construct(
        private ComplaintService $complaintService,
        private ComplaintNotificationService $complaintNotifications,
    ) {}

    /**
     * Список претензий
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $isBranchOkk = $user->hasAnyRole(ComplaintNotificationService::NOTIFY_ROLES);

        $filters = [
            'status' => $request->input('status'),
            'type' => $request->input('type'),
            'city_id' => $request->input('city_id'),
            'date_from' => $request->has('date_from')
                ? $request->input('date_from')
                : ($isBranchOkk ? null : now()->startOfMonth()->format('Y-m-d')),
            'date_to' => $request->has('date_to')
                ? $request->input('date_to')
                : ($isBranchOkk ? null : now()->endOfMonth()->format('Y-m-d')),
            'search' => $request->input('search'),
            'search_id' => $request->input('search_id'),
            'search_order' => $request->input('search_order'),
        ];
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        $complaints = $this->complaintService->getComplaints($user, $filters);

        $cities = $user->accessibleCities()->get();

        return view('complaints.index', compact('complaints', 'cities', 'filters', 'dateFrom', 'dateTo'));
    }

    /**
     * Показать претензию
     */
    public function show(int $complaint_id)
    {
        $user = auth()->user();

        $complaint = Complaint::with([
            'person.phones',
            'order.address.city',
            'order.master',
            'city',
            'createdBy',
            'closedBy',
            'comments.author',
        ])->findOrFail($complaint_id);

        $this->ensureComplaintAccessibleToUser($user, $complaint);

        $this->complaintNotifications->recordFirstView($complaint, $user);

        return view('complaints.show', compact('complaint'));
    }

    /**
     * Форма создания
     */
    public function create(Request $request)
    {
        $user = auth()->user();

        $cities = $user->accessibleCities()->get();

        // Предзаполнение из заявки или клиента
        $person = null;
        $order = null;
        $presetCityId = null;

        if ($request->filled('order_id')) {
            $order = Order::with(['persons', 'address.city'])->find($request->order_id);
            if ($order) {
                $person = $order->persons->first();
                $presetCityId = $order->address->city_id ?? null;
            }
        } elseif ($request->filled('person_id')) {
            $person = Person::with('addresses.city')->find($request->person_id);
            if ($person) {
                $presetCityId = $person->addresses->first()?->city_id;
            }
        }

        return view('complaints.create', compact('cities', 'person', 'order', 'presetCityId'));
    }

    /**
     * Сохранить новую претензию
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'person_id' => 'nullable|exists:persons,person_id',
            'order_id' => 'nullable|exists:orders,order_id',
            'city_id' => 'required|exists:cities,city_id',
            'complaint_type' => 'required|in:' . implode(',', array_keys(Complaint::TYPES)),
            'complaint_text' => 'required|string|max:5000',
        ]);

        $complaint = $this->complaintService->create($validated, auth()->id());

        return redirect()->route('complaints.show', $complaint->complaint_id)
            ->with('success', 'Претензия создана');
    }

    /**
     * Обновить претензию
     */
    public function update(Request $request, int $complaint_id)
    {
        $complaint = Complaint::findOrFail($complaint_id);
        $this->ensureComplaintAccessibleToUser(auth()->user(), $complaint);

        $validated = $request->validate([
            'complaint_type' => 'required|in:' . implode(',', array_keys(Complaint::TYPES)),
            'complaint_text' => 'required|string|max:5000',
            'complaint_status' => 'required|in:' . implode(',', array_keys(Complaint::STATUSES)),
            'complaint_result' => 'nullable|string|max:5000',
        ]);

        $this->complaintService->update($complaint, $validated);

        // Если статус стал закрытым — проставляем дату закрытия
        if (in_array($validated['complaint_status'], [Complaint::STATUS_RESOLVED, Complaint::STATUS_REJECTED])
            && !$complaint->complaint_closed_at) {
            $complaint->update([
                'complaint_closed_at' => now(),
                'complaint_closed_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Претензия обновлена');
    }

    /**
     * Отчёт по претензиям
     */
    public function report(Request $request)
    {
        $user = auth()->user();

        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $report = $this->complaintService->getReport($user, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return view('complaints.report', compact('report', 'dateFrom', 'dateTo'));
    }

    /**
     * Добавить комментарий к претензии
     */
    public function storeComment(Request $request, int $complaint_id)
    {
        $complaint = Complaint::findOrFail($complaint_id);
        $this->ensureComplaintAccessibleToUser(auth()->user(), $complaint);

        $validated = $request->validate([
            'comment_text' => 'required|string|max:2000',
        ]);

        $recentDuplicate = \App\Models\Comment::query()
            ->where('commentable_type', Complaint::class)
            ->where('commentable_id', $complaint->complaint_id)
            ->where('created_by', auth()->id())
            ->where('comment_text', $validated['comment_text'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($recentDuplicate) {
            return back()->with('success', 'Комментарий уже добавлен');
        }

        $comment = new \App\Models\Comment([
            'commentable_type' => Complaint::class,
            'commentable_id' => $complaint->complaint_id,
            'comment_text' => $validated['comment_text'],
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
        $comment->save();

        return back()->with('success', 'Комментарий добавлен');
    }

    /**
     * Претензия должна относиться к городу пользователя (кроме разработчика).
     */
    private function ensureComplaintAccessibleToUser(User $user, Complaint $complaint): void
    {
        if ($user->hasRole('developer') || $user->hasAllCitiesAccess()) {
            return;
        }

        if ($complaint->city_id && ! $user->hasAccessToCity((int) $complaint->city_id)) {
            abort(403, 'Нет доступа к этой претензии');
        }
    }

}
