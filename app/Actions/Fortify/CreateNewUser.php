<?php

namespace App\Actions\Fortify;

use App\Actions\Publishers\CreatePublisher;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly CreatePublisher $createPublisher,
    ) {}

    /**
     * Crea la cuenta con su espacio personal y, si se registra para una
     * editorial, también la editorial (que queda activa).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'account_type' => ['required', Rule::in(['personal', 'publisher'])],
            'publisher_name' => ['required_if:account_type,publisher', 'nullable', 'string', 'max:120'],
        ], attributes: [
            'account_type' => 'tipo de cuenta',
            'publisher_name' => 'nombre de la editorial',
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->createPublisher->personalFor($user);

            if ($input['account_type'] === 'publisher') {
                $this->createPublisher->handle($user, trim($input['publisher_name']));
            }

            return $user;
        });
    }
}
