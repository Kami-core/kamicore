// Navigation Manager menu tree editor.
// Handles item creation, nesting, drag-and-drop reordering, deletion and form serialization.

let newItemSequence = 0;

function createMenuItemElement(form) {
    const template = form.querySelector('#menu-item-template')
        || document.getElementById('menu-item-template');
    if (!template) {
        console.error('Navigation Manager: menu item template not found.');
        return null;
    }

    const item = template.content.firstElementChild?.cloneNode(true) || null;
    if (!item) return null;

    const groups = item.querySelector('.menu-groups-input');
    if (groups) {
        const field = groups.closest('.form-field');
        const label = field?.querySelector('label');
        const id = `menu-groups-new-${++newItemSequence}`;
        groups.id = id;
        if (label) label.htmlFor = id;
    }

    return item;
}

function initEntityFields(root) {
    window.Kami?.initDynamic?.(root);
}

function directItems(list) {
    return Array.from(list.children).filter(element => element.classList.contains('menu-item'));
}

function rebuildInputNames(form) {
    const rootList = form.querySelector('#menu-root');
    if (!rootList) return;

    function processList(list, pathPrefix) {
        directItems(list).forEach((item, index) => {
            const basePath = `${pathPrefix}[${index}]`;
            const id = item.querySelector(':scope > .nm-item-row .menu-id-input');
            const title = item.querySelector(':scope > .nm-item-row .menu-title-input');
            const url = item.querySelector(':scope > .nm-item-row .menu-url-input');
            const icon = item.querySelector(':scope > .nm-item-row .menu-icon-input');
            const groups = item.querySelector(':scope > .nm-item-row .menu-groups-input');
            const children = item.querySelector(':scope > .menu-children');

            if (id) id.name = `${basePath}[item_id]`;
            if (title) title.name = `${basePath}[item_title]`;
            if (url) url.name = `${basePath}[item_url]`;
            if (icon) icon.name = `${basePath}[item_icon]`;
            if (groups) groups.name = `${basePath}[visible_to_groups][]`;
            if (children) processList(children, `${basePath}[children]`);
        });
    }

    processList(rootList, 'items');
}

function initMenuEditor(form) {
    if (!form || form.dataset.navigationMenuEditorReady === '1') return;
    form.dataset.navigationMenuEditorReady = '1';

    const rootList = form.querySelector('#menu-root');
    if (!rootList) {
        console.warn('Navigation Manager: root menu list not found.');
        return;
    }

    let draggedItem = null;

    function clearDragState() {
        form.querySelectorAll('.nm-menu-item-dragging').forEach(item => {
            item.classList.remove('nm-menu-item-dragging');
        });
        form.querySelectorAll('.nm-menu-list-over').forEach(list => {
            list.classList.remove('nm-menu-list-over');
        });
        draggedItem = null;
    }

    form.addEventListener('click', event => {
        const addRoot = event.target.closest('.js-add-root-item');
        if (addRoot) {
            event.preventDefault();
            const item = createMenuItemElement(form);
            if (item) {
                rootList.appendChild(item);
                initEntityFields(item);
                item.querySelector('.menu-title-input')?.focus();
            }
            return;
        }

        const addChild = event.target.closest('.js-add-child');
        if (addChild) {
            event.preventDefault();
            const parentItem = addChild.closest('.menu-item');
            const childList = parentItem?.querySelector(':scope > .menu-children');
            const item = createMenuItemElement(form);
            if (childList && item) {
                childList.appendChild(item);
                initEntityFields(item);
                item.querySelector('.menu-title-input')?.focus();
            }
            return;
        }

        const deleteButton = event.target.closest('.js-delete-item');
        if (deleteButton) {
            event.preventDefault();
            deleteButton.closest('.menu-item')?.remove();
        }
    });

    form.addEventListener('dragstart', event => {
        const handle = event.target.closest('.menu-item-handle');
        if (!handle) return;

        const item = handle.closest('.menu-item');
        if (!item) return;

        draggedItem = item;
        item.classList.add('nm-menu-item-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'navigation-menu-item');
    });

    form.addEventListener('dragover', event => {
        if (!draggedItem) return;

        const list = event.target.closest('.menu-list');
        if (!list || !form.contains(list) || draggedItem.contains(list)) return;

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        form.querySelectorAll('.nm-menu-list-over').forEach(current => {
            if (current !== list) current.classList.remove('nm-menu-list-over');
        });
        list.classList.add('nm-menu-list-over');

        const targetItem = event.target.closest('.menu-item');
        if (!targetItem || targetItem === draggedItem || targetItem.parentElement !== list) {
            if (!targetItem) list.appendChild(draggedItem);
            return;
        }

        const rect = targetItem.getBoundingClientRect();
        const before = event.clientY < rect.top + rect.height / 2;
        list.insertBefore(draggedItem, before ? targetItem : targetItem.nextSibling);
    });

    form.addEventListener('drop', event => {
        if (!draggedItem) return;
        event.preventDefault();
        clearDragState();
    });

    form.addEventListener('dragend', clearDragState);
    form.addEventListener('submit', () => rebuildInputNames(form));
}


function initMenuEditors(root = document) {
    if (root instanceof Element && root.matches('[data-navigation-menu-editor]')) {
        initMenuEditor(root);
    }
    root.querySelectorAll?.('[data-navigation-menu-editor]').forEach(initMenuEditor);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initMenuEditors(document), {once: true});
} else {
    initMenuEditors(document);
}

window.Kami?.registerInitializer?.(initMenuEditors);
