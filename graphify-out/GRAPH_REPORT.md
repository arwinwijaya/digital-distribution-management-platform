# Graph Report - digital-distribution-management-platform  (2026-09-16)

## Corpus Check
- 21 files · ~231,603 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3199 nodes · 6318 edges · 252 communities (142 shown, 53 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 156 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Delivery Concurrency Tests
- Order Invoice Core Models
- Product WhatsApp Models
- Pilot Services Instrumentation
- Order Status Stock Planning
- Data Pipeline Intelligence
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 29
- Community 30
- Community 31
- Community 32
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
- Community 159
- Community 160
- Community 161
- Community 162
- Community 163
- Community 164
- Community 165
- Community 166
- Community 167
- Community 168
- Community 169
- Community 195
- Community 196
- Community 197
- Community 198
- Community 199
- Community 200
- Community 201
- Community 233
- Community 234
- Community 235
- Community 236
- Community 237
- Community 238
- Community 239
- Community 240
- Community 241
- Community 242
- Community 243
- Community 244
- Community 245
- Community 246
- Community 247
- Community 248
- Community 249
- Community 250

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
- `DataPipelineService` --conceptually_related_to--> `Immutable Snapshot`  [INFERRED]
  apps/api/app/Services/DataPipelineService.php → docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan.md
- `T4: Implement Territory Management and Geographic BI` --creates--> `TerritoryController`  [EXTRACTED]
  docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan/tasks/T4-implement-territory-management-and-geographic-bi.md → apps/api/app/Http/Controllers/TerritoryController.php
- `T7: Add Sparse AI Fallback and Recommendation/Forecast Measurement` --creates--> `MeasurementController`  [EXTRACTED]
  docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan/tasks/T7-add-sparse-ai-fallback-and-recommendation-forecast-measurement.md → apps/api/app/Http/Controllers/MeasurementController.php
- `T3: Add Scheduler, Admin Trigger, Status and Overlap Prevention` --creates--> `DataPipelineController`  [EXTRACTED]
  docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/execution-plan/tasks/T3-add-scheduler-admin-trigger-status-and-overlap-prevention.md → apps/api/app/Http/Controllers/DataPipelineController.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core transaction flow register browse order pay** — docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t2_outlet_onboarding_product_discovery_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t3_order_management_transaction_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t4_payment_credit_management_task, docs_pocket_spec_2025_09_08_development_phasing_development_phasing_spec_core_flow_priority [EXTRACTED 0.85]
- **Operational Readiness Execution Plan Phases** — docs_pocket_plans_2026_09_10_operational_readiness_execution_plan_phase_1, docs_pocket_plans_2026_09_10_operational_readiness_execution_plan_phase_2, docs_pocket_plans_2026_09_10_operational_readiness_execution_plan_phase_3 [EXTRACTED 1.00]
- **Operational Readiness Documentation Set** — docs_pilot_pre_pilot_compatibility_baseline, docs_pilot_pre_pilot_compatibility_matrix, docs_pilot_pre_pilot_gate_checklist, docs_pilot_pre_pilot_runbook [EXTRACTED 1.00]
- **Core Transaction Flow: Outlet Order to Payment** — concept_order_to_payment, concept_credit_limit_enforcement, concept_order_status_audit_trail, concept_delivery_state_machine, c_invoice_lifecycle [INFERRED 0.85]
- **Local Run Stack Docker API Web DB Redis** — docs_panduan_setup_docker_compose, docs_panduan_setup_env_lengkap, docs_panduan_setup_migrasi_seed, docs_panduan_setup_testing [INFERRED 0.85]
- **Concierge pilot T1 qualification, T2 metrics, T3 evaluation pipeline** — docs_pocket_spec_2026_09_15_concierge_production_pilot_concierge_pilot_spec_pilot_qualification_service, docs_pocket_spec_2026_09_15_concierge_production_pilot_concierge_pilot_spec_pilot_metrics_service, docs_pocket_spec_2026_09_15_concierge_production_pilot_concierge_pilot_spec_pilot_evaluation_service [EXTRACTED 1.00]
- **Pre-pilot T1-T6 compatibility baseline to runbook gate** — docs_pocket_plans_2026_09_14_business_validation_production_pilot_closeout_pre_pilot_closeout, checklist_pre_pilot_readiness_wall, development_roadmap_phase6_technical_done [EXTRACTED 1.00]

## Communities (252 total, 53 thin omitted)

### Community 0 - "Delivery Concurrency Tests"
Cohesion: 0.05
Nodes (10): DeliveryConcurrencyTest, InvoiceReminderPostgresConcurrencyTest, Product, PrePilotConcurrencyCompatibilityTest, InvoiceReminderPostgresScenarios, PostgresConcurrencyFeatureCase, LoadTest, InvoiceConcurrencyHttp (+2 more)

### Community 1 - "Order Invoice Core Models"
Cohesion: 0.06
Nodes (17): App\Services\ReceiptService, App\Support\ConcurrencyTestBarrier, Invoice, Order, Payment, InvoiceBackfillService, ReceiptService, PaymentConcurrencyTest (+9 more)

### Community 2 - "Product WhatsApp Models"
Cohesion: 0.06
Nodes (7): Product, ValidationException, WhatsAppPayloadParser, AITest, MarketplaceTest, OrderTest, ProductTest

### Community 3 - "Pilot Services Instrumentation"
Cohesion: 0.06
Nodes (19): App\Contracts\WhatsAppClient, App\Models\Invoice, App\Models\OperationalEvent, App\Models\Order, App\Models\Outlet, App\Models\Territory, App\Models\User, PilotMetricsService (+11 more)

### Community 4 - "Order Status Stock Planning"
Cohesion: 0.09
Nodes (17): App\Models\DeliveryStatusHistory, App\Models\OrderItem, App\Models\OrderStatusHistory, App\Models\Product, Supplier, PaymentTermTest, Phase1IntegrationTest, InvoiceReminderFeatureCase (+9 more)

### Community 5 - "Data Pipeline Intelligence"
Cohesion: 0.10
Nodes (11): DataMetricDefinition, DataPipelineRun, DataSnapshot, DataSnapshotValue, DataPipelineService, Carbon, DataIntelligenceIntegrationTest, DataIntelligenceSchemaTest (+3 more)

### Community 6 - "Community 6"
Cohesion: 0.05
Nodes (9): User, AuthTest, DeliveryAuthorizationTest, DeliveryTest, FinanceAccessTest, InvoiceReminderSuppressionMetricsTest, SalesTest, DeliveryTestFixtures (+1 more)

### Community 7 - "Community 7"
Cohesion: 0.08
Nodes (7): InvoiceTest, TestResponse, PrePilotCompatibilityTest, InvoiceConcurrencyHarness, Product, OrderStatusHistory, PDO

### Community 8 - "Community 8"
Cohesion: 0.06
Nodes (10): Outlet, GeographicAnalyticsService, ScaleFixtureSeeder, InvoicePaymentFixture, GeographicAnalyticsTest, InvoiceBackfillTest, InvoicePaymentHistoryTest, InvoiceReminderFeatureSetup (+2 more)

### Community 9 - "Community 9"
Cohesion: 0.05
Nodes (12): AssignTerritoryRequest, CancelOrderRequest, RegisterOutletRequest, StoreDeliveryRequest, StoreOrderRequest, StoreOutletRequest, StoreTerritoryRequest, UpdateDeliveryStatusRequest (+4 more)

### Community 10 - "Community 10"
Cohesion: 0.04
Nodes (46): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+38 more)

