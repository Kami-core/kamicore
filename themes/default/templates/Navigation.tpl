<!-- kami:template sidebar -->
	<ul class="kc-menu">
        {{menu_items}}
    </ul>
<!-- /kami:template -->

<!-- kami:template sidebar_item -->
<li><a href="{{item_url}}" data-tooltip="{{item_title}}" aria-label="{{item_title}}">{{rendered_icon}}<span> {{item_title}}</span></a></li>
<!-- /kami:template -->


<!-- kami:template topnav -->
<nav class="kc-site-nav" data-site-nav aria-label="Site navigation">
    <ul class="kc-nav kc-nav-desktop">
        {{menu_items}}
    </ul>

    <button class="kc-nav-toggle"
            type="button"
            data-nav-toggle
            aria-expanded="false"
            aria-label="Toggle navigation">
        <svg class="icon icon-menu icon-lg"></svg>
    </button>

    <div class="kc-nav-mobile" data-nav-menu hidden>
        <ul class="kc-nav-mobile-list">
            {{menu_items}}
        </ul>
    </div>
</nav>
<!-- /kami:template -->

<!-- kami:template topnav_item -->
<li>
	<a href="{{item_url}}">{{rendered_icon}}{{item_title}}</a>
	{{submenu}}
</li>
<!-- /kami:template -->

<!-- kami:template topnav_children -->
	<ul class="kc-subnav">{{menu_children}}</ul>
<!-- /kami:template -->

<!-- kami:template context -->
			<ul class="kc-menu">
				{{menu_items}}
			</ul>
<!-- /kami:template -->

<!-- kami:template context_item -->
<li><a href="{{item_url}}">{{rendered_icon}} {{item_title}}</a></li>
<!-- /kami:template -->

<!-- kami:template item_icon -->
<svg class="icon icon-{{icon}}"></svg>
<!-- /kami:template -->


<!-- kami:template menu_list -->
<section class="admin-page"
         data-navigation-manager
         data-menu-count="{{menu_count}}"
         aria-labelledby="nm-menus-title">
    <header class="admin-page-header">
        <div>
            <h2 id="nm-menus-title" class="admin-page-title">{{phrase.menus}}</h2>
            <p class="admin-page-description">{{phrase.manage_menus}}</p>
        </div>
        <span class="admin-page-status" data-menu-status>{{menu_summary}}</span>
    </header>

    <div class="admin-toolbar">
        <span class="nm-toolbar-text">{{phrase.navigation}}</span>
        <button class="admin-button admin-button-primary"
                type="button"
                data-create-toggle
                aria-controls="nm-create-panel"
                aria-expanded="false">
            <svg class="icon icon-plus icon-sm"></svg>
            <span>{{phrase.create_menu}}</span>
        </button>
    </div>

    <div id="nm-create-panel" class="nm-create-panel" hidden>
        <form action="{{create_action}}" method="post" data-create-form>
            <h3 class="admin-panel-title">{{phrase.create_menu}}</h3>
            <div class="admin-form-fields nm-menu-fields nm-create-fields">
                {{create_fields}}
            </div>
            <div class="admin-form-actions">
                <button class="admin-button admin-button-secondary" type="button" data-create-cancel>
                    {{phrase.cancel}}
                </button>
                <button class="admin-button admin-button-primary" type="submit">
                    {{phrase.create_menu}}
                </button>
            </div>
        </form>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table admin-table-wide">
            <thead>
                <tr>
                    <th>{{phrase.menu_key}}</th>
                    <th>{{phrase.menu_name}}</th>
                    <th>{{phrase.menu_description}}</th>
                    <th class="admin-actions-heading">{{phrase.actions}}</th>
                </tr>
            </thead>
            <tbody data-menu-rows>
                {{menu_rows}}
            </tbody>
        </table>
    </div>

    <div class="admin-notice" data-menu-notice hidden aria-live="polite"></div>
</section>

