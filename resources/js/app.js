import { applyCsrfToken } from './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initProfileDropdowns();
    initDateFilterClears();
    initOrdersStickyTable();
    // Уведомления филиала — public/js/city-order-notify.js (без двойного poll)
    initCsrfKeepAlive();
});

const CSRF_REFRESH_MS = 25 * 60 * 1000;

function initCsrfKeepAlive() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta || !window.axios) {
        return;
    }

    async function refreshCsrfToken() {
        try {
            const { data } = await window.axios.get('/csrf-token');
            if (data?.token) {
                applyCsrfToken(data.token);
            }
        } catch {
            /* сеть или сессия — следующий запрос покажет 419 */
        }
    }

    setInterval(refreshCsrfToken, CSRF_REFRESH_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshCsrfToken();
        }
    });
}

const CITY_UNSEEN_POLL_MS = 3000;
const STORAGE_UNSEEN_IDS = 'lc_unseen_order_ids_snapshot';
const STORAGE_NOTIFIED_IDS = 'lc_notified_order_ids';

function initCityUnseenPoll() {
    const body = document.body;
    if (!body?.dataset?.cityUnseenPoll) {
        return;
    }

    const url = body.dataset.cityUnseenCountUrl;
    const ordersBase = (body.dataset.cityOrdersBaseUrl || '').replace(/\/$/, '');
    const notifyIcon = (body.dataset.cityNotifyIcon || '').trim();
    if (!url || !window.axios) {
        return;
    }

    const badge = document.querySelector('[data-city-unseen-badge]');
    const notifyBar = document.getElementById('cityUnseenNotifyBar');
    const notifyBtn = document.getElementById('cityUnseenNotifyEnableBtn');

    function orderShowUrl(orderId) {
        return `${ordersBase}/${orderId}`;
    }

    function readIdList(key, storage) {
        try {
            const raw = storage.getItem(key);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed.map((x) => Number(x)).filter((n) => n > 0) : [];
        } catch {
            return [];
        }
    }

    function writeIdList(key, storage, ids) {
        try {
            const unique = [...new Set(ids.map((x) => Number(x)).filter((n) => n > 0))];
            // Храним ограниченный хвост, чтобы не раздувать localStorage
            storage.setItem(key, JSON.stringify(unique.slice(-2000)));
        } catch {
            /* ignore */
        }
    }

    function syncNotifyBanner() {
        if (!notifyBar || !('Notification' in window)) {
            return;
        }
        if (Notification.permission === 'default') {
            notifyBar.classList.remove('hidden');
        } else {
            notifyBar.classList.add('hidden');
        }
    }

    if (!('Notification' in window)) {
        if (notifyBar) {
            notifyBar.classList.add('hidden');
        }
    } else {
        if (notifyBtn) {
            notifyBtn.addEventListener('click', () => {
                Notification.requestPermission().then(syncNotifyBanner);
            });
        }
        syncNotifyBanner();
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(syncNotifyBanner);
        }
    }

    let initialized = false;

    function updateBadge(count) {
        if (!badge) {
            return;
        }
        const n = typeof count === 'number' && count >= 0 ? count : 0;
        badge.textContent = n > 99 ? '99+' : String(n);
        if (n === 0) {
            badge.setAttribute('hidden', 'hidden');
            badge.setAttribute('aria-hidden', 'true');
        } else {
            badge.removeAttribute('hidden');
            badge.setAttribute('aria-hidden', 'false');
        }
    }

    function showSystemNotificationsForNewOrders(newIds) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }
        if (!newIds.length) {
            return;
        }
        const icon = notifyIcon || undefined;
        const sorted = [...newIds].sort((a, b) => b - a);
        sorted.forEach((id) => {
            const n = new Notification('Lead Control', {
                body: `Новый заказ ID ${id}`,
                icon,
                tag: `lc-new-order-${id}`,
                renotify: false,
                silent: false,
            });
            n.onclick = () => {
                window.focus();
                n.close();
                window.location.href = orderShowUrl(id);
            };
        });

        if (typeof navigator !== 'undefined' && navigator.vibrate) {
            navigator.vibrate(180);
        }
    }

    async function poll() {
        try {
            const { data } = await window.axios.get(url);
            const count = typeof data.count === 'number' ? data.count : 0;
            const unseenOrderIds = Array.isArray(data.unseen_order_ids)
                ? data.unseen_order_ids.map((x) => Number(x)).filter((n) => n > 0)
                : [];

            if (!initialized) {
                writeIdList(STORAGE_UNSEEN_IDS, sessionStorage, unseenOrderIds);
                initialized = true;
                updateBadge(count);
                return;
            }

            const prevIds = readIdList(STORAGE_UNSEEN_IDS, sessionStorage);
            const prevSet = new Set(prevIds);
            const alreadyNotified = new Set(readIdList(STORAGE_NOTIFIED_IDS, localStorage));

            // Только реально новые ID, по которым ещё не уведомляли (переживает refresh)
            const brandNewIds = unseenOrderIds.filter(
                (id) => !prevSet.has(id) && !alreadyNotified.has(id)
            );

            if (brandNewIds.length > 0) {
                showSystemNotificationsForNewOrders(brandNewIds);
                writeIdList(
                    STORAGE_NOTIFIED_IDS,
                    localStorage,
                    [...alreadyNotified, ...brandNewIds]
                );
            }

            writeIdList(STORAGE_UNSEEN_IDS, sessionStorage, unseenOrderIds);
            updateBadge(count);
        } catch {
            /* ignore */
        }
    }

    poll();
    setInterval(poll, CITY_UNSEEN_POLL_MS);
}

