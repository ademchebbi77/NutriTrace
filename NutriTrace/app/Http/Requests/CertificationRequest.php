<?php

namespace App\Http\Requests;

use App\Enums\CertificationType;
use App\Models\Certification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certification = $this->route('certification');

        return $certification
            ? $this->user()->can('update', $certification)
            : $this->user()->can('create', Certification::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // "product:12" or "lot:5": what the certificate covers. Fixed once created.
            'target' => [Rule::requiredIf(! $this->route('certification')), 'nullable', 'string', 'regex:/^(product|lot):\d+$/'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CertificationType::class)],
            'issuing_organization' => ['required', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['required', 'date', 'before_or_equal:today'],
            'expiration_date' => ['nullable', 'date', 'after:issue_date'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('certifications.attributes');
    }
}
