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
    <title>@yield('title', 'Lead Control') — Lead Control</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ filemtime(public_path('favicon.png')) }}" sizes="128x128">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ filemtime(public_path('apple-touch-icon.png')) }}">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jetbrains-mono:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
@php
    $cityNotifyRoles = \App\Services\OrderCityViewService::NOTIFY_ROLES;
    $callbackNotifyRoles = ['call_center', 'senior_dispatcher', 'developer'];
@endphp
<body
    class="min-h-screen"
    @auth
        @if(auth()->user()->hasAnyRole($cityNotifyRoles))
            data-city-unseen-poll="1"
            data-city-unseen-count-url="{{ route('notifications.unseen-city-orders-count') }}"
            data-city-orders-base-url="{{ route('orders.index') }}"
            data-city-notify-icon="{{ asset('apple-touch-icon.png') }}"
            data-city-notify-sound="/crm/sounds/order-notify.mp3"
            data-complaint-unseen-poll="1"
            data-complaint-unseen-count-url="{{ route('notifications.unseen-complaints-count') }}"
            data-complaints-base-url="{{ route('complaints.index') }}"
            data-complaint-notify-sound="/crm/sounds/order-notify.mp3"
        @endif
        @if(auth()->user()->hasAnyRole($callbackNotifyRoles))
            data-callback-due-poll="1"
            data-callback-due-count-url="{{ route('notifications.due-callbacks-count') }}"
            data-callback-orders-base-url="{{ url('/crm/orders') }}"
            data-callback-notify-sound="/crm/sounds/order-notify.mp3"
        @endif
    @endauth
