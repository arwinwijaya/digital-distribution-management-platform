# Graph Report - digital-distribution-management-platform  (2026-09-09)

## Corpus Check
- 3 files · ~62,535 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1308 nodes · 2444 edges · 117 communities (50 shown, 40 thin omitted)
- Extraction: 97% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 61 edges (avg confidence: 0.85)
- Token cost: 45,000 input · 8,400 output

## Community Hubs (Navigation)
- AI Auth Sales Controllers
- Delivery Order Domain
- Request Validation Tests
- Web Frontend Pages
- Order Test Suite
- PHP Dependencies Config
- Auth Service Stack
- Monorepo Package Config
- Payment Management API
- Payment Test Suite
- User Auth Domain
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
- WhatsApp Outbound Messaging
- Error Handling Concurrency
- Order Creation Service
- Web TypeScript Config
- Web Dependencies List
- Analytics Service Code
- Order Controller Actions
- Integration Test Suite
- Delivery Concurrency Tests
- Shared TypeScript Config
- Scale Fixture Tests
- WhatsApp Inbound Tests
- Web E2E Order Flow
- Shared Type Definitions
- Web Dev Dependencies
- Supplier Seed Data
- WhatsApp Payload Parsing
- Web Runtime Dependencies
- Infrastructure DevOps Docs
- Product Test Suite
- Web NPM Scripts
- Users Commission Migrations
- Outlet Idempotency Migrations
- Product Delivery Migrations
- AI Test Suite
- Console Kernel Setup
- Marketplace Test Suite
- Outlet Test Suite
- Docs Credit Auth Env
- Web App Layout
- Phone Canonical Migration
- Console Routes Loadtest
- Flexible Overlap Rationale
- HTTP Kernel Core
- Encrypt Cookies Middleware
- Trim Strings Middleware
- Validate Signature Middleware
- Verify CSRF Middleware
- Nextjs App Config
- AI Roadmap Tasks
- Docs AI Features
- Docs Commission Orders
- Docs Transaction Flow
- Docs Delivery Roles
- Docs Trio Index
- Docs Tech Stack
- Docs WhatsApp Ordering
- Docs Database Migrations
- Docs Docker Setup
- Docs Deploy Security
- Docs Outlet Model
- Docs Testing Guide
- Docs User Roles
- Order Revenue Spec
- Payment Credit Spec
- WhatsApp Integration Spec
- Product Vision Statement
- Docs Data Stack
- Docs Product Model
- Docs Shared Package
- Docs Glossary
- Docs Sales Role
- Docs Supplier Role
- Docs CI Pipeline
- Docs Manual Setup
- Docs Troubleshooting
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
- `Outlet Warung Management Asset` --conceptually_related_to--> `T2 Outlet Onboarding Product Discovery`  [INFERRED]
  idea.md → docs/pocket/plans/2025-09-08-development-phasing/execution-plan/tasks/T2-outlet-onboarding-product-discovery.md
- `Phase 3 Data Intelligence AI Capability` --conceptually_related_to--> `T8 AI Intelligence`  [INFERRED]
  development-roadmap.md → docs/pocket/plans/2025-09-08-development-phasing/execution-plan/tasks/T8-ai-intelligence.md
- `Phase 2 Review Continuation` --conceptually_related_to--> `T9 Load Benchmark 100 Concurrent Requests`  [AMBIGUOUS]
  docs/pocket/plans/2025-09-08-development-phasing/CONTINUE-PROMPT.md → docs/performance/T9-load-test.md
