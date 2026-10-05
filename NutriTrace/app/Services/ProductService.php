<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $owner, array $data, ?UploadedFile $image = null): Product
    {
        $product = new Product($data);
        $product->created_by = $owner->id;
        $product->image_path = $image?->store('products', 'public');
        $product->save();

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, ?UploadedFile $image = null): Product
    {
        $product->fill($data);

        if ($image) {
            $previous = $product->image_path;
            $product->image_path = $image->store('products', 'public');

            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        }

        $product->save();

        return $product;
    }

    public function delete(Product $product): void
    {
        $image = $product->image_path;

        $product->delete();

        if ($image) {
            Storage::disk('public')->delete($image);
        }
    }
}
