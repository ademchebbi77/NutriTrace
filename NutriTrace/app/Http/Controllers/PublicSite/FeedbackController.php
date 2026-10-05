<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What a signed-in visitor can do from a lot page: review the product,
 * report suspicious information, bookmark the product.
 */
class FeedbackController extends Controller
{
    /**
     * One review per user per product: posting again updates the existing one.
     */
    public function review(Request $request, string $token): RedirectResponse
    {
        Gate::authorize('create', Review::class);

        $lot = $this->findLot($token);

        $data = $request->validateWithBag('review', [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [], __('reviews.attributes'));

        $review = Review::where('user_id', $request->user()->id)->where('product_id', $lot->product_id)->first() ?? new Review;
        $review->fill($data);
        $review->user_id = $request->user()->id;
        $review->product_id = $lot->product_id;
        $review->lot_id = $lot->id;
        $review->save();

        return redirect()->to(route('trace.show', $token).'#avis')->with('success', __('reviews.saved'));
    }

    public function destroyReview(Review $review): RedirectResponse
    {
        Gate::authorize('delete', $review);

        $review->delete();

        return back()->with('success', __('reviews.deleted'));
    }

    public function report(Request $request, string $token, ReportService $reports): RedirectResponse
    {
        Gate::authorize('create', Report::class);

        $lot = $this->findLot($token);

        $data = $request->validateWithBag('report', [
            'target' => ['required', 'string', 'regex:/^(lot|product|impact|certification:\d+)$/'],
            'type' => ['required', Rule::enum(ReportType::class)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], __('reports.attributes'));

        $reports->submit($request->user(), $this->resolveTarget($lot, $data['target']), [
            'type' => $data['type'],
            'description' => $data['description'],
        ]);

        return redirect()->route('trace.show', $token)->with('success', __('reports.submitted'));
    }

    public function toggleFavorite(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->isPubliclyVisible(), 404);

        $changes = $request->user()->favoriteProducts()->toggle([$product->id => ['created_at' => now()]]);

        return back()->with('success', __($changes['attached'] ? 'public.favorites.added' : 'public.favorites.removed'));
    }

    /**
     * The reported item must belong to the lot the report was sent from.
     */
    private function resolveTarget(Lot $lot, string $target): Model
    {
        $lot->loadMissing(['certifications', 'product.certifications', 'environmentalImpact']);

        $model = match (true) {
            $target === 'lot' => $lot,
            $target === 'product' => $lot->product,
            $target === 'impact' => $lot->environmentalImpact,
            default => $lot->allCertifications()->firstWhere('id', (int) substr($target, strlen('certification:'))),
        };

        if (! $model) {
            throw ValidationException::withMessages(['target' => __('reports.validation.target')])->errorBag('report');
        }

        return $model;
    }

    private function findLot(string $token): Lot
    {
        return Lot::publiclyVisible()->where('public_token', $token)->firstOrFail();
    }
}
