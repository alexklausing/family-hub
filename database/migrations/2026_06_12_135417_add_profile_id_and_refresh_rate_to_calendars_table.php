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
        Schema::table('tabs', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->constrained()->onDelete('cascade');
        });

        Schema::table('calendars', function (Blueprint $table) {
            $table->integer('refresh_rate')->default(15)->comment('Refresh rate in minutes');
            $table->timestamp('last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tabs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });

        Schema::table('calendars', function (Blueprint $table) {
            $table->dropColumn(['refresh_rate', 'last_synced_at']);
        });
    }
};
