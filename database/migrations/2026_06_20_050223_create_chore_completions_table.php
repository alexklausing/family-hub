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
        Schema::create('chore_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chore_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->timestamps();

            $table->unique(['chore_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chore_completions');
    }
};
