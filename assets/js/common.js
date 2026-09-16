(() => {
    'use strict';

    let activeModal = null;
    let previousFocus = null;
    const initializers = [];

    document.addEventListener('click', handleClick);
    document.addEventListener('submit', handleSubmit);
    document.addEventListener('keydown', handleKeydown);

    function handleClick(event) {
        const modalOpen = event.target.closest('[data-modal-open]');
        if (modalOpen) {
            event.preventDefault();
            openModal(modalOpen.dataset.modalOpen);
            return;
        }

        const modalClose = event.target.closest('[data-modal-close]');
        if (modalClose) {
            event.preventDefault();
            closeModal(modalClose.closest('[data-modal]'));
            return;
        }

        const authMode = event.target.closest('[data-auth-mode]');
        if (authMode) {
            event.preventDefault();
            setAuthMode(authMode.closest('[data-auth]'), authMode.dataset.authMode);
            return;
        }

        const navToggle = event.target.closest('[data-nav-toggle]');
        if (navToggle) {
            event.preventDefault();
            toggleControlledElement(navToggle, 'data-nav-menu');
            return;
        }

        const menuToggle = event.target.closest('[data-menu-toggle]');
        if (menuToggle) {
            event.preventDefault();
            toggleControlledElement(menuToggle, 'data-menu-panel');
            return;
        }

        const ajaxLink = event.target.closest('a[data-ajax-link], a[data-ajax][data-target]');
        if (ajaxLink) {
            event.preventDefault();
            submitAjaxLink(ajaxLink);
            return;
        }

        closeOpenMenus(event.target);
    }

    function handleSubmit(event) {
        const form = event.target.closest('form[data-ajax-form], form.ajax');
        if (!form) return;

        event.preventDefault();
        submitAjaxForm(form);
    }

    function handleKeydown(event) {
        if (event.key !== 'Escape') return;

        if (activeModal) {
            closeModal(activeModal);
            return;
        }

        document.querySelectorAll('[data-nav-menu]:not([hidden]), [data-menu-panel]:not([hidden])')
            .forEach(closeControlledElement);
    }

    function openModal(id) {
        const modal = document.getElementById(String(id).replace(/^#/, ''));
        if (!modal) return;

        if (activeModal && activeModal !== modal) {
            closeModal(activeModal, false);
        }

        previousFocus = document.activeElement;
        activeModal = modal;
        modal.hidden = false;
        document.body.classList.add('kc-modal-open');

        const focusTarget = modal.querySelector('[autofocus]')
            || modal.querySelector('input, button, select, textarea, a[href]');
        focusTarget?.focus();
    }

    function closeModal(modal = activeModal, restoreFocus = true) {
        if (!modal) return;

        modal.hidden = true;
        document.body.classList.remove('kc-modal-open');

        if (modal === activeModal) {
            activeModal = null;
        }

        if (restoreFocus && previousFocus instanceof HTMLElement) {
            previousFocus.focus();
        }
        previousFocus = null;
    }

    function setAuthMode(root, mode) {
        if (!root || !mode) return;

        root.querySelectorAll('[data-auth-mode]').forEach(button => {
            const selected = button.dataset.authMode === mode;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
        });

        root.querySelectorAll('[data-auth-panel]').forEach(panel => {
            panel.hidden = panel.dataset.authPanel !== mode;
        });
    }

    function toggleControlledElement(toggle, panelAttribute) {
        const root = toggle.closest('[data-site-nav], [data-menu]');
        const panel = root?.querySelector(`[${panelAttribute}]`);
        if (!panel) return;

        const willOpen = panel.hidden;
        closeOpenMenus(toggle);

        panel.hidden = !willOpen;
        toggle.setAttribute('aria-expanded', String(willOpen));
    }

    function closeControlledElement(panel) {
        panel.hidden = true;

        const root = panel.closest('[data-site-nav], [data-menu]');
        root?.querySelector('[data-nav-toggle], [data-menu-toggle]')
            ?.setAttribute('aria-expanded', 'false');
    }

    function closeOpenMenus(except) {
        document.querySelectorAll('[data-nav-menu]:not([hidden]), [data-menu-panel]:not([hidden])')
            .forEach(panel => {
                if (!panel.closest('[data-site-nav], [data-menu]')?.contains(except)) {
                    closeControlledElement(panel);
                }
            });
    }

    async function submitAjaxForm(form) {
        const action = form.getAttribute('action');
        if (!action) return;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;

        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const formData = new FormData(form);
        const request = createRequest(method, formData);
        const url = method === 'GET'
            ? appendQuery(normalizeAjaxUrl(action), new URLSearchParams(formData))
            : normalizeAjaxUrl(action);

        await performRequest({
            url,
            request,
            target: form.dataset.target,
            mode: form.dataset.method || 'replace',
            callback: form.dataset.callback,
            busyElement: form
        });
    }

    async function submitAjaxLink(link) {
        const url = link.getAttribute('href');
        if (!url) return;
        if (link.dataset.confirm && !window.confirm(link.dataset.confirm)) return;

        await performRequest({
            url: normalizeAjaxUrl(url),
            request: createRequest('GET'),
            target: link.dataset.target,
            mode: link.dataset.method || 'replace',
            callback: link.dataset.callback,
            busyElement: link
        });
    }

    function createRequest(method, formData = null) {
        const headers = {
            'X-Requested-With': 'XMLHttpRequest'
        };
        const token = localStorage.getItem('kami_token');

        if (token) {
            headers['X-Auth-Token'] = token;
        }

        const request = {
            method,
            credentials: 'same-origin',
            headers
        };

        if (method !== 'GET' && formData) {
            request.body = formData;
        }

        return request;
    }

    async function performRequest(options) {
        setBusy(options.busyElement, true);

        try {
            const response = await fetch(options.url, options.request);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.text();

            if (options.callback && typeof window[options.callback] === 'function') {
                window[options.callback](result, options.busyElement);
                return;
            }

            updateTarget(options.target, result, options.mode);
        } catch (error) {
            console.error('Ajax request failed:', error);
            notify('The request could not be completed.', 'error');
        } finally {
            setBusy(options.busyElement, false);
        }
    }

    function updateTarget(selector, html, mode) {
        if (!selector) return;

        if (selector === 'modal') {
            showModal(html);
            return;
        }

        const target = document.querySelector(selector);
        if (!target) return;

        if (mode === 'append') {
            target.insertAdjacentHTML('beforeend', html);
        } else if (mode === 'prepend') {
            target.insertAdjacentHTML('afterbegin', html);
        } else {
            target.innerHTML = html;
        }

        executeScripts(target);
        initDynamic(target);
    }

    function executeStyles(container) {
        container.querySelectorAll('link[rel="stylesheet"][href]').forEach(oldLink => {
            const href = new URL(oldLink.href, document.baseURI).href;
            const loaded = Array.from(document.querySelectorAll('link[rel="stylesheet"][href]'))
                .some(link => link !== oldLink && link.href === href);

            if (loaded) {
                oldLink.remove();
                return;
            }

            const link = document.createElement('link');
            [...oldLink.attributes].forEach(attribute => {
                link.setAttribute(attribute.name, attribute.value);
            });
            document.head.appendChild(link);
            oldLink.remove();
        });
    }

    function executeScripts(container) {
        executeStyles(container);
        container.querySelectorAll('script').forEach(oldScript => {
            if (oldScript.src && scriptAlreadyLoaded(oldScript.src, oldScript)) {
                oldScript.remove();
                return;
            }

            const script = document.createElement('script');
            [...oldScript.attributes].forEach(attribute => {
                script.setAttribute(attribute.name, attribute.value);
            });
            script.textContent = oldScript.textContent;
            script.async = false;

            document.head.appendChild(script);
            oldScript.remove();
        });
    }

    function scriptAlreadyLoaded(source, currentScript) {
        const url = new URL(source, document.baseURI).href;
        return Array.from(document.scripts).some(script => (
            script !== currentScript
            && script.src === url
        ));
    }

    function registerInitializer(initializer) {
        if (typeof initializer !== 'function' || initializers.includes(initializer)) return;
        initializers.push(initializer);
    }

    function initDynamic(root = document) {
        initializers.forEach(initializer => initializer(root));
    }

    function showModal(content) {
        let modal = document.getElementById('kami-modal');

        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'kami-modal';
            modal.className = 'kc-modal';
            modal.dataset.modal = '';
            modal.hidden = true;
            modal.innerHTML = `
                <div class="kc-modal-backdrop" data-modal-close></div>
                <div class="kc-modal-dialog" role="dialog" aria-modal="true">
                    <button class="kc-modal-close" type="button" data-modal-close aria-label="Close">&times;</button>
                    <div data-modal-content></div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        modal.querySelector('[data-modal-content]').innerHTML = content;
        executeScripts(modal);
        initDynamic(modal);
        openModal(modal.id);
    }

    function notify(message, type = 'info') {
        let region = document.getElementById('kami-notifications');

        if (!region) {
            region = document.createElement('div');
            region.id = 'kami-notifications';
            region.className = 'kc-notifications';
            region.setAttribute('aria-live', 'polite');
            document.body.appendChild(region);
        }

        const notice = document.createElement('div');
        notice.className = `kc-notice kc-notice-${type}`;
        notice.textContent = message;
        region.appendChild(notice);

        window.setTimeout(() => notice.remove(), 5000);
    }

    function setBusy(element, busy) {
        if (!element) return;

        element.setAttribute('aria-busy', String(busy));
        element.classList.toggle('is-busy', busy);

        if (element instanceof HTMLFormElement) {
            element.querySelectorAll('button[type="submit"], input[type="submit"]')
                .forEach(control => {
                    control.disabled = busy;
                });
        }
    }

    function normalizeAjaxUrl(url) {
        if (/^(?:https?:)?\/\//.test(url) || url.startsWith('/')) {
            return url;
        }

        return '/ajax/' + url.replace(/^\/+/, '');
    }

    function appendQuery(url, params) {
        const query = params.toString();
        if (!query) return url;

        return url + (url.includes('?') ? '&' : '?') + query;
    }

    window.Kami = Object.assign(window.Kami || {}, {
        closeModal,
        executeScripts,
        initDynamic,
        notify,
        openModal,
        registerInitializer,
        showModal
    });
})();

(() => {
    'use strict';

    let tooltip = null;
    let target = null;

    function show(element) {
        const text = element.dataset.tooltip;

        if (!text) {
            return;
        }

        if (
			element.closest('.admin-sidebar')
			&& !document.documentElement.classList.contains('admin-sidebar-collapsed')
		) {
			return;
		}

        target = element;

        tooltip ??= createTooltip();
        tooltip.textContent = text;
        tooltip.hidden = false;

        position();
    }

    function hide() {
        if (tooltip) {
            tooltip.hidden = true;
        }

        target = null;
    }

    function createTooltip() {
        const element = document.createElement('div');

        element.className = 'kami-tooltip';
        element.setAttribute('role', 'tooltip');
        element.hidden = true;

        document.body.appendChild(element);

        return element;
    }

    function position() {
        if (!tooltip || !target) {
            return;
        }

        const rect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();

        // tooltip.style.left =
        //     `${rect.left + rect.width / 2 - tooltipRect.width / 2}px`;
        //
        // tooltip.style.top =
        //     `${rect.top - tooltipRect.height - 8}px`;

		tooltip.style.left = `${rect.left + 20}px`;
		tooltip.style.top = `${rect.top + 20}px`;
    }

    document.addEventListener('mouseover', event => {
        const element = event.target.closest('[data-tooltip]');

		//console.log('1');

        if (element) {
            show(element);
        }
    });

    document.addEventListener('mouseout', event => {
        if (
            target
            && !target.contains(event.relatedTarget)
        ) {
            hide();
        }
    });

    document.addEventListener('focusin', event => {
        const element = event.target.closest('[data-tooltip]');

        if (element) {
            show(element);
        }
    });

    document.addEventListener('focusout', hide);

    window.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
})();
