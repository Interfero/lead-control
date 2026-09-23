<!DOCTYPE html>
<html lang="ru" class="{{ (auth()->user()->theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="crm-prefix" content="/crm">
    <script>
        window.crmUrl = function (path) {
            if (!path) return '/crm';
            if (/^https?:\/\//i.test(path)) return path;
            if (path.startsWith('/crm/') || path === '/crm') return path;
            return '/crm' + (path.startsWith('/') ? path : '/' + path);
        };
    </script>
    <title>Lead Control</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ filemtime(public_path('favicon.png')) }}" sizes="128x128">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ filemtime(public_path('apple-touch-icon.png')) }}">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jetbrains-mono:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-foreground font-sans min-h-screen">
    <nav class="navbar bg-sidebar text-sidebar-foreground">
        <span class="navbar-brand">LC</span>
        <div class="flex items-center gap-3">
            <button type="button" class="navbar-toolbar-btn js-theme-toggle" title="Тема" aria-label="Переключить тему">
                <span data-theme-icon="dark" class="hidden" aria-hidden="true">🌙</span>
                <span data-theme-icon="light" class="hidden" aria-hidden="true">☀️</span>
            </button>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <x-ui.button
                    type="submit"
                    variant="outline"
                    size="sm"
                    class="border-sidebar-foreground/40 text-sidebar-foreground/80 hover:text-sidebar-foreground hover:border-sidebar-foreground"
                >
                    {!! icon('logout') !!} Выход
                </x-ui.button>
            </form>
        </div>
    </nav>

    <main class="flex flex-col items-center justify-center min-h-[calc(100vh-60px)] text-center p-8">
        @yield('content')
    </main>
</body>
</html>
