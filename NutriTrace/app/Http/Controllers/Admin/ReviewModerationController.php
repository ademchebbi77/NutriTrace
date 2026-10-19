<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\Ai\ReviewModerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Modération des avis": reviews held by the local AI, waiting for the admin, and the
 * latest decisions.
 */
class ReviewModerationController extends Controller
{
    public function __construct(private readonly ReviewModerator $moderator) {}

    public function index(): View
    {
        return view('admin.reviews.index', [
            'pending' => Review::where('moderation_status', Review::PENDING)->with(['user', 'product'])->oldest('updated_at')->get(),
            'decided' => Review::whereNotNull('moderated_at')->with(['user', 'product', 'moderator'])->latest('moderated_at')->limit(30)->get(),
            'canExplain' => $this->moderator->canExplain(),
        ]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $this->moderator->decide($review, request()->user(), publish: true);

        return back()->with('success', __('ai.moderation.approved'));
    }

    public function reject(Review $review): RedirectResponse
    {
        $this->moderator->decide($review, request()->user(), publish: false);

        return back()->with('success', __('ai.moderation.rejected'));
    }

    public function explain(Review $review): RedirectResponse
    {
        $explanation = $this->moderator->explain($review);

        return back()->with($explanation ? 'success' : 'error', $explanation ? __('ai.moderation.explained') : __('ai.impact.unavailable'));
    }
}
