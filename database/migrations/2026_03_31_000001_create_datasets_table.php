<?php
// /Users/claudia/Sites/saas-automation/database/migrations/2026_03_31_000001_create_datasets_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('channel_url');
            $table->string('scheduled_time')->default('00:00');
            $table->string('timezone')->default('UTC');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('status')->default('idle'); // idle, syncing, error
            $table->boolean('is_paused')->default(false);
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
