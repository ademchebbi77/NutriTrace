<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Rules\Ean;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Authorize before validating, so a forbidden request never leaks validation errors.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:3000'],
            'origin' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'barcode' => ['nullable', new Ean, Rule::unique('products', 'barcode')->ignore($this->route('product'))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('products.attributes');
    }
}
