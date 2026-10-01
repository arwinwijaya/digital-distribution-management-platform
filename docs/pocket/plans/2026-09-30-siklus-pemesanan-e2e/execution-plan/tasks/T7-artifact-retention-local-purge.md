# Task T7 — Artifact retention + local purge

**Phase:** 2
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: Artifact retention + local purge [depends: T1] [no-tdd — structural task]

## OBJECTIVE
Wire artifact retention: CI 30 days via `actions/upload-artifact retention-days`, local purge script for 7-day retention.

Steps:
1. Create `scripts/purge-e2e-artifacts.sh` that deletes `apps/web/test-results/` and `apps/web/test-results/e2e-logs/` entries older than 7 days (`find ... -mtime +7 -delete`), and add `apps/web/package.json` script `"test:e2e:purge": "bash ../../scripts/purge-e2e-artifacts.sh"`.
2. Verify: `bash -n scripts/purge-e2e-artifacts.sh && cat apps/web/package.json | grep test:e2e:purge && ls -la scripts/purge-e2e-artifacts.sh`
3. Commit: `git commit -m "chore(e2e): add artifact purge script for 7-day local retention"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — Story 3 Rule 2, Open Questions (retensi), Implementation Notes

## WHY THIS APPROACH
Complexity: lightweight
Justification: Two small shell+config changes; no test logic; retention enforced via GitHub Actions native param + local cron/manual script.

## SANDWICH CONTEXT
[CRITICAL: Must NOT add remote log pipeline; retention only via upload-artifact and local script]
You are implementing artifact retention for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: 30 days CI, 7 days local
Files in scope: scripts/purge-e2e-artifacts.sh, apps/web/package.json, .github/workflows/e2e.yml (retention param added in T8 but script is T7)
Available after: T1 (test-results path), T4 (logger path)
Architecture rule: No Loki/Elastic, no new infra
[RESTATE: Must NOT add remote log pipeline]

## DELIVERABLE
[no-tdd — structural task] Purge script exists and package script wired; CI workflow (T8) will set retention-days:30

## QUALITY BAR
Must-have:
  - Script deletes test-results older than 7 days safely (dry-run check)
  - Script is executable

Must-not-have:
  - Backend changes
  - Deleting source files

Open question risks:
  - Retention 30/7 assumed → if wrong: adjust param, report

Rollback note:
  - Delete script + remove npm script

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: Script syntax passes and npm script present
Uncertain when: CI path differs → NEEDS_CONTEXT
Escalate when: Asked to add external storage → STOP
