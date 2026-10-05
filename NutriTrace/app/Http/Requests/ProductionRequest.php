<?php

namespace App\Http\Requests;

use App\Enums\ProductionMethod;
use App\Enums\ProductStatus;
use App\Enums\Unit;
use App\Models\Production;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionRequest extends FormRequest
{
    /**
     * Authorize before validating, so a forbidden request never leaks validation errors.
     */
    public function authorize(): bool
    {
        $production = $this->route('production');

        return $production
            ? $this->user()->can('update', $production)
            : $this->user()->can('create', Production::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Only the producer's own, non-archived products.
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('created_by', $this->user()->id)
                    ->whereNot('status', ProductStatus::ARCHIVED->value),
            ],
            'location_address' => ['nullable', 'string', 'max:255'],
            'location_city' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'production_date' => ['required', 'date', 'before_or_equal:today'],
            'expiration_date' => ['nullable', 'date', 'after:production_date'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'unit' => ['required', Rule::enum(Unit::class)],
            'production_method' => ['required', Rule::enum(ProductionMethod::class)],
            'resources_used' => ['nullable', 'array:water_l,energy_kwh,fertilizer_kg,pesticide_kg,notes'],
            'resources_used.water_l' => ['nullable', 'numeric', 'min:0'],
            'resources_used.energy_kwh' => ['nullable', 'numeric', 'min:0'],
            'resources_used.fertilizer_kg' => ['nullable', 'numeric', 'min:0'],
            'resources_used.pesticide_kg' => ['nullable', 'numeric', 'min:0'],
            'resources_used.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('productions.attributes');
    }
}
