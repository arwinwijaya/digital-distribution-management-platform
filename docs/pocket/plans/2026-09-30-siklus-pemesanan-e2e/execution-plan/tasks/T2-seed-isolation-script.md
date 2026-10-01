# Task T2 — Seed isolation script

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 2: Seed isolation script [prereq] [no-tdd — structural task]

## OBJECTIVE
Create deterministic DB reset entrypoint used before every E2E run (local and CI) via `docker compose exec api php artisan migrate:fresh --seed`.

Steps:
1. Create `scripts/e2e-seed.sh` (bash, `set -euo pipefail`, runs `docker compose exec api php artisan migrate:fresh --seed --force`), make executable, and add `apps/web/package.json` scripts: `"test:e2e:seed": "bash ../../scripts/e2e-seed.sh"` plus root `package.json` script `"e2e:seed": "bash scripts/e2e-seed.sh"` if missing.
2. Verify: `ls -la scripts/e2e-seed.sh && bash -n scripts/e2e-seed.sh && cat apps/web/package.json | grep -A2 test:e2e:seed`
3. Commit: `git commit -m "chore(e2e): add deterministic DB seed isolation script"`

## REFERENCES LOADED
docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md — In-Scope seed isolation, Spec Context (DatabaseSeeder password123), Implementation Notes

## WHY THIS APPROACH
Complexity: lightweight
Justification: Single shell entrypoint, no logic branching; matches existing `docker:up`/`api:artisan` patterns and Route verification (no /test/seed endpoint).

## SANDWICH CONTEXT
[CRITICAL: Must NOT add /test/seed endpoint or change DB schema/migrations]
You are implementing seed isolation for Siklus Pemesanan E2E.
Spec: docs/pocket/spec/2026-09-30-siklus-pemesanan-e2e/siklus-pemesanan-e2e.md
Design decision: Option B Hybrid — migrate:fresh --seed per run (assume sufficient, no snapshot)
Files in scope: scripts/e2e-seed.sh, apps/web/package.json, package.json
Available after: none (prereq)
Architecture rule: Must NOT add test-only API endpoint; must use docker compose exec api php artisan
[RESTATE: Must NOT add /test/seed endpoint or change DB schema]

## DELIVERABLE
[no-tdd — structural task] Seed script exists, is executable, and npm scripts reference it; dry-run syntax check passes.

## QUALITY BAR
Must-have:
  - scripts/e2e-seed.sh uses `docker compose exec api php artisan migrate:fresh --seed`
  - npm scripts wired for local invocation

Must-not-have:
  - New Laravel routes or controllers
  - DB schema changes
  - Hardcoded secrets

Open question risks:
  - migrate:fresh too slow on CI → if wrong: report NEEDS_CONTEXT, propose snapshot restore in follow-up

Rollback note:
  - Delete scripts/e2e-seed.sh + remove npm script entries

Red flags:
  - Work outside listed files → DONE_WITH_CONCERNS

## STOP CONDITIONS
Done when: Script file exists with correct content and package.json scripts present
Uncertain when: Docker not available locally → NEEDS_CONTEXT but still DONE_WITH_CONCERNS (CI will validate)
Escalate when: Asked to create API seed endpoint → STOP
