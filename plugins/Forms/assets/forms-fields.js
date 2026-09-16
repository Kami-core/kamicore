(function () {
    'use strict';

    if (window.FormsFields?.initialized) {
        window.FormsFields.init(document);
        return;
    }

    function elementsWithin(root, selector) {
        const items = [];
        if (root instanceof Element && root.matches(selector)) {
            items.push(root);
        }
        if (root && typeof root.querySelectorAll === 'function') {
            items.push(...root.querySelectorAll(selector));
        }
        return items;
    }

    function nextIndex(root) {
        const current = Number(root.dataset.repeatableIndex || 0);
        const next = Number.isFinite(current) ? current + 1 : 1;
        root.dataset.repeatableIndex = String(next);
        return next;
    }

    function fragmentFromTemplate(template, index) {
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        const holder = document.createElement('template');
        holder.innerHTML = html.trim();
        return holder.content;
    }

    function initializeDynamic(root) {
        window.Kami?.initDynamic?.(root);
        window.FormsRichtext?.init?.(root);
    }

    function rowList(root) {
        return root.querySelector('[data-repeatable-list], [data-media-list]');
    }

    function rows(root) {
        return Array.from(rowList(root)?.querySelectorAll(':scope > [data-repeatable-row]') || []);
    }

    function clearRow(row) {
        row.querySelectorAll('input:not([type="button"]):not([type="submit"]), textarea').forEach(control => {
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = false;
            } else {
                control.value = '';
            }
        });
        row.querySelectorAll('.ql-editor').forEach(editor => {
            editor.innerHTML = '<p><br></p>';
        });
    }

    function removeRow(root, row) {
        const currentRows = rows(root);
        if (root.dataset.repeatableRequired === '1' && currentRows.length <= 1) {
            clearRow(row);
            row.querySelector('input, textarea')?.focus();
            return;
        }
        row.remove();
    }

    function moveRow(root, row, direction) {
        const list = rowList(root);
        if (!list) return;
        if (direction < 0 && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (direction > 0 && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
    }

    function bindRowActions(root) {
        root.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button || !root.contains(button)) return;
            const row = button.closest('[data-repeatable-row]');
            if (!row) return;

            if (button.matches('[data-repeatable-remove]')) {
                removeRow(root, row);
            } else if (button.matches('[data-repeatable-up]')) {
                moveRow(root, row, -1);
            } else if (button.matches('[data-repeatable-down]')) {
                moveRow(root, row, 1);
            }
        });
    }

    function initRepeatable(root) {
        if (root.dataset.repeatableInitialized === '1') return;
        root.dataset.repeatableInitialized = '1';
        root.dataset.repeatableIndex = String(rows(root).length);
        bindRowActions(root);

        root.querySelector('[data-repeatable-add]')?.addEventListener('click', () => {
            const template = root.querySelector('template[data-repeatable-template]');
            const list = rowList(root);
            if (!template || !list) return;
            const fragment = fragmentFromTemplate(template, nextIndex(root));
            const row = fragment.firstElementChild;
            list.append(fragment);
            if (row) {
                initializeDynamic(row);
                row.querySelector('input, textarea')?.focus();
            }
        });
    }

    function mediaOptions(root, multiple) {
        let accept = [];
        try {
            const parsed = JSON.parse(root.dataset.mediaAccept || '[]');
            accept = Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            accept = [];
        }

        return {
            multiple,
            root: root.dataset.mediaRoot || '',
            accept,
            canManage: root.dataset.mediaCanManage === '1'
        };
    }

    function existingMediaValues(root) {
        return new Set(
            Array.from(root.querySelectorAll('[data-media-input]'))
                .map(input => input.value.trim())
                .filter(Boolean)
        );
    }

    function addMediaRow(root, value = '') {
        const list = rowList(root);
        const template = root.querySelector('template[data-media-row-template]');
        if (!list || !template) return null;

        const blank = Array.from(list.querySelectorAll('[data-media-input]'))
            .find(input => input.value.trim() === '');
        if (blank) {
            blank.value = value;
            return blank.closest('[data-media-row]');
        }

        const fragment = fragmentFromTemplate(template, nextIndex(root));
        const row = fragment.firstElementChild;
        const input = row?.querySelector('[data-media-input]');
        if (input) input.value = value;
        list.append(fragment);
        if (row) initializeDynamic(row);
        return row;
    }

    async function browseMedia(root, multiple) {
        if (!window.KamiMedia?.open) return null;
        return window.KamiMedia.open(mediaOptions(root, multiple));
    }

    function initMedia(root) {
        if (root.dataset.mediaFieldInitialized === '1') return;
        root.dataset.mediaFieldInitialized = '1';
        root.dataset.repeatableIndex = String(rows(root).length);
        bindRowActions(root);

        root.addEventListener('click', async event => {
            const button = event.target.closest('button');
            if (!button || !root.contains(button)) return;

            if (button.matches('[data-media-browse-row]')) {
                const row = button.closest('[data-media-row]');
                const input = row?.querySelector('[data-media-input]');
                if (!input) return;
                const file = await browseMedia(root, false);
                if (file?.url) input.value = file.url;
                return;
            }

            if (button.matches('[data-media-add-url]')) {
                const row = addMediaRow(root, '');
                row?.querySelector('[data-media-input]')?.focus();
                return;
            }

            if (button.matches('[data-media-browse-all]')) {
                const files = await browseMedia(root, true);
                if (!Array.isArray(files) || files.length === 0) return;
                const existing = existingMediaValues(root);
                for (const file of files) {
                    const url = String(file?.url || '').trim();
                    if (!url || existing.has(url)) continue;
                    addMediaRow(root, url);
                    existing.add(url);
                }
            }
        });
    }

    function init(root = document) {
        elementsWithin(root, '[data-repeatable]').forEach(initRepeatable);
        elementsWithin(root, '[data-media-field]').forEach(initMedia);
    }

    window.FormsFields = { initialized: true, init };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init(document));
    } else {
        init(document);
    }

    window.Kami?.registerInitializer?.(init);
})();
