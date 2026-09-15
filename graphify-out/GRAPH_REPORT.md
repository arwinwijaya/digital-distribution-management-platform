# Graph Report - digital-distribution-management-platform  (2026-09-15)

## Corpus Check
- 313 files · ~212,895 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3098 nodes · 6132 edges · 237 communities (134 shown, 46 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 134 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Pre-Pilot & Forecast Ops
- Product Catalog
- Invoice Domain
- Admin Orders & Delivery
- Order Status History
- User & Auth
- Order Lifecycle
- Data Intelligence Surfaces
- WhatsApp & Concurrency
- Composer & Plugins
- Order & Territory APIs
- Payments
- Analytics & Finance Metrics
- Measurement & Correlation
- Outlet & Canonicalization
- Exception Handling
- Operational Readiness Plan
- WhatsApp Contracts
- Legacy Controllers
- Invoice Test Factories
- Operations Web Page
- Data Intelligence Types
- Data Pipeline Models
- AI Controllers
- WhatsApp Retry & Delivery
- Admin Order Surfaces
- Analytics Legacy
- Dashboard
- Phase 3 Spec
- Pre-Pilot Contracts
- Phase 2 Reviews
- Operational Readiness Reviews
- Phase 3 Plan
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 143
- Community 144
- Community 145
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 154
- Community 155
- Community 156
- Community 157
- Community 158
- Community 184
- Community 185
- Community 186
- Community 187
- Community 219
- Community 220
- Community 221
- Community 222
- Community 223
- Community 224
- Community 225
- Community 226
- Community 227
- Community 228
- Community 229
- Community 230
- Community 231
- Community 232
- Community 233
- Community 234
- Community 235

## God Nodes (most connected - your core abstractions)
1. `User` - 207 edges
2. `Outlet` - 164 edges
3. `Order` - 162 edges
4. `Invoice` - 84 edges
5. `InvoiceReminder` - 56 edges
6. `Product` - 50 edges
7. `Payment` - 47 edges
8. `DataPipelineService` - 45 edges
9. `apiUrl()` - 43 edges
10. `authHeaders()` - 39 edges

## Surprising Connections (you probably didn't know these)
- `DataPipelineService` --conceptually_related_to--> `Atomic Publication`  [INFERRED]
  apps/api/app/Services/DataPipelineService.php → docs/pocket/spec/2026-09-14-phase3-data-intelligence-foundation/phase3-data-intelligence-foundation.md
- `T1: Define Shared Data Intelligence Schema and API Contracts` --creates--> `RecommendationEvent Model`  [EXTRACTED]
  docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan/tasks/T1-define-shared-data-intelligence-schema-and-api-contracts.md → apps/api/app/Models/RecommendationEvent.php
- `T1: Define Shared Data Intelligence Schema and API Contracts` --creates--> `Territory Model`  [EXTRACTED]
  docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan/tasks/T1-define-shared-data-intelligence-schema-and-api-contracts.md → apps/api/app/Models/Territory.php
- `T1: Define Shared Data Intelligence Schema and API Contracts` --creates--> `Data Intelligence Shared Types (TypeScript)`  [EXTRACTED]
  docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan/tasks/T1-define-shared-data-intelligence-schema-and-api-contracts.md → apps/web/src/lib/data-intelligence-types.ts
- `T3: Add Scheduler, Admin Trigger, Status and Overlap Prevention` --creates--> `DataPipelineController`  [EXTRACTED]
  docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan/tasks/T3-add-scheduler-admin-trigger-status-and-overlap-prevention.md → apps/api/app/Http/Controllers/DataPipelineController.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core transaction flow register browse order pay** — docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t2_outlet_onboarding_product_discovery_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t3_order_management_transaction_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t4_payment_credit_management_task, docs_pocket_spec_2025_09_08_development_phasing_development_phasing_spec_core_flow_priority [EXTRACTED 0.85]
- **Local Run Stack Docker API Web DB Redis** — docs_panduan_setup_docker_compose, docs_panduan_setup_env_lengkap, docs_panduan_setup_migrasi_seed, docs_panduan_setup_testing [INFERRED 0.85]
- **Core Transaction Flow: Outlet Order to Payment** — concept_order_to_payment, concept_credit_limit_enforcement, concept_order_status_audit_trail, concept_delivery_state_machine, c_invoice_lifecycle [INFERRED 0.85]
- **Operational Readiness Documentation Set** — docs_pilot_pre_pilot_compatibility_baseline, docs_pilot_pre_pilot_compatibility_matrix, docs_pilot_pre_pilot_gate_checklist, docs_pilot_pre_pilot_runbook [EXTRACTED 1.00]
- **Operational Readiness Execution Plan Phases** — docs_pocket_plans_2026_09_10_operational_readiness_execution_plan_phase_1, docs_pocket_plans_2026_09_10_operational_readiness_execution_plan_phase_2, docs_pocket_plans_2026_09_10_operational_readiness_execution_plan_phase_3 [EXTRACTED 1.00]

## Communities (237 total, 46 thin omitted)

### Community 0 - "Pre-Pilot & Forecast Ops"
Cohesion: 0.05
Nodes (14): App\Models\WhatsAppMessage, ForecastService, Carbon, Carbon, OperationalIssueService, Carbon, RecommendationService, SegmentationService (+6 more)

### Community 1 - "Product Catalog"
Cohesion: 0.06
Nodes (6): Product, AITest, AnalyticsTest, MarketplaceTest, OrderTest, ProductTest

### Community 2 - "Invoice Domain"
Cohesion: 0.06
Nodes (11): Invoice, InvoiceReminder, InvoiceReminderCandidateSelector, Carbon, InvoiceReminderClaimService, InvoiceReminderService, InvoiceReminderStateService, InvoiceReminderSuppressionMetricsTest (+3 more)

### Community 3 - "Admin Orders & Delivery"
Cohesion: 0.08
Nodes (39): Proof, Visit, allowedRoles(), authenticate(), LoginForm(), LoginFormProps, roleLabels, useLoginForm() (+31 more)

### Community 4 - "Order Status History"
Cohesion: 0.10
Nodes (15): App\Models\DeliveryStatusHistory, App\Models\OrderItem, App\Models\OrderStatusHistory, App\Models\Product, App\Services\ReceiptService, Supplier, DataIntelligenceSchemaTest, PaymentTermTest (+7 more)

### Community 5 - "User & Auth"
Cohesion: 0.05
Nodes (9): User, AuthTest, DeliveryAuthorizationTest, DeliveryTest, FinanceAccessTest, Phase1IntegrationTest, SalesTest, DeliveryTestFixtures (+1 more)

### Community 6 - "Order Lifecycle"
Cohesion: 0.08
Nodes (6): InvoiceTest, TestResponse, PrePilotCompatibilityTest, InvoiceConcurrencyHarness, Product, OrderStatusHistory

### Community 7 - "Data Intelligence Surfaces"
Cohesion: 0.07
Nodes (39): DataIntelligencePage(), DataIntelligenceSnapshot, GeoMap, useAdminGuard(), DEFAULT_CENTER, GeoMap(), isValidPoint(), Props (+31 more)

### Community 8 - "WhatsApp & Concurrency"
Cohesion: 0.07
Nodes (15): App\Services\WhatsAppService, App\Support\ConcurrencyTestBarrier, Order, InvoiceBackfillService, ScaleFixtureSeeder, InvoiceReminderFeatureSetup, Illuminate\Database\Eloquent\Collection, Illuminate\Database\Seeder (+7 more)

### Community 9 - "Composer & Plugins"
Cohesion: 0.04
Nodes (46): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+38 more)

