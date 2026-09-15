# Pre-Pilot Compatibility Baseline

**Status:** repository-evidence baseline only. This is not a production-pilot activation record and does not claim partner activation, a two-week pilot window, or 100 valid orders.

## 1. Evidence boundary

The baseline records behavior visible in the existing Laravel API, persistence models/migrations, Next.js client, and feature tests. It is a compatibility contract for later regression work, not a proposal for new routes, roles, schema, or transaction behavior. The only role name for operational administration is existing `admin`; there is no `admin ops` role.

Primary route inventory: `apps/api/routes/api.php`. Primary HTTP evidence: `apps/api/tests/Feature/OperationalReadinessTest.php`, with focused suites cited below.

## 2. Existing role matrix

| Role | Existing evidence and boundaries |
|---|---|
| `admin` | JWT-authenticated administrator. Approves/cancels orders, lists/views all orders, assigns finance role, accesses admin analytics, finance metrics/history, pipeline status/trigger, territories and other admin surfaces, and can send/retry WhatsApp provider messages. `OrderController`, `AnalyticsController`, `DataPipelineController`, `FinanceRoleController`, and `WhatsAppController` enforce admin checks. |
| `outlet` | Created by `POST /api/auth/register`; creates orders through `POST /api/orders`; order/payment/invoice/finance history is outlet-scoped. Payment is allowed only for the outlet's order and current active role. WhatsApp catalog is associated with the authenticated outlet. |
| `sales` | Delivery assignment/status access is scoped to deliveries assigned by that sales user. Sales visit routes are also present and protected by `deny.finance`. |
| `driver` | Delivery list/detail/status access is scoped to assigned deliveries. Delivery completion requires the existing proof fields (`recipient_name` and `proof_of_delivery_url`). |
| `finance` | Assigned/revoked by admin through `/api/admin/users/{userId}/finance-role`; current active database role is authoritative, so removing the role denies an old token. Finance can access payment/invoice/reminder/metrics histories, but `deny.finance` blocks unrelated administration, order approval, delivery, product/outlet, credit-limit, and similar routes. |

Role source: `apps/api/app/Models/User.php`, `apps/api/app/Services/FinanceAuthorizationService.php`, `apps/api/app/Http/Middleware/DenyFinanceAdministration.php`, `apps/api/database/migrations/2024_01_01_000000_create_users_table.php`, `apps/api/tests/Feature/AuthTest.php`, `apps/api/tests/Feature/FinanceAccessTest.php`, and the role assertions in `apps/api/tests/Feature/OperationalReadinessTest.php`.

## 3. Endpoint and response contract

### Auth/RBAC

- `POST /api/auth/login`: `200`, `{status: success, data: {token, token_type: Bearer, expires_in, user:{id,name,email,role}}}`; invalid credentials `401`; missing fields `422`.
- `POST /api/auth/register`: `201`; creates an outlet user and outlet in one transaction and returns token/user/outlet data.
- `GET /api/auth/me`: `200` with current user identity; protected routes without/with invalid JWT return `401`.
- `POST /api/auth/logout`: `200` and invalidates the supplied token; `POST /api/auth/refresh`: `200` with replacement token data.
- `GET /api/finance/access`: current active finance role is required; denial is `403` with the existing finance message.

### Order and approval/invoice

- `POST /api/orders`: outlet-only. `201` on creation, `200` on idempotent replay. Response data contains `id`, server `order_id`, `outlet_id`, `status`, `total_amount`, `paid_amount`, `outstanding_balance`, `commission_percentage`, item rows, and timestamps.
- `GET /api/orders/{id}` and admin aliases return the same order fields plus `status_history`; an invoice is included when loaded. `GET /api/orders` returns `data` plus bounded `meta.limit`/`meta.has_more` for admin listing.
- `PUT /api/orders/{id}/approve`: admin-only; first approval of a `New` order transitions to `Confirmed`, creates one invoice, and returns `200`. Re-approval of an already-`Confirmed` order reuses the existing invoice and returns `200` (no duplicate status-history row). An invalid state other than `Confirmed` returns `422`; non-admin is `403`.
- `GET /api/invoices`: admin, finance, and outlet access with outlet scoping where applicable. `data` is paginated and `meta` contains `page`, `limit`, `total`, `has_more`.
- `PUT /api/orders/{id}/cancel`: successful cancellation returns formatted invoice; payment-row or incompatible invoice/order state returns `409`.

