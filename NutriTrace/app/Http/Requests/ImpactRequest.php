<?php

namespace App\Http\Requests;

use App\Enums\DataSource;
use App\Models\EnvironmentalImpact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('declareImpact', $this->route('lot'));
    }

    /**
     * Keep only the indicators that were actually filled in.
     */
    protected function prepareForValidation(): void
    {
        $declared = collect($this->input('declared', []))
            ->only(EnvironmentalImpact::DECLARABLE)
            ->filter(fn ($row) => is_array($row) && filled($row['value'] ?? null))
            ->all();

        $this->merge(['declared' => $declared]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sources = array_column(DataSource::declarable(), 'value');

        return [
            'declared' => ['present', 'array'],
            'declared.*.value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            // Every declared figure must say where it comes from.
            'declared.*.source' => ['required', Rule::in($sources)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['declared.*.value' => __('impacts.attributes.value'), 'declared.*.source' => __('impacts.attributes.source')];
    }
}
