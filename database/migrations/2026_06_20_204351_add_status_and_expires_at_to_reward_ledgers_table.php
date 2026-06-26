<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_ledgers', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('source'); // pending, approved, rejected
            $table->timestamp('expires_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reward_ledgers', function (Blueprint $table) {
            $table->dropColumn(['status', 'expires_at']);
        });
    }
};