>
    {{-- Навбар --}}
    <nav class="navbar bg-sidebar text-sidebar-foreground" id="mainNavbar">
        <button type="button" class="mobile-toggle" onclick="toggleMobileMenu()">
            {!! icon('menu') !!}
        </button>

        <div class="navbar-brand-group">
            <a href="{{ route('orders.index', ['clear_filters' => 1]) }}" class="navbar-brand" title="Главная — сброс фильтров заказов">
                <span class="brand-short">LC</span>
                <span class="brand-full">Lead Control</span>
            </a>
            @hasSection('navbar_context')
                <div class="navbar-page-context" title="@yield('navbar_context')">@yield('navbar_context')</div>
            @endif
        </div>

        <ul class="navbar-menu" id="navbarMenu">
            @foreach ($navigation ?? [] as $item)
                @if (isset($item['children']))
                    {{-- Пункт с выпадающим меню --}}
                    <li class="navbar-item dropdown">
                        <a href="#" class="navbar-link dropdown-toggle {{ $item['active'] ? 'active' : '' }}" onclick="toggleNavbarDropdown(event, this)">
                            {!! icon($item['icon']) !!}
                            {{ $item['name'] }}
                            @if (($item['name'] ?? '') === 'ОКК' && auth()->user()->hasAnyRole(['senior_manager', 'branch_head']))
                                <span
                                    data-complaint-unseen-badge
                                    @if(($unseenComplaintsCount ?? 0) === 0) hidden @endif
                                    @if(($unseenComplaintsCount ?? 0) === 0) aria-hidden="true" @else aria-hidden="false" @endif
                                    class="ml-1 inline-flex min-h-[1.125rem] min-w-[1.125rem] shrink-0 items-center justify-center rounded-full bg-destructive px-1.5 text-[0.65rem] font-bold leading-none text-destructive-foreground"
                                    title="Новые претензии без просмотра филиалом"
                                >{{ ($unseenComplaintsCount ?? 0) > 99 ? '99+' : (int) ($unseenComplaintsCount ?? 0) }}</span>
                            @endif
                            @if ($item['in_development'] ?? false)
                                <span class="badge">DEV</span>
                            @endif
                        </a>
                        <div class="dropdown-menu">
                            @foreach ($item['children'] as $child)
                                <a href="{{ $child['url'] ?? route($child['route']) }}" class="dropdown-item">
                                    {{ $child['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </li>
                @else
                    {{-- Обычный пункт --}}
                    <li class="navbar-item">
                        <a href="{{ $item['url'] ?? route($item['route']) }}"
                           class="navbar-link {{ $item['active'] ? 'active' : '' }}">
                            {!! icon($item['icon']) !!}
                            {{ $item['name'] }}
                            @auth
                                @if(auth()->user()->hasAnyRole($cityNotifyRoles) && ($item['route'] ?? '') === 'orders.index')
                                    <span
                                        data-city-unseen-badge
                                        @if(($unseenCityOrdersCount ?? 0) === 0) hidden @endif
                                        @if(($unseenCityOrdersCount ?? 0) === 0) aria-hidden="true" @else aria-hidden="false" @endif
                                        class="ml-1 inline-flex min-h-[1.125rem] min-w-[1.125rem] shrink-0 items-center justify-center rounded-full bg-destructive px-1.5 text-[0.65rem] font-bold leading-none text-destructive-foreground"
                                        title="Заявки без просмотра филиалом (текущий месяц)"
                                    >{{ ($unseenCityOrdersCount ?? 0) > 99 ? '99+' : (int) ($unseenCityOrdersCount ?? 0) }}</span>
                                @endif
                            @endauth
                            @if ($item['in_development'] ?? false)
                                <span class="badge">DEV</span>
                            @endif
                        </a>
                    </li>
                @endif
            @endforeach

            @auth
                <li class="navbar-mobile-user">
                    @include('partials.navbar-profile', ['variant' => 'mobile'])
                    <button
                        type="button"
                        class="navbar-menu-theme js-theme-toggle"
                        title="Тема"
                        aria-label="Переключить тему"
                    >
                        <span class="navbar-menu-theme-icons" aria-hidden="true">
                            <span data-theme-icon="dark" class="hidden">🌙</span>
                            <span data-theme-icon="light" class="hidden">☀️</span>
                        </span>
                        <span>Тема</span>
                    </button>
                </li>
            @endauth
        </ul>
        
        <div class="navbar-right">
            @auth
                <button
                    type="button"
                    class="navbar-toolbar-btn js-theme-toggle"
                    title="Тема"
                    aria-label="Переключить тему"
                >
                    <span data-theme-icon="dark" class="hidden" aria-hidden="true">🌙</span>
                    <span data-theme-icon="light" class="hidden" aria-hidden="true">☀️</span>
                </button>
                @include('partials.navbar-profile', ['variant' => 'desktop'])
            @else
                <a href="{{ hub_login_url() }}" class="navbar-toolbar-btn navbar-toolbar-btn--link">
                    Войти
                </a>
            @endauth
        </div>
    </nav>
    <div id="complaintToastHost" class="complaint-toast-host" aria-live="polite" aria-atomic="true"></div>
    <div class="navbar-backdrop" id="navbarBackdrop" onclick="closeMobileMenu()"></div>
    
    {{-- Основной контент --}}
    <main class="main-content bg-background text-foreground">
        {{-- Флеш-сообщения --}}
        @if (session('success'))
            <x-ui.alert type="success">
                {{ session('success') }}
                @if (session('export_download_url'))
                    <div class="mt-2">
                        <a href="{{ session('export_download_url') }}" class="underline font-medium">Открыть / скачать CSV</a>
                    </div>
                @endif
            </x-ui.alert>
        @endif
        
        @if (session('error'))
            <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
        @endif
        
        @if (session('warning'))
            <x-ui.alert type="warning">{{ session('warning') }}</x-ui.alert>
        @endif

        @hasSection('breadcrumbs')
            <div class="mb-3">
                @yield('breadcrumbs')
            </div>
        @endif
        
        @yield('content')
    </main>
    
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('navbarMenu');
            const backdrop = document.getElementById('navbarBackdrop');
            const nav = document.getElementById('mainNavbar');
            const isOpen = menu.classList.toggle('open');
            if (nav) {
                nav.classList.toggle('menu-open', isOpen);
            }
            if (!isOpen) {
                document.querySelectorAll('.navbar-item.dropdown').forEach(function (item) {
                    item.classList.remove('open');
                });
                document.querySelectorAll('[data-navbar-profile-dropdown].open').forEach(function (el) {
                    el.classList.remove('open');
                    var t = el.querySelector('[data-navbar-profile-toggle]');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            }
            if (backdrop) {
                backdrop.style.display = isOpen ? 'block' : 'none';
            }
        }
        
        function closeMobileMenu() {
            document.getElementById('navbarMenu').classList.remove('open');
            document.getElementById('mainNavbar')?.classList.remove('menu-open');
            document.querySelectorAll('.navbar-item.dropdown').forEach(function (item) {
                item.classList.remove('open');
            });
            document.querySelectorAll('[data-navbar-profile-dropdown].open').forEach(function (el) {
                el.classList.remove('open');
                var t = el.querySelector('[data-navbar-profile-toggle]');
                if (t) t.setAttribute('aria-expanded', 'false');
            });
            const backdrop = document.getElementById('navbarBackdrop');
            if (backdrop) {
                backdrop.style.display = 'none';
            }
        }
        
        // Закрывать мобильное меню при переходе по ссылке (клик по пункту меню или подпункту)
        (function() {
            var menu = document.getElementById('navbarMenu');
            if (!menu) return;
            menu.addEventListener('click', function(e) {
                var link = e.target.closest('a[href]');
                if (link && link.getAttribute('href') !== '#' && (link.classList.contains('navbar-link') || link.classList.contains('dropdown-item') || link.classList.contains('navbar-profile-panel-link'))) {
                    closeMobileMenu();
                }
            });
        })();
        
        function toggleNavbarDropdown(event, element) {
            event.preventDefault();
            event.stopPropagation();
            
            const dropdown = element.closest('.dropdown');
            if (!dropdown) return;
            
            const isOpen = dropdown.classList.contains('open');
            
            // Закрываем все другие dropdown
            document.querySelectorAll('.navbar-item.dropdown').forEach(item => {
                if (item !== dropdown) {
                    item.classList.remove('open');
                }
            });
            
            if (isOpen) {
                dropdown.classList.remove('open');
            } else {
                dropdown.classList.add('open');
                var submenu = dropdown.querySelector('.dropdown-menu');
                if (submenu) {
                    setTimeout(function() {
                        submenu.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 50);
                }
            }
        }
        
        // Закрываем dropdown при клике вне его
        (function() {
            function closeDropdownsOutside(event) {
                if (!event.target.closest('.navbar-item.dropdown')) {
                    document.querySelectorAll('.navbar-item.dropdown').forEach(item => {
                        item.classList.remove('open');
                    });
                }
            }
            
            // Добавляем обработчик после загрузки DOM
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    document.addEventListener('click', closeDropdownsOutside);
                });
            } else {
            document.addEventListener('click', closeDropdownsOutside);
        }
    })();
    </script>
    
    <script>
    // При фокусе на number input — если значение 0, выделить (чтобы ввод цифры не давал 05)
    document.addEventListener('DOMContentLoaded', function() {
        document.body.addEventListener('focus', function(e) {
            if (e.target.matches('input[type="number"]') && (e.target.value === '0' || e.target.value === '')) {
                setTimeout(function() { e.target.select(); }, 0);
            }
        }, true);
    });
    function copyTextToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise((resolve, reject) => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed'; ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) { reject(e); }
            document.body.removeChild(ta);
        });
    }
    function showCopyFeedback(success, message) {
        if (typeof Toast !== 'undefined') {
            if (success) Toast.success(message || 'Скопировано в буфер обмена');
            else Toast.error(message || 'Ошибка при копировании');
        } else if (success) {
            try { console.info(message || 'Скопировано'); } catch (e) {}
        }
    }
    </script>
    <script src="{{ asset('js/toast.js') }}?v={{ filemtime(public_path('js/toast.js')) }}"></script>
    <script src="{{ asset('js/phone-mask.js') }}?v={{ filemtime(public_path('js/phone-mask.js')) }}"></script>

    @auth
    <script>
    (function () {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) return;

        function applyToken(token) {
            if (!token) return;
            meta.setAttribute('content', token);
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
            document.querySelectorAll('input[name="_token"]').forEach(function (input) {
                input.value = token;
            });
        }

        async function refreshCsrf() {
            try {
                const response = await fetch('{{ route('csrf-token') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (response.status === 401) {
                    window.location.href = '{{ hub_login_url() }}';
                    return;
                }
                if (!response.ok) return;
                const data = await response.json();
                applyToken(data.token);
            } catch (e) {}
        }

        setInterval(refreshCsrf, 25 * 60 * 1000);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') refreshCsrf();
        });
    })();
    </script>
    @endauth

    @auth
        <script src="{{ asset('js/phone-reveal.js') }}?v={{ filemtime(public_path('js/phone-reveal.js')) }}"></script>
        @if(auth()->user()->hasAnyRole($cityNotifyRoles))
            <script src="/crm/js/lc-notify-sound.js?v={{ filemtime(public_path('js/lc-notify-sound.js')) }}"></script>
            <script src="{{ asset('js/city-order-notify.js') }}?v={{ filemtime(public_path('js/city-order-notify.js')) }}"></script>
            <script src="{{ asset('js/complaint-notify.js') }}?v={{ filemtime(public_path('js/complaint-notify.js')) }}"></script>
        @endif
        @if(auth()->user()->hasAnyRole($callbackNotifyRoles))
            <script src="/crm/js/lc-notify-sound.js?v={{ filemtime(public_path('js/lc-notify-sound.js')) }}"></script>
            <script src="{{ asset('js/callback-notify.js') }}?v={{ filemtime(public_path('js/callback-notify.js')) }}"></script>
        @endif
    @endauth

    @stack('scripts')
</body>
</html>
