# Task T1 — Dummy module scaffold + seeded RNG + date utils + JABODETABEK seed constants

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet


## OBJECTIVE
Create the `apps/web/src/dummy/` module skeleton: a deterministic seeded RNG, date-window helpers (rolling 60 days), and JABODETABEK seed constants (5 territories with bbox + anchor coords, outlet names, product SKUs, supplier names). Pure functions only — no store yet.

Files:
- Create: `apps/web/src/dummy/rng.ts`
- Create: `apps/web/src/dummy/dates.ts`
- Create: `apps/web/src/dummy/seed.ts`
- Create: `apps/web/src/dummy/index.ts`
- Test: `apps/web/src/dummy/seed.test.ts`

Steps:
1. Write failing test for: deterministic seed + rolling 60-day window
   Test file: `apps/web/src/dummy/seed.test.ts`
   Level: unit

   Test intent:
   Given the fixed seed constant (deterministic, NOT derived from Date.now())
   When the RNG generates a sequence twice
   Then:
   - both sequences are byte-identical (determinism)
   - territory seed yields exactly 5 JABODETABEK territories: Jakarta, Bogor, Depok, Tangerang, Bekasi, each with anchor lat/lon inside lat -6.9..-5.9, lon 105.9..107.3
   - date helper returns a window where end = today (Asia/Jakarta) and start = end − 60 days

   Exercise through:
   - `createSeededRng(seed)` from `apps/web/src/dummy/rng.ts`
   - `dummyWindow(today?)` from `apps/web/src/dummy/dates.ts`
   - `JABODETABEK_TERRITORIES`, `DUMMY_SEED` from `apps/web/src/dummy/seed.ts`

   Test doubles:
   - mock/fake: none (pure functions; inject explicit `today` date where needed)
   - do NOT mock: rng, dates, seed constants

   Expected RED:
   - module files do not exist → import/symbol error

2. Run test — verify FAIL:
   `cd apps/web && npx jest src/dummy/seed.test.ts --runInBand`
   Expected failure: `Cannot find module '@/dummy/...'` or `createSeededRng is not a function`

3. Implement minimal code to satisfy the test:
   File: `apps/web/src/dummy/rng.ts` — mulberry32-style seeded RNG exposing `next()`, `int(min,max)`, `pick(arr)`, `shuffle(arr)`
   File: `apps/web/src/dummy/dates.ts` — `dummyWindow(today = new Date())` returning `{ start, end }` ISO date strings (Asia/Jakarta day math), plus `daysBetween(start,end)` list
   File: `apps/web/src/dummy/seed.ts` — `DUMMY_SEED = 'ddp-jabodetabek-v1'` (fixed string), `JABODETABEK_TERRITORIES` (5 entries with bbox + anchor coords), outlet-name / product-SKU / supplier-name arrays
   File: `apps/web/src/dummy/index.ts` — barrel re-exports

4. Run test — verify PASS:
   `cd apps/web && npx jest src/dummy/seed.test.ts --runInBand`
   Expected: PASS

5. Refactor while green (bounded):
   - Rule of three: same logic appears 3+ times in the files in scope → extract a named, domain-scoped helper — never a generic `utils.ts`
   - A modified file crosses ~300 lines, or a function exceeds ~50 lines → split/extract
   - Refactor only within task-scope files
   - Re-run test: `cd apps/web && npx jest src/dummy/seed.test.ts --runInBand` — must stay PASS

6. Commit:
   `git add apps/web/src/dummy/rng.ts apps/web/src/dummy/dates.ts apps/web/src/dummy/seed.ts apps/web/src/dummy/index.ts apps/web/src/dummy/seed.test.ts`
   `git commit -m "feat(dummy): scaffold deterministic seed, RNG, and JABODETABEK constants"`

## REFERENCES LOADED
docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md — Implementation Notes (generate at toggle-ON, rolling `now − 60d`, seed fixed not Date.now()); Story 2 R2 (seeded determinism); Story 2 geo scenario bbox (lat -6.9..-5.9, lon 105.9..107.3).
Preflight: jest 29 + jsdom + babel-jest; `@/` maps to `src/`; colocated `*.test.ts(x)` convention.

## WHY THIS APPROACH
Justification: Pure-function foundation with no component/store coupling — everything downstream (factory, guards, Topbar) imports these. Determinism is the acceptance linchpin (refresh must render identical numbers), so the RNG+seed is tested first.
Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: Touch ONLY apps/web/src — never apps/api, database/*, or docker-compose.yml]
You are implementing the dummy module scaffold for Dummy Mode JABODETABEK.
Spec: docs/pocket/spec/2026-02-14-dummy-mode-jabodetabek/dummy-mode.md
Design decision: Option A — Zustand singleton store + per-API-function guard (this task is the pure-function foundation, T1 of 3 phases)
Files in scope: `apps/web/src/dummy/rng.ts`, `apps/web/src/dummy/dates.ts`, `apps/web/src/dummy/seed.ts`, `apps/web/src/dummy/index.ts`, `apps/web/src/dummy/seed.test.ts` — no other files
Available after: none (prereq)
Architecture rule: No new production dependencies; Zustand/Jest already installed. Files stay under `apps/web/src/dummy/`.
[RESTATE: Touch ONLY apps/web/src — never apps/api, database/*, or docker-compose.yml]

## DELIVERABLE
Verification — task is DONE when all pass:

Given the fixed DUMMY_SEED, When the seeded RNG generates a sequence twice, Then both sequences are identical
Given dummyWindow(), When called, Then end = today and start = end − 60 days (Asia/Jakarta)
Given JABODETABEK_TERRITORIES, When read, Then exactly 5 territories each with coords inside the JABODETABEK bbox

All tests PASS. Commit exists with message matching `feat(dummy): scaffold deterministic seed, RNG, and JABODETABEK constants`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Deterministic: same seed → identical sequence across runs
  - Seed is a FIXED constant (must NOT be derived from Date.now() — per edge-case hunter watchout)
  - 5 territories with anchor coords inside the spec bbox
  - Tests written BEFORE implementation (TDD — not after)
  - Commit message follows conventional commits format

Must-not-have:
  - Backend/API/DB/docker changes
  - New production dependencies
  - Generating dummy at import time (generation happens at toggle-ON in T2 — this task only defines utils + constants)

Open question risks:
  - none (all assumptions documented; Zod deferred as follow-up)

Rollback note:
  - Delete `apps/web/src/dummy/*` — no migration to undo

## STOP CONDITIONS
Done when: DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: n/a
Escalate when: task touches files outside listed scope
