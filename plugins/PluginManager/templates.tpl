<!-- kami:template plugins -->
<section class="admin-panel">
    <header class="admin-page-header">
        <div>
            <h2 class="admin-page-title">{{phrase.plugins}}</h2>
            <p class="admin-page-description">Installed and available plugin packages.</p>
        </div>
    </header>
    <div class="admin-table-wrap">
        <table class="admin-table admin-table-top">
            <thead><tr>
                <th>Plugin</th><th>{{phrase.version}}</th><th>{{phrase.status}}</th>
                <th>{{phrase.domains}}</th><th class="admin-actions-heading">{{phrase.actions}}</th>
            </tr></thead>
            <tbody>{{plugin_rows}}</tbody>
        </table>
    </div>
</section>
<!-- /kami:template -->

<!-- kami:template plugin-category-row -->
<tr class="plm-category-row">
    <th colspan="5" scope="rowgroup">
        <span>{{category}}</span>
        <span class="plm-category-count">{{count}}</span>
    </th>
</tr>
<!-- /kami:template -->

<!-- kami:template plugin-row -->
<tr>
    <td><strong>{{title}}</strong><div class="plm-muted"><code>{{system_name}}</code></div><div class="plm-description">{{description}}</div></td>
    <td><span>{{version}}</span><div class="plm-muted">Installed: {{installed_version}}</div></td>
    <td><span class="plm-status {{status_class}}">{{status}}</span></td>
    <td>{{domains}}</td>
    <td class="admin-actions-cell"><div class="admin-actions">{{actions}}</div></td>
</tr>
<!-- /kami:template -->

<!-- kami:template plugin-detail -->
<section class="admin-panel">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_url}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{back_label}}</span>
            </a>
            <h2 class="admin-page-title">{{title}}</h2>
            <p class="admin-page-description"><code>{{system_name}}</code> · {{phrase.version}} {{version}} · prefix <code>{{prefix}}</code></p>
        </div>
    </header>
</section>
    <section class="admin-panel admin-pad plm-panel">
        <h3>Domain activation</h3>
        <form method="post" action="{{activation_action}}">
            <input type="hidden" name="plugin" value="{{system_name}}">
            <div class="plm-domain-list">{{domain_rows}}</div>
            <div class="admin-form-actions"><button class="admin-button admin-button-primary" type="submit">Save activation</button></div>
        </form>
    </section>

    <section class="admin-panel admin-pad plm-panel" data-plugin-settings data-plugin="{{system_name}}" data-settings-url="{{settings_load_url}}">
        <h3>{{phrase.settings}}</h3>
        <form method="post" action="{{settings_action}}">
            <input type="hidden" name="plugin" value="{{system_name}}">
            <div{{has_global_settings}}><h4>Global settings</h4><div class="admin-form-fields plm-settings-grid">{{global_fields}}</div></div>
            <div{{has_local_settings}}>
                <h4>Domain settings</h4>
                <label class="plm-domain-select-label">Domain
                    <select class="admin-input" name="domain_id" data-settings-domain><option value="">Select domain…</option>{{domain_options}}</select>
                </label>
                <div class="admin-form-fields plm-settings-grid" data-domain-fields></div>
            </div>
            <div class="admin-form-actions"><button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button></div>
        </form>
    </section>

<script>
(function() {
    'use strict';
    const root = document.querySelector('[data-plugin-settings]');
    if (!root) return;
    const select = root.querySelector('[data-settings-domain]');
    const fields = root.querySelector('[data-domain-fields]');
    if (!select || !fields) return;
    select.addEventListener('change', async () => {
        fields.innerHTML = '';
        if (!select.value) return;
        const url = root.dataset.settingsUrl + '?plugin=' + encodeURIComponent(root.dataset.plugin)
            + '&domain_id=' + encodeURIComponent(select.value);
        try {
            const response = await fetch(url, {credentials: 'same-origin', headers: {'Accept':'application/json'}});
            const data = await response.json();
            if (!response.ok || data.status !== 'ok') throw new Error(data.error || 'Failed to load settings.');
            fields.innerHTML = data.html || '';
            window.Kami?.executeScripts?.(fields);
            window.Kami?.initDynamic?.(fields);
        } catch (error) {
            fields.textContent = error.message;
        }
    });
})();
</script>
<!-- /kami:template -->

<!-- kami:template domain-row -->
<label class="plm-domain-row"><input type="checkbox" name="domains[]" value="{{domain_id}}"{{checked}}> <span>{{domain_name}}</span></label>
<!-- /kami:template -->