### Community 10 - "Order & Territory APIs"
Cohesion: 0.06
Nodes (12): TerritoryController, AssignTerritoryRequest, CancelOrderRequest, RegisterOutletRequest, StoreOrderRequest, StoreOutletRequest, StoreTerritoryRequest, UpdateDeliveryStatusRequest (+4 more)

### Community 11 - "Payments"
Cohesion: 0.08
Nodes (7): Payment, PaymentService, ReceiptService, InvoicePaymentFixture, InvoicePaymentHistoryTest, InvoicePaymentTest, ReceiptService

### Community 12 - "Analytics & Finance Metrics"
Cohesion: 0.07
Nodes (11): App\Services\AnalyticsService, FinanceMetricsController, FinanceRoleController, InvoiceReminderController, PaymentController, SetFinanceRoleRequest, StorePaymentRequest, FinanceAuthorizationService (+3 more)

### Community 13 - "Measurement & Correlation"
Cohesion: 0.09
Nodes (14): AttachCorrelationId, DenyFinanceAdministration, PrePilotGate, RedirectIfAuthenticated, TrustProxies, OperationalEventService, PrePilotFeatureGate, Closure (+6 more)

### Community 14 - "Outlet & Canonicalization"
Cohesion: 0.08
Nodes (6): Outlet, RecommendationEvent, InvoiceBackfillTest, MeasurementTest, PaymentConcurrencyTest, Product

