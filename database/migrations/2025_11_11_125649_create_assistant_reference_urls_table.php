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
        Schema::create('assistant_reference_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->string('url', 500);
            $table->string('title')->nullable()->comment('Tiêu đề trang web (tự động crawl)');
            $table->text('description')->nullable()->comment('Mô tả ngắn (tự động crawl)');
            $table->enum('status', ['pending', 'crawling', 'indexed', 'failed'])->default('pending');
            $table->text('crawled_content')->nullable()->comment('Nội dung đã crawl (có thể lưu tạm)');
            $table->integer('content_length')->nullable()->comment('Độ dài nội dung (bytes)');
            $table->timestamp('last_crawled_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('ai_assistant_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_reference_urls');
    }
};
