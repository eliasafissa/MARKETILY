<?php
// HAIDAR-V5-WITHDRAWALS-DISABLED: financial withdrawal endpoints intentionally unavailable.
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminOranosController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\AdminSyncController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminTransactionController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ContactWebsiteController;
use App\Http\Controllers\Api\PartnerRequestController;
use App\Http\Controllers\Api\Partner\PartnerApiController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\Deposit\DepositController;
use App\Http\Controllers\Api\Order\OrderController;
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\Store\StoreController;
use App\Http\Controllers\Api\Vip\VipController;
use App\Http\Controllers\Api\Favorite\FavoriteController;
use App\Http\Controllers\Api\Webhook\BinanceWebhookController;
use App\Http\Controllers\Api\Webhook\UsdtWebhookController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\Withdrawal\WithdrawalController;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/currency/rate', fn (\App\Services\DepositCurrency $currency) => response()->json(['base' => 'USD', 'display' => ['USD', 'SYP'], 'syp_per_usd' => $currency->rate()]));

Route::get('/auth/verify-email/{id}/{hash}', [\App\Http\Controllers\Api\Auth\EmailVerificationController::class, 'verify'])->middleware(['signed','throttle:6,1'])->name('haidar.email.verify');

Route::get('/payment-methods', fn (\App\Services\PaymentGatewayManager $gateways) => response()->json(['methods' => $gateways->publicMethods()]));

// Public auth
Route::middleware('throttle:register')->post('/auth/register', [AuthController::class, 'register']);
Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']);
Route::middleware('throttle:password-reset')->post('/auth/forgot-password', [\App\Http\Controllers\Api\Auth\SecurePasswordResetController::class, 'request']);

Route::middleware('throttle:password-reset')->post('/auth/reset-password', [\App\Http\Controllers\Api\Auth\SecurePasswordResetController::class, 'reset']);

// Public content
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/form-schema', [CategoryController::class, 'formSchema']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);

// Stores — /stores/my must be registered BEFORE /stores/{slug}
// or Laravel treats "my" as a slug and returns 404.
Route::middleware('auth:sanctum')->get('/stores/my', [StoreController::class, 'my']);
Route::get('/stores/{slug}', [StoreController::class, 'show']);

// Public settings (company info, legal pages)
Route::get('/settings/company', [SettingsController::class, 'company']);
Route::get('/settings/legal/{page}', [SettingsController::class, 'legal']);

// Provider simulator/webhook callbacks are disabled in manual payment mode.
Route::post('/webhooks/payments/{gateway}', fn () => response()->json(['message' => 'Manual settlement only'], 503))->middleware('throttle:checkout');
Route::post('/webhooks/binance', fn () => response()->json(['message' => 'Manual settlement only'], 503))->middleware('throttle:checkout');
Route::post('/webhooks/usdt', fn () => response()->json(['message' => 'Manual settlement only'], 503))->middleware('throttle:checkout');

// Public contact form (no auth, throttle)
Route::middleware('throttle:register')->post('/contact-website', [\App\Http\Controllers\Api\WebsiteLeadCaptureController::class, 'store']);

// Authenticated user routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/email-verification/send', [\App\Http\Controllers\Api\Auth\EmailVerificationController::class, 'send'])->middleware('throttle:3,60');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/wallet/balance', fn (Request $request) => response()->json(['balance' => (string) $request->user()->fresh()->balance, 'currency' => 'USD']));

    Route::middleware('verified.account')->group(function () {
    // Partner API requests
    Route::get('/partner-request', [PartnerRequestController::class, 'myRequest']);
    Route::post('/partner-request', [PartnerRequestController::class, 'store']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::middleware('throttle:30,1')->post('/orders/quote', [OrderController::class, 'quote']);
    Route::middleware('throttle:checkout')->post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // Deposits & Withdrawals
    Route::get('/account/activity-notifications', [\App\Http\Controllers\Api\Account\ActivityNotificationsController::class, 'index']);
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::middleware('throttle:checkout')->post('/deposits/preview', [DepositController::class, 'preview']);
    Route::middleware('throttle:checkout')->post('/deposits', [DepositController::class, 'store']);
    Route::middleware('throttle:checkout')->post('/deposits/{transaction}/confirm', [DepositController::class, 'confirm']);
    Route::middleware('throttle:checkout')->post('/deposits/{transaction}/cancel', [DepositController::class, 'cancel']);
    Route::get('/deposits/{transaction}', [DepositController::class, 'show']);
Route::post('/withdrawals', fn () => response()->json(['message'=>'السحب غير متاح'],410));
// VIP
    Route::get('/vip/status', [VipController::class, 'status']);

    // User transactions list
    Route::get('/transactions', function (Request $request) {
        return Transaction::where('user_id', $request->user()->id)
            ->where(function ($q) {
                $q->where('type', '!=', 'deposit')
                  ->orWhere('meta->stage', '!=', 'awaiting_transfer')->orWhereNull('meta->stage');
            })->latest()
            ->paginate(20);
    });

    // Stores — create + edit prices
    Route::post('/stores', [StoreController::class, 'store']);

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{productId}', [FavoriteController::class, 'destroy']);
    Route::get('/favorites/{productId}', [FavoriteController::class, 'check']);
    Route::post('/stores/{store}/products/{product}', [StoreController::class, 'updateProductPrice']);
    }); // verified.account
});

