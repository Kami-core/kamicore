<!-- kami:template page -->
<div>{{content}}</div>
<!-- /kami:template -->

<!-- kami:template single -->
<div>{{content}}</div>
<!-- /kami:template -->

<!-- kami:template default -->
<div>
	{{title}}
	{{content}}
</div>
<!-- /kami:template -->

<!-- kami:template responsive_grid -->
	{{title}}

	<div class="responsive_grid" style="--columns: {{max_items_per_row}}; --min-width: {{min_item_width}}px; --gap: 20px;">
		{{content}}
	</div>
<!-- /kami:template -->

<!-- kami:template card -->
<div class='card'>{{content}}</div>
<!-- /kami:template -->

<!-- kami:template compact -->
<aside class='callout'>{{content}}</aside>
<!-- /kami:template -->

<!-- kami:template block_title -->
<h3>{{content}}</h3>
<!-- /kami:template -->
