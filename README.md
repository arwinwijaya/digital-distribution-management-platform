# Digital Distribution Management Platform

A FMCG Digital Distribution Ecosystem connecting suppliers, distributors, sales teams, and thousands of retail outlets/warungs through a single platform.

## Project Structure

This is a monorepo containing:

```
├── apps/
│   ├── api/           # Laravel API backend
│   └── web/           # Next.js frontend
├── packages/
│   └── shared/        # Shared types and utilities
├── docs/              # Documentation
└── docker-compose.yml # Docker configuration
```

## Tech Stack

- **Frontend**: Next.js 14 + React 18 + TypeScript + Tailwind CSS + Zustand + Axios
- **Backend**: Laravel 11 + PHP 8.2 + JWT (`tymon/jwt-auth`)
- **Database**: PostgreSQL 16
- **Cache**: Redis 7
- **AI & Analytics**: Deterministic bounded services (forecast, recommendation, segmentation, data intelligence pipeline)
- **Messaging**: WhatsApp Cloud API integration
- **Containerization**: Docker + Docker Compose

## Prerequisites

- Node.js 20+
- PHP 8.2+
- Composer
- PostgreSQL 16+
- Redis 7+
- Docker & Docker Compose (optional)

## Quick Start

### Using Docker (Recommended)

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd digital-distribution-management-platform
   ```

2. Start all services:
   ```bash
   docker compose up -d
   ```

3. Access the applications:
   - Web: http://localhost:3000
   - API: http://localhost:8000 (health: `/api/health`)
   - Database: localhost:5432
   - Redis: localhost:6379
   - Mail catcher (Mailpit): http://localhost:8025 (SMTP on 1025)

4. Common commands:
   ```bash
   docker compose ps            # service status + health
   docker compose logs -f web   # follow a service
   docker compose down          # stop the stack (add -v to wipe data volumes)
   docker compose up -d --build # rebuild images after Dockerfile changes
   ```

> **Note:** `api` and `web` bind-mount their source directories for live reload, so
> `vendor/` and `node_modules/` come from the mounted tree, not the image. The
> image supplies the runtime (PHP extensions incl. `phpredis`, Node runtime).
> Health checks use a generous timeout because Laravel/Next boot is slow over a
> Windows bind mount.

### Manual Setup

#### API (Laravel)

1. Navigate to the API directory:
   ```bash
   cd apps/api
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy environment file:
   ```bash
   cp .env.example .env
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Configure database in `.env` file

6. Run migrations:
   ```bash
   php artisan migrate
   ```

7. Start the development server:
   ```bash
   php artisan serve
   ```

#### Web (Next.js)

1. Navigate to the web directory:
   ```bash
   cd apps/web
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Start the development server:
   ```bash
   npm run dev
   ```

## API Documentation

### Authentication

#### Login
```
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "token": "eyJ...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "role": "admin"
    }
  }
}
```

#### Get User Profile
```
GET /api/auth/me
Authorization: Bearer <token>
```

#### Logout
```
POST /api/auth/logout
Authorization: Bearer <token>
```

### Health Check
```
GET /api/health
```

## Database Schema

35 migrations supporting the full ecosystem:

### Core Tables
- **users** — role enum (admin, supplier, outlet, sales, driver), `finance_role` (nullable)
- **outlets** — linked to user, territory, credit limit; `payment_term_days`, `canonical_phone`
- **products** — linked to supplier; purchasable scope
- **suppliers** — `lead_time_days`
- **orders** → **order_items** → **order_status_history** — full audit trail, `commission_percentage` snapshot, `due_date`
- **payments** — linked to order
- **credit_limits** — per-outlet credit cap
- **deliveries** → **delivery_status_histories** — state machine (assigned → in_progress → delivered/failed)
- **sales_visits** — planning & history per sales user
- **whatsapp_messages** — idempotency fields (`claimed_at`)

### Invoice & Finance
- **invoices** — auto-created from confirmed orders
- **invoice_reminders** — jatuh tempo tracking
- **role_assignment_audits** — audit trail for finance role changes

### Data Intelligence
- **territories** — geographic distribution zones
- **data_pipeline_runs** → **data_snapshots** → **data_snapshot_values** — BI pipeline
- **metric_definitions** — configurable metrics
- **recommendation_events** — AI recommendation tracking
- **forecast_actuals** — forecast accuracy measurement

## Testing

### API Tests
```bash
cd apps/api
php artisan test
```

### Web Tests
```bash
cd apps/web
npm run test
```

## CI/CD

The project includes GitHub Actions workflows for:
- API tests (PHPUnit)
- Web tests (Jest)
- Docker build

See `.github/workflows/ci.yml` for details.

## Development Phases

- **Phase 0**: Foundation & Infrastructure ✅
- **Phase 1**: Outlet Onboarding & Product Discovery ✅
- **Phase 2**: Order Management & Transaction ✅
- **Phase 3**: Data Intelligence & AI Foundation ✅ (T1–T9 executed)
- **Phase 4**: Finance & Invoice Management ✅ (payment terms, invoice reminders, finance roles)
- **Phase 5**: Territory & Geographic BI ✅ (territory management, geographic analytics, supplier performance, stock planning)

### Key Features

| Module | Description |
|---|---|
| **Multi-supplier Marketplace** | Suppliers list products, outlets browse & order across brands |
| **Order Pipeline** | Full lifecycle: order → approve → deliver → pay, with status audit |
| **Credit Limit System** | Per-outlet credit cap enforced at order creation |
| **WhatsApp Integration** | Inbound ordering via chat, outbound notifications, catalog sharing |
| **AI & Analytics** | Deterministic forecast, recommendation, segmentation (no LLM dependency) |
| **Data Intelligence Pipeline** | Automated snapshot-based BI: geographic, supplier, stock, measurement |
| **Invoice & Finance** | Invoice generation, reminders, finance role management |
| **Territory Management** | Geographic zones, outlet assignment, coverage analytics |

## License

Proprietary - All rights reserved.
