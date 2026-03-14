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
        Schema::create('file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->string('share_token')->unique();
            $table->string('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_downloads')->nullable();
            $table->integer('download_count')->default(0);
            $table->json('shared_with')->nullable();
            $table->timestamps();
            
            $table->index('share_token');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_shares');
    }
};