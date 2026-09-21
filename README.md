<p align="center">
  <img src="brand/logo/kamicore-logo.svg" alt="KamiCore" width="180">
</p>

# KamiCore

KamiCore is a modular content management system built with PHP and PostgreSQL. It is designed around structured content, plugins, themes, multilingual data, and a small transparent core that avoids hiding application behavior behind unnecessary abstraction.

> **KamiCore 0.7 Alpha**
>
> This is an early development release intended for testing, evaluation, and real-world development. APIs, database structures, plugin contracts, theme contracts, and other internal interfaces may still change before a stable release. Release upgrades are supported, but arbitrary development snapshots are not guaranteed to remain backward compatible.

## Highlights

- Structured content types with reusable field definitions, compound fields, hierarchy, and PostgreSQL-backed indexing.
- Plugin-based page composition and lifecycle extensions.
- Themes with overridable templates and layouts.
- Multi-domain support with per-domain themes, plugin activation/settings, languages, and content presentation.
- Multilingual content and system dictionaries with fallback support and assisted translation workflows.
- User groups, plugin permissions, content permissions, and page-level access control.
- Built-in administration for pages, content, navigation, media, users, translations, plugins, themes, and system settings.
- Generic content viewing in single, list, and hierarchical tree modes.
- Redis caching with a no-cache fallback.
- Browser-based installer and checksummed core/plugin database migrations.

KamiCore also ships several **bundled but optional** capabilities that are installed explicitly when needed, including API access, Markdown rendering, search, and SEO/sitemap support. The zero-config installation intentionally stays small and does not enable scenario-specific features automatically.

## Requirements

KamiCore 0.7 Alpha currently requires:

- PHP **8.4 or newer**.
- PostgreSQL **17 or newer**.
- PHP `pgsql` extension.
- PHP `sodium` extension.
- Argon2id password hashing support.
- PostgreSQL `citext` and `pg_trgm` extensions.

Optional:

- Redis **5 or newer** and the PHP `redis` extension for application caching.
- SMTP credentials if email delivery should be configured during installation. Mail settings can also be configured later.

The PostgreSQL user used for installation must be able to create tables, indexes, triggers, functions, and the required PostgreSQL extensions in the selected database.

## Installation

1. Create an **empty PostgreSQL database** and a database user with sufficient privileges.
2. Place the KamiCore files in the site's document root.
3. Make sure the `config/` directory is writable by PHP during installation.
4. Make sure PHP can create the master secret file **outside the public document root**. The installer suggests a sibling `private/kami.secret` path by default.
5. Open the site in a browser.
6. Complete the installation form:
   - PostgreSQL connection;
   - optional Redis cache;
   - master secret file location;
   - administrator account;
   - optional SMTP configuration.
7. After a successful installation, KamiCore creates `config/config.php` and the site starts normally.

If `config/config.php` already exists, the installer refuses to run again.

## Upgrade

For an existing KamiCore installation, update the application files and then run the CLI updater from the project root:

```bash
git pull
php utils/update.php
```

The updater applies pending, checksummed core migrations in order and then updates all installed plugins, including their versioned database migrations. Already applied migrations are not run again.

Plugin `structures.json` files are used only to bootstrap structures during the **first installation** of a plugin. Later updates do not re-synchronize those declarations over the live database; structural evolution of an installed plugin is handled by explicit plugin migrations. This keeps administrator changes from being silently restored by a package update.

Create a database backup before upgrading, especially while KamiCore remains in alpha. New plugins included in a release are not installed automatically; plugin installation remains an explicit administrator action.

### Database snapshot

The distribution contains two installer snapshots:

- `install/database/schema.sql` — database structure;
- `install/database/data.sql` — initial system and sample-site data.

The installer restores them directly through PHP's `pgsql` extension; a local `psql` executable is not required on the web server.

The snapshot is intentionally clean: transient sessions, API tokens, secrets, setup history, development mail settings, and optional-plugin setup resources are not included.

## Initial site

A clean installation creates a small bilingual sample site rather than a prebuilt application. It demonstrates page composition, structured articles, navigation, static blocks, translations, administration, and the default theme while keeping scenario-specific functionality out of the baseline.

Everything in the sample site can be edited or removed from the administration area.

Optional bundled plugins such as **ApiAccess**, **ViewMd**, **Search**, and **SimpleSEO** can be installed later through PluginManager when a project actually needs them.

## Project status

KamiCore is under active development. The current alpha is already suitable for testing the architecture and building real development sites, but several contracts are intentionally still evolving.

During the alpha cycle:

- database structures and migrations may continue to evolve;
- plugin and theme contracts may change;
- configuration formats may change;
- release upgrades are supported through `php utils/update.php`;
- backward compatibility between arbitrary unpublished development snapshots is not guaranteed.

Bug reports and focused feedback are welcome, especially when they include reproducible steps and environment details.

The current development direction is tracked in [ROADMAP.md](ROADMAP.md).

## Technology

The core stack is intentionally small:

- PHP 8.4;
- PostgreSQL 17+;
- Redis 5+ when caching is enabled;
- lightweight frontend code built primarily with project CSS and JavaScript.

Database access in the core uses PHP's native `pgsql` extension.

## Security notes

KamiCore keeps its master encryption key outside the public document root and stores application secrets encrypted in the database. The installer generates a fresh master key for each installation.

API authentication is provided through the optional bundled ApiAccess plugin and uses bearer tokens whose stored values are hashed. Token permissions can only narrow the permissions already granted to the user by the normal ACL model.

As with any alpha software, review your deployment environment and configuration before exposing an installation to untrusted traffic.

## License

KamiCore is released under the **Apache License 2.0**.

Third-party libraries and assets retain their respective licenses. See [`THIRD_PARTY.md`](THIRD_PARTY.md) for bundled components and attribution.

## Links

- Project website: <https://kamicore.org/>
- Alpha demo: <https://alpha.kamicore.org/>
- GitHub organization: <https://github.com/kamicore>
