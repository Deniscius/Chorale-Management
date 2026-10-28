<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PersonName;
use App\Rules\ReadableText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name', 'name', 'address', 'profession'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim(preg_replace('/\\s+/u', ' ', $this->input($field)))]);
            }
        }
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $completing = ! $this->user()->member_id && array_intersect(['first_name', 'last_name', 'phone', 'birth_date', 'address', 'profession', 'notify_email', 'notify_sms'], array_keys($this->all())) !== [];

        return [
            'username' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique(User::class)->ignore($this->user()->id)],
            'first_name' => [Rule::requiredIf($completing), 'string', 'max:100', new PersonName],
            'last_name' => [Rule::requiredIf($completing), 'string', 'max:100', new PersonName],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255', new ReadableText],
            'profession' => ['nullable', 'string', 'max:255', new ReadableText],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_sms' => ['sometimes', 'boolean'],
            'name' => [Rule::requiredIf(! $this->user()->member_id && ! $completing), 'string', 'max:255', new PersonName],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                Rule::unique('members', 'email')->ignore($this->user()->member_id),
            ],
        ];
    }
}