### Community 15 - "Exception Handling"
Cohesion: 0.09
Nodes (9): Handler, OrderCreationService, ValidationException, WhatsAppPayloadParser, WhatsAppSenderResolver, WhatsAppService, CreditLimitService, Illuminate\Foundation\Exceptions\Handler (+1 more)

### Community 16 - "Operational Readiness Plan"
Cohesion: 0.06
Nodes (36): Constraints Reminder, DELIVERABLE, Execution Overview, EXECUTION PLAN — Operational Readiness Slice 1, File Structure Map, OBJECTIVE, Parallelizable Groups, Phases (+28 more)

### Community 17 - "WhatsApp Contracts"
Cohesion: 0.08
Nodes (11): WhatsAppClient, Authenticate, AppServiceProvider, AuthServiceProvider, AuthService, WhatsAppHttpClient, Illuminate\Support\Facades\Auth, Illuminate\Support\Facades\Http (+3 more)

### Community 18 - "Legacy Controllers"
Cohesion: 0.09
Nodes (14): App\Http\Controllers\AIController, App\Http\Controllers\AnalyticsController, App\Http\Controllers\AuthController, App\Http\Controllers\CreditLimitController, App\Http\Controllers\DeliveryController, App\Http\Controllers\MarketplaceController, App\Http\Controllers\ProductController, App\Http\Controllers\SalesController (+6 more)

### Community 19 - "Invoice Test Factories"
Cohesion: 0.09
Nodes (11): InvoiceFactory, static, InvoiceReminderFactory, static, OutletFactory, ProductFactory, static, SupplierFactory (+3 more)

### Community 20 - "Operations Web Page"
Cohesion: 0.10
Nodes (26): checkBadgeVariant(), IssuesTable(), OpsState, readinessBadgeVariant(), ReadinessCard(), SEVERITY_OPTIONS, severityBadgeVariant(), SOURCE_OPTIONS (+18 more)

### Community 21 - "Data Intelligence Types"
Cohesion: 0.08
Nodes (29): DataIntelligenceMetadata, DataPipelineRun, DataQualityStatus, DataSnapshot, FunnelMetrics, MapPoint, PipelineRunStatus, RecommendationEventPayload (+21 more)

### Community 22 - "Data Pipeline Models"
Cohesion: 0.13
Nodes (7): DataMetricDefinition, DataPipelineRun, DataSnapshot, DataSnapshotValue, Closure, DataPipelineServiceTest, DataPipelineTriggerTest

### Community 23 - "AI Controllers"
Cohesion: 0.12
Nodes (7): AIController, AuthController, Controller, MarketplaceController, ProductController, WhatsAppController, Illuminate\Http\JsonResponse

### Community 24 - "WhatsApp Retry & Delivery"
Cohesion: 0.08
Nodes (3): RoleAssignmentAudit, WhatsAppMessage, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 25 - "Admin Order Surfaces"
Cohesion: 0.16
Nodes (28): AdminOrdersPage(), approve(), showOrder(), AIData, AnalyticsPage(), DataSufficiency, Measurement, DeliveryPage() (+20 more)

### Community 26 - "Analytics Legacy"
Cohesion: 0.17
Nodes (5): AnalyticsController, AnalyticsService, InvoiceMetricsService, Carbon\CarbonInterface, Illuminate\Database\Eloquent\Builder

### Community 27 - "Dashboard"
Cohesion: 0.12
Nodes (22): AnalyticsContent(), DashboardData, DashboardLoader, DashboardPage(), DashboardState, FinanceMetricCards(), FinanceMetrics, Group (+14 more)

### Community 28 - "Phase 3 Spec"
Cohesion: 0.08
Nodes (25): Acceptance Criteria, Architecture Constraints, Context, Current State, Dependencies, Design Decision, Existing (to leverage), Implementation Notes (+17 more)

### Community 29 - "Pre-Pilot Contracts"
Cohesion: 0.10
Nodes (25): Compatibility Baseline, Correlation ID, Idempotency Payload Fingerprint, Operational Event Journal, Kill Switch, Readiness Diagnostics, READY_FOR_PILOT, Feature Flag and Kill Switch (+17 more)

### Community 30 - "Phase 2 Reviews"
Cohesion: 0.08
Nodes (24): acceptance_and_deliverables, not_fully_verified, status, verified, concerns, corrections, cross_task_issues, loop_info (+16 more)

### Community 31 - "Operational Readiness Reviews"
Cohesion: 0.08
Nodes (24): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+16 more)

