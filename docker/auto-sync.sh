#!/bin/sh
set -e
cd /repo

git config --global --add safe.directory /repo

echo "Auto-sync watcher started. Watching /repo for changes..."

while true; do
    inotifywait -r -e modify,create,delete,move \
        --exclude '(^|/)(\.git|vendor|femi9/vendor)(/|$)|\.log$|shared/\.env$' \
        -q /repo >/dev/null 2>&1

    sleep "${SYNC_DEBOUNCE_SECONDS:-10}"

    if [ -n "$(git status --porcelain)" ]; then
        git add -A

        git -c user.name="${GIT_AUTOSYNC_NAME:-auto-sync}" \
            -c user.email="${GIT_AUTOSYNC_EMAIL:-auto-sync@local}" \
            commit -q -m "Auto-sync: $(date '+%Y-%m-%d %H:%M:%S')"

        if git pull --rebase --autostash -q; then
            if git push -q; then
                echo "Auto-sync: pushed at $(date)"
            else
                echo "Auto-sync: push failed — check remote/credentials, resolve manually (git status)"
            fi
        else
            echo "Auto-sync: rebase conflict — resolve manually (git status), watcher will retry next change"
        fi
    fi
done
