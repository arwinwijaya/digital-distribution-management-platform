# Phase 4 — Formulation (updated after blocking clarifications)

## Decisions locked from blocking clarification round
- **Auth**: `admin` OR `platform_owner` via the EXISTING
  `FinanceAuthorizationService::isAdminOrOwner()` (includes `is_active` guard) — not raw
  `isAdmin() || isPlatformOwner()`. Widen the `AnalyticsController` gate to use this helper.
  Mark `/analytics` nav item `adminOnly` in Sidebar AND treat `platform_owner` as
  admin-equivalent so `adminOnly` items (incl. Analitik) stay visible to owners.
- **API contract**: NEW endpoint `GET /api/analytics/insight` reusing `AnalyticsService`
  aggregate helpers (single source of truth = shared code, not shared payload).
- **Outstanding in needs_attention**: point-in-time (all unpaid orders regardless of
  creation date), labeled distinctly ("total tunggakan") vs strip's windowed metric
  ("tunggakan 30 hari").
- **Trend + ranking**: daily buckets over the fixed 30-day window; top-10 ranking with
  `has_more` surfaced as "dan N outlet lain".
- **Dummy parity**: split existing 61-date dummy window into current 30 + previous 30
  (`end−29…end` and `end−59…end−30`); `end−60` unused; no change to `dummyWindow`.
  Dummy role always admin → strip renders.
- **Zero delta**: `direction = neutral`, chip renders `0,0%` (distinct from null case).
- **Outlet count for has_more**: add `outlet_performance_total` (COUNT DISTINCT outlet_id in
  current window); N = total − 10; line omitted when total ≤ 10.
- **Trend buckets**: zero-fill exactly 30 daily buckets in the insight layer; Dashboard keeps
  sparse behavior; shared `salesTrends()` helper untouched.
- **Severity (needs_attention sort)**: raw values, decline items first — declines by
  `|unrounded delta_percent|` desc, then outstanding-only by `outstanding_total` desc, each
  with name asc → id asc tie-break.
- **Rounding/direction rule**: threshold evaluated on integer cents (or epsilon) so exactly
  −20% is INCLUDED; direction follows the UNROUNDED delta so an `up` chip can never display
  `0,0%`.
- **Scoped degradation**: `/analytics/insight` failure must not blank the AI cards, and an
  `/ai/*` failure must not blank the strip — each section degrades independently.
- **Trust label mapping**: `data_sufficiency.level` (`insufficient|limited|adequate`) maps to
  id-ID copy (e.g. `insufficient` → "Data belum cukup").

## STORIES

### STORY 1 — Strategic metric strip with period comparison
As an admin/owner, I want each headline metric with a period-over-period delta, so that I
can see what changed, not just what the total is.

RULES
- R1: Window fixed. current = last 30 days inclusive (end−29…end); previous = the 30 days
  immediately before (end−59…end−30).
- R2: Metrics compared: orders_total, sales_total, payments_total, outstanding_total.
- R3: delta_percent = (current − previous) / previous × 100, rounded to 1 decimal.
- R4: If previous == 0, delta_percent is null (not 0, not 100); UI renders neutral
  "belum ada pembanding" chip.
- R5: direction: increase → up, decrease → down, equal (0.0) → neutral, null → neutral.
- R6: Excluded statuses (Cancelled/Canceled/Rejected/Invalid) excluded in both windows.
- R6b: outlets_total / products_total are point-in-time counts and get NO delta.

EXAMPLES
- R3 → prev 100, cur 120 → 20.0 / up
- R3 → prev 100, cur 80 → -20.0 / down
- R4 → prev 0, cur 50 → null / neutral chip "belum ada pembanding"
- R5 → prev 100, cur 100 → 0.0 / neutral chip "0,0%"
- R6 → only Cancelled prev → prev 0.00

### STORY 2 — Needs attention strip
As an admin/owner, I want a short ranked list of outlets that need attention, so that I can
act on risk without scanning every outlet.

RULES
- R7: qualifies if sales decline >= 20% vs previous window (previous > 0) OR
  point-in-time outstanding_total > 0 (using OUTSTANDING_ORDER_STATUSES allow-list).
- R8: cap 5 items.
- R9: sort severity desc → outlet name asc → outlet id asc. Decline items rank above
  outstanding-only items.
- R10: each item carries reason enum (sales_decline | outstanding_risk) + backing numbers.
- R11: outlet qualifying both appears once; sales_decline precedence.
- R12b: threshold applied to the UNROUNDED delta (decline from 1000 → 800.04 = −19.996% is
  EXCLUDED).

EXAMPLES
- 1000 → 700 (−30%) → included, sales_decline, delta -30.0
- outstanding 5000, flat sales → included, outstanding_risk
- 1000 → 850 (−15%) → excluded
- 1000 → 800.04 (−19.996%) → excluded (below threshold on unrounded value)
- 8 qualifying → exactly 5
- Alpha/Beta equal severity → Alpha first
- both reasons → one row, sales_decline
- previous 0, current > 0, no outstanding → excluded (no baseline)

### STORY 3 — Trend chart + outlet ranking
As an admin/owner, I want a daily sales trend and a top-outlet ranking, so that I can see
momentum and concentration.

RULES
- R20: trend chart = daily buckets over the fixed 30-day current window.
- R21: ranking = top-10 outlets in the current window, deterministic tie-break.
- R22: `has_more` surfaced as "dan N outlet lain" when a larger ranking exists.

### STORY 4 — AI cards with inline trust labels
R12: Recommendation card shows data_sufficiency.level and measurement.note when present.
R13: Forecast card shows method and method_version/data_sufficiency when present.
R14: absent metadata field → label omitted (no "undefined").
R15: AI cards retained + repositioned; content otherwise unchanged.