function defaultDateAfterClear(name) {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const mm = String(m + 1).padStart(2, '0');
    if (name === 'date_from') {
        return `${y}-${mm}-01`;
    }
    if (name === 'date_to') {
        const last = new Date(y, m + 1, 0);
        const dd = String(last.getDate()).padStart(2, '0');
        return `${y}-${mm}-${dd}`;
    }
    if (name === 'closed_from' || name === 'closed_to') {
        return '';
    }
    return `${y}-${mm}-01`;
}

function initDateFilterClears() {
    document.querySelectorAll('.date-clear-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetName = btn.dataset.target;
            const form = btn.closest('form');
            const input = form?.querySelector(`[name="${targetName}"]`);
            if (!input || !form) {
                return;
            }
            input.value = defaultDateAfterClear(targetName);
            const searchBtn = form.querySelector('#filtersFormSubmit');
            if (typeof form.requestSubmit === 'function') {
                if (searchBtn) {
                    form.requestSubmit(searchBtn);
                } else {
                    form.requestSubmit();
                }
            } else if (searchBtn) {
                searchBtn.click();
            } else {
                form.submit();
            }
        });
    });
}

function initProfileDropdowns() {
    function closeAllProfileDropdowns(except = null) {
        document.querySelectorAll('[data-navbar-profile-dropdown].open').forEach((dropdown) => {
            if (dropdown === except) {
                return;
            }
            dropdown.classList.remove('open');
            const t = dropdown.querySelector('[data-navbar-profile-toggle]');
            if (t) {
                t.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('[data-navbar-profile-dropdown]').forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-navbar-profile-toggle]');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const willOpen = !dropdown.classList.contains('open');
            closeAllProfileDropdowns(willOpen ? dropdown : null);
            dropdown.classList.toggle('open', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-navbar-profile-dropdown]')) {
            return;
        }
        closeAllProfileDropdowns();
    });
}

function initTheme() {
    const root = document.documentElement;
    const toggles = document.querySelectorAll('.js-theme-toggle');

    async function persistTheme(mode) {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!token || !window.axios) {
            return;
        }

        try {
            await window.axios.patch(
                '/settings/theme',
                { theme: mode },
                {
                    headers: {
                        'X-CSRF-TOKEN': token,
                    },
                }
            );
        } catch (_) {
            // Локальная тема работает даже без ответа сервера
        }
    }

    function applyTheme(mode) {
        const isDark = mode === 'dark';
        root.classList.toggle('dark', isDark);
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        } catch (_) {}

        toggles.forEach((toggle) => {
            const dark = toggle.querySelector('[data-theme-icon="dark"]');
            const light = toggle.querySelector('[data-theme-icon="light"]');
            if (dark && light) {
                dark.classList.toggle('hidden', !isDark);
                light.classList.toggle('hidden', isDark);
            }
        });
    }

    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') {
        applyTheme(saved);
    } else {
        applyTheme(root.classList.contains('dark') ? 'dark' : 'light');
    }

    window.leadControlSetTheme = async (nextTheme) => {
        if (!['dark', 'light'].includes(nextTheme)) {
            return;
        }
        applyTheme(nextTheme);
        await persistTheme(nextTheme);
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', async () => {
            const isDark = root.classList.contains('dark');
            const nextTheme = isDark ? 'light' : 'dark';
            await window.leadControlSetTheme(nextTheme);
        });
    });

    window.addEventListener('leadcontrol:set-theme', async (event) => {
        const nextTheme = event.detail?.theme;
        await window.leadControlSetTheme(nextTheme);
    });
}

/**
 * Список заказов: как в superpart.ru — одна таблица w-full в overflow-x-auto,
 * липкая шапка = клон thead в fixed-блоке на body, синхрон горизонтального скролла marginLeft.
 */
