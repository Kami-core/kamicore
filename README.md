<p align="center">
  <img src="brand/logo/kamicore-logo.svg" alt="KamiCore" width="180">
</p>

# KamiCore

KamiCore is a modular content management system built with PHP and PostgreSQL. It is designed around structured content, plugins, themes, multilingual data, and a small transparent core that avoids hiding application behavior behind unnecessary abstraction.

> **KamiCore 0.5 Alpha**
>
> This is an early development release intended for testing, evaluation, and experimentation. APIs, database structures, plugin contracts, and other internal interfaces may change before a stable release. Do not treat the current alpha as a drop-in production platform with guaranteed backward compatibility.

## Highlights

- Structured content types with reusable field definitions and PostgreSQL-backed indexing.
- Plugin-based page composition and application lifecycle extensions.
- Themes with overridable templates and layouts.
- Multi-domain support with per-domain themes, plugin activation/settings, languages, and content presentation.
- Multilingual content and system dictionaries with fallback support.
- User groups, plugin permissions, content permissions, and page-level access control.
- Built-in administration for pages, content, navigation, media, users, translations, plugins, and themes.
- Token-based API access with scoped permissions.
- Redis caching with a no-cache fallback.
- Browser-based installer for a clean first setup.

## Requirements

KamiCore 0.5 Alpha currently requires:

- PHP **8.4 or newer**.
- PostgreSQL **17 or newer**.
- PHP `pgsql` extension.
- PHP `sodium` extension.
- Argon2id password hashing support.
- PostgreSQL `citext` and `pg_trgm` extensions.

Optional:

- Redis and the PHP `redis` extension for application caching.
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

The updater applies any pending, checksummed core database migrations in order and then updates all installed plugins, including their own database migrations. Already applied migrations are not run again.

Create a database backup before upgrading, especially while KamiCore remains in alpha. New plugins included in a release are not installed automatically; plugin installation remains an explicit administrator action.

### Database snapshot

The distribution contains two installer snapshots:

- `install/database/schema.sql` — database structure;
- `install/database/data.sql` — initial system and demo data.

The installer restores them directly through PHP's `pgsql` extension; a local `psql` executable is not required on the web server.

## Initial content

The alpha package includes a small bilingual demo site. Its purpose is to demonstrate page composition, structured articles, navigation, static blocks, translations, and theme rendering without turning the distribution into a prebuilt website.

Everything in the demo can be edited or removed from the administration area after installation.

## Project status

KamiCore is under active development. The current alpha is useful for testing the architecture and building experimental sites, but some areas are intentionally still evolving.

In particular, during the alpha cycle:

- database migrations and structures may change;
- plugin APIs may change;
- configuration formats may change;
- release upgrades are supported through `php utils/update.php`, but backward compatibility between arbitrary development snapshots is not guaranteed.

Bug reports and focused feedback are welcome, especially when they include reproducible steps and environment details.

The current development direction is tracked in [ROADMAP.md](ROADMAP.md).

## Technology

The current core stack is intentionally small:

- PHP 8.4;
- PostgreSQL 17+;
- Redis 5+ when caching is enabled;
- Lightweight frontend code built primarily with project CSS and JavaScript.

Database access in the core uses PHP's native `pgsql` extension.

## Security notes

KamiCore keeps its master encryption key outside the public document root and stores application secrets encrypted in the database. The installer generates a fresh master key for each installation.

As with any alpha software, review your deployment environment and configuration before exposing a test installation to untrusted traffic.

## License

KamiCore is released under the **Apache License 2.0**.

Third-party libraries and assets retain their respective licenses. See [`THIRD_PARTY.md`](THIRD_PARTY.md) for bundled components and attribution.

## Links

- Project website: <https://kamicore.org/>
- Alpha demo: <https://alpha.kamicore.org/>
- GitHub organization: <https://github.com/kamicore>
