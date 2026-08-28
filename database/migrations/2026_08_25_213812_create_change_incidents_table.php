<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('address_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_snapshot_id')->constrained('snapshots')->cascadeOnDelete();
            $table->foreignId('closed_snapshot_id')->nullable()->constrained('snapshots')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['address_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_incidents');
    }
};