### Community 32 - "Phase 3 Plan"
Cohesion: 0.08
Nodes (24): Constraints Reminder, DELIVERABLE, Execution Overview, EXECUTION PLAN — Phase 3 Data Intelligence & AI Foundation, File Structure Map, OBJECTIVE, Parallelizable Groups, Plan Summary (+16 more)

### Community 33 - "Community 33"
Cohesion: 0.09
Nodes (15): forecastMeasurementResponse, geographicResponse, mockGetStoredToken, recommendationMeasurementResponse, stockResponse, supplierResponse, disabledResponse, issueDetailResponse (+7 more)

### Community 34 - "Community 34"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 35 - "Community 35"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 36 - "Community 36"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 37 - "Community 37"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 38 - "Community 38"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 39 - "Community 39"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 40 - "Community 40"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 41 - "Community 41"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 42 - "Community 42"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 43 - "Community 43"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 44 - "Community 44"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 45 - "Community 45"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 46 - "Community 46"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 47 - "Community 47"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 48 - "Community 48"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 49 - "Community 49"
Cohesion: 0.08
Nodes (23): Acceptance Criteria, Architecture Constraints, Context, Current State, Dependencies, Design Decision, Existing (to leverage), Implementation Notes (+15 more)

### Community 50 - "Community 50"
Cohesion: 0.13
Nodes (5): SalesController, StoreSalesVisitRequest, SalesVisit, CalendarService, Illuminate\Validation\Validator

### Community 51 - "Community 51"
Cohesion: 0.15
Nodes (5): CreditLimit, OrderItem, OrderStatusHistory, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model

### Community 52 - "Community 52"
Cohesion: 0.17
Nodes (5): DeliveryController, StoreDeliveryRequest, Delivery, RoutingService, ConcurrencyTestBarrier

### Community 53 - "Community 53"
Cohesion: 0.19
Nodes (3): PostgresConcurrencyFeatureCase, InvoiceConcurrencyHttp, RuntimeException

### Community 54 - "Community 54"
Cohesion: 0.09
Nodes (21): name, private, version, autoprefixer, axios, @babel/core, @babel/preset-env, @babel/preset-react (+13 more)

### Community 55 - "Community 55"
Cohesion: 0.15
Nodes (22): T9 Load Benchmark 100 Concurrent Requests, Scale Fixture Seeder 500 Outlets, SLO p95 and Max Under 2s, Phase 2 Review Continuation, Execution Flow Index, Phase 1 Foundation Infrastructure T1 T2 T3, Phase 2 Payment Credit Management T4-T9, Execution Order T1 to T9 DAG (+14 more)

### Community 56 - "Community 56"
Cohesion: 0.13
Nodes (8): InvoiceReminderHistoryTest, InvoiceReminderPostgresConcurrencyTest, InvoiceReminderRetryTest, InvoiceReminderTest, InvoiceReminderHistoryScenarios, InvoiceReminderPostgresScenarios, InvoiceReminderRetryScenarios, Illuminate\Foundation\Testing\DatabaseMigrations

### Community 57 - "Community 57"
Cohesion: 0.16
Nodes (3): PaymentConcurrencyHarness, PaymentConcurrencyHttp, PDO

### Community 58 - "Community 58"
Cohesion: 0.18
Nodes (4): App\Models\Delivery, OperationalReadinessFixtures, Delivery, Illuminate\Testing\TestResponse

### Community 59 - "Community 59"
Cohesion: 0.13
Nodes (8): MeasurementService, Carbon, Collection, RecommendationEvent Model, Forecast WAPE Measurement, Insufficient Data Status, Recommendation Funnel Measurement, Sparse Data Fallback (Recent-Average)

### Community 61 - "Community 61"
Cohesion: 0.11
Nodes (18): compilerOptions, allowJs, esModuleInterop, incremental, isolatedModules, jsx, lib, module (+10 more)

### Community 62 - "Community 62"
Cohesion: 0.16
Nodes (10): Operational Readiness Execution Index, Operational Readiness Phase 1, Operational Readiness Slice 1 — Create operational data foundation and shared domain records (Phase 1 of 3), Phase Completion Gate, Task List, Operational Readiness Phase 2, Operational Readiness Phase 3, Operational Readiness Slice 1 — Implement idempotent WhatsApp invoice reminders and scheduler (Phase 3 of 3) (+2 more)

### Community 63 - "Community 63"
Cohesion: 0.16
Nodes (3): OperationalReadinessController, ListOperationalIssuesRequest, OperationalReadinessService