Relevant implementation: `apps/api/app/Http/Controllers/OrderController.php`, `apps/api/app/Services/OrderCreationService.php`, `apps/api/app/Services/InvoiceService.php`, `apps/api/app/Http/Controllers/InvoiceController.php`, `apps/api/tests/Feature/OrderTest.php`, `apps/api/tests/Feature/InvoiceTest.php`, and `apps/api/tests/Feature/OperationalReadinessTest.php`.

### Delivery

- `GET /api/deliveries`, `POST /api/deliveries`, `GET /api/deliveries/{id}`, and `PATCH/POST/PUT /api/deliveries/{id}/status` (or `PUT /api/deliveries/{id}`) are the existing surface.
- Assignment returns `201` and includes delivery/order/driver/assigner/status history. Missing order is `404`; non-`Confirmed` order, duplicate assignment, or inactive/non-driver is `422`.
- Status updates return `200` with status timestamps, proof, route data, related order, and history. Ownership denial is `403`; invalid transition is `422`.

Relevant implementation/tests: `apps/api/app/Http/Controllers/DeliveryController.php`, `apps/api/app/Models/Delivery.php`, `apps/api/tests/Feature/DeliveryTest.php`, `apps/api/tests/Feature/DeliveryAuthorizationTest.php`, `apps/api/tests/Feature/DeliveryConcurrencyTest.php`, and `apps/api/tests/Feature/OperationalReadinessTest.php`.

### Payment

- `POST /api/payments`: admin, finance, or owning outlet. New payment is `201`; idempotent replay is `200`. Response includes payment `id`, `order_id`, `outlet_id`, `amount`, `payment_method`, `status`, `receipt_reference`, `idempotency_key`, receipt data, and order paid/outstanding/status fields.
- `GET /api/payments`: paginated with `page`, `limit`, `total`, and `has_more`; outlet users see only their outlet, while admin/finance can query the broader history.
- Payments require delivered or partially-paid order state. Overpayment, cancelled/paid invoice, invalid amount, or invalid request is `422`; unauthorized access is `403`.

Relevant implementation/tests: `apps/api/app/Http/Controllers/PaymentController.php`, `apps/api/app/Services/PaymentService.php`, `apps/api/tests/Feature/PaymentTest.php`, `apps/api/tests/Feature/PaymentConcurrencyTest.php`, `apps/api/tests/Feature/DeliveryConcurrencyTest.php`, and `apps/api/tests/Feature/OperationalReadinessTest.php`.

### WhatsApp

- `POST /api/whatsapp/webhook` is public by transport but signature and sender checks are enforced by the WhatsApp service. It returns `400` for malformed JSON, `401` for missing/invalid signature, `422` for rejected sender/order input, `201` for a newly processed inbound order, and `200` for duplicate delivery.
- `POST /api/whatsapp/catalog`: authenticated outlet/admin path; success `200`, disabled integration `202`, provider failure `503`.
- `POST /api/whatsapp/orders/{orderId}/notification`: admin-only; non-confirmed order `409`, disabled integration `202`, provider failure `503`, success `200`.
- `POST /api/whatsapp/messages/{messageId}/retry`: admin-only; provider failure `503`, successful state response `200`.

Relevant implementation/config/tests: `apps/api/app/Http/Controllers/WhatsAppController.php`, `apps/api/app/Services/WhatsAppService.php`, `apps/api/app/Services/WhatsAppOutboundService.php`, `apps/api/config/whatsapp.php`, `apps/api/tests/Feature/WhatsAppTest.php`, and `apps/api/tests/Feature/WhatsAppPostgresConcurrencyTest.php`.

### Analytics/finance/pipeline

- `GET /api/analytics/dashboard` is admin-only (`403` otherwise), returns `status: success` and nested metrics/trends/performance plus descriptive aliases; unsafe or overlong date filters return `422`.
- `GET /api/finance/metrics` returns bounded date-window finance metrics for admin/finance or the active outlet scope. `GET /api/finance/reminders` and `/api/reminders` expose reminder history; invoice/payment histories are paginated and scoped.
- `GET /api/admin/pipeline/status` is admin-only and returns latest run, active snapshot version/UUID, and `Asia/Jakarta` window metadata. `POST /api/admin/pipeline/manual-trigger` returns `202` with a new run identity; an active run returns `409`.

