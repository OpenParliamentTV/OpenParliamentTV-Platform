## Open Parliament TV - Platform

A PHP web application and REST API for exploring parliamentary data (speeches, sessions, agenda items, documents, people, organizations). Full‑text and faceted search is backed by OpenSearch; relational data lives in MariaDB/MySQL. The UI renders server-side via PHP and consumes the internal API.

---

### Architecture
- `index.php` - entry point for the web UI; dispatches pages and calls the internal API (`api/v1/api.php`).
- `api/` - REST API with OpenAPI spec (`api/openapi.yaml`) and API documentation page at `/api`.
- `content/` - UI pages, components, and assets (CSS/JS/fonts/images).
- `custom/` - Custom content and overriding files.
- `data/` - Git repositories of parliament data.
- `modules/` - domain logic and helpers (search, media, i18n, mail, utilities).
- `lang/` - translation JSON files; `langcache/` holds generated i18n caches (needs write access).
- `vendor/` - Composer dependencies (OpenSearch PHP client, PHPMailer).
- Configuration lives in `config.php` (see `config.sample.php` for all options); API-specific settings in `api/v1/config.api.php`.

### Prerequisites
- PHP 7.4+ (8.x recommended) with extensions: mysqli, mbstring, json, dom, curl, openssl.
- Apache webserver.
- MariaDB/MySQL:
  - Platform DB (users, orgs, metadata).
  - Parliament DB (sessions, agenda items, media IDs, texts).
- OpenSearch 2.x cluster with indices named `openparliamenttv_*`.
- Composer for PHP dependencies.
- Optional SMTP credentials (otherwise PHP `mail()`).

### Setup
1) **Place code** - Clone the repository to the directory of choice.
2) **Create config** - copy `config.sample.php` to `config.php` and fill:
   - `dir.root` base URL; `version` for cache busting.
   - UI toggles (`display`, `allow`), password `salt`.
   - Mail (PHP mail or SMTP).
   - DB credentials: `platform.sql.access.*`, `parliament.*.sql.access.*`, table names.
   - OpenSearch hosts/auth/SSL (`OpenSearch.*`) and index prefixes.
   - Optional ADS API (`ads.api.*`) and wordmark (`customization.wordmark`).
3) **Install dependencies** - run inside the root directory:
   ```bash
   composer install --no-dev
   ```
4) **Import data** - load provided SQL dumps (e.g., `openparliamenttv_parliament.sql`, `openparliamenttv_platform.sql`) into the respective databases; ensure table names match config.
5) **Index into OpenSearch** - indices `openparliamenttv_*` will be created at the first import of data.
6) **Set write permissions** - PHP user needs write access to:
   - `langcache/` (i18n caches)
   - `api/v1/cache/` (API caches)
   - optional mail log path (`logs/mail` if `mail.dev.file_path` is used)
7) **Run**
   - Production: configure Apache to serve your root directory (HTTPS recommended).

### Usage
- Web UI: `https://<host>/` (e.g. `/search`).
- API docs UI: `https://<host>/api`.
- REST base: `https://<host>/api/v1`.
- Languages: translations in `lang/*.json`.

### Security & Operations
- `config["mode"]="dev"` logs errors but hides them in the browser; set to `production` for live.
- Public endpoints are whitelisted (e.g., `getItem`, `search`, `autocomplete`, `status`, user login/registration). Admin-only actions (indexing, CRUD) require authenticated sessions.
- Sessions manage login and language; `color_scheme` cookie controls dark/light mode.
- Mail: in dev mode you can store mails to disk (`mail.dev.file_path`).

### Data & IDs
- IDs follow `<PARL>-<...>`; parsing derives type, electoral period, session, etc.
- OpenSearch highlights come from `attributes.textContents.textHTML` with `<em>` tags.

### License
See `LICENSE`.

### Custom overrides
- Use `/custom/` to override content without touching core files. The helper `include_custom()` first looks for language-specific overrides (e.g., `custom/content/footer.en.php`), then for a generic override (e.g., `custom/content/footer.php`), and falls back to the original file.
- Existing overrides: custom home/footer components and about/datapolicy/imprint pages (per language), plus custom logos under `custom/content/client/images/`.
- Custom logic: `custom/overriding.functions.php` can define hooks like `overrideVideoSource($speech)` that the media player uses when present.
- Keep your installation-specific branding or behavior in `/custom/`; core updates remain unaffected.
