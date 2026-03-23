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
        Schema::table('digests', function (Blueprint $table) {
            $table->text('global_summary_prompt')->nullable()->after('custom_prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digests', function (Blueprint $table) {
            $table->dropColumn('global_summary_prompt');
        });
    }
};
