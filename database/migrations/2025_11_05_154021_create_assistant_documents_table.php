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
        Schema::create('assistant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->integer('page_count')->nullable();
            $table->string('file_type')->nullable()->comment('pdf, docx, etc.');
            $table->bigInteger('file_size')->nullable()->comment('Size in bytes');
            $table->json('chunks')->nullable()->comment('Chunks metadata với embeddings info');
            $table->boolean('is_indexed')->default(false)->comment('Đã được index vào vector DB chưa');
            $table->timestamps();
            
            $table->index('ai_assistant_id');
            $table->index('is_indexed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_documents');
    }
};
