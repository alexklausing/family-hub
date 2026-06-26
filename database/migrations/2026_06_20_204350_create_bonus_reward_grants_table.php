<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_reward_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_reward_id')->constrained()->onDelete('cascade');
            $table->string('profile')->index();
            $table->date('week_start_date');
            $table->timestamps();

            // Prevent duplicate grants in the same week
            $table->unique(['bonus_reward_id', 'profile', 'week_start_date'], 'bonus_grant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_reward_grants');
    }
};
