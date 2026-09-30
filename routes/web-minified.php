<?php

use App\Enums\UserRole;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\PaymentController as AccountPaymentController;
use App\Http\Controllers\Account\ProfileController as AccountProfileController;
use App\Http\Controllers\Account\ReviewController as AccountReviewController;
use App\Http\Controllers\Account\SecurityController as AccountSecurityController;
use App\Http\Controllers\Account\GiftCardController as AccountGiftCardController;
use App\Http\Controllers\Account\ReturnController as AccountReturnController;
use App\Http\Controllers\Account\DisputeController as AccountDisputeController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\Storefront\BrandController as StorefrontBrandController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CartItemController;
use App\Http\Controllers\Storefront\CartPromotionController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SearchSuggestionController;
use App\Http\Controllers\Storefront\MarketController;
use App\Http\Controllers\Storefront\CurrencyController;
use App\Http\Controllers\Storefront\LanguageController;
use App\Http\Controllers\Vendor\RegistrationController as VendorRegistrationController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Storefront\StoreController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Http\Controllers\Storefront\CmsPageController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::middleware(['auth','role:admin'])->group(function () {
    Route::get('/admin/returns/{return}/refund', [\App\Http\Controllers\Admin\ReturnRefundController::class,'create'])->name('admin.returns.refund.create');
    Route::post('/admin/returns/{return}/refund', [\App\Http\Controllers\Admin\ReturnRefundController::class,'store'])->name('admin.returns.refund.store');
    Route::post('/admin/refunds/{refund}/process', [\App\Http\Controllers\Admin\ReturnRefundController::class,'process'])->name('admin.refunds.process');
});
// One operational controller, with role-specific route protection and policies.
foreach (['account' => 'customer', 'vendor' => 'vendor', 'admin' => 'admin'] as $returnPrefix => $returnRole) {
    $returnMiddleware = ['auth', 'role:'.$returnRole];
    if ($returnRole === 'vendor') $returnMiddleware[] = 'vendor.approved';
    Route::middleware($returnMiddleware)->prefix($returnPrefix)->name($returnPrefix.'.')->group(function () use ($returnRole) {
        $controller = \App\Http\Controllers\Returns\ReturnController::class;
        Route::get('/returns', [$controller, 'index'])->name('returns.index');
        Route::get('/returns/evidence/{evidence}', [$controller, 'evidence'])->name('returns.evidence');
        Route::get('/returns/{return}', [$controller, 'show'])->name('returns.show');
        Route::post('/returns/{return}/transition', [$controller, 'transition'])->name('returns.transition');
        if ($returnRole !== 'customer') Route::post('/returns/{return}/inspection', [$controller, 'inspect'])->name('returns.inspect');
        if ($returnRole === 'customer') {
            Route::get('/orders/{order:order_number}/returns/create', [$controller, 'create'])->name('orders.returns.create');
            Route::post('/returns/{return}/evidence', [$controller, 'upload'])->name('returns.upload');
        }
    });
}
Route::get('/search', SearchController::class)->name('storefront.search');
Route::get('/search/suggestions', SearchSuggestionController::class)->name('storefront.search.suggestions');
Route::post('/market', [MarketController::class, 'store'])->name('storefront.market.select');
Route::post('/currency', [CurrencyController::class, 'store'])->name('storefront.currency.select');
Route::middleware(['auth','shopper'])->get('/account/wallet', \App\Http\Controllers\Account\WalletController::class)->name('account.wallet');
Route::post('/language', [LanguageController::class, 'store'])->name('storefront.language.select');
Route::get('/category/{category:slug}', StorefrontCategoryController::class)->name('storefront.categories.show');
Route::get('/brand/{brand:slug}', StorefrontBrandController::class)->name('storefront.brands.show');
Route::get('/product/{product:slug}', [StorefrontProductController::class, 'show'])->name('storefront.products.show');
Route::get('/store/{vendor:slug}', StoreController::class)->name('storefront.stores.show');
Route::get('/pages/{slug}', CmsPageController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('storefront.pages.show');

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

Route::middleware('shopper')->group(function (): void {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
    Route::patch('/cart/items/{cartItem}', [CartItemController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');
    Route::post('/cart/promotion', [CartPromotionController::class, 'store'])->name('cart.promotion.store');
    Route::delete('/cart/promotion', [CartPromotionController::class, 'destroy'])->name('cart.promotion.destroy');
});

Route::get('/sell', [VendorRegistrationController::class, 'landing'])->name('vendor.apply');
Route::get('/sell/register', [VendorRegistrationController::class, 'create'])->name('vendor.register');
Route::post('/sell/register', [VendorRegistrationController::class, 'store']);

Route::middleware('guest.dashboard')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/account', AccountDashboardController::class)
    ->middleware(['auth', 'role:'.UserRole::Customer->value])
    ->name('account.dashboard');

Route::middleware(['auth', 'role:'.UserRole::Customer->value])->group(function (): void {
    Route::get('/account/profile', [AccountProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('/account/profile', [AccountProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/security', [AccountSecurityController::class, 'edit'])->name('account.security.edit');
    Route::put('/account/security/password', [AccountSecurityController::class, 'updatePassword'])->name('account.security.password.update');

    Route::get('/account/addresses', [AddressController::class, 'index'])->name('account.addresses.index');
    Route::post('/account/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::get('/account/addresses/{address}/edit', [AddressController::class, 'edit'])->name('account.addresses.edit');
    Route::put('/account/addresses/{address}', [AddressController::class, 'update'])->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::put('/account/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('account.addresses.default');

    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist.index');
    Route::get('/account/gift-cards', [AccountGiftCardController::class, 'index'])->name('account.gift-cards.index');
    Route::post('/account/gift-cards/balance', [AccountGiftCardController::class, 'balance'])->middleware('throttle:10,1')->name('account.gift-cards.balance');
    Route::get('/account/orders', [AccountOrderController::class, 'index'])->name('account.orders.index');
    Route::get('/account/orders/{order:order_number}', [AccountOrderController::class, 'show'])->name('account.orders.show');
    Route::get('/account/orders/{order:order_number}/success', [AccountOrderController::class, 'success'])->name('account.orders.success');
    Route::post('/account/orders/{order:order_number}/cancel', [AccountOrderController::class, 'cancel'])->name('account.orders.cancel');
    Route::post('/account/orders/{order:order_number}/return', [AccountReturnController::class, 'store'])->name('account.orders.return.store');
    Route::post('/account/orders/{order:order_number}/dispute', [AccountDisputeController::class, 'store'])->name('account.orders.dispute.store');
    Route::post('/account/disputes/{dispute}/messages', [AccountDisputeController::class, 'message'])->name('account.disputes.messages.store');
    Route::post('/account/orders/{order:order_number}/payment/retry', [AccountPaymentController::class, 'retry'])->name('account.orders.payment.retry');
    Route::get('/account/orders/{order:order_number}/payment/return', [AccountPaymentController::class, 'return'])->name('account.orders.payment.return');
    Route::get('/account/orders/{order:order_number}/payment/status', [AccountPaymentController::class, 'status'])->name('account.orders.payment.status');
    Route::get('/account/reviews', [AccountReviewController::class, 'index'])->name('account.reviews.index');
    Route::get('/account/notifications', [NotificationController::class, 'index'])->name('account.notifications.index');
    Route::get('/account/notifications/{notification}/open', [NotificationController::class, 'open'])->name('account.notifications.open');
    Route::post('/account/notifications/{notification}/read', [NotificationController::class, 'read'])->name('account.notifications.read');
    Route::post('/account/notifications/read-all', [NotificationController::class, 'readAll'])->name('account.notifications.read-all');
    Route::get('/account/orders/items/{orderItem}/review', [AccountReviewController::class, 'create'])->name('account.order-items.reviews.create');
    Route::post('/account/orders/items/{orderItem}/review', [AccountReviewController::class, 'store'])->name('account.order-items.reviews.store');
    Route::get('/account/reviews/{review}/edit', [AccountReviewController::class, 'edit'])->name('account.reviews.edit');
    Route::put('/account/reviews/{review}', [AccountReviewController::class, 'update'])->name('account.reviews.update');
    Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::put('/checkout/address', [CheckoutController::class, 'updateAddress'])->name('checkout.address.update');
    Route::put('/checkout/shipping', [CheckoutController::class, 'updateShipping'])->name('checkout.shipping.update');
    Route::put('/checkout/payment', [CheckoutController::class, 'updatePayment'])->name('checkout.payment.update');
    Route::put('/checkout/wallet', [CheckoutController::class, 'updateWallet'])->name('checkout.wallet.update');
    Route::put('/checkout/gift-card', [CheckoutController::class, 'updateGiftCard'])->name('checkout.gift-card.update');
    Route::post('/checkout/addresses', [CheckoutController::class, 'storeAddress'])->name('checkout.addresses.store');
    Route::post('/checkout/place-order', [CheckoutController::class, 'placeOrder'])->name('checkout.place-order');
});

// Disputes reuse the existing customer creation/message endpoints above.
foreach (['account'=>'customer','vendor'=>'vendor','admin'=>'admin'] as $prefix=>$role) {
    Route::middleware(['auth','role:'.$role])->prefix($prefix)->name($prefix.'.')->group(function () use ($prefix) {
        Route::get('/disputes',[\App\Http\Controllers\Disputes\DisputeController::class,'index'])->name('disputes.index');
        Route::get('/disputes/{dispute}',[\App\Http\Controllers\Disputes\DisputeController::class,'show'])->name('disputes.show');
        Route::get('/dispute-evidence/{evidence}',[\App\Http\Controllers\Disputes\DisputeController::class,'evidence'])->name('disputes.evidence');
        if ($prefix !== 'account') Route::post('/disputes/{dispute}/messages',[\App\Http\Controllers\Disputes\DisputeController::class,'message'])->name('disputes.messages.store');
        if ($prefix === 'account') Route::get('/orders/{order:order_number}/dispute',[\App\Http\Controllers\Disputes\DisputeController::class,'create'])->name('orders.dispute.create');
        if ($prefix === 'admin') {
            Route::post('/disputes/{dispute}/transition',[\App\Http\Controllers\Disputes\DisputeController::class,'transition'])->name('disputes.transition');
            Route::post('/disputes/{dispute}/assign',[\App\Http\Controllers\Disputes\DisputeController::class,'assign'])->name('disputes.assign');
            Route::post('/disputes/{dispute}/resolve',[\App\Http\Controllers\Disputes\DisputeController::class,'resolve'])->name('disputes.resolve');
        }
    });
}

// Notification preferences use the existing role inboxes and user identity.
foreach (['account'=>'customer','vendor'=>'vendor','admin'=>'admin'] as $prefix=>$role) {
    Route::middleware(['auth','role:'.$role])->prefix($prefix.'/notifications')->name($prefix.'.notifications.')->group(function(){
        Route::get('preferences',[\App\Http\Controllers\NotificationPreferenceController::class,'edit'])->name('preferences');
        Route::put('preferences',[\App\Http\Controllers\NotificationPreferenceController::class,'update'])->name('preferences.update');
    });
}

// Authenticated commerce documents. Authorization is checked before issuance.
foreach (['account'=>'customer','admin'=>'admin'] as $prefix=>$role) {
    Route::middleware(['auth','role:'.$role])->prefix($prefix)->name($prefix.'.')->group(function(){
        Route::get('orders/{order:order_number}/invoice',[\App\Http\Controllers\CommerceDocumentController::class,'invoice'])->name('documents.invoice');
        Route::get('orders/{order:order_number}/print',[\App\Http\Controllers\CommerceDocumentController::class,'print'])->name('documents.print');
        Route::get('refunds/{refund:refund_number}/document',[\App\Http\Controllers\CommerceDocumentController::class,'refund'])->name('documents.refund');
    });
}
foreach (['vendor'=>'vendor','admin'=>'admin'] as $prefix=>$role) {
    Route::middleware(['auth','role:'.$role])->prefix($prefix)->name($prefix.'.')->group(function(){
        Route::get('seller-orders/{vendorOrder:vendor_order_number}/document',[\App\Http\Controllers\CommerceDocumentController::class,'seller'])->name('documents.seller');
    });
}

// Seller finance uses the existing ledger and payout lifecycle.
Route::middleware(['auth','role:vendor'])->prefix('vendor')->name('vendor.finance.')->group(function(){
    Route::get('finance',[\App\Http\Controllers\VendorFinanceController::class,'index'])->name('index');
    foreach(['transactions','earnings','statements','export'] as $page) Route::get('finance/'.$page,[\App\Http\Controllers\VendorFinanceController::class,$page])->name($page);
    Route::get('finance/payouts',[\App\Http\Controllers\Vendor\PayoutController::class,'index'])->name('payouts');
    Route::get('orders/{vendorOrder:vendor_order_number}/financials',[\App\Http\Controllers\VendorFinanceController::class,'order'])->name('order');
});
Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.finance.')->group(function(){
    Route::get('vendors/{vendor}/finance',[\App\Http\Controllers\VendorFinanceController::class,'index'])->name('index');
    foreach(['transactions','earnings','statements','export','reconcile'] as $page) Route::get('vendors/{vendor}/finance/'.$page,[\App\Http\Controllers\VendorFinanceController::class,$page])->name($page);
    Route::get('seller-orders/{vendorOrder:vendor_order_number}/financials',[\App\Http\Controllers\VendorFinanceController::class,'order'])->name('order');
});
