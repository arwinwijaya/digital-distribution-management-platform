# EXECUTION PLAN — Worktree Order Flow Visualization

**Date:** 2026-05-06
**Spec:** docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md
**Status:** approved
**Total tasks:** 4

---

## Execution Overview

### Recommended Order
```
T1 → T2 → T3 → T4
```

> Dependency order above is **recommended** — pocket skill enforces actual
> parallelism and sequencing based on its routing logic.

### Parallelizable Groups
No parallelizable groups — tasks are sequential (each builds on previous).

### Constraints Reminder
**Architecture:** Web frontend only. `'use client'` + `mx-auto max-w-6xl` + `PageHeader` + `Card` pattern. Reuse `Card, Badge, StatusBadge, PageHeader`. Tailwind only — no new chart/flow libraries.
**Out-of-scope:** No backend API calls, no order logic changes, no filtering/search/export, no files outside `Sidebar.tsx`, `worktree/page.tsx`, `WorktreeFlow.tsx`
**Assumptions at risk:** Selesai = Paid only (not Delivered+Paid); platform_owner/supplier have no highlight

### File Structure Map

```
Rule: Sidebar menu visible for all roles
  Modify: apps/web/src/components/Sidebar.tsx:NAV_ITEMS (add Worktree entry)
  Modify: apps/web/src/components/Sidebar.test.tsx (add Worktree visibility test)

Rule: Flow diagram 6 tahap + 2 terminal
  Create: apps/web/src/components/WorktreeFlow.tsx (flow nodes + connectors + branch terminal)
  Create: apps/web/src/components/WorktreeFlow.test.tsx (node rendering tests)

Rule: Role-based highlight
  Modify: apps/web/src/components/WorktreeFlow.tsx (highlight logic based on role)
  Modify: apps/web/src/components/WorktreeFlow.test.tsx (highlight tests)

Rule: Panel detail interaction
  Modify: apps/web/src/components/WorktreeFlow.tsx (panel state + content)
  Modify: apps/web/src/components/WorktreeFlow.test.tsx (panel interaction tests)

Rule: Deep link access control
  Modify: apps/web/src/components/WorktreeFlow.tsx (access control per role)
  Modify: apps/web/src/components/WorktreeFlow.test.tsx (deep link access tests)

Rule: Auth guard + page shell
  Create: apps/web/src/app/worktree/page.tsx (auth guard + layout)
  Create: apps/web/src/app/worktree/worktree.test.tsx (page-level integration test)
```

---

## Pocket Packets

---

### Task 1: Sidebar entry + page shell [prereq]

## OBJECTIVE
Add Worktree menu item to sidebar (visible to ALL roles, no `finance`/`adminOnly` flag) and create the page shell at `/worktree` with auth guard redirecting unauthenticated users to `/login`.

Files:
- Modify: `apps/web/src/components/Sidebar.tsx` (add NAV_ITEMS entry after Sales)
- Modify: `apps/web/src/components/Sidebar.test.tsx` (add Worktree visibility assertions)
- Create: `apps/web/src/app/worktree/page.tsx`
- Test: `apps/web/src/app/worktree/worktree.test.tsx`

Steps:
1. Write failing test for: Menu Worktree tampil untuk semua role
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: unit

   Test intent:
   Given user login sebagai outlet
   When  sidebar rendered
   Then:
   - menu "Worktree" dengan icon 🌳 ada di DOM
   - href attribute = /worktree

   Exercise through: `<Sidebar />` component render
   Test doubles: mockRole('outlet'), mock next/link + next/navigation
   Expected RED: Worktree text not found in rendered sidebar

2. Run test — verify FAIL:
   `cd apps/web && npx jest Sidebar.test.tsx --testNamePattern="Worktree"`
   Expected failure: Cannot find text "Worktree"

3. Add NAV_ITEMS entry to Sidebar.tsx:
   File: `apps/web/src/components/Sidebar.tsx`
   Insert after `{ href: '/sales', label: 'Sales', icon: '📋' }`:
   ```ts
   { href: '/worktree', label: 'Worktree', icon: '🌳' },
   ```
   No `finance`, no `adminOnly` — visible to all roles.

4. Run test — verify PASS:
   `cd apps/web && npx jest Sidebar.test.tsx --testNamePattern="Worktree"`
   Expected: PASS

