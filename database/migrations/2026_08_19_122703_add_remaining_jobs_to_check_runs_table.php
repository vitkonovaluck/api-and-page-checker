<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('check_runs') || Schema::hasColumn('check_runs', 'remaining_jobs')) {
            return;
        }

        Schema::table('check_runs', function (Blueprint $table): void {
            $table->unsignedInteger('remaining_jobs')->default(0)->after('started_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('check_runs') || ! Schema::hasColumn('check_runs', 'remaining_jobs')) {
            return;
        }

        Schema::table('check_runs', function (Blueprint $table): void {
            $table->dropColumn('remaining_jobs');
        });
    }
};
