# Task T4 — Expose readiness and operational issue diagnostics

**Phase:** 1
**Depends:** T2, T3
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Expose readiness and operational issue diagnostics [depends: T2, T3] [test-risk]

## OBJECTIVE
Provide admin-only, read-only API surfaces for pre-pilot readiness and operational issues using existing authorization and the pre-pilot feature gate. Define exact disabled responses, bounded filters, pagination, severity mapping, and detail schemas; readiness checks and issue queries must not mutate source transactions or trigger retries.

Files:
- Create: `apps/api/app/Services/OperationalReadinessService.php`
- Create: `apps/api/app/Services/OperationalIssueService.php`
- Create: `apps/api/app/Http/Controllers/OperationalReadinessController.php`
- Create: `apps/api/app/Http/Requests/ListOperationalIssuesRequest.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/OperationalDiagnosticsTest.php`

Steps:

1. Write failing test for: admin-only readiness endpoint and feature-gate behavior.
   Test file: `apps/api/tests/Feature/OperationalDiagnosticsTest.php`
   Level: integration
   Test intent:
   Given an admin and a non-admin, When they request `GET /api/admin/operations/readiness`, Then admin receives HTTP 200 with `{status: "success", data: {status: "ready|warning|blocked", checks: [{name,status,evidence,remediation}], evaluated_at, correlation_id}}`; non-admin receives HTTP 403 using the existing authorization style; no order/invoice/payment/delivery/WhatsApp/pipeline source row changes.
   Checks must cover database connectivity, pre-pilot flag/kill switch, `Asia/Jakarta` scheduler convention with `withoutOverlapping`, WhatsApp required configuration when enabled, and latest data pipeline state.
   Given the flag is off or kill switch is on, When any new operations endpoint is requested, Then it returns HTTP 503 with `status: "error"` and `code: "pre_pilot_disabled"`, while existing transaction endpoints remain available.
   Test doubles: controlled config/database and existing model factories; no source-service mocks.
   Expected RED: routes, controller/service, feature-gate enforcement, and exact response contract do not exist.
2. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php --filter=readiness
   ```
   Expected failure: routes/controller/service do not exist.
3. Implement minimal readiness service/controller and admin routes. Use current `isAdmin()` authorization convention; apply `PrePilotFeatureGate` to `/api/admin/operations/readiness` and `/api/admin/operations/issues*`; do not create `admin ops` role or invoke scheduler/pipeline from HTTP. Keep readiness HTTP 200 when checks are warning/blocked, and use HTTP 503/code `pre_pilot_disabled` only when the feature gate is disabled.
4. Run focused test — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php --filter=readiness
   ```
   Expected: readiness and disabled-gate scenarios PASS; source snapshots are unchanged.
5. Write failing test for: operational issue list/detail and correlation lookup.
   Test file: `apps/api/tests/Feature/OperationalDiagnosticsTest.php`
   Level: integration
   Test intent:
   Given failed `WhatsAppMessage`, failed `InvoiceReminder`, failed `DataPipelineRun`, and operational journal rows, When admin requests `GET /api/admin/operations/issues` with `source`, `status`, `severity`, `from`, `to`, `correlation_id`, `page`, and `limit` filters, Then the response is `{status: "success", data: [...], meta: {page,limit,total,has_more}}`; each issue contains `id`, `source`, `reference`, `status`, `severity`, `attempts`, `occurred_at`, `error_class`, `correlation_id`, and `next_action`.
   Severity mapping is `critical` for failed pipeline, `warning` for failed WhatsApp/reminder, and `info` for resolved operational events. Results are chronological for a correlation ID and bounded to limit 1–100.
   Given `GET /api/admin/operations/issues/{id}` is requested, Then the same safe fields plus a redacted detail are returned; unknown IDs return 404; the source rows remain unchanged.
   Given issue data contains secrets, When list/detail is returned, Then secrets, tokens, passwords, phone numbers, payment credentials, and raw provider payloads are absent.
   Test doubles: real database rows; no source mutation and no retry transport.
   Expected RED: list/detail routes, request validation, normalization, severity mapping, and correlation lookup do not exist.