5. Write failing test for: Worktree visible for finance role (no Sales/Analitik)
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: unit

   Test intent:
   Given user login sebagai finance
   When  sidebar rendered
   Then:
   - "Worktree" text found in DOM
   - finance role sees: Dasbor, Invoice, Pembayaran, Worktree (no Sales, no Analitik)

   Exercise through: `<Sidebar />` render with mockRole('finance')
   Expected RED: Currently finance filtered items don't include Worktree if it has no `finance` flag — but Worktree should appear for finance too

6. Run test — verify FAIL:
   `cd apps/web && npx jest Sidebar.test.tsx --testNamePattern="finance.*Worktree|Worktree.*finance"`

7. Fix `visibleItemsFor` in Sidebar.tsx:
   Worktree item has neither `finance` nor `adminOnly` flags, so `NAV_ITEMS.filter((item) => !item.adminOnly)` already includes it for all non-finance roles. Finance role filters to `item.finance` only — Worktree needs to be visible to finance too.
   
   Solution: Add a separate filter for Worktree in the finance branch, or add a `worktree: true` flag and update the filter logic:
   ```ts
   if (role === 'finance') return NAV_ITEMS.filter((item) => item.finance || item.href === '/worktree');
   ```

8. Run test — verify PASS:
   `cd apps/web && npx jest Sidebar.test.tsx --testNamePattern="Worktree"`

9. Write failing test for: Unauthenticated access redirects to /login
   Test file: `apps/web/src/app/worktree/worktree.test.tsx`
   Level: integration

   Test intent:
   Given user tidak login (no token in localStorage)
   When  /worktree page dimuat
   Then:
   - page renders "Masuk" or login prompt (redirect behavior)
   - no WorktreeFlow component rendered

   Exercise through: `<WorktreePage />` render
   Test doubles: mock getStoredToken returning null
   Expected RED: page may not exist yet

10. Run test — verify FAIL:
    `cd apps/web && npx jest worktree.test.tsx`

11. Create page shell:
    File: `apps/web/src/app/worktree/page.tsx`
    ```tsx
    'use client';
    
    import { useEffect, useState } from 'react';
    import { useRouter } from 'next/navigation';
    import { getStoredToken } from '@/lib/api';
    import { useDummyStore } from '@/dummy/store';
    import { PageHeader } from '@/components/ui';
    import LoginForm from '@/components/LoginForm';
    import WorktreeFlow from '@/components/WorktreeFlow';
    
    export default function WorktreePage() {
      const router = useRouter();
      const [token, setToken] = useState<string | null>(null);
      const [ready, setReady] = useState(false);
    
      useEffect(() => {
        const t = getStoredToken();
        setToken(t);
        setReady(true);
        if (!t) router.replace('/login');
      }, [router]);
    
      if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
      if (!token) {
        return (
          <div className="mx-auto max-w-6xl">
            <PageHeader title="Worktree" description="Alur proses pemesanan end-to-end." />
            <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
              Masuk untuk melihat alur proses pemesanan.
            </div>
            <LoginForm onLogin={(nextToken) => setToken(nextToken)} />
          </div>
        );
      }
    
      return (
        <div className="mx-auto max-w-6xl">
          <PageHeader
            title="Worktree"
            description="Alur proses pemesanan dari awal sampai selesai. Klik tahap untuk melihat detail."
          />
          <WorktreeFlow />
        </div>
      );
    }
    ```

12. Create test file skeleton:
    File: `apps/web/src/app/worktree/worktree.test.tsx`

13. Run full test suite:
    `cd apps/web && npx jest --testPathPattern="Sidebar|worktree" --runInBand`

14. Commit:
    `git add apps/web/src/components/Sidebar.tsx apps/web/src/components/Sidebar.test.tsx apps/web/src/app/worktree/`
    `git commit -m "feat(worktree): add sidebar entry and page shell with auth guard"`

## REFERENCES LOADED
- `docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md` — Rule: Menu sidebar visible for all roles, Rule: Auth guard
- `apps/web/src/components/Sidebar.tsx` — existing NAV_ITEMS pattern, visibleItemsFor filter logic
- `apps/web/src/components/Sidebar.test.tsx` — existing test pattern with mockRole helper
- `apps/web/src/app/orders/page.tsx` — auth guard pattern (getStoredToken + LoginForm fallback)

