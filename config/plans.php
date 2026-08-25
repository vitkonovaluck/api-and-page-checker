<?php

declare(strict_types=1);

$freeMaxSites = 3;
$freeMaxAddressesPerSite = 20;

return [
    'default_name' => 'Free',
    'default_slug' => 'free',
    'default_max_sites' => $freeMaxSites,
    'default_max_addresses_per_site' => $freeMaxAddressesPerSite,
    'featured_slug' => 'pro',
    'catalog' => [
        [
            'name' => 'Free',
            'slug' => 'free',
            'max_sites' => $freeMaxSites,
            'max_addresses_per_site' => $freeMaxAddressesPerSite,
            'max_addresses_total' => null,
            'price_monthly' => 0,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 10,
        ],
        [
            'name' => 'Starter',
            'slug' => 'starter',
            'max_sites' => 10,
            'max_addresses_per_site' => 50,
            'max_addresses_total' => null,
            'price_monthly' => 199,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 20,
        ],
        [
            'name' => 'Pro',
            'slug' => 'pro',
            'max_sites' => 25,
            'max_addresses_per_site' => 100,
            'max_addresses_total' => null,
            'price_monthly' => 499,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 30,
        ],
        [
            'name' => 'Business',
            'slug' => 'business',
            'max_sites' => 50,
            'max_addresses_per_site' => 250,
            'max_addresses_total' => null,
            'price_monthly' => 999,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 40,
        ],
        [
            'name' => 'Agency',
            'slug' => 'agency',
            'max_sites' => null,
            'max_addresses_per_site' => null,
            'max_addresses_total' => 10000,
            'price_monthly' => 1999,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 50,
        ],
    ],
];
