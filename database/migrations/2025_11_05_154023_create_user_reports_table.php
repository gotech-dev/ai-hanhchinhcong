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
        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->onDelete('cascade');
            $table->text('report_content')->nullable()->comment('Nội dung báo cáo');
            $table->string('report_file_path', 500)->nullable()->comment('Đường dẫn file báo cáo đã generate');
            $table->string('file_format')->nullable()->comment('pdf, docx, etc.');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('chat_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_reports');
    }
};
