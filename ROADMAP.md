# KamiCore Roadmap

This roadmap describes the current direction of KamiCore development. It is not a fixed release schedule: scope may move between releases as real projects expose better priorities.

## 0.8 — Themes and administration

The main focus for 0.8 is making themes genuinely interchangeable rather than treating a theme switch as a simple directory change.

Planned work:

- Add a second production-ready theme with meaningfully different layouts and wrappers.
- Build a safe, page-centric theme migration workflow that can compare the current and target themes, map compatible layouts/wrappers, surface unresolved differences, and avoid leaving a site in a partially migrated state.
- Refine theme compatibility metadata and validation where the second-theme implementation shows that explicit contracts are useful.
- Add a small **Admin Dashboard** plugin with a compact operational summary such as the current core version, installed plugin/theme versions, core and plugin migration history, and a few other useful system facts.

The exact migration UI and compatibility rules will be driven by the real differences between the default theme and the second theme rather than designed purely in the abstract.

## 0.9+ — Developer tools

A later release is expected to introduce a dedicated **DevTools** plugin.

Its main direction is tooling for developers who need to clone, export/import, and migrate plugins and themes between installations or development environments. Additional validation, inspection, and development utilities may be added where they make those workflows safer and clearer.

The exact DevTools scope is intentionally not fixed to 0.9 yet.

## Longer-term directions

Other areas expected to evolve over time include:

- broader public API write/structure operations and documentation;
- additional authentication providers and account-security options;
- extension distribution and repository workflows;
- larger application modules built on top of the open-source core when real projects justify them.

For completed work and release history, see [CHANGELOG.md](CHANGELOG.md).
