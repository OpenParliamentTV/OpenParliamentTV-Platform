# Contributing

## Naming convention

One rule set governs every new file, function and variable. It is also being applied retroactively across the codebase; see `docs/rename-manifest.json` for the record of what moved.

### Directories and files

- **kebab-case, all lowercase**, for every directory and file: PHP, templates, JS, CSS, JSON — `search-index/`, `electoralPeriod.php`, `result-grid.php`, `criteria-chips.js`, `en.json`.
- Rationale: all-lowercase removes the dev (case-insensitive macOS) vs. prod (case-sensitive Linux) hazard for `require` paths and URLs; it matches web asset convention; and it is one rule with no exceptions to memorize.
- **No dots in base names** except the final extension and the `.{lang}.php` override suffix. Dotted names made language-suffix parsing ambiguous: in `result.grid.de.php` there is no way to tell the compound base name from the language qualifier.
- **No `functions.php`.** Name the file after the capability it provides: `mailer.php`, `git-sync.php`, `image-cache.php`.
- Direct-served fragments — self-bootstrapping, fetched by URL, bypassing the router, auth and layout — live in a page-local `components/` directory, parallel to the global `content/components/`. The directory communicates the role; no `-fragment` suffix.
- **Exempt:** bundled third-party files keep their upstream names (`safemysql.class.php`, `jquery-3.4.1.min.js`, `highlight.min.js`, everything under `modules/pdf-viewer/`). Vendor JS/CSS lives under `content/client/{js,css}/vendor/`.

### PHP functions

- **camelCase, `{prefix}{Verb}{Object}`**, where the prefix is the module's pseudo-namespace (table below): `mediaGetByID`, `iiifBuildCanvas`, `searchIndexUpdate`.
- Acronyms are words, not shouts: `Id`, `Url`, `Db`, `Api`, `Html`, `Json`, `Iiif`, `Vtt`.
- Booleans read as predicates: `is…` / `has…`.
- **No snake_case** anywhere in PHP identifiers.
- Classes: StudlyCaps (`LanguageManager`). Third-party (`SafeMySQL`) exempt.
- Constants: SCREAMING_SNAKE.

#### Module prefixes

| Module | Prefix | Examples |
|---|---|---|
| `modules/utilities` (cross-cutting) | `optv` | `optvParseStringId`, `optvHttpRequest`, `optvVarPath` |
| `modules/database` | `optvDb` | `optvDbConnection` |
| `modules/routing` — page handlers | `page` | `pageSearch`, `pageMedia` |
| `modules/routing` — auth | `auth` | `authCheckPage` |
| `modules/templating` | `tpl` | `tplRender` |
| `modules/i18n` | `lang` | free functions only — `LanguageManager` and the `L::` alias are frozen |
| `modules/mail` | `mail` | `mailSend` |
| `modules/images` | `imageCache` | `imageCacheUrl`, `imageCacheDir` |
| `modules/meta-image` | `metaImage` | `metaImageCacheServe` |
| `modules/search` (OpenSearch client) | `search` | `searchBuildQuery` |
| `modules/search-index` | `searchIndex` | `searchIndexUpdate` |
| `modules/statistics-index` | `statsIndex` | `statsIndexSpeech` |
| `modules/statistics` (query side) | `statistics` | `statisticsEntityCounts` |
| `modules/entities/*` | `{entity}` | `mediaGetNode`, `personGetNode` |
| `modules/iiif` | `iiif` | `iiifBuildManifest` |
| `modules/export` | `export` | `exportRequestedFormat` |
| `modules/transcript` | `transcript` | `transcriptBuildVtt` |
| `modules/feed` | `feed` | `feedFetchSpeeches` |
| `modules/notifications` | `alert` / `notification` | `alertMatchMedia`, `notificationSendEmail` |
| `modules/import` | `import` | `importGitSync`, `importMediaWrite` |
| `api/v1` (envelope + validation) | `api` | `apiSuccessResponse`, `apiValidateNumber` |
| `api/v1/modules` (thin wrappers) | `{entity}` | `mediaGetByID` — distinct verb from the node builder's `mediaGetNode` |

### Variables

- camelCase for all new code. Existing locals are **not** mass-renamed.
- The canonical ambient globals are documented and frozen: `$config`, `$db` (platform), `$dbp` (parliament), `$lang`, `$langJSONString`, `$color_scheme`, `$isMobile`. `$color_scheme` is grandfathered — templates and instance customizations read it.

### JavaScript / CSS

- File names kebab-case. First-party only; vendor libraries keep upstream names under `vendor/`.
- JS identifiers follow the PHP rules, applied to new code and to any file being substantially edited. No mass rename of existing JS functions.
- CSS **file names** are kebab-case. CSS **class names, DOM ids and `data-*` attributes are frozen** — instance custom CSS and JS target them.

### Frozen surface — never renamed

API payload keys and `action`/`itemType` string values · all public URL paths · DB table and column names · `$config` array keys · the file names `config.php` / `config.sample.php` / `config-*.php` / `index.php` · the directory names `content/`, `custom/`, `vendor/` · lang JSON keys · CSS classes, DOM ids, data-attributes · the `L::` alias · the ambient globals above · third-party bundled code.

## Working on renames

### Case-only renames

macOS and Windows have case-insensitive filesystems, so `git mv Foo.php foo.php` is a no-op there and the rename never reaches the repository. Use the two-step form:

```bash
git mv Old.php tmp && git mv tmp old.php
```

or set `core.ignorecase=false` for the repository.

### Rename commits are pure

Never mix a rename with a logic change. A rename commit's diff must be reviewable as a rename and nothing else, and it must change **zero bytes** of API output.

### Required gates before pushing a rename

```bash
php bin/api-diff.php --session=$SID          # must be green — renames change no output
php bin/migrate-customizations.php --check   # no old name left in an instance custom/ tree
```

See `tests/README.md` for the API harness and how to mint an admin session.

`--check --core` runs the same grep over the core tree, but is **not** usable as a gate today: the manifest records partial/split moves whose old path legitimately still exists, and its `STALE FILE` rule matches on basename rather than path, so everything it reports is a false positive.

### Instance customizations

Instances keep their overrides in a separate `custom/` tree that is synced into the platform. Renaming a core file breaks any override of it, so every rename lands in `docs/rename-manifest.json`, and `bin/migrate-customizations.php` replays those renames against an instance's `custom/` tree. This is a **required upgrade step** — there is no runtime fallback to old names.

## Schema changes

Every schema change ships **twice**:

1. into the fresh-install dump in `db/`, so new instances get it;
2. as a numbered, forward-only file under `migrations/platform/` or `migrations/parliament/`, for instances that already exist.

Applied with `php bin/migrate.php --apply`, which records each file in a `_migrations` table per database and visits the platform database plus every configured parliament. Run it with no flags first — the default is a dry run.

Every migration must be **safe to re-run** (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE`): instances predating the runner had files applied by hand, so the first `--apply` re-applies them.

The dumps in `db/` are the baseline, never a migration path, and a local gitignored SQL file is not a delivery mechanism — it never reaches a server. See `migrations/README.md`.
