<?php

namespace App\Fortify\Actions;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\SendsPasswordResetLinks;

class SendPasswordResetLink implements SendsPasswordResetLinks
{
    /**
     * Send a reset link to the user.
     */
    public function send(array $input): string
    {
        Validator::make($input, [
            'email' => ['required', 'string', 'email'],
        ])->validate();

        return Password::sendResetLink(
            $input + ['email' => $input['email']]
        );
    }
}
