<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $openAuth = match (true) {
            $request->routeIs('login') => 'login',
            $request->routeIs('register') => 'register',
            default => null,
        };

        return view('landing', [
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'featuredSlug' => (string) config('plans.featured_slug'),
            'maxSites' => (int) config('plans.default_max_sites'),
            'openAuth' => $openAuth,
        ]);
    }
}
