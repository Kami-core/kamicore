<!-- kami:template page_edit -->
<form id="page-form"
      class="pm-page-editor"
      method="post"
      action="{{save_action}}"
      data-layout-url="{{layout_data_url}}">
    <input type="hidden" name="page_id" value="{{page_id}}">
    <input type="hidden" id="page-layout" name="layout_json" value="[]">

    <section class="admin-panel admin-pad pm-editor-meta" aria-labelledby="pm-editor-title">
        <div class="pm-editor-heading">
            <div>
                <a class="admin-back-link" href="{{back_url}}">
                    <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                    <span>{{back_label}}</span>
                </a>
                <div>
                    <h2 id="pm-editor-title">{{phrase.edit_page}}</h2>
                    <span id="pm-layout-badge" class="pm-layout-badge">{{layout_title}}</span>
                </div>
            </div>
            <span id="pm-builder-status" class="pm-builder-status" aria-live="polite">
                {{phrase.loading_layout}}
            </span>
        </div>

        <div class="pm-editor-fields pm-editor-main-fields">
            <div class="pm-field">
                <label class="pm-label" for="page-title">{{phrase.title}}</label>
                <input class="admin-input"
                       id="page-title"
                       name="page_title"
                       type="text"
                       value="{{page_title}}"
                       required>
            </div>
            <div class="pm-field">
                <label class="pm-label" for="page-slug">{{phrase.slug}}</label>
                <input class="admin-input"
                       id="page-slug"
                       name="page_slug"
                       type="text"
                       value="{{page_slug}}"
                       required>
            </div>
            <div class="pm-field">
                <label class="pm-label" for="page-layout-id">{{phrase.layout}}</label>
                {{layout_field}}
            </div>
            <div class="pm-editor-parent-field">
                {{parent_field}}
            </div>
        </div>

        <div class="pm-editor-fields">
            <div class="pm-field">
                {{lifecycle_enable_field}}
            </div>
            <div class="pm-field">
                {{lifecycle_disable_field}}
            </div>
        </div>
        <p class="admin-page-description">{{lifecycle_plugins_help}}</p>
    </section>

    <div class="pm-builder">
        <aside class="admin-panel admin-panel-raised pm-plugin-palette-panel">
            <div class="pm-panel-heading">
                <h3>{{phrase.available_plugins}}</h3>
                <p>{{phrase.drag_plugins_help}}</p>
            </div>
            <ul id="plugin-palette" class="pm-plugin-palette">
                {{plugins}}
            </ul>
        </aside>

        <main class="admin-panel admin-panel-raised pm-layout-panel" aria-labelledby="pm-layout-preview-title">
            <div class="pm-panel-heading pm-layout-panel-heading">
                <div>
                    <h3 id="pm-layout-preview-title">{{phrase.layout_preview}}</h3>
                    <p>{{phrase.layout_preview_help}}</p>
                </div>
                <button id="pm-layout-retry"
                        class="admin-button admin-button-secondary"
                        type="button"
                        hidden>
                    <svg class="icon icon-refresh-cw icon-sm"></svg>
                    <span>{{phrase.retry}}</span>
                </button>
            </div>

            <div id="pm-layout-preview-root">
                {{layout_preview}}
            </div>

            <section id="pm-unplaced-section" class="pm-unplaced-section" hidden>
                <h3>{{phrase.unplaced_wrappers}}</h3>
                <p>{{phrase.unplaced_wrappers_help}}</p>
                <div id="pm-unplaced-zones" class="pm-layout-preview pm-layout-preview-fallback"></div>
            </section>
        </main>
    </div>

    <footer class="admin-form-actions pm-editor-actions">
        <a class="admin-button admin-button-secondary"
           href="{{recipe_from_page_url}}"{{recipe_from_page_hidden}}
           title="{{recipe_from_page_hint}}">
            <svg class="icon icon-copy icon-sm" aria-hidden="true"></svg>
            <span>{{recipe_from_page_label}}</span>
        </a>
        <a class="admin-button admin-button-secondary" href="{{cancel_url}}">
            {{phrase.cancel}}
        </a>
        <button id="pm-save-page"
                class="admin-button admin-button-primary"
                type="submit"
                disabled>
            <svg class="icon icon-save icon-sm"></svg>
            <span>{{phrase.save_page}}</span>
        </button>
    </footer>
</form>

