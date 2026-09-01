<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\CommissionRuleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ProductAttributeController;
use App\Http\Controllers\Admin\ProductAttributeSetController;
use App\Http\Controllers\Admin\ProductAttributeValueController;
use App\Http\Controllers\Admin\Products\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:'.UserRole::Admin->value])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::get('/refunds/{refund:refund_number}', [RefundController::class, 'show'])->name('refunds.show');
        Route::post('/orders/{order:order_number}/refunds', [RefundController::class, 'store'])->name('orders.refunds.store');
        Route::get('/commission-rules', [CommissionRuleController::class, 'index'])->name('commission-rules.index');
        Route::put('/commission-rules/global', [CommissionRuleController::class, 'updateGlobal'])->name('commission-rules.global.update');
        Route::post('/commission-rules/vendors', [CommissionRuleController::class, 'storeVendor'])->name('commission-rules.vendors.store');
        Route::delete('/commission-rules/vendors/{vendor}', [CommissionRuleController::class, 'destroyVendor'])->name('commission-rules.vendors.destroy');
        Route::get('/commissions', [CommissionController::class, 'index'])->name('commissions.index');
        Route::get('/commissions/{commission}', [CommissionController::class, 'show'])->name('commissions.show');
        Route::get('/inventory', InventoryController::class)->name('inventory.index');
        Route::get('/shipping-methods', [ShippingMethodController::class, 'index'])->name('shipping-methods.index');
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
        Route::post('/vendors/{vendor}/approve', [VendorController::class, 'approve'])->name('vendors.approve');
        Route::post('/vendors/{vendor}/reject', [VendorController::class, 'reject'])->name('vendors.reject');
        Route::post('/vendors/{vendor}/suspend', [VendorController::class, 'suspend'])->name('vendors.suspend');
        Route::post('/vendors/{vendor}/reactivate', [VendorController::class, 'reactivate'])->name('vendors.reactivate');

        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('brands', BrandController::class)->except('show');
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('products.show');
        Route::post('/products/{product}/approve', [AdminProductController::class, 'approve'])->name('products.approve');
        Route::post('/products/{product}/reject', [AdminProductController::class, 'reject'])->name('products.reject');
        Route::resource('attributes', ProductAttributeController::class)->except('show');
        Route::resource('attribute-sets', ProductAttributeSetController::class)
            ->parameters(['attribute-sets' => 'attributeSet'])
            ->except('show');
        Route::post('/attributes/{attribute}/values', [ProductAttributeValueController::class, 'store'])->name('attributes.values.store');
        Route::put('/attributes/{attribute}/values/{value}', [ProductAttributeValueController::class, 'update'])->name('attributes.values.update');
        Route::delete('/attributes/{attribute}/values/{value}', [ProductAttributeValueController::class, 'destroy'])->name('attributes.values.destroy');
    });