Relevant implementation/tests: `apps/api/app/Http/Controllers/AnalyticsController.php`, `apps/api/app/Http/Controllers/FinanceMetricsController.php`, `apps/api/app/Http/Controllers/DataPipelineController.php`, `apps/api/app/Services/DataPipelineService.php`, `apps/api/tests/Feature/AnalyticsTest.php`, `apps/api/tests/Feature/InvoiceMetricsTest.php`, `apps/api/tests/Feature/InvoicePaymentHistoryTest.php`, `apps/api/tests/Feature/InvoiceReminderHistoryTest.php`, `apps/api/tests/Feature/DataPipelineTriggerTest.php`, and `apps/api/tests/Feature/OperationalReadinessTest.php`.

### Web

`apps/web/src/lib/api.ts` defines `apiUrl`, Bearer `authHeaders`, and local token storage. `apps/web/src/components/Sidebar.tsx` synchronizes role from `/auth/me`; `finance` sees finance navigation, `admin` sees all current navigation, and other authenticated roles do not see the admin-only data-intelligence item. Existing pages are under `apps/web/src/app/{orders,admin/orders,invoices,payments,delivery,analytics,data-intelligence}`. No browser/device compatibility claim is made by this baseline.

## 4. Idempotency, concurrency, retry, and lease cases

| Case | Existing guarantee/evidence |
|---|---|
| Order retry | `StoreOrderRequest` requires or derives a stable identity from outlet plus canonical items. `orders.idempotency_key` is unique. `OrderCreationService` retries expected unique-key races and reads back the winner. Same request returns `201` then `200`, one order, one item set, and one stock decrement. |
| Order/stock/credit concurrency | Order creation uses an outlet row lock, stable product locks, one transaction, stock reservation, and credit validation. `OrderTest::test_concurrent_same_identity_submissions_return_one_order_result` verifies `[200, 201]` and one persisted order. |
| Approval/invoice retry | Approval locks the order and calls invoice creation in the same transaction. `invoices.order_id` is unique and `InvoiceService` locks/reuses an existing invoice. `InvoiceTest` and `OperationalReadinessTest` verify one invoice and one `Confirmed` history row after retries. |
| Concurrent approval | `OrderController` has bounded transaction retry; `InvoiceConcurrencyTest` verifies two HTTP workers converge on one invoice/history record. The SQLite race harness documents serialization limits; it is not evidence of production database locking semantics. |
| Payment replay/concurrency | `payments.idempotency_key` is unique. `PaymentService` locks outlet then order then payment/invoice rows, calculates money in cents, replays matching identity, and rejects identity reuse with changed order/amount/method. `PaymentConcurrencyTest` verifies `[200, 201]`, one payment, and consistent invoice/order balances. |
| Delivery/payment race | Delivery completion locks delivery then order before changing order status/history. `DeliveryConcurrencyTest` verifies matching final status/history when delivery and payment contend. |
| WhatsApp inbound idempotency | `provider_message_id` and service-level processing identity prevent duplicate inbound orders; repeated webhook returns duplicate behavior. PostgreSQL concurrency coverage is in `WhatsAppPostgresConcurrencyTest`. |
| WhatsApp outbound idempotency | `logical_key` is unique for order confirmation and `provider_idempotency_key` is stable. `insertOrIgnore` plus row lock converges concurrent notifications to one logical message. |
| WhatsApp send lease | `claimed_at` and `whatsapp.send_lease_seconds` prevent a second provider request while a lease is fresh; stale `sending` rows are reclaimable. `WhatsAppTest::test_fresh_sending_lease_is_not_duplicated_but_stale_unknown_send_is_reclaimed` verifies this. |
| Reminder retry | Invoice reminder identity is unique and retries reuse the same idempotency key. Existing operational tests verify bounded attempts, final failure state, and no invoice mutation. Dedicated PostgreSQL reminder concurrency coverage is `InvoiceReminderPostgresConcurrencyTest`. |
| Pipeline overlap/publication | `DataPipelineService` rejects active runs, publishes complete snapshots transactionally, and leaves the previous active snapshot when a run fails. `DataPipelineTriggerTest` verifies `409` overlap, `202` accepted trigger, and active snapshot/window behavior. |

## 5. Source-of-truth boundaries