## WHY THIS APPROACH
Justification: Sidebar entry + page shell are the foundation. All subsequent tasks depend on WorktreeFlow existing in a rendered page context.
Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: Worktree menu must NOT have `finance` or `adminOnly` flags — it must appear for ALL roles including finance and supplier]
You are implementing Sidebar entry + page shell for Worktree.
Spec: docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md
Design decision: Vertical flow + clickable node + slide-in panel
Files in scope: apps/web/src/components/Sidebar.tsx, apps/web/src/components/Sidebar.test.tsx, apps/web/src/app/worktree/page.tsx, apps/web/src/app/worktree/worktree.test.tsx
Test framework: Jest + @testing-library/react
Available after: none (prereq)
Architecture rule: Must follow `'use client'` + `mx-auto max-w-6xl` + `PageHeader` pattern
[RESTATE: Worktree menu visible to ALL roles — no finance/adminOnly filter]

## DELIVERABLE
✓ Given outlet login, When sidebar rendered, Then "Worktree" 🌳 menu appears with href=/worktree
✓ Given finance login, When sidebar rendered, Then "Worktree" appears (not filtered out)
✓ Given no token, When /worktree loaded, Then redirect to /login or show login prompt
✓ Given token present, When /worktree loaded, Then page renders with PageHeader and placeholder for WorktreeFlow

All tests PASS. Commit exists with message `feat(worktree): add sidebar entry and page shell with auth guard`.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Worktree item in NAV_ITEMS after Sales, no finance/adminOnly flags
  - `visibleItemsFor` updated so finance role sees Worktree
  - Page auth guard: no token → redirect/login prompt
  - Tests written BEFORE implementation (TDD)
  - Commit follows conventional commits

Must-not-have:
  - No API calls to backend
  - No modifications to existing page components
  - No new dependencies

Open question risks:
  - Selesai = Paid only → if wrong: minimal re-planning needed (Task 2)

Rollback note:
  - Delete worktree/page.tsx, remove NAV_ITEMS entry from Sidebar.tsx

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: none
Escalate when: sidebar filter logic breaks for other roles

---

### Task 2: WorktreeFlow core component (flow nodes + connectors + branch terminal) [depends: T1]

## OBJECTIVE
Create `WorktreeFlow.tsx` rendering a vertical flow diagram with 6 main stage nodes connected by arrows, plus 2 terminal branch nodes (Cancelled, Failed) connected by dashed red lines. Each node displays emoji icon, stage name, StatusBadge, and description. No panel interaction or role highlight yet — pure static visual.

Files:
- Create: `apps/web/src/components/WorktreeFlow.tsx`
- Create: `apps/web/src/components/WorktreeFlow.test.tsx`

Steps:
1. Write failing test for: 6 tahap tampil berurutan
   Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
   Level: unit

   Test intent:
   Given WorktreeFlow component rendered
   When  DOM inspected
   Then:
   - "Pemesanan" text exists
   - "Persetujuan" text exists
   - "Penugasan" text exists
   - "Pengiriman" text exists
   - "Pembayaran" text exists
   - "Selesai" text exists
   - "Dibatalkan" text exists
   - "Gagal" text exists

   Exercise through: `<WorktreeFlow />` render
   Test doubles: none needed (static component)
   Expected RED: WorktreeFlow component does not exist

2. Run test — verify FAIL:
   `cd apps/web && npx jest WorktreeFlow.test.tsx`

