<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\Unit;
use App\Models\Lot;
use App\Models\Transformation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Transformation::class);
    }

    /**
     * The form sends one quantity per available lot; only the lots actually used are kept.
     */
    protected function prepareForValidation(): void
    {
        $inputs = collect($this->input('quantities', []))
            ->filter(fn ($quantity) => is_numeric($quantity) && (float) $quantity > 0)
            ->map(fn ($quantity, $lotId) => ['lot_id' => (int) $lotId, 'quantity_used' => (float) $quantity])
            ->values()
            ->all();

        $this->merge(['inputs' => $inputs]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.lot_id' => ['required', 'integer', 'distinct'],
            'inputs.*.quantity_used' => ['required', 'numeric', 'gt:0'],
            // Only the transformer's own, non-archived products.
            'output_product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('created_by', $this->user()->id)
                    ->whereNot('status', ProductStatus::ARCHIVED->value),
            ],
            'output_quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'output_unit' => ['required', Rule::enum(Unit::class)],
            'transformation_date' => ['required', 'date', 'before_or_equal:today'],
            'expiration_date' => ['nullable', 'date', 'after:transformation_date'],
            'process_description' => ['required', 'string', 'min:10', 'max:2000'],
            'energy_used_kwh' => ['nullable', 'numeric', 'min:0'],
            'water_used_l' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Source lots must be held by the transformer, available, in sufficient quantity,
     * share one unit, and not be younger than the transformation.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $inputs = collect($this->input('inputs'))->keyBy('lot_id');
                $lots = Lot::query()->whereKey($inputs->keys())->get()->keyBy('id');

                foreach ($inputs as $lotId => $input) {
                    $lot = $lots->get($lotId);

                    if (! $lot || ! $lot->isHeldBy($this->user()) || ! $lot->isAvailable()) {
                        $validator->errors()->add('inputs', __('transformations.validation.input_unavailable'));

                        return;
                    }

                    if ($input['quantity_used'] > $lot->quantity) {
                        $validator->errors()->add("quantities.$lotId", __('transformations.validation.input_too_much', [
                            'lot' => $lot->lot_number,
                            'max' => $lot->formattedQuantity(),
                        ]));
                    }

                    if ($lot->production_date->gt($this->date('transformation_date'))) {
                        $validator->errors()->add('transformation_date', __('transformations.validation.before_source', ['lot' => $lot->lot_number]));
                    }
                }

                if ($lots->pluck('unit')->unique()->count() > 1) {
                    $validator->errors()->add('inputs', __('transformations.validation.mixed_units'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['inputs.required' => __('transformations.validation.no_input')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('transformations.attributes');
    }
}