### Community 64 - "Community 64"
Cohesion: 0.20
Nodes (5): Product, StockPlanningService, Product, StockPlanningTest, Stock Planning and Replenishment

### Community 65 - "Community 65"
Cohesion: 0.20
Nodes (4): SupplierPerformanceService, Product, SupplierPerformanceTest, Supplier Performance BI

### Community 66 - "Community 66"
Cohesion: 0.17
Nodes (11): RunDataPipeline Artisan Command, Overlap Prevention (Single Active Run Guard), Phase 1: Shared Schema and Pipeline Foundation, Phase 2: Territory and Geographic/Supplier/Stock BI, Phase 3 Data Intelligence & AI Foundation — Implement territory management and geographic BI (Phase 2 of 3), Phase Completion Gate, Task List, T3: Add Scheduler, Admin Trigger, Status and Overlap Prevention (+3 more)

### Community 67 - "Community 67"
Cohesion: 0.11
Nodes (17): Advisor Synthesis, Approach Directions, Brainstorming Methods Used, Constraint Mapping — deep, Direction A: Operational Readiness, Direction B: Sales Execution First, Direction C: Data & Intelligence Foundation, First Principles Thinking — creative (+9 more)

### Community 68 - "Community 68"
Cohesion: 0.21
Nodes (4): App\Http\Requests\StoreOrderRequest, InvoiceController, OrderController, InvoiceService

### Community 72 - "Community 72"
Cohesion: 0.12
Nodes (17): devDependencies, @babel/core, @babel/preset-env, @babel/preset-react, @babel/preset-typescript, eslint, eslint-config-next, jest (+9 more)

### Community 73 - "Community 73"
Cohesion: 0.12
Nodes (11): Invoice, invoiceColumns, InvoiceState, InvoiceSummary(), money(), PageMeta, Props, StatCard() (+3 more)

### Community 74 - "Community 74"
Cohesion: 0.15
Nodes (13): createPaymentIdempotencyKey(), CreditSummary, money(), PageMeta, Payment, paymentColumns, PaymentSession, PaymentsPage() (+5 more)

### Community 75 - "Community 75"
Cohesion: 0.16
Nodes (11): AuthChangeDetail, createRoleSynchronizer(), fetchCurrentRole(), NAV_ITEMS, NavItem, Sidebar(), SidebarAuth, useSidebarAuth() (+3 more)

### Community 76 - "Community 76"
Cohesion: 0.14
Nodes (17): Commission Snapshot, Concurrency Barrier, Core Transaction Flow, Credit Limit Enforcement, Data Intelligence Pipeline, Multi-Supplier Marketplace, Order Status Audit Trail, Order to Payment Flow (+9 more)

### Community 77 - "Community 77"
Cohesion: 0.12
Nodes (16): devDependencies, jest, ts-jest, @types/jest, typescript, jest, main, name (+8 more)

### Community 78 - "Community 78"
Cohesion: 0.21
Nodes (4): GeographicAnalyticsService, Territory Model, GeographicAnalyticsTest, Geographic BI

### Community 79 - "Community 79"
Cohesion: 0.12
Nodes (15): compilerOptions, alwaysStrict, declaration, esModuleInterop, lib, module, noImplicitAny, noImplicitThis (+7 more)

### Community 80 - "Community 80"
Cohesion: 0.21
Nodes (10): DataPipelineService, DataMetricDefinition Model, DataPipelineRun Model, DataSnapshot Model, DataSnapshotValue Model, Immutable Snapshot, Atomic Publication, T1: Define Shared Data Intelligence Schema and API Contracts (+2 more)

### Community 81 - "Community 81"
Cohesion: 0.20
Nodes (3): CreditLimitController, SetCreditLimitRequest, CreditLimitService

### Community 84 - "Community 84"
Cohesion: 0.17
Nodes (7): { execFileSync, spawn, spawnSync }, fs, net, os, path, wait(), waitForServer()

### Community 85 - "Community 85"
Cohesion: 0.15
Nodes (12): ApiResponse, AuthError, DashboardMetrics, LoginRequest, LoginResponse, Order, OrderItem, OrderStatus (+4 more)

### Community 86 - "Community 86"
Cohesion: 0.17
Nodes (3): Illuminate\Database\Eloquent\Relations\HasOne, Illuminate\Notifications\Notifiable, Tymon\JWTAuth\Contracts\JWTSubject

### Community 88 - "Community 88"
Cohesion: 0.22
Nodes (3): App\Http\Requests\StoreOutletRequest, OutletController, SetPaymentTermRequest

