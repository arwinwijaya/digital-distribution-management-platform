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

- **Frontend**: Next.js 14 + TypeScript + Tailwind CSS
- **Backend**: Laravel 11 + PHP 8.2
- **Database**: PostgreSQL 16
- **Cache**: Redis 7
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
   docker-compose up -d
   ```

3. Access the applications:
   - Web: http://localhost:3000
   - API: http://localhost:8000
   - Database: localhost:5432

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

### Users Table
- id (bigint, primary key)
- name (string)
- email (string, unique)
- email_verified_at (timestamp, nullable)
- password (string, hashed)
- role (enum: admin, supplier, outlet, sales, driver)
- phone (string, nullable)
- is_active (boolean)
- created_at (timestamp)
- updated_at (timestamp)

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

- **Phase 0**: Foundation & Infrastructure (Current)
- **Phase 1**: Outlet Onboarding & Product Discovery
- **Phase 2**: Order Management & Transaction
- **Phase 3**: Payment & Credit Management
- **Phase 4**: Dashboard & Analytics
- **Phase 5**: Sales Force & Delivery
- **Phase 6**: WhatsApp Integration
- **Phase 7**: AI & Intelligence
- **Phase 8**: Polish & Scale

## License

Proprietary - All rights reserved.