### Community 11 - "Community 11"
Cohesion: 0.07
Nodes (11): App\Services\AnalyticsService, Handler, FinanceRoleController, InvoiceReminderController, PaymentController, SetFinanceRoleRequest, StorePaymentRequest, FinanceAuthorizationService (+3 more)

### Community 12 - "Community 12"
Cohesion: 0.10
Nodes (13): AttachCorrelationId, DenyFinanceAdministration, PrePilotGate, RedirectIfAuthenticated, TrustProxies, OperationalEventService, PrePilotFeatureGate, Closure (+5 more)

### Community 13 - "Community 13"
Cohesion: 0.10
Nodes (8): AuthController, Controller, MarketplaceController, MeasurementController, ProductController, TerritoryController, WhatsAppController, Illuminate\Http\JsonResponse

### Community 14 - "Community 14"
Cohesion: 0.09
Nodes (16): App\Http\Controllers\AIController, App\Http\Controllers\AnalyticsController, App\Http\Controllers\AuthController, App\Http\Controllers\CreditLimitController, App\Http\Controllers\DeliveryController, App\Http\Controllers\MarketplaceController, App\Http\Controllers\ProductController, App\Http\Controllers\SalesController (+8 more)

### Community 15 - "Community 15"
Cohesion: 0.11
Nodes (22): Proof, SalesPage(), Visit, Order, Product, Button(), Props, sizes (+14 more)

### Community 16 - "Community 16"
Cohesion: 0.06
Nodes (33): AI recommendation, Basic analytics, Delivery, Dynamic pricing, Financial services, Forecasting, Marketplace, Order management (+25 more)

