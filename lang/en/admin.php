<?php

declare(strict_types=1);

return [
    'plans' => [
        'navigation' => 'Plans',
        'model' => 'Plan',
        'plural' => 'Plans',
        'description' => 'Site and address limits assigned to users.',
        'name' => 'Name',
        'slug' => 'Slug',
        'slug_help' => 'Lowercase, hyphenated identifier. Leave empty to generate from the name.',
        'max_sites' => 'Max sites',
        'max_addresses' => 'Max addresses per site',
        'max_addresses_total' => 'Max addresses in total',
        'max_addresses_total_help' => 'Shared across all sites. Leave empty for no total cap.',
        'unlimited' => 'Unlimited',
        'unlimited_help' => 'Leave empty for unlimited.',
        'price_monthly' => 'Monthly price',
        'price_monthly_help' => 'Whole currency units per month. Zero means the plan is free.',
        'sort_order' => 'Sort order',
        'is_default' => 'Default plan',
        'is_default_help' => 'Assigned automatically to new registrations. Only one plan can be default.',
        'is_active' => 'Active',
        'users_count' => 'Users',
    ],
    'users' => [
        'navigation' => 'Users',
        'model' => 'User',
        'plural' => 'Users',
        'description' => 'Registered accounts, roles, and assigned plans.',
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'password_help' => 'Leave empty to keep the current password.',
        'role' => 'Role',
        'color_scheme' => 'Color scheme',
        'plan' => 'Plan',
        'social_providers' => 'Linked social accounts',
        'none' => 'None',
        'created_at' => 'Created',
    ],
    'roles' => [
        'user' => 'User',
        'admin' => 'Admin',
    ],
    'open_app' => 'Open app',
];
