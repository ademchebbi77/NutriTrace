<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function toggleFavorite(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->isPubliclyVisible(), 404);

        $changes = $request->user()->favoriteProducts()->toggle([$product->id => ['created_at' => now()]]);

        return back()->with('success', __($changes['attached'] ? 'public.favorites.added' : 'public.favorites.removed'));
    }
}
