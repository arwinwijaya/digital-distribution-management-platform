# Digital Distribution Management Platform - Business Idea

> **Status implementasi (2026-09-18):** dokumen ini adalah *business idea*
> asli. Untuk status teknis terkini lihat [`development-roadmap.md`](development-roadmap.md)
> dan [`checklist.md`](checklist.md). Ringkasnya: platform sudah berjalan
> (Laravel REST API + Next.js), Phase 1–3 dan Phase 7 MVP completion sudah
> `DONE`, dan fitur inti pada daftar di bawah sebagian besar sudah
> terimplementasi. Yang belum: validasi lapangan (pilot produksi nyata),
> PWA/offline mobile, GPS/live tracking, driver roster, proof of delivery
> foto/tanda tangan, Python ML/LLM, dynamic pricing, financial services, dan
> multi-distributor (Phase 8–10).

## Background

A former FMCG Sales Manager with strong relationships across stores and
warungs in Jabodetabek has a valuable asset: distribution network and
market knowledge.

The platform aims to transform this network into a digital distribution
ecosystem connecting:

    Supplier / Brand Principal
                |
                |
    Digital Distribution Platform
                |
                |
    Warung / Store Partner
                |
                |
    Consumer

The focus is not only e-commerce, but **network management, distribution
intelligence, and sales optimization**.

------------------------------------------------------------------------

# Core Features

## 1. Management Dashboard — `[x]` implemented

Purpose: Provide business visibility for the owner.

Features: - Total registered outlets - Active outlet tracking -
Daily/monthly sales - Order performance - Best-selling products - Area
performance - Revenue monitoring - Customer growth

Example metrics:

    Total Partner Store : 1,250
    Active Outlet       : 980
    Order Today         : 350
    Monthly Sales       : Rp 2.4 Billion

------------------------------------------------------------------------

# 2. Outlet / Warung Management — `[x]` implemented

The outlet database is the main business asset.

Features: - Outlet profile - Owner information - Phone number -
Address - GPS location - Outlet category - Sales potential - Product
preference - Order history

Implemented: admin outlet list/update, category enum
(`warung|minimarket|supermarket|grosir|restoran|kafe|toko_kelontong|lainnya`),
outlet scoring (`OutletScoringService`: volume/frequency/recency, recalculated
on `Delivered`), dan purchase history + summary endpoint. GPS tersimpan
sebagai lat/long (map picker khusus outlet belum ada).

Outlet scoring:

    Outlet Score: A+

    Potential:
    ★★★★★

    Order Frequency:
    3x/week

    Payment Behavior:
    Good

------------------------------------------------------------------------

# 3. Supplier / Brand Management — `[~]` partial

Manage relationships with principals.

Features: - Supplier database - Brand information - Product list -
Purchase price - Selling price - Margin calculation - Agreement tracking

Implemented: supplier database, product list, purchase/selling price, dan
supplier performance BI (fulfillment, lead time, coverage, revenue). Yang
belum: supplier self-service pricing dan agreement tracking formal.

Example:

    Supplier:
    PT ABC Food

    Product:
    Snack X

    Purchase Price:
    Rp 8,000

    Selling Price:
    Rp 10,000

    Margin:
    20%

------------------------------------------------------------------------

# 4. Digital Product Catalog — `[x]` implemented

Digital catalog for outlet partners.

Features: - Product photo - SKU - Price - Promotion - Minimum order
quantity - Availability status

Implemented: product master, SKU, price management + price history
(`ProductPriceService`), promotion (CRUD, overlap guard, `min_order`,
snapshot saat order), dan availability status (`is_active`, stock,
supplier eligibility).

------------------------------------------------------------------------

# 5. Order Management System — `[x]` implemented

Main transaction workflow.

Flow:

    Outlet Order
          |
    Order Confirmation
          |
    Warehouse Preparation
          |
    Delivery
          |
    Payment

Order status: - New Order - Confirmed - Packed - Delivered - Paid

Implemented: outlet ordering, admin approval, status tracking/history,
idempotency + stock reservation, dan sales order collection
(`POST /sales/orders`, territory-scoped, reuse `OrderCreationService`).

------------------------------------------------------------------------

# 6. Sales Force Management — `[x]` implemented (field mobile di Phase 8)

For future sales team expansion.

Features: - Sales target - Outlet visit planning - Visit history - Order
collection - Sales performance

Implemented: sales visit planning/status/notes, sales order collection,
sales quota/target per periode (`sales_targets`), dan sales performance
dashboard (sales + admin). Yang belum: GPS check-in/telemetry dan PWA
offline (Phase 8).

Example:

    Sales:
    Andi

    Target:
    30 outlets/day

    Visited:
    22

    Orders:
    15

------------------------------------------------------------------------

# 7. Delivery Management — `[~]` partial

Manage distribution operations.

Features: - Delivery planning - Driver assignment - Route optimization -
Delivery tracking - Proof of delivery

