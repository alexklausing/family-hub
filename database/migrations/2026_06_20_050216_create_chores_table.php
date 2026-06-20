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
        Schema::create('chores', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('profile'); // e.g. 'Family', 'Alex', 'Sarah', 'Emily', 'Henry'
            $table->string('time')->nullable(); // e.g. '15:00'
            $table->json('days')->nullable(); // array of integers 0-6 (Sun-Sat)
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chores');
    }
};
