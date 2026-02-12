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
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('channel_id')->nullable()->constrained()->onDelete('set null')->after('user_id');
            $table->date('digest_date')->nullable()->after('channel_id');
            $table->string('source')->default('manual')->after('digest_date'); // 'manual', 'digest'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
            $table->dropColumn(['channel_id', 'digest_date', 'source']);
        });
    }
};
