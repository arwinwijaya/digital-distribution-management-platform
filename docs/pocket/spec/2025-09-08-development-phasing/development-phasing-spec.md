# Development Phasing Spec — Digital Distribution Management Platform
Date: 2025-09-08 | Project: Digital Distribution Management Platform | Status: grinding

---

## Problem Statement
Development roadmap saat ini terlalu high-level dan tidak terstruktur untuk solo developer. Perlu breakdown menjadi8 phase yang manageable, each delivering standalone value, dengan dependency yang jelas dan revenue generation yang bisa dimulai lebih awal.

## Root Tension
Solo developer harus menyeimbangkan antara delivering value early (revenue generation) vs building solid foundation. Setiap phase harus cukup kecil untuk manageable oleh1 orang, tapi cukup besar untuk deliver meaningful value.

## Key Constraints
- Solo developer (1 orang) = bandwidth sangat terbatas
- Revenue model kombinasi (commission + subscription)
- Timeline sangat fleksibel = fokus ke kualitas, bukan kecepatan
- Tech stack: Next.js + Laravel/NestJS + PostgreSQL
- Target:500 outlets,100 aktif,5-10 suppliers
- Phase breakdown:8 phases (Phase0-8) dari pitch-exploration
- Phase done: BOTH GWT scenarios AND success criteria required
- Recovery: Case by case (hotfix, halt, atau rollback tergantung severity)
- Technical debt: Clean untuk core, acceptable debt untuk non-core
- Testing: Automated tests hanya untuk critical paths
- Prioritization: Core flow first (register → browse → order → pay)

---

## Scope

**IN-SCOPE:**
- Development phase breakdown (Phase0-8) dengan clear deliverables per phase
- Dependency mapping antara phases dan features
- Acceptance criteria per phase (GWT scenarios + success criteria)
- Revenue generation hooks per phase (stored fields)
- Resource allocation guidance untuk solo developer
- Risk identification dan mitigation per phase
- Phase transition behavior dan overlap rules

**OUT-OF-SCOPE:**
- Detailed technical architecture (database schemas, API design)
- UI/UX design specifications
- Code implementation atau scaffolding
- Third-party integration details (WhatsApp API, payment gateway specifics)
- Marketing atau business development strategy

**ARCHITECTURE CONSTRAINTS:**
- Solo developer = limited bandwidth, phases harus manageable
- Timeline flexible = quality over speed
- Revenue model = combination (commission + subscription)
- Must build upon previous phases tanpa rewrite
- Each phase harus deliver deployable, working software

---

## Stories, Rules, and Examples

### STORY: Development Phase Execution
As a solo developer, I want a structured development phasing plan, so that I can build the platform incrementally without burnout and generate revenue early.

### RULES:

**Rule1: Deployable Phase Delivery**
Each phase harus deliver deployable, working software yang bisa diakses user.

**Rule2: Core Flow Priority**
Core user journey (register → browse → order → pay) harus complete sebelum advanced features (WhatsApp, AI).

**Rule3: Revenue Hook Integration**
Revenue generation hooks harus included di early phases:
- Phase1: Supplier subscription (stored field: subscription_status, subscription_plan)
- Phase2: Commission per transaction (stored field: commission_percentage, calculated from order amount)

**Rule4: Technical Debt Balance**
- Core features (authentication, order creation, payment recording) = production-ready quality
- Non-core features (dashboard reports, analytics) = acceptable technical debt
- Debt harus documented untuk future cleanup

**Rule5: Flexible Phase Overlap**
Phases boleh overlap jika dependencies satisfied. Contoh: Phase4 (Dashboard) bisa mulai saat Phase3 (Payment) belum100% complete.

**Rule6: Critical Path Testing**
Automated tests required hanya untuk critical paths:
- Order creation dan approval
- Payment recording
- Authentication dan authorization
- Other features = manual testing cukup

**Rule7: Phase Done Definition**
Phase dianggap "done" ketika BOTH terpenuhi:
- Semua GWT scenarios untuk phase tersebut pass
- Semua success criteria dari pitch-exploration terpenuhi
- Ada record (deployed artifact, changelog, checklist) yang documents what was delivered

**Rule8: Recovery Behavior**
Ketika deployed phase memperkenalkan regressions:
- Severity tinggi (data corruption, security): Rollback ke prior deployable state, data di-backup sebelum rollback
- Severity medium (broken functionality): Halt development, fix bug dulu
- Severity low (minor issues): Hotfix dalam phase saat ini
- Rollback preserves legitimate data di backup untuk restore nanti