3. Create WorktreeFlow component skeleton:
   File: `apps/web/src/components/WorktreeFlow.tsx`

   Define the flow data structure:
   ```ts
   type Stage = {
     id: string;
     label: string;
     status: string;     // maps to StatusBadge
     icon: string;       // emoji
     description: string;
     roles: string[];    // actor roles
     deepLink: string;   // href for "Buka menu"
     accessRoles: string[]; // roles that can access deepLink
   };

   type TerminalNode = {
     id: string;
     label: string;
     icon: string;
     sources: string[];  // which stage ids connect to this
   };

   const STAGES: Stage[] = [
     { id: 'order', label: 'Pemesanan', status: 'New', icon: '🛒', description: 'Outlet memilih produk dan mengirim pesanan', roles: ['outlet', 'sales'], deepLink: '/orders', accessRoles: ['outlet', 'sales', 'admin', 'platform_owner'] },
     { id: 'approval', label: 'Persetujuan', status: 'Confirmed', icon: '✅', description: 'Admin meninjau dan menyetujui pesanan', roles: ['admin'], deepLink: '/admin/orders', accessRoles: ['admin', 'platform_owner'] },
     { id: 'assignment', label: 'Penugasan', status: 'Assigned', icon: '📋', description: 'Admin/sales menugaskan driver untuk pengiriman', roles: ['admin', 'sales'], deepLink: '/delivery', accessRoles: ['admin', 'sales', 'driver', 'platform_owner'] },
     { id: 'delivery', label: 'Pengiriman', status: 'Delivered', icon: '🚚', description: 'Driver mengantar pesanan ke outlet', roles: ['driver'], deepLink: '/delivery', accessRoles: ['admin', 'sales', 'driver', 'platform_owner'] },
     { id: 'payment', label: 'Pembayaran', status: 'Paid', icon: '💳', description: 'Outlet membayar pesanan (bisa bertahap)', roles: ['finance', 'admin', 'outlet'], deepLink: '/payments', accessRoles: ['admin', 'finance', 'platform_owner'] },
     { id: 'done', label: 'Selesai', status: 'Completed', icon: '🎉', description: 'Pesanan selesai diproses', roles: [], deepLink: '/orders', accessRoles: ['outlet', 'sales', 'admin', 'platform_owner'] },
   ];

   const TERMINALS: TerminalNode[] = [
     { id: 'cancelled', label: 'Dibatalkan', icon: '🚫', sources: ['order', 'approval', 'payment'] },
     { id: 'failed', label: 'Gagal', icon: '❌', sources: ['delivery'] },
   ];
   ```

   Render basic structure:
   - Main flow: vertical column of `Card` nodes with `StatusBadge`
   - Connectors: vertical lines between nodes (CSS border)
   - Terminal section: below main flow, with dashed connectors from sources

4. Run test — verify PASS:
   `cd apps/web && npx jest WorktreeFlow.test.tsx`

5. Write failing test for: Connector visual antar tahap
   Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
   Level: unit

   Test intent:
   Given WorktreeFlow rendered
   When  inspect DOM structure
   Then:
   - nodes are in vertical order (Pemesanan before Persetujuan, etc.)
   - connector elements exist between consecutive main stages
   - dashed connector lines exist from order/approval/payment to Dibatalkan
   - dashed connector line exists from delivery to Gagal

   Exercise through: DOM structure check
   Expected RED: connectors not implemented yet

6. Run test — verify FAIL:
   `cd apps/web && npx jest WorktreeFlow.test.tsx --testNamePattern="connector|Connector"`

7. Implement connector rendering:
   Add CSS-based connectors between nodes using `border-l-2 border-gray-300` vertical lines.
   For terminal dashed connectors: `border-l-2 border-dashed border-danger-300`.

8. Run test — verify PASS:
   `cd apps/web && npx jest WorktreeFlow.test.tsx`

9. Write failing test for: Each node shows StatusBadge
   Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
   Level: unit

   Test intent:
   Given WorktreeFlow rendered
   When  look for StatusBadge elements
   Then:
   - badge with "Baru" text exists (New status)
   - badge with "Dikonfirmasi" text exists (Confirmed)
   - badge with "Ditugaskan" text exists (Assigned)
   - badge with "Terkirim" text exists (Delivered)
   - badge with "Lunas" text exists (Paid)
   - badge with "Selesai" text exists (Completed)

   Exercise through: StatusBadge rendering inside nodes
   Expected RED: StatusBadge not yet rendered in nodes

10. Run test — verify FAIL:
    `cd apps/web && npx jest WorktreeFlow.test.tsx --testNamePattern="StatusBadge"`

11. Add StatusBadge to each node rendering, mapping stage.status to StatusBadge component.

12. Run test — verify PASS:
    `cd apps/web && npx jest WorktreeFlow.test.tsx`

13. Refactor while green:
    - Extract `FlowNode` sub-component if node rendering exceeds ~50 lines
    - Extract `TerminalSection` sub-component for branch nodes
    - Verify: `cd apps/web && npx jest WorktreeFlow.test.tsx` — must stay PASS

14. Commit:
    `git add apps/web/src/components/WorktreeFlow.tsx apps/web/src/components/WorktreeFlow.test.tsx`
    `git commit -m "feat(worktree): add WorktreeFlow core component with flow nodes and connectors"`

## REFERENCES LOADED
- `docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md` — Story 2 (flow diagram), Story 5 (cancelled/failed)
- `apps/web/src/components/ui/Card.tsx` — reuse Card for node containers
- `apps/web/src/components/ui/StatusBadge.tsx` — reuse StatusBadge for status mapping
- `apps/web/src/components/ui/Badge.tsx` — available for role badges (Task 3)

