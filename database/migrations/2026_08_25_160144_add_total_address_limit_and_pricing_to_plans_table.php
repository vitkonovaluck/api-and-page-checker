<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_addresses_total')->nullable()->after('max_addresses_per_site');
            $table->unsignedInteger('price_monthly')->default(0)->after('max_addresses_total');
            $table->unsignedInteger('sort_order')->default(0)->after('price_monthly');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_sites')->nullable()->change();
            $table->unsignedInteger('max_addresses_per_site')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('max_sites')->nullable(false)->change();
            $table->unsignedInteger('max_addresses_per_site')->nullable(false)->change();
            $table->dropColumn([
                'max_addresses_total',
                'price_monthly',
                'sort_order',
            ]);
        });
    }
};
