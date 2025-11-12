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
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_document_id')->constrained('assistant_documents')->onDelete('cascade');
            $table->integer('chunk_index')->comment('Thứ tự chunk trong document');
            $table->text('content')->comment('Nội dung chunk');
            $table->json('embedding')->nullable()->comment('Embedding vector (JSON array) - cho MySQL/PostgreSQL');
            $table->json('metadata')->nullable()->comment('Metadata: page number, section, etc.');
            $table->timestamps();
            
            $table->index(['assistant_document_id', 'chunk_index']);
            // Note: Nếu dùng PostgreSQL với pgvector, có thể dùng column type 'vector' thay vì JSON
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
