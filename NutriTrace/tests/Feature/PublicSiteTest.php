<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Certification;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsJourneys;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use BuildsJourneys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildOliveOilJourney();
    }

    public function test_the_trace_page_is_public_and_tells_the_whole_story(): void
    {
        $this->get("/trace/{$this->oilLot->public_token}")
            ->assertOk()
            ->assertSee($this->oilLot->lot_number)
            ->assertSee('Huile d&#039;olive extra vierge', false)
            // Timeline back to the farm, with the source lot.
            ->assertSee(__('enums.event_type.PRODUCTION'))
            ->assertSee(__('public.trace.source_lot', ['number' => $this->oliveLot->lot_number]))
            ->assertSee($this->producer->displayName())
            // Map, footprint with sources, score and chain state.
            ->assertSee('id="journeyMap"', false)
            ->assertSee('id="stagesChart"', false)
            ->assertSee(__('enums.data_source.CALCULATED'))
            ->assertSee(__('enums.data_source.PROVIDED'))
            ->assertSee(__('trust.why'))
            ->assertSee(__('public.trace.chain_valid', ['count' => 10]))
            ->assertSee(__('trust.local.no'))
            ->assertDontSee(__('reports.under_investigation'))
            // Guests are invited to sign in before reviewing.
            ->assertSee(__('reviews.login_to_review'));
    }

    public function test_private_contact_details_never_appear_on_public_pages(): void
    {
        $this->producer->update(['phone' => '+216 99 111 222']);
        $organization = $this->producer->organization;
        $organization->update(['address' => '12 impasse Secrète', 'registration_number' => 'MF-SECRET-42']);

        foreach (["/trace/{$this->oilLot->public_token}", "/trace/{$this->oliveLot->public_token}", "/produits/{$this->oliveLot->product_id}", "/api/v1/trace/{$this->oliveLot->public_token}"] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee($this->producer->email)
                ->assertDontSee('+216 99 111 222')
                ->assertDontSee('12 impasse Secrète')
                ->assertDontSee('MF-SECRET-42');
        }
    }

    public function test_lots_of_draft_products_and_unknown_tokens_are_not_public(): void
    {
        $this->oilLot->product->update(['status' => ProductStatus::DRAFT]);

        $this->get("/trace/{$this->oilLot->public_token}")->assertNotFound();
        $this->get("/api/v1/trace/{$this->oilLot->public_token}")->assertNotFound();
        $this->get("/produits/{$this->oilLot->product_id}")->assertNotFound();
        $this->get('/trace/inconnu')->assertNotFound();

        // Archived products stay traceable: their lots may still be on sale.
        $this->oilLot->product->update(['status' => ProductStatus::ARCHIVED]);
        $this->get("/trace/{$this->oilLot->public_token}")->assertOk();
    }

    public function test_a_tampered_journal_is_shown_to_consumers(): void
    {
        DB::table('traceability_events')->where('lot_id', $this->oilLot->id)->limit(1)->update(['description' => 'Falsifié']);

        $this->get("/trace/{$this->oilLot->public_token}")
            ->assertOk()
            ->assertSee(__('public.trace.chain_broken'))
            ->assertSee(__('warnings.messages.chain_broken'));
    }

    public function test_only_valid_certifications_are_shown_as_valid_and_their_proof_is_public(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('preuve.pdf', 20, 'application/pdf')->store('certifications', 'local');

        $valid = Certification::factory()->bio()->verified()->for($this->oilLot, 'certifiable')->create(['document_path' => $path]);
        $pending = Certification::factory()->local()->for($this->oilLot, 'certifiable')->create(['document_path' => $path]);

        $this->get("/trace/{$this->oilLot->public_token}")
            ->assertSee(__('public.trace.cert_valid'))
            ->assertSee($valid->certificate_number)
            ->assertSee(__('enums.certification_status.PENDING'))
            ->assertSee(__('warnings.messages.certificate_pending'));

        $this->get("/trace/{$this->oilLot->public_token}/certifications/{$valid->id}/preuve")->assertOk();
        $this->get("/trace/{$this->oilLot->public_token}/certifications/{$pending->id}/preuve")->assertNotFound();
        // A valid proof cannot be fetched through another lot.
        $this->get("/trace/{$this->oliveLot->public_token}/certifications/{$valid->id}/preuve")->assertNotFound();
    }

    public function test_home_and_static_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee(__('public.home.featured'))->assertSee('Huile d&#039;olive extra vierge', false);
        $this->get('/comment-ca-marche')->assertOk()->assertSee(__('public.how.trust_title'))->assertSee('0.105');
    }

    public function test_the_catalog_lists_published_products_with_filters(): void
    {
        $honey = Product::factory()->create(['name' => 'Miel de thym', 'origin' => 'Zaghouan, Tunisie', 'category_id' => Category::factory()->create(['name' => 'Miels'])->id]);
        Product::factory()->draft()->create(['name' => 'Produit secret']);
        Certification::factory()->bio()->verified()->for($honey, 'certifiable')->create();
        Certification::factory()->bio()->for($this->oilLot->product, 'certifiable')->create();

        $this->get('/produits')
            ->assertOk()
            ->assertSee('Miel de thym')
            ->assertSee('Huile d&#039;olive extra vierge', false)
            ->assertDontSee('Produit secret');

        $this->get('/produits?q=miel')->assertSee('Miel de thym')->assertDontSee('Huile d&#039;olive', false);
        $this->get('/produits?category='.$honey->category_id)->assertSee('Miel de thym')->assertDontSee('Huile d&#039;olive', false);
        $this->get('/produits?origin=Zaghouan')->assertSee('Miel de thym')->assertDontSee('Huile d&#039;olive', false);
        // Only verified certifications match the filter: the oil's is still pending.
        $this->get('/produits?certification=BIO')->assertSee('Miel de thym')->assertDontSee('Huile d&#039;olive', false);

        $grade = $this->oilLot->environmentalImpact->grade;
        $this->get('/produits?grade='.$grade)->assertSee('Huile d&#039;olive', false)->assertDontSee('Miel de thym');
        $this->get('/produits?q=introuvable')->assertSee(__('public.catalog.empty'));

        $this->get("/produits/{$this->oilLot->product_id}")->assertOk()->assertSee($this->oilLot->lot_number);
    }

    public function test_lookup_finds_a_lot_by_number_or_token_and_a_product_by_barcode(): void
    {
        $this->oilLot->product->update(['barcode' => '6191234567897']);

        $this->get('/recherche?q='.strtolower($this->oilLot->lot_number))->assertRedirect("/trace/{$this->oilLot->public_token}");
        $this->get('/recherche?q='.$this->oilLot->public_token)->assertRedirect("/trace/{$this->oilLot->public_token}");
        $this->get('/recherche?q=6191234567897')->assertRedirect("/produits/{$this->oilLot->product_id}");
        $this->get('/recherche?q=olive')->assertRedirect('/produits?q=olive');
        $this->get('/recherche')->assertRedirect('/produits');
    }

    public function test_two_or_three_lots_can_be_compared(): void
    {
        $this->get('/comparer')->assertOk()->assertSee(__('public.compare.need_two'));

        $this->get('/comparer?lots[]='.$this->oilLot->lot_number.'&lots[]='.$this->oliveLot->lot_number.'&lots[]=LOT-0000-000')
            ->assertOk()
            ->assertSee($this->oilLot->lot_number)
            ->assertSee($this->oliveLot->lot_number)
            ->assertSee(__('public.compare.rows.co2_per_kg'))
            ->assertSee(__('public.compare.rows.trust'))
            ->assertSee(__('public.compare.not_found', ['numbers' => 'LOT-0000-000']));
    }

    public function test_the_json_api_exposes_the_lot_read_only(): void
    {
        $response = $this->getJson("/api/v1/trace/{$this->oilLot->public_token}")->assertOk();

        $response
            ->assertJsonPath('data.lot.number', $this->oilLot->lot_number)
            ->assertJsonPath('data.product.name', 'Huile d\'olive extra vierge')
            ->assertJsonPath('data.chain.valid', true)
            ->assertJsonPath('data.source_lots.0', $this->oliveLot->lot_number)
            ->assertJsonPath('data.environmental_impact.indicators.co2_kg.source', 'CALCULATED')
            ->assertJsonPath('data.transparency.score', $this->oilLot->fresh()->trust_score)
            ->assertJsonCount(10, 'data.journey')
            ->assertJsonStructure(['data' => ['journey' => [['type', 'occurred_at', 'description', 'location', 'actor', 'hash']], 'warnings', 'certifications', 'local']]);

        $this->postJson("/api/v1/trace/{$this->oilLot->public_token}")->assertStatus(405);
        $this->getJson('/api/v1/trace/inconnu')->assertNotFound();
    }

    public function test_the_api_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/trace/inconnu');
        }

        $this->getJson('/api/v1/trace/inconnu')->assertStatus(429);
    }

    public function test_a_consumer_reviews_a_product_once(): void
    {
        $consumer = User::factory()->create();
        $url = "/trace/{$this->oilLot->public_token}/avis";

        $this->post($url, ['rating' => 5])->assertRedirect('/login');
        $this->actingAs($consumer)->post($url, ['rating' => 9])->assertSessionHasErrorsIn('review', 'rating');

        $this->actingAs($consumer)->post($url, ['rating' => 4, 'comment' => 'Très bonne huile.'])->assertSessionHas('success');
        $this->actingAs($consumer)->post($url, ['rating' => 2, 'comment' => 'Finalement décevante.']);

        $this->assertSame(1, Review::count());
        $this->assertSame(2, Review::first()->rating);

        $this->get("/trace/{$this->oilLot->public_token}")->assertSee('Finalement décevante.');
        $this->actingAs($consumer)->get('/consommateur/avis')->assertOk()->assertSee('Finalement décevante.');

        // Professionals of the chain do not review products.
        $this->actingAs($this->producer)->post($url, ['rating' => 5])->assertForbidden();

        $this->actingAs(User::factory()->create())->delete('/avis/'.Review::first()->id)->assertForbidden();
        $this->actingAs($consumer)->delete('/avis/'.Review::first()->id)->assertSessionHas('success');
        $this->assertSame(0, Review::count());
    }

    public function test_a_report_is_moderated_and_shows_a_banner_while_under_review(): void
    {
        $consumer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $url = "/trace/{$this->oilLot->public_token}/signalement";
        $score = $this->oilLot->trust_score;

        $this->post($url, [])->assertRedirect('/login');
        $this->actingAs($consumer)->post($url, ['target' => 'lot', 'type' => 'SUSPICIOUS_INFORMATION', 'description' => 'court'])
            ->assertSessionHasErrorsIn('report', 'description');
        $this->actingAs($consumer)->post($url, ['target' => 'certification:999', 'type' => 'INVALID_CERTIFICATION', 'description' => 'Ce certificat semble faux.'])
            ->assertSessionHasErrorsIn('report', 'target');

        $this->actingAs($consumer)->post($url, [
            'target' => 'impact',
            'type' => 'MISLEADING_ENVIRONMENTAL_CLAIM',
            'description' => 'Le chiffre de CO2 paraît trop faible pour ce trajet.',
        ])->assertSessionHas('success');

        $report = Report::firstOrFail();
        $this->assertTrue($report->reportable->is($this->oilLot->environmentalImpact));
        // An open report costs transparency points.
        $this->assertSame($score - 5, $this->oilLot->fresh()->trust_score);
        $this->get("/trace/{$this->oilLot->public_token}")->assertDontSee(__('reports.under_investigation'));

        $this->actingAs($consumer)->get('/consommateur/signalements')->assertOk()->assertSee('trop faible');
        $this->actingAs($admin)->get('/admin/signalements')->assertOk()->assertSee($this->oilLot->lot_number);
        $this->actingAs($admin)->get("/admin/signalements/{$report->id}")->assertOk();

        $this->actingAs($admin)->put("/admin/signalements/{$report->id}", ['status' => 'UNDER_REVIEW'])->assertSessionHas('success');
        $this->get("/trace/{$this->oilLot->public_token}")->assertSee(__('reports.under_investigation'));

        // Closing needs an answer for the author.
        $this->actingAs($admin)->put("/admin/signalements/{$report->id}", ['status' => 'RESOLVED'])->assertSessionHasErrors('admin_response');
        $this->actingAs($admin)->put("/admin/signalements/{$report->id}", ['status' => 'RESOLVED', 'admin_response' => 'Chiffre vérifié avec le transformateur.']);

        $this->assertSame(ReportStatus::RESOLVED, $report->fresh()->status);
        $this->assertSame($score, $this->oilLot->fresh()->trust_score);
        $this->get("/trace/{$this->oilLot->public_token}")->assertDontSee(__('reports.under_investigation'));
        $this->actingAs($consumer)->get('/consommateur/signalements')->assertSee('Chiffre vérifié');
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.resolved']);

        $this->actingAs($this->producer)->put("/admin/signalements/{$report->id}", ['status' => 'REJECTED', 'admin_response' => 'Non.'])->assertForbidden();
        $this->actingAs($admin)->get('/admin/journal')->assertOk()->assertSee(__('admin.audit.actions.report_resolved'));
    }

    public function test_the_consumer_area_keeps_history_and_favorites(): void
    {
        $consumer = User::factory()->create();

        $this->actingAs($consumer)->get("/trace/{$this->oilLot->public_token}")->assertOk();
        $this->actingAs($consumer)->get("/trace/{$this->oilLot->public_token}")->assertOk();
        $this->assertSame(1, $consumer->viewedLots()->count());

        $this->actingAs($consumer)->get('/consommateur/historique')->assertOk()->assertSee($this->oilLot->lot_number);
        $this->actingAs($consumer)->get('/consommateur')->assertOk()->assertSee($this->oilLot->lot_number);

        $this->actingAs($consumer)->post("/produits/{$this->oilLot->product_id}/favori")->assertSessionHas('success');
        $this->actingAs($consumer)->get('/consommateur/favoris')->assertOk()->assertSee('Huile d&#039;olive extra vierge', false);
        $this->actingAs($consumer)->post("/produits/{$this->oilLot->product_id}/favori");
        $this->assertSame(0, $consumer->favoriteProducts()->count());

        // The area belongs to consumers.
        $this->actingAs($this->producer)->get('/consommateur/historique')->assertForbidden();
        $this->assertSame(0, Lot::find($this->oliveLot->id)->reviews()->count());
    }
}