Implemented: delivery planning, driver assignment/validasi, delivery
confirmation, dan `RoutingService`/`route_data` sebagai seam MVP. Yang
belum: driver roster CRUD, route optimization penuh, live tracking, dan
proof of delivery foto/tanda tangan (Phase 8).

------------------------------------------------------------------------

# 8. Payment & Credit Management — `[x]` implemented

Important for FMCG distribution.

Features: - Outlet credit limit - Outstanding payment - Payment
history - Due date monitoring - Credit risk tracking

Example:

    Outlet:
    Warung A

    Credit Limit:
    Rp 5 Million

    Outstanding:
    Rp 2.5 Million

    Due Date:
    10 September

------------------------------------------------------------------------

# 9. AI & Analytics Capability — `[~]` foundation complete

Differentiator for long-term development.

Foundation (deterministic PHP services) sudah tersedia: recommendation dengan
sparse fallback, forecasting, segmentation, geographic/supplier BI, stock
planning, dan measurement. Yang belum: Python ML service, ML pipeline, dan
LLM integration (Phase 9).

## AI Product Recommendation

Recommend products based on buying behavior.

Example:

"Outlet frequently buys coffee products. Recommend complementary snack
products."

------------------------------------------------------------------------

## Sales Forecasting

Predict future ordering behavior.

Example:

    Outlet A

    Next Order Prediction:
    5 days

    Estimated Order:
    Rp 800,000

------------------------------------------------------------------------

## Outlet Segmentation

Automatically classify outlets:

-   High Value Outlet
-   Medium Potential
-   Low Potential

------------------------------------------------------------------------

# 10. WhatsApp Integration — `[x]` implemented

Critical for Indonesian warung ecosystem.

Features: - Order through WhatsApp - Product catalog sharing - Automated
promotion - Order confirmation - Customer notification

Implemented: katalog via WhatsApp, order via signed webhook, notifikasi order
confirmed (retry/idempotency), payment reminder, dan promotion broadcast
(`promo_broadcast`, targeting outlet dengan order 30d terakhir,
idempotency + retry).

Example:

    Customer:
    "Mau order snack"

    Bot:
    "Here is our latest catalog..."

------------------------------------------------------------------------

# MVP Development Plan

> Catatan: penomoran fase di bawah adalah *business plan* asli. Penomoran
> roadmap teknis aktual (`development-roadmap.md`) berbeda — lihat "Status
> implementasi" per fase.

## Phase 1 (0-3 Months) — `[x]` DONE

Priority features:

-   Outlet Management — `[x]`
-   Product Catalog — `[x]`
-   Order Management — `[x]`
-   Supplier Management — `[~]` supplier database + performance BI tersedia
-   Dashboard — `[x]`

Goal:

Manage 500-1,000 outlets digitally. — `[?]` belum terbukti sebagai adoption
produksi.

------------------------------------------------------------------------

## Phase 2 — `[~]` PARTIAL

Additional capabilities:

-   Mobile Sales Application — `[ ]` (PWA manifest ada; offline/mobile app belum)
-   Delivery Management — `[~]` planning/assignment/confirmation ada; optimasi & live tracking belum
-   Payment Tracking — `[x]`
-   WhatsApp Automation — `[x]` (termasuk promotion broadcast)

------------------------------------------------------------------------

## Phase 3 — `[~]` FOUNDATION COMPLETE

Advanced intelligence:

-   AI Recommendation — `[~]` deterministic PHP, sparse fallback
-   Sales Forecasting — `[~]` deterministic, target akurasi bisnis belum terbukti
-   Dynamic Pricing — `[ ]` (fixed promotion rules sudah ada; pricing optimization belum)
-   Business Intelligence Dashboard — `[x]`

------------------------------------------------------------------------

# Recommended Technology Stack

> Stack aktual yang terpasang: Laravel 11 REST API (JWT) + Next.js 16,
> PostgreSQL, Docker/CI-CD. Yang belum: AWS deployment, object storage,
> Python ML/LLM.

## Frontend

-   Next.js — `[x]` implemented di `apps/web/`
-   Responsive Web Application — `[x]`

## Backend

-   Laravel / NestJS — `[x]` Laravel REST API di `apps/api/`

## Database

-   PostgreSQL — `[x]`

## Mobile

-   Progressive Web App (PWA) — `[~]` manifest + icon tersedia; service
    worker/offline belum

## Cloud

-   AWS — `[ ]` belum tersedia (Docker/CI-CD sudah ada)

## AI Integration

-   OpenAI API — `[ ]` belum tersedia
-   Machine Learning Model — `[ ]` belum tersedia (saat ini deterministic
    PHP services)

------------------------------------------------------------------------

# Business Positioning

The platform should not compete as a normal marketplace.

The real value proposition:

> "A digital distribution network that connects brands with thousands of
> trusted retail outlets while providing sales intelligence and
> operational efficiency."

The main asset:

-   Distribution relationship
-   Outlet database
-   FMCG market knowledge
-   Sales execution capability

The goal is to become a **Distribution Network Owner**, not just a
retailer.
