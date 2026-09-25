<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use LogicException;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Every field is optional so the API can apply partial updates, and a blank
     * password leaves the current password unchanged.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', ...$this->nameRules()],
            'email' => ['sometimes', ...$this->emailRules($this->targetUser()->id)],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'admin' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $isRemovingOwnAdminAccess = $this->user()->is($this->targetUser())
                    && $this->has('admin')
                    && ! $this->boolean('admin');

                if ($isRemovingOwnAdminAccess) {
                    $validator->errors()->add('admin', __('You cannot remove your own administrator access.'));
                }
            },
        ];
    }

    /**
     * Get the user being updated.
     */
    protected function targetUser(): User
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            throw new LogicException('The update user request must be used on a route bound to a user.');
        }

        return $user;
    }
}
