<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
            'maxSites' => (int) config('plans.default_max_sites'),
            'maxAddresses' => (int) config('plans.default_max_addresses_per_site'),
            'openAuth' => $openAuth,
        ]);
    }
}
