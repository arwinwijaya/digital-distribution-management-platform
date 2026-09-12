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

## Implementation Status Audit (2026-09-10)

Roadmap ini **belum 100% terimplementasi**. Execution plan T1–T9 sudah selesai dan ditutup, tetapi cakupannya hanya sebagian dari roadmap bisnis Phase 0–4.

Legend: `[x]` implemented · `[~]` partial/MVP · `[ ]` not implemented · `[?]` outcome belum terverifikasi.

Detail checklist per fitur tersedia di [`checklist.md`](checklist.md).

Ringkasan:

- Phase 0 — Business Validation & Planning: `[~]` dokumen ada, tetapi riset bisnis, BRD, dan partner database belum terbukti.
- Phase 1 — MVP Platform: `[~]` core flow sudah ada, tetapi role management, promosi, outlet scoring/category, dan sebagian analytics belum lengkap.
- Phase 2 — Sales & Distribution Automation: `[~]` sales visit, delivery, payment, dan WhatsApp order ada; invoice, reminder, sales target, live tracking, dan broadcast promosi belum ada.
- Phase 3 — Data Intelligence & AI: `[~]` recommendation, forecast, segmentation, dan basic BI ada; geographic/supplier BI, stock planning, ML pipeline, dan measurement belum ada.
- Phase 4 — Ecosystem Expansion: `[~]` marketplace dasar ada; dynamic pricing, financial services, distributor network, dan predictive supply chain belum ada.

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

-   Invoice tracking
-   Outstanding payment
-   Credit limit
-   Payment reminder

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

**Current status:** `[~] PARTIAL`. Sales visit, delivery, payment/credit, dan WhatsApp ordering tersedia; invoice, reminder, sales target/dashboard, live tracking, dan promotion broadcast belum tersedia.

------------------------------------------------------------------------

# Phase 3 - Data Intelligence & AI Capability — `[~] PARTIAL`

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

-   Sales trend
-   Geographic analysis
-   Product performance
-   Supplier performance

------------------------------------------------------------------------

**Current status:** `[~] PARTIAL`. Recommendation, deterministic forecasting, segmentation, dan basic sales/outlet BI tersedia. Geographic BI, supplier BI, stock planning, ML pipeline, LLM integration, dan accuracy/acceptance measurement belum tersedia.

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

-   Demand prediction — `[~]` forecasting dasar tersedia
-   Inventory optimization — `[ ]` belum tersedia
-   Automated replenishment — `[ ]` belum tersedia

**Current status:** `[~] PARTIAL`. Marketplace multi-supplier dasar tersedia, tetapi distributor network, dynamic pricing, financial services, dan predictive supply-chain automation belum diimplementasikan.

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

-   Python ML Service — `[ ]` belum tersedia; AI saat ini berupa PHP heuristics
-   Machine Learning Pipeline — `[ ]` belum tersedia
-   LLM Integration — `[ ]` belum tersedia
-   Analytics Platform — `[~]` masih berupa in-application Laravel aggregates

**Architecture status:** `[~] PARTIAL`. Laravel REST API, PostgreSQL, Docker, dan CI/CD tersedia; object storage dan AWS deployment belum tersedia.

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
