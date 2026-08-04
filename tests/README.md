# Tests

## API regression harness (`bin/api-diff.php`)

The restructuring work has one hard invariant: **external API responses stay byte-identical**. This harness is what enforces it. Run it before and after every API-touching change — including pure renames, which must not alter a single output byte.

It fetches every request in `tests/fixtures/api-corpus.txt`, normalizes the volatile parts of the response, and compares against the recorded fixtures in `tests/fixtures/api/`.

### Getting an admin session

Some corpus entries are marked `@admin` and need a logged-in admin. On a dev box you can mint a session file directly rather than logging in through the browser — write a session file into PHP's `session.save_path` (XAMPP: `/Applications/xampp/xamppfiles/temp`) whose `userdata.id` is a real active admin user:

```bash
SID=$(php -r 'echo bin2hex(random_bytes(13));')
printf 'userdata|a:1:{s:2:"id";i:3;}login|b:1;lang|s:2:"de";' \
  > /Applications/xampp/xamppfiles/temp/sess_$SID
```

### Usage

```bash
# Compare current responses against the fixtures (exit 1 on any mismatch)
php bin/api-diff.php --session=$SID

# Re-record fixtures — only when the change in output is intended
php bin/api-diff.php --update --session=$SID

# Fetch everything twice and report fields that differ between the two runs.
# Run this after adding corpus entries: anything reported "UNSTABLE" needs a
# normalization rule before its fixture can be trusted.
php bin/api-diff.php --selftest --session=$SID

# Narrow to a subset while iterating
php bin/api-diff.php --session=$SID --filter=iiif --verbose
```

Without `--session`, `@admin` entries record or compare their anonymous response; the harness warns when it detects this.

Note that `--selftest` fetches each URL twice back to back, which is deliberately a *narrow* window. Values that only drift over seconds (uptime counters, free disk) can pass the selftest and still fail a later comparison — when that happens, add the key to `API_DIFF_VOLATILE_KEYS` rather than re-recording.

### What the corpus covers

`getItem` for all eight entity types · `search` for every itemType, plus filtered and paged variants · `autocomplete` · `status` · content negotiation (`?format=iiif|akomantoso|parlamint`, `transcript.vtt`) · IIIF collection and search paths · the `entityDump` bulk export in both URL forms, plus the `/data/entity-dump` redirect · session-gated actions recorded **both** anonymously and with a session · error cases (unknown id, malformed id, missing `action`/`itemType`, unknown action/itemType, out-of-range limit).

Every one of these is exercised through **both URL styles** — the pretty paths that `api/v1/.htaccess` rewrites, and the raw `?action=&itemType=` form. Phase 5g replaces that rewriting with FastRoute and must keep answering both.

### Normalization

Two mechanisms, both in `bin/api-diff.php`:

- `API_DIFF_VOLATILE_KEYS` — JSON keys replaced with `<<NORMALIZED>>` at any depth (timestamps, `took`, durations, cache ages, and the live gauges in `action=system&itemType=status`: disk/index/database byte counts, shard counts, heap and CPU). Structural values in the same payload — `tables`, `nodes`, the reported labels, paths and hosts — stay pinned, because those are what a refactor can actually break.
- `API_DIFF_ROUNDED_KEYS` — keys rounded instead of blanked. Only `_score` so far: BM25 relevance shifts in the 5th decimal when OpenSearch merges segments, so full precision makes every index optimize look like a relevance regression, while blanking it would stop catching a real one. Rounding keeps ranking and magnitude pinned.
- `API_DIFF_VOLATILE_NESTED_KEYS` — `parent => [child, …]`, for keys that are volatile only under one parent. `repository.remote` in `action=status` is fetched live from the data repository's forge, so its `lastUpdated` and `numberOfSessions` move whenever anyone commits there — nothing to do with this codebase. A blanket `lastUpdated` rule would also blank `repository.local`, `database` and `index`, which are exactly what a refactor can break. `location` stays pinned: it comes from `$config`, not the network.
- `API_DIFF_VOLATILE_PATTERNS` — regexes for non-JSON bodies (RSS `lastBuildDate`, and the render-time generation date in Akoma Ntoso / TEI exports). Both XML rules are scoped to the generation date specifically: `FRBRdate` by `name="generation"`, TEI `<date>` by its position in `<publicationStmt>`. Sitting dates are content and stay pinned.

Image responses are pinned by byte length + sha256 rather than content, so fixtures stay small and readable.

Only the status line and a few pinned headers (`content-type`, `x-robots-tag`, `location`) are compared. `Date`, `Set-Cookie`, `ETag` and `Content-Length` are transport noise.

### Corpus entries must be deterministic

Anything unsorted is a trap. `result-table.php?a=search&queryOnly=1` originally had no `sort`, and unfiltered `match_all` ordering over 2.5M documents is shard- and segment-dependent — a single index optimize reshuffled which 40 rows came back and produced a 638-line diff with no code change behind it. It now carries `&sort=date-asc`. When adding an entry that lists results, pin the order explicitly.

### Direct-served AJAX fragments are in the corpus on purpose

`result-grid.php`, `result-table.php` and `pages/media/components/player.php` are fetched by JS, not by the router. Their URLs are **string-built** in JavaScript (`` `result-${view}.php` ``), so no static check and no page fetch catches a rename that breaks them — a curl of the page returns the shell and never fires the XHR. They are corpus entries so the harness exercises them directly.

### Content mismatch vs. infrastructure failure

The harness reports these separately. A `FAIL` is a content difference — the API changed. An `INFRA` line means the server did not answer (connection reset, 5xx) even after three retries with backoff; sweeping 65 requests back to back can saturate a local Apache, and that says nothing about the API. Infra failures still exit non-zero, because the sweep was inconclusive rather than clean — re-run before drawing any conclusion.

### Fixtures depend on the dataset

Entity ids in the corpus are real rows in the DE dev dataset. If that dataset is rebuilt or re-imported, the fixtures must be re-recorded — a mass failure right after an import means stale fixtures, not an API regression. Check with `--selftest` first: if entries are *stable* but differ from the fixtures, it is the data that moved.
