/**
 * Раскрытие маскированных телефонов по клику.
 */
(function () {
    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function markRevealed(btn, text) {
        const textEl = btn.querySelector('[data-phone-text]');
        const hint = btn.querySelector('[data-phone-hint]');
        if (textEl) textEl.textContent = text;
        if (hint) hint.remove();
        btn.classList.add('phone-masked--revealed');
        btn.setAttribute('aria-expanded', 'true');
        btn.disabled = true;
    }

    async function revealApi(btn) {
        if (btn.dataset.loading === '1' || btn.classList.contains('phone-masked--revealed')) return;
        const url = btn.getAttribute('data-reveal-url');
        if (!url) return;

        btn.dataset.loading = '1';
        const hint = btn.querySelector('[data-phone-hint]');
        if (hint) hint.textContent = '…';

        try {
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf(),
                },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (res.status === 401 || data.bot_kick) {
                window.location.href = '/login';
                return;
            }
            if (!res.ok || !data.success) {
                if (hint) hint.textContent = 'ошибка';
                btn.dataset.loading = '0';
                return;
            }
            markRevealed(btn, data.formatted || data.phone_number || '');
        } catch (e) {
            if (hint) hint.textContent = 'ошибка';
            btn.dataset.loading = '0';
        }
    }

    function revealLocal(btn) {
        if (btn.classList.contains('phone-masked--revealed')) return;
        const full = btn.getAttribute('data-phone-full') || '';
        if (!full) return;
        markRevealed(btn, full);
    }

    document.addEventListener('click', function (e) {
        const apiBtn = e.target.closest('[data-phone-reveal]');
        if (apiBtn) {
            e.preventDefault();
            revealApi(apiBtn);
            return;
        }
        const localBtn = e.target.closest('[data-phone-reveal-local]');
        if (localBtn) {
            e.preventDefault();
            revealLocal(localBtn);
        }
    });
})();
