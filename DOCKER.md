# Running this project with Docker

Two ways to hand this to another developer, both using the same
`docker-compose.yml` (app + MySQL). Pick one.

## Common first-time setup (both modes)

1. Clone the repo and `cd` into it.
2. Run:
   ```
   docker compose up --build
   ```
3. Open http://localhost:8080/femi9/billing/login/ and sign in as the demo
   Territory Partner — mobile `9000000001`, password `Demo@123`.

The DB comes pre-seeded (full schema + public state/district reference data
+ a synthetic demo login and product catalog — no real customer/business
data, see `docker/db-init/README.md`). Their DB stays entirely local to
their machine — nothing they do to it syncs back to anyone else's database.
Want your own real data instead? Drop a dump in `docker/db-init/` before
the first run — see that folder's README.

Docker downloads PHP, MySQL, and all Composer packages by itself —
nothing needs to be installed on the host except Docker.

The whole repo is bind-mounted into the container, so their edits (in
their own IDE, or inside the container) show up on their disk instantly
— no rebuild needed for PHP/JS/CSS changes. `vendor/` and `femi9/vendor/`
stay container-managed (rebuilt by `composer install` during the image
build) so a fresh checkout without those folders still works.

If `femi9/billing/shared/.env` already exists on their machine, the
container leaves it alone and uses it as-is. If it doesn't exist yet
(normal for a fresh clone, since it's git-ignored), the container
generates it automatically from the `environment:` values in
`docker-compose.yml` on first boot.

## Mode 1 — manual git (they push, you pull)

Just use `docker compose up --build` as above. They edit code, then run
`git add` / `git commit` / `git push` themselves like normal. You `git
pull` to get their changes. No extra service needed.

## Mode 2 — auto-sync (commits + pushes for them automatically)

Adds a watcher container that detects file changes and runs
`git add / commit / pull --rebase / push` on its own, so they don't have
to run git by hand.

```
docker compose -f docker-compose.yml -f docker-compose.autosync.yml up --build
```

Requirements on their laptop:
- Git already configured there with push access to this repo (SSH key or
  a credential helper) — the container reuses their host `~/.ssh` and
  `~/.gitconfig`, it carries no credentials of its own.
- They're on a branch that's fine to auto-push to (e.g. their own
  feature branch, not `main` directly).

Behavior:
- Debounces for `SYNC_DEBOUNCE_SECONDS` (default 10s) after the last
  change, then commits with an "Auto-sync: <timestamp>" message and
  pushes.
- Runs `git pull --rebase --autostash` before pushing, so it picks up
  your changes too. If that hits a conflict, it stops and logs a message
  telling them to resolve it manually with `git status` — it will never
  auto-resolve a conflict or force-push.
- `.git/`, `vendor/`, `femi9/vendor/`, `*.log`, and
  `femi9/billing/shared/.env` are ignored by the watcher (the `.env` is
  also git-ignored, so it's never committed either way).

## Notes

- MySQL data persists in a named Docker volume across restarts. To start
  completely fresh: `docker compose down -v`.
- To stop: `docker compose down` (keeps DB data). To rebuild after a
  Dockerfile/composer.json change: `docker compose up --build`.
