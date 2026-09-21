<!-- kami:template overview -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <h2 class="admin-page-title">{{phrase.system}}</h2>
      <p class="admin-page-description">{{phrase.system_help}}</p>
    </div>
  </header>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <tbody>
        <tr>
          <td><strong>{{phrase.system_settings}}</strong><div class="admin-page-description">{{phrase.system_settings_help}}</div></td>
          <td class="admin-actions-cell"><a class="admin-button admin-button-action" href="{{settings_url}}">{{phrase.open}}</a></td>
        </tr>
        <tr>
          <td><strong>{{phrase.languages}}</strong><div class="admin-page-description">{{phrase.languages_help}}</div></td>
          <td class="admin-actions-cell"><a class="admin-button admin-button-action" href="{{languages_url}}">{{phrase.open}}</a></td>
        </tr>
        <tr>
          <td><strong>{{phrase.domains}}</strong><div class="admin-page-description">{{phrase.domains_help}}</div></td>
          <td class="admin-actions-cell"><a class="admin-button admin-button-action" href="{{domains_url}}">{{phrase.open}}</a></td>
        </tr>
        <tr>
          <td><strong>{{phrase.secrets}}</strong><div class="admin-page-description">{{phrase.secrets_help}}</div></td>
          <td class="admin-actions-cell"><a class="admin-button admin-button-action" href="{{secrets_url}}">{{phrase.open}}</a></td>
        </tr>
      </tbody>
    </table>
  </div>
</section>
<!-- /kami:template -->

<!-- kami:template settings -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_system}}</span></a>
      <h2 class="admin-page-title">{{phrase.system_settings}}</h2>
      <p class="admin-page-description">{{phrase.system_settings_help}}</p>
    </div>
  </header>
  {{notice}}
  <form method="post" action="{{save_url}}" class="admin-form">
      <div class="admin-form-fields">{{settings_fields}}</div>
    <footer class="admin-form-actions" style="padding:16px 20px 20px">
      <a class="admin-button admin-button-secondary" href="{{back_url}}">{{phrase.cancel}}</a>
      <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
    </footer>
  </form>
</section>
<!-- /kami:template -->

<!-- kami:template setting-row -->
<div style="padding:16px 18px;border-bottom:1px solid var(--admin-border)">
  {{field}}
  <p class="admin-panel-description">{{description}}</p>
</div>
<!-- /kami:template -->

<!-- kami:template languages -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_system}}</span></a>
      <h2 class="admin-page-title">{{phrase.languages}}</h2>
      <p class="admin-page-description">{{phrase.languages_help}}</p>
    </div>
  </header>
  {{notice}}
  <form method="post" action="{{save_url}}" class="admin-form">
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-wide">
        <thead><tr><th>{{phrase.language}}</th><th>{{phrase.code}}</th><th>{{phrase.active}}</th><th>{{phrase.used_by_domains}}</th></tr></thead>
        <tbody>{{language_rows}}</tbody>
      </table>
    </div>
    <footer class="admin-form-actions" style="padding:16px 20px 20px">
      <a class="admin-button admin-button-secondary" href="{{back_url}}">{{phrase.cancel}}</a>
      <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
    </footer>
  </form>
</section>
<!-- /kami:template -->

<!-- kami:template language-row -->
<tr>
  <td><strong>{{language_name}}</strong></td>
  <td><code>{{language_code}}</code></td>
  <td>
    {{required_value}}
    <input type="checkbox" name="active_languages[]" value="{{language_code}}"{{checked}}{{disabled}}>
  </td>
  <td>{{used_by}}</td>
</tr>
<!-- /kami:template -->

<!-- kami:template domains -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_system}}</span></a>
      <h2 class="admin-page-title">{{phrase.domains}}</h2>
      <p class="admin-page-description">{{phrase.domains_help}}</p>
    </div>
    <a class="admin-button admin-button-primary" href="{{create_url}}">{{phrase.create_domain}}</a>
  </header>
  <div class="admin-table-wrap">
    <table class="admin-table admin-table-wide">
      <thead><tr><th>{{phrase.domain_name}}</th><th>{{phrase.theme}}</th><th>{{phrase.setting_languages}}</th><th class="admin-actions-heading">{{phrase.actions}}</th></tr></thead>
      <tbody>{{domain_rows}}</tbody>
    </table>
  </div>