## WHY THIS APPROACH
Justification: Build the static visual foundation first — nodes, connectors, branch terminals — then layer interaction on top in Task 3. Clean separation of visual rendering vs behavior.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: All visualization must use Tailwind CSS only — no chart/flow libraries]
You are implementing WorktreeFlow core visual component.
Spec: docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md
Design decision: Vertical flow + clickable node + slide-in panel
Files in scope: apps/web/src/components/WorktreeFlow.tsx, apps/web/src/components/WorktreeFlow.test.tsx
Test framework: Jest + @testing-library/react
Available after: T1 (page shell exists)
Architecture rule: Tailwind only, reuse Card + StatusBadge from @/components/ui
[RESTATE: No new chart/flow libraries — div/border/SVG Tailwind only]

## DELIVERABLE
✓ Given WorktreeFlow rendered, When inspect DOM, Then 6 main stage nodes exist in order: Pemesanan→Persetujuan→Penugasan→Pengiriman→Pembayaran→Selesai
✓ Given rendered, When inspect, Then terminal nodes "Dibatalkan" and "Gagal" exist
✓ Given rendered, When inspect, Then connectors between main stages visible
✓ Given rendered, When inspect, Then dashed connectors from order/approval/payment→Dibatalkan and delivery→Gagal exist
✓ Given rendered, When inspect, Then each node has StatusBadge showing correct label

All tests PASS. Commit exists.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - 6 main stages in correct order
  - 2 terminal nodes (Dibatalkan, Gagal) with correct source connectors
  - StatusBadge rendered per node
  - All connectors visible (solid for main, dashed for terminal)
  - Tests before implementation (TDD)
  - Commit follows conventional commits

Must-not-have:
  - No panel/detail interaction yet (Task 3)
  - No role-based highlight yet (Task 3)
  - No deep-link buttons yet (Task 3)
  - No new dependencies

Open question risks:
  - StatusBadge label mapping for "Assigned" → "Ditugaskan" — verify StatusBadge has this mapping (it does: `assigned: { label: 'Ditugaskan', variant: 'blue' }`)

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: none
Escalate when: StatusBadge doesn't map expected status

---

### Task 3: WorktreeFlow interactive panel + role highlight + deep links [depends: T2]

## OBJECTIVE
Add interactivity to WorktreeFlow: click node → slide-in detail panel with role badges, action description, deep-link "Buka menu" button (hidden if role no access). Add role-based highlight: highlighted stages get border solid + bg-primary-50, non-highlighted get border dashed + opacity-60. Panel responsive: right on desktop, bottom on mobile.

Files:
- Modify: `apps/web/src/components/WorktreeFlow.tsx` (add panel state, highlight, access control)
- Modify: `apps/web/src/app/worktree/page.tsx` (pass role from useSidebarAuth)
- Modify: `apps/web/src/components/WorktreeFlow.test.tsx` (extend tests)

Steps:
1. Write failing test for: Klik step tampilkan panel detail
   Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
   Level: unit

   Test intent:
   Given WorktreeFlow rendered with role='admin'
   When  user klik node "Persetujuan"
   Then:
   - panel detail muncul (text "Approve order" or deskripsi Persetujuan visible)
   - panel contains StatusBadge "Dikonfirmasi"
   - panel contains role badge "Admin"
   - panel contains "Buka menu" link with href="/admin/orders" (admin in accessRoles)

   Exercise through: fireEvent.click on Persetujuan node, then check panel content
   Expected RED: No panel appears on click

2. Run test — verify FAIL:
   `cd apps/web && npx jest WorktreeFlow.test.tsx --testNamePattern="panel|click"`

3. Implement panel state and rendering in WorktreeFlow.tsx:
   - Add `useState<string | null>(selectedStageId)` for panel state
   - On node click: toggle selectedStageId (if same → null, if different → new id)
   - Render detail panel when selectedStageId is set:
     - Stage name + StatusBadge
     - Role badges (Badge component per role)
     - Action description
     - Deep link button ("Buka menu") wrapped in `<Link>`
   - Panel layout: `flex flex-col lg:flex-row` — panel on right (desktop) or below (mobile)
   - Click outside panel: add `useEffect` with click listener on document, close if click target outside panel

4. Run test — verify PASS:
   `cd apps/web && npx jest WorktreeFlow.test.tsx --testNamePattern="panel|click"`

