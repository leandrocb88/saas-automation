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
            $table->string('pdf_path')->nullable()->after('duration');
            $table->string('audio_path')->nullable()->after('pdf_path');
            $table->enum('pdf_status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->after('audio_path');
            $table->enum('audio_status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->after('pdf_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'audio_path', 'pdf_status', 'audio_status']);
        });
    }
};
