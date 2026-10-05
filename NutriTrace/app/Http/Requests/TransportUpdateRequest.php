<?php

namespace App\Http\Requests;

use App\Enums\TransportType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransportUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('transport'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transport_type' => ['required', Rule::enum(TransportType::class)],
            'distance_km' => ['required', 'numeric', 'min:0', 'max:40000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('transfers.attributes');
    }
}
