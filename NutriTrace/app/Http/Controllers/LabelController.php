<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Printable labels with the QR code that opens the public traceability page of a lot.
 */
class LabelController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lot::class);

        return view('labels.index', [
            'lots' => Lot::heldBy($request->user())->with('product')->latest()->get(),
        ]);
    }

    public function show(Lot $lot): View
    {
        Gate::authorize('view', $lot);

        return view('labels.show', [
            'lot' => $lot->load(['product', 'environmentalImpact']),
            'qr' => QrCode::format('svg')->size(220)->margin(1)->errorCorrection('M')->generate($lot->publicUrl()),
        ]);
    }
}
