<!-- kami:template default-single -->
<article class="viewcontent-single">
    <header class="viewcontent-single-header">
        <h1>{{title}}</h1>
        {{summary}}
    </header>
    <div class="viewcontent-fields">{{fields}}</div>
</article>
<!-- /kami:template -->

<!-- kami:template default-single-summary -->
<p class="viewcontent-summary">{{summary}}</p>
<!-- /kami:template -->

<!-- kami:template default-single-field -->
<div class="viewcontent-field"><strong>{{label}}:</strong> {{value}}</div>
<!-- /kami:template -->

<!-- kami:template content-list -->
<div class="viewcontent-list">{{items}}</div>
{{pagination}}
<!-- /kami:template -->

<!-- kami:template default-list-item -->
<article class="viewcontent-list-item">
    <h3>{{title}}</h3>
    {{summary}}
</article>
<!-- /kami:template -->

<!-- kami:template default-list-summary -->
<p>{{summary}}</p>
<!-- /kami:template -->

<!-- kami:template default-list-title-link -->
<a href="{{url}}">{{title}}</a>
<!-- /kami:template -->

<!-- kami:template default-list-title-text -->
<span>{{title}}</span>
<!-- /kami:template -->

<!-- kami:template default-tree-title-link -->
<a href="{{url}}" data-tooltip="{{summary}}">{{title}}</a>
<!-- /kami:template -->

<!-- kami:template default-tree-title-text -->
<span data-tooltip="{{summary}}***">{{title}}</span>
<!-- /kami:template -->

<!-- kami:template content-list-empty -->
<div class="viewcontent-empty">{{message}}</div>
<!-- /kami:template -->

<!-- kami:template content-tree -->
<nav class="viewcontent-tree" data-viewcontent-tree data-endpoint="/ajax/ViewContent/tree_children" data-base-url="{{base_url}}" data-content-types="{{content_types}}" data-sort-field="{{sort_field}}" data-sort-direction="{{sort_direction}}">
    {{nodes}}
</nav>
<!-- /kami:template -->


<!-- kami:template tree-node-branch -->
<details class="viewcontent-tree-node{{active_class}}" data-viewcontent-node data-item-id="{{item_id}}" data-loaded="{{data_loaded}}"{{open}}>
    <summary>{{item}}</summary>
    <div class="viewcontent-tree-children" data-viewcontent-children>{{children}}</div>
</details>
<!-- /kami:template -->

<!-- kami:template tree-node-leaf -->
<div class="viewcontent-tree-node viewcontent-tree-leaf{{active_class}}" data-viewcontent-node data-item-id="{{item_id}}" data-loaded="1">
    {{item}}
</div>
<!-- /kami:template -->

<!-- kami:template default-tree-item -->
{{title}}
<!-- /kami:template -->

<!-- kami:template config -->
<section class="admin-panel">
    <header class="admin-page-header">
        <div>
            <h2 class="admin-page-title">{{heading}}</h2>
            <p class="admin-page-description">{{description}}</p>
        </div>
    </header>
    {{notice}}
    <form method="post" action="{{save_action}}">
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-top">
                <thead>
                    <tr>
                        <th>{{text_enabled}}</th>
                        <th>{{text_content_type}}</th>
                        <th>{{text_single_template}}</th>
                        <th>{{text_list_template}}</th>
                        <th>{{text_tree_template}}</th>
                    </tr>
                </thead>
                <tbody>{{type_rows}}</tbody>
            </table>
        </div>
        <p class="admin-page-description">{{fallback_hint}}</p>
        <div class="admin-form-actions">
            <button class="admin-button admin-button-primary" type="submit">{{text_save}}</button>
        </div>
    </form>
</section>
<!-- /kami:template -->

<!-- kami:template config-row -->
<tr>
    <td><input type="checkbox" name="types[{{ct_id}}][enabled]" value="1"{{checked}}></td>
    <td><strong>{{title}}</strong><div class="admin-page-description"><code>{{system_name}}</code></div></td>
    <td><input class="admin-input" type="text" name="types[{{ct_id}}][single_template]" value="{{single_template}}" placeholder="default-single"></td>
    <td><input class="admin-input" type="text" name="types[{{ct_id}}][list_template]" value="{{list_template}}" placeholder="default-list-item"></td>
    <td><input class="admin-input" type="text" name="types[{{ct_id}}][tree_template]" value="{{tree_template}}" placeholder="default-tree-item"></td>
</tr>
<!-- /kami:template -->

<!-- kami:template content-list-raw -->
{{content}}
<!-- /kami:template -->
