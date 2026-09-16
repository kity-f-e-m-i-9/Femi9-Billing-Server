# Database seed

Drop a `.sql` dump here (e.g. `billing.sql`) before the **first** `docker compose up`.
MySQL auto-imports every `*.sql` file in this folder when the `db` container's
data volume is empty (first boot only — it will NOT re-import on later runs).

To create a dump from an existing database:

```
mysqldump -u root -p billing0femi9_billingapp > docker/db-init/billing.sql
```

If you don't have a dump yet, the app will still start, but every page that
needs a table will fail until you import one (see `femi9/billing/db_migrations/`
for the incremental schema — there is no single full schema file in this repo).
