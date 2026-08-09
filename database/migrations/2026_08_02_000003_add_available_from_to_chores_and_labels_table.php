<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an "available from" (HH:MM) unlock time. Chores stay hidden on the
     * daily board until this time of day. Can be set per-chore and per-label.
     */
    public function up(): void
    {
        Schema::table('chores', function (Blueprint $table) {
            $table->string('available_from')->nullable()->after('time');
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->string('available_from')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chores', function (Blueprint $table) {
            $table->dropColumn('available_from');
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn('available_from');
        });
    }
};
