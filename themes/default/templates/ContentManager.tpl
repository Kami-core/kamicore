<!-- kami:template item-edit -->
<section class="admin-page cm-item-editor" aria-labelledby="cm-item-editor-title">
    <header class="admin-page-header cm-item-editor-header">
        <div>
            <a class="admin-back-link" href="{{back_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_items}}</span>
            </a>
            <h2 id="cm-item-editor-title" class="admin-page-title">{{title}}</h2>
            <p class="admin-page-description">{{phrase.edit_item_help}}</p>
        </div>
    </header>

    <div class="cm-form-panel">
        {{form}}
    </div>
</section>
<!-- /kami:template -->

<!-- kami:template item-row -->
<tr id="item_row_{{item_id}}" data-content-item-row>
    <td>
        <a class="cm-item-link" href="{{edit_link}}">{{title}}</a>
    </td>
    <td class="admin-actions-cell">
        <div class="admin-actions">
            <a class="admin-action-button"
               href="{{edit_link}}"
               title="{{phrase.edit_item}}"
               aria-label="{{phrase.edit_item}}">
                <svg class="icon icon-pencil icon-sm"></svg>
            </a>
            <button class="admin-action-button admin-action-danger"
                    type="button"
                    data-delete-item
                    data-delete-url="{{delete_link}}"
                    data-item-title="{{title_attribute}}"
                    title="{{phrase.delete_item}}"
                    aria-label="{{phrase.delete_item}}">
                <svg class="icon icon-trash icon-sm"></svg>
            </button>
        </div>
    </td>
</tr>
<!-- /kami:template -->

<!-- kami:template items-empty-row -->
<tr class="cm-state-row">
    <td colspan="2" class="admin-empty-state">{{phrase.no_items}}</td>
</tr>
<!-- /kami:template -->

<!-- kami:template items-list -->
<section class="admin-page"
         data-content-items-page
         data-item-count="{{items_count}}"
         aria-labelledby="cm-items-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{types_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_content_types}}</span>
            </a>
            <h2 id="cm-items-title" class="admin-page-title">
                {{phrase.content}}: {{type_name}}
            </h2>
            <p class="admin-page-description">{{phrase.manage_items}}</p>
        </div>
        <span class="admin-page-status" data-content-items-status>{{items_summary}}</span>
    </header>

    <div class="admin-toolbar">
        <div class="cm-toolbar-main">
            <form class="cm-search" role="search" action="{{search_link}}" method="get">
                <label class="admin-visually-hidden" for="cm-items-search">{{phrase.search}}</label>
                <svg class="icon icon-search icon-sm" aria-hidden="true"></svg>
                <input id="cm-items-search"
                       class="admin-input"
                       type="search"
                       name="cm-q"
                       placeholder="{{phrase.search}}"
                       autocomplete="off"
                       value="{{q}}">
            </form>
        </div>

        <a class="admin-button admin-button-primary" href="{{create_link}}">
            <svg class="icon icon-plus icon-sm"></svg>
            <span>{{phrase.new_item}}</span>
        </a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{phrase.name}}</th>
                    <th class="admin-actions-heading">{{phrase.actions}}</th>
                </tr>
            </thead>
            <tbody data-content-items-rows>
                {{item_rows}}
            </tbody>
        </table>
    </div>

    <footer class="cm-page-footer">
        <div class="cm-pagination">{{pagination}}</div>
        <a class="admin-button admin-button-primary" href="{{create_link}}">
            <svg class="icon icon-plus icon-sm"></svg>
            <span>{{phrase.new_item}}</span>
        </a>
    </footer>

    <div class="admin-notice" data-content-items-notice hidden aria-live="polite"></div>
</section>

<script>
(function() {
    'use strict';

    const root = document.querySelector('[data-content-items-page]');
    if (!root) return;

    const text = {{ui_text}};
    const rows = root.querySelector('[data-content-items-rows]');
    const status = root.querySelector('[data-content-items-status]');
    const notice = root.querySelector('[data-content-items-notice]');
    let itemCount = Number(root.dataset.itemCount || 0);

    function format(pattern, values = {}) {
        return Object.entries(values).reduce(
            (result, [key, value]) => result.replaceAll('{' + key + '}', String(value)),
            pattern
        );
    }

    function setItemCount(count) {
        itemCount = Math.max(0, count);
        status.textContent = format(text.itemCount, { count: itemCount });
    }

    function showNotice(message, kind) {
        notice.textContent = message;
        notice.className = 'admin-notice admin-notice-' + kind;
        notice.hidden = false;
    }

    function renderEmptyState() {
        if (rows.querySelector('[data-content-item-row]')) return;

        const row = document.createElement('tr');
        row.className = 'cm-state-row';

        const cell = document.createElement('td');
        cell.colSpan = 2;
        cell.className = 'admin-empty-state';
        cell.textContent = text.noItems;

        row.appendChild(cell);
        rows.appendChild(row);
    }

    async function itemDelete(button) {
        const row = button.closest('[data-content-item-row]');
        const itemTitle = button.dataset.itemTitle || '';
        const url = button.dataset.deleteUrl;

        if (!row || !url || !window.confirm(
            format(text.confirmDeleteItem, { title: itemTitle })
        )) {
            return;
        }

        button.disabled = true;
        notice.hidden = true;

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            if (data.status !== 'ok') {
                throw new Error(data.error || text.deleteFailed);
            }

            row.remove();
            setItemCount(itemCount - 1);
            renderEmptyState();
            showNotice(data.message || text.itemDeleted, 'success');
        } catch (error) {
            console.error('Content Manager: failed to delete item.', error);
            button.disabled = false;
            showNotice(text.deleteFailed, 'error');
        }
    }

    root.addEventListener('click', event => {
        const button = event.target.closest('[data-delete-item]');
        if (button) itemDelete(button);
    });
})();
</script>
<!-- /kami:template -->

