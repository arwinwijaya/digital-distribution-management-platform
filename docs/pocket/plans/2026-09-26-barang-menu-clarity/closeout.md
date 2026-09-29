# Closeout — 2026-09-26-barang-menu-clarity

- **Plan:** docs/pocket/plans/2026-09-26-barang-menu-clarity
- **Type:** flat
- **Started:** 2026-09-28  ·  **Closed:** 2026-09-29
- **Baseline SHA:** 12a2782f7339eb81b0b82cd868aeac36273c604  ·  **Final SHA:** 8601bc88edc08a94eec87c4f164aa3386f88afa3
- **Result:** CLOSED — all phases DONE, all reviewable tasks REVIEW_PASS

## Phases

### Phase 1 — execution-plan/index.md  (DONE)

| Task | Name | done_sha | Verdict |
|------|------|----------|---------|
| T1 | Backend product list contract, filters, summary, supplier, and sorting | e7e615aca2882b952b7b76869c13c2489d5eeb07 | REVIEW_PASS |
| T2 | Frontend product contract, clarity helpers, sort mirror, and dummy parity | 954c28f733c235dd9d54ace02e4b42fe69cba271 | REVIEW_PASS |
| T3 | Local expandable Products identity/detail row and accessibility | 966ad6471ee0ca14715ce956d4e335ae01120375 | REVIEW_PASS |
| T4 | Server-side filter controls, state reset, summary, sorting, paging, and list retry | bb3217ad875aa3c97d66345a59ea96758d78311a | REVIEW_PASS |
| T5 | Price history panel pagination, retry, abort, and stale-response guard | d92e2c14d0a95e8e3153ad370984b80622ba2c3a | REVIEW_PASS |
| T6 | Cross-unit contract and regression verification | 8601bc88edc08a94eec87c4f164aa3386f88afa3 | REVIEW_PASS |

_SHA range: 12a2782f7339eb81b0b82cd868aeac36273c604..8601bc88edc08a94eec87c4f164aa3386f88afa3_

## Carried Forward

Non-blocking observations from review — accepted at close, recorded for follow-up.

### T2
- (Minor) No test asserts exact backend-vs-dummy category ordering under mixed-case/locale-sensitive values.

### T4
- (Strength) Tanpa kategori works end-to-end: frontend sends category=__none__ and backend matches only null/empty/whitespace-only via whereNull OR TRIM(category)='', never literal '__none__'.
- (Strength) Ordinary category filter stays exact-after-trim and case-sensitive; backend uses whereRaw TRIM(category)=? with no LOWER.
- (Strength) Filters are server-side with AND semantics; include_unpurchasable=1 passes through correctly.
- (Strength) Filter change resets cursor to 0, preserves sort/order via sortRef, closes expanded rows.
- (Strength) Retryable list error with keyboard-accessible 'Coba lagi', disabled + guard while loading.
- (Strength) AbortController per list load with abort-on-new-load and abort-on-unmount; stale responses cannot paint.
- (Strength) Category metadata distinct, non-empty, trimmed, case-insensitively sorted (strcasecmp) matching frontend ordering.
- (Strength) No changes to Table.tsx, no database migration, no order/inventory deduction or public-catalog eligibility changes.

### T5
- (Strength) AbortController + requestId dual guard, duplicate load-more protection, dummy zero-network parity, clean panel/page separation.
- (Minor) Adapter 404 status propagation (api.ts:373) lacks a dedicated test; panel 404 test uses fabricated Error({status:404, message:'Product not found'}) whose message also matches the regex fallback.
- (Minor) onClose wiring (page.tsx:299 → ProductRowDetail.tsx:106 → PriceHistoryPanel.tsx:140) has no regression test.
- (Minor) Unstable callback identities: page.tsx passes historyFetch as inline arrow and closeExpandedRows as plain function; parent re-render aborts in-flight history and refetches. Consider useCallback.
- (Minor) Load-more request creates its own AbortController but never aborts it on unmount/product change; correctness preserved by requestId guard but network request not cancelled.
- (Minor) api.ts uses (err as any).status = 404 instead of the existing ApiError class; consistency with sibling admin pages would use ApiError.
- (Minor) Load-more 404 path classifies as deleted and shows 'Produk ini sudah dihapus.' but does not call onClose (only initial-load path does).

### T6
- (Strength) Real seams only; exact-value assertions (no tautologies); complete edge fixtures (orphan/null supplier, null category, tie-break product); clean commit scope.
- (Minor) New backend test method ~157 lines — exceeds ~50-line heuristic but matches file convention (pre-existing tests at 88/62/60 lines); well-structured with comment-delimited sections.
- (Minor) Both test files exceed 300 lines — pre-existing state; large feature test classes are the established convention for this codebase.

## Skipped Tasks

_None_