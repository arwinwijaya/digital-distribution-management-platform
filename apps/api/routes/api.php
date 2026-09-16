<?php

use App\Http\Controllers\AdminOutletController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditLimitController;
use App\Http\Controllers\DataPipelineController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\FinanceRoleController;
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
use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\GeographicAnalyticsController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\StockPlanningController;
use App\Http\Controllers\SupplierPerformanceController;
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
    Route::patch('/admin/products/{id}', [AdminProductController::class, 'update']);
    Route::get('/admin/products/{id}/prices', [AdminProductController::class, 'prices']);

    // Admin outlet management (CRUD, purchase history, scoring)
    Route::get('/admin/outlets', [AdminOutletController::class, 'index']);
    Route::patch('/admin/outlets/{id}', [AdminOutletController::class, 'update']);
    Route::get('/admin/outlets/{outletId}/orders', [AdminOutletController::class, 'orders']);
    Route::get('/admin/outlets/{outletId}/summary', [AdminOutletController::class, 'summary']);

    // Promotion management (F4): full CRUD admin-only
    Route::get('/admin/promotions', [PromotionController::class, 'index']);
    Route::post('/admin/promotions', [PromotionController::class, 'store']);
    Route::get('/admin/promotions/{id}', [PromotionController::class, 'show']);
    Route::patch('/admin/promotions/{id}', [PromotionController::class, 'update']);
    Route::delete('/admin/promotions/{id}', [PromotionController::class, 'destroy']);
    Route::post('/admin/promotions/{id}/broadcast', [PromotionBroadcastController::class, 'broadcast']);

    // Admin-controlled finance role assignment and removal.
    Route::post('/admin/users/{userId}/finance-role', [FinanceRoleController::class, 'assign']);
    Route::delete('/admin/users/{userId}/finance-role', [FinanceRoleController::class, 'remove']);

    // General user listing + role assignment (F1 — platform_owner is superset of admin).
    Route::get('/admin/users', [UserRoleController::class, 'index']);
    Route::patch('/admin/users/{userId}/role', [UserRoleController::class, 'assignRole']);
    // Payment terms are administrator-controlled and outlet-scoped.
    Route::get('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'showPaymentTerms']);
    Route::put('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'updatePaymentTerms']);
    Route::post('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'updatePaymentTerms']);

    // Legacy outlet/catalog endpoints remain available only to authenticated clients.
    Route::post('/outlets', [OutletController::class, 'store'])->middleware('deny.finance');
    Route::get('/products', [ProductController::class, 'index'])->middleware('deny.finance');
    Route::prefix('marketplace')->middleware('deny.finance')->group(function () {
        Route::get('/suppliers', [MarketplaceController::class, 'suppliers']);
        Route::get('/products', [MarketplaceController::class, 'products']);
    });
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'update']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Order routes
    Route::post('/orders', [OrderController::class, 'store'])->middleware('deny.finance');
    // The controller authorizes this list for admins and does not require an outlet relation.
    Route::get('/orders', [OrderController::class, 'index'])->middleware('deny.finance');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->middleware('deny.finance');
    // Explicit admin alias for clients that keep admin APIs under /admin.
    Route::get('/admin/orders', [OrderController::class, 'index'])->middleware('deny.finance');
    Route::get('/admin/orders/{id}', [OrderController::class, 'show'])->middleware('deny.finance');
    Route::put('/orders/{id}/approve', [OrderController::class, 'approve'])->middleware('deny.finance');
    Route::put('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    // Invoice history is available to current admin, finance, and outlet roles;
    // the controller applies the corresponding outlet scope.
    Route::get('/invoices', [InvoiceController::class, 'index']);

    // Sales visit planning routes (controller scopes sales users to their own visits).
    Route::get('/sales/visits', [SalesController::class, 'index'])->middleware('deny.finance');
    Route::post('/sales/visits', [SalesController::class, 'store'])->middleware('deny.finance');
    Route::get('/sales/visits/{id}', [SalesController::class, 'show'])->middleware('deny.finance');
    Route::patch('/sales/visits/{id}', [SalesController::class, 'update'])->middleware('deny.finance');

    // Delivery assignment and auditable lifecycle routes.
    Route::get('/deliveries', [DeliveryController::class, 'index'])->middleware('deny.finance');
    Route::post('/deliveries', [DeliveryController::class, 'store'])->middleware('deny.finance');
    Route::get('/deliveries/{id}', [DeliveryController::class, 'show'])->middleware('deny.finance');
    Route::patch('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus'])->middleware('deny.finance');
    Route::post('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus'])->middleware('deny.finance');
    Route::put('/deliveries/{id}', [DeliveryController::class, 'updateStatus'])->middleware('deny.finance');

    // Payment routes
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments', [PaymentController::class, 'index']);

    // Owner analytics routes
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);

    // Deterministic, bounded AI-assisted analytics for outlet and admin contexts.
    Route::prefix('ai')->middleware('deny.finance')->group(function () {
        Route::get('/recommendations', [AIController::class, 'recommendations']);
        Route::get('/forecast', [AIController::class, 'forecast']);
        Route::get('/segmentation', [AIController::class, 'segmentation']);
    });

    // WhatsApp outbound operations use the authenticated outlet/admin identity.
    Route::post('/whatsapp/catalog', [WhatsAppController::class, 'catalog']);
    Route::post('/whatsapp/orders/{orderId}/notification', [WhatsAppController::class, 'notify']);
    Route::post('/whatsapp/messages/{messageId}/retry', [WhatsAppController::class, 'retry']);

    Route::get('/finance/metrics', [FinanceMetricsController::class, 'index']);
    Route::get('/finance/reminders', [InvoiceReminderController::class, 'index']);
    Route::get('/reminders', [InvoiceReminderController::class, 'index']);

    // Admin-only data pipeline status and manual trigger
    Route::prefix('admin/pipeline')->group(function () {
        Route::get('/status', [DataPipelineController::class, 'status']);
        Route::post('/manual-trigger', [DataPipelineController::class, 'manualTrigger']);
    });

    // Geographic BI and territory management routes (admin-only)
    Route::get('/admin/analytics/geographic', [GeographicAnalyticsController::class, 'index']);
    Route::get('/admin/territories', [TerritoryController::class, 'index']);
    Route::post('/admin/territories', [TerritoryController::class, 'store']);
    Route::patch('/admin/territories/{territoryId}', [TerritoryController::class, 'update']);
    Route::post('/admin/territories/{territoryId}/assign', [TerritoryController::class, 'assign']);

    // Supplier performance BI (admin-only via controller boundary, reads active snapshot)
    Route::get('/admin/analytics/suppliers', [SupplierPerformanceController::class, 'index']);

    // Stock planning BI (admin-only via controller boundary, reads active snapshot)
    Route::get('/admin/analytics/stock-planning', [StockPlanningController::class, 'index']);

    // Measurement ingestion and admin-only BI (controller enforces admin boundary)
    Route::post('/admin/measurement/events', [MeasurementController::class, 'storeEvent']);
    Route::get('/admin/analytics/measurement/recommendations', [MeasurementController::class, 'recommendations']);
    Route::get('/admin/analytics/measurement/forecasts', [MeasurementController::class, 'forecasts']);

    // Pre-pilot operational diagnostics (admin-only, gated)
    Route::prefix('admin/operations')->middleware('pre_pilot')->group(function () {
        Route::get('/readiness', [OperationalReadinessController::class, 'readiness']);
        Route::get('/issues', [OperationalReadinessController::class, 'issues']);
        Route::get('/issues/{id}', [OperationalReadinessController::class, 'issueDetail']);
    });

    // Credit limit and outstanding balance routes
    Route::get('/credit-limit', [CreditLimitController::class, 'show'])->middleware('deny.finance');
    Route::get('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'show'])->middleware('deny.finance');
    Route::put('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'update'])->middleware('deny.finance');
    Route::post('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'update'])->middleware('deny.finance');
    }); // reject.stale_jwt
}); // auth:api

Route::middleware(['auth:api', 'reject.stale_jwt'])->get('/finance/access', [FinanceRoleController::class, 'access']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});
