<!-- kami:template overview -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <h2 class="admin-page-title">{{phrase.users_access}}</h2>
      <p class="admin-page-description">{{phrase.users_access_help}}</p>
    </div>
  </header>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <tbody>
        <tr>
          <td><strong>{{phrase.users}}</strong><div class="admin-page-description">{{phrase.users_help}}</div></td>
          <td class="admin-actions-cell"><a class="admin-button admin-button-action" href="{{users_url}}">{{phrase.open}}</a></td>
        </tr>
        <tr>
          <td><strong>{{phrase.groups_acl}}</strong><div class="admin-page-description">{{phrase.groups_acl_help}}</div></td>
          <td class="admin-actions-cell"><a class="admin-button admin-button-action" href="{{groups_url}}">{{phrase.open}}</a></td>
        </tr>
      </tbody>
    </table>
  </div>
</section>
<!-- /kami:template -->

<!-- kami:template users -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back}}</span></a>
      <h2 class="admin-page-title">{{phrase.users}}</h2>
      <p class="admin-page-description">{{phrase.users_help}}</p>
    </div>
    <div class="admin-actions-cell">
      <a class="admin-button admin-button-secondary" href="{{groups_url}}">{{phrase.groups_acl}}</a>
      <a class="admin-button admin-button-primary" href="{{create_url}}">{{phrase.create_user}}</a>
    </div>
  </header>
  <div class="admin-table-wrap">
    <table class="admin-table admin-table-wide">
      <thead><tr><th>{{phrase.username}}</th><th>{{phrase.email}}</th><th>{{phrase.group}}</th><th>{{phrase.status}}</th><th>{{phrase.email_verification}}</th><th class="admin-actions-heading">{{phrase.actions}}</th></tr></thead>
      <tbody>{{user_rows}}</tbody>
    </table>
  </div>
</section>
<!-- /kami:template -->

<!-- kami:template user-row -->
<tr>
  <td><strong>{{username}}</strong></td>
  <td>{{email}}</td>
  <td>{{group}} <span class="admin-page-description">({{group_name}})</span></td>
  <td>{{status}}</td>
  <td>{{verification}}</td>
  <td class="admin-actions-cell"><a class="admin-button admin-button-action admin-button-small" href="{{edit_url}}">{{phrase.edit}}</a></td>
</tr>
<!-- /kami:template -->

<!-- kami:template user-edit -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_users}}</span></a>
      <h2 class="admin-page-title">{{page_title}}</h2>
    </div>
  </header>
  <form method="post" action="{{save_url}}" class="admin-form">
    <input type="hidden" name="user_id" value="{{user_id}}">
    <section class="simple admin-pad">
      <div class="admin-form-fields">{{fields}}{{password_field}}</div>
    </section>
    <footer class="admin-form-actions" style="padding:16px 20px 20px">
      <a class="admin-button admin-button-secondary" href="{{back_url}}">{{phrase.cancel}}</a>
      <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
    </footer>
  </form>
</section>
<!-- /kami:template -->

<!-- kami:template groups -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back}}</span></a>
      <h2 class="admin-page-title">{{phrase.groups_acl}}</h2>
      <p class="admin-page-description">{{phrase.groups_acl_help}}</p>
    </div>
    <div class="admin-actions-cell">
      <a class="admin-button admin-button-secondary" href="{{users_url}}">{{phrase.users}}</a>
      <a class="admin-button admin-button-primary" href="{{create_url}}">{{phrase.create_group}}</a>
    </div>
  </header>
  <div class="admin-table-wrap">
    <table class="admin-table admin-table-wide">
      <thead><tr><th>{{phrase.group}}</th><th>{{phrase.description}}</th><th>{{phrase.users}}</th><th class="admin-actions-heading">{{phrase.actions}}</th></tr></thead>
      <tbody>{{group_rows}}</tbody>
    </table>
  </div>
</section>
<!-- /kami:template -->

<!-- kami:template group-row -->
<tr>
  <td><strong>{{title}}</strong>{{system_badge}}<div class="admin-page-description">{{name}}</div></td>
  <td>{{description}}</td>
  <td>{{users}}</td>
  <td class="admin-actions-cell">
    <a class="admin-button admin-button-action admin-button-small" href="{{edit_url}}">{{phrase.edit}}</a>
    <a class="admin-button admin-button-action admin-button-small" href="{{acl_url}}">{{phrase.permissions}}</a>
    {{delete}}
  </td>
</tr>
<!-- /kami:template -->

<!-- kami:template group-delete -->
<form method="post" action="{{delete_url}}" onsubmit="return confirm('{{confirm}}')">
  <input type="hidden" name="usergroup_id" value="{{group_id}}">
  <button class="admin-button admin-button-danger admin-button-small" type="submit">{{phrase.delete}}</button>
</form>
<!-- /kami:template -->

<!-- kami:template group-edit -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_groups}}</span></a>
      <h2 class="admin-page-title">{{page_title}}</h2>
    </div>
  </header>
  <form method="post" action="{{save_url}}" class="admin-form">
    <input type="hidden" name="usergroup_id" value="{{group_id}}">
    <section class="simple admin-pad"><div class="admin-form-fields">{{fields}}</div></section>
    <footer class="admin-form-actions" style="padding:16px 20px 20px">
      <a class="admin-button admin-button-secondary" href="{{back_url}}">{{phrase.cancel}}</a>
      <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
    </footer>
  </form>
</section>
<!-- /kami:template -->

<!-- kami:template acl -->
<section class="admin-panel">
		<header class="admin-page-header">
    <div>
      <a class="admin-back-link" href="{{back_url}}"><svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg><span>{{phrase.back_to_groups}}</span></a>
      <h2 class="admin-page-title">{{phrase.permissions}}: {{group_title}}</h2>
      <p class="admin-page-description">{{group_name}}</p>
    </div>
  </header>
</section>

  <form method="post" action="{{save_url}}" class="admin-form">
    <input type="hidden" name="usergroup_id" value="{{group_id}}">

    <section class="admin-panel">
      <header class="admin-panel-header"><div><h3 class="admin-panel-title">{{phrase.pages}}</h3><p class="admin-panel-description">{{phrase.pages_help}}</p></div></header>
      <div style="padding:4px 18px 18px">{{page_fields}}</div>
    </section>

    <section class="admin-panel">
      <header class="admin-panel-header"><div><h3 class="admin-panel-title">{{phrase.plugins}}</h3><p class="admin-panel-description">{{phrase.plugins_help}}</p></div></header>
      <div style="padding:4px 18px 18px">{{plugin_fields}}</div>
    </section>

    <section class="admin-panel">
      <header class="admin-panel-header"><div><h3 class="admin-panel-title">{{phrase.content}}</h3><p class="admin-panel-description">{{phrase.content_help}}</p></div></header>
      <div class="admin-table-wrap">
        <table class="admin-table admin-table-wide"><thead><tr><th>{{phrase.content_type}}</th><th>{{phrase.permissions}}</th></tr></thead><tbody>{{content_rows}}</tbody></table>
      </div>
    </section>

    <footer class="admin-form-actions" style="padding:16px 20px 20px">
      <a class="admin-button admin-button-secondary" href="{{back_url}}">{{phrase.cancel}}</a>
      <button class="admin-button admin-button-primary" type="submit">{{phrase.save}}</button>
    </footer>
  </form>

<!-- /kami:template -->

<!-- kami:template content-acl-row -->
<tr><td><strong>{{content_type}}</strong></td><td>{{capabilities}}</td></tr>
<!-- /kami:template -->