### Community 17 - "Community 17"
Cohesion: 0.09
Nodes (11): InvoiceFactory, static, InvoiceReminderFactory, static, OutletFactory, ProductFactory, static, SupplierFactory (+3 more)

### Community 18 - "Community 18"
Cohesion: 0.10
Nodes (27): checkBadgeVariant(), IssuesTable(), OpsState, readinessBadgeVariant(), ReadinessCard(), SEVERITY_OPTIONS, severityBadgeVariant(), SOURCE_OPTIONS (+19 more)

### Community 19 - "Community 19"
Cohesion: 0.08
Nodes (29): DataIntelligenceMetadata, DataPipelineRun, DataQualityStatus, DataSnapshot, FunnelMetrics, MapPoint, PipelineRunStatus, RecommendationEventPayload (+21 more)

### Community 20 - "Community 20"
Cohesion: 0.16
Nodes (5): AnalyticsController, AnalyticsService, InvoiceMetricsService, Carbon\CarbonInterface, Illuminate\Database\Eloquent\Builder

### Community 21 - "Community 21"
Cohesion: 0.09
Nodes (15): AuthChangeDetail, createRoleSynchronizer(), fetchCurrentRole(), NAV_ITEMS, NavItem, Sidebar(), SidebarAuth, useSidebarAuth() (+7 more)

### Community 22 - "Community 22"
Cohesion: 0.08
Nodes (3): DeliveryStatusHistory, OrderItem, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 23 - "Community 23"
Cohesion: 0.12
Nodes (22): AnalyticsContent(), DashboardData, DashboardLoader, DashboardPage(), DashboardState, FinanceMetricCards(), FinanceMetrics, Group (+14 more)

### Community 24 - "Community 24"
Cohesion: 0.18
Nodes (25): AdminOrdersPage(), approve(), showOrder(), AIData, AnalyticsPage(), DataSufficiency, Measurement, DeliveryPage() (+17 more)

### Community 25 - "Community 25"
Cohesion: 0.08
Nodes (25): Acceptance Criteria, Architecture Constraints, Context, Current State, Dependencies, Design Decision, Existing (to leverage), Implementation Notes (+17 more)

### Community 26 - "Community 26"
Cohesion: 0.10
Nodes (25): Compatibility Baseline, Correlation ID, Idempotency Payload Fingerprint, Operational Event Journal, Kill Switch, Readiness Diagnostics, READY_FOR_PILOT, Feature Flag and Kill Switch (+17 more)

### Community 27 - "Community 27"
Cohesion: 0.08
Nodes (24): acceptance_and_deliverables, not_fully_verified, status, verified, concerns, corrections, cross_task_issues, loop_info (+16 more)

### Community 28 - "Community 28"
Cohesion: 0.08
Nodes (24): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+16 more)

### Community 29 - "Community 29"
Cohesion: 0.08
Nodes (24): Constraints Reminder, DELIVERABLE, Execution Overview, EXECUTION PLAN — Phase 3 Data Intelligence & AI Foundation, File Structure Map, OBJECTIVE, Parallelizable Groups, Plan Summary (+16 more)

### Community 30 - "Community 30"
Cohesion: 0.16
Nodes (20): DataIntelligencePage(), DataIntelligenceSnapshot, useAdminGuard(), adminFetch(), ApiResponse, buildFunnelEventPayload(), fetchForecastMeasurementData(), fetchGeographicData() (+12 more)

### Community 31 - "Community 31"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 32 - "Community 32"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 33 - "Community 33"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

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
Nodes (23): Acceptance Criteria, Architecture Constraints, Context, Current State, Dependencies, Design Decision, Existing (to leverage), Implementation Notes (+15 more)

### Community 47 - "Community 47"
Cohesion: 0.18
Nodes (4): OrderStatusHistory, Territory, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model

### Community 48 - "Community 48"
Cohesion: 0.13
Nodes (16): RunDataPipeline Artisan Command, Overlap Prevention (Single Active Run Guard), Phase 1: Shared Schema and Pipeline Foundation, Phase 3 Data Intelligence & AI Foundation — Define shared data-intelligence schema and API contracts (Phase 1 of 3), Phase Completion Gate, Task List, Phase 2: Territory and Geographic/Supplier/Stock BI, Phase 3 Data Intelligence & AI Foundation — Implement territory management and geographic BI (Phase 2 of 3) (+8 more)

### Community 49 - "Community 49"
Cohesion: 0.09
Nodes (21): name, private, version, autoprefixer, axios, @babel/core, @babel/preset-env, @babel/preset-react (+13 more)

