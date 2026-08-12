<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Not Found</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-dark text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="text-9xl font-bold bg-gradient-to-r from-purple-400 to-blue-500 bg-clip-text text-transparent mb-4">
            404
        </div>
        <h1 class="text-3xl font-bold mb-4">Page Not Found</h1>
        <p class="text-gray-400 mb-8 max-w-md mx-auto">
            The page you're looking for doesn't exist or has been moved. Check the URL or go back to the homepage.
        </p>
        <a href="{{ url('/') }}" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-8 py-3 rounded-xl font-medium transition">
            Back to Home
        </a>
    </div>
</body>
</html>
