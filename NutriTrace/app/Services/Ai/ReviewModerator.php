<?php

namespace App\Services\Ai;

use App\Models\Review;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Moderation of consumer reviews by the local AI: a review flagged as insulting, spam,
 * off-topic or exposing personal data waits for the admin before being published.
 * When the AI service is off, reviews are published as before.
 */
class ReviewModerator
{
    public function __construct(
        private readonly ImpactAiContext $context,
        private readonly AiClient $client,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Decide the status of a new or edited review (it is saved by the caller).
     */
    public function moderate(Review $review): void
    {
        $result = filled($review->comment) && $this->client->has('moderation')
            ? $this->client->moderateReview($this->context->forReview($review))
            : null;

        $review->forceFill([
            'moderation_status' => ($result['flagged'] ?? false) ? Review::PENDING : Review::PUBLISHED,
            'moderation_label' => $result['label'] ?? null,
            'moderation_confidence' => $result['confidence'] ?? null,
            'moderation_reasons' => $result['reasons'] ?? null,
            'moderation_explanation' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);
    }

    public function explain(Review $review): ?string
    {
        $result = $this->client->moderateReview($this->context->forReview($review), explain: true);

        if (blank($result['explanation'] ?? null)) {
            return null;
        }

        $review->forceFill(['moderation_explanation' => $result['explanation']])->save();

        return $result['explanation'];
    }

    public function decide(Review $review, User $admin, bool $publish): void
    {
        $review->forceFill([
            'moderation_status' => $publish ? Review::PUBLISHED : Review::REJECTED,
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
        ])->save();

        $this->audit->log($publish ? 'review.approved' : 'review.rejected', $review, mb_substr((string) $review->comment, 0, 120));
    }

    public function canExplain(): bool
    {
        return $this->client->canChat();
    }
}
