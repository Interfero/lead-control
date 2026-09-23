/**
 * Маска телефона +7 (XXX) XXX-XX-XX и утилиты валидации ввода (клиенты КЦ).
 * Курсор ставится в конец введённых цифр по таблице позиций.
 */
(function() {
    'use strict';

    // Позиция курсора после N цифр в строке "+7 (XXX) XXX-XX-XX"
    var CURSOR_POSITIONS = [0, 5, 6, 7, 10, 11, 12, 14, 15, 17, 18];

    function getRawDigits(str) {
        if (!str) return '';
        var digits = String(str).replace(/\D/g, '');
        if (digits.length === 11 && (digits[0] === '7' || digits[0] === '8')) {
            digits = digits.slice(1);
        }
        return digits.slice(0, 10);
    }

    function formatPhoneMask(digits) {
        if (digits.length === 0) return '';
        var d = digits.split('');
        return '+7 (' + (d[0] || '_') + (d[1] || '_') + (d[2] || '_') + ') ' +
            (d[3] || '_') + (d[4] || '_') + (d[5] || '_') + '-' +
            (d[6] || '_') + (d[7] || '_') + '-' + (d[8] || '_') + (d[9] || '_');
    }

    /**
     * Применить маску телефона к полю ввода.
     * Курсор всегда в конце введённых цифр.
     */
    /**
     * Поле только с 10 цифрами; «+7» вынесен отдельно (unified UI).
     */
    function applySplitPhoneMask(inputEl) {
        if (!inputEl || inputEl.dataset.phoneMaskApplied === '1') return;
        inputEl.dataset.phoneMaskApplied = '1';
        inputEl.setAttribute('inputmode', 'numeric');
        inputEl.setAttribute('maxlength', '10');

        inputEl.addEventListener('input', function() {
            inputEl.value = getRawDigits(inputEl.value).slice(0, 10);
        });

        inputEl.addEventListener('paste', function(e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text');
            inputEl.value = getRawDigits(pasted).slice(0, 10);
        });

        if (inputEl.value) {
            inputEl.value = getRawDigits(inputEl.value).slice(0, 10);
        }
    }

    function applyPhoneMask(inputEl) {
        if (!inputEl || inputEl.dataset.phoneMaskApplied === '1') return;
        var mode = inputEl.getAttribute('data-phone-mask') || '';
        if (mode === 'split') {
            applySplitPhoneMask(inputEl);
            return;
        }
        inputEl.dataset.phoneMaskApplied = '1';
        inputEl.setAttribute('inputmode', 'numeric');
        inputEl.setAttribute('maxlength', '18');
        inputEl.classList.add('phone-mask');

        function setValueAndCursor(raw) {
            var formatted = raw.length > 0 ? formatPhoneMask(raw) : '';
            inputEl.value = formatted;
            var pos = CURSOR_POSITIONS[raw.length];
            if (pos !== undefined && pos <= formatted.length) {
                inputEl.setSelectionRange(pos, pos);
            }
        }

        inputEl.addEventListener('input', function() {
            var raw = getRawDigits(inputEl.value);
            setValueAndCursor(raw);
        });

        inputEl.addEventListener('focus', function() {
            if (getRawDigits(inputEl.value).length === 0) {
                inputEl.placeholder = '+7 (___) ___-__-__';
            }
        });

        inputEl.addEventListener('paste', function(e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text');
            var raw = getRawDigits(pasted);
            setValueAndCursor(raw);
        });

        var form = inputEl.closest('form');
        if (form && !form.dataset.phoneMaskSubmitBound) {
            form.dataset.phoneMaskSubmitBound = '1';
            form.addEventListener('submit', function() {
                form.querySelectorAll('.phone-mask').forEach(function(inp) {
                    inp.value = getRawDigits(inp.value);
                });
                setTimeout(function() {
                    form.querySelectorAll('.phone-mask').forEach(function(inp) {
                        var r = getRawDigits(inp.value);
                        inp.value = r.length > 0 ? formatPhoneMask(r) : '';
                    });
                }, 0);
            }, true);
        }

        if (inputEl.value) {
            var r = getRawDigits(inputEl.value);
            inputEl.value = r.length > 0 ? formatPhoneMask(r) : '';
        }
    }

    function allowDigitsOnly(inputEl) {
        if (!inputEl || inputEl.dataset.digitsOnlyApplied === '1') return;
        inputEl.dataset.digitsOnlyApplied = '1';
        inputEl.setAttribute('inputmode', 'numeric');
        inputEl.addEventListener('input', function() {
            inputEl.value = inputEl.value.replace(/\D/g, '');
        });
    }

    function allowAddressSafe(inputEl) {
        if (!inputEl || inputEl.dataset.addressSafeApplied === '1') return;
        inputEl.dataset.addressSafeApplied = '1';
        inputEl.addEventListener('input', function() {
            inputEl.value = inputEl.value.replace(/[^\p{L}\p{N}\s,.\-№\/]/gu, '');
        });
    }

    function initPhoneMasks() {
        document.querySelectorAll('.phone-mask, [data-phone-mask]').forEach(applyPhoneMask);
        document.querySelectorAll('[data-digits-only]').forEach(allowDigitsOnly);
        document.querySelectorAll('[data-address-safe]').forEach(allowAddressSafe);
    }

    window.applyPhoneMask = applyPhoneMask;
    window.applySplitPhoneMask = applySplitPhoneMask;
    window.allowDigitsOnly = allowDigitsOnly;
    window.allowAddressSafe = allowAddressSafe;
    window.initPhoneMasks = initPhoneMasks;
    window.getRawDigitsPhone = getRawDigits;

    document.addEventListener('DOMContentLoaded', initPhoneMasks);
})();
