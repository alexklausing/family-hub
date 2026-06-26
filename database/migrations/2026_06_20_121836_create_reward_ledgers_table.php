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
        Schema::create('reward_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('profile')->index();
            $table->string('type'); // 'monetary' or 'textual'
            $table->string('reward_text')->nullable(); // e.g. "Extra game time"
            $table->decimal('amount', 8, 2)->nullable(); // e.g. 5.00
            $table->string('source')->nullable(); // e.g. "chore_completion" or "redemption"
            $table->foreignId('chore_completion_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_ledgers');
    }
};
