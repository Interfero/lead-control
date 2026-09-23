<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Отображение страницы настроек
     */
    public function index()
    {
        return view('settings.index');
    }
    
    /**
     * Обновление пароля пользователя
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['call_center', 'senior_manager', 'branch_head', 'tech_director'])) {
            abort(403, 'Смена пароля в настройках для вашей роли отключена');
        }

        $validated = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ], [
            'current_password.required' => 'Текущий пароль обязателен',
            'new_password.required' => 'Новый пароль обязателен',
            'new_password.min' => 'Новый пароль должен содержать минимум 8 символов',
            'new_password.confirmed' => 'Пароли не совпадают',
        ]);
        
        // Проверяем текущий пароль
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Неверный текущий пароль'])->withInput();
        }
        
        // Обновляем пароль
        $user->forceFill([
            'password' => $validated['new_password'], // cast hashed
            'must_change_password' => false,
            'password_set_at' => now(),
        ])->save();
        
        return back()->with('success', 'Пароль успешно изменён');
    }

    /**
     * Сохранение темы оформления (светлая / тёмная).
     */
    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:dark,light'],
        ]);

        $user = auth()->user();
        $user->theme = $validated['theme'];
        $user->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Тема обновлена.');
    }
}
