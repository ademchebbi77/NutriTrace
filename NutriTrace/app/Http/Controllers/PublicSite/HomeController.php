<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Lot;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('public.home', [
            'featured' => Product::published()
                ->whereHas('lots')
                ->with(['category', 'certifications', 'lots.environmentalImpact', 'lots.certifications'])
                ->latest()
                ->limit(4)
                ->get(),
            'stats' => [
                'lots' => Lot::publiclyVisible()->count(),
                'organizations' => Organization::where('is_verified', true)->count(),
                'certifications' => Certification::where('status', CertificationStatus::VERIFIED)->count(),
            ],
        ]);
    }

    public function howItWorks(): View
    {
        return view('public.how-it-works', [
            'footprint' => config('footprint'),
            'trust' => config('trust'),
        ]);
    }
}