<!-- kami:template types-list -->
<section class="admin-page"
         data-content-types-page
         aria-labelledby="cm-types-title">
    <header class="admin-page-header">
        <div>
            <h2 id="cm-types-title" class="admin-page-title">{{phrase.content_types}}</h2>
            <p class="admin-page-description">{{phrase.manage_schemas}}</p>
        </div>
        <span class="admin-page-status" data-content-types-status></span>
    </header>

    <div class="admin-toolbar">
        <div class="cm-search">
            <label class="admin-visually-hidden" for="cm-types-search">
                {{phrase.search_content_types}}
            </label>
            <svg class="icon icon-search icon-sm" aria-hidden="true"></svg>
            <input id="cm-types-search"
                   class="admin-input"
                   type="search"
                   placeholder="{{phrase.search_content_types}}"
                   autocomplete="off"
                   data-content-types-search>
        </div>

        <div class="cm-toolbar-actions">
            {{manager_tools}}
            <a class="admin-button admin-button-primary" href="{{create_link}}">
                <svg class="icon icon-plus icon-sm"></svg>
                <span>{{phrase.create_content_type}}</span>
            </a>
        </div>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{phrase.name_and_description}}</th>
                    <th class="cm-count-heading">{{phrase.items}}</th>
                    <th class="admin-actions-heading">{{phrase.actions}}</th>
                </tr>
            </thead>
            <tbody data-content-types-rows></tbody>
        </table>
    </div>
</section>

<script>
(function initContentTypesPage() {
    'use strict';

    const root = document.querySelector('[data-content-types-page]');
    if (!root) return;

    const text = {{ui_text}};
    const search = root.querySelector('[data-content-types-search]');
    const rows = root.querySelector('[data-content-types-rows]');
    const status = root.querySelector('[data-content-types-status]');
    let contentTypes = {{types_json}};

    function format(pattern, values = {}) {
        return Object.entries(values).reduce(
            (result, [key, value]) => result.replaceAll('{' + key + '}', String(value)),
            pattern
        );
    }

    function createIcon(name) {
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.classList.add('icon', 'icon-' + name, 'icon-sm');
        return icon;
    }

    function render() {
        const query = search.value.trim().toLowerCase();
        const filtered = contentTypes.filter(type => {
            if (!query) return true;

            return String(type.title || '').toLowerCase().includes(query)
                || String(type.description || '').toLowerCase().includes(query);
        });

        rows.replaceChildren();
        status.textContent = format(text.typeCount, { count: filtered.length });

        if (filtered.length === 0) {
            const row = document.createElement('tr');
            row.className = 'cm-state-row';

            const cell = document.createElement('td');
            cell.colSpan = 3;
            cell.className = 'admin-empty-state';
            cell.textContent = text.noTypes;

            row.appendChild(cell);
            rows.appendChild(row);
            return;
        }

        filtered.forEach(type => rows.appendChild(createRow(type)));
    }

    function createRow(type) {
        const row = document.createElement('tr');

        const details = document.createElement('td');
        const title = document.createElement('a');
        title.className = 'cm-type-link';
        title.href = type.url_items;
        title.textContent = type.title || '';

        const description = document.createElement('p');
        description.className = 'cm-type-description';
        description.textContent = type.description || '\u2014';
        details.append(title, description);

        const items = document.createElement('td');
        items.className = 'cm-count-cell';

        const badge = document.createElement('span');
        badge.className = 'cm-count-badge';
        badge.textContent = String(type.items_count ?? 0);
        items.appendChild(badge);

        const actions = document.createElement('td');
        actions.className = 'admin-actions-cell';

        const group = document.createElement('div');
        group.className = 'admin-actions';
        group.append(
            createAction('a', 'tags', text.openItems, type.url_items),
            createAction('a', 'pencil', text.editStructure, type.url_edit),
            createAction('button', 'trash', text.deleteType, null, type.type_id)
        );
        actions.appendChild(group);

        row.append(details, items, actions);
        return row;
    }

    function createAction(tag, iconName, title, href = null, deleteId = null) {
        const action = document.createElement(tag);
        action.className = 'admin-action-button';
        action.title = title;
        action.setAttribute('aria-label', title);
        action.appendChild(createIcon(iconName));

        if (href) action.href = href;
        if (tag === 'button') action.type = 'button';

        if (deleteId !== null) {
            action.classList.add('admin-action-danger');
            action.dataset.deleteType = String(deleteId);
        }

        return action;
    }

    search.addEventListener('input', render);
    rows.addEventListener('click', async event => {
        const button = event.target.closest('[data-delete-type]');
        if (!button) return;

        const type = contentTypes.find(
            item => String(item.type_id) === button.dataset.deleteType
        );
        if (!type || !window.confirm(
            format(text.confirmDeleteType, { title: type.title || '' })
        )) {
            return;
        }

        button.disabled = true;
        try {
            const formData = new FormData();
            formData.append('ct_id', String(type.type_id));
            const response = await fetch('{{delete_endpoint}}', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.error || text.deleteFailed);
            }
            contentTypes = contentTypes.filter(item => item.type_id !== type.type_id);
            render();
            window.alert(data.message || text.typeDeleted);
        } catch (error) {
            window.alert(error.message || text.deleteFailed);
            button.disabled = false;
        }
    });

    render();
})();
</script>
<!-- /kami:template -->

