<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\VendorOrder;
use App\Models\CmsPage;
use App\Services\SettingsService;
use App\Services\NavigationService;
use App\Policies\ProductPolicy;
use App\Policies\ProductReviewPolicy;
use App\Policies\VendorOrderPolicy;
use App\Services\AdminDashboard\AdminNavigationCounterService;
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
        $this->app->bind(\App\Services\Refunds\ExternalRefundGateway::class, \App\Services\Refunds\PendingExternalRefundGateway::class);
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

            $categories = app(NavigationService::class)->megaMenu();
            /*$categories = Category::query()
                ->whereNull('parent_id')
                ->active()
                ->with([
                    'activeChildren' => fn ($query) => $query->ordered()->limit(6),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();*/


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

            $settings = app(SettingsService::class);
            $view->with([

                'categories' => $categories,

                'wishlistCount' => app(WishlistStateService::class)
                    ->countForUser($user),

                'cartCount' => app(EvaluateCart::class)
                    ->count($cart),

                'headerPages' => CmsPage::query()->public()->where('show_in_header', true)->orderBy('sort_order')->get(),
                'headerNavigation' => app(NavigationService::class)->menu('header'),
                'marketplaceName' => $settings->get('general.marketplace_name', config('marketplace.name')),

            ]);

        });

        View::composer('components.storefront.footer', function ($view): void {
            $settings = app(SettingsService::class);
            $nav=app(NavigationService::class); $view->with(['footerPages'=>CmsPage::query()->public()->where('show_in_footer', true)->orderBy('sort_order')->get(),'footerMenus'=>['shop'=>$nav->menu('footer_shop'),'help'=>$nav->menu('footer_help'),'sellers'=>$nav->menu('footer_sellers'),'company'=>$nav->menu('footer_company')],'socialLinks'=>['facebook'=>$settings->get('social.facebook'),'instagram'=>$settings->get('social.instagram'),'linkedin'=>$settings->get('social.linkedin')],'marketplaceName'=>$settings->get('general.marketplace_name',config('marketplace.name')),'tagline'=>$settings->get('general.tagline'),'supportEmail'=>$settings->get('contact.support_email'),'supportPhone'=>$settings->get('contact.support_phone'),'copyrightText'=>$settings->get('general.copyright_text',null)]);
        });

        View::composer('layouts.admin', function ($view): void {
            $view->with('adminNavigationCounters', app(AdminNavigationCounterService::class)->counts());
        });
    }
}