### STORY 5 — Access control
R16: non-admin/non-owner authenticated user → 403 with existing error envelope.
R16b: platform_owner → 200 (ownership superset).
R17: unauthenticated → 401.
R17b: `/analytics` nav item marked adminOnly (hidden for outlet/sales/driver/supplier).

### STORY 6 — Dummy-mode parity
R18: dummy ON → full new contract rendered with ZERO network calls.
R19: new fixture dummy.analyticsInsight owns new fields.
R19b: dummy window split current 30 (end−29…end) / previous 30 (end−59…end−30).

## GWT SCENARIOS (updated)

Scenario: Delta computed for a normal increase
  Given previous 30-day sales_total "100.00" and current "120.00"
  When an admin requests GET /api/analytics/insight
  Then metrics_delta.sales_total.delta_percent is 20.0 and direction is "up"

Scenario: Delta computed for a decline
  Given previous "100.00" and current "80.00"
  Then delta_percent is -20.0 and direction is "down"

Scenario: Zero baseline yields null delta
  Given previous "0.00" and current "50.00"
  Then delta_percent is null and direction is "neutral"
  And the UI renders "belum ada pembanding"

Scenario: Zero change yields neutral 0,0% chip
  Given previous "100.00" and current "100.00"
  Then delta_percent is 0.0 and direction is "neutral"
  And the UI renders "0,0%" (not "belum ada pembanding")

Scenario: Windows are equal-length and adjacent
  Given today is 2026-09-18
  Then comparison.period.start_date is "2026-08-20", end_date "2026-09-18"
  And comparison.previous_period.start_date is "2026-07-21", end_date "2026-08-19"

Scenario: Excluded statuses do not count in either window
  Given an outlet has only "Cancelled" orders in the previous window
  Then that outlet's previous sales_total is "0.00"

Scenario: Decline >=20% appears in needs_attention
  Given an outlet sold "1000.00" previous and "700.00" current
  Then needs_attention contains it with reason "sales_decline" and delta_percent -30.0

Scenario: Decline just under threshold is excluded (unrounded)
  Given an outlet sold "1000.00" previous and "800.04" current
  Then needs_attention does NOT contain it

Scenario: Outstanding point-in-time appears in needs_attention
  Given an outlet has unpaid orders totaling "5000.00" older than 30 days
  Then needs_attention contains it with reason "outstanding_risk"

Scenario: Outlet with no baseline and no outstanding is excluded
  Given previous sales "0.00", current "500.00", no outstanding
  Then needs_attention does NOT contain it

Scenario: needs_attention capped at five
  Given eight qualifying outlets
  Then needs_attention has exactly 5 items

Scenario: needs_attention deterministic ordering
  Given "Alpha" and "Beta" equal severity
  Then "Alpha" precedes "Beta"

Scenario: Outlet qualifying for both reasons appears once
  Then it appears once with reason "sales_decline"

Scenario: Trend chart is daily over the current window
  Given the fixed 30-day current window
  Then sales_trends has one bucket per day with orders_total and sales_total

Scenario: Ranking surfaces has_more
  Given 15 outlets with sales in the current window
  Then outlet_performance has 10 rows and analytics_limits.outlet_performance_has_more is true
  And the UI shows "dan 5 outlet lain"

Scenario: platform_owner is allowed
  Given an authenticated platform_owner
  Then GET /api/analytics/insight returns 200

Scenario: Non-admin/non-owner is rejected
  Given an authenticated outlet user
  Then GET /api/analytics/insight returns 403 with status "error"

Scenario: Unauthenticated is rejected
  Given no token
  Then 401

Scenario: AI trust labels render when present
  Given recommendations data_sufficiency.level "insufficient"
  Then the card displays "Data belum cukup"

Scenario: Missing metadata does not break the card
  Given measurement metadata absent
  Then no measurement label is rendered and the page renders without error

Scenario: Dummy mode serves the full contract with zero network
  Given dummy mode ON
  Then deltas and needs_attention are rendered and no network request is made

Scenario: Dummy window splits into current 30 + previous 30
  Given dummyWindow end is "2026-02-14"
  Then current slice is "2026-01-16".."2026-02-14" and previous slice is "2025-12-17".."2026-01-15"

Scenario: platform_owner keeps the Analitik nav item
  Given the sidebar renders for a platform_owner
  Then the Analitik item is visible (adminOnly items shown to owners)

Scenario: outlet count drives the "dan N outlet lain" line
  Given 15 outlets had sales in the current window
  Then outlet_performance_total is 15 and the UI shows "dan 5 outlet lain"
  Given only 7 outlets had sales
  Then outlet_performance_total is 7 and the trailing line is omitted

Scenario: Trend zero-fills order-less days
  Given the current 30-day window has orders on only 12 days
  Then sales_trends has exactly 30 buckets
  And order-less days carry orders_total 0 and sales_total "0.00"

Scenario: Exactly -20% is included
  Given an outlet sold "1000.00" previous and "800.00" current
  Then needs_attention contains it (threshold inclusive, cents math)

Scenario: needs_attention ordering is decline-first then raw magnitude
  Given a -35% decline outlet and an outstanding 900000 outlet
  Then the decline outlet precedes the outstanding outlet

Scenario: insight failure degrades only its own section
  Given /analytics/insight fails but the three /ai/* calls succeed
  Then the AI cards still render and the strip shows a scoped error

Scenario: AI failure degrades only the AI section
  Given an /ai/* call fails but /analytics/insight succeeds
  Then the strip renders normally and the AI section shows a scoped error

Scenario: inactive platform_owner is rejected
  Given a platform_owner whose is_active is false
  Then GET /api/analytics/insight returns 403
