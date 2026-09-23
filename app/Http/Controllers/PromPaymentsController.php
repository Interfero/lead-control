<?php

namespace App\Http\Controllers;

use App\Models\PromPayment;
use App\Models\Promoter;
use App\Models\City;
use App\Models\Bank;
use App\Services\PromPaymentsService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PromPaymentsController extends Controller
{
    public function __construct(
        private PromPaymentsService $paymentsService
    ) {}
    
    /**
     * Список оплат
     */
    public function index(Request $request)
    {
        $filters = [
            'city_id' => $request->input('city_id'),
            'promoter_id' => $request->input('promoter_id'),
            'status' => $request->input('status'),
        ];
        
        $payments = $this->paymentsService->getPayments($filters);
        
        $cities = City::where('is_active', true)->orderBy('city_name')->get();
        $promoters = Promoter::where('promoter_status', 'active')
            ->orderBy('promoter_name')
            ->get();
        
        return view('prom.payments.index', compact('payments', 'cities', 'promoters', 'filters'));
    }
    
    /**
     * Форма создания оплаты
     */
    public function create()
    {
        $cities = City::where('is_active', true)->orderBy('city_name')->get();
        $promoters = Promoter::where('promoter_status', 'active')
            ->orderBy('promoter_name')
            ->get();
        $banks = Bank::where('is_active', true)->orderBy('bank_name')->get();
        $weeks = $this->paymentsService->getAvailableWeeks();
        
        return view('prom.payments.create', compact('cities', 'promoters', 'banks', 'weeks'));
    }
    
    /**
     * Предпросмотр оплаты (AJAX)
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,city_id',
            'promoter_id' => 'required|exists:promoters,promoter_id',
            'week_start' => 'required|date',
            'week_end' => 'required|date|after_or_equal:week_start',
        ]);
        
        $preview = $this->paymentsService->getPaymentPreview(
            $validated['city_id'],
            $validated['promoter_id'],
            Carbon::parse($validated['week_start']),
            Carbon::parse($validated['week_end'])
        );
        
        return response()->json($preview);
    }
    
    /**
     * Получить последние реквизиты промоутера (AJAX)
     */
    public function lastRequisites(int $promoterId)
    {
        $requisites = $this->paymentsService->getLastPaymentRequisites($promoterId);
        
        if (!$requisites) {
            return response()->json(['found' => false]);
        }
        
        return response()->json([
            'found' => true,
            'requisites' => $requisites['requisites'],
            'bank_id' => $requisites['bank_id'],
            'bank_name' => $requisites['bank_name'],
        ]);
    }
    
    /**
     * Сохранить оплату
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'required|exists:cities,city_id',
            'promoter_id' => 'required|exists:promoters,promoter_id',
            'week_start' => 'required|date',
            'week_end' => 'required|date|after_or_equal:week_start',
            'amount_adjustment' => 'nullable|integer',
            'payment_requisites' => 'nullable|string|max:255',
            'payment_bank_id' => 'nullable|exists:banks,bank_id',
            'payment_comment' => 'nullable|string|max:1000',
        ]);
        
        try {
            $payment = $this->paymentsService->create($validated, auth()->user());
            
            return redirect()
                ->route('prom.payments.show', $payment)
                ->with('success', 'Оплата создана');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
    
    /**
     * Просмотр оплаты
     */
    public function show(PromPayment $payment)
    {
        $payment->load(['city', 'promoter', 'bank', 'cfmOperation', 'details', 'creator']);
        $banks = Bank::where('is_active', true)->orderBy('bank_name')->get();
        
        return view('prom.payments.show', compact('payment', 'banks'));
    }
    
    /**
     * Обновить оплату
     */
    public function update(Request $request, PromPayment $payment)
    {
        $validated = $request->validate([
            'amount_adjustment' => 'nullable|integer',
            'payment_requisites' => 'nullable|string|max:255',
            'payment_bank_id' => 'nullable|exists:banks,bank_id',
            'payment_comment' => 'nullable|string|max:1000',
        ]);
        
        try {
            $this->paymentsService->update($payment, $validated);
            
            return redirect()
                ->route('prom.payments.show', $payment)
                ->with('success', 'Оплата обновлена');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
    
    /**
     * Удалить оплату
     */
    public function destroy(PromPayment $payment)
    {
        try {
            $this->paymentsService->delete($payment);
            
            return redirect()
                ->route('prom.payments')
                ->with('success', 'Оплата удалена');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
    
    /**
     * Получить текст для копирования (AJAX)
     */
    public function copyText(PromPayment $payment)
    {
        $payment->load(['promoter', 'details', 'bank']);
        
        $statusLabel = $payment->payment_status === 'created' ? 'Сформировано' : 'Оплачено';
        
        $lines = [
            "Оплата №{$payment->payment_id} - {$statusLabel}",
            "Промоутер: {$payment->promoter?->promoter_name}",
            "Неделя: {$payment->week_start->format('d.m')} - {$payment->week_end->format('d.m.Y')}",
        ];
        
        $i = 1;
        foreach ($payment->details as $detail) {
            $date = Carbon::parse($detail->action_date)->format('d.m.y');
            $lines[] = "{$i}. {$date} - {$detail->leaflets_count} - {$detail->route_name}";
            $i++;
        }
        
        $lines[] = "Всего листовок: {$payment->total_leaflets}";
        $lines[] = "Расчётный коэффициент: {$payment->rate_per_leaflet} тг";
        $lines[] = "Итого: {$payment->amount_base} тг";
        $lines[] = "Корректировки: {$payment->amount_adjustment} тг";
        $lines[] = "К оплате: {$payment->amount_total} тг";
        
        // Добавляем реквизиты
        $lines[] = "";
        $bankName = $payment->bank?->bank_name ?? '';
        $requisites = $payment->payment_requisites ?? '';
        if ($bankName || $requisites) {
            $lines[] = "Реквизиты: {$bankName}" . ($bankName && $requisites ? ' - ' : '') . $requisites;
        }
        
        return response()->json([
            'text' => implode("\n", $lines)
        ]);
    }
}
