# Graph Report - digital-distribution-management-platform  (2026-09-09)

## Corpus Check
- 183 files · ~55,477 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1262 nodes · 2418 edges · 104 communities (45 shown, 32 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 49 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Web Frontend Pages
- Product Catalog Orders
- Payment Management API
- PHP Dependencies Config
- Auth Middleware Stack
- Outlet Management API
- AI Auth Controllers
- Core Domain Relations
- Delivery Credit Models
- Payment Test Suite
- AI Forecast Service
- Database Factories Config
- Phase Two Verdict
- T1 Review Record
- T2 Review Record
- T3 Review Record
- T4 Review Record
- T5 Review Record
- T6 Review Record
- T7 Review Record
- T8 Review Record
- T9 Review Record
- Supplier Model Tests
- Delivery Management API
- Sales Visit Management
- Web Dependencies List
- Web TypeScript Config
- Shared Package Config
- Request Validation Layer
- Delivery Concurrency Tests
- Monorepo Root Config
- Shared TypeScript Config
- Scale Fixture Tests
- WhatsApp Outbound Messaging
- WhatsApp Inbound Tests
- Execution Planning Docs
- WhatsApp Webhook Service
- Web E2E Order Flow
- Shared Type Definitions
- Error Handling Parsing
- Credit Limit API
- Web Dev Dependencies
- Analytics API Routing
- Order Creation Service
- Web Runtime Dependencies
- Infrastructure DevOps Docs
- WhatsApp Payload Parsing
- Auth Test Suite
- Web NPM Scripts
- WhatsApp Client Contract
- Order Controller Actions
- User Commission Migrations
- Outlet Idempotency Migrations
- Product Delivery Migrations
- Console Kernel Setup
- Order Request Validation
- Order Creation Logic
- Marketplace Test Suite
- Outlet Test Suite
- Web App Layout
- Phone Canonical Migration
- Console Routes Loadtest
- Flexible Overlap Rationale
- HTTP Kernel Core
- Encrypt Cookies Middleware
- Trim Strings Middleware
- Trust Proxies Middleware
- Validate Signature Middleware
- Verify CSRF Middleware
- Nextjs App Config
- AI Roadmap Tasks
- Order Revenue Spec
- Payment Credit Spec
- WhatsApp Integration Spec
- Product Vision Statement
- Sales Force Task
- CI Web Tests

## God Nodes (most connected - your core abstractions)
1. `User` - 100 edges
2. `Order` - 79 edges
3. `Outlet` - 76 edges
4. `Product` - 66 edges
5. `OrderTest` - 38 edges
6. `TestCase` - 31 edges
7. `PaymentTest` - 30 edges
8. `apiUrl()` - 27 edges
9. `Payment` - 23 edges
10. `WhatsAppMessage` - 23 edges

## Surprising Connections (you probably didn't know these)
- `Phase 1 MVP Platform Development` --conceptually_related_to--> `Phase 1 Foundation Infrastructure T1 T2 T3`  [INFERRED]
  development-roadmap.md → docs/pocket/plans/2025-09-08-development-phasing/execution-plan/phase-1.md
- `Phase 3 Data Intelligence AI Capability` --conceptually_related_to--> `T8 AI Intelligence`  [INFERRED]
  development-roadmap.md → docs/pocket/plans/2025-09-08-development-phasing/execution-plan/tasks/T8-ai-intelligence.md
- `Outlet Warung Management Asset` --conceptually_related_to--> `T2 Outlet Onboarding Product Discovery`  [INFERRED]
  idea.md → docs/pocket/plans/2025-09-08-development-phasing/execution-plan/tasks/T2-outlet-onboarding-product-discovery.md
- `CI API Tests Job` --references--> `PostgreSQL Database Service`  [EXTRACTED]
  .github/workflows/ci.yml → docker-compose.yml
- `CI API Tests Job` --references--> `Redis Cache Service`  [EXTRACTED]
  .github/workflows/ci.yml → docker-compose.yml

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core transaction flow register browse order pay** — docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t2_outlet_onboarding_product_discovery_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t3_order_management_transaction_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t4_payment_credit_management_task, docs_pocket_spec_2025_09_08_development_phasing_development_phasing_spec_core_flow_priority [EXTRACTED 0.85]
- **Containerized monorepo delivery pipeline** — docker_compose_api, docker_compose_web, docker_compose_postgres, github_workflows_ci_docker_build [INFERRED 0.75]

## Communities (104 total, 32 thin omitted)

### Community 0 - "Web Frontend Pages"
Cohesion: 0.08
Nodes (50): AdminOrdersPage(), approve(), showOrder(), AIData, AnalyticsPage(), DataSufficiency, Measurement, DashboardData (+42 more)

### Community 1 - "Product Catalog Orders"
Cohesion: 0.07
Nodes (6): Product, OrderTest, ProductTest, WhatsAppPostgresConcurrencyTest, LoadTest, RuntimeException

### Community 2 - "Payment Management API"
Cohesion: 0.09
Nodes (11): PaymentController, StorePaymentRequest, Order, HasOne, Payment, AnalyticsService, PaymentService, ReceiptService (+3 more)

### Community 3 - "PHP Dependencies Config"
Cohesion: 0.04
Nodes (46): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+38 more)

