<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $professional = fn () => UserRole::tryFrom((string) $this->input('role'))?->isProfessional() ?? false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // ADMIN is never accepted here: admins come from the seeder or make:admin.
            'role' => ['required', Rule::in(array_column(UserRole::registrable(), 'value'))],
            'organization_name' => [Rule::requiredIf($professional), 'nullable', 'string', 'max:255'],
            'organization_city' => [Rule::requiredIf($professional), 'nullable', 'string', 'max:100'],
            'organization_address' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return __('account.attributes');
    }
}
