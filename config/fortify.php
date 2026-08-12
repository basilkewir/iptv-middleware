<?php

return [
    'username' => 'email',
    'email' => 'username',
    'home' => '/dashboard',
    'views' => false,
    'middleware' => ['web'],
    'guard' => 'web',
    'redirects' => [
        'login' => '/dashboard',
        'logout' => '/login',
    ],
];
