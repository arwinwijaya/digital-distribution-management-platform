# Development Roadmap - Digital Distribution Management Platform

## Product Vision

Build a digital distribution ecosystem that connects FMCG suppliers,
distributors, sales teams, and thousands of retail outlets/warungs
through a single platform.

The objective is to transform traditional FMCG distribution into a
data-driven business by utilizing:

-   Outlet network
-   Digital ordering
-   Sales intelligence
-   Operational automation
-   AI-based recommendation and forecasting

## Implementation Status Audit (2026-09-14)

Roadmap ini **belum 100% terimplementasi**. Execution plan awal, operational readiness, dan Phase 3 Data Intelligence Foundation sudah selesai dengan `REVIEW_PASS`, tetapi beberapa capability bisnis dan kesiapan produksi masih menjadi pekerjaan lanjutan.

Legend: `[x]` implemented · `[~]` partial/MVP/foundation · `[ ]` not implemented · `[?]` outcome belum terverifikasi.

Detail checklist per fitur tersedia di [`checklist.md`](checklist.md). Closeout terbaru:

- [`Operational Readiness`](docs/pocket/plans/2026-09-10-operational-readiness/closeout.md)
- [`Phase 3 Data Intelligence Foundation`](docs/pocket/plans/2026-09-14-phase3-data-intelligence-foundation/closeout.md)

Catatan: `README.md` memakai penomoran fase implementasi teknis (hingga Phase 5), sedangkan dokumen ini memakai fase roadmap bisnis 0–4. Keduanya merujuk ke implementasi repository yang sama.

Ringkasan:

- Phase 0 — Business Validation & Planning: `[~]` dokumen ada, tetapi riset bisnis, BRD, dan partner database belum terbukti.
- Phase 1 — MVP Platform: `[~]` core flow sudah ada, tetapi role management, promosi, outlet scoring/category, dan sebagian analytics belum lengkap.
- Phase 2 — Sales & Distribution Automation: `[~]` invoice, reminder, payment, delivery, sales visit, dan WhatsApp order tersedia; sales target, live tracking, dan broadcast promosi belum ada.
- Phase 3 — Data Intelligence & AI: `[~]` foundation selesai—pipeline, geographic/supplier BI, stock planning, measurement, admin UI/map, dan atomic publication tersedia; ML/LLM dan action workflow belum ada.
- Phase 4 — Ecosystem Expansion: `[~]` marketplace dasar ada; dynamic pricing, financial services, distributor network, dan automated replenishment belum tersedia.

------------------------------------------------------------------------

# Phase 0 - Business Validation & Planning — `[~] PARTIAL`

## Objective

Validate business model, target users, and operational process before
development.

## Activities

### Business Process Mapping

-   Current distribution workflow
-   Supplier relationship process
-   Outlet ordering behavior
-   Payment process
-   Delivery process

### User Research

Target users: - Business owner - Supplier/principal - Sales
representative - Warung/store owner - Delivery team

### Output

-   Business requirement document — `[ ]` belum ditemukan
-   Product roadmap — `[x]` tersedia di file ini
-   MVP scope — `[x]` tersedia di `docs/pocket/spec/` dan execution plan
-   Initial partner database — `[ ]` belum tersedia; fixture bukan database partner produksi

**Current status:** `[~] PARTIAL`. Process mapping dan user research belum memiliki bukti validasi lapangan.

------------------------------------------------------------------------

# Phase 1 - MVP Platform Development — `[~] PARTIAL`

## Objective

Build the first platform version to manage distribution operations.

## Main Features

## 1. User Management

Roles:

-   Platform Owner
-   Admin
-   Sales
-   Supplier
-   Outlet Partner

Features: - Login - Role management - User profile

------------------------------------------------------------------------

## 2. Outlet Management

Features:

-   Outlet registration
-   Outlet profile
-   Location mapping
-   Outlet category
-   Purchase history
-   Outlet scoring

Goal:

Create digital database of retail network.

------------------------------------------------------------------------

## 3. Product Catalog

Features:

-   Product master
-   SKU management
-   Price management
-   Promotion
-   Product availability

------------------------------------------------------------------------

## 4. Order Management

Features:

-   Outlet ordering
-   Order approval
-   Order status tracking
-   Order history

Flow:

    Outlet
      |
    Order
      |
    Admin Approval
      |
    Warehouse
      |
    Delivery
      |
    Completed

