<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewUpdateAndUserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_consumer_edits_their_own_review_and_it_is_moderated_again(): void
    {
        $consumer = User::factory()->create();
        $review = Review::factory()->for($consumer)->create(['rating' => 2, 'comment' => 'Moyen.']);

        // AI disabled in tests: the edited review is published again.
        $this->actingAs($consumer)->patch("/avis/{$review->id}", ['rating' => 5, 'comment' => 'Finalement excellent.'])
            ->assertSessionHas('success', __('reviews.updated'));

        $review->refresh();
        $this->assertSame(5, $review->rating);
        $this->assertSame('Finalement excellent.', $review->comment);
        $this->assertSame(Review::PUBLISHED, $review->moderation_status);
    }

    public function test_a_review_cannot_be_edited_by_someone_else_or_with_a_wrong_rating(): void
    {
        $review = Review::factory()->create(['rating' => 4]);

        $this->actingAs(User::factory()->create())->patch("/avis/{$review->id}", ['rating' => 1])
            ->assertForbidden();

        $this->actingAs($review->user)->patch("/avis/{$review->id}", ['rating' => 9])
            ->assertSessionHasErrors('rating');
        $this->assertSame(4, $review->fresh()->rating);
    }

    public function test_the_admin_deletes_a_user_but_not_an_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = User::factory()->producer()->create(['name' => 'Ferme à supprimer']);

        $this->actingAs($admin)->from('/admin/utilisateurs')->delete("/admin/utilisateurs/{$producer->id}")
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHas('success');
        $this->assertModelMissing($producer);

        // Another admin, or themselves: refused by the policy.
        $otherAdmin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete("/admin/utilisateurs/{$otherAdmin->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/admin/utilisateurs/{$admin->id}")->assertForbidden();
        $this->assertModelExists($otherAdmin);
        $this->assertModelExists($admin);
    }

    public function test_only_the_admin_area_can_delete_users(): void
    {
        $victim = User::factory()->create();

        $this->actingAs(User::factory()->producer()->create())->delete("/admin/utilisateurs/{$victim->id}")
            ->assertForbidden();
        $this->assertModelExists($victim);
    }
}
