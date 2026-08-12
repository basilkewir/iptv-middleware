<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\VOD;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $featuredChannels = Channel::where('is_active', true)
            ->where('is_free', true)
            ->limit(12)
            ->get();

        $featuredVods = VOD::where('is_active', true)
            ->where('is_featured', true)
            ->limit(8)
            ->get();

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('price')
            ->get();

        return view('public.home', compact('featuredChannels', 'featuredVods', 'plans'));
    }

    public function about()
    {
        return view('public.about');
    }

    public function contact()
    {
        return view('public.contact');
    }

    public function faq()
    {
        return view('public.faq');
    }

    public function terms()
    {
        return view('public.terms');
    }

    public function privacy()
    {
        return view('public.privacy');
    }
}
