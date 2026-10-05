<?php

namespace Tests\Feature\Modules;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_producer_can_create_a_product_with_an_image(): void
    {
        Storage::fake('public');

        $producer = User::factory()->producer()->create();

        $this->actingAs($producer)->get('/producteur/produits/create')->assertOk();

        $response = $this->actingAs($producer)->post('/producteur/produits', $this->payload([
            'barcode' => '6191234567897',
            'image' => UploadedFile::fake()->image('olives.jpg'),
        ]));

        $product = Product::firstOrFail();

        $response->assertRedirect("/producteur/produits/{$product->id}");
        $this->assertTrue($product->isOwnedBy($producer));
        $this->assertSame(ProductStatus::PUBLISHED, $product->status);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_product_validation_is_in_french_and_checks_the_barcode(): void
    {
        $producer = User::factory()->producer()->create();

        $this->actingAs($producer)
            ->post('/producteur/produits', $this->payload(['name' => '', 'barcode' => '6191234567890']))
            ->assertSessionHasErrors([
                'name' => 'Le champ nom du produit est obligatoire.',
                'barcode' => __('products.validation.barcode'),
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_uploads_must_be_images(): void
    {
        $producer = User::factory()->producer()->create();

        $this->actingAs($producer)
            ->post('/producteur/produits', $this->payload(['image' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php')]))
            ->assertSessionHasErrors('image');
    }

    public function test_a_producer_only_sees_and_edits_their_own_products(): void
    {
        $producer = User::factory()->producer()->create();
        $mine = Product::factory()->create(['created_by' => $producer->id, 'name' => 'Mes olives']);
        $other = Product::factory()->create(['name' => 'Tomates du voisin']);

        $this->actingAs($producer)->get('/producteur/produits')
            ->assertOk()
            ->assertSee('Mes olives')
            ->assertDontSee('Tomates du voisin');

        $this->actingAs($producer)->get("/producteur/produits/{$other->id}")->assertForbidden();
        $this->actingAs($producer)->get("/producteur/produits/{$other->id}/edit")->assertForbidden();
        $this->actingAs($producer)->put("/producteur/produits/{$other->id}", $this->payload())->assertForbidden();
        $this->actingAs($producer)->delete("/producteur/produits/{$other->id}")->assertForbidden();

        $this->actingAs($producer)
            ->put("/producteur/produits/{$mine->id}", $this->payload(['name' => 'Olives Chemlali', 'status' => 'archived']))
            ->assertRedirect("/producteur/produits/{$mine->id}");

        $this->assertSame('Olives Chemlali', $mine->fresh()->name);
        $this->assertSame(ProductStatus::ARCHIVED, $mine->fresh()->status);
    }

    public function test_a_product_with_lots_cannot_be_deleted(): void
    {
        $producer = User::factory()->producer()->create();
        $free = Product::factory()->create(['created_by' => $producer->id]);
        $used = Production::factory()->by($producer)->create()->product;

        $this->actingAs($producer)->delete("/producteur/produits/{$used->id}")->assertForbidden();
        $this->actingAs($producer)->delete("/producteur/produits/{$free->id}")->assertRedirect('/producteur/produits');

        $this->assertModelExists($used);
        $this->assertModelMissing($free);
    }

    public function test_transformers_manage_products_in_their_own_area(): void
    {
        $transformer = User::factory()->transformer()->create();

        $this->actingAs($transformer)->post('/transformateur/produits', $this->payload(['name' => 'Huile d\'olive']))
            ->assertRedirect();

        $this->actingAs($transformer)->get('/transformateur/produits')->assertOk()->assertSee('Huile d\'olive');
        $this->actingAs($transformer)->get('/producteur/produits')->assertForbidden();
    }

    public function test_distributors_and_consumers_cannot_manage_products(): void
    {
        $this->actingAs(User::factory()->distributor()->create())->get('/producteur/produits')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/producteur/produits', $this->payload())->assertForbidden();
    }

    public function test_admin_reads_every_product_but_cannot_change_them(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Miel de thym']);

        $this->actingAs($admin)->get('/admin/produits')->assertOk()->assertSee('Miel de thym');
        $this->actingAs($admin)->get("/admin/produits/{$product->id}")->assertOk();
        $this->actingAs($admin)->put("/admin/produits/{$product->id}", $this->payload())->assertStatus(405);
    }

    public function test_admin_manages_categories(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/categories', ['name' => 'Miels'])->assertSessionHas('success');
        $category = Category::firstWhere('name', 'Miels');
        $this->assertSame('miels', $category->slug);

        $this->actingAs($admin)->post('/admin/categories', ['name' => 'Miels'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", ['name' => 'Miels et ruches'])
            ->assertRedirect('/admin/categories');
        $this->assertSame('miels-et-ruches', $category->fresh()->slug);

        // A category in use is protected.
        Product::factory()->create(['category_id' => $category->id]);
        $this->actingAs($admin)->delete("/admin/categories/{$category->id}")->assertForbidden();

        $empty = Category::factory()->create();
        $this->actingAs($admin)->delete("/admin/categories/{$empty->id}")->assertSessionHas('success');
        $this->assertModelMissing($empty);

        $this->actingAs(User::factory()->producer()->create())->get('/admin/categories')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Olives Chemlali',
            'category_id' => Category::factory()->create()->id,
            'description' => 'Olives récoltées à la main.',
            'origin' => 'Sfax, Tunisie',
            'status' => 'published',
        ];
    }
}
