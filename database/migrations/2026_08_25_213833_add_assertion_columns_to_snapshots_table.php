<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->boolean('assertion_failed')->default(false)->after('error_message');
            $table->json('assertion_results')->nullable()->after('assertion_failed');
            $table->string('check_outcome', 32)->nullable()->after('assertion_results');
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropColumn(['assertion_failed', 'assertion_results', 'check_outcome']);
        });
    }
};