### Community 4 - "Auth Middleware Stack"
Cohesion: 0.08
Nodes (15): Authenticate, RedirectIfAuthenticated, AppServiceProvider, AuthServiceProvider, RouteServiceProvider, AuthService, WhatsAppHttpClient, Closure (+7 more)

### Community 5 - "Outlet Management API"
Cohesion: 0.11
Nodes (7): OutletController, StoreOutletRequest, CreditLimit, Outlet, CreditLimitService, AnalyticsTest, Illuminate\Contracts\Validation\ValidationRule

### Community 6 - "AI Auth Controllers"
Cohesion: 0.15
Nodes (8): AIController, AuthController, Controller, MarketplaceController, ProductController, WhatsAppController, Illuminate\Http\JsonResponse, Illuminate\Http\Request

### Community 7 - "Core Domain Relations"
Cohesion: 0.09
Nodes (7): User, SalesTest, Illuminate\Database\Eloquent\Relations\HasMany, Illuminate\Database\Eloquent\Relations\HasOne, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable, Tymon\JWTAuth\Contracts\JWTSubject

### Community 8 - "Delivery Credit Models"
Cohesion: 0.14
Nodes (6): DeliveryStatusHistory, OrderStatusHistory, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\BelongsTo, InvalidArgumentException

### Community 10 - "AI Forecast Service"
Cohesion: 0.17
Nodes (6): ForecastService, RecommendationService, SegmentationService, Carbon, Carbon\Carbon, Illuminate\Support\Collection

### Community 11 - "Database Factories Config"
Cohesion: 0.12
Nodes (8): OutletFactory, ProductFactory, static, SupplierFactory, static, UserFactory, Illuminate\Database\Eloquent\Factories\Factory, Illuminate\Support\Str

### Community 12 - "Phase Two Verdict"
Cohesion: 0.08
Nodes (24): acceptance_and_deliverables, not_fully_verified, status, verified, concerns, corrections, cross_task_issues, loop_info (+16 more)

### Community 13 - "T1 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 14 - "T2 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 15 - "T3 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 16 - "T4 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 17 - "T5 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 18 - "T6 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 19 - "T7 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 20 - "T8 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 21 - "T9 Review Record"
Cohesion: 0.08
Nodes (23): cycle, fix_instructions, loop_info, current_cycle, cycles_remaining, max_cycles, overall, reviewed_sha (+15 more)

### Community 22 - "Supplier Model Tests"
Cohesion: 0.22
Nodes (6): Supplier, Phase1IntegrationTest, TestCase, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Support\Facades\Hash

