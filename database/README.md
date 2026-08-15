# Database migrations

HerikaServer schema changes are ordered, immutable migrations. Normal requests only verify the migration ledger; they never run DDL.

## Operator commands

Back up the database before applying migrations.

```bash
php scripts/database.php status
php scripts/database.php migrate
php scripts/database.php verify
php scripts/database.php doctor
```

Existing databases without `chim_meta.schema_migrations` must use the compatibility bridge once:

```bash
php scripts/database.php legacy-bridge
```

The bridge runs the frozen legacy updater and the narrowly audited `legacy-baseline-repair.sql`, validates the generated `202608110000` baseline contract, records that baseline, applies every pending migration in its own transaction, and validates the current schema contract. It is not called by normal web or game traffic.

## Adding a schema change

1. Add `database/migrations/YYYYMMDDNNNN_short_name.sql`. Versions are globally ordered and filenames are lowercase.
2. Use raw SQL by default. Do not add `BEGIN`, `COMMIT`, or `ROLLBACK`; the runner owns the transaction.
3. Use a PHP migration only when a data transformation genuinely needs application logic. The file must return a callable that accepts the PostgreSQL connection.
4. Apply the migration to a disposable database, then regenerate `database/schema-contract.json` with the locally maintained schema-contract tool outside this repository.

5. Test both a fresh database and a copy of an existing database. Run `migrate` twice, then run `verify`.

Never edit an applied migration. The runner checks the stored name and SHA-256 checksum and rejects drift, missing files, and gaps.

## Contracts and legacy code

- `database/baseline/202608110000_contract.json` is the generated structural contract for the one-time legacy transition. It is immutable.
- `database/schema-contract.json` is the generated contract for the latest code and migration. It detects missing tables, columns, constraints, indexes, views, and required extensions.
- `debug/db_updates.php` is frozen as a temporary compatibility bridge. New changes must not be added there.
- `data/database_default.sql` remains the legacy seed during the transition. The installer immediately runs the bridge after importing it.