------------------------------------------------------------------------

## 5. Dashboard Basic Analytics

Metrics:

-   Total outlets
-   Active outlets
-   Sales value
-   Order volume
-   Product performance

------------------------------------------------------------------------

## Phase 1 Success Criteria

Target:

-   500 registered outlets — `[?]` belum terbukti sebagai adoption produksi
-   100 active ordering outlets — `[?]` belum terbukti sebagai adoption produksi
-   5-10 supplier partners — `[?]` belum terbukti sebagai partner aktif produksi

**Current status:** `[~] PARTIAL`. Core register → browse → order → approval flow berjalan, tetapi role management, outlet category/scoring, promotion, dan sebagian analytics belum tersedia.

------------------------------------------------------------------------

# Phase 2 - Sales & Distribution Automation — `[~] PARTIAL`

## Objective

Improve field execution and operational efficiency.

## Features

------------------------------------------------------------------------

# Sales Force Application

Features:

-   Sales visit planning
-   Outlet visit tracking
-   Order collection
-   Sales target
-   Performance dashboard

------------------------------------------------------------------------

# Delivery Management

Features:

-   Delivery planning
-   Driver management
-   Route planning
-   Delivery confirmation
-   Proof of delivery

------------------------------------------------------------------------

# Payment Management

Features:

-   Invoice tracking — `[x]` invoice otomatis dari order confirmed, status, dan riwayat berbasis role
-   Outstanding payment — `[x]`
-   Credit limit — `[x]` dengan enforcement saat submit order
-   Payment reminder — `[x]` scheduler, state machine, retry/idempotency, dan metrik reminder

------------------------------------------------------------------------

# WhatsApp Integration

Features:

-   Product catalog via WhatsApp
-   Order via WhatsApp
-   Automated notification
-   Promotion broadcast

------------------------------------------------------------------------

## Phase 2 Success Criteria

Target:

-   1,000+ active outlets — `[?]` belum terbukti sebagai adoption produksi
-   Digital ordering adoption \>70% — `[?]` belum ada measurement
-   Reduced manual order processing — `[?]` belum ada measurement

**Current status:** `[~] PARTIAL`. Operational readiness untuk invoice, payment, finance role, reminder, dan delivery proof sudah selesai; sales target/dashboard, GPS/live tracking, dan promotion broadcast masih belum tersedia.

------------------------------------------------------------------------

# Phase 3 - Data Intelligence & AI Capability — `[~] FOUNDATION COMPLETE`

## Objective

Transform distribution data into business intelligence.

------------------------------------------------------------------------

# AI Recommendation Engine

Purpose:

Increase sales opportunity.

Capability:

-   Product recommendation
-   Cross-selling suggestion
-   Outlet behavior analysis

Example:

    Outlet A usually buys:
    - Coffee
    - Sugar

    Recommendation:
    Add snack product X

------------------------------------------------------------------------

# Sales Forecasting

Purpose:

Predict demand.

Capability:

-   Outlet order prediction
-   Product demand forecast
-   Stock planning

------------------------------------------------------------------------

# Outlet Intelligence

Capability:

Automatic segmentation:

-   High potential outlet
-   Growth outlet
-   Declining outlet

------------------------------------------------------------------------

# Business Intelligence Dashboard

Features:

-   Sales trend — `[x]`
-   Geographic analysis — `[x]` territory, coverage, dan map data
-   Product performance — `[x]` melalui snapshot data intelligence
-   Supplier performance — `[x]` fulfillment, lead time, coverage, dan revenue
-   Measurement — `[x]` recommendation funnel dan forecast accuracy/WAPE

------------------------------------------------------------------------

**Current status:** `[~] FOUNDATION COMPLETE`. Recommendation dengan sparse fallback, deterministic forecasting, segmentation, geographic BI, supplier BI, stock planning, measurement, pipeline snapshot, admin UI/map, dan atomic publication sudah tersedia. Dedicated action workflow, Python ML/LLM integration, dan verifikasi target akurasi/acceptance bisnis masih belum tersedia.

------------------------------------------------------------------------

# Phase 4 - Ecosystem Expansion — `[~] PARTIAL`

## Objective

Become a digital FMCG distribution ecosystem.

------------------------------------------------------------------------

## Advanced Features

### Marketplace Model

Connect:

-   Multiple suppliers
-   Multiple distributors
-   Thousands of outlets