<!-- kami:template fields-list -->
<section class="admin-page"
         data-content-fields-page
         aria-labelledby="cm-fields-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_content_types}}</span>
            </a>
            <h2 id="cm-fields-title" class="admin-page-title">{{phrase.field_definitions}}</h2>
            <p class="admin-page-description">{{phrase.field_definitions_help}}</p>
        </div>
        <span class="admin-page-status" data-content-fields-status></span>
    </header>

    <div class="admin-toolbar">
        <div class="cm-search">
            <label class="admin-visually-hidden" for="cm-fields-search">
                {{phrase.search_fields}}
            </label>
            <svg class="icon icon-search icon-sm" aria-hidden="true"></svg>
            <input id="cm-fields-search"
                   class="admin-input"
                   type="search"
                   placeholder="{{phrase.search_fields}}"
                   autocomplete="off"
                   data-content-fields-search>
        </div>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table cm-global-fields-table">
            <thead>
                <tr>
                    <th>{{phrase.field}}</th>
                    <th>{{phrase.field_type}}</th>
                    <th>{{phrase.used_in}}</th>
                    <th>{{phrase.status}}</th>
                    <th class="admin-actions-heading">{{phrase.actions}}</th>
                </tr>
            </thead>
            <tbody data-content-fields-rows></tbody>
        </table>
    </div>

    <div class="admin-notice" data-content-fields-notice hidden aria-live="polite"></div>
</section>

