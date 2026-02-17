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
        Schema::table('digest_runs', function (Blueprint $table) {
            $table->string('pdf_status')->default('pending')->after('pdf_path');
            $table->string('audio_status')->default('pending')->after('audio_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digest_runs', function (Blueprint $table) {
            //
        });
    }
};
