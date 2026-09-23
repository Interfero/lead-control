@extends('layouts.app')

@section('title', 'Клиенты КЦ')

@section('content')
<div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
    <a href="{{ route('persons.create') }}" class="btn btn-primary">
        {!! icon('add') !!} Создать запись
    </a>
</div>

{{-- Форма поиска --}}
<div class="card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 150px;">
            <label class="form-label">ID заказа</label>
            <input type="text" id="searchOrderId" class="form-input" placeholder="Введите ID заказа" inputmode="numeric" data-digits-only autocomplete="off">
        </div>
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label">Адрес</label>
            <input type="text" id="searchAddress" class="form-input" placeholder="Улица или дом" data-address-safe>
        </div>
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label">Телефон</label>
            <div class="phone-input-ru">
                <span class="phone-input-ru-prefix">+7</span>
                <input type="text" id="searchPhone" class="form-input phone-input-ru-field" placeholder="9001234567" maxlength="10" inputmode="numeric" data-phone-mask="split">
            </div>
        </div>
        <div style="display: flex; align-items: flex-end;">
            <button type="button" class="btn btn-primary" id="searchPersonsBtn">
                {!! icon('search') !!} Найти
            </button>
        </div>
    </div>
</div>

{{-- Результаты поиска --}}
<div class="card persons-index-table-wrap">
    <table class="table" id="resultsTable">
        <thead>
            <tr>
                <th>ID заказа</th>
                <th>Клиент</th>
                <th>Имя</th>
                <th>Город</th>
                <th>Адрес</th>
                <th>Комментарий</th>
            </tr>
        </thead>
        <tbody id="resultsBody">
            <tr>
                <td colspan="6" style="text-align: center; color: #6b7280; padding: 2rem;">
                    Введите ID заказа, телефон или адрес для поиска
                </td>
            </tr>
        </tbody>
    </table>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const SEARCH_URL = @json(route('persons.search'));
    const ORDER_SHOW_TMPL = @json(route('orders.show', ['order_id' => 999999999]));
    const PERSON_SHOW_TMPL = @json(route('persons.show', ['person_id' => 999999999]));

    function orderUrl(id) {
        return ORDER_SHOW_TMPL.replace('999999999', String(id));
    }
    function personUrl(id) {
        return PERSON_SHOW_TMPL.replace('999999999', String(id));
    }

    function toastWarn(msg) {
        if (typeof Toast !== 'undefined') Toast.warning(msg);
        else alert(msg);
    }
    function toastError(msg) {
        if (typeof Toast !== 'undefined') Toast.error(msg);
        else alert(msg);
    }

    const orderInput = document.getElementById('searchOrderId');
    const phoneInput = document.getElementById('searchPhone');
    const addressInput = document.getElementById('searchAddress');
    const searchBtn = document.getElementById('searchPersonsBtn');
    const resultsBody = document.getElementById('resultsBody');

    orderInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
    });

    function sanitizeAddress(value) {
        return value.replace(/[^a-zA-Z\u0400-\u04FF0-9\s,.\-]/g, '');
    }
    addressInput.addEventListener('input', function () {
        this.value = sanitizeAddress(this.value);
    });
    document.querySelectorAll('[data-address-safe]').forEach(function (el) {
        el.addEventListener('input', function () {
            this.value = sanitizeAddress(this.value);
        });
    });

    function openSearchResult(r) {
        // Поиск по заказу / есть order_id → карточка заказа; иначе клиент
        if (r && r.order_id) {
            window.location.href = orderUrl(r.order_id);
            return;
        }
        if (r && r.person_id) {
            window.location.href = personUrl(r.person_id);
        }
    }

    function renderResults(results) {
        if (!Array.isArray(results)) {
            toastError('Некорректный ответ сервера');
            return;
        }

        if (results.length === 0) {
            resultsBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; color: #6b7280; padding: 2rem;">
                        Ничего не найдено
                    </td>
                </tr>
            `;
            return;
        }

        resultsBody.innerHTML = results.map(function (r, idx) {
            const name = (r.person_name || '') + (r.person_age ? ', ' + r.person_age : '');
            return `
                <tr class="clickable" data-result-idx="${idx}" style="cursor:pointer" title="Открыть">
                    <td>${r.order_id || '—'}</td>
                    <td>${r.person_id || ''}</td>
                    <td>${name}</td>
                    <td>${r.city_name || ''}</td>
                    <td>${r.address || ''}</td>
                    <td>${r.address_adds || '-'}</td>
                </tr>
            `;
        }).join('');

        resultsBody.querySelectorAll('tr[data-result-idx]').forEach(function (tr) {
            tr.addEventListener('click', function () {
                const i = Number(tr.getAttribute('data-result-idx'));
                openSearchResult(results[i]);
            });
        });
    }

    async function searchPersons() {
        const orderId = (orderInput.value || '').trim();
        const phone = (phoneInput.value || '').replace(/\D/g, '').slice(0, 10);
        const address = (addressInput.value || '').trim();

        if (!orderId && !phone && !address) {
            toastWarn('Введите ID заказа, телефон или адрес для поиска');
            return;
        }

        resultsBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; color: #6b7280; padding: 2rem;">
                    Поиск…
                </td>
            </tr>
        `;

        try {
            const params = new URLSearchParams();
            if (orderId) params.append('order_id', orderId);
            if (phone) params.append('phone', phone);
            if (address) params.append('address', address);

            const response = await fetch(SEARCH_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(function () { return {}; });
            if (!response.ok) {
                toastError(payload.message || ('Ошибка ' + response.status));
                resultsBody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; color: #6b7280; padding: 2rem;">
                            Ошибка поиска
                        </td>
                    </tr>
                `;
                return;
            }
            renderResults(payload);
        } catch (error) {
            console.error('Ошибка поиска:', error);
            toastError('Ошибка при поиске. Проверьте подключение к серверу.');
            resultsBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; color: #6b7280; padding: 2rem;">
                        Ошибка поиска
                    </td>
                </tr>
            `;
        }
    }

    searchBtn.addEventListener('click', searchPersons);
    [orderInput, phoneInput, addressInput].forEach(function (el) {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchPersons();
            }
        });
    });
})();
</script>
@endpush
