# Pre-Pilot Gate Checklist

**Purpose:** The gate recommends `READY_FOR_PILOT` only when all checks pass. Any failure means `BLOCKED` and no pilot activation is permitted.

**Spec:** `docs/pocket/spec/2026-09-14-business-validation-production-pilot/order-to-payment-pilot.md`
**Runbook:** `docs/pilot/pre-pilot-runbook.md`

---

## 1. Evidence — Required Commands (all must PASS)

| # | Command | Coverage |
|---|---------|----------|
| 1 | `cd apps/api && php artisan test tests/Feature/PrePilotCompatibilityTest.php tests/Feature/PrePilotConcurrencyCompatibilityTest.php tests/Feature/PrePilotControlsTest.php tests/Feature/OperationalDiagnosticsTest.php` | T2–T4 compatibility, controls, diagnostics |
| 2 | `cd apps/api && php artisan test tests/Feature/OrderTest.php tests/Feature/InvoiceTest.php tests/Feature/PaymentTest.php tests/Feature/DeliveryTest.php tests/Feature/WhatsAppTest.php` | Existing core — only 2 pre-existing failures allowed (see baseline) |
| 3 | `cd apps/web && npm test -- --runInBand src/app/operations/page.test.tsx && npm run lint` | Web `/operations` page typecheck and behavior |
| 4 | `php artisan migrate:status` shows both `2026_09_15_000001_create_operational_events_table` and `2026_09_15_000002_add_idempotency_payload_hash_to_orders_table` as ran | Migrations applied |
| 5 | `php artisan route:list \| grep operations` shows `/api/admin/operations/readiness`, `/issues`, `/issues/{id}` | Routes registered |

> Only the 2 documented pre-existing failures in OrderTest are allowed (`test_cannot_approve_already_confirmed_order` and `test_concurrent_admin_approvals_append_one_confirmed_history`). Any other failure blocks.

---

## 2. Safe Controls Verification

- [ ] `config/pre_pilot.php` default is `enabled=false` and `kill_switch=false`.
- [ ] With `enabled=false` or `kill_switch=true`, all `/api/admin/operations/*` return HTTP 503 `{"status":"error","code":"pre_pilot_disabled"}`.
- [ ] Existing transaction endpoints (orders, payments, delivery, WhatsApp) remain available when `pre_pilot` is disabled — response is 200/201 as before.
- [ ] Migration `create_operational_events_table` rollback is additive and reversible.
- [ ] Migration `add_idempotency_payload_hash_to_orders_table` rollback is nullable and does not backfill or affect existing orders.

---

## 3. Diagnostics Verification

- [ ] Readiness endpoint returns `{status:"success", data:{status:[ready|warning|blocked], checks:[...], evaluated_at, correlation_id}}`.
- [ ] Readiness checks include database, flag/kill, scheduler, WhatsApp, data pipeline with correct status mapping.
- [ ] Issue list supports filters `source, status, severity, from, to, correlation_id, page, limit(1-100)` and returns `{status:"success", data:[...], meta:{page,limit,total,has_more}}`.
- [ ] Issue detail at `/issues/{id}` returns safe redacted detail; unknown IDs return 404.
- [ ] Severity mapping: pipeline=critical, WhatsApp/reminder=warning, resolved operational event=info.
- [ ] Correlation IDs are bounded to `^[A-Za-z0-9._:-]{1,100}$`, oversized/unsafe → UUID.

---

## 4. Redaction & Source-Row Immutability

- [ ] Issue API responses do not expose `phone, token, password, credential, secret, bearer, provider_message_id, raw, payload, body, card, payment` or auth headers.
- [ ] Diagnostics endpoints do not mutate order/invoice/payment/delivery/WhatsApp/pipeline rows.
- [ ] Idempotency conflict on same-key/different-payload returns HTTP 422 without mutating the first order.

---

## 5. Web Surface

- [ ] `/operations` page renders readiness checks, issue list, filter controls, and safe detail when APIs succeed.
- [ ] `/operations` page renders a disabled/activation-required state when API returns 503 `pre_pilot_disabled`.
- [ ] Filters serialize only allowlisted values; failed refresh preserves last successful list with an alert.
- [ ] `Sidebar.tsx` exposes `/operations` only for role `admin` (via `adminOnly:true`); non-admin roles see no new nav entry.

---

## 6. Scope — What is NOT Activated

- [ ] No partner or territory population occurred.
- [ ] No pilot tagging or 100-order KPI is claimed.
- [ ] No financial ledger mutation (no balance/credit adjustment beyond existing tests).
- [ ] No new roles other than existing `admin`, `outlet`, `sales`, `finance`, `driver`.
- [ ] No retry transport invoked from GET.

---

## 7. Result

| Field | Value |
|-------|-------|
| Verdict | `READY_FOR_PILOT` / `BLOCKED` |
| Evaluated at | `YYYY-MM-DD` |
| Evaluated by | Name / Role |
| Evidence SHA | `git rev-parse HEAD` |

### Sign-off

- **Platform admin:** _________________________________ Date: ___________
- **Product owner:** _________________________________ Date: ___________

### Rollback Reference

- See `docs/pilot/pre-pilot-runbook.md` §6 for rollback/kill-switch steps.
- Record any rollback execution date and reason in the log below.

### Notes

| Date | Observer | Note |
|------|----------|------|
|      |          |      |
