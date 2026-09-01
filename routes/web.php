<?php

use App\Enums\UserRole;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\PaymentController as AccountPaymentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CartItemController;
use App\Http\Controllers\Vendor\RegistrationController as VendorRegistrationController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\StoreController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('storefront.search');
Route::get('/category/{category:slug}', StorefrontCategoryController::class)->name('storefront.categories.show');
Route::get('/product/{product:slug}', [StorefrontProductController::class, 'show'])->name('storefront.products.show');
Route::get('/store/{vendor:slug}', StoreController::class)->name('storefront.stores.show');

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

Route::middleware('shopper')->group(function (): void {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
    Route::patch('/cart/items/{cartItem}', [CartItemController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');
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
    Route::get('/account/addresses', [AddressController::class, 'index'])->name('account.addresses.index');
    Route::post('/account/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::get('/account/addresses/{address}/edit', [AddressController::class, 'edit'])->name('account.addresses.edit');
    Route::put('/account/addresses/{address}', [AddressController::class, 'update'])->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::put('/account/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('account.addresses.default');

    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist.index');
    Route::get('/account/orders', [AccountOrderController::class, 'index'])->name('account.orders.index');
    Route::get('/account/orders/{order:order_number}', [AccountOrderController::class, 'show'])->name('account.orders.show');
    Route::get('/account/orders/{order:order_number}/success', [AccountOrderController::class, 'success'])->name('account.orders.success');
    Route::post('/account/orders/{order:order_number}/cancel', [AccountOrderController::class, 'cancel'])->name('account.orders.cancel');
    Route::post('/account/orders/{order:order_number}/payment/retry', [AccountPaymentController::class, 'retry'])->name('account.orders.payment.retry');
    Route::get('/account/orders/{order:order_number}/payment/return', [AccountPaymentController::class, 'return'])->name('account.orders.payment.return');
    Route::get('/account/orders/{order:order_number}/payment/status', [AccountPaymentController::class, 'status'])->name('account.orders.payment.status');
    Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::put('/checkout/address', [CheckoutController::class, 'updateAddress'])->name('checkout.address.update');
    Route::put('/checkout/shipping', [CheckoutController::class, 'updateShipping'])->name('checkout.shipping.update');
    Route::put('/checkout/payment', [CheckoutController::class, 'updatePayment'])->name('checkout.payment.update');
    Route::post('/checkout/addresses', [CheckoutController::class, 'storeAddress'])->name('checkout.addresses.store');
    Route::post('/checkout/place-order', [CheckoutController::class, 'placeOrder'])->name('checkout.place-order');
});