function syncOrdersFiltersBarHeightCss() {
    const el = document.querySelector('.orders-filters-sticky');
    if (!el) {
        return;
    }
    document.documentElement.style.setProperty(
        '--orders-sticky-filters-height',
        `${Math.ceil(el.getBoundingClientRect().height)}px`
    );
    document.documentElement.style.setProperty('--orders-clone-top', `${getOrdersTableStickyTopPx()}px`);
}

function getOrdersTableStickyTopPx() {
    const st = getComputedStyle(document.documentElement);
    const nav = parseFloat(st.getPropertyValue('--navbar-height'));
    const fh = parseFloat(st.getPropertyValue('--orders-sticky-filters-height'));
    return (Number.isFinite(nav) ? nav : 60) + (Number.isFinite(fh) ? fh : 76);
}

function ordersStickyCloneEnabled() {
    return window.matchMedia('(min-width: 1024px)').matches;
}

function initOrdersStickyTable() {
    document.querySelectorAll('table.orders-sticky-table').forEach((table) => {
        const scrollParent = document.createElement('div');
        scrollParent.className = 'orders-table-scroll overflow-x-auto min-w-0 max-w-full';
        table.parentNode.insertBefore(scrollParent, table);
        scrollParent.appendChild(table);

        const thead = table.querySelector('thead');
        if (!thead) {
            return;
        }

        let clone = null;
        let visible = false;

        const filterBar = document.querySelector('.orders-filters-sticky');
        if (filterBar && typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => {
                syncOrdersFiltersBarHeightCss();
                if (visible) {
                    buildClone();
                }
            }).observe(filterBar);
        }

        function syncMultiselectLabel(origMs, cloneMs) {
            const ot = origMs.querySelector('.multiselect-text');
            const ct = cloneMs.querySelector('.multiselect-text');
            if (!ot || !ct) {
                return;
            }
            ct.textContent = ot.textContent;
            ct.classList.toggle('text-muted-foreground', ot.classList.contains('text-muted-foreground'));
        }

        function bindCloneMultiselects(origThead, clonedThead) {
            const origBoxes = origThead.querySelectorAll('.multiselect');
            const cloneBoxes = clonedThead.querySelectorAll('.multiselect');
            origBoxes.forEach((oms, mi) => {
                const cms = cloneBoxes[mi];
                if (!cms) {
                    return;
                }
                syncMultiselectLabel(oms, cms);
                const oChecks = oms.querySelectorAll('input[type="checkbox"]');
                const cChecks = cms.querySelectorAll('input[type="checkbox"]');
                cChecks.forEach((ccb, ci) => {
                    const ocb = oChecks[ci];
                    if (!ocb) {
                        return;
                    }
                    ccb.removeAttribute('name');
                    ccb.checked = ocb.checked;
                    ccb.addEventListener('change', () => {
                        ocb.checked = ccb.checked;
                        ocb.dispatchEvent(new Event('change', { bubbles: true }));
                        if (typeof window.updateMultiselect === 'function') {
                            window.updateMultiselect(ocb);
                        }
                        syncMultiselectLabel(oms, cms);
                    });
                });
                const oBtns = oms.querySelectorAll('.multiselect-actions button');
                const cBtns = cms.querySelectorAll('.multiselect-actions button');
                cBtns.forEach((cb, bi) => {
                    cb.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const ob = oBtns[bi];
                        if (ob) {
                            ob.click();
                        }
                        requestAnimationFrame(() => syncMultiselectLabel(oms, cms));
                    });
                });
            });
        }

        function bindCloneTextFilters(origThead, clonedThead) {
            const cloneInputs = clonedThead.querySelectorAll('input.table-filter');
            cloneInputs.forEach((cinp) => {
                const name = cinp.getAttribute('name');
                if (!name) {
                    return;
                }
                const orig = origThead.querySelector(`input.table-filter[name="${CSS.escape(name)}"]`);
                if (!orig) {
                    return;
                }
                cinp.removeAttribute('name');
                cinp.value = orig.value;
                cinp.addEventListener('input', () => {
                    orig.value = cinp.value;
                    orig.dispatchEvent(new Event('input', { bubbles: true }));
                });
                cinp.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        orig.value = cinp.value;
                        orig.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                    }
                });
            });
        }

        function bindCloneSortLinks(origThead, clonedThead) {
            const oLinks = origThead.querySelectorAll('tr:first-child th a');
            const cLinks = clonedThead.querySelectorAll('tr:first-child th a');
            cLinks.forEach((cl, i) => {
                cl.addEventListener('click', (e) => {
                    e.preventDefault();
                    const ol = oLinks[i];
                    if (ol) {
                        ol.click();
                    }
                });
            });
        }

        function bindCloneFilterSearchButtons(origThead, clonedThead, form) {
            if (!form) {
                return;
            }
            clonedThead.querySelectorAll('.filter-search-btn').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const wrap = btn.closest('[data-filter-input]');
                    const cinp =
                        wrap?.querySelector('input.table-filter') ?? wrap?.querySelector('input[type="text"],input[type="number"]');
                    const name = cinp?.dataset?.syncName;
                    let orig = null;
                    if (name) {
                        orig = origThead.querySelector(`input[name="${CSS.escape(name)}"]`);
                    }
                    if (!orig && cinp?.id) {
                        orig = origThead.querySelector(`#${CSS.escape(cinp.id)}`);
                    }
                    if (orig && cinp) {
                        orig.value = cinp.value;
                    }
                    const submitBtn = form.querySelector('#filtersFormSubmit');
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitBtn ?? undefined);
                    } else {
                        form.submit();
                    }
                });
            });
        }

        function markCloneInputsForSync(clonedThead) {
            clonedThead.querySelectorAll('input.table-filter').forEach((inp) => {
                const n = inp.getAttribute('name');
                if (n) {
                    inp.dataset.syncName = n;
                }
            });
        }

        function buildClone() {
            if (clone) {
                clone.remove();
            }

            clone = document.createElement('div');
            clone.className = 'table-sticky-clone';

            const inner = document.createElement('table');
            inner.className = table.className.replace(/\btable-sticky\b/g, '').replace(/\s+/g, ' ').trim();
            inner.style.width = `${table.offsetWidth}px`;

            const clonedThead = thead.cloneNode(true);
            markCloneInputsForSync(clonedThead);

            const origCells = thead.querySelectorAll('th, td');
            const cloneCells = clonedThead.querySelectorAll('th, td');
            origCells.forEach((cell, i) => {
                const cc = cloneCells[i];
                if (cc) {
                    const w = cell.getBoundingClientRect().width;
                    cc.style.width = `${w}px`;
                    cc.style.minWidth = `${w}px`;
                    cc.style.maxWidth = `${w}px`;
                    cc.style.boxSizing = 'border-box';
                }
            });

            inner.appendChild(clonedThead);
            clone.appendChild(inner);
            document.body.appendChild(clone);

            bindCloneSortLinks(thead, clonedThead);
            bindCloneTextFilters(thead, clonedThead);
            bindCloneMultiselects(thead, clonedThead);
            const form = document.getElementById('filtersForm');
            bindCloneFilterSearchButtons(thead, clonedThead, form);

            syncCloneScroll();
        }

        function syncCloneScroll() {
            if (!clone || !scrollParent) {
                return;
            }
            const rect = scrollParent.getBoundingClientRect();
            clone.style.left = `${rect.left}px`;
            clone.style.width = `${rect.width}px`;
            const innerTable = clone.querySelector('table');
            if (innerTable) {
                innerTable.style.marginLeft = `${-scrollParent.scrollLeft}px`;
            }
        }

        function show() {
            if (!ordersStickyCloneEnabled()) {
                hide();

                return;
            }
            if (!visible) {
                buildClone();
                visible = true;
            }
            clone.style.display = '';
            syncCloneScroll();
        }

        function hide() {
            if (clone) {
                clone.style.display = 'none';
            }
            visible = false;
            window.closeAllMultiselectDropdowns?.();
        }

        function onScrollOrResize() {
            syncOrdersFiltersBarHeightCss();
            if (!ordersStickyCloneEnabled()) {
                hide();

                return;
            }
            const stickyTop = getOrdersTableStickyTopPx();
            const tableRect = table.getBoundingClientRect();
            const theadRect = thead.getBoundingClientRect();
            const tbodyBottom =
                table.querySelector('tbody')?.getBoundingClientRect().bottom ?? tableRect.bottom;

            const shouldShow =
                theadRect.top < stickyTop && tbodyBottom > stickyTop + theadRect.height;
            if (shouldShow) {
                show();
            } else {
                hide();
            }
            if (visible) {
                syncCloneScroll();
            }
        }

        window.addEventListener('scroll', onScrollOrResize, { passive: true });
        window.addEventListener('resize', () => {
            syncOrdersFiltersBarHeightCss();
            if (visible) {
                buildClone();
            } else {
                onScrollOrResize();
            }
        });
        scrollParent.addEventListener('scroll', () => (visible ? syncCloneScroll() : null), { passive: true });

        syncOrdersFiltersBarHeightCss();
        requestAnimationFrame(() => {
            syncOrdersFiltersBarHeightCss();
            onScrollOrResize();
        });
        window.addEventListener('load', () => {
            syncOrdersFiltersBarHeightCss();
            if (visible) {
                buildClone();
            }
        });
    });
}
