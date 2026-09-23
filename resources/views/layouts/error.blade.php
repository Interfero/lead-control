<!DOCTYPE html>
<html lang="ru" class="{{ auth()->check() && (auth()->user()->theme ?? 'light') === 'dark' ? 'dark' : '' }}">
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
    <title>@yield('title', 'Ошибка') — Lead Control CRM</title>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jetbrains-mono:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-foreground font-sans min-h-screen flex flex-col">
    <nav class="bg-sidebar h-[60px] px-6 flex items-center justify-between">
        <a href="{{ hub_login_url() }}" class="text-sidebar-foreground text-xl font-semibold no-underline">LC</a>
        @auth
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('settings') }}"
                    class="text-sidebar-foreground/60 hover:text-sidebar-foreground border border-sidebar-foreground/40 hover:border-sidebar-foreground px-4 py-2 rounded-md text-sm no-underline transition-all duration-200"
                >
                    Настройки
                </a>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="text-sidebar-foreground/60 hover:text-sidebar-foreground border border-sidebar-foreground/40 hover:border-sidebar-foreground px-4 py-2 rounded-md text-sm transition-all duration-200 bg-transparent cursor-pointer"
                    >
                        Выход
                    </button>
                </form>
            </div>
        @else
            <a
                href="{{ hub_login_url() }}"
                class="text-sidebar-foreground/60 hover:text-sidebar-foreground border border-sidebar-foreground/40 hover:border-sidebar-foreground px-4 py-2 rounded-md text-sm no-underline transition-all duration-200"
            >
                Войти
            </a>
        @endauth
    </nav>
    <main class="flex-1 flex items-center justify-center p-8">
        @yield('content')
    </main>
</body>
</html>
