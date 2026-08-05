<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('url', 766);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
