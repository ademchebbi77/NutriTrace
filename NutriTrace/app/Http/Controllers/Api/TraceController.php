<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TraceResource;
use App\Models\Lot;
use App\Services\LotInsightsBuilder;

class TraceController extends Controller
{
    /**
     * GET /api/v1/trace/{public_token}
     */
    public function show(string $token, LotInsightsBuilder $insights): TraceResource
    {
        $lot = Lot::publiclyVisible()->where('public_token', $token)->firstOrFail();

        return new TraceResource($insights->for($lot));
    }
}
