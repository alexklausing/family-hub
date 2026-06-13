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
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('recipe_uuid')->nullable();
            $table->string('name');
            $table->string('ingredient')->nullable();
            $table->string('quantity')->nullable();
            $table->string('aisle')->nullable();
            $table->boolean('purchased')->default(false);
            $table->integer('order_flag')->default(0);
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
