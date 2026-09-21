<!-- kami:template search-form -->
<form class="search-form" action="{{action}}" method="get" role="search">
<div class="search-form-bar">
    <select class="search-form-input"
            name="q"
            data-ajax-url="{{autocomplete_url}}"
            data-placeholder="{{placeholder}}"
            data-create="1"
            aria-label="{{placeholder}}">
        {{query_option}}
    </select>
    <button class="search-form-button" type="submit" aria-label="{{button_label}}">
            <span class="search-form-button-text">{{button_label}}</span>
            <svg class="icon icon-search" aria-hidden="true"></svg>
        </button>
</div>
</form>
<!-- /kami:template -->

<!-- kami:template search-results -->
<section class="search-results">
    <h1 class="search-results-title">{{search_title}}</h1>
    <div class="search-results-list">{{results}}</div>
    {{pagination}}
</section>
<!-- /kami:template -->

<!-- kami:template search-empty -->
<p class="search-results-empty">{{message}}</p>
<!-- /kami:template -->