<script>
(function initContentFieldsPage() {
    'use strict';

    const root = document.querySelector('[data-content-fields-page]');
    if (!root) return;

    const text = {{ui_text}};
    const search = root.querySelector('[data-content-fields-search]');
    const rows = root.querySelector('[data-content-fields-rows]');
    const status = root.querySelector('[data-content-fields-status]');
    const notice = root.querySelector('[data-content-fields-notice]');
    let fields = {{fields_json}};

    function format(pattern, values = {}) {
        return Object.entries(values).reduce(
            (result, [key, value]) => result.replaceAll('{' + key + '}', String(value)),
            pattern
        );
    }

    function createIcon(name) {
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.classList.add('icon', 'icon-' + name, 'icon-sm');
        icon.setAttribute('aria-hidden', 'true');
        return icon;
    }

    function showNotice(message, kind) {
        notice.textContent = message;
        notice.className = 'admin-notice admin-notice-' + kind;
        notice.hidden = false;
    }

    function lockReason(field) {
        if (field.usage_count > 0) return text.usedLock;
        return '';
    }

    function createRow(field) {
        const row = document.createElement('tr');
        row.dataset.fieldId = String(field.field_id);

        const details = document.createElement('td');
        const title = document.createElement('strong');
        title.className = 'cm-field-title';
        title.textContent = field.title || field.system_name;
        const name = document.createElement('code');
        name.className = 'cm-field-name';
        name.textContent = field.system_name;
        details.append(title, name);
        if (field.description) {
            const description = document.createElement('small');
            description.className = 'cm-field-description';
            description.textContent = field.description;
            details.appendChild(description);
        }

        const type = document.createElement('td');
        const typeBadge = document.createElement('span');
        typeBadge.className = 'cm-type-badge';
        typeBadge.textContent = field.type_name;
        type.appendChild(typeBadge);

        const usage = document.createElement('td');
        if (field.usage_count > 0) {
            const usageText = document.createElement('span');
            usageText.className = 'cm-field-usage';
            usageText.textContent = field.usage.join(', ');
            usage.appendChild(usageText);
        } else {
            const unused = document.createElement('span');
            unused.className = 'cm-field-state cm-field-state-unused';
            unused.textContent = text.unused;
            usage.appendChild(unused);
        }

        const dataState = document.createElement('td');
        const valueBadge = document.createElement('span');
        valueBadge.className = 'cm-field-state';
        if (field.has_values) {
            valueBadge.classList.add('cm-field-state-has-values');
            valueBadge.textContent = text.storedValues;
        } else {
            valueBadge.classList.add('cm-field-state-empty');
            valueBadge.textContent = text.noStoredValues;
        }
        dataState.appendChild(valueBadge);

        const actions = document.createElement('td');
        actions.className = 'admin-actions-cell';
        const group = document.createElement('div');
        group.className = 'admin-actions';
        const editLink = document.createElement('a');
        editLink.className = 'admin-action-button';
        editLink.href = field.edit_url;
        editLink.title = text.editField;
        editLink.setAttribute('aria-label', text.editField);
        editLink.appendChild(createIcon('pencil'));
        group.appendChild(editLink);

        const button = document.createElement('button');
        button.className = 'admin-action-button admin-action-danger';
        button.type = 'button';
        button.appendChild(createIcon('trash'));

        if (field.deletable) {
            button.dataset.deleteField = String(field.field_id);
            button.title = text.deleteField;
            button.setAttribute('aria-label', text.deleteField);
        } else {
            const reason = lockReason(field);
            button.disabled = true;
            button.title = reason;
            button.setAttribute('aria-label', reason);
        }
        group.appendChild(button);
        actions.appendChild(group);

        row.append(details, type, usage, dataState, actions);
        return row;
    }

    function render() {
        const query = search.value.trim().toLowerCase();
        const filtered = fields.filter(field => {
            if (!query) return true;
            return [
                field.title,
                field.description,
                field.system_name,
                field.type_name,
                ...(field.usage || [])
            ].some(value => String(value || '').toLowerCase().includes(query));
        });

        rows.replaceChildren();
        status.textContent = format(text.fieldCount, { count: filtered.length });

        if (filtered.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'admin-empty-state';
            cell.textContent = text.noFields;
            row.appendChild(cell);
            rows.appendChild(row);
            return;
        }

        filtered.forEach(field => rows.appendChild(createRow(field)));
    }

    async function deleteField(button) {
        const field = fields.find(item => String(item.field_id) === button.dataset.deleteField);
        const confirmPattern = field?.has_values
            ? text.confirmDeleteWithValues
            : text.confirmDelete;
        if (!field || !window.confirm(
            format(confirmPattern, { title: field.title || field.system_name })
        )) {
            return;
        }

        button.disabled = true;
        notice.hidden = true;
        try {
            const formData = new FormData();
            formData.append('field_id', String(field.field_id));
            const response = await fetch('{{delete_endpoint}}', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.error || text.deleteFailed);
            }

            fields = fields.filter(item => item.field_id !== field.field_id);
            render();
            showNotice(data.message || text.deleted, 'success');
        } catch (error) {
            button.disabled = false;
            showNotice(error.message || text.deleteFailed, 'error');
        }
    }

    search.addEventListener('input', render);
    rows.addEventListener('click', event => {
        const button = event.target.closest('[data-delete-field]');
        if (button) deleteField(button);
    });
    render();
})();
</script>
<!-- /kami:template -->

