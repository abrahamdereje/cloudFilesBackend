<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_uid')->index(); // Firebase User ID
            $table->string('filename');
            $table->string('original_filename');
            $table->string('s3_path')->unique();
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // in bytes
            $table->string('extension', 20);
            $table->boolean('is_public')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for faster queries
            $table->index(['firebase_uid', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('files');
    }
};
