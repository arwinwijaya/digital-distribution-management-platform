# Task T6 — Implement idempotent WhatsApp invoice reminders and scheduler

**Phase:** 3
**Depends:** T4
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 6: Implement idempotent WhatsApp invoice reminders and scheduler [depends: T4] [test-risk]

## OBJECTIVE
Create H-1 and first-overdue reminder processing in Asia/Jakarta with unique invoice/event/date identities, three bounded retries at 1/5/15 minutes, provider idempotency reuse, failure audit, and finance-visible bounded reminder history.

Files:
- Create `apps/api/app/Services/InvoiceReminderService.php`, `apps/api/app/Http/Controllers/InvoiceReminderController.php`, and `apps/api/app/Console/Commands/ProcessInvoiceReminders.php`.
- Modify `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/app/Console/Kernel.php`, `apps/api/config/whatsapp.php`, and `apps/api/routes/api.php`.
- Test `apps/api/tests/Feature/InvoiceReminderTest.php` and preserve relevant `WhatsAppPostgresConcurrencyTest.php` coverage.

Steps:
Timing test contract: every H-1/overdue/retry cycle freezes Carbon at an explicit instant in `Asia/Jakarta`, asserts the exact due-boundary inclusivity/exclusivity, and restores the test clock after the scenario.
1. RED/GREEN cycle — H-1 reminder once:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration with external-service boundary. Test intent: Given an eligible unpaid invoice due tomorrow in Asia/Jakarta, when the Artisan reminder command runs twice, then one H-1 reminder identity/provider call exists. Exercise through `php artisan invoices:reminders`; fake only `WhatsAppClient`. Expected RED: no reminder service/command exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=h_minus_one_reminder_is_sent_once --testdox`.
   Implement candidate selection and unique event identity, then run the same command and verify PASS.
2. RED/GREEN cycle — Missed H-1 recovery:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given H-1 was missed and the invoice remains eligible, when the next command runs, then exactly one H-1 reminder is created. Exercise through Artisan with Carbon in Asia/Jakarta. Test doubles: fake `WhatsAppClient` only. Expected RED: no recovery window exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=missed_h_minus_one_is_recovered --testdox`.
   Implement recovery predicate, then run the same command and verify PASS.
3. RED/GREEN cycle — First overdue reminder once:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given an unpaid invoice crosses its due boundary, when the command runs repeatedly, then one overdue reminder exists and later runs do not create another. Test doubles: fake `WhatsAppClient` only. Expected RED: no overdue event identity exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=overdue_reminder_is_sent_once --testdox`.
   Implement first-overdue selection and uniqueness, then run the same command and verify PASS.
4. RED/GREEN cycle — Paid invoice suppression:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given an invoice becomes paid before processing, when the command runs, then no reminder is created/sent. Test doubles: fake `WhatsAppClient` only. Expected RED: paid-state suppression is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=paid_invoice_suppresses_reminder --testdox`.
   Implement the paid eligibility check, then run the same command and verify PASS.
5. RED/GREEN cycle — Cancelled invoice suppression:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given an invoice is cancelled before processing, when the command runs, then no reminder is created/sent. Test doubles: fake `WhatsAppClient` only. Expected RED: cancelled-state suppression is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=cancelled_invoice_suppresses_reminder --testdox`.
   Implement the cancelled eligibility check, then run the same command and verify PASS.
6. RED/GREEN cycle — Provider retry schedule and identity:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration with external-service boundary. Test intent: Given provider errors, when retry processing occurs, then attempts use one reminder/provider idempotency identity and next attempts are scheduled at 1, 5, and 15 minutes. Test doubles: fake `WhatsAppClient`; do not mock reminder/invoice/DB. Expected RED: no invoice retry state exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=provider_failure_schedules_idempotent_backoff --testdox`.
   Implement attempt/backoff identity, then run the same command and verify PASS.
7. RED/GREEN cycle — Final failure audit and invoice immutability:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given all bounded attempts fail, when final processing completes, then reminder is failed/audited and invoice status/balance remain unchanged. Test doubles: fake `WhatsAppClient` that always fails. Expected RED: no final audit/immutability assertion exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=final_reminder_failure_is_audited_without_invoice_mutation --testdox`.
   Implement final failure state, then run the same command and verify PASS.
8. RED/GREEN cycle — Valid bounded reminder history:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given reminders for two outlets, when authorized finance/admin requests a valid page/limit, then rows are scoped/stable/bounded with metadata. Test doubles: none. Expected RED: no history endpoint exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=reminder_history_is_bounded_and_scoped --testdox`.
   Implement the bounded query, then run the same command and verify PASS.
9. RED/GREEN cycle — Invalid reminder pagination:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given invalid page or limit, when reminder history is requested, then 422 is returned before an unbounded query. Test doubles: none. Expected RED: no validation exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=invalid_reminder_pagination_is_rejected --testdox`.
   Implement validation, then run the same command and verify PASS.
10. RED/GREEN cycle — Scheduler registration and timezone:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration. Test intent: Given the Laravel scheduler, when registered events are inspected, then invoice reminder processing is scheduled with `Asia/Jakarta` timezone at a cadence that can process minute-level retries. Exercise through the real scheduler event registry, not a private service method. Test doubles: none; freeze Carbon only in date-boundary tests. Expected RED: Kernel has no reminder event.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=scheduler_registers_invoice_reminders_in_jakarta --testdox`.
   Implement Kernel schedule registration, then run the same command and verify PASS.
