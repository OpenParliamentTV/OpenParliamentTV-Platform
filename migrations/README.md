# Schema migrations

Numbered, forward-only SQL files applied on top of an existing database. `db/openparliamenttv_platform.sql` and `db/openparliamenttv_parliament.sql` stay the fresh-install baseline and already contain everything here; these files exist for databases that were created before a change.

```
migrations/
  platform/    applied once to the platform database
  parliament/  applied once to every configured parliament database
```

Naming: `NNNN_snake_case_description.sql`, zero-padded, unique per directory. The number is **application order, not authorship order** — `0001` (entity_remap) was simply the first one written; `0002`–`0006` backfill older changes that had until then lived only in a local scratch file. They touch independent tables, so the ordering between them carries no meaning.

## Running them

```bash
php bin/migrate.php                          # report what is pending (default: dry run)
php bin/migrate.php --apply                  # apply it
php bin/migrate.php --status                 # what has been applied, per database
php bin/migrate.php --apply --scope=platform # platform database only
php bin/migrate.php --apply --parliament=DE  # one parliament database
```

The runner records every applied file in a `_migrations` table inside each database, visits the platform database and **every** configured parliament, and stops at the first failure in a database without recording that file — so a half-applied migration is never marked done. Exit codes: `0` success or nothing pending, `1` a migration failed, `2` bad usage.

A first run against an instance that had the older files applied by hand simply re-applies them. That is safe, and it is why the next rule exists.

## Every file must be safe to re-run

`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `ADD KEY IF NOT EXISTS`, `INSERT IGNORE`. `0005` and `0006` need MariaDB 10.0+ for `IF NOT EXISTS` on index/column DDL.

Verified for the current set: on a database with every one of these features dropped, applying all six produces a schema **identical** to the fresh-install dump; three further passes, with an operator edit and a deleted seed row in between, change nothing but the `AUTO_INCREMENT` counter. One asymmetry is inherent — a deliberately deleted seed row comes back on a re-run, under a new id, because "deleted" and "never inserted" are indistinguishable from inside the file. Now that `bin/migrate.php` records what ran, that only arises if someone re-applies a file by hand.

## Adding a schema change

1. Add the DDL to the fresh-install dump in `db/`, so new instances get it.
2. Add a migration here, for instances that already exist.
3. Make it re-runnable.

Anything a deployed instance needs belongs here. A local, gitignored SQL file does not reach a server — that is the gap this directory closes.
