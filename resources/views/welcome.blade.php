<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'IPTV Middleware') }} - Premium Streaming</title>
    <meta name="description" content="Premium IPTV Streaming Middleware - Watch live TV, movies, and on-demand content">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-dark text-white">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="fixed w-full z-50 bg-dark/90 backdrop-blur-md border-b border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-2xl font-bold bg-gradient-to-r from-purple-400 to-blue-500 bg-clip-text text-transparent">
                                IPTV
                            </h1>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="#features" class="text-gray-300 hover:text-white px-3 py-2 text-sm font-medium transition">Features</a>
                            <a href="#pricing" class="text-gray-300 hover:text-white px-3 py-2 text-sm font-medium transition">Pricing</a>
                            <a href="#channels" class="text-gray-300 hover:text-white px-3 py-2 text-sm font-medium transition">Channels</a>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('login') }}" class="text-gray-300 hover:text-white px-3 py-2 text-sm font-medium transition">Login</a>
                        <a href="{{ route('register') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            Get Started
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative pt-32 pb-20 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-purple-900/20 to-dark"></div>
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[600px] bg-purple-600/20 rounded-full blur-3xl"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-5xl md:text-7xl font-extrabold mb-6">
                    <span class="bg-gradient-to-r from-purple-400 via-pink-500 to-blue-500 bg-clip-text text-transparent">
                        Premium IPTV
                    </span>
                    <br>
                    <span class="text-white">Streaming Middleware</span>
                </h1>
                <p class="text-xl text-gray-400 mb-10 max-w-2xl mx-auto">
                    Enterprise-grade IPTV middleware solution. Manage channels, subscriptions, and deliver crystal-clear streaming to thousands of concurrent users.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-purple-600 hover:bg-purple-700 text-white px-8 py-4 rounded-xl text-lg font-semibold transition transform hover:scale-105 shadow-lg shadow-purple-500/25">
                        Start Free Trial
                    </a>
                    <a href="#features" class="border border-white/20 hover:border-white/40 text-white px-8 py-4 rounded-xl text-lg font-semibold transition hover:bg-white/5">
                        Explore Features
                    </a>
                </div>
                <div class="mt-16 flex items-center justify-center gap-12 text-gray-500">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">10K+</div>
                        <div class="text-sm">Live Channels</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">50K+</div>
                        <div class="text-sm">VOD Titles</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">99.9%</div>
                        <div class="text-sm">Uptime SLA</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-white">24/7</div>
                        <div class="text-sm">Support</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-20 relative">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold mb-4">Powerful Features</h2>
                    <p class="text-gray-400 text-lg max-w-2xl mx-auto">
                        Everything you need to build and scale your IPTV business
                    </p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:bg-white/10 transition group">
                        <div class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-purple-600/30 transition">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Live Streaming</h3>
                        <p class="text-gray-400">Multi-protocol support with HLS, MPEG-TS, RTSP, and HTTP Live Streaming for maximum compatibility.</p>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:bg-white/10 transition group">
                        <div class="w-12 h-12 bg-blue-600/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600/30 transition">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">VOD Library</h3>
                        <p class="text-gray-400">Complete video-on-demand management with categories, metadata, and automatic transcoding support.</p>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:bg-white/10 transition group">
                        <div class="w-12 h-12 bg-green-600/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-green-600/30 transition">
                            <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">DRM Protection</h3>
                        <p class="text-gray-400">Enterprise-grade content protection with DRM support, encryption, and secure token authentication.</p>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:bg-white/10 transition group">
                        <div class="w-12 h-12 bg-yellow-600/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-yellow-600/30 transition">
                            <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Multi-User Management</h3>
                        <p class="text-gray-400">Role-based access control with admin, reseller, and subscriber roles with granular permissions.</p>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:bg-white/10 transition group">
                        <div class="w-12 h-12 bg-red-600/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-red-600/30 transition">
                            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Analytics Dashboard</h3>
                        <p class="text-gray-400">Real-time analytics with viewer stats, bandwidth monitoring, and revenue tracking reports.</p>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:bg-white/10 transition group">
                        <div class="w-12 h-12 bg-pink-600/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-pink-600/30 transition">
                            <svg class="w-6 h-6 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Payment Gateway</h3>
                        <p class="text-gray-400">Integrated payments with Stripe, PayPal, Bitcoin, and regional payment methods for global reach.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-20 relative bg-white/5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold mb-4">Choose Your Plan</h2>
                    <p class="text-gray-400 text-lg">Flexible pricing for every viewer</p>
                </div>
                <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                    <!-- Basic -->
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:border-purple-500/50 transition">
                        <div class="text-center mb-8">
                            <h3 class="text-xl font-semibold mb-2">Basic</h3>
                            <div class="text-4xl font-bold mb-2">$9.99<span class="text-lg text-gray-500">/mo</span></div>
                            <p class="text-gray-500 text-sm">Perfect for casual viewers</p>
                        </div>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                5,000+ Live Channels
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                10,000+ VOD Titles
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                HD Quality (1080p)
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                1 Connection
                            </li>
                            <li class="flex items-center text-gray-500">
                                <svg class="w-5 h-5 text-gray-600 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                Catch-up TV
                            </li>
                        </ul>
                        <button class="w-full bg-white/10 hover:bg-white/20 text-white py-3 rounded-xl font-medium transition">
                            Select Basic
                        </button>
                    </div>

                    <!-- Premium -->
                    <div class="bg-gradient-to-b from-purple-900/30 to-dark border border-purple-500/50 rounded-2xl p-8 relative transform scale-105">
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2 bg-purple-600 text-white px-4 py-1 rounded-full text-sm font-medium">
                            Most Popular
                        </div>
                        <div class="text-center mb-8">
                            <h3 class="text-xl font-semibold mb-2">Premium</h3>
                            <div class="text-4xl font-bold mb-2">$19.99<span class="text-lg text-gray-500">/mo</span></div>
                            <p class="text-gray-500 text-sm">Best value for families</p>
                        </div>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                10,000+ Live Channels
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                50,000+ VOD Titles
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                4K Ultra HD
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                3 Connections
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                7-Day Catch-up
                            </li>
                        </ul>
                        <button class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-medium transition">
                            Select Premium
                        </button>
                    </div>

                    <!-- VIP -->
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-8 hover:border-yellow-500/50 transition">
                        <div class="text-center mb-8">
                            <h3 class="text-xl font-semibold mb-2">VIP</h3>
                            <div class="text-4xl font-bold mb-2">$39.99<span class="text-lg text-gray-500">/mo</span></div>
                            <p class="text-gray-500 text-sm">Ultimate experience</p>
                        </div>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                15,000+ Live Channels
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                100,000+ VOD Titles
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                4K HDR + Dolby
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                5 Connections
                            </li>
                            <li class="flex items-center text-gray-300">
                                <svg class="w-5 h-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                30-Day Catch-up
                            </li>
                        </ul>
                        <button class="w-full bg-white/10 hover:bg-white/20 text-white py-3 rounded-xl font-medium transition">
                            Select VIP
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 border-t border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-500">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'IPTV Middleware') }}. All rights reserved.</p>
            </div>
        </footer>
    </div>
</body>
</html>
