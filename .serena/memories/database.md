# Database

PostgreSQL 16 in Docker (`DB_CONNECTION=pgsql`, host `db`, db `ddp_database`, user `ddp_user`). Tests use sqlite `:memory:`. Config: `apps/api/config/database.php` (`pgsql` block, `search_path=public`).

## Migrations
- 47 files in `apps/api/database/migrations/`, timestamps 2024-01-01 .. 2026-09-16. Several are ALTERs (indexes, added columns) rather than greenfield.
- Seeders: `DatabaseSeeder`, `ScaleFixtureSeeder`. Factories (7): Invoice, InvoiceReminder, Outlet, Product, Promotion, Supplier, User.
- No auto-migrate on container start; run `php artisan migrate --seed` (see `mem:suggested_commands`).

## Core tables & relationships
- `users` (role enum, `finance_role`, `jwt_version`, `territory_id`, `is_active`) 1:1 `outlets`/`suppliers`; 1:N `sales_visits`, `deliveries`, `role_assignment_audits`.
- `outlets` (unique phone, `canonical_phone`, `payment_term_days`, `territory_id`, category/score) belongs to user + territory; has credit_limit, orders, invoices.
- `orders` (unique `order_id`, unique `idempotency_key` 64, `idempotency_payload_hash`, `commission_percentage` snapshot, `due_date`, `paid_amount`, `promotion_id`, `discount_amount`, `sales_user_id`) 1:N `order_items` + `order_status_history` + `payments`; 1:1 `invoices` + `deliveries`.
- `invoices` (unique order_id) 1:N `invoice_reminders` (send-lease/idempotency fields).
- `deliveries` 1:N `delivery_status_histories` (state machine assigned -> in_progress -> delivered/failed).
- `products` (supplier_id, purchasable scope) 1:N `product_price_histories`.
- `promotions`, `sales_targets`, `territories`, `credit_limits`, `whatsapp_messages` (idempotency/claimed_at).
- BI: `data_pipeline_runs` -> `data_snapshots` -> `data_snapshot_values`; `data_metric_definitions`, `recommendation_events`, `forecast_actuals`.
- Ops: `operational_events` (correlation journal).
- Telescope: `telescope_entries*` (from `laravel/telescope`).

## Where CRUD happens
Almost entirely inside `app/Services/*` (not controllers), typically wrapped in `DB::transaction` + `lockForUpdate`. See `mem:api/domain`.
