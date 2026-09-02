<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\VendorOrder;
use App\Policies\ProductPolicy;
use App\Policies\ProductReviewPolicy;
use App\Policies\VendorOrderPolicy;
use App\Services\Cart\CartResolver;
use App\Services\Cart\EvaluateCart;
use App\Services\Storefront\WishlistStateService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Policies
        |--------------------------------------------------------------------------
        */

        Gate::policy(Product::class, ProductPolicy::class);

        Gate::policy(ProductReview::class, ProductReviewPolicy::class);

        Gate::policy(VendorOrder::class, VendorOrderPolicy::class);


        /*
        |--------------------------------------------------------------------------
        | Storefront Header View Composer
        |--------------------------------------------------------------------------
        |
        | Everything required by the global storefront header should be
        | provided here because the header is rendered on many different
        | storefront pages.
        |
        */

        View::composer('components.storefront.header', function ($view): void {

            $request = request();

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            |
            | Load top-level categories together with their active children.
            | This makes categories available in the header on every page,
            | instead of only pages whose controller passes $categories.
            |
            */

            $categories = Category::query()
                ->whereNull('parent_id')
                ->active()
                ->with([
                    'activeChildren' => fn ($query) => $query->ordered()->limit(6),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | Cart
            |--------------------------------------------------------------------------
            |
            | Vendors and admins do not use the storefront customer cart.
            |
            */

            $user = $request->user();

            $cart = $user?->isVendor() || $user?->isAdmin()
                ? null
                : app(CartResolver::class)->current($request);


            /*
            |--------------------------------------------------------------------------
            | Share Header Data
            |--------------------------------------------------------------------------
            */

            $view->with([

                'categories' => $categories,

                'wishlistCount' => app(WishlistStateService::class)
                    ->countForUser($user),

                'cartCount' => app(EvaluateCart::class)
                    ->count($cart),

            ]);

        });
    }
}