### Community 23 - "Delivery Management API"
Cohesion: 0.20
Nodes (3): DeliveryController, Delivery, RoutingService

### Community 24 - "Sales Visit Management"
Cohesion: 0.16
Nodes (4): SalesController, StoreSalesVisitRequest, SalesVisit, CalendarService

### Community 25 - "Web Dependencies List"
Cohesion: 0.11
Nodes (18): jest, typescript, name, private, version, autoprefixer, axios, eslint (+10 more)

### Community 26 - "Web TypeScript Config"
Cohesion: 0.11
Nodes (18): compilerOptions, allowJs, esModuleInterop, incremental, isolatedModules, jsx, lib, module (+10 more)

### Community 27 - "Shared Package Config"
Cohesion: 0.11
Nodes (17): devDependencies, jest, ts-jest, @types/jest, typescript, jest, typescript, main (+9 more)

### Community 28 - "Request Validation Layer"
Cohesion: 0.15
Nodes (5): RegisterOutletRequest, StoreDeliveryRequest, UpdateDeliveryStatusRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Validator

### Community 30 - "Monorepo Root Config"
Cohesion: 0.12
Nodes (15): devDependencies, typescript, typescript, name, private, scripts, api:artisan, build (+7 more)

### Community 31 - "Shared TypeScript Config"
Cohesion: 0.12
Nodes (15): compilerOptions, alwaysStrict, declaration, esModuleInterop, lib, module, noImplicitAny, noImplicitThis (+7 more)

### Community 32 - "Scale Fixture Tests"
Cohesion: 0.23
Nodes (4): OrderItem, ScaleFixtureSeeder, AITest, Illuminate\Database\Seeder

### Community 35 - "Execution Planning Docs"
Cohesion: 0.18
Nodes (14): Phase 1 MVP Platform Development, T9 Load Benchmark 100 Concurrent Requests, Scale Fixture Seeder 500 Outlets, SLO p95 and Max Under 2s, Phase 2 Review Continuation, Execution Flow Index, Phase 1 Foundation Infrastructure T1 T2 T3, Phase 2 Payment Credit Management T4-T9 (+6 more)

### Community 37 - "Web E2E Order Flow"
Cohesion: 0.17
Nodes (7): { execFileSync, spawn, spawnSync }, fs, net, os, path, wait(), waitForServer()

### Community 38 - "Shared Type Definitions"
Cohesion: 0.15
Nodes (12): ApiResponse, AuthError, DashboardMetrics, LoginRequest, LoginResponse, Order, OrderItem, OrderStatus (+4 more)

### Community 39 - "Error Handling Parsing"
Cohesion: 0.24
Nodes (4): Handler, Illuminate\Foundation\Exceptions\Handler, Illuminate\Validation\ValidationException, Throwable

### Community 41 - "Web Dev Dependencies"
Cohesion: 0.20
Nodes (10): devDependencies, eslint, eslint-config-next, jest, @testing-library/jest-dom, @testing-library/react, @types/node, @types/react (+2 more)

### Community 42 - "Analytics API Routing"
Cohesion: 0.22
Nodes (4): AnalyticsController, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\Facades\Route

### Community 43 - "Order Creation Service"
Cohesion: 0.31
Nodes (4): ConcurrencyTestBarrier, Illuminate\Database\QueryException, Illuminate\Support\Facades\DB, Symfony\Component\HttpKernel\Exception\ConflictHttpException

### Community 44 - "Web Runtime Dependencies"
Cohesion: 0.22
Nodes (9): dependencies, autoprefixer, axios, next, postcss, react, react-dom, tailwindcss (+1 more)

### Community 45 - "Infrastructure DevOps Docs"
Cohesion: 0.25
Nodes (9): Laravel API Service, PostgreSQL Database Service, Redis Cache Service, Nextjs Web Service, CI API Tests Job, CI Docker Build Job, Monorepo Structure apps api web packages shared, Digital Distribution Management Platform Overview (+1 more)

