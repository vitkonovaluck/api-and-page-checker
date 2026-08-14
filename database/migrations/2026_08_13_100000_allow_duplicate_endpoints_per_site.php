<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL uses addresses_site_id_endpoint_unique as the FK index for site_id.
        // Add a dedicated index first, then drop the unique — otherwise error 1553.
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('site_id');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unique(['site_id', 'endpoint']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex(['site_id']);
        });
    }
};
