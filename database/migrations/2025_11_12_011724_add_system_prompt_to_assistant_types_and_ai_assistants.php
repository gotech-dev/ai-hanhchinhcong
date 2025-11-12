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
        // Thêm system_prompt vào assistant_types
        Schema::table('assistant_types', function (Blueprint $table) {
            $table->text('system_prompt')->nullable()->after('description')
                ->comment('System prompt mặc định cho loại trợ lý này');
            $table->text('system_prompt_template')->nullable()->after('system_prompt')
                ->comment('Template prompt với placeholders {name}, {description}');
        });

        // Thêm system_prompt_override vào ai_assistants
        Schema::table('ai_assistants', function (Blueprint $table) {
            $table->text('system_prompt_override')->nullable()->after('config')
                ->comment('Override system prompt mặc định của loại');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_types', function (Blueprint $table) {
            $table->dropColumn(['system_prompt', 'system_prompt_template']);
        });

        Schema::table('ai_assistants', function (Blueprint $table) {
            $table->dropColumn('system_prompt_override');
        });
    }
};
