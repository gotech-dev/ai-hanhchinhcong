<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Thay đổi assistant_type từ enum sang string để dễ mở rộng
     * Thêm các loại trợ lý mới cho hành chính công Việt Nam
     */
    public function up(): void
    {
        // Thay đổi enum thành string để dễ mở rộng
        // MySQL không hỗ trợ thay đổi trực tiếp enum, cần làm theo cách này
        Schema::table('ai_assistants', function (Blueprint $table) {
            // Tạo cột tạm
            $table->string('assistant_type_new', 50)->nullable()->after('description');
        });
        
        // Copy dữ liệu từ cột cũ sang cột mới
        DB::statement('UPDATE ai_assistants SET assistant_type_new = CAST(assistant_type AS CHAR)');
        
        // Xóa cột cũ
        Schema::table('ai_assistants', function (Blueprint $table) {
            $table->dropColumn('assistant_type');
        });
        
        // Đổi tên cột mới thành assistant_type
        DB::statement('ALTER TABLE ai_assistants CHANGE assistant_type_new assistant_type VARCHAR(50) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Khôi phục lại enum (chỉ với 2 giá trị ban đầu)
        Schema::table('ai_assistants', function (Blueprint $table) {
            $table->enum('assistant_type_old', ['report_generator', 'qa_based_document'])->nullable()->after('description');
        });
        
        // Copy dữ liệu (chỉ giữ lại 2 loại cũ, set NULL cho loại mới)
        DB::statement("UPDATE ai_assistants SET assistant_type_old = assistant_type WHERE assistant_type IN ('report_generator', 'qa_based_document')");
        
        // Xóa cột cũ
        Schema::table('ai_assistants', function (Blueprint $table) {
            $table->dropColumn('assistant_type');
        });
        
        // Đổi tên cột mới thành assistant_type
        DB::statement("ALTER TABLE ai_assistants CHANGE assistant_type_old assistant_type ENUM('report_generator', 'qa_based_document') NOT NULL");
    }
};
