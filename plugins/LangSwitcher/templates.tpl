<!-- kami:template simple -->
<style>
	.ls-simple {
		display: flex;
		align-items: center;
		gap: 0;
	}

	.ls-simple a, .ls-simple span {
		color: inherit;
		text-decoration: none;
		text-transform: uppercase;
		opacity: .7;
	}

	.ls-simple a:hover {
		opacity: 1;
	}

	.ls-simple .active {
		font-weight: 700;
		opacity: 1;
	}

	.ls-simple * + *::before {
		content: "·";
		display: inline-block;
		margin: 0 4px;
		font-weight: 400;
		opacity: .5;
	}
</style>

<div class="ls-simple">{{items}}</div>
<!-- /kami:template -->

<!-- kami:template simple_item -->
<a class="{{active}}" href="{{url}}">{{lang_code}}</a>
<!-- /kami:template -->

<!-- kami:template simple_active_item -->
<span class="{{active}}">{{lang_code}}</span>
<!-- /kami:template -->


<!-- kami:template flags -->
<style>
	.ls-flags {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.ls-flags a {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 3px;
		border: 1px solid transparent;
		border-radius: var(--kc-radius-sm);
		text-decoration: none;
	}

	.ls-flags a:hover,
	.ls-flags a.active {
		border-color: var(--kc-border);
		background: var(--kc-on-dark);
	}

	.ls-flags img {
		display: block;
		width: 24px;
		height: 16px;
		object-fit: cover;
	}
</style>

<div class="ls-flags">{{items}}</div>
<!-- /kami:template -->

<!-- kami:template flags_item -->
<a class="{{active}}" href="{{url}}" title="{{lang_name}}">
	<img src="{{flag_path}}/{{lang_code}}.svg" alt="{{lang_name}}">
</a>
<!-- /kami:template -->


<!-- kami:template select -->
<style>
	.ls-select {
		padding: 5px 28px 5px 8px;
		border: 1px solid var(--kc-border);
		border-radius: var(--kc-radius-sm);
		background: transparent;
		color: inherit;
		font: inherit;
		cursor: pointer;
	}
</style>

<select
	class="ls-select"
	aria-label="Language"
	onchange="if (this.value) window.location.href = this.value"
>
	{{items}}
</select>
<!-- /kami:template -->

<!-- kami:template select_item -->
<option value="{{url}}" {{selected}}>{{lang_name}}</option>
<!-- /kami:template -->