5. Write failing test for: Deep link access control — outlet cannot see "Buka menu" on Persetujuan
   Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
   Level: unit

   Test intent:
   Given WorktreeFlow rendered with role='outlet'
   When  user klik node "Persetujuan"
   Then:
   - panel detail opens
   - "Buka menu" button/link is NOT rendered (outlet not in accessRoles for /admin/orders)

   Also positive cases:
   Given WorktreeFlow rendered with role='admin'
   When  user klik "Persetujuan"
   Then  "Buka menu" visible with href=/admin/orders

   Given WorktreeFlow rendered with role='driver'
   When  user klik "Pengiriman"
   Then  "Buka menu" visible with href=/delivery

   Exercise through: click nodes, check "Buka menu" presence/absence and href
   Expected RED: "Buka menu" button currently always renders

6. Run test — verify FAIL

7. Implement access control for "Buka menu":
   - Accept `role` prop in WorktreeFlow
   - In panel rendering: only show "Buka menu" button if `stage.accessRoles.includes(role)`
   - Pass role from page.tsx: `<WorktreeFlow role={role} />`

8. Run test — verify PASS

9. Write failing test for: Role-based highlight — all role mappings
   Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
   Level: unit

   Test intent:
   Given WorktreeFlow rendered with role='admin'
   When  inspect node styling
   Then:
   - Persetujuan, Penugasan, Pembayaran have 'bg-primary-50' (highlighted)
   - Pemesanan, Pengiriman, Selesai have 'opacity-60' (not highlighted)

   Given WorktreeFlow rendered with role='outlet'
   When  inspect node styling
   Then: only Pemesanan highlighted, rest opacity-60

   Given WorktreeFlow rendered with role='sales'
   When  inspect node styling
   Then: Pemesanan + Penugasan highlighted

   Given WorktreeFlow rendered with role='driver'
   When  inspect node styling
   Then: only Pengiriman highlighted

   Given WorktreeFlow rendered with role='finance'
   When  inspect node styling
   Then: only Pembayaran highlighted

   Given WorktreeFlow rendered with role='supplier'
   When  inspect node styling
   Then: NO highlight, all nodes normal opacity

   Given WorktreeFlow rendered with role='platform_owner'
   When  inspect node styling
   Then: NO highlight, all nodes normal opacity

   Exercise through: check className of node containers per role
   Expected RED: No highlight logic exists

10. Run test — verify FAIL

11. Implement role-based highlight:
    - Define highlight mapping:
      ```ts
      const ROLE_HIGHLIGHTS: Record<string, string[]> = {
        outlet: ['order'],
        sales: ['order', 'assignment'],
        driver: ['delivery'],
        admin: ['approval', 'assignment', 'payment'],
        finance: ['payment'],
        supplier: [],
        platform_owner: [],
      };
      ```
    - In node rendering: check if `ROLE_HIGHLIGHTS[role]?.includes(stage.id)`
    - Highlighted: `border-solid border-primary-200 bg-primary-50 shadow-sm`
    - Non-highlighted: `border-dashed border-gray-200 bg-gray-50 opacity-60`
    - Supplier/platform_owner: all nodes normal opacity (no highlight, no dim)

12. Run test — verify PASS:
    `cd apps/web && npx jest WorktreeFlow.test.tsx`

13. Write failing test for: Panel closes on toggle (click same node again)
    Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
    Level: unit

    Test intent:
    Given WorktreeFlow with panel open on Persetujuan
    When  user klik "Persetujuan" node lagi
    Then:
      panel detail content disappears (toggled closed)

14. Run test — verify FAIL, implement toggle, verify PASS

15. Write failing test for: Panel closes on click outside
    Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
    Level: unit

    Test intent:
    Given panel open
    When  user klik area di luar panel (document.body)
    Then:
      panel closes

16. Run test — verify FAIL, implement outside click handler, verify PASS

17. Update page.tsx to pass role:
    File: `apps/web/src/app/worktree/page.tsx`
    - Add `useSidebarAuth` hook (copy from Sidebar.tsx pattern — `useState<string|null>` + `useEffect` calling GET /auth/me, or use dummy store for offline role)
    - Call `const { role } = useSidebarAuth()`
    - Pass role to `<WorktreeFlow role={role} />`

