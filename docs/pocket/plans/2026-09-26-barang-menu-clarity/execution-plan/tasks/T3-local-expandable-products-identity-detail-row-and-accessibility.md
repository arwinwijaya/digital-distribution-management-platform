# Task T3 — Local expandable Products identity/detail row and accessibility

**Phase:** 1
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 3: Local expandable Products identity/detail row and accessibility [depends: T2]

## OBJECTIVE
Replace the Products page's name-click/side-history interaction with a Products-local expandable row/detail composition while continuing to render the existing shared `Table` for the tabular header/cell layout. Create `ProductRowDetail.tsx` for identity, supplier facts, product status facts, price context, normalized stock value, static unit note, timestamps, and the local keyboard/ARIA trigger. Consume the `product-clarity.ts` types/helpers from T2 (do not duplicate status/stock logic). Keep `Ubah harga` an independent button and leave `apps/web/src/components/ui/Table.tsx` unchanged.

Steps:
1. Write failing test for: main identity columns and fallback/precedence labels
   Test file: `apps/web/src/app/admin/products/page.test.tsx`
   Level: integration (rendered Products page)
   Test intent: Given products with category, null/empty category, active supplier, inactive supplier, inactive product, and null supplier / When the page loads / Then headers include Nama, SKU, Kategori, Status, Harga Jual, Stok (unit), Dibuat, Diperbarui, Aksi; category fallback is `—`; price tooltip explains order price; stock null renders 0/Habis; main status is Aktif for active product with active/orphan/null supplier, Tidak bisa dibeli for active product with non-active supplier, and Nonaktif for inactive product.
   Exercise through: rendered `AdminProductsPage` and public DOM/accessibility output
   Test doubles: mock `fetch` response only; do not mock ProductRowDetail or classification helpers
   Expected RED: current page has old Harga/Stok headers, no category/status columns, no tooltip, and renders null stock as `—`.
2. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/page.test.tsx --runInBand`
3. Create `ProductRowDetail.tsx` and update page columns/rendering to use local clarity helpers and existing Badge/StatusBadge/Card primitives → verify PASS → refactor while green → commit `feat(web): clarify product table identity columns`
4. Write failing test for: expanded detail identity and stock value
   Test file: `apps/web/src/app/admin/products/ProductRowDetail.test.tsx`
   Level: unit
   Test intent: Given a product with description/category/supplier status/price/normalized stock/timestamps / When the local detail is rendered open / Then it shows all identity facts, supplier name/status, product status facts, `Nilai stok Rp ...`, and `Satuan belum terdefinisi di sistem`, without inventing a UoM.
   Exercise through: `ProductRowDetail` public rendered output
   Test doubles: none; pass a plain AdminProduct fixture
   Expected RED: detail component does not exist.
5. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/ProductRowDetail.test.tsx --runInBand`
6. Implement the local detail panel fields and static unit note using T2's exported status/stock/category helpers and existing UI primitives → verify PASS → refactor while green → commit `feat(web): add expanded product identity detail`
7. Write failing test for: keyboard trigger, ARIA relation, and independent price action
   Test file: `apps/web/src/app/admin/products/ProductRowDetail.test.tsx`
   Level: unit
   Test intent: Given a closed row trigger / When Enter or Space is pressed / Then detail toggles, `aria-expanded` changes, and `aria-controls` points at the detail panel; given `Ubah harga` is focused and activated / Then its callback runs and row expansion does not toggle.
   Exercise through: local trigger/button DOM events and callbacks
   Test doubles: spy callback for price action only; do not mock React event handling or the detail component
   Expected RED: current product name button opens a side panel, has no aria-expanded/controls, and action independence is untested.
8. Run test — verify FAIL: `cd apps/web && npx jest src/app/admin/products/ProductRowDetail.test.tsx --runInBand`
9. Implement semantic row trigger/keyboard handling and stop propagation/independent controls locally in Products; keep `Table.tsx` untouched → verify PASS → refactor while green → commit `feat(web): make product expansion keyboard accessible`

## REFERENCES LOADED
`docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md` — Rules: Main table identity; Expanded identity detail; Price and stock labels; Expanded detail; Accessibility. Code: page, Table, Badge, StatusBadge, Card, Button, format helpers.
[CRITICAL: expandable behavior is local to Products; shared Table.tsx must not change]

## WHY THIS APPROACH
Complexity: standard
Justification: the page must coordinate a local detail row with shared table markup, while the detail component is independently testable for accessibility and calculated display facts.

## SANDWICH CONTEXT
[CRITICAL: Do not add expandable behavior to `apps/web/src/components/ui/Table.tsx` or alter its behavior for other admin pages]
You are implementing the Products-local expandable row for Barang Menu Clarity.
Spec: `docs/pocket/spec/2026-09-26-barang-menu-clarity/barang-menu-clarity.md`
Design decision: Option A — local expandable row in Products.
Files in scope: `apps/web/src/app/admin/products/page.tsx`, `ProductRowDetail.tsx`, their tests, and existing UI primitive imports only.
Available after: T2 (product clarity helpers, adapter contract, and dummy parity).
Architecture rule: compose existing `Table`, `Card`, `Badge`, `StatusBadge`, `TableSummary`, and `TablePagination`; use a local row/detail implementation and preserve independent `Ubah harga` behavior.
[RESTATE: Shared Table.tsx remains unchanged; all expansion and ARIA behavior belongs to Products.]

## DELIVERABLE
Given a loaded product list / When the page renders / Then identity columns and explicit Harga Jual/Stok (unit) labels appear with category/status/fallbacks.
Given a product row trigger / When Enter or Space is pressed / Then the detail panel opens/closes with correct `aria-expanded` and `aria-controls`.
Given expanded detail / When displayed / Then description, category, supplier context, both product/supplier facts, timestamps, price context, normalized stock value, and static unit note appear.
Given the price action / When clicked or keyboard-activated / Then only price editing runs and row expansion does not toggle.
Given an inactive product with inactive supplier / When rendered / Then one main badge is Nonaktif while detail explains supplier inactivity.

## QUALITY BAR
Must-have:
  - Local Products implementation owns expansion; shared Table.tsx has no diff.
  - Null/empty category displays em dash; null stock displays normalized 0/Habis.
  - Supplier null/orphan safely displays em dash and is not classified as non-purchasable.
  - Keyboard and ARIA behavior is directly tested.
Must-not-have:
  - No side-panel-only solution, no UoM invention, no edits to shared table behavior, and no reimplemented status/stock logic; import T2 helpers.
Open question risks:
  - Existing table row markup may require a local wrapper to place a detail row; preserve table semantics or report `NEEDS_CONTEXT` rather than invalid HTML.
Rollback note:
  - Revert page and new local detail files; shared Table.tsx remains unchanged.
Red flags:
  - Any edit to `apps/web/src/components/ui/Table.tsx` → STOP.

## STOP CONDITIONS
Done when: page/detail tests pass, ARIA and independent action behavior are observable, and Table.tsx is byte-for-byte unchanged.
Uncertain when: valid HTML requires a local table renderer that would duplicate shared behavior; escalate before changing shared Table.
Escalate when: expansion cannot be implemented locally without changing shared component behavior.
