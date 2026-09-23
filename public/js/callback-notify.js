(function () {
    const POLL_MS = 30000;
    const STORAGE_DUE_CALLBACK_IDS = 'lc_due_callback_ids_snapshot';
    const STORAGE_TOASTED_IDS = 'lc_callback_toast_shown_ids';

    function initCallbackDuePoll() {
        const body = document.body;
        if (!body?.dataset?.callbackDuePoll) {
            return;
        }

        const url = body.dataset.callbackDueCountUrl;
        const ordersBase = (body.dataset.callbackOrdersBaseUrl || '').replace(/\/$/, '');
        const soundUrl = (body.dataset.callbackNotifySound || '').trim();
        const notifyIcon = (body.dataset.cityNotifyIcon || '').trim();
        if (!url || !window.axios) {
            return;
        }

        const toastHost = document.getElementById('complaintToastHost');

        function orderShowUrl(orderId) {
            return `${ordersBase}/${orderId}`;
        }

        function readJsonArray(key) {
            try {
                const raw = JSON.parse(localStorage.getItem(key) || '[]');
                return Array.isArray(raw) ? raw.map((x) => Number(x)).filter((n) => Number.isFinite(n)) : [];
            } catch {
                return [];
            }
        }

        function writeJsonArray(key, ids) {
            try {
                localStorage.setItem(key, JSON.stringify([...new Set(ids.map((x) => Number(x)))]));
            } catch {
                /* ignore */
            }
        }

        function playChime() {
            const chimeUrl = soundUrl || '/crm/sounds/order-notify.mp3';
            if (window.LcNotifySound) {
                window.LcNotifySound.play(chimeUrl);
                return;
            }
            const chime = new Audio(chimeUrl);
            chime.play().catch(() => {});
        }

        function showInPageToast(title, message, href) {
            if (!toastHost) {
                return;
            }

            const toast = document.createElement('div');
            toast.className = 'complaint-toast callback-toast';
            toast.innerHTML =
                '<div class="complaint-toast__header">' +
                '<strong class="complaint-toast__title"></strong>' +
                '<button type="button" class="complaint-toast__close" aria-label="Закрыть">&times;</button>' +
                '</div>' +
                '<div class="complaint-toast__body"></div>';
            toast.querySelector('.complaint-toast__title').textContent = title;
            toast.querySelector('.complaint-toast__body').textContent = message;
            toast.querySelector('.complaint-toast__close').addEventListener('click', (event) => {
                event.stopPropagation();
                toast.remove();
            });
            toast.addEventListener('click', () => {
                window.location.href = href;
            });
            toastHost.appendChild(toast);

            window.setTimeout(() => {
                toast.classList.add('complaint-toast--fade');
                window.setTimeout(() => toast.remove(), 400);
            }, 20000);
        }

        function notifyCallbacks(ids) {
            if (!ids.length) {
                return;
            }

            [...ids]
                .sort((a, b) => b - a)
                .forEach((id) => {
                    const bodyText = `Прозвон по заказу №${id}: время события наступило, нужен звонок клиенту`;
                    showInPageToast('Прозвон — событие', bodyText, orderShowUrl(id));

                    if ('Notification' in window && Notification.permission === 'granted') {
                        const n = new Notification('Lead Control', {
                            body: bodyText,
                            icon: notifyIcon || undefined,
                            tag: `lc-callback-due-${id}`,
                            silent: false,
                        });
                        n.onclick = () => {
                            window.focus();
                            n.close();
                            window.location.href = orderShowUrl(id);
                        };
                    }
                });

            playChime();
            if (typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate(180);
            }
        }

        async function poll() {
            try {
                const { data } = await window.axios.get(url);
                const dueIds = Array.isArray(data.due_callback_order_ids)
                    ? data.due_callback_order_ids.map((x) => Number(x)).filter((n) => Number.isFinite(n))
                    : [];

                const toasted = new Set(readJsonArray(STORAGE_TOASTED_IDS));
                const toNotify = dueIds.filter((id) => !toasted.has(id));

                if (toNotify.length > 0) {
                    notifyCallbacks(toNotify);
                    toNotify.forEach((id) => toasted.add(id));
                }

                // Помним показанные только пока заказ ещё due (закрыли/сменили статус — можно показать снова, если вернётся)
                writeJsonArray(
                    STORAGE_TOASTED_IDS,
                    dueIds.filter((id) => toasted.has(id))
                );
                writeJsonArray(STORAGE_DUE_CALLBACK_IDS, dueIds);
            } catch {
                /* ignore */
            }
        }

        poll();
        setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                poll();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCallbackDuePoll);
    } else {
        initCallbackDuePoll();
    }
})();