18. Write failing test for: Responsive layout — panel position classes
    Test file: `apps/web/src/components/WorktreeFlow.test.tsx`
    Level: unit

    Test intent:
    Given WorktreeFlow with panel open
    When  inspect panel container className
    Then:
    - panel has 'lg:w-80' or 'lg:flex-row' class for desktop right layout
    - container has 'flex-col lg:flex-row' for responsive stacking

    Exercise through: className check (jsdom doesn't resize — verify class presence)
    Expected RED: responsive classes not yet applied

    Run: `cd apps/web && npx jest WorktreeFlow.test.tsx --testNamePattern="responsive"`
    Implement: `flex flex-col lg:flex-row` on container, `lg:w-80 shrink-0` on panel
    Run: verify PASS

19. Run full test suite:
    `cd apps/web && npx jest --testPathPattern="Sidebar|WorktreeFlow|worktree" --runInBand`

20. Refactor while green:
    - Extract `DetailPanel` sub-component if panel rendering exceeds ~50 lines
    - Extract highlight class logic into a helper function if duplicated
    - Verify tests stay PASS

21. Commit:
    `git add apps/web/src/components/WorktreeFlow.tsx apps/web/src/app/worktree/page.tsx apps/web/src/components/WorktreeFlow.test.tsx`
    `git commit -m "feat(worktree): add interactive panel, role highlight, and deep link access control"`

## REFERENCES LOADED
- `docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md` — Story 3 (highlight), Story 4 (panel), Story 1 (access control)
- `apps/web/src/components/ui/Badge.tsx` — role badge in panel
- `apps/web/src/components/Sidebar.tsx` — useSidebarAuth pattern for role resolution

## WHY THIS APPROACH
Justification: Layering interaction on top of the visual foundation from Task 2. Panel state, highlight, and access control are tightly coupled — implement together for consistency.
Complexity: standard

## SANDWICH CONTEXT
[CRITICAL: Panel must be responsive — right on desktop (≥1024px), below on mobile (<1024px). Use lg: breakpoint]
You are implementing interactive panel, role highlight, and deep link access control.
Spec: docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md
Design decision: Vertical flow + clickable node + slide-in panel (right/bottom responsive)
Files in scope: apps/web/src/components/WorktreeFlow.tsx, apps/web/src/app/worktree/page.tsx, apps/web/src/components/WorktreeFlow.test.tsx
Test framework: Jest + @testing-library/react
Available after: T2 (core flow renders)
Architecture rule: Reuse Badge, StatusBadge, Card from @/components/ui
[RESTATE: Deep link "Buka menu" button must be hidden when role not in accessRoles]

## DELIVERABLE
✓ Given admin click Persetujuan, When panel opens, Then "Buka menu" visible (admin in accessRoles)
✓ Given outlet click Persetujuan, When panel opens, Then "Buka menu" NOT visible (outlet not in accessRoles)
✓ Given admin login, When inspect nodes, Then Persetujuan+Penugasan+Pembayaran highlighted (bg-primary-50)
✓ Given outlet login, When inspect nodes, Then only Pemesanan highlighted
✓ Given supplier login, When inspect nodes, Then no highlight, all nodes normal opacity
✓ Given panel open, When click same node, Then panel closes (toggle)
✓ Given panel open, When click outside, Then panel closes
✓ Given desktop viewport, When panel open, Then panel on right (~320px)
✓ Given mobile viewport, When panel open, Then panel below (full-width)

All tests PASS. Commit exists.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Panel toggle on node click (same node = close)
  - Outside click closes panel
  - Access control: "Buka menu" hidden if role not in accessRoles
  - Highlight mapping per role as defined in spec
  - Responsive: lg: breakpoint for right/bottom
  - Role passed from page.tsx to WorktreeFlow
  - Tests before implementation (TDD)

Must-not-have:
  - No API calls
  - No new dependencies
  - No overlay/modal for panel (slide-in only)

Open question risks:
  - platform_owner highlights → assumed: no highlight (confirmed in spec)
  - Redirect /login if unauthenticated → handled in T1 page.tsx

## STOP CONDITIONS
Done when: all DELIVERABLE scenarios pass, tests green, commit created
Uncertain when: none
Escalate when: responsive breakpoint doesn't work correctly

---

### Task 4: End-to-end integration tests + final verification [depends: T3]

## OBJECTIVE
Write page-level integration tests covering: page renders with auth, WorktreeFlow visible when logged in, login prompt when not authenticated, sidebar Worktree link navigates correctly. Run full test suite and verify all pass.

Files:
- Modify/Create: `apps/web/src/app/worktree/worktree.test.tsx`
- Modify: `apps/web/src/components/Sidebar.test.tsx` (verify Worktree link href)

Steps:
1. Write integration test for: Page renders WorktreeFlow when authenticated
   Test file: `apps/web/src/app/worktree/worktree.test.tsx`
   Level: integration

   Test intent:
   Given localStorage has ddp_token
   When  WorktreePage rendered
   Then:
   - PageHeader with "Worktree" title exists
   - WorktreeFlow component rendered (check for stage text "Pemesanan")
   - No login prompt visible

   Exercise through: `<WorktreePage />` with mocked token
   Test doubles: mock getStoredToken, mock fetch('/auth/me') returning role
   Expected RED: Page may not render WorktreeFlow yet

2. Run test — verify FAIL

3. Ensure page.tsx correctly renders WorktreeFlow when token exists (may already work from T1)

4. Run test — verify PASS

5. Write integration test for: Sidebar Worktree link has correct href
   Test file: `apps/web/src/components/Sidebar.test.tsx`
   Level: unit (extend existing)

   Test intent:
   Given user login sebagai outlet
   When  sidebar rendered
   Then:
   - "Worktree" link has href="/worktree"

6. Add test to Sidebar.test.tsx, run, verify PASS

7. Run full test suite:
   `cd apps/web && npx jest --runInBand`
   Expected: ALL tests pass

8. Visual/structural verification:
   - Check Sidebar.tsx: Worktree entry position (after Sales)
   - Check WorktreeFlow.tsx: no imports from outside allowed files
   - Check page.tsx: no backend API calls

9. Commit:
   `git add apps/web/src/app/worktree/worktree.test.tsx apps/web/src/components/Sidebar.test.tsx`
   `git commit -m "test(worktree): add integration tests for worktree page and sidebar link"`

## REFERENCES LOADED
- `docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md` — all acceptance criteria (final verification)
- `apps/web/src/components/Sidebar.test.tsx` — existing test pattern
- `apps/web/src/app/worktree/worktree.test.tsx` — existing page test from T1

## WHY THIS APPROACH
Justification: Final integration verification to ensure all pieces work together. Catches cross-component issues that unit tests in T2-T3 miss.
Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: Full test suite must pass — no skipped tests, no focused tests]
You are implementing final integration tests for Worktree feature.
Spec: docs/pocket/spec/2026-05-06-worktree-order-flow/worktree.md
Design decision: Vertical flow + clickable node + slide-in panel
Files in scope: apps/web/src/app/worktree/worktree.test.tsx, apps/web/src/components/Sidebar.test.tsx
Test framework: Jest + @testing-library/react
Available after: T3 (full feature implemented)
Architecture rule: No new dependencies, no out-of-scope file modifications
[RESTATE: Full test suite must pass]

