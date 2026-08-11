<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->timestamp('started_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'source']);
        });

        Schema::table('snapshots', function (Blueprint $table) {
            $table->foreignId('check_run_id')
                ->nullable()
                ->after('address_id')
                ->constrained('check_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_run_id');
        });

        Schema::dropIfExists('check_runs');
    }
};