- `CI Docker Build Job` --references--> `Laravel API Service`  [EXTRACTED]
  .github/workflows/ci.yml → docker-compose.yml

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core transaction flow register browse order pay** — docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t2_outlet_onboarding_product_discovery_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t3_order_management_transaction_task, docs_pocket_plans_2025_09_08_development_phasing_execution_plan_tasks_t4_payment_credit_management_task, docs_pocket_spec_2025_09_08_development_phasing_development_phasing_spec_core_flow_priority [EXTRACTED 0.85]
- **Containerized monorepo delivery pipeline** — docker_compose_api, docker_compose_web, docker_compose_postgres, github_workflows_ci_docker_build [INFERRED 0.75]
- **Core Transaction Flow Outlet to Paid** — docs_dokumentasi_teknis_core_transaction_flow, docs_dokumentasi_teknis_order_creation_service, docs_dokumentasi_teknis_delivery_model, docs_dokumentasi_teknis_credit_limit_service, docs_panduan_pengguna_alur_6_langkah [EXTRACTED 0.95]
- **WhatsApp Commerce Loop Inbound Outbound** — docs_dokumentasi_teknis_whatsapp_integration, docs_dokumentasi_teknis_whatsapp_service, docs_dokumentasi_teknis_whatsapp_idempotency, docs_panduan_pengguna_whatsapp_ordering [EXTRACTED 0.95]
- **Local Run Stack Docker API Web DB Redis** — docs_dokumentasi_teknis_docker_compose, docs_panduan_setup_docker_compose, docs_panduan_setup_env_lengkap, docs_panduan_setup_migrasi_seed, docs_panduan_setup_testing [INFERRED 0.85]

## Communities (117 total, 40 thin omitted)

### Community 0 - "AI Auth Sales Controllers"
Cohesion: 0.05
Nodes (24): AIController, AnalyticsController, AuthController, Controller, MarketplaceController, OutletController, ProductController, SalesController (+16 more)

### Community 1 - "Delivery Order Domain"
Cohesion: 0.06
Nodes (14): DeliveryController, Delivery, DeliveryStatusHistory, OrderItem, OrderStatusHistory, RoutingService, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model (+6 more)

### Community 2 - "Request Validation Tests"
Cohesion: 0.05
Nodes (15): CreditLimitController, RegisterOutletRequest, SetCreditLimitRequest, StoreDeliveryRequest, StoreOrderRequest, StoreOutletRequest, StoreSalesVisitRequest, UpdateDeliveryStatusRequest (+7 more)

### Community 3 - "Web Frontend Pages"
Cohesion: 0.08
Nodes (50): AdminOrdersPage(), approve(), showOrder(), AIData, AnalyticsPage(), DataSufficiency, Measurement, DashboardData (+42 more)

### Community 4 - "Order Test Suite"
Cohesion: 0.08
Nodes (5): Product, OrderTest, WhatsAppPostgresConcurrencyTest, LoadTest, RuntimeException

### Community 5 - "PHP Dependencies Config"
Cohesion: 0.04
Nodes (46): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+38 more)

### Community 6 - "Auth Service Stack"
Cohesion: 0.08
Nodes (15): Authenticate, RedirectIfAuthenticated, AppServiceProvider, AuthServiceProvider, RouteServiceProvider, AuthService, WhatsAppHttpClient, Closure (+7 more)

### Community 7 - "Monorepo Package Config"
Cohesion: 0.06
Nodes (31): devDependencies, typescript, typescript, name, private, scripts, api:artisan, build (+23 more)

### Community 8 - "Payment Management API"
Cohesion: 0.15
Nodes (6): PaymentController, Payment, PaymentService, ReceiptService, ValidationException, Illuminate\Testing\TestResponse

### Community 10 - "User Auth Domain"
Cohesion: 0.10
Nodes (4): User, AuthTest, SalesTest, Illuminate\Foundation\Auth\User

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

### Community 22 - "WhatsApp Outbound Messaging"
Cohesion: 0.15
Nodes (3): WhatsAppClient, WhatsAppMessage, WhatsAppOutboundService

### Community 23 - "Error Handling Concurrency"
Cohesion: 0.18
Nodes (8): Handler, ConcurrencyTestBarrier, Illuminate\Database\QueryException, Illuminate\Foundation\Exceptions\Handler, Illuminate\Support\Facades\DB, Illuminate\Validation\ValidationException, Symfony\Component\HttpKernel\Exception\ConflictHttpException, Throwable

### Community 24 - "Order Creation Service"
Cohesion: 0.16
Nodes (3): OrderCreationService, WhatsAppSenderResolver, WhatsAppService

### Community 25 - "Web TypeScript Config"
Cohesion: 0.11
Nodes (18): compilerOptions, allowJs, esModuleInterop, incremental, isolatedModules, jsx, lib, module (+10 more)

