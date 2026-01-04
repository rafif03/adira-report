<?php

namespace App\Actions\Fortify;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $data = [
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ];

        // Always attach the guest role for newly registered users.
        // If the role is missing or soft-deleted, recreate/restore it.
        $guestRole = Role::withTrashed()->where('slug', 'guest')->first();
        if (! $guestRole) {
            $guestRole = Role::create([
                'name' => 'Guest',
                'slug' => 'guest',
            ]);
        } elseif (method_exists($guestRole, 'trashed') && $guestRole->trashed()) {
            $guestRole->restore();
        }

        $data['role_id'] = $guestRole->id;

        return User::create($data);
    }
}
