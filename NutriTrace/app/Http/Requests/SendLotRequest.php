<?php

namespace App\Http\Requests;

use App\Enums\AccountStatus;
use App\Enums\TransportType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send', $this->route('lot'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // An approved, active transformer or distributor other than the sender.
            'recipient_id' => [
                'required',
                'integer',
                Rule::notIn([$this->user()->id]),
                Rule::exists('users', 'id')
                    ->whereIn('role', [UserRole::TRANSFORMATEUR->value, UserRole::DISTRIBUTEUR->value])
                    ->where('is_active', true)
                    ->where('account_status', AccountStatus::APPROVED->value),
            ],
            'transport_type' => ['required', Rule::enum(TransportType::class)],
            'departure_date' => [
                'required',
                'date',
                'after_or_equal:'.$this->route('lot')->production_date->toDateString(),
                'before_or_equal:now',
            ],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:40000'],
            'note' => ['nullable', 'string', 'max:500'],
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
