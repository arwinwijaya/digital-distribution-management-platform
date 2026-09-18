# Pre-Pilot Runbook

> **Status:** Documentation only — this does NOT authorize a production pilot.
> Any future pilot activation requires a separate spec with explicit partner, date, and KPI decisions.

---

## 1. Safe Defaults & Enable/Disable

| Setting | File | Default | Effect |
|---------|------|---------|--------|
| `pre_pilot.enabled` | `config/pre_pilot.php` | `false` | When false, all `/api/admin/operations/*` routes return HTTP 503 `{"status":"error","code":"pre_pilot_disabled"}` |
| `pre_pilot.kill_switch` | `config/pre_pilot.php` | `false` | When true, same 503 behavior; existing transactions are unaffected |

**To enable the pre-pilot surface:**
```bash
# Set in .env or config
PRE_PILOT_ENABLED=true
PRE_PILOT_KILL_SWITCH=false
php artisan config:cache
```

**To disable (kill switch):**
```bash
# Set in .env or config
PRE_PILOT_KILL_SWITCH=true
php artisan config:cache
```

**Important:** The kill switch does NOT roll back, cancel, or mutate any existing orders, payments, invoices, or deliveries. It only disables the diagnostics surface.

---

## 2. Readiness Review

**Endpoint:** `GET /api/admin/operations/readiness` (admin-only)

**Checks:**
| Check | Status values | Remediation |
|-------|---------------|-------------|
| `database` | ok / fail | Verify DB connectivity |
| `pre_pilot_flag` | ok / warn | Ensure `pre_pilot.enabled=true` and `kill_switch=false` |
| `scheduler` | ok / warn | Confirm `Asia/Jakarta` timezone with `withoutOverlapping` |
| `whatsapp` | ok / warn | Ensure `whatsapp.enabled=true` with valid config when WhatsApp is active |
| `data_pipeline` | ready / warn / blocked | Run `php artisan data:pipeline` at least once before pilot |

**Response shape:**
```json
{
  "status": "success",
  "data": {
    "status": "ready|warning|blocked",
    "checks": [{"name":"...","status":"...","evidence":"...","remediation":"..."}],
    "evaluated_at": "2026-09-15T...",
    "correlation_id": "..."
  }
}
```

**Remediation ownership:** Platform admin is responsible for resolving warnings; product owner signs off on blocked items before any pilot handoff.

---

## 3. Issue Inbox

**Endpoint:** `GET /api/admin/operations/issues` (admin-only, read-only)

**Filters:** `source` (whatsapp/reminder/pipeline/operational_event), `status`, `severity`, `from`, `to`, `correlation_id`, `page`, `limit` (1–100)

**Severity mapping:**
| Source | Severity | Meaning |
|--------|----------|---------|
| `pipeline` (failed) | `critical` | Data pipeline failure requires immediate attention |
| `whatsapp` (failed) | `warning` | WhatsApp delivery failed; retry via existing endpoint |
| `reminder` (failed) | `warning` | Invoice reminder failed; inspect manually |
| `operational_event` (resolved) | `info` | Informational; no action needed |

**Retry actions (existing endpoints only, NOT triggered by GET):**
- WhatsApp retry: `POST /api/whatsapp/messages/{id}/retry`
- Pipeline rerun: `php artisan data:pipeline`

**Do NOT retry from GET.** The issue list is purely diagnostic.

---

## 4. Correlation ID Investigation

Correlation IDs are attached to every request via the `X-Correlation-ID` header (bounded to `[A-Za-z0-9._:-]`, max 100 chars; oversized/unsafe values are replaced with a UUID).

**Investigation flow:**
1. Obtain the correlation ID from the failing request's response header or logs.
2. Query issues: `GET /api/admin/operations/issues?correlation_id=<ID>`
3. All journal events for that correlation ID are returned chronologically.
4. **Redaction rules:** Raw payloads, tokens, phone numbers, passwords, payment credentials, and provider message IDs are never exposed in the API response.

---

## 5. Compatibility Regression Commands

```bash
# API compatibility (idempotency, concurrency, ordering)
cd apps/api
php artisan test tests/Feature/PrePilotCompatibilityTest.php
php artisan test tests/Feature/Concurrency/PrePilotConcurrencyCompatibilityTest.php

# Pre-pilot controls (correlation, journal, flag)
php artisan test tests/Feature/PrePilotControlsTest.php

# Operational diagnostics (readiness, issues)
php artisan test tests/Feature/OperationalDiagnosticsTest.php

# Existing core suites (must pass with 0 failures)
php artisan test tests/Feature/OrderTest.php tests/Feature/InvoiceTest.php tests/Feature/PaymentTest.php
php artisan test tests/Feature/DeliveryTest.php tests/Feature/WhatsAppTest.php

# Web surface
cd apps/web
npm test -- --runInBand src/app/operations/page.test.tsx
npm run lint
```

**Resolved:** the two `OrderTest` re-approval assertions (`test_cannot_approve_already_confirmed_order`, `test_concurrent_admin_approvals_append_one_confirmed_history`) were stale `422` expectations against the idempotent `200` contract. They have been corrected (see `test_reapproving_already_confirmed_order_is_idempotent`) and documented in `docs/pilot/pre-pilot-compatibility-baseline.md`. All commands above must now pass with 0 failures.

---

## 6. Rollback / Kill-Switch Procedure

1. **Immediate (no data loss):** Set `PRE_PILOT_KILL_SWITCH=true` and `php artisan config:cache`.
   - All `/api/admin/operations/*` routes return 503.
   - Existing order/payment/invoice/delivery/WhatsApp workflows continue normally.
   - No source rows are mutated.

2. **Config rollback:** Revert `config/pre_pilot.php` to defaults (enabled=false, kill_switch=false).

3. **Migration rollback (if needed):**
   ```bash
   php artisan migrate:rollback --path=database/migrations/2026_09_15_000001_create_operational_events_table.php
   php artisan migrate:rollback --path=database/migrations/2026_09_15_000002_add_idempotency_payload_hash_to_orders_table.php
   ```
   The idempotency payload hash is nullable; rolling back the column does not affect existing orders.

4. **Code rollback:** `git revert` to any prior commit; no production-pilot code exists in the codebase.

---

## 7. Handoff Requirements for Future Production Pilot

A separate spec must address these **unresolved decisions** before any production pilot is authorized:

| Decision | Current status |
|----------|---------------|
| Partner selection and onboarding | Not decided |
| Territory/scope definition | Not decided |
| Pilot date and duration | Not decided |
| 100-order KPI claim and measurement | Not decided (no KPI is claimed now) |
| Financial ledger mutations | Not in scope |
| Production-grade retry/backoff | Not in scope |
| Business validation criteria | Not decided |

**This runbook does NOT authorize any of the above. It only documents pre-pilot readiness.**
