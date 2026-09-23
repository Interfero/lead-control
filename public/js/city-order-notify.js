/**
 * Уведомления о новых заказах для филиала (+ директора с доступом к городам).
 */
(function () {
    const POLL_MS = 3000;
    const STORAGE_SNAPSHOT = 'lc_unseen_order_ids_v3';
    const STORAGE_NOTIFIED = 'lc_notified_order_ids_v3';

    function waitForAxios(cb) {
        if (window.axios) {
            cb();
            return;
        }
        let tries = 0;
        const timer = setInterval(function () {
            if (window.axios || ++tries > 80) {
                clearInterval(timer);
                if (window.axios) {
                    cb();
                } else {
                    console.warn('[city-notify] axios not available');
                }
            }
        }, 100);
    }

    function start() {
        const body = document.body;
        if (!body?.dataset?.cityUnseenPoll) {
            return;
        }
        if (body.dataset.cityUnseenPollBound === '1') {
            return;
        }
        body.dataset.cityUnseenPollBound = '1';

        const url = body.dataset.cityUnseenCountUrl;
        const ordersBase = (body.dataset.cityOrdersBaseUrl || '').replace(/\/$/, '');
        const notifyIcon = (body.dataset.cityNotifyIcon || '').trim();
        const soundUrl = (body.dataset.cityNotifySound || '/crm/sounds/order-notify.mp3').trim();

        const badge = document.querySelector('[data-city-unseen-badge]');
        const notifyBar = document.getElementById('cityUnseenNotifyBar');
        const notifyBtn = document.getElementById('cityUnseenNotifyEnableBtn');
        const testBtn = document.getElementById('cityNotifyTestBtn');
        const statusEl = document.getElementById('cityNotifyStatus');
        const toastHost = document.getElementById('complaintToastHost');

        if (window.LcNotifySound) {
            window.LcNotifySound.bindUnlock(soundUrl);
        }

        function setStatus(text) {
            if (statusEl) {
                statusEl.textContent = text || '';
            }
        }

        function orderShowUrl(orderId) {
            return ordersBase + '/' + orderId;
        }

        function readIds(key, storage) {
            try {
                const raw = storage.getItem(key);
                const parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed.map(Number).filter(function (n) { return n > 0; }) : [];
            } catch (e) {
                return [];
            }
        }

        function writeIds(key, storage, ids) {
            try {
                const unique = Array.from(new Set(ids.map(Number).filter(function (n) { return n > 0; })));
                storage.setItem(key, JSON.stringify(unique.slice(-3000)));
            } catch (e) {}
        }

        function showToast(title, message, href) {
            if (!toastHost) {
                return;
            }
            const el = document.createElement('div');
            el.className = 'complaint-toast';
            el.innerHTML =
                '<div class="complaint-toast__header">' +
                '<strong class="complaint-toast__title"></strong>' +
                '<button type="button" class="complaint-toast__close" aria-label="Закрыть">&times;</button>' +
                '</div><div class="complaint-toast__body"></div>';
            el.querySelector('.complaint-toast__title').textContent = title;
            el.querySelector('.complaint-toast__body').textContent = message;
            el.querySelector('.complaint-toast__close').addEventListener('click', function (e) {
                e.stopPropagation();
                el.remove();
            });
            el.addEventListener('click', function () {
                if (href) {
                    window.location.href = href;
                }
            });
            toastHost.appendChild(el);
            setTimeout(function () {
                el.classList.add('complaint-toast--fade');
                setTimeout(function () { el.remove(); }, 400);
            }, 12000);
        }

        function playSound() {
            if (!window.LcNotifySound) {
                setStatus('Нет модуля звука');
                return Promise.resolve(false);
            }
            return window.LcNotifySound.play(soundUrl).then(function (mode) {
                setStatus(mode ? ('Звук: ' + mode) : 'Звук не удался — кликните страницу');
                return !!mode;
            });
        }

        if (notifyBar) {
            notifyBar.classList.remove('hidden');
        }

        if (testBtn) {
            testBtn.addEventListener('click', function () {
                window.LcNotifySound?.unlock(soundUrl).then(function () {
                    playSound();
                    showToast('Тест CRM', 'Если слышите — уведомления работают', ordersBase);
                });
            });
        }

        if (notifyBtn && 'Notification' in window) {
            notifyBtn.addEventListener('click', function () {
                window.LcNotifySound?.unlock(soundUrl);
                Notification.requestPermission();
            });
        }

        let initialized = false;
        let lastCount = null;

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

        function notifyNewOrders(newIds) {
            if (!newIds.length) {
                return;
            }
            playSound();
            newIds.sort(function (a, b) { return b - a; }).slice(0, 5).forEach(function (id) {
                const bodyText = 'Новый заказ ID ' + id;
                showToast('Lead Control', bodyText, orderShowUrl(id));
                if ('Notification' in window && Notification.permission === 'granted') {
                    try {
                        const n = new Notification('Lead Control', {
                            body: bodyText,
                            icon: notifyIcon || undefined,
                            tag: 'lc-new-order-' + id,
                            silent: true,
                        });
                        n.onclick = function () {
                            window.focus();
                            n.close();
                            window.location.href = orderShowUrl(id);
                        };
                    } catch (e) {}
                }
            });
            if (navigator.vibrate) {
                navigator.vibrate(180);
            }
        }

        async function poll() {
            try {
                const { data } = await window.axios.get(url);
                const count = typeof data.count === 'number' ? data.count : 0;
                const unseenOrderIds = Array.isArray(data.unseen_order_ids)
                    ? data.unseen_order_ids.map(Number).filter(function (n) { return n > 0; })
                    : [];

                if (!initialized) {
                    writeIds(STORAGE_SNAPSHOT, sessionStorage, unseenOrderIds);
                    initialized = true;
                    lastCount = count;
                    updateBadge(count);
                    setStatus('Слежу (' + count + ' непросмотренных)');
                    return;
                }

                const prevSet = new Set(readIds(STORAGE_SNAPSHOT, sessionStorage));
                const alreadyNotified = new Set(readIds(STORAGE_NOTIFIED, localStorage));
                let brandNewIds = unseenOrderIds.filter(function (id) {
                    return !prevSet.has(id) && !alreadyNotified.has(id);
                });

                if (brandNewIds.length === 0 && lastCount !== null && count > lastCount) {
                    brandNewIds = unseenOrderIds.filter(function (id) { return !alreadyNotified.has(id); }).slice(0, count - lastCount);
                }

                if (brandNewIds.length > 0) {
                    notifyNewOrders(brandNewIds);
                    writeIds(STORAGE_NOTIFIED, localStorage, Array.from(alreadyNotified).concat(brandNewIds));
                    setStatus('Новых: ' + brandNewIds.length);
                }

                writeIds(STORAGE_SNAPSHOT, sessionStorage, unseenOrderIds);
                lastCount = count;
                updateBadge(count);
            } catch (e) {
                setStatus('Ошибка опроса');
                console.warn('[city-notify]', e);
            }
        }

        poll();
        setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                poll();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        waitForAxios(start);
    });
})();
