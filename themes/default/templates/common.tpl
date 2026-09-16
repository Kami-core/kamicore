<!-- kami:template pageheader -->

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{page_title}}</title>

    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <base href="{{base_href}}">

    {{head_plugins}}

    {{system_css}}
    {{assets_css}}

	<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/themes/default/assets/css/default.css">
    {{system_js}}
    {{assets_js}}
<!-- /kami:template -->

<!-- kami:template bodyheader -->
<header class="kc-header">
    <div class="kc-container">
        <nav class="kc-navbar" aria-label="Primary navigation">
            <div class="kc-navbar-left">
                <a href="/" class="kc-logo">
                    <img src="/brand/logo/kamicore-logo.svg" alt="KamiCore">
                    <span>KamiCore</span>
                </a>
            </div>

            <div class="kc-navbar-right">
                {{top_plugins}}
            </div>
        </nav>
    </div>
</header>
<!-- /kami:template -->

<!-- kami:template admin-pageheader -->
	<link rel="stylesheet" href="/themes/default/assets/css/admin.css">

    <script src="themes/default/assets/js/admin.js" defer></script>

    <script>
        if (localStorage.getItem('kami.admin.sidebar.collapsed') === '1') {
			document.documentElement.classList.add('admin-sidebar-collapsed');
		}
    </script>
<!-- /kami:template -->


<!-- kami:template footer -->
<footer class="kc-page-footer">
    <div class="kc-container kc-text-center">
        {{footer_plugins}}
    </div>
</footer>
{{system_custom_code}}
<!-- /kami:template -->

<!-- kami:template admin-footer -->
<footer class="kc-page-footer kc-admin-footer">
    <div class="kc-container kc-text-center">
        {{content_bottoms}}
    </div>
</footer>
<!-- /kami:template -->

<!-- kami:template admin-form -->
<form action="{{action}}" method={{method}}>

{{fields}}

	<div class="uk-margin">
		<button class="uk-button uk-button-primary" type=submit>{{lang_submit}}</button>
	</div>
</form>


<script>
	//initAll();
</script>
<!-- /kami:template -->

<!-- kami:template error -->
<div class="uk-alert-danger" uk-alert>
    <p>{{message}}</p>
</div>
<!-- /kami:template -->

<!-- kami:template form-checkbox -->
<div class="uk-margin">
	<label><input class="uk-checkbox" type="checkbox" name="{{name}}" value="{{value}}" {{checked}}> {{placeholder}}</label>
</div>
<!-- /kami:template -->

<!-- kami:template form-ct_id -->
<div class="uk-margin">
	<input class="uk-input uk-form-width-large" type=number name="{{name}}" value="{{value}}" placeholder="{{placeholder}}" {{required}}>
</div>

<div class="uk-margin">
  <label class="uk-form-label" for="{{name}}">{{placeholder}}</label>
  <div class="uk-form-controls">
    <select
      id="{{name}}"
      class="uk-select"
      data-remote-select="1"
      data-endpoint="/ajax/Form/get_select_options"
      data-type="ct"
      data-limit="20"
      data-placeholder="Start typing to search..."
      data-allow-empty="1"
    {{required}}>
      <!-- Optional prefilled option(s) for edit form -->
      <option value="{{value}}" selected>{{value_label}}</option>
    </select>
  </div>
</div>
<!-- /kami:template -->

<!-- kami:template form-date -->
<div class="uk-margin">
	<input class="uk-input uk-form-width-small" type=date name="{{name}}" value="{{value}}" placeholder="{{placeholder}}" {{required}}>
</div>
<!-- /kami:template -->

