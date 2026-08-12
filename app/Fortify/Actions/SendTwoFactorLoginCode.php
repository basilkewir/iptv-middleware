<?php

namespace App\Fortify\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\SendsTwoFactorLoginCode;

class SendTwoFactorLoginCode implements SendsTwoFactorLoginCode
{
    /**
     * Send a login code to the user.
     */
    public function send(User $user): string
    {
        return $user->generateTwoFactorCode();
    }
}