### Community 89 - "Community 89"
Cohesion: 0.20
Nodes (8): Data Intelligence Integration Test, Phase 3: AI Fallback, Frontend, and Integration Verification, Phase 3 Data Intelligence & AI Foundation — Add sparse AI fallback and recommendation/forecast measurement (Phase 3 of 3), Phase Completion Gate, Task List, T6: Implement Stock Planning and Replenishment, T7: Add Sparse AI Fallback and Recommendation/Forecast Measurement, T9: Verify Pipeline Publication Consumed Atomically Across BI Units

### Community 91 - "Community 91"
Cohesion: 0.18
Nodes (11): dependencies, autoprefixer, axios, leaflet, next, postcss, react, react-dom (+3 more)

### Community 92 - "Community 92"
Cohesion: 0.18
Nodes (11): Approval Idempotency, Legacy Invoice Backfill, Invoice Lifecycle, Payment Lifecycle, Delivery Proof, WhatsApp Payment Reminders, T3 invoice lifecycle, T5 legacy backfill (+3 more)

### Community 93 - "Community 93"
Cohesion: 0.18
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 6: Implement idempotent WhatsApp invoice reminders and scheduler [depends: T4] [test-risk] (+2 more)

### Community 98 - "Community 98"
Cohesion: 0.33
Nodes (10): Data Intelligence Admin Page, GeoMap Component, MeasurementCards Component, StockPlanningTable Component, SupplierPerformanceTable Component, TerritoryTable Component, Data Intelligence API Client, Data Intelligence Shared Types (TypeScript) (+2 more)

### Community 99 - "Community 99"
Cohesion: 0.20
Nodes (10): AI recommendation, Forecasting, Outlet intelligence, Overall status, Phase 0 — Business Validation & Planning, Phase 3 — Data Intelligence & AI Capability, Prioritas lanjutan yang disarankan, Recommended technical architecture (+2 more)

### Community 100 - "Community 100"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 10: Verify the complete operational readiness slice end to end [depends: T5, T6, T7, T8, T9] [test-risk] (+2 more)

### Community 101 - "Community 101"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 2: Add finance role, request-time authorization, and payment terms [depends: T1] (+2 more)

### Community 102 - "Community 102"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 3: Implement invoice lifecycle and cancellation [depends: T1, T2] (+2 more)

### Community 103 - "Community 103"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 4: Complete payment lifecycle and bounded payment history [depends: T3] (+2 more)

### Community 104 - "Community 104"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 5: Add idempotent legacy invoice backfill [depends: T3] (+2 more)

### Community 105 - "Community 105"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 7: Add finance invoice/payment metrics API [depends: T4, T6] (+2 more)

### Community 106 - "Community 106"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 8: Enforce delivery proof at the API boundary [prereq] (+2 more)

### Community 107 - "Community 107"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 9: Add finance invoice/payment/metrics web surfaces [depends: T2, T3, T4, T7, T8] (+2 more)

### Community 108 - "Community 108"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 1: Define shared data-intelligence schema and API contracts [prereq] (+2 more)

### Community 109 - "Community 109"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 2: Implement staged pipeline and atomic immutable publication [depends: T1] [test-risk] (+2 more)

### Community 110 - "Community 110"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 3: Add scheduler, admin trigger, status and overlap prevention [depends: T2] [test-risk] (+2 more)

### Community 111 - "Community 111"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 4: Implement territory management and geographic BI [depends: T3] [test-risk] (+2 more)

### Community 112 - "Community 112"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 5: Implement supplier performance BI with coverage [depends: T4] [test-risk] (+2 more)

### Community 113 - "Community 113"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 6: Implement stock planning and replenishment [depends: T5] [test-risk] (+2 more)

### Community 114 - "Community 114"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 7: Add sparse AI fallback and recommendation/forecast measurement [depends: T6] [test-risk] (+2 more)

### Community 115 - "Community 115"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 8: Build Next.js admin data-intelligence surfaces and Leaflet map [depends: T7] [test-risk] (+2 more)

### Community 116 - "Community 116"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 9: Verify pipeline publication is consumed atomically across BI units [depends: T7] [test-risk] (+2 more)

### Community 117 - "Community 117"
Cohesion: 0.28
Nodes (4): BackfillInvoices, ProcessInvoiceReminders, RunDataPipeline, Illuminate\Console\Command

