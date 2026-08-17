<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sites', 'requests_per_minute')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table): void {
            $table->unsignedSmallInteger('requests_per_minute')->nullable()->after('schedule_last_run_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sites', 'requests_per_minute')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('requests_per_minute');
        });
    }
};
