<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('value');
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });

        Schema::table('addresses', function (Blueprint $table): void {
            $table->foreignId('site_token_id')
                ->nullable()
                ->constrained('site_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('site_token_id');
        });

        Schema::dropIfExists('site_tokens');
    }
};
