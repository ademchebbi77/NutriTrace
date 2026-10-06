<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Production;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'featured' => Product::published()
                ->with(['category'])
                ->latest()
                ->limit(4)
                ->get(),
            'stats' => [
                'products' => Product::published()->count(),
                'organizations' => Organization::where('is_verified', true)->count(),
                'productions' => Production::count(),
            ],
        ]);
    }

    public function howItWorks(): View
    {
        return view('public.how-it-works');
    }
}