<!-- kami:template type-edit -->
<section class="admin-page cm-structure-editor"
         data-structure-editor
         data-type-id="{{type_id}}"
         aria-labelledby="cm-structure-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_content_types}}</span>
            </a>
            <h2 id="cm-structure-title" class="admin-page-title">{{page_title}}</h2>
            <p class="admin-page-description">{{phrase.structure_editor_help}}</p>
        </div>
    </header>

    {{declarative_notice}}

    <form class="cm-structure-form" method="post" action="{{save_action}}">
        <input type="hidden" name="ct_id" value="{{type_id}}">
        <div class="cm-form-panel cm-structure-basics">
            <div class="cm-form-grid">
                <label class="cm-field">
                    <span>{{phrase.system_name}}</span>
                    <input class="admin-input" type="text" name="system_name"
                           value="{{system_name}}" pattern="[a-z][a-z0-9_]*" required>
                </label>
                <label class="cm-field">
                    <span>{{phrase.title}}</span>
                    <input class="admin-input" type="text" name="title" value="{{title}}" required>
                </label>
                <label class="cm-field cm-field-wide">
                    <span>{{phrase.description}}</span>
                    <textarea class="admin-input" name="description" rows="3">{{description}}</textarea>
                </label>
                <label class="cm-field">
                    <span>{{phrase.parent_content_type}}</span>
                    <select class="admin-input" name="parent_id">{{parent_options}}</select>
                </label>
                <label class="cm-field">
                    <span>{{phrase.title_field}}</span>
                    <select class="admin-input" name="title_field">{{title_field_options}}</select>
                </label>
                <label class="cm-field">
                    <span>{{phrase.summary_field}}</span>
                    <select class="admin-input" name="summary_field">{{summary_field_options}}</select>
                </label>
                <label class="cm-check-field">
                    <input type="checkbox" name="has_slug" value="1"{{has_slug_checked}}>
                    <span>{{phrase.has_slug}}</span>
                </label>
            </div>
            <div class="admin-form-actions">
                <button class="admin-button admin-button-primary" type="submit">
                    <svg class="icon icon-save icon-sm"></svg>
                    <span>{{phrase.save_content_type}}</span>
                </button>
            </div>
        </div>
    </form>

    <div class="cm-structure-fields"{{fields_hidden}}>
        <div class="cm-section-heading">
            <div>
                <h3>{{phrase.fields}}</h3>
                <p>{{phrase.fields_help}}</p>
            </div>
            <a class="admin-button admin-button-primary" href="{{new_field_link}}">
                <svg class="icon icon-plus icon-sm"></svg>
                <span>{{phrase.create_field}}</span>
            </a>
        </div>

        <div class="cm-attach-field">
            <label class="admin-visually-hidden" for="cm-existing-field">{{phrase.existing_field}}</label>
            <select id="cm-existing-field" class="admin-input" data-existing-field>
                {{available_field_options}}
            </select>
            <button class="admin-button admin-button-secondary" type="button" data-attach-field>
                <svg class="icon icon-plus icon-sm"></svg>
                <span>{{phrase.attach_field}}</span>
            </button>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table cm-fields-table">
                <thead>
                    <tr>
                        <th>{{phrase.field}}</th>
                        <th>{{phrase.field_type}}</th>
                        <th>{{phrase.order}}</th>
                        <th class="admin-actions-heading">{{phrase.actions}}</th>
                    </tr>
                </thead>
                <tbody data-field-rows>{{field_rows}}</tbody>
            </table>
        </div>
    </div>
    <div class="admin-notice" data-structure-notice hidden aria-live="polite"></div>
</section>

<script>
(function() {
    'use strict';
    const root = document.querySelector('[data-structure-editor]');
    if (!root || !Number(root.dataset.typeId)) return;
    const text = {{ui_text}};
    const typeId = root.dataset.typeId;
    const notice = root.querySelector('[data-structure-notice]');
    const existing = root.querySelector('[data-existing-field]');

    function showNotice(message, kind) {
        notice.textContent = message;
        notice.className = 'admin-notice admin-notice-' + kind;
        notice.hidden = false;
    }

    async function post(endpoint, values) {
        const formData = new FormData();
        Object.entries(values).forEach(([key, value]) => formData.append(key, value));
        const response = await fetch('/ajax/ContentManager/' + endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'ok') {
            throw new Error(data.error || text.operationFailed);
        }
        return data;
    }

    root.querySelector('[data-attach-field]').addEventListener('click', async event => {
        if (!existing.value) return;
        event.currentTarget.disabled = true;
        try {
            await post('fieldAttach', { ct_id: typeId, field_id: existing.value });
            window.location.reload();
        } catch (error) {
            showNotice(error.message, 'error');
            event.currentTarget.disabled = false;
        }
    });

    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-field-action]');
        if (!button) return;
        const row = button.closest('[data-field-row]');
        const action = button.dataset.fieldAction;
        if (!['up', 'down', 'detach'].includes(action)) return;
        if (action === 'detach' && !window.confirm(text.confirmDetach)) return;

        button.disabled = true;
        try {
            const values = { ct_id: typeId, field_id: row.dataset.fieldId };
            if (action === 'up' || action === 'down') values.direction = action;
            const endpoint = action === 'up' || action === 'down'
                ? 'fieldMove'
                : 'fieldDetach';
            await post(endpoint, values);
            window.location.reload();
        } catch (error) {
            showNotice(error.message, 'error');
            button.disabled = false;
        }
    });
})();
</script>
<!-- /kami:template -->

<!-- kami:template type-field-row -->
<tr data-field-row data-field-id="{{field_id}}">
    <td>
        <strong class="cm-field-title">{{field_title}}</strong>
        <code class="cm-field-name">{{field_name}}</code>
        <small class="cm-field-description">{{field_description}}</small>
    </td>
    <td><span class="cm-type-badge">{{field_type}}</span></td>
    <td class="cm-order-cell">{{field_order}}</td>
    <td class="admin-actions-cell">
        <div class="admin-actions">
            <button class="admin-action-button" type="button" data-field-action="up"
                    title="{{phrase.move_up}}" aria-label="{{phrase.move_up}}">
                <svg class="icon icon-chevron-up icon-sm"></svg>
            </button>
            <button class="admin-action-button" type="button" data-field-action="down"
                    title="{{phrase.move_down}}" aria-label="{{phrase.move_down}}">
                <svg class="icon icon-chevron-down icon-sm"></svg>
            </button>
            <a class="admin-action-button" href="{{edit_link}}"
               title="{{phrase.edit_field}}" aria-label="{{phrase.edit_field}}">
                <svg class="icon icon-pencil icon-sm"></svg>
            </a>
            <button class="admin-action-button" type="button" data-field-action="detach"
                    title="{{phrase.detach_field}}" aria-label="{{phrase.detach_field}}">
                <svg class="icon icon-x icon-sm"></svg>
            </button>
        </div>
    </td>
