# API Domain: Controllers, Services, Models

## Controllers (`app/Http/Controllers/`, 33)
Auth: `AuthController` (registerOutlet/login/me/update/logout/refresh). Orders: `OrderController` (store/index/show/approve/cancel), `SalesOrderController`, `SalesOutletController`, `SalesPerformanceController`, `SalesTargetController`, `SalesController`. Finance: `InvoiceController`, `InvoiceReminderController`, `FinanceMetricsController`, `FinanceRoleController`, `CreditLimitController`, `PaymentController`. Catalog/admin: `ProductController`, `AdminProductController`, `OutletController`, `AdminOutletController`, `MarketplaceController`, `PromotionController`, `PromotionBroadcastController`, `TerritoryController`, `UserRoleController`. Delivery: `DeliveryController`. Analytics/BI: `AnalyticsController`, `AIController`, `GeographicAnalyticsController`, `StockPlanningController`, `SupplierPerformanceController`, `MeasurementController`, `DataPipelineController`. Messaging/ops: `WhatsAppController`, `OperationalReadinessController`. Base: `Controller`.

## Services (`app/Services/`, 43) - all business logic
- Order/credit: `OrderCreationService` (transactional + idempotent + credit + promotion snapshot + stock reservation), `CreditLimitService`, `RoutingService`.
- Invoice/finance: `InvoiceService`, `InvoiceMetricsService`, `InvoiceBackfillService`, `FinanceAuthorizationService`, `FinanceRoleService`.
- Reminders: `InvoiceReminderService` orchestrates `InvoiceReminderCandidateSelector`, `InvoiceReminderClaimService`, `InvoiceReminderStateService` (send-lease based, idempotent).
- Payment: `PaymentService`, `ReceiptService`.
- Sales: `SalesPerformanceService`, `TerritoryController`-backed services, `SegmentationService`.
- Analytics/BI: `AnalyticsService`, `ForecastService`, `RecommendationService`, `GeographicAnalyticsService`, `StockPlanningService`, `SupplierPerformanceService`, `ActiveDataSnapshotReader`, `DataPipelineService`, `MeasurementService`.
- Promotions: `PromotionService`.
- WhatsApp: `WhatsAppService`, `WhatsAppHttpClient` (impl of `App\Contracts\WhatsAppClient`), `WhatsAppOutboundService`, `WhatsAppPayloadParser`, `WhatsAppSenderResolver`.
- Ops/pilot: `PrePilotFeatureGate`, `PilotEvaluationService`, `PilotMetricsService`, `PilotQualificationService`, `OperationalReadinessService`, `OperationalIssueService`, `OperationalEventService`, `CalendarService`, `OutletScoringService`, `ProductPriceService`, `AuthService`.

## Models (`app/Models/`, 26)
`User` (JWTSubject; roles enum + `finance_role`, `jwt_version`, `territory_id`), `Outlet`, `Product`, `Supplier`, `Order`, `OrderItem`, `OrderStatusHistory`, `Payment`, `CreditLimit`, `Invoice`, `InvoiceReminder`, `Delivery`, `DeliveryStatusHistory`, `SalesVisit`, `SalesTarget`, `Promotion`, `ProductPriceHistory`, `Territory`, `RoleAssignmentAudit`, `WhatsAppMessage`, `OperationalEvent`, `DataMetricDefinition`, `DataPipelineRun`, `DataSnapshot`, `DataSnapshotValue`, `RecommendationEvent`.

## Critical domain invariants
- Order creation (`OrderCreationService::create`): 3-attempt loop; inside `DB::transaction` locks the outlet row (`lockForUpdate`), checks `orders.idempotency_key` for replay (read-back on unique-race `QueryException`), validates same payload via `idempotency_payload_hash` (`hash_equals`), locks/validates products without mutation, applies promotion (min_order + window) with `discount_amount` snapshot, runs `CreditLimitService::assertCanPlace` on post-discount total, reserves stock, persists order + items + status history.
- `CreditLimitService`: sums outstanding of orders in statuses `New,Confirmed,Delivered,Partially Paid` under lock; no `CreditLimit` row => unlimited.
- `InvoiceService`: `createForApprovedOrder` reuses by `order_id` (unique); `cancelOrder` is atomic and blocks when any payment row exists.
- `Order::generateUniqueOrderId()` format `ORD-YYYYMMDD-XXXXX`.
