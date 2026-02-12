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
        Schema::table('users', function (Blueprint $table) {
            // Drop old specific columns
            $table->dropColumn(['daily_usage_youtube', 'daily_usage_zillow']);
            
            // Add new generic columns
            $table->integer('daily_usage')->default(0);
            $table->string('service_type')->default('youtube'); // Default needed for existing rows, or make nullable
            
            // Update Unique Constraint
            $table->dropUnique(['email']);
            $table->unique(['email', 'service_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
