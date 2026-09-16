# Task T1 — Database Migrations for Phase 7

**Phase:** 1
**Depends:** none
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 1: Database Migrations for Phase 7 [prereq]

## OBJECTIVE
Create all additive database migrations needed by Phase 7 feature groups. Every migration must be reversible and non-destructive to existing data.

Files:
- Create: `apps/api/database/migrations/2026_09_16_000001_add_platform_owner_to_users_role_enum.php`
- Create: `apps/api/database/migrations/2026_09_16_000002_add_territory_id_to_users_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000003_add_category_and_score_to_outlets_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000004_create_product_price_histories_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000005_add_sales_user_id_to_orders_table.php`
- Create: `apps/api/database/migrations/2026_09_16_000006_add_promo_broadcast_to_whatsapp_messages_message_type.php`

(Note: promotions + sales_targets tables are created in T5 and T6 respectively — not here — because their RED cycles must create them.)

Steps:
1. Create migration: add `platform_owner` and `finance` to users.role enum.
   - Postgres: `ALTER TYPE ... ADD VALUE IF NOT EXISTS 'platform_owner'` and `'finance'`
   - Down: document non-reversibility (enum values cannot be removed in Postgres)
   - Verify: `php artisan migrate --pretend` shows no errors

2. Create migration: add nullable `territory_id` FK to users table.
   - Down: drop column

3. Create migration: add nullable `category` (default 'lainnya') and `score` (default 0, integer) to outlets table.
   - Down: drop columns

4. Create migration: create `product_price_histories` table.
   - Columns: id, product_id FK, old_price decimal(12,2), new_price decimal(12,2), changed_by FK users, changed_at timestamp, timestamps
   - Down: drop table

5. Create migration: add nullable `sales_user_id` FK to orders table.
   - Down: drop column

6. Create migration: add `promo_broadcast` to whatsapp_messages.message_type enum.
   - Postgres: `ALTER TYPE ... ADD VALUE IF NOT EXISTS 'promo_broadcast'`
   - Down: document non-reversibility

7. Verify promotions table design: `broadcast_at` is included as a nullable timestamp column in T5's `create_promotions_table` migration — no separate T1 migration for it.

8. Verify all migrations run: `php artisan migrate:fresh`
9. Verify all rollbacks: `php artisan migrate:rollback`
10. Commit: `git add apps/api/database/migrations/ && git commit -m "chore(db): add Phase 7 additive migrations for roles, outlets, products, promotions, orders, and whatsapp"`

[no-tdd — structural task]

## REFERENCES LOADED
- spec — Context → Related Areas: users table has enum `['admin','supplier','outlet','sales','driver']`; whatsapp_messages has message_type enum
- apps/api/database/migrations/2024_01_01_000000_create_users_table.php — existing role enum definition
- apps/api/database/migrations/2026_09_08_000014_create_whatsapp_messages_table.php — existing message_type enum

## WHY THIS APPROACH
All migrations are additive (new columns nullable with defaults, new tables). Postgres enum ADD VALUE is non-transactional but safe for new values. No existing rows affected.

Complexity: lightweight

## SANDWICH CONTEXT
[CRITICAL: All migrations must be additive — no dropping columns or removing enum values from existing tables]
You are implementing database migrations for Phase 7 MVP Completion.
Spec: docs/pocket/spec/2026-09-16-phase7-mvp-completion/phase7-mvp-completion.md
Design decision: Incremental service-layer extension with dedicated controllers per domain.
Files in scope: apps/api/database/migrations/ (new files only)
Available after: none (prereq)
Architecture rule: Postgres enum migrations are additive and non-transactional; document non-reversible down-migrations
[RESTATE: All migrations must be additive — no destructive changes to existing schema]

## DELIVERABLE
Given all Phase 7 migrations exist, When `php artisan migrate:fresh` runs, Then all tables/columns/enums are created without error
Given all Phase 7 migrations exist, When `php artisan migrate:rollback` runs, Then rollback completes for reversible migrations
Given the users table exists, When platform_owner role is added, Then existing rows are unaffected (new enum value only)

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
- Every migration is reversible where Postgres allows (document non-reversible enum down-migrations)
- New columns are nullable with defaults where applicable
- No existing data is modified or deleted
- Foreign keys have appropriate indexes

Must-not-have:
- Destructive changes to existing tables (no DROP COLUMN on populated tables)
- Non-nullable columns without defaults on existing tables
- Migrations that require downtime

Rollback note:
- Enum migrations (platform_owner, promo_broadcast) are non-reversible in Postgres — documented in migration files
- All other migrations are standard up/down

## STOP CONDITIONS
Done when: all migrations created, `php artisan migrate:fresh` succeeds, `php artisan migrate:rollback` completes
Escalate when: migration requires destructive change to existing data
