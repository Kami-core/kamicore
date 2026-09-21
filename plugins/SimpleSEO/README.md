# SimpleSEO

SimpleSEO renders page metadata and JSON-LD after page plugins have finished.
It uses PHP 8.4, the existing pgsql database layer and the regular Kami renderer.

## Installation

Install and enable SimpleSEO through PluginManager. Its migration creates
seo_pages, seo_types, seo_domains and seo_schemas.

Enable the plugin for the required domain. Add its view handler to the
head_plugins wrapper of the required pages. Add its manage handler to an
administrator page, or use the supplied standard setup preset. Grant manage
only to groups that should edit SEO settings.

Settings and schema overrides belong to the current domain. Metadata is edited
in all active domain languages; inactive language values are retained on save.
The plugin does not create or change page permissions.

## Runtime

The view handler returns {{seo-data}} once per request. During finalize():

1. Request::routedItemId() selects a content item and its content-type settings.
   Without a routed item, the current page settings are used.
2. Content::getItem() provides the item fields in the current language.
3. Metadata templates and JSON-LD templates use Renderer::render() with
   compiledTemplate. JSON preparation and validation stay inside SimpleSEO.
4. Breadcrumbs::getBreadcrumbItems() supplies breadcrumb data.
5. faqItems and listItems are read from the final layout parameters.
6. The resulting tags are placed in layoutParams['seo-data']. The document title
   is supplied through layoutParams['page_title'], so the theme's existing title
   tag remains the only title tag.

If the theme also displays page_title as a visible heading, that heading will
receive the SEO title. Themes can use their own separate heading parameter.

The existing array_replace() semantics are preserved: the last producer wins.
SEO does not call list or FAQ producers and does not repeat their content queries.

## Producer contracts

During normal wrapper processing, before finalization:

    $this->layoutParams['faqItems'] = [
        ['question' => 'Question?', 'answer' => 'Answer.'],
    ];

    $this->layoutParams['listItems'] = [
        ['name' => 'An article', 'url' => '/blog/an-article'],
    ];

Supply only the content shown on the current page. FAQ text is normalized to
plain text. List order determines ListItem positions, starting at 1 for this
visible list. URLs must use HTTP(S) or be internal paths.

ViewArticles and ViewContent list mode populate listItems. Empty lists replace
previous lists just like any other layout value. Items without an available URL
are skipped. No FAQ producer is automatically added to existing content.

A plugin can optionally provide layoutParams['canonical_url'] when it owns a
nonstandard route. Normally SimpleSEO uses the current path, preserving semantic
path parameters such as pagination and removing the query string. Query-based
content variants need an explicit canonical override. Canonical, image and Open
Graph overrides can also be entered in the SEO editor.

Automatic hreflang links use active languages with a translation row for the
displayed page/item and the same route. They are suppressed when the current
canonical is overridden; custom route/language policies are not inferred.

## Metadata

Page and content-type settings contain language-specific title, description,
image, canonical, og_title and og_description templates. Robots, Open Graph type
and selected JSON-LD schemas are shared across languages.

Empty title uses the domain title_format, then the item's title or page title.
Empty item description uses the item's summary; an empty page description is
omitted. Empty image uses the domain's default sharing image. Empty Open Graph
title/description use the resolved SEO title/description.

For a content type, templates can refer directly to fields:

    Title: {{display_title}} — {{site_name}}
    Description: {{summary}}
    Image: {{article_image}}

Metadata for a list page is not inherited by individual content items.

## JSON-LD templates

Templates are valid JSON with placeholders in values, for example:

    {
      "@context": "https://schema.org",
      "@type": "BlogPosting",
      "headline": "{{display_title}}",
      "description": "{{summary}}",
      "url": "{{canonical_url}}"
    }

An exact quoted placeholder preserves the value's JSON type, including arrays,
numbers and booleans. Embedded placeholders, such as "{{title}} — {{site_name}}",
produce strings. Missing/empty properties and empty optional nested entities are
omitted. Zero and false are preserved.

No PHP is executed. JSON is validated before saving and after rendering. The
final script encoding protects against closing script tags and further template
substitutions. Invalid JSON-LD blocks are skipped without breaking the page;
rendering errors are logged. Missing optional variables appear in preview notes.

Fields from item['data'] are available by their system names. Reserved names:

- title: resolved SEO title (initially the item/page title for metadata templates)
- summary: the item's display summary, falling back to a field named summary
- description: resolved SEO description
- page_title: translated page title
- site_name, site_url, site_logo: domain values
- canonical_url, language: current URL and language
- image: resolved sharing image
- item_id, item_slug: routed item identity
- created_at, updated_at: ISO 8601 dates; updated_at prefers the item data field
- published_at: an ISO 8601 value when the item has that field

Reserved names take precedence over item fields. An image field with a different
name, for example article_image, remains directly available.

Built-in presets include Home (WebSite + WebPage), WebPage, CollectionPage,
Article, BlogPosting, AboutPage, ContactPage, Organization and Person. A page/type
can select several blocks. They are emitted in one @graph. CollectionPage links
to an automatically supplied ItemList; page nodes link to BreadcrumbList.

Presets can be edited per domain and restored. Custom schemas can be created,
duplicated and disabled. A custom schema still assigned to a page/type cannot be
deleted until those assignments are removed.

Schema.org markup does not guarantee enhanced search results. Validation here
covers JSON structure and supported template input, not every Schema.org rule.

## Manager

The manager has four tabs: Pages, Content types, JSON-LD schemas and Domain
defaults. Lists support search and configured/default filtering. Content types
are limited to types with has_slug enabled.

Language tabs are saved together. Placeholder buttons insert into the last
focused template field. JSON syntax feedback runs locally. Server-side preview
uses unsaved form values; it does not save them or execute page plugins.

For an item preview, select a sample item and its actual page. The item selector
shows the latest 100 items. FAQ, lists and breadcrumbs contributed by page plugins
are verified on the live page rather than generated by the manager preview.

Writes require the manage ACL, POST and a session-bound CSRF token. Form tokens
are stored in session data and remain usable with caching disabled.

## Verification

On an installed development instance:

    php plugins/SimpleSEO/tests/verify.php ai-dev01.kamicore.org

The CLI test uses an explicit administrator test context, exercises rendering,
metadata/schema persistence, previews, ACL and form validation, and rolls all
database changes back. It does not create an authenticated browser session.
