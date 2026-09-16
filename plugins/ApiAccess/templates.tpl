<!-- kami:template token-list -->
<div class="aa-page">
  <header>
    <div>
      <h2 class="admin-page-title">{{phrase.title}}</h2>
      <p class="admin-page-description">{{phrase.description}}</p>
    </div>
    <div class="kc-btn-row is-left">
      <a class="kc-btn kc-btn-primary" href="{{create_url}}">{{phrase.create}}</a>
    </div>
  </header>
  {{notice}}
  <div class="kc-table-wrap">
    <table class="kc-table">
      <thead>
        <tr>
          <th>{{phrase.name}}</th>
          <th>{{phrase.token}}</th>
          <th>{{phrase.created}}</th>
          <th>{{phrase.last_used}}</th>
          <th>{{phrase.expires}}</th>
          <th>{{phrase.access}}</th>
          <th>{{phrase.status}}</th>
          <th class="kc-table-actions-heading">{{phrase.actions}}</th>
        </tr>
      </thead>
      <tbody>{{token_rows}}</tbody>
    </table>
  </div>
</div>
<!-- /kami:template -->

<!-- kami:template token-row -->
<tr>
  <td><strong>{{name}}</strong></td>
  <td><code>{{token_hint}}</code></td>
  <td>{{created}}</td>
  <td>{{last_used}}</td>
  <td>{{expires}}</td>
  <td>{{access}}</td>
  <td><strong>{{status}}</strong></td>
  <td class="kc-table-actions-cell">{{actions_html}}</td>
</tr>
<!-- /kami:template -->

<!-- kami:template tokens-empty -->
<tr><td colspan="8" class="kc-table-empty">{{phrase.no_tokens}}</td></tr>
<!-- /kami:template -->

<!-- kami:template token-actions -->
<div class="kc-btn-group">{{actions}}</div>
<!-- /kami:template -->

<!-- kami:template token-action-edit -->
<a class="kc-btn kc-btn-secondary kc-btn-icon" href="{{edit_url}}" title="{{phrase.edit}}"><svg class="icon icon-settings"></svg></a>
<!-- /kami:template -->

<!-- kami:template token-action-enable -->
<form method="post" action="{{action_url}}">
  <input type="hidden" name="token_id" value="{{token_id}}">
  <button class="kc-btn kc-btn-secondary kc-btn-icon" type="submit" title="{{phrase.enable}}"><svg class="icon icon-refresh-cw"></svg></button>
</form>
<!-- /kami:template -->

<!-- kami:template token-action-disable -->
<form method="post" action="{{action_url}}" onsubmit="return confirm({{confirm}})">
  <input type="hidden" name="token_id" value="{{token_id}}">
  <button class="kc-btn kc-btn-secondary kc-btn-icon" type="submit" title="{{phrase.disable}}"><svg class="icon icon-octagon-pause"></svg></button>
</form>
<!-- /kami:template -->

<!-- kami:template token-action-revoke -->
<form method="post" action="{{action_url}}" onsubmit="return confirm({{confirm}})">
  <input type="hidden" name="token_id" value="{{token_id}}">
  <button class="kc-btn kc-btn-danger kc-btn-icon" type="submit" title="{{phrase.revoke}}"><svg class="icon icon-octagon-x"></svg></button>
</form>
<!-- /kami:template -->

<!-- kami:template token-action-delete -->
<form method="post" action="{{action_url}}" onsubmit="return confirm({{confirm}})">
  <input type="hidden" name="token_id" value="{{token_id}}">
  <button class="kc-btn kc-btn-danger kc-btn-icon" type="submit" title="{{phrase.delete}}"><svg class="icon icon-trash-2"></svg></button>
</form>
<!-- /kami:template -->

<!-- kami:template token-edit -->
<div class="aa-page">
  <header>
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.cancel}}</span></a>
      <h2 class="admin-page-title">{{page_title}}</h2>
    </div>
  </header>
  {{notice}}
  <form method="post" action="{{save_url}}" class="admin-form">
    <input type="hidden" name="token_id" value="{{token_id}}">
    <section class="card">
      <div class="admin-form-fields">{{fields}}{{token_info}}</div>
    </section>
    {{permissions_html}}
    <footer class="admin-form-actions" style="padding:16px 20px 20px">
      <a class="admin-button admin-button-secondary" href="{{back_url}}">{{phrase.cancel}}</a>
      <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
    </footer>
  </form>
</div>
<!-- /kami:template -->

<!-- kami:template token-info -->
<div class="form-field">
  <label>{{phrase.token_hint}}</label>
  <div><code>{{token_hint}}</code></div>
  <p class="form-hint">{{phrase.token_hint_help}}</p>
</div>
<div class="form-field">
  <label>{{phrase.created_at}}</label>
  <div>{{created_at}}</div>
</div>
<!-- /kami:template -->

<!-- kami:template permissions -->
<div class="aa-page">
  <header class="admin-panel-header">
    <div>
      <h3 class="admin-panel-title">{{phrase.permissions}}</h3>
    </div>
  </header>
  <div class="card">
    <div class="aa-permission-section">
      <h3>{{phrase.api_actions}}</h3>
      <p class="admin-page-description">{{phrase.api_actions_help}}</p>
      {{api_actions}}
    </div>
    <div class="aa-permission-section">
      <h3>{{phrase.content_access}}</h3>
      <p class="admin-page-description">{{phrase.content_access_help}}</p>
      <div class="kc-table-wrap">
        <table class="kc-table">
          <tbody>{{content_rows}}</tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<style>
.aa-permissions-body{padding:4px 18px 18px}.aa-permission-section+.aa-permission-section{margin-top:24px}.aa-permission-group{margin:14px 0}.aa-permission-group h4{margin:0 0 8px}.aa-permission-option{display:inline-flex;align-items:center;gap:7px;margin:4px 16px 4px 0}.aa-permission-code{opacity:.65;font-size:.85em}
</style>
<!-- /kami:template -->

<!-- kami:template token-created -->
<div class="aa-page">
    <header>
      <div>
        <h2 class="admin-panel-title">{{phrase.token_created}}</h2>
        <p class="admin-panel-description"><strong>{{phrase.token_created_help}}</strong></p>
      </div>
    </header>
    <div style="padding:18px">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <code id="aa-created-token" style="overflow-wrap:anywhere">{{token}}</code>
        <button class="admin-button admin-button-primary" type="button" id="aa-copy-token">{{phrase.copy}}</button>
      </div>
      <div style="margin-top:18px">
        <a class="admin-button admin-button-secondary" href="{{done_url}}">{{phrase.done}}</a>
      </div>
    </div>
</div>
<script>
(() => {
  const button = document.getElementById('aa-copy-token');
  const token = document.getElementById('aa-created-token');
  if (!button || !token) return;
  button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(token.textContent || '');
      button.textContent = '{{phrase.copied}}';
    } catch (error) {
      window.getSelection()?.selectAllChildren(token);
    }
  });
})();
</script>
<!-- /kami:template -->

<!-- kami:template notice-success -->
<div class="kc-notice kc-notice-success" role="status">{{message}}</div>
<!-- /kami:template -->

<!-- kami:template notice-error -->
<div class="kc-notice kc-notice-error" role="alert">{{message}}</div>
<!-- /kami:template -->