### Community 118 - "Community 118"
Cohesion: 0.28
Nodes (5): TelescopeServiceProvider, Illuminate\Support\Facades\Gate, Laravel\Telescope\IncomingEntry, Laravel\Telescope\Telescope, Laravel\Telescope\TelescopeApplicationServiceProvider

### Community 119 - "Community 119"
Cohesion: 0.22
Nodes (9): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task T1 — Create operational data foundation and shared domain records (+1 more)

### Community 123 - "Community 123"
Cohesion: 0.25
Nodes (8): scripts, build, dev, lint, start, test, test:e2e, test:watch

### Community 124 - "Community 124"
Cohesion: 0.29
Nodes (8): Delivery Proof Enforcement, Finance Metrics, WhatsApp Invoice Reminders, T4 payment lifecycle, T6 whatsapp reminders, T7 finance metrics api, T8 delivery proof, T9 finance web surfaces

### Community 125 - "Community 125"
Cohesion: 0.32
Nodes (8): Finance Role, Payment Terms, Stale Token Invalidation, Task T1: Create Operational Data Foundation, Task T10: Verify Complete Operational Readiness E2E, Task T2: Add Finance Role and Payment Terms, Invoice Per-Order Uniqueness Rationale, Request-Time Authorization Rationale

### Community 126 - "Community 126"
Cohesion: 0.25
Nodes (7): Carried Forward, Closeout — 2026-09-14-phase3-data-intelligence-foundation, Phase 1 — execution-plan/phase-1.md  (DONE), Phase 2 — execution-plan/phase-2.md  (DONE), Phase 3 — execution-plan/phase-3.md  (DONE), Phases, Skipped Tasks

### Community 127 - "Community 127"
Cohesion: 0.25
Nodes (7): devDependencies, typescript, name, private, version, workspaces, typescript

### Community 128 - "Community 128"
Cohesion: 0.25
Nodes (8): scripts, api:artisan, build, dev, docker:down, docker:up, lint, test

### Community 130 - "Community 130"
Cohesion: 0.29
Nodes (5): RouteServiceProvider, Illuminate\Cache\RateLimiting\Limit, Illuminate\Foundation\Support\Providers\RouteServiceProvider, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\Facades\Route

### Community 132 - "Community 132"
Cohesion: 0.29
Nodes (5): Props, sizes, variants, Modal(), Props

### Community 133 - "Community 133"
Cohesion: 0.33
Nodes (6): Delivery State Machine, Development Roadmap, Development Phasing Closeout, Operational Readiness Closeout, Operational Readiness Execution Plan, Proof-Gated Delivery Rationale

### Community 134 - "Community 134"
Cohesion: 0.38
Nodes (7): Budget Idempotency, Compatibility Contract, Lock Ordering, Pre-Pilot Compatibility Baseline, Pre-Pilot Compatibility Matrix, Additive-Only Migration Rationale, Payment Ledger Preservation Rationale

### Community 135 - "Community 135"
Cohesion: 0.29
Nodes (7): Carried Forward, Closeout — 2026-09-10-operational-readiness, Phase 1 — execution-plan/phase-1.md  (DONE), Phase 2 — execution-plan/phase-2.md  (DONE), Phase 3 — execution-plan/phase-3.md  (DONE), Phases, Skipped Tasks

### Community 136 - "Community 136"
Cohesion: 0.24
Nodes (7): Execution Flow, Phase 3 Data Intelligence & AI Foundation — Execution Index, Phase Summary, Task Index, Phase 3 Data Intelligence & AI Foundation — Define shared data-intelligence schema and API contracts (Phase 1 of 3), Phase Completion Gate, Task List

### Community 137 - "Community 137"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 139 - "Community 139"
Cohesion: 0.33
Nodes (4): inter, metadata, AppShell(), next

### Community 140 - "Community 140"
Cohesion: 0.33
Nodes (6): Basic analytics, Order management, Outlet management, Phase 1 — MVP Platform Development, Product catalog, User management

### Community 141 - "Community 141"
Cohesion: 0.33
Nodes (6): Carried Forward, Closeout — 2025-09-08-development-phasing, Phase 1 — execution-plan/phase-1.md  (DONE), Phase 2 — execution-plan/phase-2.md  (DONE), Phases, Skipped Tasks

### Community 142 - "Community 142"
Cohesion: 0.70
Nodes (4): down(), replacePostgresRoleConstraint(), replaceRoleConstraint(), up()

### Community 143 - "Community 143"
Cohesion: 0.60
Nodes (4): createImmutabilityGuards(), down(), dropImmutabilityGuards(), up()