### Community 48 - "Web NPM Scripts"
Cohesion: 0.25
Nodes (8): scripts, build, dev, lint, start, test, test:e2e, test:watch

### Community 54 - "Console Kernel Setup"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 59 - "Web App Layout"
Cohesion: 0.40
Nodes (3): inter, metadata, next

### Community 62 - "Flexible Overlap Rationale"
Cohesion: 0.50
Nodes (4): Flexible Overlap Execution Decision, Flexible Overlap Execution Rule, Hybrid Vertical Slice Plus Pilot Direction, Digital Distribution Network Owner Value Proposition

### Community 85 - "AI Roadmap Tasks"
Cohesion: 0.67
Nodes (3): Phase 3 Data Intelligence AI Capability, T5 Dashboard Analytics, T8 AI Intelligence

## Ambiguous Edges - Review These
- `T9 Load Benchmark 100 Concurrent Requests` → `Phase 2 Review Continuation`  [AMBIGUOUS]
  docs/pocket/plans/2025-09-08-development-phasing/CONTINUE-PROMPT.md · relation: conceptually_related_to

## Knowledge Gaps
- **392 isolated node(s):** `name`, `type`, `description`, `keywords`, `license` (+387 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 553 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **32 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `T9 Load Benchmark 100 Concurrent Requests` and `Phase 2 Review Continuation`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `User` connect `Core Domain Relations` to `Product Catalog Orders`, `Payment Management API`, `Auth Middleware Stack`, `Outlet Management API`, `AI Auth Controllers`, `Delivery Credit Models`, `Payment Test Suite`, `Database Factories Config`, `Supplier Model Tests`, `Delivery Management API`, `Request Validation Layer`, `Delivery Concurrency Tests`, `Scale Fixture Tests`, `WhatsApp Inbound Tests`, `Error Handling Parsing`, `Auth Test Suite`, `WhatsApp Client Contract`, `Marketplace Test Suite`, `Outlet Test Suite`, `Console Routes Loadtest`?**
  _High betweenness centrality (0.090) - this node is a cross-community bridge._
- **Why does `Outlet` connect `Outlet Management API` to `Product Catalog Orders`, `Payment Management API`, `AI Auth Controllers`, `Core Domain Relations`, `Delivery Credit Models`, `Payment Test Suite`, `AI Forecast Service`, `Database Factories Config`, `Supplier Model Tests`, `Request Validation Layer`, `Delivery Concurrency Tests`, `Scale Fixture Tests`, `WhatsApp Outbound Messaging`, `WhatsApp Inbound Tests`, `WhatsApp Webhook Service`, `Error Handling Parsing`, `Credit Limit API`, `Order Creation Service`, `WhatsApp Client Contract`, `Order Creation Logic`, `Outlet Test Suite`?**
  _High betweenness centrality (0.060) - this node is a cross-community bridge._
- **Why does `Order` connect `Payment Management API` to `Scale Fixture Tests`, `WhatsApp Outbound Messaging`, `Product Catalog Orders`, `Outlet Management API`, `AI Auth Controllers`, `Core Domain Relations`, `Delivery Credit Models`, `Error Handling Parsing`, `AI Forecast Service`, `Order Creation Service`, `Payment Test Suite`, `Order Controller Actions`, `Supplier Model Tests`, `Delivery Management API`, `Order Creation Logic`, `Delivery Concurrency Tests`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **What connects `name`, `type`, `description` to the rest of the system?**
  _392 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Web Frontend Pages` be split into smaller, more focused modules?**
  _Cohesion score 0.08121158911325724 - nodes in this community are weakly interconnected._
- **Should `Product Catalog Orders` be split into smaller, more focused modules?**
  _Cohesion score 0.06919945725915876 - nodes in this community are weakly interconnected._