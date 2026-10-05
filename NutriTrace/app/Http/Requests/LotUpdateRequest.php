<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LotUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lot'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productionDate = $this->route('lot')->production_date->toDateString();

        return [
            'expiration_date' => ['nullable', 'date', 'after:'.$productionDate],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('lots.attributes');
    }
}
