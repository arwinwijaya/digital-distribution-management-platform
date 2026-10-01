#!/usr/bin/env bash
set -euo pipefail

# Purge local E2E artifacts older than 7 days.
# Usage: npm run test:e2e:purge (from apps/web/)
#        bash scripts/purge-e2e-artifacts.sh (from repo root)

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
REPO_ROOT=$( cd "$SCRIPT_DIR/.." && pwd )
WEB_DIR="$REPO_ROOT/apps/web"
RESULTS_DIR="$WEB_DIR/test-results"
LOGS_DIR="$RESULTS_DIR/e2e-logs"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$TIMESTAMP] Purging E2E artifacts older than 7 days in $RESULTS_DIR"

if [[ -d "$RESULTS_DIR" ]]; then
    # Delete screenshot, trace, video subdirectories
    for subdir in "$RESULTS_DIR"/{screenshots,traces,videos}; do
        if [[ -d "$subdir" ]]; then
            find "$subdir" -type f -name '*.png' -o -name '*.json' -o -name '*.zip' -o -name '*.webm' \
                -mtime +7 -delete 2>/dev/null || true
            echo "  Purged files older than 7 days in $subdir"
            # Remove empty subdirectories
            find "$subdir" -mindepth 1 -type d -empty -delete 2>/dev/null || true
        fi
    done

    # Keep the e2e-logs directory (clean by age, but the logger creates new files)
    if [[ -d "$LOGS_DIR" ]]; then
        find "$LOGS_DIR" -type f -name '*.jsonl' -mtime +7 -delete 2>/dev/null || true
        echo "  Purged JSONL logs older than 7 days in $LOGS_DIR"
    fi

    # Remove empty directories inside test-results
    find "$RESULTS_DIR" -mindepth 1 -type d -empty -delete 2>/dev/null || true
else
    echo "  No test-results directory found, skipping."
fi

echo "[$TIMESTAMP] Purge complete"
