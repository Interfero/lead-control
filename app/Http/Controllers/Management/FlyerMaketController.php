<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\FlyerMaket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FlyerMaketController extends Controller
{
    public function index(): View
    {
        $makets = FlyerMaket::query()
            ->orderBy('flyer_maket_name')
            ->paginate(50);

        return view('management.flyer-makets.index', compact('makets'));
    }

    public function create(): View
    {
        return view('management.flyer-makets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'flyer_maket_name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        FlyerMaket::create([
            'flyer_maket_name' => $validated['flyer_maket_name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('management.flyer-makets.index')
            ->with('success', 'Макет создан');
    }

    public function edit(FlyerMaket $flyer_maket): View
    {
        return view('management.flyer-makets.edit', ['maket' => $flyer_maket]);
    }

    public function update(Request $request, FlyerMaket $flyer_maket): RedirectResponse
    {
        $validated = $request->validate([
            'flyer_maket_name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $flyer_maket->update([
            'flyer_maket_name' => $validated['flyer_maket_name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('management.flyer-makets.index')
            ->with('success', 'Макет сохранён');
    }
}