<!-- kami:template form-html -->
<div class="uk-margin quill-raw-field"
     data-quill-raw
     data-target-input="{{name}}__out"
     data-target-html="{{name}}__html"
     data-target-editor="{{name}}__editor"
     data-min-height="200">

  <h2>{{placeholder}}</h2>

  <div class="uk-form-controls">

    <ul uk-tab="connect: #{{name}}__switcher">
      <li><a href="#">Visual</a></li>
      <li><a href="#">HTML</a></li>
    </ul>

    <ul id="{{name}}__switcher" class="uk-switcher uk-margin-small-top">
      <li>
        <div id="{{name}}__editor"
             class="uk-card uk-card-default uk-card-body uk-card-small"
             style="min-height: 200px;"></div>
      </li>

      <li>
        <textarea id="{{name}}__html"
                  class="uk-textarea"
                  rows="12"
                  spellcheck="false">{{value}}</textarea>

        <div class="uk-text-meta uk-margin-small-top">
          Warning: HTML may be normalized when switching back to Visual mode.
        </div>
      </li>
    </ul>

    <input type="hidden"
           id="{{name}}__out"
           name="{{name}}"
           value="{{value}}">

    {{description_block}}
    {{error_block}}

  </div>
</div>
<!-- /kami:template -->

<!-- kami:template form-integer -->
<div class="uk-margin">
	 <label class="uk-form-label">{{placeholder}}</label>
	 <div class="uk-form-controls">
		<input class="uk-input uk-form-width-large" type=number name="{{name}}" value="{{value}}" placeholder="{{placeholder}}" {{required}} {{min}} {{max}}>
	</div>
</div>
<!-- /kami:template -->

<!-- kami:template form-item_id -->
<div class="uk-margin" id="tsblock_{{id}}">
  <label class="uk-form-label" for="{{id}}">{{placeholder}}</label>
  <div class="uk-form-controls">
    <select
      id="{{id}}"
      name="{{name}}"
      class="uk-select"
      data-remote-select="1"
      data-endpoint="/ajax/Form/get_select_options"
      data-type="item"
      data-type_ids="{{ct_ids}}"
      data-limit="20"
      data-placeholder="Start typing to search..."
      data-allow-empty="1"
      {{multiple}}
    {{required}}>
      <!-- Optional prefilled option(s) for edit form -->
      {{options}}
    </select>
  </div>
</div>
<!-- /kami:template -->

<!-- kami:template form-media -->
<div class="uk-margin">
	<input class="uk-input uk-form-width-large" type=text name="{{name}}" value="{{value}}" placeholder="Временная заглушка для медиа и прочих файлов." {{required}}>
</div>
<!-- /kami:template -->

<!-- kami:template form-select-option -->
<option value="{{value}}" {{selected}}>{{title}}</option>
<!-- /kami:template -->

<!-- kami:template form-select -->
<div class="uk-margin">
        <label class="uk-form-label" for="form-stacked-select">{{placeholder}}</label>
        <div class="uk-form-controls">
            <select class="uk-select" id="{{id}}" name="{{name}}">
                {{options}}
            </select>
        </div>
</div>
<!-- /kami:template -->

<!-- kami:template form-string-orcid -->
<div class="uk-margin">
	 <label class="uk-form-label">{{placeholder}}</label>
	 <div class="uk-form-controls">
		<input class="uk-input uk-form-width-large mask-orcid" type=text name="{{name}}" value="{{value}}" placeholder="{{placeholder}}" {{required}}>
	</div>
</div>

<script>
/*
	const mask = new Mask({ mask: "#-#" })
  Maska.create('.mask-orcid', {
    mask: '####-####-####-###X',
    tokens: {
      '#': { pattern: /[0-9]/ },
      'X': { pattern: /[0-9X]/ }
    }
  });
*/
</script>
<!-- /kami:template -->

<!-- kami:template form-string -->
<div class="uk-margin">
	 <label class="uk-form-label">{{placeholder}}</label>
	 <div class="uk-form-controls">
		<input class="uk-input uk-form-width-large" type=text name="{{name}}" value="{{value}}" placeholder="{{placeholder}}" {{required}}>
	</div>
</div>
<!-- /kami:template -->

<!-- kami:template form-url -->
<div class="uk-margin">
	<input class="uk-input uk-form-width-large" type=text name="{{name}}" value="{{value}}" placeholder="{{placeholder}}" {{required}}>
</div>
<!-- /kami:template -->

<!-- kami:template form -->
<form action="{{action}}" method={{method}}>

{{fields}}

	<div class="uk-margin">
		<button class="uk-button uk-button-primary" type=submit>{{lang_submit}}</button>
	</div>
</form>
<!-- /kami:template -->
