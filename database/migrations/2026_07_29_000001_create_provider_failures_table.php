<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_failures', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->string('api_key_mask');
            $table->string('error_type');
            $table->text('error_message')->nullable();
            $table->timestamp('failed_at');
            $table->timestamps();

            $table->index(['provider', 'model', 'api_key_mask', 'failed_at'], 'pf_cooldown_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_failures');
    }
};
