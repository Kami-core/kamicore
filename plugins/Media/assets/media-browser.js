(function () {
    'use strict';

    const DEFAULT_ENDPOINTS = {
        list: '/ajax/Media/listFiles',
        upload: '/ajax/Media/upload',
        mkdir: '/ajax/Media/createFolder',
        rename: '/ajax/Media/rename',
        move: '/ajax/Media/move',
        delete: '/ajax/Media/delete'
    };

    function joinPath(base, name) {
        return [base, name].filter(Boolean).join('/');
    }

    function normalizeAccept(value) {
        if (Array.isArray(value)) {
            return value.map(item => String(item).trim()).filter(Boolean);
        }
        if (typeof value === 'string') {
            return value.split(/[\s,]+/).map(item => item.trim()).filter(Boolean);
        }
        return [];
    }

    function formatBytes(bytes) {
        const value = Number(bytes || 0);
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
        if (value < 1024 * 1024 * 1024) return (value / (1024 * 1024)).toFixed(1) + ' MB';
        return (value / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
    }

    async function requestJson(url, options) {
        const requestOptions = options || {};
        const response = await fetch(url, {
            ...requestOptions,
            credentials: 'same-origin',
            headers: Object.assign({'Accept': 'application/json'}, requestOptions.headers || {})
        });

        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('Invalid response from Media.');
        }

        if (!response.ok || data.status !== 'ok') {
            throw new Error(data.error || 'Media request failed.');
        }
        return data;
    }

    class MediaBrowser {
        constructor(root, options = {}) {
            this.rootElement = root;
            this.browserRoot = options.root ?? root.dataset.root ?? '';
            this.currentPath = this.browserRoot;
            this.canManage = options.canManage ?? root.dataset.canManage === '1';
            this.picker = Boolean(options.picker);
            this.multiple = Boolean(options.multiple);
            this.accept = normalizeAccept(options.accept);
            this.onSelect = typeof options.onSelect === 'function' ? options.onSelect : null;
            this.selected = this.multiple ? new Map() : null;
            this.endpoints = {
                list: options.listUrl || root.dataset.listUrl || DEFAULT_ENDPOINTS.list,
                upload: options.uploadUrl || root.dataset.uploadUrl || DEFAULT_ENDPOINTS.upload,
                mkdir: options.mkdirUrl || root.dataset.mkdirUrl || DEFAULT_ENDPOINTS.mkdir,
                rename: options.renameUrl || root.dataset.renameUrl || DEFAULT_ENDPOINTS.rename,
                move: options.moveUrl || root.dataset.moveUrl || DEFAULT_ENDPOINTS.move,
                delete: options.deleteUrl || root.dataset.deleteUrl || DEFAULT_ENDPOINTS.delete
            };

            this.grid = root.querySelector('[data-media-grid]');
            this.empty = root.querySelector('[data-media-empty]');
            this.notice = root.querySelector('[data-media-notice]');
            this.breadcrumbs = root.querySelector('[data-media-breadcrumbs]');
            this.uploadButton = root.querySelector('[data-media-upload]');
            this.mkdirButton = root.querySelector('[data-media-mkdir]');
            this.refreshButton = root.querySelector('[data-media-refresh]');
            this.fileInput = root.querySelector('[data-media-file-input]');

            this.bind();
            this.load(this.currentPath);
        }

        bind() {
            if (this.uploadButton) {
                this.uploadButton.hidden = !this.canManage;
                this.uploadButton.addEventListener('click', () => this.fileInput?.click());
            }
            if (this.mkdirButton) {
                this.mkdirButton.hidden = !this.canManage;
                this.mkdirButton.addEventListener('click', () => this.createFolder());
            }
            this.refreshButton?.addEventListener('click', () => this.load(this.currentPath));
            this.fileInput?.addEventListener('change', () => this.uploadFiles());
        }

        async load(path) {
            this.clearNotice();
            const query = new URLSearchParams({path, root: this.browserRoot});
            try {
                const response = await requestJson(this.endpoints.list + '?' + query.toString());
                this.currentPath = response.data.path;
                this.canManage = Boolean(response.data.can_manage);
                this.render(response.data);
            } catch (error) {
                this.showError(error.message);
            }
        }

        render(data) {
            this.renderBreadcrumbs(data.breadcrumbs || []);
            this.grid.replaceChildren();
            if (!this.multiple) {
                this.selected = null;
            }

            const entries = (data.entries || []).filter(entry => this.matchesAccept(entry));
            for (const entry of entries) {
                this.grid.append(this.createCard(entry));
            }

            this.empty.hidden = entries.length !== 0;
            if (this.mkdirButton) {
                this.mkdirButton.hidden = !data.can_create_folder;
            }
            if (this.uploadButton) {
                this.uploadButton.hidden = !this.canManage;
            }
        }

        renderBreadcrumbs(items) {
            this.breadcrumbs.replaceChildren();
            items.forEach((item, index) => {
                if (index > 0) {
                    const separator = document.createElement('span');
                    separator.className = 'media-browser-path-separator';
                    separator.textContent = '/';
                    this.breadcrumbs.append(separator);
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = item.name;
                button.addEventListener('click', () => this.load(item.path));
                this.breadcrumbs.append(button);
            });
        }

        createCard(entry) {
            const card = document.createElement('article');
            card.className = 'media-card';
            card.dataset.path = entry.path;
            if (
                this.picker
                && this.multiple
                && entry.type === 'file'
                && this.selected instanceof Map
                && this.selected.has(entry.path)
            ) {
                card.classList.add('is-selected');
            }

            const main = document.createElement('button');
            main.type = 'button';
            main.className = 'media-card-main';

            const preview = document.createElement('div');
            preview.className = 'media-card-preview';

            if (entry.type === 'directory') {
                preview.innerHTML = '<svg class="icon icon-folder media-card-icon" aria-hidden="true"></svg>';
                main.addEventListener('click', () => this.load(entry.path));
            } else if (entry.previewable && entry.url) {
                const image = document.createElement('img');
                image.src = entry.preview_url || entry.url;
                image.alt = '';
                image.loading = 'lazy';
                preview.append(image);
                main.addEventListener('click', () => this.selectEntry(entry, card));
            } else {
                preview.innerHTML = '<svg class="icon icon-image media-card-icon" aria-hidden="true"></svg>';
                main.addEventListener('click', () => this.selectEntry(entry, card));
            }

            const body = document.createElement('div');
            body.className = 'media-card-body';
            const name = document.createElement('span');
            name.className = 'media-card-name';
            name.textContent = entry.name;
            name.title = entry.name;
            body.append(name);

            const meta = document.createElement('span');
            meta.className = 'media-card-meta';
            meta.textContent = entry.type === 'directory' ? 'Folder' : formatBytes(entry.size);
            body.append(meta);

            main.append(preview, body);
            card.append(main);

            if (!this.picker && this.canManage) {
                card.append(this.createActions(entry));
            } else if (!this.picker && entry.type === 'file') {
                card.append(this.createReadActions(entry));
            }

            return card;
        }

        createReadActions(entry) {
            const actions = document.createElement('div');
            actions.className = 'media-card-actions';
            actions.append(this.actionButton('Copy URL', () => this.copyUrl(entry)));
            return actions;
        }

        createActions(entry) {
            const actions = document.createElement('div');
            actions.className = 'media-card-actions';
            if (entry.type === 'file') {
                actions.append(this.actionButton('Copy URL', () => this.copyUrl(entry)));
            }
            if (!entry.system_date_directory) {
                actions.append(this.actionButton('Rename', () => this.renameEntry(entry)));
                actions.append(this.actionButton('Move', () => this.moveEntry(entry)));
            }
            actions.append(this.actionButton('Delete', () => this.deleteEntry(entry)));
            return actions;
        }

        actionButton(label, callback) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-button admin-button-action admin-button-small';
            button.textContent = label;
            button.addEventListener('click', callback);
            return button;
        }

        selectEntry(entry, card) {
            if (entry.type !== 'file') return;
            if (this.picker) {
                if (this.multiple) {
                    if (!(this.selected instanceof Map)) {
                        this.selected = new Map();
                    }
                    if (this.selected.has(entry.path)) {
                        this.selected.delete(entry.path);
                        card.classList.remove('is-selected');
                    } else {
                        this.selected.set(entry.path, entry);
                        card.classList.add('is-selected');
                    }
                    const selection = Array.from(this.selected.values());
                    this.onSelect?.(selection);
                    this.rootElement.dispatchEvent(new CustomEvent('media:selected', {detail: selection}));
                    return;
                }

                this.rootElement.querySelectorAll('.media-card.is-selected').forEach(el => el.classList.remove('is-selected'));
                card.classList.add('is-selected');
                this.selected = entry;
                this.onSelect?.(entry);
                this.rootElement.dispatchEvent(new CustomEvent('media:selected', {detail: entry}));
                return;
            }
            this.copyUrl(entry);
        }

        async uploadFiles() {
            const files = Array.from(this.fileInput?.files || []);
            if (!files.length) return;

            const form = new FormData();
            form.append('path', this.currentPath);
            form.append('root', this.browserRoot);
            files.forEach(file => form.append('files[]', file, file.name));

            try {
                const response = await requestJson(this.endpoints.upload, {method: 'POST', body: form});
                if (this.fileInput) this.fileInput.value = '';
                await this.load(response.path || this.currentPath);
                this.showSuccess(files.length === 1 ? 'File uploaded.' : 'Files uploaded.');
            } catch (error) {
                if (this.fileInput) this.fileInput.value = '';
                this.showError(error.message);
            }
        }

        async createFolder() {
            const name = window.prompt('Folder name');
            if (!name) return;
            try {
                await this.postForm(this.endpoints.mkdir, {path: this.currentPath, root: this.browserRoot, name});
                await this.load(this.currentPath);
            } catch (error) {
                this.showError(error.message);
            }
        }

        async renameEntry(entry) {
            const name = window.prompt('New name', entry.name);
            if (!name || name === entry.name) return;
            try {
                await this.postForm(this.endpoints.rename, {path: entry.path, root: this.browserRoot, name});
                await this.load(this.currentPath);
            } catch (error) {
                this.showError(error.message);
            }
        }

        async moveEntry(entry) {
            const destination = window.prompt('Destination folder relative to Media root', this.browserRoot || '');
            if (destination === null) return;
            try {
                await this.postForm(this.endpoints.move, {path: entry.path, root: this.browserRoot, destination});
                await this.load(this.currentPath);
            } catch (error) {
                this.showError(error.message);
            }
        }

        async deleteEntry(entry) {
            if (!window.confirm('Delete this item? Existing links may stop working.')) return;
            try {
                await this.postForm(this.endpoints.delete, {path: entry.path, root: this.browserRoot});
                await this.load(this.currentPath);
            } catch (error) {
                this.showError(error.message);
            }
        }

        async copyUrl(entry) {
            if (!entry.url) return;
            try {
                await navigator.clipboard.writeText(entry.url);
                this.showSuccess('URL copied.');
            } catch (error) {
                window.prompt('Copy URL', entry.url);
            }
        }

        async postForm(url, values) {
            const form = new FormData();
            Object.entries(values).forEach(([key, value]) => form.append(key, value));
            return requestJson(url, {method: 'POST', body: form});
        }

        matchesAccept(entry) {
            if (entry.type === 'directory' || this.accept.length === 0) return true;
            return this.accept.some(rule => {
                const normalized = String(rule).toLowerCase();
                const mime = String(entry.mime || '').toLowerCase();
                const extension = String(entry.extension || '').toLowerCase();
                if (normalized.endsWith('/*')) return mime.startsWith(normalized.slice(0, -1));
                if (normalized.startsWith('.')) return extension === normalized.slice(1);
                if (normalized.includes('/')) return mime === normalized;
                return extension === normalized.replace(/^\./, '');
            });
        }

        showError(message) {
            this.showNotice(message, 'is-error');
        }

        showSuccess(message) {
            this.showNotice(message, 'is-success');
        }

        showNotice(message, className) {
            if (!this.notice) return;
            this.notice.textContent = message;
            this.notice.className = 'media-browser-notice ' + className;
            this.notice.hidden = false;
        }

        clearNotice() {
            if (!this.notice) return;
            this.notice.hidden = true;
            this.notice.textContent = '';
            this.notice.className = 'media-browser-notice';
        }
    }

    function modalMarkup(options) {
        const modal = document.createElement('div');
        modal.className = 'media-modal';
        modal.innerHTML = `
            <div class="media-modal-backdrop" data-media-close></div>
            <section class="media-modal-dialog" role="dialog" aria-modal="true">
                <header class="media-modal-header">
                    <strong data-media-title></strong>
                    <button class="admin-button admin-button-secondary admin-button-small" type="button" data-media-close>Close</button>
                </header>
                <div class="media-modal-content">
                    <div class="media-browser" data-media-browser>
                        <div class="media-browser-toolbar">
                            <div class="media-browser-actions">
                                <button class="admin-button admin-button-primary" type="button" data-media-upload>Upload</button>
                                <button class="admin-button admin-button-secondary" type="button" data-media-mkdir>New folder</button>
                                <button class="admin-button admin-button-secondary" type="button" data-media-refresh>Refresh</button>
                                <input type="file" hidden multiple data-media-file-input>
                            </div>
                            <div class="media-browser-path" data-media-breadcrumbs></div>
                        </div>
                        <div class="media-browser-notice" data-media-notice hidden></div>
                        <div class="media-browser-grid" data-media-grid></div>
                        <div class="media-browser-empty" data-media-empty hidden>This folder is empty.</div>
                    </div>
                </div>
                <footer class="media-modal-footer">
                    <button class="admin-button admin-button-secondary" type="button" data-media-close>Cancel</button>
                    <button class="admin-button admin-button-primary" type="button" data-media-confirm disabled>Select</button>
                </footer>
            </section>`;
        const title = String(options.title || 'Select media');
        modal.querySelector('[data-media-title]').textContent = title;
        modal.querySelector('.media-modal-dialog').setAttribute('aria-label', title);
        return modal;
    }

    function openPicker(options = {}) {
        return new Promise((resolve) => {
            const modal = modalMarkup(options);
            document.body.append(modal);
            const browserElement = modal.querySelector('[data-media-browser]');
            const confirmButton = modal.querySelector('[data-media-confirm]');
            const previouslyFocused = document.activeElement;
            const multiple = Boolean(options.multiple);
            let selected = multiple ? [] : null;

            new MediaBrowser(browserElement, {
                picker: true,
                root: options.root || '',
                accept: options.accept || [],
                canManage: options.canManage ?? false,
                onSelect: null,
                ...options,
                multiple
            });

            browserElement.addEventListener('media:selected', (event) => {
                selected = event.detail;
                const count = multiple
                    ? (Array.isArray(selected) ? selected.length : 0)
                    : (selected ? 1 : 0);
                confirmButton.disabled = count === 0;
                confirmButton.textContent = multiple ? `Select (${count})` : 'Select';
            });

            let keyHandler = null;
            const close = (value) => {
                if (keyHandler) document.removeEventListener('keydown', keyHandler);
                modal.remove();
                if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
                if (value && typeof options.onSelect === 'function') {
                    options.onSelect(value);
                }
                resolve(value);
            };

            modal.querySelectorAll('[data-media-close]').forEach(button => {
                button.addEventListener('click', () => close(null));
            });
            confirmButton.addEventListener('click', () => {
                if (multiple) {
                    if (Array.isArray(selected) && selected.length > 0) close(selected);
                    return;
                }
                if (selected) close(selected);
            });

            keyHandler = (event) => {
                if (event.key !== 'Escape') return;
                close(null);
            };
            document.addEventListener('keydown', keyHandler);
            modal.querySelector('[data-media-close]')?.focus();
        });
    }

    function initPageBrowsers(root = document) {
        root.querySelectorAll('[data-media-browser]').forEach(element => {
            if (element.dataset.mediaInitialized === '1') return;
            element.dataset.mediaInitialized = '1';
            new MediaBrowser(element);
        });
    }

    window.KamiMedia = Object.assign(window.KamiMedia || {}, {
        Browser: MediaBrowser,
        open: openPicker,
        init: initPageBrowsers
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initPageBrowsers());
    } else {
        initPageBrowsers();
    }
})();
