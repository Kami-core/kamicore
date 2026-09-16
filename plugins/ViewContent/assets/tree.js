(() => {
    'use strict';

    if (window.__viewContentTreeInitialized) return;
    window.__viewContentTreeInitialized = true;

    document.addEventListener('toggle', async (event) => {
        const node = event.target;
        if (!(node instanceof HTMLDetailsElement) || !node.open) return;
        if (!node.matches('[data-viewcontent-node]')) return;
        if (node.dataset.loaded !== '0' || node.dataset.loading === '1') return;

        const tree = node.closest('[data-viewcontent-tree]');
        const children = node.querySelector(':scope > [data-viewcontent-children]');
        if (!tree || !children) return;

        node.dataset.loading = '1';

        const params = new URLSearchParams({
            parent_id: node.dataset.itemId || '',
            base_url: tree.dataset.baseUrl || '',
            content_types: tree.dataset.contentTypes || '',
            sort_field: tree.dataset.sortField || '',
            sort_direction: tree.dataset.sortDirection || 'asc'
        });

        try {
            const response = await fetch(`${tree.dataset.endpoint}?${params.toString()}`, {
                credentials: 'same-origin',
                headers: {'Accept': 'text/html'}
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            children.innerHTML = await response.text();
            node.dataset.loaded = '1';
        } catch (error) {
            console.error('ViewContent tree loading failed.', error);
        } finally {
            delete node.dataset.loading;
        }
    }, true);
})();
