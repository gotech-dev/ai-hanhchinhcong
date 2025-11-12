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
     * Chuyển tất cả assistants có assistant_type = 'report_generator' → 'document_drafting'
     * Chuyển template_file_path thành document_templates records
     */
    public function up(): void
    {
        // 1. Get all report_generator assistants
        $reportGenerators = DB::table('ai_assistants')
            ->where('assistant_type', 'report_generator')
            ->whereNotNull('template_file_path')
            ->get();
        
        foreach ($reportGenerators as $assistant) {
            try {
                // 2. Create document_template record from template_file_path
                $templatePath = $assistant->template_file_path;
                $fileName = basename($templatePath);
                
                // Detect document type from file name or default to 'bao_cao'
                $documentType = $this->detectDocumentTypeFromFileName($fileName);
                
                // Create document template
                $templateId = DB::table('document_templates')->insertGetId([
                    'ai_assistant_id' => $assistant->id,
                    'document_type' => $documentType,
                    'template_subtype' => null,
                    'name' => $this->generateTemplateName($documentType, null),
                    'file_name' => $fileName,
                    'file_path' => $templatePath,
                    'file_type' => pathinfo($fileName, PATHINFO_EXTENSION),
                    'file_size' => null,
                    'metadata' => json_encode(['migrated_from_report_generator' => true]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // 3. Update assistant type to document_drafting
                DB::table('ai_assistants')
                    ->where('id', $assistant->id)
                    ->update([
                        'assistant_type' => 'document_drafting',
                        'updated_at' => now(),
                    ]);
                
                \Illuminate\Support\Facades\Log::info('Migrated report_generator to document_drafting', [
                    'assistant_id' => $assistant->id,
                    'template_id' => $templateId,
                    'document_type' => $documentType,
                ]);
                
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to migrate report_generator', [
                    'assistant_id' => $assistant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // 4. Update assistants without template_file_path (just change type)
        DB::table('ai_assistants')
            ->where('assistant_type', 'report_generator')
            ->whereNull('template_file_path')
            ->update([
                'assistant_type' => 'document_drafting',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This is a one-way migration. Reverting would require complex logic.
        // For safety, we'll just log a warning.
        \Illuminate\Support\Facades\Log::warning('Migration migrate_report_generator_to_document_drafting cannot be fully reverted');
    }
    
    /**
     * Detect document type from file name
     */
    protected function detectDocumentTypeFromFileName(string $fileName): string
    {
        $fileName = strtolower($fileName);
        
        if (strpos($fileName, 'quyet_dinh') !== false || strpos($fileName, 'quyet-dinh') !== false) {
            return 'quyet_dinh';
        }
        if (strpos($fileName, 'cong_van') !== false || strpos($fileName, 'cong-van') !== false) {
            return 'cong_van';
        }
        if (strpos($fileName, 'to_trinh') !== false || strpos($fileName, 'to-trinh') !== false) {
            return 'to_trinh';
        }
        if (strpos($fileName, 'bien_ban') !== false || strpos($fileName, 'bien-ban') !== false) {
            return 'bien_ban';
        }
        if (strpos($fileName, 'thong_bao') !== false || strpos($fileName, 'thong-bao') !== false) {
            return 'thong_bao';
        }
        if (strpos($fileName, 'nghi_quyet') !== false || strpos($fileName, 'nghi-quyet') !== false) {
            return 'nghi_quyet';
        }
        
        // Default to bao_cao (report)
        return 'bao_cao';
    }
    
    /**
     * Generate template name
     */
    protected function generateTemplateName(string $documentType, ?string $subtype): string
    {
        $typeNames = [
            'quyet_dinh' => 'Quyết định',
            'cong_van' => 'Công văn',
            'to_trinh' => 'Tờ trình',
            'bao_cao' => 'Báo cáo',
            'bien_ban' => 'Biên bản',
            'thong_bao' => 'Thông báo',
            'nghi_quyet' => 'Nghị quyết',
        ];
        
        return $typeNames[$documentType] ?? ucfirst(str_replace('_', ' ', $documentType));
    }
};