## DELIVERABLE
✓ Given token in localStorage, When WorktreePage rendered, Then WorktreeFlow visible with stage "Pemesanan"
✓ Given no token, When WorktreePage rendered, Then login prompt shown, no WorktreeFlow
✓ Given outlet login, When sidebar rendered, Then Worktree link href="/worktree"
✓ Full test suite (npx jest --runInBand) — ALL tests pass, zero failures

All tests PASS. Commit exists.

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - Page-level integration test for authenticated state
  - Page-level integration test for unauthenticated state
  - Sidebar link href verification
  - Full test suite passes
  - Commit follows conventional commits

Must-not-have:
  - No skipped tests (`it.skip`)
  - No focused tests (`it.only`)
  - No out-of-scope modifications

Open question risks:
  - None remaining (all resolved in pocket-grinding)

## STOP CONDITIONS
Done when: full test suite passes, all DELIVERABLE scenarios verified, commit created
Uncertain when: none
Escalate when: any existing test breaks due to worktree changes

---

## Plan Summary

| Task | Name | Depends | Complexity | Key Verification |
|------|------|---------|------------|-----------------|
| T1 | Sidebar entry + page shell | prereq | lightweight | Worktree visible for all roles + auth guard |
| T2 | WorktreeFlow core component | T1 | standard | 6 nodes + 2 terminal + connectors + StatusBadge |
| T3 | Interactive panel + highlight + deep links | T2 | standard | Panel toggle, access control, role highlight |
| T4 | Integration tests + final verification | T3 | lightweight | Full test suite passes |