### Community 50 - "Community 50"
Cohesion: 0.15
Nodes (22): T9 Load Benchmark 100 Concurrent Requests, Scale Fixture Seeder 500 Outlets, SLO p95 and Max Under 2s, Phase 2 Review Continuation, Execution Flow Index, Phase 1 Foundation Infrastructure T1 T2 T3, Phase 2 Payment Credit Management T4-T9, Execution Order T1 to T9 DAG (+14 more)

### Community 51 - "Community 51"
Cohesion: 0.19
Nodes (4): AIController, ForecastService, Carbon, RecommendationService

### Community 52 - "Community 52"
Cohesion: 0.14
Nodes (4): WhatsAppMessage, WhatsAppSenderResolver, WhatsAppService, ConcurrencyTestBarrier

### Community 54 - "Community 54"
Cohesion: 0.14
Nodes (13): OrdersPage(), allowedRoles(), authenticate(), LoginForm(), LoginFormProps, roleLabels, useLoginForm(), submit() (+5 more)

### Community 55 - "Community 55"
Cohesion: 0.20
Nodes (5): App\Http\Requests\StoreOrderRequest, App\Services\WhatsAppService, InvoiceController, OrderController, InvoiceService

### Community 56 - "Community 56"
Cohesion: 0.15
Nodes (3): RecommendationEvent, MeasurementTest, Illuminate\Support\Str

### Community 57 - "Community 57"
Cohesion: 0.19
Nodes (5): Product, StockPlanningService, Product, StockPlanningTest, Stock Planning and Replenishment

### Community 58 - "Community 58"
Cohesion: 0.11
Nodes (17): Data Intelligence Integration Test, Phase 3: AI Fallback, Frontend, and Integration Verification, Phase 3 Data Intelligence & AI Foundation — Add sparse AI fallback and recommendation/forecast measurement (Phase 3 of 3), Phase Completion Gate, Task List, T7: Add Sparse AI Fallback and Recommendation/Forecast Measurement, DELIVERABLE, OBJECTIVE (+9 more)

### Community 59 - "Community 59"
Cohesion: 0.11
Nodes (18): compilerOptions, allowJs, esModuleInterop, incremental, isolatedModules, jsx, lib, module (+10 more)

### Community 60 - "Community 60"
Cohesion: 0.17
Nodes (4): CreditLimitController, SetCreditLimitRequest, CreditLimit, CreditLimitService

### Community 61 - "Community 61"
Cohesion: 0.16
Nodes (3): OperationalReadinessController, ListOperationalIssuesRequest, OperationalReadinessService

### Community 62 - "Community 62"
Cohesion: 0.17
Nodes (4): SalesController, StoreSalesVisitRequest, SalesVisit, CalendarService

### Community 64 - "Community 64"
Cohesion: 0.20
Nodes (4): SupplierPerformanceService, Product, SupplierPerformanceTest, Supplier Performance BI

### Community 65 - "Community 65"
Cohesion: 0.11
Nodes (17): Advisor Synthesis, Approach Directions, Brainstorming Methods Used, Constraint Mapping — deep, Direction A: Operational Readiness, Direction B: Sales Execution First, Direction C: Data & Intelligence Foundation, First Principles Thinking — creative (+9 more)

### Community 66 - "Community 66"
Cohesion: 0.18
Nodes (3): App\Models\Delivery, OperationalReadinessFixtures, Delivery

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (7): MeasurementService, Carbon, Collection, Forecast WAPE Measurement, Insufficient Data Status, Recommendation Funnel Measurement, Sparse Data Fallback (Recent-Average)

### Community 68 - "Community 68"
Cohesion: 0.17
Nodes (17): DataMetricDefinition Model, DataPipelineRun Model, DataSnapshot Model, DataSnapshotValue Model, RecommendationEvent Model, Territory Model, Data Intelligence Admin Page, GeoMap Component (+9 more)

### Community 70 - "Community 70"
Cohesion: 0.12
Nodes (17): devDependencies, @babel/core, @babel/preset-env, @babel/preset-react, @babel/preset-typescript, eslint, eslint-config-next, jest (+9 more)

### Community 71 - "Community 71"
Cohesion: 0.12
Nodes (13): forecastMeasurementResponse, geographicResponse, mockGetStoredToken, recommendationMeasurementResponse, stockResponse, supplierResponse, disabledResponse, issueDetailResponse (+5 more)

### Community 72 - "Community 72"
Cohesion: 0.15
Nodes (13): createPaymentIdempotencyKey(), CreditSummary, money(), PageMeta, Payment, paymentColumns, PaymentSession, PaymentsPage() (+5 more)

