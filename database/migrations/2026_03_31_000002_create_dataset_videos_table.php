<?php
// /Users/claudia/Sites/saas-automation/database/migrations/2026_03_31_000002_create_dataset_videos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['dataset_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_videos');
    }
};
