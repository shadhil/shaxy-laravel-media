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
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // Storage location
            $table->string('disk', 50)->default('public');
            $table->string('path');                          // primary path (original for images)
            $table->string('directory');
            $table->string('filename');

            // File metadata
            $table->string('mime_type', 100);
            $table->string('type', 20)->index();             // image | document | audio | video | other
            $table->unsignedBigInteger('size');              // bytes
            $table->string('context', 100)->nullable();      // 'organizations/logos', 'editor', etc.
            $table->string('original_filename')->nullable();  // client filename at upload time

            // Image-specific: variant paths keyed by name
            $table->json('variants')->nullable();

            // Which model owns this file (nullable — editor images have no model)
            $table->nullableMorphs('mediable');              // mediable_type + mediable_id + index
            $table->string('model_column')->nullable();      // which column on the model, e.g. 'logo'

            // Who uploaded it
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Query indexes
            $table->index(['disk', 'type']);
            $table->index('context');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