### EXAMPLES:

**Rule1 → Example A:** Phase1 (Outlet Onboarding) delivers outlet registration, product catalog browsing, dan basic search — semua deployable dan functional.

**Rule1 → Example B:** Phase2 (Order Management) delivers order creation, approval, dan tracking — builds upon Phase1 tanpa rewrite.

**Rule2 → Example C:** Core flow (register → browse → order → pay) harus complete by end of Phase3, sebelum advanced features seperti WhatsApp atau AI.

**Rule3 → Example D:** Phase1 menyertakan supplier subscription hook (subscription_status = 'active'/'inactive', subscription_plan = 'basic'/'premium'). Phase2 menyertakan commission hook (commission_percentage = 2%, stored di order).

**Rule4 → Example E:** Payment gateway integration boleh ada technical debt (basic implementation), tapi authentication harus production-ready.

**Rule5 → Example F:** Dashboard development (Phase4) bisa start sementara Phase3 (Payment) masih dalam progress jika dependencies (order entity schema + order status API) sudah stable.

**Rule6 → Example G:** Automated tests untuk order creation dan payment recording (critical paths), manual testing untuk dashboard reports.

**Rule7 → Example H:** Phase1 done ketika: (1) outlet registration GWT pass, (2) product catalog browsing GWT pass, (3)10-20 pilot outlets registered, (4) minimal1 supplier dengan50+ produk.

**Rule8 → Example I:** Jika Phase2 deployment merusak order status API → rollback ke Phase1 deployable state, fix API, redeploy Phase2.

---

## GWT Scenarios

### Scenario1: Phase1 Completion — Outlet Onboarding
Given the platform has no outlets registered
When a new outlet completes registration form with valid data
Then outlet profile is created dan visible di admin dashboard
And outlet bisa browse product catalog dengan harga dan availability
And outlet validation rejects incomplete data (missing phone, address) dengan specific error messages
And duplicate registration by phone number is rejected dengan error message

### Scenario2: Phase2 Completion — Order Transaction
Given an outlet has browsed the product catalog
When outlet selects products dan places an order
Then order is created dengan status "New" dan unique order ID
And admin bisa view dan approve the order
And outlet bisa track order status through completion
And duplicate order submission is prevented (server-generated order ID)

### Scenario3: Phase3 Completion — Payment Recording
Given an order has been delivered
When admin records payment against the order (full atau partial)
Then payment is recorded dan order status updates to "Paid" (atau "Partially Paid" untuk partial payment)
And outlet's outstanding balance is updated accordingly
And payment receipt bisa di-generate dengan detailed data (item list, tax, discounts, payment method)
And receipt generation fails gracefully jika data incomplete
And partial payments create remaining balance yang harus dibayar

### Scenario4: Phase Dependencies — Schema + API Stability
Given Phase2 (Order Management) sedang dalam development
When order entity schema dan order status API sudah stable
Then Phase3 (Payment) bisa mulai depend pada Phase2
And Phase3 payment recording bisa reference order data tanpa breakage
And if Phase2 schema must change after Phase3 started, Phase3 halts until schema stabilizes again

### Scenario5: Phase Flexibility — Overlap Execution
Given Phase2 (Order Management) is80% complete
When Phase3 (Payment) dependencies (schema + API) are satisfied
Then developer bisa begin Phase3 work while completing Phase2
And both phases bisa progress in parallel tanpa conflict

### Scenario6: Core Flow Priority
Given time constraints require feature prioritization
When deciding antara core flow completion dan advanced features
Then core flow (register → browse → order → pay) harus complete first
And advanced features (WhatsApp, AI) bisa deferred ke later phases

### Scenario7: Technical Debt Balance
Given a non-core feature needs implementation
When implementing the feature
Then core features maintain production-ready quality
And non-core features bisa have acceptable technical debt
And debt is documented untuk future cleanup
And debt repayment required when non-core feature becomes critical

### Scenario8: Revenue Hook Integration
Given Phase1 dan Phase2 sedang dalam development
When implementing supplier dan outlet features
Then supplier subscription hook is stored di database (subscription_status, subscription_plan)
And commission per transaction hook is stored di database (commission_percentage, calculated from order amount)
And revenue model is operational by end of Phase2

### Scenario9: Phase Done Definition
Given a phase sedang dalam development
When semua GWT scenarios pass DAN semua success criteria terpenuhi
Then phase is marked as "complete"
And ada record (deployed artifact, changelog, checklist) yang documents what was delivered

