<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Rules\Ean;
use Database\Seeders\Concerns\MakesProductImages;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    use MakesProductImages;

    /**
     * Categories and products of the demo producers and transformers.
     */
    public function run(): void
    {
        $categories = collect([
            'Huiles et olives', 'Fruits et légumes', 'Produits laitiers', 'Miels', 'Céréales', 'Conserves et épicerie',
        ])->mapWithKeys(fn (string $name) => [$name => Category::create(['name' => $name])]);

        $products = [
            // [owner email, name, category, origin, status, background color, description]
            ['producteur@nutritrace.test', 'Olives Chemlali', 'Huiles et olives', 'Sfax, Tunisie', ProductStatus::PUBLISHED, '#6b8e23', 'Olives de variété Chemlali récoltées à la main, destinées à la trituration.'],
            ['producteur@nutritrace.test', 'Olives de table Meski', 'Huiles et olives', 'Sfax, Tunisie', ProductStatus::PUBLISHED, '#556b2f', 'Olives vertes charnues de variété Meski, en saumure naturelle.'],
            ['producteur2@nutritrace.test', 'Lait cru de vache', 'Produits laitiers', 'Béja, Tunisie', ProductStatus::PUBLISHED, '#5b9bd5', 'Lait entier collecté chaque matin auprès du troupeau de la ferme.'],
            ['producteur2@nutritrace.test', 'Blé dur Karim', 'Céréales', 'Béja, Tunisie', ProductStatus::PUBLISHED, '#d4a017', 'Blé dur de variété Karim cultivé en pluvial dans le nord-ouest.'],
            ['producteur3@nutritrace.test', 'Tomates de plein champ', 'Fruits et légumes', 'Korba, Nabeul, Tunisie', ProductStatus::PUBLISHED, '#d9453b', 'Tomates rondes de saison cultivées en plein champ au Cap Bon.'],
            ['producteur3@nutritrace.test', 'Oranges Maltaises', 'Fruits et légumes', 'Cap Bon, Tunisie', ProductStatus::PUBLISHED, '#f28c28', 'Oranges demi-sanguines Maltaises, spécialité du Cap Bon.'],
            ['producteur3@nutritrace.test', 'Piments Baklouti', 'Fruits et légumes', 'Korba, Nabeul, Tunisie', ProductStatus::DRAFT, '#b22222', 'Piments forts Baklouti, base de la harissa traditionnelle.'],
            ['producteur4@nutritrace.test', 'Miel de thym', 'Miels', 'Zaghouan, Tunisie', ProductStatus::PUBLISHED, '#c9871f', 'Miel de thym de montagne, récolté en petites quantités.'],
            ['producteur4@nutritrace.test', 'Miel de romarin', 'Miels', 'Zaghouan, Tunisie', ProductStatus::PUBLISHED, '#b8860b', 'Miel clair et doux butiné sur les romarins du djebel.'],
            ['transformateur@nutritrace.test', 'Huile d\'olive extra vierge', 'Huiles et olives', 'Sfax, Tunisie', ProductStatus::PUBLISHED, '#808000', 'Huile extraite à froid à partir d\'olives Chemlali triturées dans les 24 heures.'],
            ['transformateur2@nutritrace.test', 'Fromage Sicilien de Béja', 'Produits laitiers', 'Béja, Tunisie', ProductStatus::PUBLISHED, '#e0b354', 'Fromage frais traditionnel de Béja au lait de vache.'],
        ];

        $owners = User::whereIn('email', array_unique(array_column($products, 0)))->get()->keyBy('email');

        foreach ($products as $index => [$email, $name, $category, $origin, $status, $color, $description]) {
            $payload = '619'.str_pad((string) (100000001 + $index * 137), 9, '0', STR_PAD_LEFT);

            $product = new Product([
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'description' => $description,
                'origin' => $origin,
                'status' => $status,
                'barcode' => $payload.Ean::checkDigit($payload),
            ]);

            $product->created_by = $owners[$email]->id;
            $product->image_path = $this->placeholderImage($name, $color);
            $product->save();
        }
    }
}