### Community 73 - "Community 73"
Cohesion: 0.14
Nodes (17): Commission Snapshot, Concurrency Barrier, Core Transaction Flow, Credit Limit Enforcement, Data Intelligence Pipeline, Multi-Supplier Marketplace, Order Status Audit Trail, Order to Payment Flow (+9 more)

### Community 74 - "Community 74"
Cohesion: 0.12
Nodes (16): devDependencies, jest, ts-jest, @types/jest, typescript, jest, main, name (+8 more)

### Community 75 - "Community 75"
Cohesion: 0.27
Nodes (3): DeliveryController, Delivery, RoutingService

### Community 76 - "Community 76"
Cohesion: 0.19
Nodes (10): MarketplaceCatalog(), Product, Supplier, Product, ProductCatalog(), Badge(), Props, styles (+2 more)

### Community 77 - "Community 77"
Cohesion: 0.20
Nodes (7): Operational Readiness Execution Index, Operational Readiness Phase 1, Operational Readiness Phase 2, Operational Readiness Phase 3, Operational Readiness Slice 1 — Implement idempotent WhatsApp invoice reminders and scheduler (Phase 3 of 3), Phase Completion Gate, Task List

### Community 78 - "Community 78"
Cohesion: 0.12
Nodes (15): compilerOptions, alwaysStrict, declaration, esModuleInterop, lib, module, noImplicitAny, noImplicitThis (+7 more)

### Community 83 - "Community 83"
Cohesion: 0.17
Nodes (9): GeoMap, DEFAULT_CENTER, GeoMap(), isValidPoint(), Props, POINTS_WITH_INVALID, VALID_POINTS, GeographicMapPoint (+1 more)

### Community 84 - "Community 84"
Cohesion: 0.15
Nodes (15): Approval Idempotency, Legacy Invoice Backfill, Delivery Proof Enforcement, Finance Metrics, Invoice Lifecycle, Payment Lifecycle, WhatsApp Invoice Reminders, T3 invoice lifecycle (+7 more)

### Community 85 - "Community 85"
Cohesion: 0.20
Nodes (3): WhatsAppClient, WhatsAppHttpClient, Illuminate\Support\Facades\Http

### Community 86 - "Community 86"
Cohesion: 0.19
Nodes (3): InvoiceReminder, InvoiceReminderRetryTest, InvoiceReminderRetryScenarios

### Community 88 - "Community 88"
Cohesion: 0.19
Nodes (3): OperationalEvent, PrePilotControlsTest, Illuminate\Contracts\Pagination\LengthAwarePaginator

### Community 91 - "Community 91"
Cohesion: 0.17
Nodes (7): { execFileSync, spawn, spawnSync }, fs, net, os, path, wait(), waitForServer()

### Community 92 - "Community 92"
Cohesion: 0.17
Nodes (8): Invoice, invoiceColumns, InvoiceState, InvoiceSummary(), money(), PageMeta, Props, StatCard()

### Community 93 - "Community 93"
Cohesion: 0.15
Nodes (12): ApiResponse, AuthError, DashboardMetrics, LoginRequest, LoginResponse, Order, OrderItem, OrderStatus (+4 more)

### Community 96 - "Community 96"
Cohesion: 0.21
Nodes (4): InvoiceReminderHistoryTest, InvoiceReminderTest, InvoiceReminderHistoryScenarios, Illuminate\Foundation\Testing\DatabaseMigrations

### Community 97 - "Community 97"
Cohesion: 0.20
Nodes (12): Delivery Proof, Finance Role, Payment Terms, Stale Token Invalidation, WhatsApp Payment Reminders, Task T1: Create Operational Data Foundation, Task T10: Verify Complete Operational Readiness E2E, Task T2: Add Finance Role and Payment Terms (+4 more)

### Community 98 - "Community 98"
Cohesion: 0.22
Nodes (3): App\Http\Requests\StoreOutletRequest, OutletController, SetPaymentTermRequest

### Community 99 - "Community 99"
Cohesion: 0.20
Nodes (4): Authenticate, AuthService, Tymon\JWTAuth\Exceptions\JWTException, Tymon\JWTAuth\Facades\JWTAuth

### Community 100 - "Community 100"
Cohesion: 0.18
Nodes (3): Illuminate\Database\Eloquent\Relations\HasOne, Illuminate\Notifications\Notifiable, Tymon\JWTAuth\Contracts\JWTSubject

### Community 101 - "Community 101"
Cohesion: 0.18
Nodes (11): dependencies, autoprefixer, axios, leaflet, next, postcss, react, react-dom (+3 more)

