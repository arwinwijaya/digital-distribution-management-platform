# Task T4 — Backend — OrderController additive filters

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 4: Backend — OrderController additive filters [prereq]

## OBJECTIVE
Add additive query parameters to `OrderController::index()`: `outlet_id` (positive int), `status` (csv of the four canonical statuses), `start`/`end` (`YYYY-MM-DD`, `start<=end`, range ≤90 days). Validate before query construction; return a 422 envelope on failure; never broaden the query when validation fails; keep existing behavior when no new params are passed.

Steps:
1. Write failing test for: additive filtering
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given orders across outlets/statuses/dates / When `GET /api/admin/orders?outlet_id=12&status=New,Confirmed&start=2026-09-19&end=2026-09-25` / Then only matching orders are returned and pagination/sort/`meta.total` reflect the filtered set.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: new params ignored
2. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderQueryTest`
3. Implement additive scoping (reuse `App\Support\ListQuery` for scalar parsing) → verify PASS → refactor → commit `feat(controller): order additive filters`
4. Write failing test for: 422 validation envelope + unknown query key rejection
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given `outlet_id=abc` / When the endpoint is called / Then 422 with `errors.outlet_id`; Given `status=Unknown` / Then 422 with `errors.status`; Given `start>end` or a range >90 days or a malformed date / Then 422 with a field error; Given ANY unknown additional query key (e.g. `?foo=1` or `?page_size=999` not in the allowlist) / Then 422 identifying the offending key and NO order rows are returned; and in every failure no broadened query executes.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: invalid values and unknown keys silently ignored
5. Run test — verify FAIL: `cd apps/api && php artisan test --filter=OrderQueryTest`
6. Implement validation-before-query (including rejection of every query key outside the `outlet_id`/`status`/`start`/`end` allowlist) → verify PASS → refactor → commit `feat(controller): order filter validation with 422 envelope`
7. Write failing test for: unchanged default behavior
   Test file: `apps/api/tests/Feature/OrderQueryTest.php`
   Level: integration
   Test intent: Given no new parameters / When the endpoint is called / Then results, pagination, and sort are byte-identical to the pre-change behavior.
   Exercise through: HTTP endpoint
   Test doubles: none
   Expected RED: n/a — guards regression, expect PASS after step 3; keep as regression lock
8. Run test — verify PASS: `cd apps/api && php artisan test --filter=OrderQueryTest`

> Test **intent** only — never test source code.

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md` — rule: Additive order-list filter contract (allowlisted keys; unknown keys → 422; validation before query construction); Story: Order navigation preserves context
[CRITICAL: Without this section, packet is incomplete]

## WHY THIS APPROACH
Complexity: standard
Justification: validation-first with backward-compatible pagination and an explicit error contract.

## SANDWICH CONTEXT
[CRITICAL: Validation runs BEFORE query construction — on failure no broadened/unfiltered query executes]
You are implementing additive filters for the admin order list.
Spec: docs/pocket/spec/2026-09-26-outlet-request-map-dashboard/spec.md
Design decision: Option A
Files in scope: `apps/api/app/Http/Controllers/OrderController.php`, `apps/api/tests/Feature/OrderQueryTest.php`
Available after: none (prereq)
Architecture rule: scalar-safe parsing (`ListQuery`), allowlist validation, 422 envelope `{status,message,errors}`, existing behavior unchanged when no new params
[RESTATE: Validation runs BEFORE query construction]

## DELIVERABLE
Given valid `outlet_id`/`status`/`start`/`end`, When `GET /api/admin/orders`, Then filtered results with preserved pagination/sort/meta
Given `outlet_id=abc` or `status=Unknown` or `start>end` or range>90d or malformed date, When called, Then 422 `{status,message,errors:{field}}` and no broadened query
Given any query key outside the `outlet_id`/`status`/`start`/`end` allowlist, When called, Then 422 identifying the unknown key and no order rows are returned
Given no new parameters, When called, Then identical pre-change behavior
[must-not] Given an invalid filter, When called, Then the endpoint must NOT return all orders

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - `outlet_id` positive integer
  - `status` csv normalized to unique values, each in the four canonical statuses
  - `start`/`end` valid `YYYY-MM-DD`, `start<=end`, range ≤90 days
  - Every query key outside the `outlet_id`/`status`/`start`/`end` allowlist → 422
  - 422 with field-level errors; never a 500
Must-not-have:
  - Broadened query on validation failure
  - New RBAC/menu keys
Open question risks:
  - Existing `SORT_ALLOWLIST`/cursor pagination interacts with filters → report NEEDS_CONTEXT if sort breaks
Rollback note:
  - Remove the additive filter handling; existing unfiltered listing is unchanged
Red flags:
  - Validation after query construction → STOP
  - Touching protected services → STOP

## STOP CONDITIONS
Done when: all filter combos pass, 422 contract correct, default behavior unchanged
Uncertain when: cursor pagination cannot compose with the filters
Escalate when: filtered queries break the existing pagination contract
