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
        Schema::create('assistant_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Mã loại (ví dụ: qa_based_document)');
            $table->string('name', 255)->comment('Tên hiển thị');
            $table->text('description')->nullable()->comment('Mô tả chi tiết');
            $table->boolean('is_active')->default(true)->comment('Trạng thái hoạt động');
            $table->string('icon', 100)->nullable()->comment('Icon class hoặc emoji');
            $table->string('color', 7)->nullable()->comment('Màu sắc (hex code)');
            $table->integer('sort_order')->default(0)->comment('Thứ tự sắp xếp');
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_types');
    }
};


