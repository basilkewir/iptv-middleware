<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Forbidden</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-dark text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="text-9xl font-bold bg-gradient-to-r from-orange-400 to-yellow-500 bg-clip-text text-transparent mb-4">
            403
        </div>
        <h1 class="text-3xl font-bold mb-4">Access Forbidden</h1>
        <p class="text-gray-400 mb-8 max-w-md mx-auto">
            You don't have the required permissions to view this page. Contact your administrator if you believe this is an error.
        </p>
        <a href="{{ url('/') }}" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-8 py-3 rounded-xl font-medium transition">
            Back to Home
        </a>
    </div>
</body>
</html>
