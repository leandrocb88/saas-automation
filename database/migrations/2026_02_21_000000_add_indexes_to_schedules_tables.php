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
        Schema::table('digest_schedules', function (Blueprint $table) {
            $table->index('preferred_time');
            $table->index('is_active');
        });

        Schema::table('digests', function (Blueprint $table) {
            $table->index('scheduled_at');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digest_schedules', function (Blueprint $table) {
            $table->dropIndex(['preferred_time']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('digests', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropIndex(['is_active']);
        });
    }
};