11. RED/GREEN cycle — Closed invoice before retry:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php`. Level: integration with external-service boundary. Test intent: Given a failed pending reminder, when the invoice is paid or cancelled before its retry time, then the next processing run makes no provider call and does not advance retry mutation. Test doubles: fake `WhatsAppClient` with call count; do not mock invoice/reminder/database. Expected RED: retry path does not re-check current invoice state.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --filter=closed_invoice_suppresses_pending_retry --testdox`.
   Implement retry-time state check, then run the same command and verify PASS.
12. RED/GREEN cycle — Concurrent reminder workers:
   Test file: `apps/api/tests/Feature/InvoiceReminderTest.php` and `apps/api/tests/Feature/WhatsAppPostgresConcurrencyTest.php`. Level: integration (with PostgreSQL concurrency scenario). Test intent: Given concurrent reminder workers for one invoice/event/date, when both claim/send, then one logical reminder and one provider call exist. Test doubles: fake WhatsApp client; do not mock claim/transaction. Expected RED: existing WhatsApp concurrency test covers order notifications only.
   Run RED: `cd apps/api && DB_CONNECTION=pgsql php artisan test tests/Feature/WhatsAppPostgresConcurrencyTest.php tests/Feature/InvoiceReminderTest.php --filter='concurrent_invoice_reminder_workers|invoice_reminder_is_sent_once' --testdox`.
   Implement reminder-specific claim/unique-key integration, then run the same PostgreSQL command and verify PASS.
13. Refactor while green: keep date logic in `InvoiceReminderService`, transport in `WhatsAppOutboundService`, and settings in `config/whatsapp.php`; run `cd apps/api && php artisan test tests/Feature/InvoiceReminderTest.php --testdox` and, when PostgreSQL is available, `cd apps/api && php artisan test tests/Feature/WhatsAppPostgresConcurrencyTest.php --testdox`.
14. Commit: `git add apps/api/app/Services/InvoiceReminderService.php apps/api/app/Http/Controllers/InvoiceReminderController.php apps/api/app/Console/Commands/ProcessInvoiceReminders.php apps/api/app/Services/WhatsAppOutboundService.php apps/api/app/Console/Kernel.php apps/api/config/whatsapp.php apps/api/routes/api.php apps/api/tests/Feature/InvoiceReminderTest.php apps/api/tests/Feature/WhatsAppPostgresConcurrencyTest.php && git commit -m "feat(reminders): add bounded invoice WhatsApp workflow"`.

## REFERENCES LOADED
- Spec reminder timing, idempotency, retry, audit, timezone, and suppression GWT scenarios.
- `apps/api/app/Services/WhatsAppOutboundService.php`, `WhatsAppMessage.php`, `WhatsAppTest.php`, and `WhatsAppPostgresConcurrencyTest.php` — existing provider idempotency, lease, and failure isolation.
- `apps/api/app/Console/Kernel.php` — scheduling seam.

## WHY THIS APPROACH
Complexity: deep and `[test-risk]`. It crosses invoice state, scheduler time, persistence uniqueness, network failure, and existing outbound leases.

## SANDWICH CONTEXT
[CRITICAL: Reminder failure must never mutate invoice status/balance, and every retry must reuse one logical/provider idempotency identity.]
You are implementing invoice reminders for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — reuse existing WhatsApp outbound transport.
Files in scope: the files listed for Task 6.
Available after: T4 invoice payment state synchronization.
Architecture rule: use Asia/Jakarta boundaries, additive reminder audit records, existing WhatsApp client/webhook contract, bounded retries, and no new queue/provider dependency.
[RESTATE: Reminder failure must never mutate invoice status/balance, and every retry must reuse one logical/provider idempotency identity.]

## DELIVERABLE
- Given an unpaid invoice due tomorrow in Asia/Jakarta, when the command runs twice, then one H-1 reminder exists/sends.
- Given H-1 was missed, when the next eligible run occurs, then the reminder is recovered once.
- Given an unpaid invoice crosses due date, when the command runs repeatedly, then one overdue reminder exists.
- Given a provider failure, then attempts are bounded at three retries with 1/5/15-minute backoff, final failure is audited, and invoice state is unchanged.
- Given paid/cancelled invoice, when the scheduler runs, then no reminder is created.
- Given reminder records for multiple outlets, when history is requested, then rows are bounded, stable, validated, and outlet-scoped.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: Asia/Jakarta date semantics, unique invoice/event/date key, same provider idempotency key across retry, final failure audit, and bounded/stable reminder list.
Must-not-have: no arbitrary reminder channel, no automated collections, no invoice status mutation from provider failure, no replacement of WhatsApp webhook contract.
Open question risks: exact reminder body text is implementation detail; it must identify the outlet/invoice and event without exposing unrelated outlet data.
Rollback note: disable scheduler before reverting if provider calls are problematic; retain reminder audit records.

## STOP CONDITIONS
Escalate if provider/client idempotency cannot be preserved or if a retry could send after payment/cancellation due to an unprotected state check.
