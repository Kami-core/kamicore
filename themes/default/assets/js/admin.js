(function () {

    const storageKey = 'kami.admin.sidebar.collapsed';
    const shell = document.querySelector('[data-admin-shell]');
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');


    if (!shell || !toggle) {
        return;
    }

    const setCollapsed = collapsed => {
        shell.toggleAttribute('data-sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', String(!collapsed));
		document.documentElement.classList.toggle('admin-sidebar-collapsed', collapsed);

        localStorage.setItem(storageKey, collapsed ? '1' : '0');
    };

    setCollapsed(localStorage.getItem(storageKey) === '1');

    toggle.addEventListener('click', () => {
        setCollapsed(!shell.hasAttribute('data-sidebar-collapsed'));
    });

})();