</tr>
<!-- /kami:template -->

<!-- kami:template field-edit -->
<section class="admin-page cm-field-editor" data-field-editor
         aria-labelledby="cm-field-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_structure}}</span>
            </a>
            <h2 id="cm-field-title" class="admin-page-title">{{page_title}}</h2>
            <p class="admin-page-description">{{phrase.field_editor_help}}</p>
        </div>
        <span class="admin-page-status">{{usage_notice}}</span>
    </header>

    <form class="cm-structure-form" method="post" action="{{save_action}}">
        <input type="hidden" name="ct_id" value="{{type_id}}">
        <input type="hidden" name="field_id" value="{{field_id}}">
        <div class="cm-form-panel">
            <div class="cm-form-grid">
                <label class="cm-field">
                    <span>{{phrase.system_name}}</span>
                    <input class="admin-input" type="text" name="system_name"
                           value="{{system_name}}" pattern="[a-z][a-z0-9_]*" required>
                </label>
                <label class="cm-field">
                    <span>{{phrase.field_type}}</span>
                    <select class="admin-input" name="type_id" required>{{field_type_options}}</select>
                </label>
                <label class="cm-field">
                    <span>{{phrase.title}}</span>
                    <input class="admin-input" type="text" name="title" value="{{title}}" required>
                </label>
                <label class="cm-field">
                    <span>{{phrase.search_weight}}</span>
                    <select class="admin-input" name="search_weight">{{search_weight_options}}</select>
                </label>
                <label class="cm-field cm-field-wide">
                    <span>{{phrase.description}}</span>
                    <textarea class="admin-input" name="description" rows="3">{{description}}</textarea>
                </label>
                <label class="cm-field">
                    <span>{{phrase.default_value}}</span>
                    <input class="admin-input" type="text" name="default_value" value="{{default_value}}">
                </label>
            </div>

            {{field_parameters}}

            <fieldset class="cm-settings-grid">
                <legend>{{phrase.field_settings}}</legend>
                <label><input type="checkbox" name="required" value="1"{{required_checked}}> {{phrase.required}}</label>
                <label><input type="checkbox" name="indexed" value="1"{{indexed_checked}}> {{phrase.indexed}}</label>
                <label><input type="checkbox" name="unique" value="1"{{unique_checked}}> {{phrase.unique}}</label>
                <label><input type="checkbox" name="translatable" value="1"{{translatable_checked}}> {{phrase.translatable}}</label>
                <label><input type="checkbox" name="multiple" value="1"{{multiple_checked}}> {{phrase.multiple}}</label>
                <label><input type="checkbox" name="hidden" value="1"{{hidden_checked}}> {{phrase.hidden}}</label>
                <label><input type="checkbox" name="readonly" value="1"{{readonly_checked}}> {{phrase.readonly}}</label>
            </fieldset>

            <div class="admin-form-actions">
                <a class="admin-button admin-button-secondary" href="{{back_link}}">{{phrase.cancel}}</a>
                <button class="admin-button admin-button-primary" type="submit">
                    <svg class="icon icon-save icon-sm"></svg>
                    <span>{{phrase.save_field}}</span>
                </button>
            </div>
        </div>
    </form>
</section>
<script>
(() => {
    const editor = document.querySelector('[data-field-editor]');
    if (!editor) return;

    const typeSelect = editor.querySelector('select[name="type_id"]');
    const groups = [...editor.querySelectorAll('[data-field-parameters]')];
    if (!typeSelect || groups.length === 0) return;

    function syncParameterGroup() {
        const selectedType = typeSelect.value;
        const selectedName = typeSelect.selectedOptions[0]?.dataset.typeName || '';
        groups.forEach(group => {
            const active = group.dataset.fieldParameters === selectedType;
            group.hidden = !active;
            group.disabled = !active;
        });

        for (const name of ['unique', 'translatable']) {
            const input = editor.querySelector(`input[name="${name}"]`);
            if (!input) continue;
            input.disabled = selectedName === 'compound';
            if (input.disabled) input.checked = false;
        }
    }

    typeSelect.addEventListener('change', syncParameterGroup);
    syncParameterGroup();
})();
</script>
<!-- /kami:template -->