### Scenario10: Recovery Behavior — High Severity
Given Phase2 telah deployed dan in use
When bug ditemukan yang causes data corruption di order system
Then developer rolls back ke Phase1 deployable state
And legitimate data (orders, outlets, payments) di-backup sebelum rollback
And bug is fixed sebelum redeploy Phase2
And data bisa di-restore dari backup setelah fix deployed

### Scenario11: Recovery Behavior — Medium Severity
Given Phase2 telah deployed
When bug ditemukan yang prevents order creation
Then developer halts Phase3 development
And fixes the bug di Phase2
And resumes Phase3 setelah fix deployed

### Scenario12: Recovery Behavior — Low Severity
Given Phase2 telah deployed
When minor bug ditemukan (e.g., UI formatting issue)
Then developer hotfixes within current phase development
And continues with Phase3 tanpa halt

---

## Recommended Scenarios (from Edge Case Hunter)

### Scenario13: Technical Debt Escalation
Given a non-core feature was implemented dengan acceptable technical debt
When the feature becomes critical (e.g., dashboard reports needed untuk payment decisions)
Then the debt must be repaid sebelum feature bisa relied upon untuk core flow decisions

### Scenario14: Phase Failure Recovery
Given Phase2 (Order Management) telah deployed dan in use
When bug ditemukan yang prevents order creation
Then developer must hotfix Phase2 sebelum starting Phase3
And Phase3 dependencies on order creation are blocked until fix deployed

### Scenario15: Registration Validation
Given an outlet submits registration form dengan missing required fields (no phone, no address)
Then system rejects registration dengan specific validation errors
And does not create partial outlet profile

### Scenario16: Duplicate Order Prevention
Given an outlet double-clicks "Place Order" button
Then only one order is created, not two duplicate orders

### Scenario17: Phase Completion Record
Given a phase is marked "complete"
Then ada record (deployed artifact, changelog, atau checklist) yang documents what was delivered
Enabling rollback decisions jika next phase surfaces regressions

### Scenario18: Credit Limit Enforcement
Given an outlet has outstanding credit yang exceeds its credit limit (termasuk pending orders)
When outlet attempts to place new order
Then order is blocked dan outlet notified about credit limit
And credit limit validated at order submission time (not admin approval time)
And admin bisa adjust credit limit untuk allow order
And orders dengan status 'New' atau 'Confirmed' dihitung dalam outstanding balance

### Scenario19: Product Catalog Empty State
Given outlet has registered dan supplier has no products loaded
When outlet browses product catalog
Then outlet sees empty state message indicating products are coming soon
And outlet is not blocked from future browsing

### Scenario20: Product Availability During Order
Given outlet has product in cart dan product becomes unavailable before order submission
When outlet submits order
Then system notifies outlet that product is unavailable dan removes it from the order
And outlet can adjust atau cancel the order

### Scenario21: Credit Limit Zero
Given outlet credit limit is set to zero by admin
When outlet attempts to place order
Then order is blocked dan outlet notified that credit limit must be adjusted

### Scenario22: Cumulative Pending Orders Exceeding Credit Limit
Given outlet has pending orders totaling Rp 4M dan credit limit is Rp 5M
When outlet attempts new order for Rp 2M
Then order is blocked because cumulative outstanding would exceed limit

### Scenario23: Schema Evolution During Overlap
Given Phase2 dan Phase3 both modify the order entity
When merge conflict occurs
Then developer resolves conflict before continuing
And Phase3 changes take precedence for new fields
And Phase3 halts if Phase2 schema must change after Phase3 started

---

## Open Questions

### BLOCKING (resolved):
1. ✅ Phase done definition: BOTH GWT scenarios AND success criteria required
2. ✅ Recovery behavior: Case by case (severity-based: rollback, halt, hotfix)
3. ✅ Phase2 dependency: Both order entity schema AND order status API must stable
4. ✅ Phase breakdown:8 phases (Phase0-8) dari pitch-exploration
5. ✅ Payment receipt: Detailed data (item list, tax, discounts, payment method)
6. ✅ Revenue hooks: Stored field (subscription_status, subscription_plan, commission_percentage)
7. ✅ Duplicate registration: Reject by phone number only
8. ✅ Idempotency: Order ID based (server generates unique order ID, no time window)
9. ✅ Credit calculation: Pending orders count in outstanding balance
10. ✅ Credit limit timing: Validate at submission time
11. ✅ Partial payments: Allowed
12. ✅ Schema evolution: Phase3 halts until Phase2 schema stabilizes
13. ✅ Rollback data: Preserve in backup

