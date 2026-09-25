<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'admin' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Apply the validated input to the given user without saving it.
     *
     * `admin` and `email_verified_at` are not mass assignable, so they are set explicitly here.
     */
    public function fillUser(User $user): User
    {
        $validated = $this->safe();

        $user->fill($validated->only(['name', 'email']));

        if ($this->filled('password')) {
            $user->password = $validated['password'];
        }

        if ($validated->has('admin')) {
            $user->admin = $this->boolean('admin');
        }

        if ($validated->has('email_verified')) {
            $user->email_verified_at = $this->boolean('email_verified')
                ? ($user->email_verified_at ?? now())
                : null;
        } elseif ($user->exists && $user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        return $user;
    }
}