- **Identity/RBAC:** `users` current `role` and `is_active`, with JWT used for authentication. `FinanceAuthorizationService` re-queries current role; an old finance token does not preserve access after role removal.
- **Outlet scope:** `outlets` and the `User::outlet` relation. Order, invoice, payment, reminder, and WhatsApp associations use outlet IDs/relations; outlet history endpoints filter by the authenticated outlet.
- **Order transaction:** `orders`, `order_items`, `order_status_history`, and `products` are the transaction boundary for order creation, stock reservation, status history, and commission snapshot. `OrderCreationService` is the write orchestrator.
- **Approval/invoice:** `orders.status` plus `invoices` (one invoice per order) are authoritative. `InvoiceService` creates/reuses invoices and cancellation is transactional.
- **Delivery:** `deliveries` and `delivery_status_histories` are authoritative for delivery state/proof; delivery completion appends the corresponding order status history.
- **Payment:** `payments` are the payment rows; `orders.paid_amount/status` and `invoices.paid_amount/balance_amount/status` are updated under the payment transaction. No analytics/read surface is a financial ledger replacement.
- **WhatsApp:** `whatsapp_messages` is the message attempt/state record; provider IDs, logical keys, attempts, `claimed_at`, `sent_at`, and errors describe delivery. Provider response is not an alternate order/payment source.
- **Analytics/finance:** dashboard and finance metrics are derived from existing order/payment/invoice/reminder rows. They are read models and must not mutate source transactions.
- **Pipeline/data intelligence:** `data_pipeline_runs` records execution; `data_snapshots` and `data_snapshot_values` hold immutable published derived snapshots; the active snapshot reader is not source transaction truth.
- **Web/config:** web state and local token storage are client concerns. `apps/api/config/*.php` controls defaults and integration behavior; configuration does not replace persisted transaction state.

## 6. Baseline commands

From repository root:

```bash
cd apps/api && php artisan test --filter='AuthTest|FinanceAccessTest|OrderTest|InvoiceTest|PaymentTest|PaymentConcurrencyTest|DeliveryTest|DeliveryAuthorizationTest|DeliveryConcurrencyTest|WhatsAppTest|AnalyticsTest|InvoiceMetricsTest|DataPipelineTriggerTest|OperationalReadinessTest'
cd apps/web && npm run lint && npm test -- --runInBand
```

Focused PostgreSQL concurrency tests require a dedicated migrated PostgreSQL test environment and are not claimed green merely because the command is listed:

```bash
cd apps/api && DB_CONNECTION=pgsql php artisan test --filter='WhatsAppPostgresConcurrencyTest|InvoiceReminderPostgresConcurrencyTest'
```

## 7. Explicit gaps and assumptions

- **Gap — full route-role matrix:** route declarations and selected controllers/tests were inspected, but every non-core controller response was not exhaustively normalized here. Use the matrix row command and the focused test file as the regression boundary rather than inferring undocumented behavior.
- **Gap — OrderTest re-approval expectation:** `OrderTest::test_cannot_approve_already_confirmed_order` asserts `422` on re-approval, but the current `OrderController::approveInTransaction` returns `200` with invoice reuse. `InvoiceTest::test_approval_retry_reuses_invoice` and `InvoiceTest::test_concurrent_approvals_create_one_invoice` both verify `[200, 200]` and one invoice. The implementation behavior (200) is the compatibility baseline; the stale `OrderTest` assertion is a test-to-implementation mismatch.
- **Gap — PostgreSQL runtime evidence:** the repository includes PostgreSQL concurrency tests, but their result depends on external database credentials/configuration. SQLite race tests explicitly do not prove PostgreSQL row-lock behavior.
- **Gap — web test coverage:** the package declares Jest and TypeScript checks, but this baseline does not claim all pages have dedicated tests. `data-intelligence` has visible component/page tests; the command is a verification gate, not a recorded result.
- **Assumption — configuration parity:** test and deployment environments must preserve the existing `auth:api`, database, order, and WhatsApp configuration keys. No new defaults are introduced by T1.
- **Assumption — provider semantics:** a WhatsApp provider honors the stable idempotency key at its boundary. The application prevents duplicate logical attempts and reuses the key, but provider-side delivery is external.
- **Gap — scheduler liveness:** `DataPipelineTriggerTest` verifies registration at `02:00` in `Asia/Jakarta`; it does not prove a production worker is running.
- **Gap — operational feature activation:** no feature flag, kill switch, partner activation, production pilot, two-week window, or 100-order result is established by this baseline.

## 8. Compatibility decision

Preserve all listed existing routes, role names, status codes, response fields, source tables, idempotency identities, locks, retries, and leases. Any future additive operational evidence must remain outside the transaction source-of-truth boundaries and must not require changing existing order, approval/invoice, delivery, payment, WhatsApp, analytics, finance, pipeline, web, schema, or configuration behavior.
