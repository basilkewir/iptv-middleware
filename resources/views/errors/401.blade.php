<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>401 - Unauthorized</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-dark text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="text-9xl font-bold bg-gradient-to-r from-red-400 to-orange-500 bg-clip-text text-transparent mb-4">
            401
        </div>
        <h1 class="text-3xl font-bold mb-4">Unauthorized Access</h1>
        <p class="text-gray-400 mb-8 max-w-md mx-auto">
            You don't have permission to access this resource. Please login with valid credentials.
        </p>
        <a href="{{ route('login') }}" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-8 py-3 rounded-xl font-medium transition">
            Go to Login
        </a>
    </div>
</body>
</html>