<!-- kami:template setup -->
<section class="admin-page" data-setup-wizard data-plugins='{{plugins_json}}' data-domains='{{domains_json}}' data-selected-plugin="{{selected_plugin}}" data-plan-url="{{plan_url}}" data-apply-url="{{apply_url}}">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="{{back_url}}">
                <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
                <span>{{back_label}}</span>
            </a>
            <h2 class="admin-page-title">Plugin setup</h2>
            <p class="admin-page-description">Build and validate a setup plan before changing the site.</p>
        </div>
    </header>
    <section class="admin-panel plm-panel">
        <div class="plm-setup-controls">
            <label>Plugin<select class="admin-input" data-setup-plugin></select></label>
            <label>Preset<select class="admin-input" data-setup-preset></select></label>
            <label>Domain<select class="admin-input" data-setup-domain></select></label>
            <button class="admin-button admin-button-primary" type="button" data-build-plan>Build plan</button>
        </div>
    </section>
    <section class="admin-panel plm-panel" data-plan-panel hidden>
        <div data-plan-summary></div>
        <pre class="plm-plan-json" data-plan-json></pre>
        <div class="admin-form-actions"><button class="admin-button admin-button-primary" type="button" data-apply-plan disabled>Apply</button></div>
    </section>
    <div class="kc-notice" data-setup-notice hidden></div>
</section>
<script>
(function() {
    'use strict';
    const root = document.querySelector('[data-setup-wizard]');
    if (!root) return;
    const plugins = JSON.parse(root.dataset.plugins || '{}');
    const domains = JSON.parse(root.dataset.domains || '[]');
    const pluginSelect = root.querySelector('[data-setup-plugin]');
    const presetSelect = root.querySelector('[data-setup-preset]');
    const domainSelect = root.querySelector('[data-setup-domain]');
    const panel = root.querySelector('[data-plan-panel]');
    const summary = root.querySelector('[data-plan-summary]');
    const json = root.querySelector('[data-plan-json]');
    const apply = root.querySelector('[data-apply-plan]');
    const notice = root.querySelector('[data-setup-notice]');
    let plan = null;

    function option(value, label) { const el=document.createElement('option'); el.value=value; el.textContent=label; return el; }
    pluginSelect.append(option('', 'Select plugin…'));
    Object.entries(plugins).forEach(([name, data]) => pluginSelect.append(option(name, data.title || name)));
    domains.forEach(domain => domainSelect.append(option(domain.id, domain.name)));

    function loadPresets() {
        presetSelect.replaceChildren(option('', 'Select preset…'));
        const data = plugins[pluginSelect.value];
        (data?.presets || []).forEach(name => presetSelect.append(option(name, name)));
    }
    pluginSelect.addEventListener('change', loadPresets);
    if (root.dataset.selectedPlugin && plugins[root.dataset.selectedPlugin]) {
        pluginSelect.value = root.dataset.selectedPlugin; loadPresets();
    }

    root.querySelector('[data-build-plan]').addEventListener('click', async () => {
        notice.hidden = true; panel.hidden = true; plan = null; apply.disabled = true;
        try {
            const url = root.dataset.planUrl + '?plugin=' + encodeURIComponent(pluginSelect.value)
                + '&preset=' + encodeURIComponent(presetSelect.value)
                + '&domain_id=' + encodeURIComponent(domainSelect.value);
            const response = await fetch(url, {credentials:'same-origin', headers:{'Accept':'application/json'}});
            const data = await response.json();
            if (!response.ok || data.status !== 'ok') throw new Error(data.error || 'Failed to build setup plan.');
            plan = data.plan; panel.hidden = false;
            summary.textContent = plan.valid ? 'Plan is valid.' : 'Plan has collisions that must be resolved.';
            json.textContent = JSON.stringify(plan, null, 2);
            apply.disabled = !plan.valid;
        } catch (error) {
            notice.textContent = error.message; notice.className='kc-notice kc-notice-error'; notice.hidden=false;
        }
    });

    apply.addEventListener('click', async () => {
        if (!plan || !plan.valid) return;
        apply.disabled = true; notice.hidden = true;
        try {
            const response = await fetch(root.dataset.applyUrl, {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/json','Accept':'application/json'},
                body:JSON.stringify({plan})
            });
            const data = await response.json();
            if (!response.ok || data.status !== 'ok') throw new Error(data.error || 'Setup failed.');
            notice.textContent = 'Setup completed.'; notice.className='kc-notice kc-notice-success'; notice.hidden=false;
        } catch (error) {
            notice.textContent = error.message; notice.className='kc-notice kc-notice-error'; notice.hidden=false; apply.disabled=false;
        }
    });
})();
</script>
<!-- /kami:template -->
