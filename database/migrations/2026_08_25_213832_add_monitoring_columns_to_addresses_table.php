<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->json('ignore_json_paths')->nullable()->after('request_body');
            $table->json('ignore_headers')->nullable()->after('ignore_json_paths');
            $table->json('ignore_body_regex')->nullable()->after('ignore_headers');
            $table->json('watch_json_paths')->nullable()->after('ignore_body_regex');
            $table->json('assertions')->nullable()->after('watch_json_paths');
            $table->string('kind', 32)->default('http')->after('assertions');
            $table->unsignedInteger('step_order')->nullable()->after('kind');
            $table->string('extract_json_path')->nullable()->after('step_order');
            $table->string('extract_as')->nullable()->after('extract_json_path');
            $table->foreignId('check_agent_id')->nullable()->after('site_token_id')->constrained()->nullOnDelete();
            $table->foreignId('baseline_snapshot_id')->nullable()->after('check_agent_id')->constrained('snapshots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('baseline_snapshot_id');
            $table->dropConstrainedForeignId('check_agent_id');
            $table->dropColumn([
                'ignore_json_paths',
                'ignore_headers',
                'ignore_body_regex',
                'watch_json_paths',
                'assertions',
                'kind',
                'step_order',
                'extract_json_path',
                'extract_as',
            ]);
        });
    }
};
