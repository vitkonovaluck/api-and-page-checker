<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_agents', function (Blueprint $table): void {
            $table->string('region', 64)->nullable()->after('hostname');
        });
    }

    public function down(): void
    {
        Schema::table('check_agents', function (Blueprint $table): void {
            $table->dropColumn('region');
        });
    }
};
