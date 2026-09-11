# Changelog

All notable changes to KamiCore will be documented in this file.

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
