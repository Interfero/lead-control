<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Настройка 2FA — Lead Control</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary via-[#4c1d95] to-[#2d1b5e] p-6">
    <x-ui.card class="w-full max-w-lg p-8 shadow-2xl border-0 bg-card">
        <h1 class="text-xl font-semibold text-center mb-2">Двухфакторная аутентификация</h1>
        <p class="text-sm text-muted-foreground text-center mb-6">
            Для роли разработчика / гендиректора 2FA обязательна. Отсканируйте QR в Google Authenticator / Authy или введите ключ вручную.
        </p>

        @if (session('warning'))
            <x-ui.alert type="warning" class="mb-4">{{ session('warning') }}</x-ui.alert>
        @endif
        @if ($errors->any())
            <x-ui.alert type="error" class="mb-4">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </x-ui.alert>
        @endif

        <div class="flex flex-col items-center gap-3 mb-6">
            <img src="{{ $qrUrl }}" alt="QR для 2FA" width="200" height="200" class="rounded-md bg-white p-2">
            <p class="text-xs text-muted-foreground break-all text-center">Ключ: <code class="font-mono">{{ $secret }}</code></p>
        </div>

        <form method="POST" action="{{ route('two-factor.setup.confirm') }}" class="space-y-4">
            @csrf
            <x-ui.form-group label="Код из приложения" name="code" class="mb-0">
                <x-ui.input
                    type="text"
                    name="code"
                    id="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    required
                    autofocus
                    class="text-center text-2xl tracking-widest"
                />
            </x-ui.form-group>
            <x-ui.button type="submit" class="w-full">Подтвердить и включить</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-muted-foreground underline">Выйти</button>
        </form>
    </x-ui.card>
</body>
</html>