### Community 145 - "Community 145"
Cohesion: 0.40
Nodes (5): Data Pipeline, Phase3 closeout, Phase3 execution index, Phase3 execution plan, One Laravel orchestrator immutable snapshot

### Community 146 - "Community 146"
Cohesion: 0.40
Nodes (5): Delivery, Payment, Phase 2 — Sales & Distribution Automation, Sales force, WhatsApp

### Community 147 - "Community 147"
Cohesion: 0.40
Nodes (5): Dynamic pricing, Financial services, Marketplace, Phase 4 — Ecosystem Expansion, Predictive supply chain

### Community 148 - "Community 148"
Cohesion: 0.40
Nodes (4): Answer, Outcome, Q: Map the architecture for operational readiness planning across order approval, payment transactions, finance role authorization, delivery proof, WhatsApp scheduling, and analytics dashboard, Source Nodes

### Community 150 - "Community 150"
Cohesion: 0.83
Nodes (3): down(), getConnection(), up()

### Community 151 - "Community 151"
Cohesion: 0.50
Nodes (4): Flexible Overlap Execution Decision, Flexible Overlap Execution Rule, Hybrid Vertical Slice Plus Pilot Direction, Digital Distribution Network Owner Value Proposition

### Community 152 - "Community 152"
Cohesion: 0.50
Nodes (4): Execution Flow, Operational Readiness Slice 1 — Execution Index, Phase Summary, Task Index

### Community 186 - "Community 186"
Cohesion: 0.67
Nodes (3): Operational Readiness Slice 1 — Implement invoice lifecycle and cancellation (Phase 2 of 3), Phase Completion Gate, Task List

## Ambiguous Edges - Review These
- `T9 Load Benchmark 100 Concurrent Requests` → `Phase 2 Review Continuation`  [AMBIGUOUS]
  docs/pocket/plans/2025-09-08-development-phasing/CONTINUE-PROMPT.md · relation: conceptually_related_to

## Knowledge Gaps
- **1001 isolated node(s):** `ApiResponse`, `AuthError`, `DashboardMetrics`, `LoginRequest`, `LoginResponse` (+996 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1391 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **46 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `T9 Load Benchmark 100 Concurrent Requests` and `Phase 2 Review Continuation`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `Delivery` connect `Community 58` to `Community 65`, `Admin Orders & Delivery`?**
  _High betweenness centrality (0.149) - this node is a cross-community bridge._
- **Why does `User` connect `User & Auth` to `Pre-Pilot & Forecast Ops`, `Community 129`, `Product Catalog`, `Invoice Domain`, `Order Status History`, `Order Lifecycle`, `WhatsApp & Concurrency`, `Community 138`, `Payments`, `Analytics & Finance Metrics`, `Outlet & Canonicalization`, `WhatsApp Contracts`, `Invoice Test Factories`, `Data Pipeline Models`, `AI Controllers`, `WhatsApp Retry & Delivery`, `Community 50`, `Community 51`, `Community 52`, `Community 53`, `Community 58`, `Community 59`, `Community 60`, `Community 64`, `Community 65`, `Community 69`, `Community 70`, `Community 71`, `Community 78`, `Community 82`, `Community 86`, `Community 87`, `Community 90`, `Community 97`, `Community 118`, `Community 122`?**
  _High betweenness centrality (0.103) - this node is a cross-community bridge._
- **Why does `Order` connect `WhatsApp & Concurrency` to `Pre-Pilot & Forecast Ops`, `Product Catalog`, `Invoice Domain`, `Order Status History`, `User & Auth`, `Order Lifecycle`, `Payments`, `Analytics & Finance Metrics`, `Outlet & Canonicalization`, `Exception Handling`, `AI Controllers`, `WhatsApp Retry & Delivery`, `Analytics Legacy`, `Community 51`, `Community 52`, `Community 58`, `Community 60`, `Community 64`, `Community 65`, `Community 68`, `Community 69`, `Community 70`, `Community 71`, `Community 78`, `Community 81`, `Community 82`, `Community 83`, `Community 86`, `Community 97`?**
  _High betweenness centrality (0.097) - this node is a cross-community bridge._
- **What connects `ApiResponse`, `AuthError`, `DashboardMetrics` to the rest of the system?**
  _1001 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Pre-Pilot & Forecast Ops` be split into smaller, more focused modules?**
  _Cohesion score 0.054414414414414414 - nodes in this community are weakly interconnected._
- **Should `Product Catalog` be split into smaller, more focused modules?**
  _Cohesion score 0.05997778600518327 - nodes in this community are weakly interconnected._