### Community 26 - "Web Dependencies List"
Cohesion: 0.11
Nodes (17): jest, name, private, version, autoprefixer, axios, eslint, eslint-config-next (+9 more)

### Community 27 - "Analytics Service Code"
Cohesion: 0.29
Nodes (3): AnalyticsService, Carbon\CarbonInterface, Illuminate\Database\Eloquent\Builder

### Community 28 - "Order Controller Actions"
Cohesion: 0.18
Nodes (4): OrderController, StorePaymentRequest, Order, HasOne

### Community 29 - "Integration Test Suite"
Cohesion: 0.22
Nodes (5): Phase1IntegrationTest, TestCase, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Support\Facades\Hash

### Community 31 - "Shared TypeScript Config"
Cohesion: 0.12
Nodes (15): compilerOptions, alwaysStrict, declaration, esModuleInterop, lib, module, noImplicitAny, noImplicitThis (+7 more)

### Community 32 - "Scale Fixture Tests"
Cohesion: 0.18
Nodes (14): Phase 1 MVP Platform Development, T9 Load Benchmark 100 Concurrent Requests, Scale Fixture Seeder 500 Outlets, SLO p95 and Max Under 2s, Phase 2 Review Continuation, Execution Flow Index, Phase 1 Foundation Infrastructure T1 T2 T3, Phase 2 Payment Credit Management T4-T9 (+6 more)

### Community 34 - "Web E2E Order Flow"
Cohesion: 0.17
Nodes (7): { execFileSync, spawn, spawnSync }, fs, net, os, path, wait(), waitForServer()

### Community 35 - "Shared Type Definitions"
Cohesion: 0.15
Nodes (12): ApiResponse, AuthError, DashboardMetrics, LoginRequest, LoginResponse, Order, OrderItem, OrderStatus (+4 more)

### Community 36 - "Web Dev Dependencies"
Cohesion: 0.20
Nodes (10): devDependencies, eslint, eslint-config-next, jest, @testing-library/jest-dom, @testing-library/react, @types/node, @types/react (+2 more)

### Community 37 - "Supplier Seed Data"
Cohesion: 0.28
Nodes (4): Supplier, ScaleFixtureSeeder, Illuminate\Database\Seeder, Illuminate\Support\Facades\Config

### Community 39 - "Web Runtime Dependencies"
Cohesion: 0.22
Nodes (9): dependencies, autoprefixer, axios, next, postcss, react, react-dom, tailwindcss (+1 more)

### Community 40 - "Infrastructure DevOps Docs"
Cohesion: 0.25
Nodes (9): Laravel API Service, PostgreSQL Database Service, Redis Cache Service, Nextjs Web Service, CI API Tests Job, CI Docker Build Job, Monorepo Structure apps api web packages shared, Digital Distribution Management Platform Overview (+1 more)

### Community 42 - "Web NPM Scripts"
Cohesion: 0.25
Nodes (8): scripts, build, dev, lint, start, test, test:e2e, test:watch

### Community 47 - "Console Kernel Setup"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 50 - "Docs Credit Auth Env"
Cohesion: 0.33
Nodes (6): Credit limit enforced at order creation not payment, CreditLimitService, JWT Authentication tymon/jwt-auth, WhatsApp Cloud API Integration, Pembayaran dan Limit Kredit, Variabel Environment lengkap JWT WhatsApp Komisi

### Community 51 - "Web App Layout"
Cohesion: 0.40
Nodes (3): inter, metadata, next

### Community 54 - "Flexible Overlap Rationale"
Cohesion: 0.50
Nodes (4): Flexible Overlap Execution Decision, Flexible Overlap Execution Rule, Hybrid Vertical Slice Plus Pilot Direction, Digital Distribution Network Owner Value Proposition

### Community 76 - "AI Roadmap Tasks"
Cohesion: 0.67
Nodes (3): Phase 3 Data Intelligence AI Capability, T5 Dashboard Analytics, T8 AI Intelligence

### Community 77 - "Docs AI Features"
Cohesion: 0.67
Nodes (3): AI deterministic and bounded with safe fallback not hallucination, AI Services Forecast Recommendation Segmentation, Fitur Pintar AI dan Analitik

