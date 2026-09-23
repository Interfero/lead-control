(function () {
    const MONTHS_RU = [
        'янв.', 'февр.', 'март', 'апр.', 'май', 'июнь',
        'июль', 'авг.', 'сент.', 'окт.', 'нояб.', 'дек.',
    ];

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    function formatRuDate(iso) {
        if (!iso) return '';
        const p = iso.split('-');
        if (p.length !== 3) return iso;
        return `${p[2]}.${p[1]}.${p[0]}`;
    }

    function ordersDatetimeSummary(from, to, fh, fm, th, tm) {
        if (!from || !to) return '';
        return `${formatRuDate(from)} ${pad2(fh)}:${pad2(fm)} - ${formatRuDate(to)} ${pad2(th)}:${pad2(tm)}`;
    }

    function parseIso(iso) {
        if (!iso) return null;
        const p = iso.split('-').map(Number);
        if (p.length !== 3 || p.some(Number.isNaN)) return null;
        return new Date(p[0], p[1] - 1, p[2]);
    }

    function toIso(date) {
        return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
    }

    function compareIso(a, b) {
        if (a === b) return 0;
        return a < b ? -1 : 1;
    }

    window.initOrdersDatetimeFilter = function initOrdersDatetimeFilter() {
        const form = document.getElementById('filtersForm');
        const popup = document.getElementById('ordersDatetimeFilterPopup');
        const intervalBar = document.getElementById('ordersDatetimeIntervalBar');
        const hiddenFrom = document.getElementById('ordersDateFrom');
        const hiddenTo = document.getElementById('ordersDateTo');
        const display = document.getElementById('ordersDatetimeIntervalDisplay');
        const todayBtn = document.getElementById('ordersDatetimeToday');
        if (!form || !popup || !hiddenFrom || !hiddenTo || !display) return;

        const part = (name) => popup.querySelector(`[data-dt-part="${name}"]`);
        const summaryEl = popup.querySelector('[data-dt-summary]');
        const gridLeft = popup.querySelector('[data-dt-grid="left"]');
        const gridRight = popup.querySelector('[data-dt-grid="right"]');
        const labelLeft = popup.querySelector('[data-dt-month-label="left"]');
        const labelRight = popup.querySelector('[data-dt-month-label="right"]');

        let anchorEl = null;
        let viewYear = new Date().getFullYear();
        let viewMonth = new Date().getMonth();
        let draftFrom = hiddenFrom.value || '';
        let draftTo = hiddenTo.value || '';
        let pickingEnd = false;

        function syncDateFieldsForSubmit() {
            const intervalActive = intervalBar && !intervalBar.classList.contains('hidden');
            const visibleFrom = form.querySelector('.orders-date-filters input[name="date_from"]');
            const visibleTo = form.querySelector('.orders-date-filters input[name="date_to"]');
            const idVal = form.querySelector('[name="search_id"]')?.value?.trim();
            const addrVal = form.querySelector('[name="search_address"]')?.value?.trim();
            const nameVal = form.querySelector('[name="search_name"]')?.value?.trim();
            const textLookup = Boolean(idVal || addrVal || nameVal);

            if (textLookup) {
                if (hiddenFrom) {
                    hiddenFrom.value = '';
                    hiddenFrom.setAttribute('disabled', 'disabled');
                }
                if (hiddenTo) {
                    hiddenTo.value = '';
                    hiddenTo.setAttribute('disabled', 'disabled');
                }
                visibleFrom?.setAttribute('disabled', 'disabled');
                visibleTo?.setAttribute('disabled', 'disabled');
                return;
            }

            if (intervalActive) {
                visibleFrom?.setAttribute('disabled', 'disabled');
                visibleTo?.setAttribute('disabled', 'disabled');
                hiddenFrom?.removeAttribute('disabled');
                hiddenTo?.removeAttribute('disabled');
            } else {
                hiddenFrom?.setAttribute('disabled', 'disabled');
                hiddenTo?.setAttribute('disabled', 'disabled');
                visibleFrom?.removeAttribute('disabled');
                visibleTo?.removeAttribute('disabled');
                if (visibleFrom?.value) {
                    hiddenFrom.value = visibleFrom.value;
                }
                if (visibleTo?.value) {
                    hiddenTo.value = visibleTo.value;
                }
            }
        }

        form.addEventListener('submit', syncDateFieldsForSubmit);
        syncDateFieldsForSubmit();

        function showIntervalBar() {
            if (!intervalBar) return;
            intervalBar.classList.remove('hidden');
            syncDateFieldsForSubmit();
            window.dispatchEvent(new Event('resize'));
        }

        function hideIntervalBar() {
            if (!intervalBar) return;
            intervalBar.classList.add('hidden');
            syncDateFieldsForSubmit();
            window.dispatchEvent(new Event('resize'));
        }

        function readTimes() {
            return {
                fh: parseInt(part('from-hour')?.value || '0', 10),
                fm: parseInt(part('from-minute')?.value || '0', 10),
                th: parseInt(part('to-hour')?.value || '23', 10),
                tm: parseInt(part('to-minute')?.value || '0', 10),
            };
        }

        function syncDraftToParts() {
            if (part('from')) part('from').value = draftFrom || '';
            if (part('to')) part('to').value = draftTo || '';
        }

        function refreshSummary() {
            syncDraftToParts();
            const t = readTimes();
            const text = ordersDatetimeSummary(draftFrom, draftTo, t.fh, t.fm, t.th, t.tm);
            if (summaryEl) summaryEl.textContent = text;
        }

        function refreshDisplay() {
            const t = readTimes();
            display.value = ordersDatetimeSummary(hiddenFrom.value, hiddenTo.value, t.fh, t.fm, t.th, t.tm);
        }

        function syncPopupFromHidden() {
            draftFrom = hiddenFrom.value || '';
            draftTo = hiddenTo.value || '';
            pickingEnd = Boolean(draftFrom && !draftTo);

            const base = parseIso(draftFrom) || parseIso(draftTo) || new Date();
            viewYear = base.getFullYear();
            viewMonth = base.getMonth();

            syncDraftToParts();
            renderCalendars();
            refreshSummary();
        }

        function closePopup() {
            popup.classList.add('hidden');
            anchorEl = null;
        }

        function positionPopup(el) {
            const r = el.getBoundingClientRect();
            popup.classList.remove('hidden');
            const pw = popup.offsetWidth;
            const ph = popup.offsetHeight;
            let left = r.left;
            let top = r.bottom + 8;
            if (left + pw > window.innerWidth - 8) {
                left = Math.max(8, window.innerWidth - pw - 8);
            }
            if (top + ph > window.innerHeight - 8) {
                top = Math.max(8, r.top - ph - 8);
            }
            popup.style.left = left + 'px';
            popup.style.top = top + 'px';

            const arrowLeft = Math.min(Math.max(r.left + r.width / 2 - left - 6, 12), pw - 24);
            popup.style.setProperty('--popup-arrow-left', `${arrowLeft}px`);
        }

        function openPopup(el) {
            anchorEl = el;
            syncPopupFromHidden();
            positionPopup(el);
        }

        function submitForm() {
            const btn = document.getElementById('filtersFormSubmit');
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(btn || undefined);
            } else {
                form.submit();
            }
        }

        function monthLabel(year, month) {
            return `${MONTHS_RU[month]} ${year}`;
        }

        function renderMonthGrid(year, month, container) {
            if (!container) return;

            const firstDay = new Date(year, month, 1);
            const startDow = (firstDay.getDay() + 6) % 7;
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrev = new Date(year, month, 0).getDate();

            container.innerHTML = '';

            for (let i = 0; i < 42; i++) {
                const cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'orders-dt-cal__day';

                let dayNum;
                let cellYear = year;
                let cellMonth = month;
                let muted = false;

                if (i < startDow) {
                    dayNum = daysInPrev - startDow + i + 1;
                    cellMonth = month - 1;
                    if (cellMonth < 0) {
                        cellMonth = 11;
                        cellYear -= 1;
                    }
                    muted = true;
                } else if (i >= startDow + daysInMonth) {
                    dayNum = i - startDow - daysInMonth + 1;
                    cellMonth = month + 1;
                    if (cellMonth > 11) {
                        cellMonth = 0;
                        cellYear += 1;
                    }
                    muted = true;
                } else {
                    dayNum = i - startDow + 1;
                }

                const iso = toIso(new Date(cellYear, cellMonth, dayNum));
                cell.textContent = String(dayNum);
                cell.dataset.iso = iso;

                if (muted) cell.classList.add('is-muted');

                if (draftFrom && iso === draftFrom) cell.classList.add('is-start');
                if (draftTo && iso === draftTo) cell.classList.add('is-end');
                if (draftFrom && draftTo) {
                    const cmpFrom = compareIso(iso, draftFrom);
                    const cmpTo = compareIso(iso, draftTo);
                    if (cmpFrom > 0 && cmpTo < 0) cell.classList.add('is-in-range');
                }

                cell.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onDayClick(iso);
                });

                container.appendChild(cell);
            }
        }

        function onDayClick(iso) {
            if (!pickingEnd || !draftFrom || (draftFrom && draftTo)) {
                draftFrom = iso;
                draftTo = '';
                pickingEnd = true;
            } else {
                draftTo = iso;
                if (compareIso(draftTo, draftFrom) < 0) {
                    const tmp = draftFrom;
                    draftFrom = draftTo;
                    draftTo = tmp;
                }
                pickingEnd = false;
            }
            syncDraftToParts();
            renderCalendars();
            refreshSummary();
        }

        function renderCalendars() {
            const rightMonth = viewMonth + 1;
            const rightYear = rightMonth > 11 ? viewYear + 1 : viewYear;
            const rightMonthNorm = rightMonth % 12;

            if (labelLeft) labelLeft.textContent = monthLabel(viewYear, viewMonth);
            if (labelRight) labelRight.textContent = monthLabel(rightYear, rightMonthNorm);

            renderMonthGrid(viewYear, viewMonth, gridLeft);
            renderMonthGrid(rightYear, rightMonthNorm, gridRight);
        }

        popup.querySelector('[data-dt-nav="prev"]')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            viewMonth -= 1;
            if (viewMonth < 0) {
                viewMonth = 11;
                viewYear -= 1;
            }
            renderCalendars();
        });

        popup.querySelector('[data-dt-nav="next"]')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            viewMonth += 1;
            if (viewMonth > 11) {
                viewMonth = 0;
                viewYear += 1;
            }
            renderCalendars();
        });

        popup.querySelector('[data-dt-apply]')?.addEventListener('click', () => {
            if (!draftFrom || !draftTo) return;
            hiddenFrom.value = draftFrom;
            hiddenTo.value = draftTo;
            refreshDisplay();
            showIntervalBar();
            closePopup();
            submitForm();
        });

        popup.querySelector('[data-dt-cancel]')?.addEventListener('click', closePopup);

        todayBtn?.addEventListener('click', () => {
            const now = new Date();
            const iso = toIso(now);
            hiddenFrom.value = iso;
            hiddenTo.value = iso;
            draftFrom = iso;
            draftTo = iso;
            refreshDisplay();
            showIntervalBar();
            submitForm();
        });

        document.querySelectorAll('[data-orders-dt-open]').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (el.classList.contains('orders-dt-filter-btn')) {
                    if (!intervalBar.classList.contains('hidden')) {
                        hideIntervalBar();
                        closePopup();
                        return;
                    }
                    showIntervalBar();
                    return;
                }
                if (intervalBar?.classList.contains('hidden')) {
                    showIntervalBar();
                }
                openPopup(el);
            });
        });

        popup.querySelectorAll('[data-dt-part="from-hour"], [data-dt-part="from-minute"], [data-dt-part="to-hour"], [data-dt-part="to-minute"]').forEach((input) => {
            input.addEventListener('change', refreshSummary);
        });

        document.addEventListener('click', (e) => {
            if (popup.classList.contains('hidden')) return;
            if (popup.contains(e.target)) return;
            if (e.target.closest('[data-orders-dt-open]')) return;
            closePopup();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closePopup();
        });

        refreshDisplay();

        if (hiddenFrom.value || hiddenTo.value) {
            showIntervalBar();
        }
    };
})();
