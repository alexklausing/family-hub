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
        Schema::table('chores', function (Blueprint $table) {
            $table->string('reward')->nullable();
            $table->foreignId('label_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chores', function (Blueprint $table) {
            $table->dropForeign(['label_id']);
            $table->dropColumn(['reward', 'label_id']);
        });
    }
};