### Community 102 - "Community 102"
Cohesion: 0.18
Nodes (11): Delivery State Machine, Operational Readiness Closeout, Carried Forward, Closeout — 2026-09-10-operational-readiness, Phase 1 — execution-plan/phase-1.md  (DONE), Phase 2 — execution-plan/phase-2.md  (DONE), Phase 3 — execution-plan/phase-3.md  (DONE), Phases (+3 more)

### Community 103 - "Community 103"
Cohesion: 0.18
Nodes (11): File Structure Map, Rule: Data foundation and shared operational records, Rule: Delivery proof, Rule: Finance metrics, Rule: Finance role, authorization, and outlet payment terms, Rule: Finance web surfaces, Rule: Full operational integration, Rule: Invoice lifecycle and cancellation (+3 more)

### Community 104 - "Community 104"
Cohesion: 0.18
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 10: Verify the complete operational readiness slice end to end [depends: T5, T6, T7, T8, T9] [test-risk] (+2 more)

### Community 105 - "Community 105"
Cohesion: 0.18
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 5: Implement supplier performance BI with coverage [depends: T4] [test-risk] (+2 more)

### Community 110 - "Community 110"
Cohesion: 0.20
Nodes (10): DELIVERABLE, EXECUTION PLAN — Operational Readiness Slice 1, OBJECTIVE, Plan Summary, Pocket Packets, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT (+2 more)

### Community 111 - "Community 111"
Cohesion: 0.20
Nodes (10): STOP CONDITIONS, Task 10: Verify the complete operational readiness slice end to end [depends: T5, T6, T7, T8, T9] [test-risk], Task 2: Add finance role, request-time authorization, and payment terms [depends: T1], Task 3: Implement invoice lifecycle and cancellation [depends: T1, T2], Task 4: Complete payment lifecycle and bounded payment history [depends: T3], Task 5: Add idempotent legacy invoice backfill [depends: T3], Task 6: Implement idempotent WhatsApp invoice reminders and scheduler [depends: T4] [test-risk], Task 7: Add finance invoice/payment metrics API [depends: T4, T6] (+2 more)

### Community 112 - "Community 112"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 2: Add finance role, request-time authorization, and payment terms [depends: T1] (+2 more)

### Community 113 - "Community 113"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 3: Implement invoice lifecycle and cancellation [depends: T1, T2] (+2 more)

### Community 114 - "Community 114"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 4: Complete payment lifecycle and bounded payment history [depends: T3] (+2 more)

### Community 115 - "Community 115"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 5: Add idempotent legacy invoice backfill [depends: T3] (+2 more)

### Community 116 - "Community 116"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 6: Implement idempotent WhatsApp invoice reminders and scheduler [depends: T4] [test-risk] (+2 more)

### Community 117 - "Community 117"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 7: Add finance invoice/payment metrics API [depends: T4, T6] (+2 more)

### Community 118 - "Community 118"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 8: Enforce delivery proof at the API boundary [prereq] (+2 more)

### Community 119 - "Community 119"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 9: Add finance invoice/payment/metrics web surfaces [depends: T2, T3, T4, T7, T8] (+2 more)

### Community 120 - "Community 120"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 1: Define shared data-intelligence schema and API contracts [prereq] (+2 more)

### Community 121 - "Community 121"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 2: Implement staged pipeline and atomic immutable publication [depends: T1] [test-risk] (+2 more)

### Community 122 - "Community 122"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 3: Add scheduler, admin trigger, status and overlap prevention [depends: T2] [test-risk] (+2 more)

### Community 123 - "Community 123"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 4: Implement territory management and geographic BI [depends: T3] [test-risk] (+2 more)

### Community 124 - "Community 124"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 6: Implement stock planning and replenishment [depends: T5] [test-risk] (+2 more)

### Community 125 - "Community 125"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 8: Build Next.js admin data-intelligence surfaces and Leaflet map [depends: T7] [test-risk] (+2 more)

### Community 126 - "Community 126"
Cohesion: 0.20
Nodes (10): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task 9: Verify pipeline publication is consumed atomically across BI units [depends: T7] [test-risk] (+2 more)

### Community 127 - "Community 127"
Cohesion: 0.28
Nodes (4): BackfillInvoices, ProcessInvoiceReminders, RunDataPipeline, Illuminate\Console\Command

### Community 129 - "Community 129"
Cohesion: 0.28
Nodes (4): AppServiceProvider, AuthServiceProvider, Illuminate\Support\Facades\Auth, Illuminate\Support\ServiceProvider

### Community 130 - "Community 130"
Cohesion: 0.28
Nodes (5): TelescopeServiceProvider, Illuminate\Support\Facades\Gate, Laravel\Telescope\IncomingEntry, Laravel\Telescope\Telescope, Laravel\Telescope\TelescopeApplicationServiceProvider

