<!-- kami:template articles-list -->
<div class="articles-list">
    {{items_per_page_selector}}
    <div class="articles-list-items">{{articles}}</div>
    {{pagination}}
</div>
<!-- /kami:template -->

<!-- kami:template article-card -->
<article class="article-card">
    {{preview}}
    <div class="article-card-content">
        <h2 class="article-card-title"><a href="{{url}}">{{title}}</a></h2>
        {{published_at}}
        <p class="article-card-summary">{{summary}}</p>
        <a class="article-card-more" href="{{url}}">{{phrase.read_more}}</a>
    </div>
</article>
<!-- /kami:template -->

<!-- kami:template article-card-preview -->
<a class="article-card-preview" href="{{url}}">
    <img src="{{preview}}" alt="{{alt}}" loading="lazy">
</a>
<!-- /kami:template -->

<!-- kami:template article-card-date -->
<time class="article-card-date" datetime="{{datetime}}">
    <svg class="icon icon-calendar icon-sm" aria-hidden="true"></svg>
    <span>{{date}}</span>
</time>
<!-- /kami:template -->

<!-- kami:template articles-empty -->
<p class="articles-empty">{{phrase.no_articles}}</p>
<!-- /kami:template -->

<!-- kami:template items-per-page-selector -->
<div>
<form class="articles-per-page" method="get" action="{{action_url}}">
    <label>
        <span>{{label}}</span>
        <select class="kc-input kc-input-sm" name="{{select_name}}" onchange="this.form.submit()">{{options}}</select>
    </label>
</form>
</div>
<!-- /kami:template -->

<!-- kami:template items-per-page-option -->
<option value="{{value}}"{{selected}}>{{label}}</option>
<!-- /kami:template -->

<!-- kami:template article-page -->
<div class="article-page">
	<div class="article-preview">
		<!-- <img src="{{article_image}}"> -->
		{{preview}}
	</div>

	<div class="article-headbox">
		<h1>{{title}}</h1>

		<time class="article-date" datetime="{{published_at_iso}}">
			<svg class="icon icon-calendar icon-sm" aria-hidden="true"></svg>
			<span>{{published_at}}</span>
		</time>

		<aside>{{summary}}</aside>
	</div>

	<div class="article-body">
		{{article_body}}
	</div>
</div>
<!-- /kami:template -->

<!-- kami:template article-img -->
    <img src="{{preview}}" alt="{{alt}}">
<!-- /kami:template -->
