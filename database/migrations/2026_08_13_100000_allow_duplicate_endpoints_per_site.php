<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
