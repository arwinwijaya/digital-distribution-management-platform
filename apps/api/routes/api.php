<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditLimitController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\FinanceRoleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\WhatsAppController;
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
    // Admin-controlled finance role assignment and removal.
    Route::post('/admin/users/{userId}/finance-role', [FinanceRoleController::class, 'assign']);
    Route::delete('/admin/users/{userId}/finance-role', [FinanceRoleController::class, 'remove']);
    // Payment terms are administrator-controlled and outlet-scoped.
    Route::get('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'showPaymentTerms']);
    Route::put('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'updatePaymentTerms']);
    Route::post('/admin/outlets/{outletId}/payment-terms', [OutletController::class, 'updatePaymentTerms']);

    // Legacy outlet/catalog endpoints remain available only to authenticated clients.
    Route::post('/outlets', [OutletController::class, 'store']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::prefix('marketplace')->group(function () {
        Route::get('/suppliers', [MarketplaceController::class, 'suppliers']);
        Route::get('/products', [MarketplaceController::class, 'products']);
    });
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Order routes
    Route::post('/orders', [OrderController::class, 'store']);
    // The controller authorizes this list for admins and does not require an outlet relation.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    // Explicit admin alias for clients that keep admin APIs under /admin.
    Route::get('/admin/orders', [OrderController::class, 'index']);
    Route::get('/admin/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/approve', [OrderController::class, 'approve']);

    // Sales visit planning routes (controller scopes sales users to their own visits).
    Route::get('/sales/visits', [SalesController::class, 'index']);
    Route::post('/sales/visits', [SalesController::class, 'store']);
    Route::get('/sales/visits/{id}', [SalesController::class, 'show']);
    Route::patch('/sales/visits/{id}', [SalesController::class, 'update']);

    // Delivery assignment and auditable lifecycle routes.
    Route::get('/deliveries', [DeliveryController::class, 'index']);
    Route::post('/deliveries', [DeliveryController::class, 'store']);
    Route::get('/deliveries/{id}', [DeliveryController::class, 'show']);
    Route::patch('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus']);
    Route::post('/deliveries/{id}/status', [DeliveryController::class, 'updateStatus']);
    Route::put('/deliveries/{id}', [DeliveryController::class, 'updateStatus']);

    // Payment routes
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments', [PaymentController::class, 'index']);

    // Owner analytics routes
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);

    // Deterministic, bounded AI-assisted analytics for outlet and admin contexts.
    Route::prefix('ai')->group(function () {
        Route::get('/recommendations', [AIController::class, 'recommendations']);
        Route::get('/forecast', [AIController::class, 'forecast']);
        Route::get('/segmentation', [AIController::class, 'segmentation']);
    });

    // WhatsApp outbound operations use the authenticated outlet/admin identity.
    Route::post('/whatsapp/catalog', [WhatsAppController::class, 'catalog']);
    Route::post('/whatsapp/orders/{orderId}/notification', [WhatsAppController::class, 'notify']);
    Route::post('/whatsapp/messages/{messageId}/retry', [WhatsAppController::class, 'retry']);

    // Credit limit and outstanding balance routes
    Route::get('/credit-limit', [CreditLimitController::class, 'show']);
    Route::get('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'show']);
    Route::put('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'update']);
    Route::post('/admin/outlets/{outletId}/credit-limit', [CreditLimitController::class, 'update']);
});

Route::middleware('auth:api')->get('/finance/access', [FinanceRoleController::class, 'access']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});
