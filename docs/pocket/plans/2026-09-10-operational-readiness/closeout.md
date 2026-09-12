# Closeout — 2026-09-10-operational-readiness

- **Plan:** docs/pocket/plans/2026-09-10-operational-readiness
- **Type:** phased
- **Started:** 2026-09-10  ·  **Closed:** 2026-09-12
- **Baseline SHA:** 6eeb3be66d8c96b4ccbccc0ba2f39b75ecbd632b  ·  **Final SHA:** abef6c5a4a12def9277a1b4d3f0891a48ed2f05b
- **Result:** CLOSED — all phases DONE, all reviewable tasks REVIEW_PASS

## Phases

### Phase 1 — execution-plan/phase-1.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T1 | Create operational data foundation and shared domain records | c9a98b274daecbcda1e08ddd7f399f02806a5426 | REVIEW_PASS |
| T2 | Add finance role, request-time authorization, and payment terms | 4f5fdfd2563dc89f379fb9bbcd5dd0428a383c9e | REVIEW_PASS |
| T8 | Enforce delivery proof at the API boundary | e1a383e3e98fd2538fd98deefb6c25fc9a6c73f4 | REVIEW_PASS |

_SHA range: 6eeb3be66d8c96b4ccbccc0ba2f39b75ecbd632b..e1a383e3e98fd2538fd98deefb6c25fc9a6c73f4_

### Phase 2 — execution-plan/phase-2.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T3 | Implement invoice lifecycle and cancellation | 26a0dce0637f725e746d21d78faab0f54dd3e4e2 | REVIEW_PASS |
| T4 | Complete payment lifecycle and bounded payment history | efe3d7d966e02182b76808abcd81d71a659a6b59 (reviewed at 6389d26a732dcf7e8503d573d51fe576def725c7, ancestor-alias) | REVIEW_PASS |
| T5 | Add idempotent legacy invoice backfill | ecaf55a2cdc6acf705beedd43a7710f8d206f19a (reviewed at 536ffec61b10f0f28bb76bc6b5ce11d8975fad8d, ancestor-alias) | REVIEW_PASS |

_SHA range: e1a383e3e98fd2538fd98deefb6c25fc9a6c73f4..ecaf55a2cdc6acf705beedd43a7710f8d206f19a_

### Phase 3 — execution-plan/phase-3.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T6 | Implement idempotent WhatsApp invoice reminders and scheduler | a744d62f4588d526134090000e11557f7cb5d7b7 (corrected f7658c125919c31cacd568d5e49cc88894f0b895, P3-INT-001) | REVIEW_PASS |
| T7 | Add finance invoice/payment metrics API | 800a72695f6d68d02c5e0e4bdc420d0475753a8f (corrected via shared f7658c125919c31cacd568d5e49cc88894f0b895, P3-INT-001) | REVIEW_PASS |
| T9 | Add finance invoice/payment/metrics web surfaces | 3021493ea87da27792a369cc7e1d890a42479b54 (corrected 90209975fd2f050306d4963d2facb29dedf43a92, P3-INT-002; 51d836beb4ab974c487a0b78e1d02e7390fd7846, P3-INT-003) | REVIEW_PASS |
| T10 | Verify the complete operational readiness slice end to end | abef6c5a4a12def9277a1b4d3f0891a48ed2f05b (corrected b467f0a9db7b34731a5b873cb846af8a69b7fe59, P3-SPEC-001) | REVIEW_PASS |

_SHA range: ecaf55a2cdc6acf705beedd43a7710f8d206f19a..abef6c5a4a12def9277a1b4d3f0891a48ed2f05b_

Phase-level pass: phase-pass-phase-3.json PHASE_PASS_RESOLVED (cycle 2, resolved P3-INT-001, P3-INT-002, P3-INT-003, P3-SPEC-001).

## Carried Forward

- **T5** (Minor): backfill() is 56 lines (29-84) marginally over the ~50-line heuristic; method remains cohesive, so no split mandated — consider extracting per-order processing to a private method if the method grows further — apps/api/app/Services/InvoiceBackfillService.php:29
- **T5** (Minor): hasInvoiceNumberConflict() queries without row lock inside the per-row transaction; race is guarded by the invoices.invoice_number unique constraint and outer Throwable catch, but an explicit lock would make intent clearer — apps/api/app/Services/InvoiceBackfillService.php:91
- **T5** (Minor): deterministic issue/due dates use Carbon::today() directly; tests pin to today consistently and are green, but injecting a clock or Carbon::setTestNow would make date-dependent behavior more explicit — apps/api/app/Services/InvoiceBackfillService.php:93
- **T1** (strength): additive migrations provide nullable outlet terms, invoice-per-order uniqueness, reminder logical-key uniqueness, foreign keys, operational indexes, and safe down paths
- **T1** (strength): invoice, reminder, and role-audit models expose focused relationships, casts, centralized domain constants, and factories without replacing the payment ledger
- **T2** (strength): FinanceAuthorizationService centralizes request-time database role checks and requires active users
- **T2** (strength): role assignment/removal uses a transaction and row lock, audits only actual role changes, guards inactive targets, and makes repeated assignment idempotent
- **T3** (strength): approval transaction keeps row locking, invoice creation/reuse, status-history idempotency, and post-commit WhatsApp isolation
- **T4** (strength): PaymentController moneyToCents() is a clean string/cents parser with no float arithmetic
- **T4** (strength): valid-payment scope correctly filters status = completed AND amount > 0; idempotent replay returns 200 with same payment ID; conflicting identity returns 422 with no mutation
- **T5** (strength): additive-only backfill with no DELETE/TRUNCATE/destroy; command is admin-controlled Artisan only
- **T6** (strength): correction diff is surgical — 3 production hunks (SUPPRESSED constant, two terminal guards, two suppression branches) plus a focused metrics guard, no out-of-scope file changes
- **T6** (strength): test isolation is clean — InvoiceReminderSuppressionMetricsTest uses RefreshDatabase, counting WhatsAppClient wrapper, and direct InvoiceReminder seed without mocking invoice/reminder/database
- **T7** (strength): correction diff on the T7 surface is a targeted 14-line change to reminderCounts only; double guard (SENT-only status predicate plus sent_at NOT NULL) provides defense-in-depth
- **T8** (strength): server-side conditional validation in UpdateDeliveryStatusRequest.php; mutation in DeliveryController.php
- **T9** (strength): P3-INT-002 adds createPaymentIdempotencyKey as a named, testable function; P3-INT-003 replaces two UTC date helpers with one jakartaDateString(offsetDays) — genuine deduplication of UTC date logic
- **T10** (strength): correction diff is a pure addition (+273/-0, one file) — no prior assertion weakened, no scenario renamed or removed, no production/config change
- **T10** (strength): ten labeled phases decompose the long scenario into approve/deliver/second-outlet/pay/remind/metrics/bounded/scoped/isolated/zero-safe units with exact-value comments documenting expected metric arithmetic

## Skipped Tasks

_None_ — every task in all three phases was DONE with a current REVIEW_PASS verdict.
