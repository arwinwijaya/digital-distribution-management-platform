# Task T3 — Implement safe pre-pilot controls, correlation ID, and operational event journal

**Phase:** 1
**Depends:** T1
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Implement safe pre-pilot controls, correlation ID, and operational event journal [depends: T1] [test-risk]

## OBJECTIVE
Add independently kill-switchable pre-pilot controls and safe request correlation without changing any existing transaction behavior or response JSON body. Persist only redacted operational request outcomes in an additive journal when the feature is enabled.

Files:
- Create: `apps/api/config/pre_pilot.php`
- Create: `apps/api/app/Services/PrePilotFeatureGate.php`
- Create: `apps/api/app/Http/Middleware/AttachCorrelationId.php`
- Create: `apps/api/database/migrations/2026_09_15_000001_create_operational_events_table.php`
- Create: `apps/api/app/Models/OperationalEvent.php`
- Create: `apps/api/app/Services/OperationalEventService.php`
- Modify: `apps/api/app/Http/Kernel.php`
- Test: `apps/api/tests/Feature/PrePilotControlsTest.php`

Steps:

1. Write failing test for: correlation header behavior, JSON compatibility, and journal outcome capture.
   Test file: `apps/api/tests/Feature/PrePilotControlsTest.php`
   Level: integration
   Test intent:
   Given a request with a safe `X-Correlation-ID`, When `/api/health` or an existing authenticated endpoint completes, Then the same ID is returned in the response header, the existing JSON body/status remains unchanged, and no secret is persisted.
   Given no valid ID or an unsafe/oversized ID, When the request completes, Then a server-generated bounded ID is returned.
   Given the pre-pilot flag is enabled, When one request succeeds or fails, Then exactly one operational event stores route/action, actor when available, status, outcome, error class, timestamp, and correlation ID with redacted metadata.
   Given the journal write fails, When the original request completes, Then the original response remains unchanged and the failure is logged safely.
   Exercise through: Laravel HTTP middleware and existing public route, including an exception response boundary.
   Test doubles: no production service mocks; use real request and database; simulate journal failure only at the persistence seam.
   Expected RED: middleware/config/journal and success/failure wiring do not exist.
2. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php --filter=correlation
   ```
   Expected failure: missing middleware or header assertion.
3. Implement minimal code:
   - Add `pre_pilot.php` with `enabled` and `kill_switch` environment values defaulting safely off.
   - Add `PrePilotFeatureGate` with one public `isEnabled()` boundary; new features must use it, existing transactions must not depend on it.
   - Add `AttachCorrelationId` to the API middleware group. Accept only bounded safe characters; otherwise generate a UUID. Put the ID in request attributes/log context and response header, including exception responses.
   - Add the operational event migration/model/service with correlation ID, route/action, actor ID, HTTP status, outcome, error class, timestamps, and redacted metadata. Do not store request body, auth header, token, password, phone, or payment credential.
   - Wire the middleware/service around `$next($request)` so one success or failure outcome is recorded after response/exception classification; use a stable event identity to prevent duplicate writes.
   - Journal only when the feature gate is enabled; journal failure must not fail or roll back the original request and must emit a safe log entry.
   - Define bounded correlation input as a non-empty value of at most 100 ASCII characters from `[A-Za-z0-9._:-]`; generated IDs are UUIDs.
4. Run focused test — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php --filter=correlation
   ```
5. Write failing test for: flag and kill switch isolation.
   Test intent:
   Given the flag is off or kill switch is on, When existing order/payment/health behavior is exercised, Then existing workflow remains available and only additional journal behavior is disabled; when enabled, the new journal is written with redacted metadata.
   Exercise through: config gate, existing route, and migration.
6. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php --filter=flag
   ```
   Expected RED: the flag does not yet isolate journal capture or kill-switch behavior.
7. Implement minimal flag behavior and migration rollback without changing existing JSON bodies or source rows.
8. Run all task tests — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/PrePilotControlsTest.php
   ```
9. Refactor while green:
   - Keep redaction and event identity in `OperationalEventService`; no generic utility.
   - Ensure middleware/database failure is fail-open for existing requests but visible in safe application logs.
   - Verify migration `down()` drops only the additive operational journal table.
10. Commit:
   ```bash
   git add apps/api/config/pre_pilot.php apps/api/app/Services/PrePilotFeatureGate.php apps/api/app/Http/Middleware/AttachCorrelationId.php apps/api/database/migrations/2026_09_15_000001_create_operational_events_table.php apps/api/app/Models/OperationalEvent.php apps/api/app/Services/OperationalEventService.php apps/api/app/Http/Kernel.php apps/api/tests/Feature/PrePilotControlsTest.php
   git commit -m "feat(pre-pilot): add safe controls and correlation IDs"
   ```

## REFERENCES LOADED

- T1 compatibility contract.
- `apps/api/app/Http/Kernel.php` — middleware groups and aliases.
- `apps/api/routes/api.php` — existing API route behavior.
- `apps/api/config/orders.php`, `apps/api/config/whatsapp.php` — existing additive config conventions.
- `apps/api/tests/Feature/OperationalReadinessTest.php` — config/database test conventions.

## WHY THIS APPROACH

Complexity: deep. Middleware, configuration, persistence, redaction, and fail-open behavior can accidentally affect every API request; regression isolation is required.

## SANDWICH CONTEXT

[CRITICAL: New controls must be additive and fail-open for existing transactions; the operational journal is not transaction truth.]
You are implementing pre-pilot operational controls.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: listed config, middleware, journal, kernel, and test files only.
Available after: T1.
Architecture rule: no new dependency; do not mutate order/invoice/payment/delivery/WhatsApp/pipeline source rows; preserve existing JSON bodies.
[RESTATE: New controls must be additive and fail-open for existing transactions; the operational journal is not transaction truth.]

## DELIVERABLE

Given a valid or absent correlation header, When an API request completes, Then a safe correlation ID is returned without JSON contract changes.
Given flag off/kill switch on, When existing transactions run, Then they remain available and no new operational side effect is required.
Given flag on, When a request completes or fails, Then one redacted operational event is available without changing the request outcome.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Safe defaults and tested kill switch.
- Correlation response header.
- Redaction and journal failure isolation.
- Additive reversible migration.

Must-not-have:
- No source transaction mutation.
- No response body/status breaking change.
- No new retry/auth/observability dependency.

## STOP CONDITIONS

Done when: controls tests pass and existing compatibility suite remains green.
Escalate when: middleware or journal cannot fail open without hiding a production failure.
