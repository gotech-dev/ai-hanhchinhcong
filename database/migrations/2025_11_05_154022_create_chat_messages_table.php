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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->onDelete('cascade');
            $table->enum('sender', ['user', 'assistant']);
            $table->text('content');
            $table->enum('message_type', ['text', 'document', 'form', 'preview'])->default('text');
            $table->json('metadata')->nullable()->comment('Thông tin bổ sung: file path, form data, etc.');
            $table->timestamp('created_at');
            
            $table->index(['chat_session_id', 'created_at']);
            $table->index('sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
