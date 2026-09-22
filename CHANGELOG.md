# Changelog

All notable changes to KamiCore will be documented in this file.

## [0.7.1] - 2026-09-22

### Security

- Added centralized session-bound CSRF protection for all `/ajax/` requests and all unsafe frontend requests. The core browser runtime reads the current token from a secure same-origin cookie and automatically attaches it to AJAX headers and same-origin non-GET form data, including dynamically inserted forms.
- Kept API requests and plugin-owned virtual endpoints outside the browser CSRF policy so they can continue using their own authentication, signatures, one-time tokens, or other endpoint-specific trust mechanisms.
- Updated standalone UserAccount browser flows that call protected AJAX actions so they initialize the browser CSRF context and load the core browser runtime.

### Changed

- Extended the asset registry so individual JavaScript assets can opt out of deferred execution.
- Load `assets/js/common.js` before deferred/plugin scripts so core browser request security is active before inline plugin code can issue initial AJAX requests.
- Updated bundled package versions for UserAccount and SimpleSEO and synchronized the clean-install plugin metadata.

### Fixed

- Fixed initial page-load AJAX requests being rejected with `403` before the deferred core browser runtime had initialized.
- Fixed SimpleSEO finalization so SEO generation is enabled during plugin initialization instead of depending on placeholder rendering order.

## [0.7.0] - 2026-09-21

### Added

- Added the bundled Search plugin with configurable content-type scope, a reusable search form, result pages, pagination, ACL-aware result filtering, and AJAX autocomplete.
- Added the bundled SimpleSEO plugin with per-page and per-content-type metadata, JSON-LD schema templates, breadcrumb and FAQ integration, canonical URLs, and static XML sitemap generation.
- Added the `lang_code` field type for selecting active system languages.
- Added canonical viewer metadata for content types through `canonical_viewer_for` plugin declarations and per-type viewer selection.
- Added PageManager support for creating a reusable page recipe from an existing page.
- Added field type management to ContentManager, including dependency counts and safe deletion of unused field types.
- Expanded TranslationManager with batch translation of missing values, per-language counts, source context and instructions, and outdated-translation indicators based on edit timestamps.

### Changed

- Changed plugin `structures.json` lifecycle semantics: structures are now bootstrap snapshots used only on first plugin installation. Existing installations keep the database as the runtime source of truth, and later structural changes are delivered through versioned plugin migrations.
- Kept bundled optional capabilities such as ApiAccess, ViewMd, Search, and SimpleSEO out of the zero-config installation. They remain available in the distribution and are installed explicitly when needed.
- Regenerated the clean installer database snapshot from the final zero-config database and verified it through a complete restore into an isolated PostgreSQL instance.
- Cleaned the zero-config installation state so it no longer contains development SMTP configuration, API tokens, sessions, secrets, setup history, test settings, Markdown test content, or optional-plugin pages and navigation entries.
- Normalized legacy UserAccount and UserProfile plugin settings into the current runtime settings format.
- Renamed the article image field from `article_preview` to `article_image` without changing its field ID or UUID and migrated existing article data in place.
- Improved ViewArticles canonical/category URL handling and updated article templates to use the renamed article image field.
- Standardized TranslationManager list and dictionary interfaces and continued moving plugin-owned templates and assets out of the default theme.
- Updated bundled plugin manifests, translations, versions, and clean-install data for the 0.7.0 distribution.

### Fixed

- Fixed ContentManager field-structure editing so fields can be removed reliably when optional controls are not present in the current form.
- Fixed PageManager parent selection when creating a regular page and kept parent clearing behavior consistent.
- Fixed ViewArticles links so articles with a primary category resolve through the intended category route.
- Fixed plugin updates so local administrator changes to content structures are no longer silently restored from `structures.json`.

### Removed

- Removed the obsolete `field_variants` table, `fields.variant_id`, and the remaining runtime compatibility code for field variants.
- Removed the unused legacy `user_messages` and `user_messages_old` tables from the core schema.
- Removed stale optional-plugin setup pages, navigation entries, and development-only settings from the clean installation snapshot.

## [0.6.0] - 2026-09-16

### Added

