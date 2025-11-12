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
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->string('document_type', 50)->comment('Loại văn bản: cong_van, quyet_dinh, to_trinh, etc.');
            $table->string('template_subtype', 50)->nullable()->comment('Loại phụ: bo_nhiem, khen_thuong, di, den, etc.');
            $table->string('name')->comment('Tên template: Quyết định bổ nhiệm, Công văn đi, etc.');
            $table->string('file_name')->comment('Tên file gốc');
            $table->string('file_path', 500)->comment('Đường dẫn file template');
            $table->string('file_type', 10)->default('docx')->comment('Loại file: docx, pdf');
            $table->bigInteger('file_size')->nullable()->comment('Kích thước file (bytes)');
            $table->json('metadata')->nullable()->comment('Metadata: placeholders, structure, etc.');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('ai_assistant_id');
            $table->index(['document_type', 'template_subtype']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