### NON-BLOCKING (documented as assumptions):
1. What is maximum acceptable technical debt percentage per phase? → Assumption: No specific percentage, qualitative judgment
2. How should phase transitions be documented? → Assumption: Changelog + deployment checklist
3. What automated tests are critical path? → Assumption: Order creation, payment recording, auth
4. How should phase overlap be managed technically? → Assumption: Feature flags atau branches

---

## Design Proposals (Phase5)

### Option A: Sequential Phase Execution
Execute phases sequentially, each fully complete sebelum next begins.
Scenarios satisfied:1,2,3,6,7,8,9,13,15,16,17,19,20,21
Scenarios at risk:4,5,22,23 (no overlap allowed)
Tradeoffs:
+ Simple to manage, clear boundaries
− Longer timeline, no parallel work
Risk: Solo developer burnout dari long sequential execution

### Option B: Flexible Overlap Execution
Allow phases to overlap when dependencies satisfied.
Scenarios satisfied:1,2,3,4,5,6,7,8,9,13,14,15,16,17,18,19,20,21,22,23
Scenarios at risk: None (all scenarios designed for this)
Tradeoffs:
+ Faster delivery, better resource utilization
− More complex dependency management
Risk: Dependency conflicts if not carefully managed

### Option C: Milestone-Based Execution
Define milestones (not phases) dan execute features within milestones.
Scenarios satisfied:1,2,3,6,7,8,9,15,16,17,19,20,21
Scenarios at risk:4,5,22,23 (no clear phase boundaries)
Tradeoffs:
+ More flexible, feature-focused
− Harder to track progress, unclear boundaries
Risk: Scope creep, unclear completion criteria

### Recommendation: Option B (Flexible Overlap Execution)
Reasoning: Solo developer membutuhkan flexibility untuk maximize productivity. Scenarios4,5,14,22,23 didesain untuk approach ini. Dependencies (schema + API stability) memastikan overlap aman. Recovery behavior (Scenario10-12) menangani risks dari overlap. Credit limit enforcement (Scenario18) dan partial payments (Scenario3) memastikan financial integrity. Idempotency (Scenario2,16) dan duplicate prevention (Scenario15) memastikan data integrity.

---

## Architecture Validation

**Checklist:**
- [x] Respects layer boundaries (in-scope only, no architecture leakage)
- [x] Follows existing patterns (greenfield, no patterns to violate)
- [x] No new dependencies that violate constraints
- [x] Build-vs-buy considered (revenue hooks are stored fields, not integrations)
- [x] Rollback strategy defined (Scenario10-12)
- [x] No silent data migrations (greenfield, no existing data)
- [x] Performance characteristics acceptable (no performance requirements in scope)
- [x] No security regressions (authentication is production-ready per Rule4)

**PASS** — All items checked.

---

## Acceptance Criteria

### ACCEPTANCE CRITERIA — Development Phasing
Date: 2025-09-08 | Scope confirmed: yes

**Rule1: Deployable Phase Delivery**
✓ Given phase is in development, When all GWT scenarios pass, Then phase is deployable
✓ Given phase is deployed, When user accesses platform, Then phase features are functional

**Rule2: Core Flow Priority**
✓ Given time constraints, When prioritizing features, Then core flow (register→browse→order→pay) is complete before advanced features
✓ Given Phase3 complete, When checking feature list, Then core flow is operational

**Rule3: Revenue Hook Integration**
✓ Given Phase1 complete, When checking database, Then supplier subscription fields exist (subscription_status, subscription_plan)
✓ Given Phase2 complete, When checking database, Then commission fields exist (commission_percentage)
✗ Given Phase1 complete, When checking database, Then subscription fields missing → BLOCKING

**Rule4: Technical Debt Balance**
✓ Given core feature, When implementing, Then quality is production-ready
✓ Given non-core feature, When implementing, Then acceptable debt allowed
✓ Given debt documented, When feature becomes critical, Then debt is repaid

**Rule5: Flexible Phase Overlap**
✓ Given Phase2 is80% complete, When Phase3 dependencies satisfied, Then Phase3 can start
✗ Given Phase2 is80% complete, When Phase3 dependencies NOT satisfied, Then Phase3 cannot start → BLOCKING

**Rule6: Critical Path Testing**
✓ Given critical path feature (order, payment, auth), When implementing, Then automated tests exist
✓ Given non-critical feature, When implementing, Then manual testing sufficient