------------------------------------------------------------------------

### Dynamic Pricing

AI-based:

-   Promotion recommendation
-   Pricing optimization
-   Demand-based strategy

------------------------------------------------------------------------

### Financial Services Integration

Potential:

-   Digital payment
-   Outlet credit scoring
-   Working capital partnership

------------------------------------------------------------------------

### Predictive Supply Chain

Capability:

-   Demand prediction — `[x]` forecasting dasar tersedia
-   Inventory optimization — `[~]` bounded stock planning melalui reorder point dan safety stock tersedia; optimasi lintas gudang belum ada
-   Automated replenishment — `[ ]` eksekusi ke supplier atau purchase order belum tersedia

**Current status:** `[~] PARTIAL`. Marketplace multi-supplier dasar tersedia, tetapi distributor network, dynamic pricing, financial services, dan automated replenishment belum diimplementasikan.

------------------------------------------------------------------------

# Remaining Roadmap — Prioritas Setelah Foundation

## 1. Production Readiness & Pilot Adoption

- Deploy AWS dan object storage.
- Tambahkan backup/restore, secrets management, queue/scheduler worker, observability, dan security hardening.
- Jalankan pilot dengan distributor dan outlet nyata.
- Ukur active outlet, digital-ordering adoption, processing time, payment collection, forecast accuracy, dan recommendation acceptance.

## 2. Field Operations

- PWA/mobile app untuk sales dan driver.
- Offline order, GPS check-in, route optimization, live tracking, dan proof of delivery foto/tanda tangan.
- Sales target, achievement, dan performance dashboard.
- WhatsApp promotion broadcast.

## 3. AI Action Workflow

- Ubah recommendation menjadi draft order/campaign.
- Ubah stock plan menjadi approval dan replenishment action.
- Tambahkan A/B test, monitoring revenue lift, serta kalibrasi forecast.

## 4. Commercial Ecosystem

- Promotion engine dan dynamic pricing.
- External payment gateway.
- Outlet credit scoring dan working-capital partnership.
- Multi-distributor workflow dengan tenant isolation.
- Automated replenishment ke supplier atau purchase order.

## 5. Business Validation

- Lengkapi user research, BRD, partner database, dan proses operasional tervalidasi.
- Validasi target 500/1.000 outlet menggunakan data produksi, bukan fixture atau test seeder.

------------------------------------------------------------------------

# Recommended Technical Architecture

## Frontend

-   Phase 1: Next.js Web Application — `[x]` implemented di `apps/web/`
-   Phase 2: Progressive Web App (PWA) — `[ ]` belum tersedia
-   Phase 2: Mobile Sales Application — `[ ]` belum tersedia

------------------------------------------------------------------------

## Backend

Recommended:

-   Laravel / NestJS
-   REST API Architecture

------------------------------------------------------------------------

## Database

Primary:

-   PostgreSQL

Supporting:

-   Redis Cache
-   Object Storage

------------------------------------------------------------------------

## Cloud Infrastructure

Recommended:

-   AWS
-   Container-based deployment
-   CI/CD pipeline

------------------------------------------------------------------------

## AI Infrastructure

Phase 3:

-   Python ML Service — `[ ]` belum tersedia; AI saat ini berupa deterministic PHP services
-   Machine Learning Pipeline — `[ ]` belum tersedia
-   LLM Integration — `[ ]` belum tersedia
-   Analytics Platform — `[x]` snapshot-based data intelligence pipeline dan in-application analytics tersedia; platform analytics eksternal belum ada

**Architecture status:** `[~] PARTIAL`. Laravel REST API, PostgreSQL, Docker, CI/CD, dan data intelligence foundation tersedia; object storage, AWS deployment, Python ML/LLM stack, serta production observability belum tersedia.

------------------------------------------------------------------------

# Development Team Requirement

## Initial Team (MVP)

-   Product Owner
-   Business Analyst
-   UI/UX Designer
-   Frontend Developer
-   Backend Developer
-   QA Engineer
-   DevOps Engineer

------------------------------------------------------------------------

# Long Term Vision

From:

"Traditional FMCG sales network"

Become:

"Digital Distribution Intelligence Platform"

The competitive advantage:

1.  Strong outlet network
2.  FMCG sales experience
3.  Real-time market data
4.  AI-driven decision making
5.  Efficient distribution operation
