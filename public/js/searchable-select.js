(function () {
    function normalize(str) {
        return (str || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function initSearchableSelect(root) {
        const hidden = root.querySelector('[data-searchable-value]');
        const input = root.querySelector('[data-searchable-input]');
        const dropdown = root.querySelector('[data-searchable-dropdown]');
        const clearBtn = root.querySelector('[data-searchable-clear]');
        const options = JSON.parse(root.dataset.options || '[]');
        const emptyLabel = root.dataset.emptyLabel || 'Ничего не найдено';
        let filtered = options;
        let activeIndex = -1;

        function findOption(value) {
            return options.find(function (opt) {
                return String(opt.value) === String(value);
            });
        }

        function setClearVisible(visible) {
            if (!clearBtn) return;
            clearBtn.classList.toggle('hidden', !visible);
        }

        function selectOption(opt) {
            hidden.value = opt.value;
            input.value = opt.label;
            setClearVisible(true);
            closeDropdown();
        }

        function clearSelection() {
            hidden.value = '';
            input.value = '';
            setClearVisible(false);
            closeDropdown();
            input.focus();
        }

        function closeDropdown() {
            dropdown.classList.add('hidden');
            activeIndex = -1;
        }

        function openDropdown() {
            dropdown.classList.remove('hidden');
        }

        function renderList(items) {
            dropdown.innerHTML = '';
            if (!items.length) {
                const empty = document.createElement('div');
                empty.className = 'searchable-select__empty';
                empty.textContent = emptyLabel;
                dropdown.appendChild(empty);
                return;
            }

            items.forEach(function (opt, index) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'searchable-select__option' + (index === activeIndex ? ' is-active' : '');
                btn.textContent = opt.label;
                btn.setAttribute('role', 'option');
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectOption(opt);
                });
                dropdown.appendChild(btn);
            });
        }

        function filter(query) {
            const q = normalize(query);
            filtered = !q
                ? options.slice()
                : options.filter(function (opt) {
                      return normalize(opt.label).includes(q);
                  });
            activeIndex = filtered.length ? 0 : -1;
            renderList(filtered);
        }

        function syncInputFromHidden() {
            const selected = findOption(hidden.value);
            if (selected) {
                input.value = selected.label;
                setClearVisible(true);
            } else if (!hidden.value) {
                input.value = '';
                setClearVisible(false);
            }
        }

        input.addEventListener('focus', function () {
            filter(input.value);
            openDropdown();
        });

        input.addEventListener('input', function () {
            hidden.value = '';
            setClearVisible(false);
            filter(input.value);
            openDropdown();
        });

        input.addEventListener('blur', function () {
            setTimeout(function () {
                closeDropdown();
                if (!hidden.value && normalize(input.value)) {
                    const exact = options.find(function (opt) {
                        return normalize(opt.label) === normalize(input.value);
                    });
                    if (exact) {
                        selectOption(exact);
                        return;
                    }
                    if (filtered.length === 1) {
                        selectOption(filtered[0]);
                        return;
                    }
                }
                syncInputFromHidden();
            }, 150);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (dropdown.classList.contains('hidden')) {
                    filter(input.value);
                    openDropdown();
                    return;
                }
                if (filtered.length) {
                    activeIndex = Math.min(activeIndex + 1, filtered.length - 1);
                    renderList(filtered);
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (filtered.length) {
                    activeIndex = Math.max(activeIndex - 1, 0);
                    renderList(filtered);
                }
            } else if (e.key === 'Enter') {
                if (!dropdown.classList.contains('hidden') && activeIndex >= 0 && filtered[activeIndex]) {
                    e.preventDefault();
                    selectOption(filtered[activeIndex]);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
                syncInputFromHidden();
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                clearSelection();
            });
        }

        syncInputFromHidden();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);
    });
})();
