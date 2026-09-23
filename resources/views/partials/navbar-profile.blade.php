@php
    $navbarProfileVariant = $variant ?? 'desktop';
    $isMobileProfile = $navbarProfileVariant === 'mobile';
@endphp

<div
    class="navbar-profile-dropdown {{ $isMobileProfile ? 'navbar-profile-dropdown--mobile' : '' }}"
    data-navbar-profile-dropdown
>
    <button
        type="button"
        class="navbar-toolbar-btn navbar-profile-toggle {{ $isMobileProfile ? 'navbar-profile-toggle--mobile' : '' }}"
        data-navbar-profile-toggle
        aria-expanded="false"
        aria-haspopup="true"
    >
        <span class="navbar-profile-name">
            {{ auth()->user()->user_name }}
            <span class="navbar-profile-id" title="ID пользователя">· ID {{ auth()->user()->user_id }}</span>
        </span>
        <svg
            class="navbar-profile-arrow"
            data-navbar-profile-arrow
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div class="navbar-profile-panel" role="menu">
        <a href="{{ route('settings') }}" class="navbar-profile-panel-link" role="menuitem">Настройки</a>
        <form action="{{ route('logout') }}" method="POST" role="none">
            @csrf
            <button type="submit" class="navbar-profile-panel-link navbar-profile-panel-logout" role="menuitem">
                Выход
            </button>
        </form>
    </div>
</div>
