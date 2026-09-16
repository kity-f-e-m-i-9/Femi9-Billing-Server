# Database seed

This folder ships with a ready-to-run demo database — no real customer or
business data:

- `01-schema.sql` — full table structure (empty, no rows).
- `02-reference-data.sql` — public reference data only: Indian states and
  district names. The `usertype`/`userid`/`assigned_SSID` columns are
  placeholder values, not real Super Stockist IDs.
- `03-dummy-seed.sql` — synthetic demo data: one working login and a small
  fake product catalog.

**Demo login:** Territory Partner, mobile `9000000001`, password `Demo@123`.

MySQL auto-imports every `*.sql` file in this folder, in filename order, the
**first** time the `db` container's data volume is created (it will NOT
re-import on later runs — `docker compose down -v` resets it).

## Using your own real data instead

Drop your own dump here too (e.g. `04-my-data.sql`) — it'll run after the
files above. Or replace them entirely if you don't want the demo data at all.

```
mysqldump -u root -p billing0femi9_billingapp > docker/db-init/04-my-data.sql
```

Real dumps you add here are git-ignored automatically — only the three
`01`/`02`/`03` files above are tracked in the repo (see `.gitignore`). Never
commit a real dump: this repo holds live customer, invoice, and GST data.
