<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD видов работ (справочник work_types) — заменяет хардкод Order::EQUIPMENT_TYPES/CORE_EQUIPMENT.
 * code — тот же строковый код, что уходит в SuperPart/GM/Desk (crm_code/equipmentType) и хранится в
 * orders.equipment_type, поэтому его нельзя менять после создания (только name/ставки/статус).
 */
class WorkTypeManagementController extends Controller
{
    public function index(): View
    {
        $workTypes = WorkType::query()->ordered()->get();

        return view('management.work-types.index', compact('workTypes'));
    }

    public function create(): View
    {
        return view('management.work-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        WorkType::create($validated + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('management.work-types.index')->with('success', 'Вид работ создан');
    }

    public function edit(WorkType $workType): View
    {
        return view('management.work-types.edit', compact('workType'));
    }

    public function update(Request $request, WorkType $workType): RedirectResponse
    {
        $validated = $this->validated($request, $workType);

        $workType->update($validated + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('management.work-types.index')->with('success', 'Вид работ обновлён');
    }

    /**
     * Удаление запрещено, если код уже используется хоть одной заявкой — такую заявку мы бы
     * сломали (equipment_type стал бы «висячей» строкой без подписи/ставки). В этом случае
     * предлагаем деактивировать вместо удаления.
     */
    public function destroy(WorkType $workType): RedirectResponse
    {
        if (Order::query()->where('equipment_type', $workType->code)->exists()) {
            return back()->with('error', 'Нельзя удалить: есть заявки с этим видом работ. Деактивируйте вместо удаления.');
        }

        $workType->delete();

        return redirect()->route('management.work-types.index')->with('success', 'Вид работ удалён');
    }

    /**
     * code валидируется и принимается только при создании — при редактировании его вообще нет
     * в правилах, так что даже подменённое в запросе значение будет отброшено validate()
     * и не попадёт в $workType->update(). Это и есть неизменяемость кода после создания.
     */
    private function validated(Request $request, ?WorkType $workType = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'order_core' => ['required', 'in:core,non_core,other'],
            'partner_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'partner_cap' => ['required', 'numeric', 'min:0'],
            'master_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        if ($workType === null) {
            $rules['code'] = ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('work_types', 'code')];
        }

        return $request->validate($rules);
    }
}
