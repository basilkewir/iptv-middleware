<?php

namespace App\Providers;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(\App\Fortify\Actions\CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(\App\Fortify\Actions\UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(\App\Fortify\Actions\UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(\App\Fortify\Actions\ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $minuteKey = 'login|'.$request->ip().'|'.now()->minute;
            return Limit::perMinute(5)->by($minuteKey);
        });
    }
}