<!-- kami:template field-attachment-edit -->
<section class="admin-page cm-field-editor" aria-labelledby="cm-field-attachment-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_structure}}</span>
            </a>
            <h2 id="cm-field-attachment-title" class="admin-page-title">{{page_title}}</h2>
            <p class="admin-page-description">{{phrase.field_attachment_editor_help}}</p>
        </div>
        <span class="admin-page-status">
            <code>{{system_name}}</code> · {{field_type}}
        </span>
    </header>

    <form class="cm-structure-form" method="post" action="{{save_action}}">
        <input type="hidden" name="ct_id" value="{{type_id}}">
        <input type="hidden" name="field_id" value="{{field_id}}">
        <div class="cm-form-panel">
            <div class="cm-form-grid">
                <label class="cm-field">
                    <span>{{phrase.title}}</span>
                    <input class="admin-input" type="text" name="title" value="{{title}}">
                </label>
                <label class="cm-field">
                    <span>{{phrase.search_weight}}</span>
                    <select class="admin-input" name="search_weight">{{search_weight_options}}</select>
                </label>
                <label class="cm-field cm-field-wide">
                    <span>{{phrase.description}}</span>
                    <textarea class="admin-input" name="description" rows="3">{{description}}</textarea>
                </label>
                <label class="cm-field">
                    <span>{{phrase.default_value}}</span>
                    <input class="admin-input" type="text" name="default_value" value="{{default_value}}">
                </label>
            </div>

            <fieldset class="cm-settings-grid">
                <legend>{{phrase.field_attachment_settings}}</legend>
                <label><input type="checkbox" name="required" value="1"{{required_checked}}> {{phrase.required}}</label>
                <label><input type="checkbox" name="multiple" value="1"{{multiple_checked}}> {{phrase.multiple}}</label>
                <label><input type="checkbox" name="hidden" value="1"{{hidden_checked}}> {{phrase.hidden}}</label>
                <label><input type="checkbox" name="readonly" value="1"{{readonly_checked}}> {{phrase.readonly}}</label>
            </fieldset>

            <div class="admin-form-actions">
                <a class="admin-button admin-button-secondary" href="{{back_link}}">{{phrase.cancel}}</a>
                <button class="admin-button admin-button-primary" type="submit">
                    <svg class="icon icon-save icon-sm"></svg>
                    <span>{{phrase.save_field}}</span>
                </button>
            </div>
        </div>
    </form>
</section>
<!-- /kami:template -->

<!-- kami:template global-field-edit -->
<section class="admin-page cm-field-editor" data-field-editor
         aria-labelledby="cm-global-field-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_fields}}</span>
            </a>
            <h2 id="cm-global-field-title" class="admin-page-title">{{page_title}}</h2>
            <p class="admin-page-description">{{phrase.global_field_editor_help}}</p>
        </div>
        <span class="admin-page-status">{{usage_notice}}</span>
    </header>

    <form class="cm-structure-form" method="post" action="{{save_action}}">
        <input type="hidden" name="field_id" value="{{field_id}}">
        <div class="cm-form-panel">
            <div class="cm-form-grid">
                <label class="cm-field">
                    <span>{{phrase.system_name}}</span>
                    <input class="admin-input" type="text" name="system_name"
                           value="{{system_name}}" pattern="[a-z][a-z0-9_]*" required>
                </label>
                <label class="cm-field">
                    <span>{{phrase.field_type}}</span>
                    <select class="admin-input" name="type_id" required>{{field_type_options}}</select>
                </label>
                <label class="cm-field">
                    <span>{{phrase.title}}</span>
                    <input class="admin-input" type="text" name="title" value="{{title}}" required>
                </label>
                <label class="cm-field cm-field-wide">
                    <span>{{phrase.description}}</span>
                    <textarea class="admin-input" name="description" rows="3">{{description}}</textarea>
                </label>
            </div>

            {{field_parameters}}

            <fieldset class="cm-settings-grid">
                <legend>{{phrase.global_field_settings}}</legend>
                <label><input type="checkbox" name="indexed" value="1"{{indexed_checked}}> {{phrase.indexed}}</label>
                <label><input type="checkbox" name="unique" value="1"{{unique_checked}}> {{phrase.unique}}</label>
                <label><input type="checkbox" name="translatable" value="1"{{translatable_checked}}> {{phrase.translatable}}</label>
            </fieldset>

            <div class="admin-form-actions">
                <a class="admin-button admin-button-secondary" href="{{back_link}}">{{phrase.cancel}}</a>
                <button class="admin-button admin-button-primary" type="submit">
                    <svg class="icon icon-save icon-sm"></svg>
                    <span>{{phrase.save_field}}</span>
                </button>
            </div>
        </div>
    </form>
</section>
<script>
(() => {
    const editor = document.querySelector('[data-field-editor]');
    if (!editor) return;

    const typeSelect = editor.querySelector('select[name="type_id"]');
    const groups = [...editor.querySelectorAll('[data-field-parameters]')];
    if (!typeSelect || groups.length === 0) return;

    function syncParameterGroup() {
        const selectedType = typeSelect.value;
        const selectedName = typeSelect.selectedOptions[0]?.dataset.typeName || '';
        groups.forEach(group => {
            const active = group.dataset.fieldParameters === selectedType;
            group.hidden = !active;
            group.disabled = !active;
        });

        for (const name of ['unique', 'translatable']) {
            const input = editor.querySelector(`input[name="${name}"]`);
            if (!input) continue;
            input.disabled = selectedName === 'compound';
            if (input.disabled) input.checked = false;
        }
    }

    typeSelect.addEventListener('change', syncParameterGroup);
    syncParameterGroup();
})();
</script>
<!-- /kami:template -->

