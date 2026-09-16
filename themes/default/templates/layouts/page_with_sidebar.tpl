<!DOCTYPE html>
<html lang="{{language_code}}">
<head>
{{template:pageheader}}
</head>

<body class="kami kami-frontend kami-wide">

{{template:bodyheader}}

<section class="kc-section kc-content-section">
<div class="kc-container">



	<div class="sidebar-grid">

		<div class="sidebar">
			<input
				type="checkbox"
				id="sidebar-toggle"
				class="sidebar-toggle"
			>

			<label for="sidebar-toggle" class="sidebar-toggle-label card">
				<span>{{sidebar_label}}(toggle)</span>

				<span class="sidebar-toggle-icon">
					<svg class="icon icon-chevron-down" aria-hidden="true"></svg>
				</span>
			</label>

			<div class="sidebar-content">
				<div class="card">
				{{sidebar_plugins}}
				</div>
			</div>
		</div>

		<div>
			{{content_plugins}}
		</div>

	</div>



</section>

{{template:footer}}

</body>
</html>
