<!-- kami:template themes -->
<section class="admin-panel">
		<header class="admin-page-header">
        <div>
            <h2 class="admin-page-title">{{phrase.themes}}</h2>
            <p class="admin-page-description">{{phrase.themes_help}}</p>
        </div>
    </header>

    {{notice}}

    <div class="admin-table-wrap">
        <table class="admin-table admin-table-top">
            <thead>
                <tr>
                    <th>{{phrase.theme}}</th>
                    <th>{{phrase.version}}</th>
                    <th>{{phrase.status}}</th>
                    <th>{{phrase.used_on}}</th>
                    <th class="admin-actions-heading">{{phrase.actions}}</th>
                </tr>
            </thead>
            <tbody>{{theme_rows}}</tbody>
        </table>
    </div>
</section>
<!-- /kami:template -->

<!-- kami:template theme-row -->
<tr>
    <td>
        <strong>{{title}}</strong>
        <div><code>{{system_name}}</code></div>
    </td>
    <td>{{version}}</td>
    <td>{{status}}</td>
    <td>{{usage}}</td>
    <td class="admin-actions-cell"><div class="admin-actions">{{actions}}</div></td>
</tr>
<!-- /kami:template -->
