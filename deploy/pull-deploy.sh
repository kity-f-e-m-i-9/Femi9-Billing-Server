#!/bin/bash
# Pull-based deploy for the cPanel server (replaces FTP deploys from GitHub Actions).
#
# Runs ON THE SERVER from cron (every minute). If GitHub's main branch has new
# commits it updates a private clone, then copies the files into the web root
# with rsync. It never deletes anything in the web root (no --delete), so
# uploaded files, logs and .env stay untouched; a file removed from the repo has
# to be removed from the server by hand.
#
# Setup (one time, as the cPanel account user, see README in the chat/docs):
#   REPO_DIR   = private clone of the repo        (default ~/deploy-repo)
#   WEB_ROOT   = the site's document root         (default ~/public_html)
# Needs a read-only GitHub "deploy key" for the clone.

set -euo pipefail

REPO_DIR="${REPO_DIR:-$HOME/deploy-repo}"
WEB_ROOT="${WEB_ROOT:-$HOME/public_html}"
BRANCH="${BRANCH:-main}"
LOG="${LOG:-$HOME/deploy.log}"
LOCK="$HOME/.pull-deploy.lock"

# The whole script lives in a function so bash has read it completely before
# `git reset` replaces this very file mid-run.
main() {
  exec 9>"$LOCK"
  flock -n 9 || exit 0          # another run is still going

  cd "$REPO_DIR"
  git fetch -q origin "$BRANCH"
  LOCAL=$(git rev-parse HEAD)
  REMOTE=$(git rev-parse "origin/$BRANCH")
  [ "$LOCAL" = "$REMOTE" ] && [ -z "${FORCE:-}" ] && exit 0

  echo "[$(date '+%F %T')] deploying${DRY:+ (DRY RUN)} ${LOCAL:0:7} -> ${REMOTE:0:7}" >> "$LOG"
  git reset -q --hard "origin/$BRANCH"

  # NOTE: .user.ini files in the repo are local-MAMP-only PHP overrides - never deploy them.
  RSYNC_FLAGS="-rlptD"; [ -n "${DRY:-}" ] && RSYNC_FLAGS="-rlptDn --itemize-changes"   # DRY=1 shows what would change
  rsync $RSYNC_FLAGS \
    --exclude='.git' --exclude='.github' --exclude='deploy' --exclude='.cpanel.yml' \
    --exclude='.claude' --exclude='docs' --exclude='sql' \
    --exclude='.env' --exclude='.env.*' \
    --exclude='.user.ini' \
    --exclude='**/logs/**' --exclude='error_log' \
    ./ "$WEB_ROOT/" >> "$LOG" 2>&1

  echo "[$(date '+%F %T')] done $(git rev-parse --short HEAD)" >> "$LOG"
}

main "$@"
exit $?
# deploy-test: harmless marker to verify the cron picks up pushes
