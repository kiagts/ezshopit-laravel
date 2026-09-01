<?php

use App\Enums\UserRole;
use App\Http\Controllers\Vendor\ApplicationStatusController;
use App\Http\Controllers\Vendor\CommissionController;
use App\Http\Controllers\Vendor\DashboardController;
use App\Http\Controllers\Vendor\InventoryController;
use App\Http\Controllers\Vendor\OnboardingController;
use App\Http\Controllers\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Vendor\Products\CategoryConfigurationController;
use App\Http\Controllers\Vendor\Products\ProductController;
use App\Http\Controllers\Vendor\Products\ProductImageController;
use App\Http\Controllers\Vendor\ShippingMethodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:'.UserRole::Vendor->value])
    ->prefix('vendor')
    ->name('vendor.')
    ->group(function (): void {
        Route::get('/onboarding', [OnboardingController::class, 'edit'])->name('onboarding.edit');
        Route::patch('/onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');
        Route::post('/onboarding/submit', [OnboardingController::class, 'submit'])->name('onboarding.submit');
        Route::get('/application-status', ApplicationStatusController::class)->name('application-status');

        Route::get('/', DashboardController::class)
            ->middleware('vendor.approved')
            ->name('dashboard');

        Route::middleware('vendor.approved')->group(function (): void {
            Route::get('/catalog/categories/{category}/configuration', CategoryConfigurationController::class)
                ->name('catalog.categories.configuration');

            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/commissions', [CommissionController::class, 'index'])->name('commissions.index');
            Route::get('/orders', [VendorOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{vendorOrder:vendor_order_number}', [VendorOrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{vendorOrder:vendor_order_number}/confirm', [VendorOrderController::class, 'confirm'])->name('orders.confirm');
            Route::post('/orders/{vendorOrder:vendor_order_number}/processing', [VendorOrderController::class, 'processing'])->name('orders.processing');
            Route::post('/orders/{vendorOrder:vendor_order_number}/ready-to-ship', [VendorOrderController::class, 'readyToShip'])->name('orders.ready-to-ship');
            Route::post('/orders/{vendorOrder:vendor_order_number}/ship', [VendorOrderController::class, 'ship'])->name('orders.ship');
            Route::post('/orders/{vendorOrder:vendor_order_number}/deliver', [VendorOrderController::class, 'deliver'])->name('orders.deliver');
            Route::post('/orders/{vendorOrder:vendor_order_number}/cancel', [VendorOrderController::class, 'cancel'])->name('orders.cancel');
            Route::get('/products/{product}/inventory', [InventoryController::class, 'product'])->name('products.inventory.show');
            Route::get('/products/{product}/variants/{variant}/inventory/history', [InventoryController::class, 'history'])->name('products.variants.inventory.history');
            Route::post('/products/{product}/variants/{variant}/inventory/increase', [InventoryController::class, 'increase'])->name('products.variants.inventory.increase');
            Route::post('/products/{product}/variants/{variant}/inventory/decrease', [InventoryController::class, 'decrease'])->name('products.variants.inventory.decrease');
            Route::post('/products/{product}/variants/{variant}/inventory/adjust', [InventoryController::class, 'adjust'])->name('products.variants.inventory.adjust');
            Route::put('/products/{product}/variants/{variant}/inventory/threshold', [InventoryController::class, 'threshold'])->name('products.variants.inventory.threshold');

            Route::resource('products', ProductController::class)->except('show');
            Route::resource('shipping-methods', ShippingMethodController::class)
                ->parameters(['shipping-methods' => 'shippingMethod'])
                ->except('show');
            Route::post('/products/{product}/submit', [ProductController::class, 'submit'])->name('products.submit');
            Route::post('/products/{product}/activate', [ProductController::class, 'activate'])->name('products.activate');
            Route::post('/products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');
            Route::post('/products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
            Route::put('/products/{product}/images/{image}', [ProductImageController::class, 'update'])->name('products.images.update');
            Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
            Route::post('/products/{product}/images/{image}/primary', [ProductImageController::class, 'primary'])->name('products.images.primary');
            Route::post('/products/{product}/images/reorder', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
        });
    });
