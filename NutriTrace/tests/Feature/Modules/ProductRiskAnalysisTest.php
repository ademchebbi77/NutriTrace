<?php

namespace Tests\Feature\Modules;

use App\Enums\CertificationStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Certification;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use App\Services\Ai\CohereClient;
use App\Services\Ai\ProductRiskAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductRiskAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_producer_can_view_risk_analysis_for_their_product(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        $response = $this->actingAs($producer)
            ->get("/producteur/produits/{$product->id}/analyse-risques");

        $response->assertOk()
            ->assertSee('Analyse de Risques')
            ->assertSee($product->name);
    }

    public function test_a_producer_cannot_view_risk_analysis_for_other_producers_products(): void
    {
        $producer = User::factory()->producer()->create();
        $otherProduct = Product::factory()->create();

        $response = $this->actingAs($producer)
            ->get("/producteur/produits/{$otherProduct->id}/analyse-risques");

        $response->assertForbidden();
    }

    public function test_admin_can_view_risk_analysis_for_any_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)
            ->get("/admin/produits/{$product->id}/analyse-risques");

        $response->assertOk()
            ->assertSee('Analyse de Risques');
    }

    public function test_risk_analysis_detects_missing_origin(): void
    {
        $product = Product::factory()->create(['origin' => null]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertTrue($analysis['success']);
        $this->assertGreaterThan(0, $analysis['categories']['traceability_completeness']['score']);
        $this->assertStringContainsString('Origine', implode(' ', $analysis['categories']['traceability_completeness']['issues']));
    }

    public function test_risk_analysis_detects_missing_description(): void
    {
        $product = Product::factory()->create(['description' => null]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertGreaterThan(0, $analysis['categories']['traceability_completeness']['score']);
        $this->assertStringContainsString('Description', implode(' ', $analysis['categories']['traceability_completeness']['issues']));
    }

    public function test_risk_analysis_detects_draft_status(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::DRAFT]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertGreaterThan(0, $analysis['categories']['traceability_completeness']['score']);
        $this->assertStringContainsString('brouillon', implode(' ', $analysis['categories']['traceability_completeness']['issues']));
    }

    public function test_risk_analysis_detects_products_without_lots(): void
    {
        $product = Product::factory()->create();
        // No lots created

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertGreaterThan(0, $analysis['categories']['traceability_completeness']['score']);
        $this->assertStringContainsString('Aucun lot', implode(' ', $analysis['categories']['traceability_completeness']['issues']));
    }

    public function test_risk_analysis_detects_lots_without_production_records(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        // Create lot without production
        $lot = Lot::factory()->create([
            'product_id' => $product->id,
            'current_holder_id' => $producer->id,
            'production_id' => null,
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertGreaterThan(0, $analysis['categories']['traceability_completeness']['score']);
        $this->assertStringContainsString('production', implode(' ', $analysis['categories']['traceability_completeness']['issues']));
    }

    public function test_risk_analysis_detects_inconsistent_dates(): void
    {
        $producer = User::factory()->producer()->create();
        $production = Production::factory()->by($producer)->create([
            'production_date' => now()->subDays(10),
        ]);

        $lot = $production->lot;
        $lot->update([
            'expiration_date' => now()->subDays(20), // Expires before production!
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($lot->product);

        $this->assertGreaterThan(0, $analysis['categories']['data_consistency']['score']);
        $this->assertStringContainsString('péremption antérieure', implode(' ', $analysis['categories']['data_consistency']['issues']));
    }

    public function test_risk_analysis_detects_expired_lots(): void
    {
        $producer = User::factory()->producer()->create();
        $production = Production::factory()->by($producer)->create();

        $lot = $production->lot;
        $lot->update([
            'expiration_date' => now()->subDays(5), // Already expired
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($lot->product);

        $this->assertGreaterThan(0, $analysis['categories']['quality_and_storage']['score']);
        $this->assertStringContainsString('périmé', implode(' ', $analysis['categories']['quality_and_storage']['issues']));
    }

    public function test_risk_analysis_detects_soon_to_expire_lots(): void
    {
        $producer = User::factory()->producer()->create();
        $production = Production::factory()->by($producer)->create();

        $lot = $production->lot;
        $lot->update([
            'expiration_date' => now()->addDays(3), // Expires soon
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($lot->product);

        $this->assertGreaterThan(0, $analysis['categories']['quality_and_storage']['score']);
        $this->assertStringContainsString('expire dans moins de 7 jours', implode(' ', $analysis['categories']['quality_and_storage']['issues']));
    }

    public function test_risk_analysis_detects_expired_certifications(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        Certification::factory()->create([
            'certifiable_type' => Product::class,
            'certifiable_id' => $product->id,
            'owner_id' => $producer->id,
            'status' => CertificationStatus::VERIFIED,
            'expiration_date' => now()->subDays(10), // Expired
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertGreaterThan(0, $analysis['categories']['certification_verification']['score']);
        $this->assertStringContainsString('expirée', implode(' ', $analysis['categories']['certification_verification']['issues']));
    }

    public function test_risk_analysis_detects_pending_certifications(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        Certification::factory()->create([
            'certifiable_type' => Product::class,
            'certifiable_id' => $product->id,
            'owner_id' => $producer->id,
            'status' => CertificationStatus::PENDING,
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertGreaterThan(0, $analysis['categories']['certification_verification']['score']);
        $this->assertStringContainsString('en attente', implode(' ', $analysis['categories']['certification_verification']['issues']));
    }

    public function test_risk_analysis_detects_rejected_certifications(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        Certification::factory()->create([
            'certifiable_type' => Product::class,
            'certifiable_id' => $product->id,
            'owner_id' => $producer->id,
            'status' => CertificationStatus::REJECTED,
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertGreaterThan(0, $analysis['categories']['certification_verification']['score']);
        $this->assertStringContainsString('rejetée', implode(' ', $analysis['categories']['certification_verification']['issues']));
    }

    public function test_risk_analysis_calculates_correct_risk_levels(): void
    {
        $analyzer = app(ProductRiskAnalyzer::class);

        // Very low risk: complete product with all data
        $producer = User::factory()->producer()->create();
        $goodProduct = Product::factory()->create([
            'created_by' => $producer->id,
            'status' => ProductStatus::PUBLISHED,
            'origin' => 'Sfax, Tunisie',
            'description' => 'Excellent product',
        ]);
        Production::factory()->by($producer)->create(['product_id' => $goodProduct->id]);

        $goodAnalysis = $analyzer->analyze($goodProduct);
        $this->assertContains($goodAnalysis['risk_level'], ['very_low', 'low']);

        // High risk: product with multiple issues
        $badProduct = Product::factory()->create([
            'status' => ProductStatus::DRAFT,
            'origin' => null,
            'description' => null,
        ]);

        $badAnalysis = $analyzer->analyze($badProduct);
        $this->assertGreaterThanOrEqual(30, $badAnalysis['risk_score']);
    }

    public function test_risk_analysis_works_without_cohere_api_configured(): void
    {
        config(['services.cohere.api_key' => null]);

        $product = Product::factory()->create();

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertTrue($analysis['success']);
        $this->assertNull($analysis['ai_explanation']);
        $this->assertNotEmpty($analysis['recommendations']);
        $this->assertIsArray($analysis['categories']);
    }

    public function test_risk_analysis_uses_cohere_when_configured(): void
    {
        // Mock successful Cohere API response
        Http::fake([
            'api.cohere.com/*' => Http::response([
                'id' => 'test-id',
                'finish_reason' => 'COMPLETE',
                'message' => [
                    'content' => [
                        [
                            'text' => "EXPLICATION:\nLe produit présente quelques lacunes de traçabilité.\n\nRECOMMANDATIONS:\n1. Compléter l'origine du produit\n2. Ajouter une description détaillée\n3. Publier le produit",
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.cohere.api_key' => 'test-key',
            'services.cohere.model' => 'command-r-08-2024',
        ]);

        $product = Product::factory()->create([
            'origin' => null,
            'description' => null,
        ]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertTrue($analysis['success']);
        $this->assertNotNull($analysis['ai_explanation']);
        $this->assertStringContainsString('traçabilité', $analysis['ai_explanation']);
        $this->assertNotEmpty($analysis['recommendations']);
    }

    public function test_risk_analysis_handles_cohere_api_failure_gracefully(): void
    {
        // Mock failed Cohere API response
        Http::fake([
            'api.cohere.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429),
        ]);

        config([
            'services.cohere.api_key' => 'test-key',
        ]);

        $product = Product::factory()->create();

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertTrue($analysis['success']);
        $this->assertNull($analysis['ai_explanation']); // Should fallback
        $this->assertNotEmpty($analysis['recommendations']); // Should still have basic recommendations
    }

    public function test_risk_analysis_includes_complete_data_summary(): void
    {
        $producer = User::factory()->producer()->create();
        $category = Category::factory()->create(['name' => 'Olives']);
        $product = Product::factory()->create([
            'created_by' => $producer->id,
            'category_id' => $category->id,
            'name' => 'Olives Chemlali',
            'origin' => 'Sfax',
        ]);

        Production::factory()->by($producer)->create(['product_id' => $product->id]);

        $analyzer = app(ProductRiskAnalyzer::class);
        $analysis = $analyzer->analyze($product);

        $this->assertArrayHasKey('data_summary', $analysis);
        $this->assertEquals('Olives Chemlali', $analysis['data_summary']['product_name']);
        $this->assertEquals('Olives', $analysis['data_summary']['category']);
        $this->assertEquals('Sfax', $analysis['data_summary']['origin']);
        $this->assertEquals(1, $analysis['data_summary']['lots_count']);
    }

    public function test_cohere_client_is_not_configured_without_api_key(): void
    {
        config(['services.cohere.api_key' => null]);

        $client = app(CohereClient::class);

        $this->assertFalse($client->isConfigured());
    }

    public function test_cohere_client_is_configured_with_api_key(): void
    {
        config(['services.cohere.api_key' => 'test-key']);

        $client = app(CohereClient::class);

        $this->assertTrue($client->isConfigured());
    }

    public function test_cohere_client_handles_connection_errors(): void
    {
        Http::fake([
            'api.cohere.com/*' => fn () => throw new ConnectionException('Connection failed'),
        ]);

        config(['services.cohere.api_key' => 'test-key']);

        $client = app(CohereClient::class);
        $response = $client->chat([
            ['role' => 'user', 'content' => 'Test message'],
        ]);

        $this->assertNull($response);
    }

    public function test_distributors_cannot_access_risk_analysis(): void
    {
        $distributor = User::factory()->distributor()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($distributor)
            ->get("/producteur/produits/{$product->id}/analyse-risques");

        $response->assertForbidden();
    }

    public function test_risk_analysis_view_displays_all_categories(): void
    {
        $producer = User::factory()->producer()->create();
        $product = Product::factory()->create(['created_by' => $producer->id]);

        $response = $this->actingAs($producer)
            ->get("/producteur/produits/{$product->id}/analyse-risques");

        $response->assertOk()
            ->assertSee('Complétude de la traçabilité')
            ->assertSee('Cohérence des données')
            ->assertSee('Qualité et conservation')
            ->assertSee('Vérification des certifications');
    }
}
