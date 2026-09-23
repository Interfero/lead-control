<div class="tabs-header">
    <ul class="nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('prom.journal.meetings') ? 'active' : '' }}" 
               href="{{ route('prom.journal.meetings') }}">
                {!! icon('users') !!} Встречи
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('prom.journal.appointments') ? 'active' : '' }}" 
               href="{{ route('prom.journal.appointments') }}">
                {!! icon('calendar') !!} Записи
            </a>
        </li>
    </ul>
    <div class="tabs-actions">
        @if(request()->routeIs('prom.journal.meetings'))
            <a href="{{ route('prom.meetings.create') }}" class="btn btn-primary">
                {!! icon('add') !!} Добавить встречу
            </a>
        @else
            <a href="{{ route('prom.appointments.create') }}" class="btn btn-primary">
                {!! icon('add') !!} Добавить запись
            </a>
        @endif
    </div>
</div>
