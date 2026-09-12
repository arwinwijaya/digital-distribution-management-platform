# Task T7 — Add finance invoice/payment metrics API

**Phase:** 3
**Depends:** T4, T6
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: Add finance invoice/payment metrics API [depends: T4, T6]

## OBJECTIVE
Expose the six baseline invoice/payment metric groups with a default 30-day event window, all-active current-state metrics, zero-safe denominators, role/outlet scoping, and stable date validation without changing the existing dashboard API contract.

Files:
- Create `apps/api/app/Services/InvoiceMetricsService.php` and `apps/api/app/Http/Controllers/FinanceMetricsController.php`.
- Modify `apps/api/routes/api.php`; modify `apps/api/app/Services/AnalyticsService.php` only when extracting a non-breaking shared aggregate helper is necessary.
- Test `apps/api/tests/Feature/InvoiceMetricsTest.php`.

Steps:
Timing test contract: freeze Carbon at a known instant in `Asia/Jakarta` for the default-window and date-boundary cycles; assert exact 30-day inclusivity/exclusivity, then clear the test clock.
1. RED/GREEN cycle — Default window/current-state separation:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given active invoices inside/outside 30 days, when metrics omit dates, then event groups use last 30 days and active outstanding/status includes all active invoices. Exercise through `GET /api/finance/metrics`. Test doubles: no query/service mocks; real data and Carbon. Expected RED: endpoint absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_use_default_window_and_current_state --testdox`.
   Implement separate predicates, then run the same command and verify PASS.
2. RED/GREEN cycle — Issued invoice metric:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given invoices issued inside/outside the event window, when metrics are requested, then issued-invoice count uses issue date and the default/custom window. Test doubles: none. Expected RED: group absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_issued_invoices --testdox`.
   Implement the issue-date aggregate, then run the same command and verify PASS.
3. RED/GREEN cycle — Outstanding balance metric:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given active invoices including one issued before the window, when metrics are requested, then current outstanding balance includes all active unpaid/partial balances and excludes paid/cancelled. Test doubles: none. Expected RED: no invoice aggregate.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_current_outstanding_balance --testdox`.
   Implement current-state aggregate, then run the same command and verify PASS.
4. RED/GREEN cycle — Overdue rate:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given active overdue/non-overdue/paid/cancelled invoices, when metrics are requested, then overdue rate follows the defined active denominator and excludes cancelled invoices. Test doubles: none. Expected RED: no overdue metric.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_overdue_rate --testdox`.
   Implement the due-date/status aggregate, then run the same command and verify PASS.
5. RED/GREEN cycle — Full collection time:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given an invoice paid by multiple completed payments, when metrics are calculated, then collection time runs issue date to final payment date and partial-only invoices are excluded. Test doubles: none. Expected RED: no payment-date aggregate.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_measure_full_collection_time --testdox`.
   Implement final completed-payment date aggregation, then run the same command and verify PASS.
6. RED/GREEN cycle — Payment-status breakdown:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given invoices in each status, when metrics are requested, then payment-status breakdown counts each status correctly. Test doubles: none. Expected RED: no status breakdown.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_payment_status_breakdown --testdox`.
   Implement status aggregation, then run the same command and verify PASS.
7. RED/GREEN cycle — Reminder success/failure:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given reminder records inside the window with sent and failed outcomes, when metrics are requested, then success/failure counts are returned. Test doubles: none. Expected RED: reminders are not included.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_return_reminder_success_and_failure --testdox`.
   Implement reminder aggregates, then run the same command and verify PASS.
8. RED/GREEN cycle — Valid custom window:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given a valid custom window, when requested, then event metrics use it. Test doubles: none. Expected RED: no date contract exists.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_accept_valid_custom_window --testdox`.
   Implement valid date parsing, then run the same command and verify PASS.
9. RED/GREEN cycle — Invalid custom window:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given malformed, out-of-order, or over-limit dates, when requested, then 422 is returned before unbounded aggregation. Test doubles: none. Expected RED: invalid date behavior is absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_reject_invalid_custom_window --testdox`.
   Implement validation/range bounds, then run the same command and verify PASS.
10. RED/GREEN cycle — Zero denominators:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given no qualifying denominator, when metrics are requested, then each metric returns numeric zero and the response remains valid. Test doubles: none. Expected RED: endpoint absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_are_zero_safe --testdox`.
   Implement zero-safe arithmetic, then run the same command and verify PASS.
11. RED/GREEN cycle — Authorization and outlet isolation:
   Test file: `apps/api/tests/Feature/InvoiceMetricsTest.php`. Level: integration. Test intent: Given finance/admin and outlet users, when metrics are requested, then finance/admin are allowed and outlet results exclude other outlets. Test doubles: none. Expected RED: route authorization absent.
   Run RED: `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php --filter=metrics_are_authorized_and_outlet_scoped --testdox`.
   Implement current-role scope checks, then run the same command and verify PASS.
12. Refactor while green: reuse only named aggregate helpers that preserve `analytics/dashboard`; run `cd apps/api && php artisan test tests/Feature/InvoiceMetricsTest.php tests/Feature/AnalyticsTest.php --testdox`.
13. Commit: `git add apps/api/app/Services/InvoiceMetricsService.php apps/api/app/Http/Controllers/FinanceMetricsController.php apps/api/routes/api.php apps/api/app/Services/AnalyticsService.php apps/api/tests/Feature/InvoiceMetricsTest.php && git commit -m "feat(analytics): add finance invoice metrics"`.

## REFERENCES LOADED
- Spec metrics definitions and zero-denominator GWT scenarios.
- `apps/api/app/Services/AnalyticsService.php`, `AnalyticsController.php`, `AnalyticsTest.php` — bounded aggregate style and existing contract.
- Invoice/payment/reminder models and services from T1/T3/T4/T6.

## WHY THIS APPROACH
Complexity: standard. It adds a separate contract to protect the existing executive dashboard while sharing its database-aggregate discipline.

## SANDWICH CONTEXT
[CRITICAL: Current-state metrics must include all active invoices while event metrics use the defined date window; never collapse both semantics into one date predicate.]
You are implementing finance invoice/payment metrics for Operational Readiness Slice 1.
Spec: `docs/pocket/spec/2026-09-10-operational-readiness/operational-readiness-spec.md`
Design decision: Option A — extend existing analytics patterns.
Files in scope: the files listed for Task 7.
Available after: T4 payment state and T6 reminder outcomes.
Architecture rule: use bounded deterministic database aggregates and preserve the existing analytics dashboard response contract.
[RESTATE: Current-state metrics must include all active invoices while event metrics use the defined date window; never collapse both semantics into one date predicate.]

## DELIVERABLE
- Given invoices inside and outside 30 days, when metrics use defaults, then event groups use the last 30 days and active outstanding/status groups include all active invoices.
- Given an invoice paid through multiple records, then collection time runs from issue date to final payment date and partial-only invoices are excluded.
- Given no qualifying denominator, then the metric returns `0` and the API remains valid.
- Given finance requests outlet-scoped metrics, then only authorized outlet data is aggregated; unrelated admin/finance routes remain protected.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have: all six groups, explicit date semantics, zero-safe arithmetic, bounded query shape, default/custom window validation, and role/outlet isolation.
Must-not-have: no geographic/supplier BI, no changes to legacy analytics response fields, no unbounded table hydration.
Open question risks: cancelled overdue denominator follows the approved assumption; report if product owner changes it.
Rollback note: remove only the new route/service; preserve existing dashboard analytics.

## STOP CONDITIONS
Escalate if metric definitions require data not represented by existing invoice/payment/reminder records or if the existing dashboard contract must change.
