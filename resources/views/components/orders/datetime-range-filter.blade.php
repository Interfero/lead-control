@props([
    'dateFrom' => null,
    'dateTo' => null,
])

<div
    id="ordersDatetimeFilterPopup"
    class="orders-dt-filter-popup hidden"
    role="dialog"
    aria-modal="true"
    aria-label="Фильтр по времени заявки"
>
    <div class="orders-dt-filter-popup__calendars">
        <div class="orders-dt-cal" data-dt-calendar="left">
            <div class="orders-dt-cal__header">
                <button type="button" class="orders-dt-cal__nav" data-dt-nav="prev" aria-label="Предыдущий месяц">‹</button>
                <span class="orders-dt-cal__title" data-dt-month-label="left"></span>
            </div>
            <div class="orders-dt-cal__weekdays" aria-hidden="true">
                <span>пн</span><span>вт</span><span>ср</span><span>чт</span><span>пт</span><span>сб</span><span>вс</span>
            </div>
            <div class="orders-dt-cal__grid" data-dt-grid="left"></div>
        </div>
        <div class="orders-dt-cal" data-dt-calendar="right">
            <div class="orders-dt-cal__header">
                <span class="orders-dt-cal__title" data-dt-month-label="right"></span>
                <button type="button" class="orders-dt-cal__nav" data-dt-nav="next" aria-label="Следующий месяц">›</button>
            </div>
            <div class="orders-dt-cal__weekdays" aria-hidden="true">
                <span>пн</span><span>вт</span><span>ср</span><span>чт</span><span>пт</span><span>сб</span><span>вс</span>
            </div>
            <div class="orders-dt-cal__grid" data-dt-grid="right"></div>
        </div>
    </div>

    <input type="hidden" data-dt-part="from" value="{{ $dateFrom }}">
    <input type="hidden" data-dt-part="to" value="{{ $dateTo }}">

    <div class="orders-dt-filter-popup__times">
        <div class="orders-dt-filter-popup__time-group">
            <select class="form-input orders-dt-filter-popup__time" data-dt-part="from-hour" aria-label="Час начала">
                @for ($h = 0; $h < 24; $h++)
                    <option value="{{ $h }}" @selected($h === 0)>{{ $h }}</option>
                @endfor
            </select>
            <span>:</span>
            <select class="form-input orders-dt-filter-popup__time" data-dt-part="from-minute" aria-label="Минуты начала">
                @foreach ([0, 15, 30, 45] as $m)
                    <option value="{{ $m }}" @selected($m === 0)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </select>
        </div>
        <span class="orders-dt-filter-popup__time-sep">—</span>
        <div class="orders-dt-filter-popup__time-group">
            <select class="form-input orders-dt-filter-popup__time" data-dt-part="to-hour" aria-label="Час окончания">
                @for ($h = 0; $h < 24; $h++)
                    <option value="{{ $h }}" @selected($h === 23)>{{ $h }}</option>
                @endfor
            </select>
            <span>:</span>
            <select class="form-input orders-dt-filter-popup__time" data-dt-part="to-minute" aria-label="Минуты окончания">
                @foreach ([0, 15, 30, 45] as $m)
                    <option value="{{ $m }}" @selected($m === 0)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="orders-dt-filter-popup__footer">
        <p class="orders-dt-filter-popup__summary" data-dt-summary></p>
        <div class="orders-dt-filter-popup__actions">
            <button type="button" class="btn btn-secondary btn-sm" data-dt-cancel>Отменить</button>
            <button type="button" class="btn btn-primary btn-sm" data-dt-apply>Выбрать</button>
        </div>
    </div>
</div>