<script>
(function() {
    'use strict';

    const text = {{builder_text}};
    const pageForm = document.getElementById('page-form');
    const palette = document.getElementById('plugin-palette');
    const layoutField = document.getElementById('page-layout');
    const layoutSelect = document.getElementById('page-layout-id');
    const layoutBadge = document.getElementById('pm-layout-badge');
    const statusLabel = document.getElementById('pm-builder-status');
    const retryButton = document.getElementById('pm-layout-retry');
    const saveButton = document.getElementById('pm-save-page');
    const previewRoot = document.getElementById('pm-layout-preview-root');
    const unplacedSection = document.getElementById('pm-unplaced-section');
    const unplacedZones = document.getElementById('pm-unplaced-zones');

    if (!pageForm || !palette || !layoutField || !previewRoot) {
        console.warn('Page layout builder: required elements not found.');
        return;
    }

    const dragMime = 'application/x-kami-page-plugin';
    let dragState = null;
    let pluginInstanceCounter = 0;

    function createIcon(name) {
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.classList.add('icon', 'icon-' + name, 'icon-sm');
        return icon;
    }

    function setStatus(message) {
        statusLabel.textContent = message;
    }

    function setDragState(state, event) {
        dragState = state;
        pageForm.classList.add('pm-builder-is-dragging');

        const serialized = JSON.stringify({
            source: state.source,
            plugin: state.plugin || '',
            title: state.title || '',
            instanceId: state.instanceId || ''
        });

        event.dataTransfer.effectAllowed = state.source === 'palette' ? 'copy' : 'move';
        event.dataTransfer.setData(dragMime, serialized);
        event.dataTransfer.setData('text/plain', serialized);
    }

    function readDragState(event) {
        if (dragState) {
            return dragState;
        }

        for (const type of [dragMime, 'text/plain']) {
            try {
                const raw = event.dataTransfer.getData(type);
                if (!raw) {
                    continue;
                }

                const payload = JSON.parse(raw);
                if (payload.source === 'palette') {
                    return {
                        source: 'palette',
                        plugin: payload.plugin || '',
                        title: payload.title || payload.plugin || ''
                    };
                }

                if (payload.source === 'instance' && payload.instanceId) {
                    const element = pageForm.querySelector(
                        '.plugin-instance[data-instance-id="' + CSS.escape(payload.instanceId) + '"]'
                    );
                    if (element) {
                        return {
                            source: 'instance',
                            instanceId: payload.instanceId,
                            element
                        };
                    }
                }
            } catch (error) {
                // Ignore unrelated external drag data.
            }
        }

        return null;
    }

    function clearDragState() {
        pageForm.classList.remove('pm-builder-is-dragging');
        pageForm.querySelectorAll('.plugin-dropzone--over').forEach(dropzone => {
            dropzone.classList.remove('plugin-dropzone--over');
        });
        pageForm.querySelectorAll('.plugin-instance--dragging').forEach(instance => {
            instance.classList.remove('plugin-instance--dragging');
        });
        palette.querySelectorAll('.pm-plugin-palette-item--dragging').forEach(item => {
            item.classList.remove('pm-plugin-palette-item--dragging');
        });
        dragState = null;
    }

    function getWrapperName(dropzone) {
        return dropzone.dataset.wrapper || '';
    }

    function createPlaceholder() {
        const placeholder = document.createElement('div');
        placeholder.className = 'plugin-dropzone-empty';
        placeholder.append(createIcon('plus'), document.createTextNode(text.dragHere));
        return placeholder;
    }

    function ensureDropzonePlaceholder(dropzone) {
        const hasInstances = dropzone.querySelector('.plugin-instance') !== null;
        const placeholder = dropzone.querySelector('.plugin-dropzone-empty');

        if (!hasInstances && !placeholder) {
            dropzone.appendChild(createPlaceholder());
        } else if (hasInstances && placeholder) {
            placeholder.remove();
        }
    }

    function applyWrapperMeta(dropzone, wrapper) {
        const zone = dropzone.closest('.pm-layout-zone');
        if (!zone) {
            return;
        }

        const title = zone.querySelector('[data-wrapper-title]');
        const description = zone.querySelector('[data-wrapper-description]');

        if (title) {
            title.textContent = wrapper.title || wrapper.name;
        }
        if (description) {
            description.textContent = wrapper.description || '';
            description.hidden = !wrapper.description;
        }

        zone.classList.toggle('pm-layout-zone-unknown', wrapper.known === false);
    }

    function updateLayoutState() {
        const layout = [];

        document.querySelectorAll('.plugin-dropzone[data-wrapper]').forEach(dropzone => {
            const wrapperName = getWrapperName(dropzone);
            if (!wrapperName) {
                return;
            }

            const items = Array.from(
                dropzone.querySelectorAll(':scope > .plugin-instance')
            ).map(instance => ({
                plugin: instance.dataset.plugin || '',
                instance_id: instance.dataset.instanceId || '',
                wrapper: wrapperName
            }));

            layout.push({
                wrapper: wrapperName,
                items
            });
        });

        layoutField.value = JSON.stringify(layout);
    }

    function togglePluginSettings(instance, forceOpen = null) {
        const config = instance.querySelector('.pm-plugin-config');
        const toggle = instance.querySelector('.pm-plugin-settings-toggle');
        if (!config || !toggle) {
            return;
        }

        const shouldOpen = forceOpen === null ? config.hidden : forceOpen;
        config.hidden = !shouldOpen;
        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

        if (shouldOpen) {
            window.Kami?.initDynamic?.(config);
        }
    }

    function attachInstanceEvents(instance) {
        if (instance.dataset.builderReady === '1') {
            return;
        }

        instance.dataset.builderReady = '1';
        instance.draggable = false;

        const dragHandle = instance.querySelector('.pm-plugin-drag-handle');
        if (dragHandle) {
            dragHandle.draggable = true;
            dragHandle.addEventListener('dragstart', event => {
                event.stopPropagation();
                setDragState({
                    source: 'instance',
                    instanceId: instance.dataset.instanceId || '',
                    element: instance
                }, event);
                instance.classList.add('plugin-instance--dragging');
            });
            dragHandle.addEventListener('dragend', clearDragState);
        }

        instance.querySelector('.pm-plugin-settings-toggle')
            ?.addEventListener('click', () => togglePluginSettings(instance));

        instance.querySelector('.plugin-remove')
            ?.addEventListener('click', () => {
                const dropzone = instance.closest('.plugin-dropzone');
                instance.remove();
                if (dropzone) {
                    ensureDropzonePlaceholder(dropzone);
                }
                updateLayoutState();
            });
    }

    function generateInstanceId() {
        pluginInstanceCounter++;
        return 'plugin_' + pluginInstanceCounter;
    }

    function createPluginInstance(pluginName, pluginTitle, wrapperName) {
        const instanceId = generateInstanceId();
        const instance = document.createElement('article');
        instance.className = 'pm-plugin-instance plugin-instance';
        instance.dataset.plugin = pluginName;
        instance.dataset.instanceId = instanceId;
        instance.dataset.wrapper = wrapperName;

        const header = document.createElement('header');
        header.className = 'pm-plugin-header';

        const dragHandle = document.createElement('span');
        dragHandle.className = 'pm-plugin-drag-handle';
        dragHandle.title = text.movePlugin;
        dragHandle.setAttribute('role', 'button');
        dragHandle.setAttribute('tabindex', '0');
        dragHandle.setAttribute('aria-label', text.movePlugin);
        dragHandle.setAttribute('draggable', 'true');
        dragHandle.appendChild(createIcon('plug'));

        const identity = document.createElement('div');
        identity.className = 'pm-plugin-identity';
        const title = document.createElement('strong');
        title.textContent = pluginTitle;
        const name = document.createElement('span');
        name.textContent = pluginName;
        identity.append(title, name);

        const actions = document.createElement('div');
        actions.className = 'pm-plugin-actions';

        const settings = document.createElement('button');
        settings.className = 'admin-action-button admin-action-button-small pm-plugin-settings-toggle';
        settings.type = 'button';
        settings.title = text.pluginSettings;
        settings.setAttribute('aria-label', text.pluginSettings);
        settings.setAttribute('aria-expanded', 'false');
        settings.appendChild(createIcon('settings'));

        const remove = document.createElement('button');
        remove.className = 'admin-action-button admin-action-button-small admin-action-danger plugin-remove';
        remove.type = 'button';
        remove.title = text.removePlugin;
        remove.setAttribute('aria-label', text.removePlugin);
        remove.appendChild(createIcon('trash'));

        actions.append(settings, remove);
        header.append(dragHandle, identity, actions);

        const config = document.createElement('div');
        config.className = 'pm-plugin-config plugin-config';
        config.hidden = true;
        config.dataset.loaded = '0';
        config.textContent = text.loadingPluginSettings;

        instance.append(header, config);
        attachInstanceEvents(instance);
        loadPluginConfig(instance, pluginName, instanceId, wrapperName);

        return instance;
    }

    async function loadPluginConfig(instance, pluginName, instanceId, wrapperName, handlerName = '') {
        const config = instance.querySelector('.plugin-config');
        const formData = new FormData();
        formData.append('plugin', pluginName);
        formData.append('instance_id', instanceId);
        formData.append('wrapper', wrapperName);
        if (handlerName) {
            formData.append('handler', handlerName);
        }
        formData.append(
            'page_id',
            document.querySelector('input[name="page_id"]')?.value || ''
        );

        try {
            const response = await fetch('/ajax/PageManager/pluginContextForm', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            config.innerHTML = await response.text();
            config.dataset.loaded = '1';
            window.Kami?.executeScripts?.(config);
            window.Kami?.initDynamic?.(config);
        } catch (error) {
            console.error('Page Manager: failed to load plugin settings.', error);
            config.innerHTML = '';
            const notice = document.createElement('div');
            notice.className = 'kc-notice kc-notice-error';
            notice.textContent = text.pluginSettingsFailed;
            config.appendChild(notice);
            togglePluginSettings(instance, true);
        }
    }

    function findInsertBefore(dropzone, clientY) {
        const instances = Array.from(
            dropzone.querySelectorAll(':scope > .plugin-instance:not(.plugin-instance--dragging)')
        );

        return instances.reduce((closest, instance) => {
            const box = instance.getBoundingClientRect();
            const offset = clientY - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset, element: instance };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    function attachDropzoneEvents(dropzone) {
        if (dropzone.dataset.dropzoneReady === '1') {
            return;
        }

        dropzone.dataset.dropzoneReady = '1';

        dropzone.addEventListener('dragenter', event => {
            if (!dragState) {
                return;
            }
            event.preventDefault();
            dropzone.classList.add('plugin-dropzone--over');
        });

        dropzone.addEventListener('dragover', event => {
            if (!dragState) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = dragState.source === 'palette' ? 'copy' : 'move';
            dropzone.classList.add('plugin-dropzone--over');
        });

        dropzone.addEventListener('dragleave', event => {
            if (!dropzone.contains(event.relatedTarget)) {
                dropzone.classList.remove('plugin-dropzone--over');
            }
        });

        dropzone.addEventListener('drop', event => {
            event.preventDefault();
            dropzone.classList.remove('plugin-dropzone--over');

            const state = readDragState(event);
            if (!state) {
                return;
            }

            const wrapperName = getWrapperName(dropzone);
            const insertBefore = findInsertBefore(dropzone, event.clientY);
            let instance;
            let previousDropzone = null;

            if (state.source === 'palette') {
                if (!state.plugin) {
                    clearDragState();
                    return;
                }
                instance = createPluginInstance(
                    state.plugin,
                    state.title || state.plugin,
                    wrapperName
                );
            } else {
                instance = state.element;
                if (!instance) {
                    clearDragState();
                    return;
                }
                previousDropzone = instance.closest('.plugin-dropzone');
                instance.dataset.wrapper = wrapperName;
            }

            if (insertBefore) {
                dropzone.insertBefore(instance, insertBefore);
            } else {
                dropzone.appendChild(instance);
            }

            ensureDropzonePlaceholder(dropzone);
            if (previousDropzone && previousDropzone !== dropzone) {
                ensureDropzonePlaceholder(previousDropzone);
            }
            updateLayoutState();
            clearDragState();
        });
    }

    function hydrateDropzone(dropzone, wrapper) {
        applyWrapperMeta(dropzone, wrapper);
        dropzone.innerHTML = wrapper.plugins_html || '';
        dropzone.querySelectorAll('.plugin-instance').forEach(attachInstanceEvents);
        attachDropzoneEvents(dropzone);
        ensureDropzonePlaceholder(dropzone);
        window.Kami?.executeScripts?.(dropzone);
        window.Kami?.initDynamic?.(dropzone);
    }

    function createFallbackZone(wrapper) {
        const zone = document.createElement('section');
        zone.className = 'pm-layout-zone pm-layout-zone-unplaced';

        const header = document.createElement('header');
        header.className = 'pm-zone-header';
        const title = document.createElement('strong');
        title.dataset.wrapperTitle = '';
        const description = document.createElement('span');
        description.dataset.wrapperDescription = '';
        header.append(title, description);

        const dropzone = document.createElement('div');
        dropzone.className = 'plugin-dropzone';
        dropzone.dataset.wrapper = wrapper.name;

        zone.append(header, dropzone);
        unplacedZones.appendChild(zone);
        hydrateDropzone(dropzone, wrapper);
    }

    async function loadLayout() {
        retryButton.hidden = true;
        saveButton.disabled = true;
        setStatus(text.loadingLayout);
        unplacedZones.replaceChildren();
        unplacedSection.hidden = true;

        try {
            let layoutUrl = pageForm.dataset.layoutUrl;
            if (layoutSelect?.value) {
                layoutUrl += '/pgm-layoutId/' + encodeURIComponent(layoutSelect.value);
            }

            const response = await fetch(layoutUrl, {
                credentials: 'same-origin'
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            if (data.status !== 'ok') {
                throw new Error(data.error || 'Unknown error');
            }

            if (typeof data.layout_preview === 'string') {
                previewRoot.innerHTML = data.layout_preview;
            }

            if (layoutBadge && layoutSelect?.selectedOptions[0]) {
                layoutBadge.textContent = layoutSelect.selectedOptions[0].textContent || '';
            }

            pluginInstanceCounter = Number(data.last_instance || 0);
            const wrappers = data.wrappers || {};
            const placed = new Set();

            previewRoot.querySelectorAll('.plugin-dropzone[data-wrapper]').forEach(dropzone => {
                const wrapperName = getWrapperName(dropzone);
                const wrapper = wrappers[wrapperName] || {
                    name: wrapperName,
                    title: wrapperName,
                    description: text.unknownWrapper,
                    known: false,
                    plugins_html: ''
                };
                placed.add(wrapperName);
                hydrateDropzone(dropzone, wrapper);
            });

            Object.values(wrappers).forEach(wrapper => {
                if (!placed.has(wrapper.name)) {
                    createFallbackZone(wrapper);
                }
            });

            unplacedSection.hidden = unplacedZones.children.length === 0;
            updateLayoutState();
            setStatus(text.layoutLoaded);
            saveButton.disabled = false;
        } catch (error) {
            console.error('Page Manager: failed to load layout.', error);
            setStatus(text.layoutFailed);
            retryButton.hidden = false;
        }
    }

    layoutSelect?.addEventListener('change', () => {
        loadLayout();
    });

    palette.addEventListener('dragstart', event => {
        const item = event.target.closest('.pm-plugin-palette-item');
        if (!item) {
            return;
        }

        setDragState({
            source: 'palette',
            plugin: item.dataset.plugin || '',
            title: item.dataset.label || item.dataset.plugin || '',
            element: item
        }, event);
        item.classList.add('pm-plugin-palette-item--dragging');
    });

    palette.addEventListener('dragend', clearDragState);
    document.addEventListener('drop', clearDragState);

    retryButton.addEventListener('click', loadLayout);
    pageForm.addEventListener('change', event => {
        const handlerSelect = event.target.closest('select[name^="plugin_handler["]');
        if (!handlerSelect) {
            return;
        }

        const instance = handlerSelect.closest('.plugin-instance');
        if (!instance) {
            return;
        }

        loadPluginConfig(
            instance,
            instance.dataset.plugin || '',
            instance.dataset.instanceId || '',
            instance.dataset.wrapper || '',
            handlerSelect.value
        );
    });
    pageForm.addEventListener('submit', updateLayoutState);
    loadLayout();
})();
</script>
<!-- /kami:template -->

<!-- kami:template pages -->
<section id="domain-pages-block" class="admin-panel" aria-labelledby="pm-pages-title">
    <header class="admin-page-header">
        <h2 id="pm-pages-title" class="admin-page-title">{{phrase.pages}}</h2>
        <div class="pm-page-header-actions">
            {{recipe_tools}}
            <span id="domain-pages-status" class="admin-page-status" aria-live="polite">
                {{phrase.select_domain_to_view_pages}}
            </span>
        </div>
    </header>

    <div class="admin-toolbar admin-toolbar-end">
        <div class="pm-domain-field">
            <label class="pm-label" for="domain-select">{{phrase.domain}}</label>
            <div class="pm-domain-controls">
                {{domain_select}}
            </div>
        </div>

        <div class="pm-create-buttons">
            <button id="page-recipe-create-toggle"
                    class="admin-button admin-button-secondary"
                    type="button"
                    aria-controls="page-recipe-create-wrapper"
                    aria-expanded="false"
                    disabled{{recipe_create_disabled}}>
                <svg class="icon icon-layers icon-sm"></svg>
                <span>{{phrase.create_from_recipe}}</span>
            </button>
            <button id="page-create-toggle"
                    class="admin-button admin-button-primary"
                    type="button"
                    aria-controls="page-create-wrapper"
                    aria-expanded="false"
                    disabled>
                <svg class="icon icon-plus icon-sm"></svg>
                <span>{{phrase.add_page}}</span>
            </button>
        </div>
    </div>

    <div id="page-create-wrapper" class="pm-create-panel" hidden>
        <form action="{{create_action}}"
              id="page-create-form"
              method="post">
            <input type="hidden" name="domain_id" id="page-create-domain-id" value="">

            <h3 class="pm-create-title">
                {{phrase.new_page_for}} <strong id="page-create-domain-name"></strong>
            </h3>

            <div class="pm-form-grid">
                <div class="pm-field">
                    <label class="pm-label" for="page-create-title">{{phrase.title}}</label>
                    <input class="admin-input"
                           type="text"
                           id="page-create-title"
                           name="title"
                           required>
                </div>

                <div class="pm-field">
                    <label class="pm-label" for="page-create-slug">{{phrase.slug}}</label>
                    <input class="admin-input"
                           type="text"
                           id="page-create-slug"
                           name="slug"
                           placeholder="/about"
                           required>
                </div>

                <div class="pm-field">
                    <label class="pm-label" for="layout_id">{{phrase.layout}}</label>
                    <div id="layout-container"></div>
                </div>

                <div id="page-parent-container" class="pm-parent-field"></div>
            </div>

            <div class="admin-form-actions">
                <button id="page-create-cancel"
                        class="admin-button admin-button-secondary"
                        type="button">
                    {{phrase.cancel}}
                </button>
                <button class="admin-button admin-button-primary" type="submit">
                    {{phrase.create_page}}
                </button>
            </div>
        </form>
    </div>

    <div id="page-recipe-create-wrapper" class="pm-create-panel" hidden>
        <form action="{{recipe_create_action}}"
              id="page-recipe-create-form"
              method="post">
            <input type="hidden" name="domain_id" id="page-recipe-domain-id" value="">

            <h3 class="pm-create-title">
                {{phrase.create_from_recipe_for}} <strong id="page-recipe-domain-name"></strong>
            </h3>

            <div class="pm-form-grid pm-recipe-create-grid">
                <div class="pm-field">
                    <label class="pm-label" for="page-recipe-key">{{phrase.recipe}}</label>
                    {{recipe_select}}
                    <div id="page-recipe-meta" class="pm-field-hint"></div>
                </div>

                <div class="pm-field">
                    <label class="pm-label" for="page-recipe-title">{{phrase.title}}</label>
                    <input class="admin-input"
                           type="text"
                           id="page-recipe-title"
                           name="title"
                           required>
                </div>

                <div class="pm-field">
                    <label class="pm-label" for="page-recipe-slug">{{phrase.slug}}</label>
                    <input class="admin-input"
                           type="text"
                           id="page-recipe-slug"
                           name="slug"
                           placeholder="about"
                           required>
                </div>
            </div>

            <div class="admin-form-actions">
                <button id="page-recipe-create-cancel"
                        class="admin-button admin-button-secondary"
                        type="button">
                    {{phrase.cancel}}
                </button>
                <button class="admin-button admin-button-primary" type="submit">
                    {{phrase.create_page}}
                </button>
            </div>
        </form>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table admin-table-wider">
            <thead>
                <tr>
                    <th>{{phrase.title}}</th>
                    <th>{{phrase.slug}}</th>
                    <th>{{phrase.layout}}</th>
                    <th class="admin-actions-heading">{{phrase.actions}}</th>
                </tr>
            </thead>
            <tbody id="domain-pages-list">
                <tr class="pm-state-row">
                    <td colspan="4" class="admin-empty-state">
                        {{phrase.no_domain_selected}}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<script>
(function() {
    'use strict';

    const text = {{ui_text}};
    const pageRoute = {{page_route}};
    const domainSelect = document.getElementById('domain-select');
    const pagesTbody = document.getElementById('domain-pages-list');
    const statusLabel = document.getElementById('domain-pages-status');
    const createToggle = document.getElementById('page-create-toggle');
    const createWrapper = document.getElementById('page-create-wrapper');
    const createForm = document.getElementById('page-create-form');
    const createCancel = document.getElementById('page-create-cancel');
    const createDomainInput = document.getElementById('page-create-domain-id');
    const createDomainName = document.getElementById('page-create-domain-name');
    const createTitleInput = document.getElementById('page-create-title');
    const layoutContainer = document.getElementById('layout-container');
    const parentContainer = document.getElementById('page-parent-container');
    const recipeMeta = {{recipe_meta}};
    const canCreateRecipe = {{can_create_recipe}};
    const recipeCreateToggle = document.getElementById('page-recipe-create-toggle');
    const recipeCreateWrapper = document.getElementById('page-recipe-create-wrapper');
    const recipeCreateForm = document.getElementById('page-recipe-create-form');
    const recipeCreateCancel = document.getElementById('page-recipe-create-cancel');
    const recipeDomainInput = document.getElementById('page-recipe-domain-id');
    const recipeDomainName = document.getElementById('page-recipe-domain-name');
    const recipeSelect = document.getElementById('page-recipe-key');
    const recipeTitleInput = document.getElementById('page-recipe-title');
    const recipeMetaLabel = document.getElementById('page-recipe-meta');
    const pluralRules = new Intl.PluralRules(document.documentElement.lang || 'en');

    let currentPageCount = 0;
    let loadController = null;

    if (!domainSelect || !pagesTbody || !statusLabel || !createToggle) {
        console.warn('Page Manager: required elements not found.');
        return;
    }

    function format(pattern, values = {}) {
        return Object.entries(values).reduce(
            (result, [key, value]) => result.replaceAll(`{${key}}`, String(value)),
            pattern
        );
    }

    function createIcon(name) {
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.classList.add('icon', `icon-${name}`, 'icon-sm');
        return icon;
    }

    function getDomainIdFromUrl() {
        const pathname = window.location.pathname.replace(/\/+$/, '') || '/';
        const domainPrefix = pageRoute + '/pgm-domainId/';

        if (!pathname.startsWith(domainPrefix)) {
            return '';
        }

        const encodedDomainId = pathname.slice(domainPrefix.length);
        if (!encodedDomainId || encodedDomainId.includes('/')) {
            return '';
        }

        try {
            return decodeURIComponent(encodedDomainId);
        } catch (error) {
            return '';
        }
    }

    function hasDomainOption(domainId) {
        return Array.from(domainSelect.options).some(option => option.value === domainId);
    }

    function setDomainSelectValue(domainId) {
        domainSelect.value = domainId;

        if (domainSelect.tomselect) {
            domainSelect.tomselect.setValue(domainId, true);
        }
    }

    function getSelectedDomainName() {
        return domainSelect.selectedOptions[0]?.textContent.trim() || '';
    }

    function updateDomainUrl(domainId) {
        const path = domainId
            ? pageRoute + '/pgm-domainId/' + encodeURIComponent(domainId)
            : pageRoute;

        if (window.location.pathname !== path) {
            window.history.pushState({ domainId: domainId || null }, '', path);
        }
    }

    function setStatus(message) {
        statusLabel.textContent = message;
    }

    function setPageCount(count) {
        currentPageCount = count;
        const category = pluralRules.select(count);
        const pattern = text.pageCount[category] || text.pageCount.other;
        setStatus(format(pattern, { count }));
    }

    function setCreateAvailable(available) {
        createToggle.disabled = !available;
        if (recipeCreateToggle) {
            recipeCreateToggle.disabled = !available || Object.keys(recipeMeta).length === 0;
        }
        if (!available) {
            closeCreateForm();
            closeRecipeCreateForm();
        }
    }

    function openCreateForm() {
        if (createToggle.disabled || !domainSelect.value) {
            return;
        }

        closeRecipeCreateForm();
        createDomainInput.value = domainSelect.value;
        createDomainName.textContent = getSelectedDomainName();
        createWrapper.hidden = false;
        createToggle.setAttribute('aria-expanded', 'true');
        createTitleInput.focus();
    }

    function closeCreateForm(reset = true) {
        createWrapper.hidden = true;
        createToggle.setAttribute('aria-expanded', 'false');

        if (reset && createForm) {
            createForm.reset();
        }

        createDomainInput.value = domainSelect.value || '';
        createDomainName.textContent = getSelectedDomainName();
    }

    function updateRecipeMeta() {
        if (!recipeSelect || !recipeMetaLabel) return;
        const data = recipeMeta[recipeSelect.value];
        if (!data) {
            recipeMetaLabel.textContent = '';
            return;
        }
        const prefix = data.page_prefix || '';
        recipeMetaLabel.textContent = prefix
            ? format(text.recipePrefix, { prefix }) + ' · ' + data.layout
            : text.recipeNoPrefix + ' · ' + data.layout;
    }

    function openRecipeCreateForm() {
        if (!recipeCreateToggle || recipeCreateToggle.disabled || !domainSelect.value) return;
        closeCreateForm();
        recipeDomainInput.value = domainSelect.value;
        recipeDomainName.textContent = getSelectedDomainName();
        recipeCreateWrapper.hidden = false;
        recipeCreateToggle.setAttribute('aria-expanded', 'true');
        updateRecipeMeta();
        recipeSelect?.focus();
    }

    function closeRecipeCreateForm(reset = true) {
        if (!recipeCreateWrapper || !recipeCreateToggle) return;
        recipeCreateWrapper.hidden = true;
        recipeCreateToggle.setAttribute('aria-expanded', 'false');
        if (reset && recipeCreateForm) recipeCreateForm.reset();
        if (recipeDomainInput) recipeDomainInput.value = domainSelect.value || '';
        if (recipeDomainName) recipeDomainName.textContent = getSelectedDomainName();
        updateRecipeMeta();
    }

    function clearPagesTable(message, retry = false) {
        pagesTbody.replaceChildren();

        const row = document.createElement('tr');
        row.className = 'pm-state-row';
        const cell = document.createElement('td');
        cell.colSpan = 4;
        cell.className = 'admin-empty-state';

        const messageElement = document.createElement('span');
        messageElement.textContent = message;
        cell.appendChild(messageElement);

        if (retry) {
            const retryButton = document.createElement('button');
            retryButton.className = 'pm-retry';
            retryButton.type = 'button';
            retryButton.append(createIcon('refresh-cw'), document.createTextNode(text.retry));
            retryButton.addEventListener('click', () => loadDomain(domainSelect.value));
            cell.appendChild(retryButton);
        }

        row.appendChild(cell);
        pagesTbody.appendChild(row);
    }

    function createEditUrl(pageId) {
        return pageRoute + '/pgm-action/edit/pgm-pageId/' + encodeURIComponent(pageId);
    }

    async function deletePage(page, row, button) {
        const confirmed = window.confirm(
            format(text.deleteConfirm, { title: page.title || page.id })
        );

        if (!confirmed) {
            return;
        }

        button.disabled = true;

        try {
            const response = await fetch(
                '/ajax/PageManager/deletePage/pgm-id/' + encodeURIComponent(page.id),
                { credentials: 'same-origin' }
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            await loadDomain(domainSelect.value);
        } catch (error) {
            console.error('Page Manager: failed to delete page.', error);
            setStatus(text.deleteFailed);
            button.disabled = false;
        }
    }

    function renderPages(pages) {
        pagesTbody.replaceChildren();

        if (!pages.length) {
            clearPagesTable(text.noPages);
            return;
        }

        pages.forEach(page => {
            const row = document.createElement('tr');

            const titleCell = document.createElement('td');
            const titleLink = document.createElement('a');
            titleLink.className = 'pm-page-link';
            titleLink.href = createEditUrl(page.id);
            titleLink.textContent = page.title || '';
            titleLink.style.setProperty(
                '--pm-page-depth',
                String(Math.max(0, Number(page.depth) || 0))
            );
            titleCell.appendChild(titleLink);

            const slugCell = document.createElement('td');
            slugCell.className = 'pm-page-slug';
            slugCell.textContent = page.slug || '';

            const layoutCell = document.createElement('td');
            layoutCell.textContent = page.layout || '';

            const actionsCell = document.createElement('td');
            actionsCell.className = 'admin-actions-cell';
            const actions = document.createElement('div');
            actions.className = 'admin-actions';

            const editLink = document.createElement('a');
            editLink.className = 'admin-action-button';
            editLink.href = createEditUrl(page.id);
            editLink.title = text.editPage;
            editLink.setAttribute('aria-label', text.editPage);
            editLink.appendChild(createIcon('pencil'));

            let recipeLink = null;
            if (canCreateRecipe) {
                recipeLink = document.createElement('a');
                recipeLink.className = 'admin-action-button';
                recipeLink.href = pageRoute
                    + '/pgm-action/recipes/pgm-pageId/'
                    + encodeURIComponent(page.id);
                recipeLink.title = text.createRecipeFromPage;
                recipeLink.setAttribute('aria-label', text.createRecipeFromPage);
                recipeLink.appendChild(createIcon('copy'));
            }

            const deleteButton = document.createElement('button');
            deleteButton.className = 'admin-action-button admin-action-danger';
            deleteButton.type = 'button';
            deleteButton.title = text.deletePage;
            deleteButton.setAttribute('aria-label', text.deletePage);
            deleteButton.appendChild(createIcon('trash'));
            deleteButton.addEventListener('click', () => {
                deletePage(page, row, deleteButton);
            });

            actions.append(editLink);
            if (recipeLink) actions.append(recipeLink);
            actions.append(deleteButton);
            actionsCell.appendChild(actions);
            row.append(titleCell, slugCell, layoutCell, actionsCell);
            pagesTbody.appendChild(row);
        });
    }

    async function loadDomain(domainId) {
        if (loadController) {
            loadController.abort();
        }

        closeCreateForm();
        closeRecipeCreateForm();
        setCreateAvailable(false);

        if (!domainId) {
            setStatus(text.selectDomain);
            clearPagesTable(text.noDomain);
            return;
        }

        loadController = new AbortController();
        setStatus(text.loading);
        clearPagesTable(text.loading);

        const formData = new FormData();
        formData.append('domain_id', domainId);

        try {
            const response = await fetch('/ajax/PageManager/domainPages', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                signal: loadController.signal
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            if (data.status !== 'ok') {
                throw new Error(data.error || 'Unknown error');
            }

            const pages = data.pages || [];
            renderPages(pages);
            layoutContainer.innerHTML = data.layouts || '';
            if (parentContainer) {
                parentContainer.innerHTML = data.parent_field || '';
                window.Kami?.initDynamic?.(parentContainer);
            }
            setPageCount(pages.length);
            setCreateAvailable(true);
            createDomainInput.value = domainId;
            createDomainName.textContent = getSelectedDomainName();
            if (recipeDomainInput) recipeDomainInput.value = domainId;
            if (recipeDomainName) recipeDomainName.textContent = getSelectedDomainName();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error('Page Manager: failed to load pages.', error);
            setStatus(text.loadFailed);
            clearPagesTable(text.loadFailed, true);
        }
    }

    domainSelect.addEventListener('change', function() {
        updateDomainUrl(this.value);
        loadDomain(this.value);
    });

    createToggle.addEventListener('click', openCreateForm);
    createCancel.addEventListener('click', () => closeCreateForm());
    recipeCreateToggle?.addEventListener('click', openRecipeCreateForm);
    recipeCreateCancel?.addEventListener('click', () => closeRecipeCreateForm());
    recipeSelect?.addEventListener('change', updateRecipeMeta);

    createForm.addEventListener('submit', function(event) {
        if (!createDomainInput.value) {
            event.preventDefault();
            closeCreateForm();
        }
    });

    recipeCreateForm?.addEventListener('submit', function(event) {
        if (!recipeDomainInput.value || !recipeSelect?.value) {
            event.preventDefault();
            closeRecipeCreateForm(false);
        }
    });

    window.addEventListener('popstate', function() {
        const domainId = getDomainIdFromUrl();

        if (domainId && !hasDomainOption(domainId)) {
            setDomainSelectValue('');
            setCreateAvailable(false);
            setStatus(text.domainUnavailable);
            clearPagesTable(text.domainUnavailable);
            return;
        }

        setDomainSelectValue(domainId);
        loadDomain(domainId);
    });

    const initialDomainId = getDomainIdFromUrl();

    if (initialDomainId && !hasDomainOption(initialDomainId)) {
        setStatus(text.domainUnavailable);
        clearPagesTable(text.domainUnavailable);
        return;
    }

    setDomainSelectValue(initialDomainId);
    loadDomain(initialDomainId);
})();
</script>
<!-- /kami:template -->

<!-- kami:template recipes -->
<section class="admin-panel">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_url}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_pages}}</span>
            </a>
            <h2 class="admin-page-title">{{phrase.page_recipes}}</h2>
            <p class="admin-page-description">{{phrase.page_recipes_description}}</p>
        </div>
    </header>
</section>
    <div class="pm-recipes-grid">
        <section class="admin-card admin-pad pm-recipe-panel">
            <h3>{{phrase.recipes}}</h3>
            <div class="pm-recipe-list">{{recipe_rows}}</div>
        </section>

        <section class="admin-card admin-pad pm-recipe-panel">
            <h3>{{editor_title}}</h3>
            {{draft_notice}}
            <form class="admin-form" method="post" action="{{save_action}}">
                <input type="hidden" name="recipe_id" value="{{recipe_id}}">
                <label>{{phrase.recipe_key}}
                    <input class="admin-input" type="text" name="recipe_key" value="{{recipe_key}}" required>
                </label>
                <label>{{phrase.name}}
                    <input class="admin-input" type="text" name="name" value="{{name}}" required>
                </label>
                <label>{{phrase.description}}
                    <textarea class="admin-input admin-textarea" name="description" rows="3">{{description}}</textarea>
                </label>
                <label>{{phrase.payload_json}}
                    <textarea class="admin-input admin-textarea pm-recipe-code" name="payload" rows="20" spellcheck="false">{{payload}}</textarea>
                </label>
                <div class="admin-form-actions">
                    <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
                </div>
            </form>
        </section>
    </div>

<!-- /kami:template -->

<!-- kami:template recipe-row -->
<article class="pm-recipe-row">
    <div>
        <strong>{{name}}</strong>
        <div><code>{{recipe_key}}</code> · {{phrase.layout}} <code>{{layout}}</code></div>
        <p>{{description}}</p>
    </div>
    <div class="admin-actions">
        <a class="admin-button admin-button-secondary" href="{{edit_url}}">{{phrase.edit}}</a>
        <a class="admin-button admin-button-danger" href="{{delete_url}}" onclick="return confirm('{{phrase.delete_recipe_confirm}}')">{{phrase.delete}}</a>
    </div>
</article>
<!-- /kami:template -->

<!-- kami:template recipe-empty -->
<div class="admin-empty-state">{{phrase.no_recipes}}</div>
<!-- /kami:template -->

<!-- kami:template plugin -->
<li class="pm-plugin-palette-item"
    draggable="true"
    data-plugin="{{plugin_name}}"
    data-label="{{plugin_title}}">
    <svg class="icon icon-plug icon-sm"></svg>
    <span>{{plugin_title}}</span>
</li>
<!-- /kami:template -->

<!-- kami:template wrapper -->
<section class="pm-layout-zone">
    <header class="pm-zone-header">
        <strong>{{wrapper_title}}</strong>
        <span>{{wrapper_description}}</span>
    </header>
    <div class="plugin-dropzone" data-wrapper="{{wrapper_name}}">
        {{wrapper_plugins}}
    </div>
</section>
<!-- /kami:template -->

<!-- kami:template wrapper_plugin -->
<article class="pm-plugin-instance plugin-instance"
         data-plugin="{{plugin_name}}"
         data-instance-id="plugin_{{instance_id}}"
         data-wrapper="{{wrapper_name}}"
         draggable="false">
    <header class="pm-plugin-header">
        <span class="pm-plugin-drag-handle"
              role="button"
              tabindex="0"
              draggable="true"
              title="{{phrase.move_plugin}}"
              aria-label="{{phrase.move_plugin}}">
            <svg class="icon icon-plug icon-sm"></svg>
        </span>
        <div class="pm-plugin-identity">
            <strong>{{plugin_title}}</strong>
            <span>{{plugin_name}}</span>
        </div>
        <div class="pm-plugin-actions">
            <button class="admin-action-button admin-action-button-small pm-plugin-settings-toggle"
                    type="button"
                    title="{{phrase.plugin_settings}}"
                    aria-label="{{phrase.plugin_settings}}"
                    aria-expanded="false">
                <svg class="icon icon-settings icon-sm"></svg>
            </button>
            <button class="admin-action-button admin-action-button-small admin-action-danger plugin-remove"
                    type="button"
                    title="{{phrase.remove_plugin}}"
                    aria-label="{{phrase.remove_plugin}}">
                <svg class="icon icon-trash icon-sm"></svg>
            </button>
        </div>
    </header>
    <div class="pm-plugin-config plugin-config" hidden>
        {{context_form}}
    </div>
</article>
<!-- /kami:template -->