6. Run test — verify FAIL:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php --filter=issues
   ```
   Expected failure: route/service/request absent.
7. Implement `OperationalIssueService`, request validation, controller, and routes. Register:
   - `GET /api/admin/operations/readiness`
   - `GET /api/admin/operations/issues`
   - `GET /api/admin/operations/issues/{id}`
   Read existing `WhatsAppMessage`, `InvoiceReminder`, `DataPipelineRun`, and `OperationalEvent` rows; normalize to the exact safe schema without changing those models or retry state. Validate allowlisted filters (`source`, `status`, `severity`, ISO dates, `correlation_id`, `page`, `limit`), apply severity mapping, stable ordering, and bounded pagination. Gate all three routes with HTTP 503/code `pre_pilot_disabled` when disabled.
8. Run all task tests — verify PASS:
   ```bash
   cd apps/api && php artisan test tests/Feature/OperationalDiagnosticsTest.php
   ```
9. Refactor while green:
   - Keep source-specific mapping in `OperationalIssueService`.
   - Keep admin authorization at the controller boundary using existing service/convention.
   - Ensure response never includes request body, auth header, token, password, phone, payment credential, or raw provider payload.
10. Commit:
   ```bash
   git add apps/api/app/Services/OperationalReadinessService.php apps/api/app/Services/OperationalIssueService.php apps/api/app/Http/Controllers/OperationalReadinessController.php apps/api/app/Http/Requests/ListOperationalIssuesRequest.php apps/api/routes/api.php apps/api/tests/Feature/OperationalDiagnosticsTest.php
   git commit -m "feat(pre-pilot): expose readiness and issue diagnostics"
   ```

## REFERENCES LOADED

- T1 compatibility baseline and T2/T3 regression/control tests.
- `apps/api/app/Http/Controllers/DataPipelineController.php` — current admin status and authorization convention.
- `apps/api/app/Models/DataPipelineRun.php`, `InvoiceReminder.php`, `WhatsAppMessage.php` — existing failure/status fields.
- `apps/api/app/Console/Kernel.php` — scheduler timezone and overlap convention.
- `apps/api/tests/Feature/OperationalReadinessTest.php`, `WhatsAppTest.php`, reminder retry tests — source behavior and fixtures.

## WHY THIS APPROACH

Complexity: standard. The API aggregates several existing failure stores but must keep them read-only and avoid recreating retry or authorization logic.

## SANDWICH CONTEXT

[CRITICAL: Readiness and issue APIs are diagnostics only; they cannot mutate or retry source workflows.]
You are implementing pre-pilot operational diagnostics.
Spec: `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
Design decision: compatibility-first pre-pilot hardening.
Files in scope: listed readiness/issue services, controller, request, routes, and test only.
Available after: T2 and T3.
Architecture rule: use existing admin authorization, existing model state, allowlisted fields, and redaction; no new retry or auth system.
[RESTATE: Readiness and issue APIs are diagnostics only; they cannot mutate or retry source workflows.]

## DELIVERABLE

Given readiness dependencies/configuration, When admin requests readiness, Then a deterministic status and remediation evidence are returned without mutation.
Given existing failures and journal events, When admin filters issues or correlation ID, Then safe chronological diagnostics are returned without source mutation.
[must-not] Given an issue list/detail request, When it is processed, Then no retry, status update, or financial mutation occurs.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR

Must-have:
- Admin-only access using existing role.
- Read-only, bounded, redacted queries.
- Deterministic status/issue response.

Must-not-have:
- No new role or authorization bypass.
- No retry triggered by GET.
- No source row updates.

## STOP CONDITIONS

Done when: diagnostics tests and T2/T3 compatibility tests pass.
Escalate when: a required readiness signal cannot be observed without mutating or invoking a source workflow.
