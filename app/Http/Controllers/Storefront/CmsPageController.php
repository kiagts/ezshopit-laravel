<?php
namespace App\Http\Controllers\Storefront; use App\Http\Controllers\Controller; use App\Services\CmsPageService; use Illuminate\View\View;
class CmsPageController extends Controller { public function __invoke(string $slug,CmsPageService $service):View{$page=$service->public($slug);return view('storefront.pages.show',compact('page'));} }
