<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digests', function (Blueprint $table) {
            // 'videos', 'shorts', 'streams' — stored as JSON array
            $table->json('video_types')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('digests', function (Blueprint $table) {
            $table->dropColumn('video_types');
        });
    }
};
