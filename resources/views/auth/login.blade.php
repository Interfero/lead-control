<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Вход — Lead Control</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ filemtime(public_path('favicon.png')) }}" sizes="128x128">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ filemtime(public_path('apple-touch-icon.png')) }}">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jetbrains-mono:400,500,600,700&display=swap" rel="stylesheet" />
    {{-- Legacy: если Vite-артефакты на сервере не совпали с manifest — остаётся базовая вёрстка (как в layouts/app) --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col md:flex-row font-sans antialiased bg-gradient-to-br from-primary via-[#4c1d95] to-[#2d1b5e]">
    <div class="hidden md:flex flex-1 items-center justify-center p-8 text-primary-foreground">
        <div class="text-5xl md:text-6xl font-light tracking-widest text-center">Lead Control</div>
    </div>

    <div class="flex flex-1 items-center justify-center p-6 md:p-8 min-h-[50vh] md:min-h-screen">
        <x-ui.card class="w-full max-w-md p-8 md:p-10 shadow-2xl border-0 bg-card">
            <h1 class="text-xl font-semibold text-center text-foreground mb-6">Вход в личный кабинет</h1>

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

            <form method="POST" action="{{ route('crm.login.submit') }}" class="space-y-5">
                @csrf

                <x-ui.form-group label="E-mail" name="email" class="mb-0">
                    <x-ui.input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        :error="$errors->has('email')"
                    />
                </x-ui.form-group>

                <x-ui.form-group label="Пароль" name="password" class="mb-0">
                    <div class="relative">
                        <x-ui.input
                            type="password"
                            name="password"
                            id="password"
                            required
                            class="pr-11"
                            :error="$errors->has('password')"
                        />
                        <button
                            type="button"
                            class="password-toggle absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                            onclick="togglePassword()"
                            aria-label="Показать или скрыть пароль"
                        >
                            <span id="password-toggle-icon">{!! icon('eye') !!}</span>
                        </button>
                    </div>
                </x-ui.form-group>

                <x-ui.button
                    type="submit"
                    class="w-full justify-center bg-gradient-to-r from-primary to-indigo-500 hover:opacity-95 border-0"
                >
                    Войти
                </x-ui.button>
            </form>
        </x-ui.card>
    </div>

    <div class="md:hidden flex items-center justify-center py-6 text-primary-foreground/90 text-2xl font-light tracking-wide">
        Lead Control
    </div>

    <script>
        const eyeHtml = {!! json_encode(icon('eye')) !!};
        const eyeOffHtml = {!! json_encode(icon('eye_off')) !!};

        function togglePassword() {
            const input = document.getElementById('password');
            const iconEl = document.getElementById('password-toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                iconEl.innerHTML = eyeOffHtml;
            } else {
                input.type = 'password';
                iconEl.innerHTML = eyeHtml;
            }
        }
    </script>
</body>
</html>
