<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sites', 'chain_checks')) {
            return;
        }

        DB::table('sites')->where('chain_checks', true)->update([
            'schedule_enabled' => true,
            'schedule_interval' => 'after',
        ]);

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn('chain_checks');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sites', 'chain_checks')) {
            return;
        }

        Schema::table('sites', function (Blueprint $table): void {
            $table->boolean('chain_checks')->default(false)->after('requests_per_minute');
        });
    }
};
