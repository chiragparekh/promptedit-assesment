<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->unsignedBigInteger('estimated_provider_cost_nano')->nullable()->after('reasoning_tokens_rate_cents');
            $table->unsignedBigInteger('estimated_price_nano')->nullable()->after('estimated_provider_cost_nano');
            $table->unsignedBigInteger('actual_provider_cost_nano')->nullable()->after('estimated_price_nano');
            $table->unsignedBigInteger('actual_price_nano')->nullable()->after('actual_provider_cost_nano');
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_provider_cost_nano',
                'estimated_price_nano',
                'actual_provider_cost_nano',
                'actual_price_nano',
            ]);
        });
    }
};
