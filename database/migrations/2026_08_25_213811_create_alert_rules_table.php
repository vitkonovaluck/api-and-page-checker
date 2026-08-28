<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained('notification_channels')->cascadeOnDelete();
            $table->json('events');
            $table->unsignedSmallInteger('min_consecutive')->default(1);
            $table->unsignedInteger('cooldown_minutes')->default(0);
            $table->boolean('notify_on_manual')->default(false);
            $table->boolean('digest_value_changes')->default(false);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'address_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