<script>
(function() {
    'use strict';

    const root = document.querySelector('[data-navigation-manager]');
    if (!root) return;

    const text = {{ui_text}};
    const createToggle = root.querySelector('[data-create-toggle]');
    const createPanel = root.querySelector('#nm-create-panel');
    const createCancel = root.querySelector('[data-create-cancel]');
    const createForm = root.querySelector('[data-create-form]');
    const rows = root.querySelector('[data-menu-rows]');
    const status = root.querySelector('[data-menu-status]');
    const notice = root.querySelector('[data-menu-notice]');
    let menuCount = Number(root.dataset.menuCount || 0);

    function format(pattern, values = {}) {
        return Object.entries(values).reduce(
            (result, [key, value]) => result.replaceAll('{' + key + '}', String(value)),
            pattern
        );
    }

    function setCreatePanel(open) {
        createPanel.hidden = !open;
        createToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            createPanel.querySelector('input, textarea')?.focus();
        }
    }

    function showNotice(message, kind) {
        notice.textContent = message;
        notice.className = 'admin-notice admin-notice-' + kind;
        notice.hidden = false;
    }

    function setMenuCount(count) {
        menuCount = Math.max(0, count);
        status.textContent = format(text.menuCount, { count: menuCount });
    }

    function renderEmptyState() {
        if (rows.querySelector('[data-menu-row]')) return;

        const row = document.createElement('tr');
        row.className = 'nm-state-row';
        row.innerHTML = '<td colspan="4" class="admin-empty-state"></td>';
        row.firstElementChild.textContent = text.noMenus;
        rows.appendChild(row);
    }

    async function deleteMenu(button) {
        const row = button.closest('[data-menu-row]');
        const url = button.dataset.deleteUrl || '';
        const title = button.dataset.menuTitle || '';
        if (!row || !url || !window.confirm(format(text.confirmDeleteMenu, { title }))) {
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
                throw new Error(text.deleteFailed);
            }

            row.remove();
            rows.querySelector('.nm-state-row')?.remove();
            setMenuCount(menuCount - 1);
            renderEmptyState();
            showNotice(text.menuDeleted, 'success');
        } catch (error) {
            console.error('Navigation Manager: failed to delete menu.', error);
            button.disabled = false;
            showNotice(text.deleteFailed, 'error');
        }
    }

    createToggle?.addEventListener('click', () => setCreatePanel(createPanel.hidden));
    createCancel?.addEventListener('click', () => {
        createForm?.reset();
        setCreatePanel(false);
    });
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-delete-menu]');
        if (button) deleteMenu(button);
    });
})();
</script>
<!-- /kami:template -->

<!-- kami:template menu_list_row -->
<tr data-menu-row>
    <td class="nm-key-cell"><code>{{menu_key}}</code></td>
    <td>
        <a class="nm-menu-link" href="{{edit_link}}">{{menu_title}}</a>
    </td>
    <td class="nm-description-cell">{{menu_description}}</td>
    <td class="admin-actions-cell">
        <div class="admin-actions">
            <a class="admin-action-button"
               href="{{edit_link}}"
               title="{{phrase.edit}}"
               aria-label="{{phrase.edit}}">
                <svg class="icon icon-pencil icon-sm"></svg>
            </a>
            <button class="admin-action-button admin-action-danger"
                    type="button"
                    data-delete-menu
                    data-delete-url="{{delete_link}}"
                    data-menu-title="{{title_attribute}}"
                    title="{{phrase.delete}}"
                    aria-label="{{phrase.delete}}">
                <svg class="icon icon-trash icon-sm"></svg>
            </button>
        </div>
    </td>
</tr>
<!-- /kami:template -->

<!-- kami:template menu_list_empty -->
<tr class="nm-state-row">
    <td colspan="4" class="admin-empty-state">{{phrase.no_menus}}</td>
</tr>
<!-- /kami:template -->

