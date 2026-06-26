<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('profile')->index();
            $table->foreignId('chore_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('label_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('required_days'); // [0, 1, 2, 3, 4] for Sunday-Thursday
            $table->string('reward_value'); // "15 mins stay up late" or "$5.00"
            $table->integer('expires_in_days')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_rewards');
    }
};
