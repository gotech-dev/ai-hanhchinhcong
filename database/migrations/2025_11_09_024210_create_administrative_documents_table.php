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
        Schema::create('administrative_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_id')->constrained('ai_assistants')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('Người tạo/xử lý');
            
            // Thông tin cơ bản
            $table->string('so_van_ban')->nullable()->comment('Số văn bản');
            $table->date('ngay_van_ban')->nullable()->comment('Ngày văn bản');
            $table->enum('loai_van_ban', ['van_ban_den', 'van_ban_di'])->comment('Văn bản đến hoặc văn bản đi');
            $table->string('document_type')->nullable()->comment('Loại văn bản: cong_van, quyet_dinh, to_trinh, bao_cao, etc.');
            
            // Nơi gửi/nhận
            $table->string('noi_gui')->nullable()->comment('Nơi gửi (cho văn bản đến)');
            $table->string('noi_nhan')->nullable()->comment('Nơi nhận (cho văn bản đi)');
            $table->text('trich_yeu')->nullable()->comment('Trích yếu nội dung');
            
            // Phân loại và xử lý
            $table->enum('muc_do', ['khan_cap', 'thuong', 'khong_khan'])->default('thuong')->comment('Mức độ khẩn cấp');
            $table->integer('thoi_han_xu_ly')->nullable()->comment('Thời hạn xử lý (số ngày)');
            $table->date('deadline')->nullable()->comment('Hạn xử lý');
            $table->foreignId('nguoi_xu_ly_id')->nullable()->constrained('users')->onDelete('set null')->comment('Người được phân công xử lý');
            $table->string('phong_ban_xu_ly')->nullable()->comment('Phòng ban xử lý');
            
            // Trạng thái
            $table->enum('trang_thai', ['moi', 'dang_xu_ly', 'da_xu_ly', 'qua_han', 'huy'])->default('moi')->comment('Trạng thái xử lý');
            
            // File
            $table->string('file_name')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_type')->nullable()->comment('pdf, docx, etc.');
            $table->bigInteger('file_size')->nullable()->comment('Size in bytes');
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Thông tin bổ sung: OCR text, classification, etc.');
            $table->json('classification')->nullable()->comment('Kết quả phân loại AI');
            
            // Lưu trữ
            $table->string('storage_path')->nullable()->comment('Đường dẫn lưu trữ: year/month/sender');
            $table->boolean('is_archived')->default(false)->comment('Đã lưu trữ');
            
            $table->timestamps();
            
            // Indexes
            $table->index('ai_assistant_id');
            $table->index('user_id');
            $table->index('loai_van_ban');
            $table->index('document_type');
            $table->index('muc_do');
            $table->index('trang_thai');
            $table->index('deadline');
            $table->index('ngay_van_ban');
            $table->index(['ai_assistant_id', 'loai_van_ban', 'trang_thai'], 'idx_assistant_loai_trang_thai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrative_documents');
    }
};
