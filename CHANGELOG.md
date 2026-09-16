# Changelog

All notable changes to KamiCore will be documented in this file.

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
