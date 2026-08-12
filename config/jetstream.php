<?php

use Laravel\Jetstream\Features;

return [
    'stack' => 'inertia',
    'middleware' => 'web',
    'auth_mode' => 'session',
    'home' => '/dashboard',
    'guard' => 'web',
    'password_reset' => true,
    'features' => [
        Features::termsAndPrivacyPolicy(),
        Features::profilePhotos(),
        Features::api(),
        Features::accountDeletion(),
    ],
    'profile_photo_disk' => 'public',
    'profile_photo_path' => 'profile-photos',
    'profile_photo_max_size' => 1024,
];
