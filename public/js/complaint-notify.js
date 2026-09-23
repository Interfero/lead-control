(function () {
    const COMPLAINT_UNSEEN_POLL_MS = 3000;
    const STORAGE_UNSEEN_COMPLAINT_IDS = 'lc_unseen_complaint_ids_snapshot';

    function initComplaintUnseenPoll() {
        const body = document.body;
        if (!body?.dataset?.complaintUnseenPoll) {
            return;
        }

        const url = body.dataset.complaintUnseenCountUrl;
        const complaintsBase = (body.dataset.complaintsBaseUrl || '').replace(/\/$/, '');
        const soundUrl = (body.dataset.complaintNotifySound || '').trim();
        const notifyIcon = (body.dataset.cityNotifyIcon || '').trim();
        if (!url || !window.axios) {
            return;
        }

        const badge = document.querySelector('[data-complaint-unseen-badge]');
        const toastHost = document.getElementById('complaintToastHost');
        let chime = null;
        let audioUnlocked = false;

        if (soundUrl) {
            chime = new Audio(soundUrl);
            chime.preload = 'auto';
        }

        function unlockAudio() {
            if (!chime || audioUnlocked) {
                return;
            }
            const previousVolume = chime.volume;
            chime.volume = 0.01;
            chime
                .play()
                .then(() => {
                    chime.pause();
                    chime.currentTime = 0;
                    chime.volume = previousVolume;
                    audioUnlocked = true;
                })
                .catch(() => {
                    chime.volume = previousVolume;
                });
        }

        document.addEventListener('click', unlockAudio, { once: true });
        document.addEventListener('keydown', unlockAudio, { once: true });

        function playChime() {
            const url = soundUrl || '/crm/sounds/order-notify.mp3';
            if (window.LcNotifySound) {
                window.LcNotifySound.play(url);
                return;
            }
            if (!chime) {
                return;
            }
            chime.currentTime = 0;
            chime.play().catch(() => {});
        }

        function complaintShowUrl(complaintId) {
            return `${complaintsBase}/${complaintId}`;
        }

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

        function showInPageToast(title, message, href) {
            if (!toastHost) {
                return;
            }

            const toast = document.createElement('div');
            toast.className = 'complaint-toast';
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
            }, 12000);
        }

        function showSystemNotificationsForNewComplaints(newIds, countDelta) {
            const icon = notifyIcon || undefined;

            if (newIds.length > 0) {
                const sorted = [...newIds].sort((a, b) => b - a);
                sorted.forEach((id) => {
                    const bodyText = `Новая претензия ID ${id}`;
                    showInPageToast('Претензия', bodyText, complaintShowUrl(id));

                    if ('Notification' in window && Notification.permission === 'granted') {
                        const n = new Notification('Lead Control', {
                            body: bodyText,
                            icon: icon,
                            tag: `lc-new-complaint-${id}`,
                            silent: false,
                        });
                        n.onclick = () => {
                            window.focus();
                            n.close();
                            window.location.href = complaintShowUrl(id);
                        };
                    }
                });
                playChime();
            } else if (countDelta > 0) {
                const bodyText =
                    countDelta === 1
                        ? 'Появилась новая претензия без просмотра филиалом'
                        : `Новых претензий без просмотра филиалом: ${countDelta}`;
                showInPageToast('Претензии', bodyText, complaintsBase);

                if ('Notification' in window && Notification.permission === 'granted') {
                    const n = new Notification('Lead Control', {
                        body: bodyText,
                        icon: icon,
                        tag: 'lc-new-complaints-fallback',
                        silent: false,
                    });
                    n.onclick = () => {
                        window.focus();
                        n.close();
                        window.location.href = complaintsBase;
                    };
                }
                playChime();
            }

            if (typeof navigator !== 'undefined' && navigator.vibrate) {
                navigator.vibrate(180);
            }
        }

        let lastCount = null;
        let initialized = false;

        async function poll() {
            try {
                const { data } = await window.axios.get(url);
                const count = typeof data.count === 'number' ? data.count : 0;
                const unseenComplaintIds = Array.isArray(data.unseen_complaint_ids)
                    ? data.unseen_complaint_ids.map((x) => Number(x))
                    : [];

                if (!initialized) {
                    try {
                        sessionStorage.setItem(STORAGE_UNSEEN_COMPLAINT_IDS, JSON.stringify(unseenComplaintIds));
                    } catch {
                        /* ignore */
                    }
                    lastCount = count;
                    initialized = true;
                    updateBadge(count);
                    return;
                }

                let prevIds = [];
                try {
                    prevIds = JSON.parse(sessionStorage.getItem(STORAGE_UNSEEN_COMPLAINT_IDS) || '[]');
                } catch {
                    prevIds = [];
                }
                if (!Array.isArray(prevIds)) {
                    prevIds = [];
                }
                const prevSet = new Set(prevIds);
                const newIds = unseenComplaintIds.filter((id) => !prevSet.has(id));
                const countDelta = lastCount !== null && count > lastCount ? count - lastCount : 0;

                if (newIds.length > 0 || countDelta > 0) {
                    showSystemNotificationsForNewComplaints(newIds, newIds.length > 0 ? 0 : countDelta);
                }

                try {
                    sessionStorage.setItem(STORAGE_UNSEEN_COMPLAINT_IDS, JSON.stringify(unseenComplaintIds));
                } catch {
                    /* ignore */
                }

                lastCount = count;
                updateBadge(count);
            } catch {
                /* ignore */
            }
        }

        poll();
        setInterval(poll, COMPLAINT_UNSEEN_POLL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComplaintUnseenPoll);
    } else {
        initComplaintUnseenPoll();
    }
})();
