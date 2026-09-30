<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Homepage\HomepageService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(HomepageService $homepage): View
    {
        return view('storefront.home', $homepage->build());
    }
}
