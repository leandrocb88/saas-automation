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
        Schema::create('digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Morning Tech"
            $table->string('frequency')->default('daily'); // daily, weekly
            $table->time('scheduled_at')->default('08:00');
            $table->string('day_of_week')->nullable(); // mon, tue, etc. (for weekly)
            $table->text('custom_prompt')->nullable();
            $table->string('mode')->default('channels'); // channels, search_term, mixed
            $table->string('search_term')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('digest_channels', function (Blueprint $table) {
            $table->foreignId('digest_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_id')->constrained()->onDelete('cascade');
            $table->primary(['digest_id', 'channel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digest_channels');
        Schema::dropIfExists('digests');
    }
};
