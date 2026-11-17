<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_id', 30)->unique();
            $table->string('status', 20)->default('queued');
            $table->text('prompt');
            $table->string('size', 20)->default('1024x1024');
            $table->string('quality', 20)->default('medium');
            $table->json('reference_images')->nullable();
            $table->json('metadata')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('input_text_tokens_rate_cents')->nullable();
            $table->unsignedInteger('input_image_tokens_rate_cents')->nullable();
            $table->unsignedInteger('output_tokens_rate_cents')->nullable();
            $table->unsignedInteger('reasoning_tokens_rate_cents')->nullable();
            $table->json('error')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