</section>
<!-- /kami:template -->

<!-- kami:template domain-row -->
<tr>
  <td><strong>{{domain_name}}</strong>{{root_badge}}</td>
  <td>{{theme}}</td>
  <td>{{languages}}</td>
  <td class="admin-actions-cell"><a class="admin-button admin-button-action admin-button-small" href="{{edit_url}}">{{phrase.edit_domain}}</a></td>
</tr>
<!-- /kami:template -->

<!-- kami:template domain-edit -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_domains}}</span></a>
      <h2 class="admin-page-title">{{page_title}}</h2>
    </div>
  </header>
  {{notice}}
</section>
  <form method="post" action="{{save_url}}" class="admin-form">
    <input type="hidden" name="domain_id" value="{{domain_id}}">
    <section class="admin-panel">
      <header class="admin-panel-header"><h3 class="admin-panel-title">{{phrase.domain_settings}}</h3></header>
      <div class="admin-form-fields">{{property_fields}}{{domain_fields}}</div>
    </section>
    <section class="admin-panel">
      <header class="admin-panel-header"><div><h3 class="admin-panel-title">{{phrase.domain_overrides}}</h3><p class="admin-panel-description">{{phrase.domain_overrides_help}}</p></div></header>
      <div class="admin-form-fields">{{override_fields}}</div>
    </section>
    <footer class="admin-form-actions" style="padding:16px 20px 20px">
      <a class="admin-button admin-button-secondary" href="{{back_url}}">{{phrase.cancel}}</a>
      <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
    </footer>
  </form>

<!-- /kami:template -->

<!-- kami:template override-row -->
<div style="padding:16px 18px;border-bottom:1px solid var(--admin-border)">
  <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px"><input type="checkbox" name="{{override_name}}" value="1"{{checked}}> {{phrase.override}}</label>
  {{field}}
  <p class="admin-panel-description">{{description}} {{phrase.global_value}}: <strong>{{global_value}}</strong></p>
</div>
<!-- /kami:template -->

<!-- kami:template secrets -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_system}}</span></a>
      <h2 class="admin-page-title">{{phrase.secrets}}</h2>
      <p class="admin-page-description">{{phrase.secrets_help}}</p>
    </div>
  </header>
  {{notice}}
</section>

  <section class="admin-panel">
    <header class="admin-panel-header"><h3 class="admin-panel-title">{{phrase.add_secret}}</h3></header>
    <form method="post" action="{{save_url}}" class="admin-form" style="padding:16px 18px">
      <div class="admin-form-fields">{{secret_fields}}</div>
      <div class="admin-form-actions" style="margin-top:16px"><button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button></div>
    </form>
  </section>
  <section class="admin-panel">
    <header class="admin-panel-header"><h3 class="admin-panel-title">{{phrase.stored_secrets}}</h3></header>
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-wide">
        <thead><tr><th>{{phrase.namespace}}</th><th>{{phrase.secret_name}}</th><th>{{phrase.scope}}</th><th>{{phrase.updated}}</th><th class="admin-actions-heading">{{phrase.actions}}</th></tr></thead>
        <tbody>{{secret_rows}}</tbody>
      </table>
    </div>
  </section>

<!-- /kami:template -->

<!-- kami:template secret-row -->
<tr>
  <td>{{namespace}}</td><td><code>{{secret_name}}</code></td><td>{{scope}}</td><td>{{updated}}</td>
  <td class="admin-actions-cell">
    <form method="post" action="{{delete_url}}" onsubmit="return confirm('{{confirm}}')">
      <input type="hidden" name="namespace" value="{{namespace_attr}}">
      <input type="hidden" name="secret_name" value="{{secret_name_attr}}">
      <input type="hidden" name="domain_id" value="{{domain_id}}">
      <button class="admin-button admin-button-danger admin-button-small" type="submit">{{phrase.delete}}</button>
    </form>
  </td>
</tr>
<!-- /kami:template -->
