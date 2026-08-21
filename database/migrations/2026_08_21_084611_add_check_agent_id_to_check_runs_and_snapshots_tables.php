<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_runs', function (Blueprint $table): void {
            $table->foreignId('check_agent_id')
                ->nullable()
                ->after('site_id')
                ->constrained('check_agents')
                ->nullOnDelete();
        });

        Schema::table('snapshots', function (Blueprint $table): void {
            $table->foreignId('check_agent_id')
                ->nullable()
                ->after('check_run_id')
                ->constrained('check_agents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('check_agent_id');
        });

        Schema::table('check_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('check_agent_id');
        });
    }
};