### Community 132 - "Community 132"
Cohesion: 0.22
Nodes (9): DELIVERABLE, OBJECTIVE, Pocket Packet, QUALITY BAR, REFERENCES LOADED, SANDWICH CONTEXT, STOP CONDITIONS, Task T1 — Create operational data foundation and shared domain records (+1 more)

### Community 133 - "Community 133"
Cohesion: 0.25
Nodes (9): T1 commit 71b6b15 PilotQualificationService plus PilotQualificationTest, T2 commit 184ea92 PilotMetricsService plus PilotWorkflowTest plus fixtures plus OperationalEventService helpers, T3 commit 6598ce0 PilotEvaluationService plus PilotEvaluationTest, Decision matrix SCALE_UP ITERATE STOP with phase7Evidence, KPI guardrails error below 5 percent delivery above 95 percent payment above 90 percent speed delta minus 30 percent 20 valid orders, Option A Pilot Minimum Viable chosen over WhatsApp Bridge and Extended 4 week because parsing risk and partner fatigue, PilotEvaluationService evaluate decision kpiSummary guardrailsMet phase7Evidence lessonsLearned rootCauseAnalysis recommendations SCALE_UP ITERATE STOP MIN_VALID_ORDERS 20, PilotMetricsService measureLifecycleTiming calculateSpeedDelta target -30 percent calculateReliability countValidOrders evaluateVolume plus 3d extend (+1 more)

### Community 137 - "Community 137"
Cohesion: 0.25
Nodes (8): scripts, build, dev, lint, start, test, test:e2e, test:watch

### Community 138 - "Community 138"
Cohesion: 0.25
Nodes (8): Data Pipeline, Immutable Snapshot, Atomic Publication, Phase3 closeout, Phase3 execution index, Phase3 execution plan, One Laravel orchestrator immutable snapshot, Orchestrated Immutable Snapshot Design Decision

### Community 139 - "Community 139"
Cohesion: 0.25
Nodes (7): Carried Forward, Closeout — 2026-09-14-phase3-data-intelligence-foundation, Phase 1 — execution-plan/phase-1.md  (DONE), Phase 2 — execution-plan/phase-2.md  (DONE), Phase 3 — execution-plan/phase-3.md  (DONE), Phases, Skipped Tasks

### Community 140 - "Community 140"
Cohesion: 0.25
Nodes (7): devDependencies, typescript, name, private, version, workspaces, typescript

### Community 141 - "Community 141"
Cohesion: 0.25
Nodes (8): scripts, api:artisan, build, dev, docker:down, docker:up, lint, test

### Community 142 - "Community 142"
Cohesion: 0.29
Nodes (5): RouteServiceProvider, Illuminate\Cache\RateLimiting\Limit, Illuminate\Foundation\Support\Providers\RouteServiceProvider, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\Facades\Route

### Community 144 - "Community 144"
Cohesion: 0.38
Nodes (7): Budget Idempotency, Compatibility Contract, Lock Ordering, Pre-Pilot Compatibility Baseline, Pre-Pilot Compatibility Matrix, Additive-Only Migration Rationale, Payment Ledger Preservation Rationale

### Community 145 - "Community 145"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 149 - "Community 149"
Cohesion: 0.33
Nodes (4): inter, metadata, AppShell(), next

### Community 150 - "Community 150"
Cohesion: 0.70
Nodes (4): down(), replacePostgresRoleConstraint(), replaceRoleConstraint(), up()

### Community 151 - "Community 151"
Cohesion: 0.60
Nodes (4): createImmutabilityGuards(), down(), dropImmutabilityGuards(), up()

### Community 152 - "Community 152"
Cohesion: 0.40
Nodes (4): ForecastSnapshot, FunnelStep, MeasurementCards(), MeasurementCardsProps

### Community 153 - "Community 153"
Cohesion: 0.70
Nodes (5): 24 Pilot tests PASS 6 qualification 14 workflow 4 evaluation, Roadmap Implementation Checklist audit 2026-09-14, Development Roadmap Digital Distribution Management Platform, Business Validation Pre-Pilot closeout T1-T6 DONE, Concierge Production Pilot closeout T1-T3 DONE

### Community 154 - "Community 154"
Cohesion: 0.40
Nodes (5): Constraints Reminder, Execution Overview, Parallelizable Groups, Phases, Recommended Order

### Community 155 - "Community 155"
Cohesion: 0.40
Nodes (4): Answer, Outcome, Q: Map the architecture for operational readiness planning across order approval, payment transactions, finance role authorization, delivery proof, WhatsApp scheduling, and analytics dashboard, Source Nodes