### Community 78 - "Docs Commission Orders"
Cohesion: 0.67
Nodes (3): Commission percentage snapshotted per order for history immutability, Order Model, Status Pesanan Baru to Lunas

### Community 79 - "Docs Transaction Flow"
Cohesion: 0.67
Nodes (3): Core Transaction Flow order to paid, OrderCreationService, Alur Kerja 6 Langkah to Lunas

### Community 80 - "Docs Delivery Roles"
Cohesion: 0.67
Nodes (3): Delivery Model, Delivery state machine with audited actor history, Role Driver Pengiriman

### Community 81 - "Docs Trio Index"
Cohesion: 1.00
Nodes (3): Dokumentasi Teknis DDP, Panduan Pengguna DDP, Panduan Setup DDP

### Community 82 - "Docs Tech Stack"
Cohesion: 0.67
Nodes (3): Laravel 11 Backend apps/api, Next.js 14 Frontend apps/web, Prasyarat Tech Stack Node PHP Postgres Redis

### Community 83 - "Docs WhatsApp Ordering"
Cohesion: 0.67
Nodes (3): WhatsApp lease plus idempotency key prevents duplicate orders, WhatsAppService and Outbound HttpClient Parser Resolver, Memesan lewat WhatsApp

## Ambiguous Edges - Review These
- `T9 Load Benchmark 100 Concurrent Requests` → `Phase 2 Review Continuation`  [AMBIGUOUS]
  docs/pocket/plans/2025-09-08-development-phasing/CONTINUE-PROMPT.md · relation: conceptually_related_to

## Knowledge Gaps
- **421 isolated node(s):** `AIData`, `DataSufficiency`, `Measurement`, `DashboardData`, `Group` (+416 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 587 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **40 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `T9 Load Benchmark 100 Concurrent Requests` and `Phase 2 Review Continuation`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `Outlet` connect `Request Validation Tests` to `AI Auth Sales Controllers`, `Delivery Order Domain`, `WhatsApp Inbound Tests`, `Order Test Suite`, `Supplier Seed Data`, `Payment Management API`, `Payment Test Suite`, `User Auth Domain`, `Database Factories Config`, `AI Test Suite`, `Outlet Test Suite`, `WhatsApp Outbound Messaging`, `Error Handling Concurrency`, `Order Creation Service`, `Analytics Service Code`, `Integration Test Suite`, `Delivery Concurrency Tests`?**
  _High betweenness centrality (0.060) - this node is a cross-community bridge._
- **Why does `User` connect `User Auth Domain` to `AI Auth Sales Controllers`, `Delivery Order Domain`, `Request Validation Tests`, `WhatsApp Inbound Tests`, `Order Test Suite`, `Supplier Seed Data`, `Auth Service Stack`, `Payment Management API`, `Payment Test Suite`, `Product Test Suite`, `Database Factories Config`, `AI Test Suite`, `Marketplace Test Suite`, `Outlet Test Suite`, `Console Routes Loadtest`, `Error Handling Concurrency`, `Integration Test Suite`, `Delivery Concurrency Tests`?**
  _High betweenness centrality (0.055) - this node is a cross-community bridge._
- **Why does `Order` connect `Order Controller Actions` to `AI Auth Sales Controllers`, `Delivery Order Domain`, `Request Validation Tests`, `Order Test Suite`, `Supplier Seed Data`, `Payment Management API`, `Payment Test Suite`, `AI Test Suite`, `WhatsApp Outbound Messaging`, `Error Handling Concurrency`, `Order Creation Service`, `Analytics Service Code`, `Integration Test Suite`, `Delivery Concurrency Tests`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **What connects `AIData`, `DataSufficiency`, `Measurement` to the rest of the system?**
  _421 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `AI Auth Sales Controllers` be split into smaller, more focused modules?**
  _Cohesion score 0.054061624649859946 - nodes in this community are weakly interconnected._
- **Should `Delivery Order Domain` be split into smaller, more focused modules?**
  _Cohesion score 0.05583972719522592 - nodes in this community are weakly interconnected._