- Added the bundled ViewMd plugin and `markdown` field type, with Parsedown 1.8 integration and configurable safe mode for Markdown rendering.
- Added the bundled ViewContent plugin for generic content rendering in single, list, and tree modes, including fallback templates, pagination, hierarchical trees, lazy child loading, and field-type-specific renderers.
- Added ContentManager read APIs for field types, fields, content types, single items, item lists, and content search.
- Added hierarchical content item support through `parent_id`, including parent selection in ContentManager and cycle prevention.
- Added plugin categories in manifests and grouped plugin presentation in PluginManager.
- Added the `Assets` core service and BasePlugin asset helpers so plugins can register their own CSS and JavaScript.

### Changed

- Moved plugin-specific frontend and administration styles and scripts out of the default theme and into the plugins that own them, leaving the theme primarily responsible for final presentation and overrides.
- Added asset rendering to HTML AJAX responses so dynamically loaded plugin output can include required registered assets.
- Refactored shared helpers out of the legacy `functions.php` surface into focused core utilities and registries, including HTML escaping, URL building, cryptographic helpers, date handling, page lookup, plugin lookup, and JSON handling.
- Standardized manager save flows around HTTP redirects and persistent Notifications instead of inline JavaScript redirects and transient messages.
- Centralized HTML escaping and JSON-for-HTML encoding across manager interfaces.
- Updated plugin manifests, categories, versions, bundled translations, administration styling, and the clean installation database snapshot for the 0.6.0 distribution.
- Simplified installer configuration rendering so placeholders are emitted as typed PHP literals and validated after replacement.

### Fixed

- Fixed PageManager parent clearing so removing a parent places the page at the domain root instead of assigning the home page as parent.
- Fixed PageManager return links so the selected domain is preserved after editing a page.
- Fixed PageManager plugin instance parameter handling during page saves.
- Fixed the ViewStatic manifest so `static_content_block` is again available as a supported content type.
- Fixed clean-install configuration generation so `USE_CACHE` is written as a valid boolean instead of leaving the `__CACHE_ENABLED__` placeholder unresolved.

### Security

- Reworked API authentication around Bearer tokens with checks for token state, expiration, active users, and API-enabled user groups.
- Added token-scoped API permissions for plugin actions and content operations. Token restrictions can only narrow the permissions granted by the user's normal ACL.
- Hardened API dispatch by requiring domain-active plugins, explicitly declared API actions, allowed HTTP methods, and supported request and response media types, with appropriate HTTP error responses.
- Strengthened output handling in administration interfaces by consolidating HTML escaping and safe JSON embedding.

## [0.5.0] - 2026-09-12

### Added

- Added compound content fields with configurable components, per-component translation, multiple values, validation, indexing, filtering, and editing support in ContentManager and Forms.
- Added the bundled Breadcrumbs plugin with page and content breadcrumb generation through the layout parameter lifecycle.
- Added `ClientContext`, providing a long-lived client context identifier independent of the authentication session.
- Added system language activation management in SystemManager, including protection for languages currently required by configured domains.
- Added the `CORE_VERSION` runtime constant.
- Added the `php utils/update.php` upgrade workflow with an advisory lock, tracked and checksummed core database migrations, and automatic updates for installed plugins and their migrations.

### Changed

- Separated session lifecycle and authentication state from `User` into the dedicated `Session` core class.
- Changed Notifications to use client context instead of session ID, so transient notifications survive session rotation where appropriate.
- Moved unused template placeholder cleanup out of `Renderer::render()` into the explicit finalization stage. Final HTML pages, error pages, and HTML AJAX responses are now finalized only after all late layout parameters are available.
- Renamed the frontend `PAGE_NAME` runtime constant to `PAGE_SLUG` to reflect its actual meaning.
- Improved session and cookie lifecycle handling, including replacement of pending cookies with the same name.
- Updated the clean installation database snapshot and seed data for the new field type and notification context model.
- Changed template bundle cache keys to include source file metadata, preventing stale rendered templates after code updates.

### Fixed

- Fixed configured post-login behavior so `reload`, `redirect`, and `nothing` are respected consistently, including OAuth return URLs.
- Fixed CSRF/request handling around session changes and request dispatch.
- Fixed notification cleanup and delivery behavior after authentication state changes.
- Removed stale test content from the clean distribution database.

### Security

- Added session ID rotation when authentication state changes, including login and logout.
- Replaced several legacy interpolated SQL queries with parameterized PostgreSQL queries.
- Strengthened generated identifiers by using cryptographically secure random bytes directly.
- Hardened request handling against ambiguous or conflicting input values.

## [0.4.0] - 2026-09-06

- Initial public alpha release of KamiCore.