<!-- kami:template menu_edit -->
<section class="admin-page nm-editor" aria-labelledby="nm-editor-title">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{cancel_url}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{phrase.back_to_menus}}</span>
            </a>
            <h2 id="nm-editor-title" class="admin-page-title">{{menu_title}}</h2>
            <p class="admin-page-description">{{phrase.edit_menu_help}}</p>
        </div>
    </header>

    <form id="menu-form" class="nm-editor-form" method="post" action="{{save_action}}">
        <section class="admin-panel nm-panel" aria-labelledby="nm-settings-title">
            <header class="admin-panel-header">
                <h3 id="nm-settings-title" class="admin-panel-title">{{phrase.menu_settings}}</h3>
            </header>
            <div class="admin-form-fields nm-panel-body nm-menu-fields">
                {{menu_fields}}
                <p class="nm-field-warning">{{menu_key_warning}}</p>
            </div>
        </section>

        <section class="admin-panel nm-panel" aria-labelledby="nm-items-title">
            <header class="admin-panel-header nm-items-header">
                <div>
                    <h3 id="nm-items-title" class="admin-panel-title">{{phrase.menu_items}}</h3>
                    <p class="admin-panel-description">{{phrase.menu_items_help}}</p>
                </div>
                <button class="admin-button admin-button-primary js-add-root-item" type="button">
                    <svg class="icon icon-plus icon-sm"></svg>
                    <span>{{phrase.add_item}}</span>
                </button>
            </header>

            <div class="nm-panel-body nm-tree-wrap">
                <ul id="menu-root" class="menu-list nm-menu-list nm-menu-root">
                    {{menu_items}}
                </ul>
            </div>
        </section>

        <footer class="admin-form-actions nm-editor-actions">
            <a class="admin-button admin-button-secondary" href="{{cancel_url}}">{{phrase.cancel}}</a>
            <button class="admin-button admin-button-primary" type="submit">
                <svg class="icon icon-save icon-sm"></svg>
                <span>{{phrase.save}}</span>
            </button>
        </footer>
    </form>
</section>

<template id="menu-item-template">
    <li class="menu-item nm-menu-item">
        <div class="nm-item-row">
            <button class="menu-item-handle nm-drag-handle"
                    type="button"
                    draggable="true"
                    title="{{phrase.drag_item}}"
                    aria-label="{{phrase.drag_item}}">
                <span aria-hidden="true">⋮⋮</span>
            </button>

            <div class="nm-item-fields">
                <div class="nm-field">
                    <label>{{phrase.title}}</label>
                    <input class="admin-input menu-title-input" type="text" placeholder="{{phrase.title}}">
                </div>
                <div class="nm-field">
                    <label>{{phrase.url}}</label>
                    <input class="admin-input menu-url-input" type="text" placeholder="/path-or-url">
                </div>
                <div class="nm-field">
                    <label>{{phrase.icon_optional}}</label>
                    <input class="admin-input menu-icon-input" type="text" placeholder="icon-name">
                </div>
                {{new_item_visibility_field}}
            </div>

            <div class="nm-item-actions">
                <button class="admin-button admin-button-small admin-button-secondary js-add-child" type="button">
                    {{phrase.add_subitem}}
                </button>
                <button class="admin-button admin-button-small admin-button-danger js-delete-item" type="button">
                    {{phrase.delete}}
                </button>
            </div>

            <input type="hidden" class="menu-id-input" value="">
        </div>
        <ul class="menu-list menu-children nm-menu-list nm-menu-children"></ul>
    </li>
</template>

<script type="module">
    import { initMenuEditor } from '/plugins/Navigation/assets/menu-editor.js';

    const form = document.getElementById('menu-form');
    if (form) initMenuEditor(form);
</script>
<!-- /kami:template -->

<!-- kami:template menu_edit_row -->
<li class="menu-item nm-menu-item">
    <div class="nm-item-row">
        <button class="menu-item-handle nm-drag-handle"
                type="button"
                draggable="true"
                title="{{phrase.drag_item}}"
                aria-label="{{phrase.drag_item}}">
            <span aria-hidden="true">⋮⋮</span>
        </button>

        <div class="nm-item-fields">
            <div class="nm-field">
                <label>{{phrase.title}}</label>
                <input class="admin-input menu-title-input" type="text" value="{{item_title}}">
            </div>
            <div class="nm-field">
                <label>{{phrase.url}}</label>
                <input class="admin-input menu-url-input" type="text" value="{{item_url}}">
            </div>
            <div class="nm-field">
                <label>{{phrase.icon_optional}}</label>
                <input class="admin-input menu-icon-input" type="text" value="{{item_icon}}">
            </div>
            {{visibility_field}}
        </div>

        <div class="nm-item-actions">
            <button class="admin-button admin-button-small admin-button-secondary js-add-child" type="button">
                {{phrase.add_subitem}}
            </button>
            <button class="admin-button admin-button-small admin-button-danger js-delete-item" type="button">
                {{phrase.delete}}
            </button>
        </div>

        <input type="hidden" class="menu-id-input" value="{{id}}">
    </div>
    <ul class="menu-list menu-children nm-menu-list nm-menu-children">{{menu_children}}</ul>
</li>
<!-- /kami:template -->