**Rule7: Phase Done Definition**
✓ Given phase in development, When GWT scenarios pass AND success criteria met, Then phase is "complete"
✓ Given phase complete, When checking records, Then deployed artifact, changelog, atau checklist exists
✗ Given phase in development, When only GWT scenarios pass BUT success criteria not met, Then phase is NOT complete → BLOCKING

**Rule8: Recovery Behavior**
✓ Given high severity regression, When detected, Then rollback to prior deployable state
✓ Given high severity rollback, When executed, Then legitimate data preserved in backup
✓ Given medium severity regression, When detected, Then halt and fix
✓ Given low severity regression, When detected, Then hotfix in current phase

**Additional Acceptance Criteria (from Edge Case Hunter):**
✓ Given outlet registers with existing phone number, When submitted, Then registration rejected with error message
✓ Given outlet double-clicks Place Order, When submitted, Then only one order created (server-generated ID)
✓ Given outlet has pending orders, When calculating outstanding balance, Then pending orders counted
✓ Given admin changes credit limit, When outlet submits order, Then validated against limit at submission time
✓ Given outlet makes partial payment, When recorded, Then remaining balance tracked
✓ Given Phase2 schema changes after Phase3 started, When detected, Then Phase3 halts until schema stabilizes
✓ Given rollback required, When executed, Then legitimate data preserved in backup for restore

**OPEN QUESTIONS (risks if unresolved):**
- Technical debt percentage per phase → assumed: qualitative judgment
- Phase transition documentation → assumed: changelog + deployment checklist

**OUT-OF-SCOPE (remind pocket-planning):**
- Detailed technical architecture (database schemas, API design)
- UI/UX design specifications
- Code implementation atau scaffolding
- Third-party integration details

---

## Phase Breakdown (Authoritative:8 phases dari pitch-exploration)

### Phase0: Foundation & Infrastructure (Week1-2)
**Goal:** Setup teknis sebelum feature development
**Deliverables:** Project setup, authentication, database schema, API structure, deployment pipeline
**Success Criteria:** Developer bisa login, API accessible, deployment automated

### Phase1: Outlet Onboarding & Product Discovery (Week3-6)
**Goal:** Outlet bisa register dan lihat produk
**Vertical Slice:** Outlet Registration → View Product Catalog
**Success Criteria:**10-20 pilot outlets registered,1 supplier dengan50+ produk
**Revenue Hook:** Supplier subscription (subscription_status, subscription_plan)

### Phase2: Order Management & Transaction (Week7-10)
**Goal:** Outlet bisa order dan transaksi tercatat
**Vertical Slice:** Outlet Order → Admin Confirmation → Order Tracking
**Success Criteria:**50+ orders processed, order flow end-to-end lancar
**Revenue Hook:** Commission per transaction (commission_percentage)

### Phase3: Payment & Credit Management (Week11-14)
**Goal:** Pembayaran tercatat dan credit management jalan
**Vertical Slice:** Payment Recording → Credit Limit → Outstanding Tracking
**Success Criteria:** Payment recording for all orders, credit management prevents over-limit
**Dependencies:** Phase2 order entity schema + order status API must stable

### Phase4: Dashboard & Analytics (Week15-17)
**Goal:** Business visibility untuk owner/admin
**Vertical Slice:** Key Metrics Dashboard → Sales Reports → Outlet Performance
**Success Criteria:** Owner bisa monitor business health, reports actionable
**Overlap:** Bisa mulai saat Phase3 dependencies satisfied

### Phase5: Sales Force & Delivery (Week18-22)
**Goal:** Sales team dan delivery operations manageable
**Vertical Slice:** Sales Visit Planning → Delivery Assignment → Proof of Delivery
**Success Criteria:** Sales productivity terukur, delivery operations ter-automasi

### Phase6: WhatsApp Integration (Week23-27)
**Goal:** Outlet bisa berinteraksi via WhatsApp
**Vertical Slice:** WhatsApp Order → Notification → Catalog Sharing
**Success Criteria:** WhatsApp adoption >50%, order via WhatsApp lancar
**Risk:** WhatsApp Business API approval lead time panjang

### Phase7: AI & Intelligence (Week28-33)
**Goal:** Data-driven insights dan recommendations
**Vertical Slice:** Product Recommendation → Sales Forecasting → Outlet Segmentation
**Success Criteria:** Recommendation acceptance >10%, forecast accuracy >70%

### Phase8: Polish & Scale (Week34+)
**Goal:** Production-ready dengan fitur advanced
**Features:** Performance optimization, advanced BI, mobile PWA, marketplace, dynamic pricing
**Success Criteria:**500+ outlets,100+ active,5-10 suppliers, sustainable revenue
