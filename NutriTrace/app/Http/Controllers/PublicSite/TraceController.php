<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\ReportType;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Lot;
use App\Services\LotInsightsBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TraceController extends Controller
{
    /**
     * Public traceability page of a lot, reached by QR code. No login required.
     */
    public function show(Request $request, string $token, LotInsightsBuilder $insights): View
    {
        $lot = $this->findLot($token);

        // Remember the visit in the consumer's history.
        if ($user = $request->user()) {
            $user->viewedLots()->syncWithoutDetaching([$lot->id => ['viewed_at' => now()]]);
            $user->viewedLots()->updateExistingPivot($lot->id, ['viewed_at' => now()]);
        }

        $lot->product->load('reviews.user');

        return view('public.trace', [
            'insights' => $insights->for($lot),
            'lot' => $lot,
            'reviews' => $lot->product->reviews->sortByDesc('created_at')->values(),
            'ownReview' => $user?->reviews()->where('product_id', $lot->product_id)->first(),
            'otherLots' => Lot::publiclyVisible()
                ->where('product_id', $lot->product_id)
                ->whereKeyNot($lot->id)
                ->with('environmentalImpact')
                ->latest('production_date')
                ->limit(4)
                ->get(),
            'reportTypes' => ReportType::cases(),
            'qr' => QrCode::format('svg')->size(140)->margin(1)->generate($lot->publicUrl()),
        ]);
    }

    /**
     * Proof of a certification, public only when the certification is valid
     * and belongs to this lot or its product.
     */
    public function proof(string $token, Certification $certification): StreamedResponse
    {
        $lot = $this->findLot($token);
        $lot->loadMissing(['certifications', 'product.certifications']);

        abort_unless($certification->isValid() && $lot->allCertifications()->contains('id', $certification->id), 404);

        return CertificationController::streamDocument($certification);
    }

    private function findLot(string $token): Lot
    {
        return Lot::publiclyVisible()->where('public_token', $token)->firstOrFail();
    }
}
