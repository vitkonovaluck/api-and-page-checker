<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->timestamp('ssl_expires_at')->nullable()->after('requests_per_minute');
            $table->timestamp('ssl_checked_at')->nullable()->after('ssl_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['ssl_expires_at', 'ssl_checked_at']);
        });
    }
};
