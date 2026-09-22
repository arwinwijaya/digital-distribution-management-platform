<?php

use App\Http\Controllers\AdminOutletController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditLimitController;
use App\Http\Controllers\DataPipelineController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DriverRosterController;
use App\Http\Controllers\FinanceRoleController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\FinanceMetricsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PromotionBroadcastController;
use App\Http\Controllers\RbacMatrixController;
use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\GeographicAnalyticsController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\StockPlanningController;
use App\Http\Controllers\SupplierPerformanceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesOutletController;
use App\Http\Controllers\SalesTargetController;
use App\Http\Controllers\SalesPerformanceController;
use App\Http\Controllers\InvoiceReminderController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\OperationalReadinessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/auth/register', [AuthController::class, 'registerOutlet']);
Route::post('/auth/login', [AuthController::class, 'login']);
// Provider delivery is public by transport, but the controller verifies its HMAC signature.
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Stale JWT rejection must run after auth is resolved but before
    // any request body is processed.
    Route::middleware('reject.stale_jwt')->group(function () {
    // Admin product price management (F3)
    Route::patch('/admin/products/{id}', [AdminProductController::class, 'update'])->middleware('rbac:admin_products:edit');
    Route::get('/admin/products/{id}/prices', [AdminProductController::class, 'prices'])->middleware('rbac:admin_products:read');

    // Admin outlet management (CRUD, purchase history, scoring)
    Route::get('/admin/outlets', [AdminOutletController::class, 'index'])->middleware('rbac:outlets:read');
    Route::post('/admin/outlets', [AdminOutletController::class, 'store'])->middleware('rbac:outlets:edit');
    Route::patch('/admin/outlets/{id}', [AdminOutletController::class, 'update'])->middleware('rbac:outlets:edit');
    Route::get('/admin/outlets/{outletId}/orders', [AdminOutletController::class, 'orders'])->middleware('rbac:outlets:read');
    Route::get('/admin/outlets/{outletId}/summary', [AdminOutletController::class, 'summary'])->middleware('rbac:outlets:read');

    // Sales order collection (F5): thin wrapper over OrderCreationService with territory binding
    Route::get('/sales/outlets', [SalesOutletController::class, 'index'])->middleware('rbac:sales:read');
    Route::post('/sales/orders', [SalesOrderController::class, 'store'])->middleware('rbac:sales:edit');
    Route::get('/sales/my-performance', [SalesPerformanceController::class, 'myPerformance'])->middleware('rbac:sales:read');

    // Sales targets (F5): admin-only CRUD
    Route::post('/admin/sales-targets', [SalesTargetController::class, 'store'])->middleware('rbac:admin_sales_performance:edit');
    Route::get('/admin/sales-targets', [SalesTargetController::class, 'index'])->middleware('rbac:admin_sales_performance:read');
    Route::get('/admin/sales-targets/{id}', [SalesTargetController::class, 'show'])->middleware('rbac:admin_sales_performance:read');
    Route::patch('/admin/sales-targets/{id}', [SalesTargetController::class, 'update'])->middleware('rbac:admin_sales_performance:edit');
    Route::delete('/admin/sales-targets/{id}', [SalesTargetController::class, 'destroy'])->middleware('rbac:admin_sales_performance:edit');

    // Sales performance (F5): admin-only all-sales view
    Route::get('/admin/sales/performance', [SalesPerformanceController::class, 'adminPerformance'])->middleware('rbac:admin_sales_performance:read');

    // Promotion management (F4): full CRUD admin-only
    Route::get('/admin/promotions', [PromotionController::class, 'index'])->middleware('rbac:admin_promotions:read');
    Route::post('/admin/promotions', [PromotionController::class, 'store'])->middleware('rbac:admin_promotions:edit');
    Route::get('/admin/promotions/{id}', [PromotionController::class, 'show'])->middleware('rbac:admin_promotions:read');
    Route::patch('/admin/promotions/{id}', [PromotionController::class, 'update'])->middleware('rbac:admin_promotions:edit');
    Route::delete('/admin/promotions/{id}', [PromotionController::class, 'destroy'])->middleware('rbac:admin_promotions:edit');
    Route::post('/admin/promotions/{id}/broadcast', [PromotionBroadcastController::class, 'broadcast'])->middleware('rbac:admin_promotions:edit');

    // Admin-controlled finance role assignment and removal (K-B: menu gate only;
    // the controller's assertAdmin remains the real mutation authorization).
    Route::post('/admin/users/{userId}/finance-role', [FinanceRoleController::class, 'assign'])->middleware('rbac:admin_users:read');
    Route::delete('/admin/users/{userId}/finance-role', [FinanceRoleController::class, 'remove'])->middleware('rbac:admin_users:read');

    // General user listing + role assignment (F1 — platform_owner is superset of admin).
    // K-B: mutations stay gated by the controller (isPlatformOwner).
    Route::get('/admin/users', [UserRoleController::class, 'index'])->middleware('rbac:admin_users:read');
    Route::patch('/admin/users/{userId}/role', [UserRoleController::class, 'assignRole'])->middleware('rbac:admin_users:read');

    // RBAC menu matrix (owner+admin). Authorized at controller level (K-A) —
    // deliberately NOT guarded by `rbac:` middleware.
    Route::get('/admin/rbac/matrix', [RbacMatrixController::class, 'index']);
    Route::put('/admin/rbac/matrix', [RbacMatrixController::class, 'update']);
    // Payment terms are administrator-controlled and outlet-scoped.
    Route::get('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'showPaymentTerms'])->middleware('rbac:outlets:read');
    Route::put('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'updatePaymentTerms'])->middleware('rbac:outlets:edit');
    Route::post('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'updatePaymentTerms'])->middleware('rbac:outlets:edit');

    // Legacy outlet/catalog endpoints remain available only to authenticated clients.
    // K-C: self-service outlet registration is authorized at controller level
    // (finance is rejected there). A `rbac:outlets:edit` gate would wrongly
    // require the outlet role to hold `edit` on the `outlets` menu.
    Route::post('/outlets', [OutletController::class, 'store']);
    Route::get('/products', [ProductController::class, 'index'])->middleware('rbac:products:read');
    Route::prefix('marketplace')->group(function () {
        Route::get('/suppliers', [MarketplaceController::class, 'suppliers'])->middleware('rbac:marketplace:read');
        Route::get('/products', [MarketplaceController::class, 'products'])->middleware('rbac:marketplace:read');
    });
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'update']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Order routes
    Route::post('/orders', [OrderController::class, 'store'])->middleware('rbac:orders:edit');
    // The controller authorizes this list for admins and does not require an outlet relation.
    Route::get('/orders', [OrderController::class, 'index'])->middleware('rbac:orders:read');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->middleware('rbac:orders:read');
    // Explicit admin alias for clients that keep admin APIs under /admin.
    Route::get('/admin/orders', [OrderController::class, 'index'])->middleware('rbac:admin_orders:read');
    Route::get('/admin/orders/{id}', [OrderController::class, 'show'])->middleware('rbac:admin_orders:read');
    Route::put('/orders/{id}/approve', [OrderController::class, 'approve'])->middleware('rbac:admin_orders:edit');
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('rbac:orders:edit');

    // Invoice history is available to current admin, finance, and outlet roles;
    // the controller applies the corresponding outlet scope.
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('rbac:invoices:read');

    // Sales visit planning routes (controller scopes sales users to their own visits).
    Route::get('/sales/visits', [SalesController::class, 'index'])->middleware('rbac:sales:read');
    Route::post('/sales/visits', [SalesController::class, 'store'])->middleware('rbac:sales:edit');
    Route::get('/sales/visits/{id}', [SalesController::class, 'show'])->middleware('rbac:sales:read');
    Route::patch('/sales/visits/{id}', [SalesController::class, 'update'])->middleware('rbac:sales:edit');

    // Driver roster (Phase 8, T5): admin-only CRUD over driver_profiles.
    Route::get('/admin/drivers', [DriverRosterController::class, 'index'])->middleware('rbac:driver_roster:read');
    Route::post('/admin/drivers', [DriverRosterController::class, 'store'])->middleware('rbac:driver_roster:edit');
    Route::patch('/admin/drivers/{id}', [DriverRosterController::class, 'update'])->middleware('rbac:driver_roster:edit');
    Route::delete('/admin/drivers/{id}', [DriverRosterController::class, 'destroy'])->middleware('rbac:driver_roster:edit');

    // Delivery assignment and auditable lifecycle routes.
    Route::get('/deliveries', [DeliveryController::class, 'index'])->middleware('rbac:delivery:read');
    Route::post('/deliveries', [DeliveryController::class, 'store'])->middleware('rbac:delivery:edit');
    Route::get('/deliveries/{id}', [DeliveryController::class, 'show'])->middleware('rbac:delivery:read');
    Route::patch('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus'])->middleware('rbac:delivery:edit');
    Route::post('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus'])->middleware('rbac:delivery:edit');
    Route::put('/deliveries/{id}', [DeliveryController::class, 'updateStatus'])->middleware('rbac:delivery:edit');

    // Payment routes
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('rbac:payments:edit');
    Route::get('/payments', [PaymentController::class, 'index'])->middleware('rbac:payments:read');

    // Owner analytics routes
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])->middleware('rbac:analytics:read');
    Route::get('/analytics/insight', [AnalyticsController::class, 'insight'])->middleware('rbac:analytics:read');

    // Deterministic, bounded AI-assisted analytics for outlet and admin contexts.
    // K-C: dual-audience (outlet OR admin) — authorization lives in AIController
    // (isAdmin() || isOutlet()), so no single `rbac:` menu gate applies.
    Route::prefix('ai')->group(function () {
        Route::get('/recommendations', [AIController::class, 'recommendations']);
        Route::get('/forecast', [AIController::class, 'forecast']);
        Route::get('/segmentation', [AIController::class, 'segmentation']);
    });

    // WhatsApp outbound operations use the authenticated outlet/admin identity.
    Route::post('/whatsapp/catalog', [WhatsAppController::class, 'catalog'])->middleware('rbac:orders:edit');
    Route::post('/whatsapp/orders/{orderId}/notification', [WhatsAppController::class, 'notify'])->middleware('rbac:orders:edit');
    Route::post('/whatsapp/messages/{messageId}/retry', [WhatsAppController::class, 'retry'])->middleware('rbac:orders:edit');

    Route::get('/finance/metrics', [FinanceMetricsController::class, 'index'])->middleware('rbac:invoices:read');
    Route::get('/finance/reminders', [InvoiceReminderController::class, 'index'])->middleware('rbac:invoices:read');
    Route::get('/reminders', [InvoiceReminderController::class, 'index'])->middleware('rbac:invoices:read');

    // Admin-only data pipeline status and manual trigger.
    // K-C: the menu gate is `read` (admin holds read on data_intelligence); the
    // real mutation authorization is the controller's isAdmin() guard.
    Route::prefix('admin/pipeline')->group(function () {
        Route::get('/status', [DataPipelineController::class, 'status'])->middleware('rbac:data_intelligence:read');
        Route::post('/manual-trigger', [DataPipelineController::class, 'manualTrigger'])->middleware('rbac:data_intelligence:read');
    });

    // Geographic BI and territory management routes (admin-only).
    // K-C: mutations are gated at `read` level (admin holds analytics:read);
    // TerritoryController enforces the admin boundary for writes.
    Route::get('/admin/analytics/geographic', [GeographicAnalyticsController::class, 'index'])->middleware('rbac:data_intelligence:read');
    Route::get('/admin/territories', [TerritoryController::class, 'index'])->middleware('rbac:analytics:read');
    Route::post('/admin/territories', [TerritoryController::class, 'store'])->middleware('rbac:analytics:read');
    Route::patch('/admin/territories/{territoryId}', [TerritoryController::class, 'update'])->middleware('rbac:analytics:read');
    Route::post('/admin/territories/{territoryId}/assign', [TerritoryController::class, 'assign'])->middleware('rbac:analytics:read');

    // Supplier performance BI (admin-only via controller boundary, reads active snapshot)
    Route::get('/admin/analytics/suppliers', [SupplierPerformanceController::class, 'index'])->middleware('rbac:data_intelligence:read');

    // Stock planning BI (admin-only via controller boundary, reads active snapshot)
    Route::get('/admin/analytics/stock-planning', [StockPlanningController::class, 'index'])->middleware('rbac:data_intelligence:read');

    // Measurement ingestion and admin-only BI (controller enforces admin boundary).
    // K-C: menu gate is `read`; MeasurementController::storeEvent() enforces admin.
    Route::post('/admin/measurement/events', [MeasurementController::class, 'storeEvent'])->middleware('rbac:data_intelligence:read');
    Route::get('/admin/analytics/measurement/recommendations', [MeasurementController::class, 'recommendations'])->middleware('rbac:data_intelligence:read');
    Route::get('/admin/analytics/measurement/forecasts', [MeasurementController::class, 'forecasts'])->middleware('rbac:data_intelligence:read');

    // Pre-pilot operational diagnostics (admin-only, gated)
    Route::prefix('admin/operations')->middleware('pre_pilot')->group(function () {
        Route::get('/readiness', [OperationalReadinessController::class, 'readiness'])->middleware('rbac:operations:read');
        Route::get('/issues', [OperationalReadinessController::class, 'issues'])->middleware('rbac:operations:read');
        Route::get('/issues/{id}', [OperationalReadinessController::class, 'issueDetail'])->middleware('rbac:operations:read');
    });

    // Credit limit and outstanding balance routes
    Route::get('/credit-limit', [CreditLimitController::class, 'show'])->middleware('rbac:outlets:read');
    Route::get('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'show'])->middleware('rbac:outlets:read');
    Route::put('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'update'])->middleware('rbac:outlets:edit');
    Route::post('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'update'])->middleware('rbac:outlets:edit');
    }); // reject.stale_jwt
}); // auth:api

// Finance-only diagnostics: authorization is enforced by the controller (assertFinance).
Route::middleware(['auth:api', 'reject.stale_jwt'])->get('/finance/access', [FinanceRoleController::class, 'access']);

// Health check (invokable controller so `route:cache` works in production).
Route::get('/health', HealthController::class);
