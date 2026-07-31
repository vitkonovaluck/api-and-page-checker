<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('headers')->nullable();
            $table->longText('body')->nullable();
            $table->string('body_hash', 64)->nullable();
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['address_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