// Admin routes
Route::middleware(['auth:sanctum', 'role:admin|moderator'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'stats']);
    Route::get('/health', [AdminDashboardController::class, 'health']);

    Route::get('/website-leads', [\App\Http\Controllers\Api\Admin\WebsiteLeadsController::class, 'index']);
    Route::patch('/website-leads/{id}', [\App\Http\Controllers\Api\Admin\WebsiteLeadsController::class, 'update'])->whereNumber('id');
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::post('/users/{user}/balance', [UserController::class, 'adjustBalance']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/stats', [AdminProductController::class, 'stats']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::patch('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

    Route::get('/categories/selection', \App\Http\Controllers\Api\Admin\CategorySelectionController::class);
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::patch('/categories/{category}/visibility', [AdminCategoryController::class, 'visibility']);
    Route::patch('/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::post('/categories/{category}/fields', [AdminCategoryController::class, 'storeField']);
    Route::delete('/fields/{field}', [AdminCategoryController::class, 'destroyField']);

    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/pending-manual', [AdminOrderController::class, 'pendingManual']);
    Route::get('/orders/pending-manual/count', [AdminOrderController::class, 'pendingManualCount']);
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('/deposits', [AdminTransactionController::class, 'deposits']);
    Route::get('/deposits/alerts', [AdminTransactionController::class, 'pendingAlerts']);
    Route::get('/deposits/{transaction}', [AdminTransactionController::class, 'showDeposit']);
    Route::get('/deposits/{transaction}/receipt', [AdminTransactionController::class, 'receipt']);
    Route::post('/deposits/{transaction}/approve', [AdminTransactionController::class, 'approveDeposit']);
    Route::post('/deposits/{transaction}/reject', [AdminTransactionController::class, 'rejectDeposit']);
Route::get('/withdrawals/{transaction}', fn () => response()->json(['message'=>'السحب غير متاح'],410));
Route::post('/withdrawals/{transaction}/reject', fn () => response()->json(['message'=>'السحب غير متاح'],410));

    // Oranos monitor
    Route::get('/oranos/balance', [AdminOranosController::class, 'balance']);
    Route::post('/oranos/refresh', [AdminOranosController::class, 'refresh']);

    // Settings — admin only
    Route::middleware('role:admin')->group(function () {
        Route::put('/currency/rate', [\App\Http\Controllers\Api\Admin\AdminCurrencyController::class, 'update']);
        Route::post('/users/{user}/binance-uid', [AdminTransactionController::class, 'bindVerifiedBinanceSender']);
        Route::post('/sync/apply-markup', [AdminSyncController::class, 'applyMarkup']);
        Route::post('/sync/products', [AdminSyncController::class, 'syncProducts']);
        Route::get('/settings', [AdminSettingsController::class, 'index']);
        Route::post('/settings', [AdminSettingsController::class, 'update']);
        Route::post('/settings/bulk', [AdminSettingsController::class, 'bulkUpdate']);
        Route::put('/settings/company', [AdminSettingsController::class, 'updateCompany']);
        Route::put('/settings/legal/{page}', [AdminSettingsController::class, 'updateLegal']);
    });

    // Partner API requests
    Route::get('/partner-requests', [PartnerRequestController::class, 'index']);
    Route::post('/partner-requests/{partnerApiRequest}/approve', [PartnerRequestController::class, 'approve']);
    Route::post('/partner-requests/{partnerApiRequest}/reject', [PartnerRequestController::class, 'reject']);
});

// Partner API routes (public, auth via api-token header)
Route::middleware(['partner.api', 'throttle:60,1'])->prefix('partner')->group(function () {
    Route::get('/me', [PartnerApiController::class, 'me']);
    Route::get('/categories', [PartnerApiController::class, 'categories']);
    Route::get('/products', [PartnerApiController::class, 'products']);
    Route::get('/products/{slug}', [PartnerApiController::class, 'product']);
    Route::post('/orders', [PartnerApiController::class, 'createOrder']);
    Route::get('/orders/{id}', [PartnerApiController::class, 'orderStatus']);
});