### Community 157 - "Community 157"
Cohesion: 0.83
Nodes (3): down(), getConnection(), up()

### Community 158 - "Community 158"
Cohesion: 0.50
Nodes (3): Props, StockItem, StockPlanningTable()

### Community 159 - "Community 159"
Cohesion: 0.67
Nodes (3): Props, SupplierPerformanceTable(), SupplierRecord

### Community 160 - "Community 160"
Cohesion: 0.67
Nodes (3): Props, TerritoryTable(), GeographicTableRow

### Community 161 - "Community 161"
Cohesion: 0.50
Nodes (4): Flexible Overlap Execution Decision, Flexible Overlap Execution Rule, Hybrid Vertical Slice Plus Pilot Direction, Digital Distribution Network Owner Value Proposition

### Community 162 - "Community 162"
Cohesion: 0.50
Nodes (4): Execution Flow, Operational Readiness Slice 1 — Execution Index, Phase Summary, Task Index

### Community 163 - "Community 163"
Cohesion: 0.50
Nodes (4): Execution Flow, Phase 3 Data Intelligence & AI Foundation — Execution Index, Phase Summary, Task Index

### Community 197 - "Community 197"
Cohesion: 0.67
Nodes (3): Outstanding AWS deployment observability plus real field pilot with live partner orders, Phase 6 technical DONE Phase 3 Done field validation deferred, Phase 7 evidence documentation executiveSummary kpiDetail recommendations dataCollection

### Community 198 - "Community 198"
Cohesion: 0.67
Nodes (3): Operational Readiness Slice 1 — Create operational data foundation and shared domain records (Phase 1 of 3), Phase Completion Gate, Task List

### Community 199 - "Community 199"
Cohesion: 0.67
Nodes (3): Operational Readiness Slice 1 — Implement invoice lifecycle and cancellation (Phase 2 of 3), Phase Completion Gate, Task List

### Community 200 - "Community 200"
Cohesion: 0.67
Nodes (3): Concierge Production Pilot execution plan T1-T3, Concierge Production Pilot execution index, Concierge Production Pilot Phase 6 spec

## Ambiguous Edges - Review These
- `T9 Load Benchmark 100 Concurrent Requests` → `Phase 2 Review Continuation`  [AMBIGUOUS]
  docs/pocket/plans/2025-09-08-development-phasing/CONTINUE-PROMPT.md · relation: conceptually_related_to

## Knowledge Gaps
- **1011 isolated node(s):** `Props`, `Props`, `Props`, `OpsState`, `FetchResult` (+1006 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1416 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **53 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `T9 Load Benchmark 100 Concurrent Requests` and `Phase 2 Review Continuation`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `Delivery` connect `Community 66` to `Community 64`, `Community 15`?**
  _High betweenness centrality (0.153) - this node is a cross-community bridge._
- **Why does `User` connect `Community 6` to `Community 128`, `Order Invoice Core Models`, `Community 130`, `Product WhatsApp Models`, `Order Status Stock Planning`, `Data Pipeline Intelligence`, `Delivery Concurrency Tests`, `Community 7`, `Community 8`, `Community 9`, `Community 11`, `Community 13`, `Community 143`, `Community 17`, `Community 147`, `Community 22`, `Community 47`, `Community 53`, `Community 56`, `Community 57`, `Community 63`, `Community 64`, `Community 66`, `Community 69`, `Community 75`, `Community 81`, `Community 82`, `Community 87`, `Community 88`, `Community 96`, `Community 99`, `Community 100`?**
  _High betweenness centrality (0.124) - this node is a cross-community bridge._
- **Why does `Order` connect `Order Invoice Core Models` to `Delivery Concurrency Tests`, `Product WhatsApp Models`, `Order Status Stock Planning`, `Data Pipeline Intelligence`, `Community 6`, `Community 7`, `Community 8`, `Community 135`, `Community 11`, `Community 13`, `Community 20`, `Community 148`, `Community 22`, `Community 47`, `Community 51`, `Community 52`, `Community 53`, `Community 55`, `Community 56`, `Community 57`, `Community 60`, `Community 63`, `Community 64`, `Community 66`, `Community 69`, `Community 75`, `Community 79`, `Community 81`, `Community 87`, `Community 90`, `Community 100`, `Community 106`?**
  _High betweenness centrality (0.109) - this node is a cross-community bridge._
- **What connects `Props`, `Props`, `Props` to the rest of the system?**
  _1011 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Delivery Concurrency Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.05201292976785189 - nodes in this community are weakly interconnected._
- **Should `Order Invoice Core Models` be split into smaller, more focused modules?**
  _Cohesion score 0.061621621621621624 - nodes in this community are weakly interconnected._