# Open Parliament TV - Platform

**Open Parliament TV** is a **search engine** and **interactive video platform** for **parliamentary debates**. This repository is the **platform**: a PHP web application and REST API that ingests parliamentary data (speeches, sessions, agenda items, documents, people, organisations), indexes it for full-text and faceted search, and presents it with a transcript-synced video player.

It is multi-parliament by design: one codebase serves many instances, each with its own database and branding (for example the German Bundestag at `de.openparliament.tv`).

## The Open Parliament TV ecosystem

This repository is the **presentation and API layer**: it ingests the unified, enriched per-session data produced upstream and serves it as a searchable web application and REST API. Related repositories:

- **[OpenParliamentTV-Tools](https://github.com/OpenParliamentTV/OpenParliamentTV-Tools)** is the data import pipeline. It fetches parliamentary proceedings and media feeds, parses them into a unified per-session JSON format, and enriches them with named-entity linking, sentence-level audio alignment, and named-entity recognition.
- **[OpenParliamentTV-Architecture](https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture)** holds the system design and data-format specifications, for example [PIPELINE.md](https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture/blob/main/PIPELINE.md), [STAGE2-FORMAT.md](https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture/blob/main/STAGE2-FORMAT.md), [DATA-STRUCTURES.md](https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture/blob/main/DATA-STRUCTURES.md), and [PLATFORM-DB-SCHEMA.md](https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture/blob/main/PLATFORM-DB-SCHEMA.md) (the conceptual database schema).
- **Parliament data repositories**: one published data repository per parliament (for example [OpenParliamentTV-Data-DE](https://github.com/OpenParliamentTV/OpenParliamentTV-Data-DE)). The platform clones these under `data/` and imports them into MariaDB and OpenSearch.

The flow is: Tools produces per-session JSON, which is published to the Data repositories, which this Platform ingests, indexes, and serves.

---

## Features

- **Full-text and faceted search** over speeches and entities, backed by OpenSearch.
- **Entity pages** for people, organisations, terms, documents, sessions, electoral periods and agenda items, linked to Wikidata and other sources.
- **Transcript-synced video player** (FrameTrail) with shareable, timecoded quotes.
- **REST API** (`/api/v1`) with an OpenAPI spec and a docs UI at `/api`.
- **Feeds and interop**: RSS/Atom feeds (`/feed/*`), IIIF manifests, and WebVTT transcripts.
- **Alerts and notifications**: users can save searches and receive in-app or email digests.
- **Multi-language UI** (German, English, French, Turkish) and **per-instance branding and content** via `custom/`.
- **Multi-parliament**: each parliament uses its own database; instances are configured, not forked.

## Tech stack

- **PHP** with [FastRoute](https://github.com/nikic/FastRoute) (routing) and [Plates](https://platesphp.com/) (templating), served by **Apache** (mod_rewrite).
- **MariaDB / MySQL** via the `SafeMySQL` wrapper (parameterised queries).
- **OpenSearch 2.x** for search and aggregated statistics.
- Composer dependencies: `opensearch-project/opensearch-php`, `phpmailer/phpmailer`, `nikic/fast-route`, `league/plates`, `symfony/yaml`.
- Frontend: Bootstrap 5 and jQuery, FrameTrail video player.

## Architecture

```
Apache (.htaccess, single catch-all)
  index.php     front controller: config + session + i18n + Plates engine
  FastRoute     matches the URL against routes/web.php
  handler       modules/routing/handlers.php loads data and renders a Plates template
```

- **Auth is checked once** at the routing layer (`modules/routing/auth.php`), not in individual templates.
- **Templates** use layout inheritance: `content/base.php` (the outer HTML) wraps `content/layout/{default,admin,embed}.php`, which wrap `content/pages/*/page.php`, with shared `content/components/*`.
- **Result lists and the media player** are fetched by JavaScript as standalone PHP fragments rather than rendered inline, which keeps pages light.
- **The REST API** under `api/v1/` is independent of the web routing and has its own `.htaccess`.

## Directory layout

```
index.php                 Front controller (FastRoute dispatch)
routes/web.php            Route definitions
modules/routing/          Route handlers and centralized auth
modules/templating/       Plates engine factory and custom-override resolver
modules/                  Domain logic: search, media, feed, notifications, images, i18n, mail, utilities
api/v1/                   REST API (openapi.yaml; docs page served at /api)
content/                  base.php, layout/, pages/, components/, and client assets (css/js/fonts/images)
custom/                   Per-instance overrides (branding, content, hooks); see Customization
data/                     Parliament data repositories and import/indexing scripts
db/                        SQL schema dumps (platform + parliament databases)
lang/                     UI translations (de/en/fr/tr); langcache/ holds generated caches
cache/images/             Cached entity thumbnails
config.php                Main configuration (copy from config.sample.php)
```

## Requirements

- **PHP 7.4+** (8.1+ recommended) with extensions: `mysqli`, `mbstring`, `json`, `dom`, `curl`, `openssl`.
- **Apache** with `mod_rewrite`.
- **MariaDB / MySQL**: a platform database (users, organisations, terms, documents) plus one database per parliament (sessions, agenda items, media, electoral periods).
- **OpenSearch 2.x** (indices prefixed `openparliamenttv_*`, statistics in `optv_statistics_*`).
- **Composer**.
- Optional **SMTP** credentials (otherwise PHP `mail()` is used).

## Setup

1. **Clone** the repository into your web root.
2. **Install dependencies**:
   ```bash
   composer install --no-dev
   ```
3. **Create config**: copy and edit.
   ```bash
   cp config.sample.php config.php
   ```
   Fill in at least: `dir.root` (base URL) and `version` (cache busting); database credentials (`platform.sql.*`, `parliament.<code>.sql.*`, table names); OpenSearch hosts/auth/SSL and index prefixes; password `salt`; mail (PHP mail or SMTP); optional `ads.api.*` and `customization.wordmark`.
4. **Load relational data**: import the schema dumps from `db/` — `db/openparliamenttv_platform.sql` into the platform database and `db/openparliamenttv_parliament.sql` into each parliament database (table names must match the config). The data model is documented conceptually in [PLATFORM-DB-SCHEMA.md](https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture/blob/main/PLATFORM-DB-SCHEMA.md).
5. **Set write permissions** for the PHP user:
   - `langcache/` for generated i18n caches
   - `api/v1/cache/` for API response caches
   - `cache/images/` for the entity thumbnail cache
   - the mail log path if `mail.dev.file_path` is used
6. **Import and index parliament data** (creates the OpenSearch indices on first run):
   ```bash
   php data/cronUpdater.php --parliament DE
   ```
   In production this is typically run via cron or triggered from the admin Import page rather than ad hoc.
7. **Serve**: point Apache at the repository root.

## Configuration highlights

- `mode`: `dev` logs errors and surfaces them locally; `production` hides them. Always run live sites in `production`.
- `allow.publicAccess`: whether anonymous visitors can read public content.
- `parliament.<code>`: per-parliament database connection and labels; add one block per instance.
- `OpenSearch.*`: hosts, auth, SSL, and index prefixes.
- `customization.wordmark` and the `custom/` directory: per-instance branding.

## API

- **Docs UI**: `https://<host>/api` (spec at `api/openapi.yaml`).
- **Base**: `https://<host>/api/v1`, REST path form, for example:
  - `GET /api/v1/search/media?q=...&personID=Q...`
  - `GET /api/v1/person/{id}`
  - `GET /api/v1/status?parliament=DE`
- **Public actions** (whitelisted): `getItem`, `search`, `autocomplete`, `status`, `lang`, `user` (login/register/etc.), `iiif`, `transcript`, `alert`, `notification`, `systemMessage`.
- **Admin actions** (CRUD, indexing, import, raw DB reads) require an authenticated session.

## Customization (per-instance overrides)

Keep instance-specific branding and content in `custom/` so core updates stay clean. Any template or partial name is resolved in this order (language from the active session):

```
custom/content/{name}.{lang}.php   (language-specific override)
custom/content/{name}.php          (generic override)
content/{name}.php                 (default)
```

- **Page overrides** (`custom/content/pages/<page>/page.php`) are full Plates pages:
  ```php
  <?php defined('OPTV') or die(); ?>
  <?php $this->layout('layout/default') ?>
  <main> ... your content ... </main>
  ```
  Do **not** include `header.php` or `footer.php`; the layout provides them.
- **Partial and component overrides** (`custom/content/footer.php`, `components/home.php`, and similar) are plain fragments: `<?php defined('OPTV') or die(); ?>` followed by markup. They must **not** carry bootstrap `require_once` (config/security/i18n); the entry point already loads those.
- **Function hooks**: `custom/overriding.functions.php` may define hooks such as `overrideVideoSource($speech)`.
- **Assets**: custom logos and images under `custom/content/client/images/`.

Every template begins with `<?php defined('OPTV') or die(); ?>`; the `OPTV` constant is defined in `config.php`, so direct browser access to a template is blocked. A small set of components are fetched directly by JavaScript (`result.grid.php`, `result.table.php`, `entity.form.php`, `entity.preview.ads.php`, `content/pages/media/content.player.php`) and therefore do **not** carry the guard.

## Security

- All database access goes through `SafeMySQL` with parameterised placeholders.
- Output is escaped with `h()`, `hAttr()`, and `safeHtml()` (`modules/utilities/security.php`); response headers via `applySecurityHeaders()`.
- Authorization is enforced server-side; public and admin actions are whitelisted in the API and the routing layer.

## License

See [`LICENSE`](LICENSE).
