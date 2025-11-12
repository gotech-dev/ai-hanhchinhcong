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
        Schema::table('assistant_documents', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('is_indexed')->comment('pending, processing, indexed, error');
            $table->integer('chunks_count')->nullable()->after('status')->comment('Number of chunks created');
            $table->timestamp('indexed_at')->nullable()->after('chunks_count')->comment('When document was indexed');
            
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_documents', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'chunks_count', 'indexed_at']);
        });
    }
};