<!-- kami:template type-managers -->
<section class="admin-page cm-manager-page"
         data-type-managers-page
         aria-labelledby="cm-managers-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_link}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_content_types}}</span>
            </a>
            <h2 id="cm-managers-title" class="admin-page-title">
                {{phrase.content_type_managers}}
            </h2>
            <p class="admin-page-description">{{phrase.content_type_managers_help}}</p>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table cm-manager-table">
            <thead>
                <tr>
                    <th>{{phrase.content_type}}</th>
                    <th>{{phrase.owner_plugin}}</th>
                    <th>{{phrase.default_manager}}</th>
                    <th>{{phrase.current_manager}}</th>
                    <th>{{phrase.source}}</th>
                    <th class="admin-actions-heading">{{phrase.actions}}</th>
                </tr>
            </thead>
            <tbody>
                {{manager_rows}}
            </tbody>
        </table>
    </div>

    <div class="admin-notice" data-manager-notice hidden aria-live="polite"></div>
</section>

<script>
(function() {
    'use strict';

    const root = document.querySelector('[data-type-managers-page]');
    if (!root) return;

    const text = {{ui_text}};
    const notice = root.querySelector('[data-manager-notice]');
    const endpoint = '/ajax/ContentManager/typeManagerUpdate';

    function showNotice(message, kind) {
        notice.textContent = message;
        notice.className = 'admin-notice admin-notice-' + kind;
        notice.hidden = false;
    }

    function setBusy(row, busy) {
        row.querySelectorAll('button').forEach(button => {
            if (busy) {
                button.dataset.wasDisabled = button.disabled ? '1' : '0';
                button.disabled = true;
            } else {
                button.disabled = button.dataset.wasDisabled === '1';
                delete button.dataset.wasDisabled;
            }
        });
    }

    async function updateManager(row, mode) {
        const select = row.querySelector('[data-manager-select]');
        const badge = row.querySelector('[data-manager-badge]');
        const resetButton = row.querySelector('[data-manager-reset]');
        const formData = new FormData();

        formData.append('ct_id', row.dataset.typeId);
        formData.append('mode', mode);
        if (mode === 'override') {
            formData.append('manager_plugin_id', select.value);
        }

        notice.hidden = true;
        setBusy(row, true);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.error || text.saveFailed);
            }

            select.value = data.manager_id === null
                ? ''
                : String(data.manager_id);
            badge.textContent = data.overridden
                ? text.overridden
                : row.dataset.defaultSource;
            badge.classList.toggle(
                'cm-manager-badge-overridden',
                data.overridden
            );
            resetButton.disabled = !data.overridden;
            showNotice(data.message || (
                mode === 'reset' ? text.reset : text.saved
            ), 'success');
        } catch (error) {
            console.error('Content Manager: manager update failed.', error);
            showNotice(error.message || text.saveFailed, 'error');
        } finally {
            setBusy(row, false);
            resetButton.disabled = !badge.classList.contains(
                'cm-manager-badge-overridden'
            );
        }
    }

    root.addEventListener('click', event => {
        const saveButton = event.target.closest('[data-manager-save]');
        const resetButton = event.target.closest('[data-manager-reset]');
        const button = saveButton || resetButton;
        if (!button) return;

        const row = button.closest('[data-manager-row]');
        if (!row) return;

        updateManager(row, resetButton ? 'reset' : 'override');
    });
})();
</script>
<!-- /kami:template -->

<!-- kami:template type-manager-row -->
<tr data-manager-row data-type-id="{{type_id}}"
    data-default-source="{{default_source}}">
    <td>
        <strong class="cm-manager-type-title">{{type_title}}</strong>
        <code class="cm-manager-system-name">{{system_name}}</code>
    </td>
    <td>{{owner_name}}</td>
    <td>{{default_manager}}</td>
    <td>
        <select class="admin-input admin-input-wide"
                data-manager-select
                aria-label="{{phrase.current_manager}}">
            {{manager_options}}
        </select>
    </td>
    <td>
        <span class="cm-manager-badge{{override_class}}" data-manager-badge>
            {{override_label}}
        </span>
    </td>
    <td class="admin-actions-cell">
        <div class="admin-actions">
            <button class="admin-action-button"
                    type="button"
                    data-manager-save
                    title="{{phrase.save_manager}}"
                    aria-label="{{phrase.save_manager}}">
                <svg class="icon icon-save icon-sm"></svg>
            </button>
            <button class="admin-action-button"
                    type="button"
                    data-manager-reset
                    title="{{phrase.restore_default_manager}}"
                    aria-label="{{phrase.restore_default_manager}}"{{reset_disabled}}>
                <svg class="icon icon-refresh-cw icon-sm"></svg>
            </button>
        </div>
    </td>
</tr>
<!-- /kami:template -->
