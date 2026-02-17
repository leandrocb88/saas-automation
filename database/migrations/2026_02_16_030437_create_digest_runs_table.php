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
        Schema::create('digest_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('digest_id')->nullable()->constrained()->onDelete('set null');
            $table->string('batch_id')->index(); // Corresponds to videos.share_token
            $table->integer('summary_count')->default(0);
            $table->integer('total_duration')->default(0); // In seconds
            $table->string('pdf_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digest_runs');
    }
};
