<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 - Server Error</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-dark text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="text-9xl font-bold bg-gradient-to-r from-red-400 to-pink-500 bg-clip-text text-transparent mb-4">
            500
        </div>
        <h1 class="text-3xl font-bold mb-4">Server Error</h1>
        <p class="text-gray-400 mb-8 max-w-md mx-auto">
            Something went wrong on our end. Our team has been notified and is working to resolve the issue.
        </p>
        <button onclick="window.location.reload()" class="inline-block bg-purple-600 hover:bg-purple-700 text-white px-8 py-3 rounded-xl font-medium transition">
            Try Again
        </button>
    </div>
</body>
</html>
