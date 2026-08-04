# Schema migrations

Numbered, forward-only SQL files applied on top of an existing database. `db/openparliamenttv_platform.sql` and `db/openparliamenttv_parliament.sql` stay the fresh-install baseline and already contain everything here; these files exist for databases that were created before a change.

```
migrations/
  platform/    applied once to the platform database
  parliament/  applied once to every configured parliament database
```

Naming: `NNNN_snake_case_description.sql`, zero-padded, unique per directory.

Every file must be **safe to re-run** — `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE` — because until `bin/migrate.php` exists to record what has been applied, they are run by hand.

Apply them with the MySQL client:

```bash
mysql -u <user> -p <platform-db>   < migrations/platform/0001_entity_remap.sql
```

`db/db_migration.sql` is a gitignored local scratch pad and never reaches a server. Anything a deployed instance needs belongs here instead.
