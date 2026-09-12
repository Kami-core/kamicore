# KamiCore Roadmap

This roadmap describes the current direction of KamiCore development. It is not a release schedule: items may move, change scope, or be replaced as the architecture evolves. No item below has a fixed delivery date unless it is announced separately in a release plan.

## Current focus

The next major development focus is the public API. KamiCore already has token-based access control, API-aware plugin actions, content permissions, and the first read/write endpoints; the next step is to turn these pieces into a coherent and documented API that can be used by external applications.

Near-term API work includes:

- Define and stabilize the public request/response conventions.
- Expand content and structure endpoints for practical external use.
- Keep API permissions constrained by both the user's rights and the token scope.
- Add documentation and examples once the contracts are stable enough to publish.

## Existing plugins

The following areas are expected to receive further work in the near term:

- **UserAccount** — add Telegram authentication and Telegram-based two-factor authentication, then make additional authentication providers easier to integrate.
- **ViewArticles** — add more sorting and presentation options while keeping the default viewer intentionally small.
- **PluginManager** — add lightweight plugin categories for administration and prepare metadata for a future extension repository.

## Planned plugins and features

These are likely additions, but their exact shape is still open to change:

- **Search** — configurable search across selected content types, with full-text, substring, and tag-oriented search modes where appropriate.
- **Markdown support** — a dedicated Markdown field type with plain textarea editing and a small viewer plugin. Markdown content will remain Markdown rather than being treated as a generic rich-text format.
- **SeoSimple** — a small SEO plugin for essential page/content metadata and canonical output, integrated with the existing layout and breadcrumb lifecycle. More advanced SEO automation can remain a separate future plugin.
- **Developer tools** — utilities for inspecting extension manifests, cloning plugin/theme skeletons, and other development-oriented tasks where they provide clear value.

## Longer-term ideas

These are exploratory and should not be read as commitments for the next release:

- A standalone catalog/e-commerce foundation with products, categories, customers, and orders, followed by more advanced offer and pricing models if the basic architecture proves useful.
- More authentication providers and account-security options.
- Broader extension distribution and commercial plugin/theme workflows around the open-source core.

## Recently completed

KamiCore 0.5.0 established several pieces that the next work can build on:

- Core and plugin database migrations with a CLI upgrade workflow.
- Compound content fields.
- Dedicated session and client-context handling.
- Breadcrumb generation through the page/content lifecycle.
- System language activation management.
- Late renderer finalization for layout parameters and placeholders.

For release history and completed changes, see [CHANGELOG.md](CHANGELOG.md).
