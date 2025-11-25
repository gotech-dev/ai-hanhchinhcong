<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\AiAssistant;
use App\Models\ChatSession;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentDraftingService
{
    public function __construct(
        protected ?DocumentFormatChecker $formatChecker = null,
        protected ?DocumentProcessor $documentProcessor = null,
        protected ?PdfTemplateProcessor $pdfTemplateProcessor = null
    ) {
        // Lazy load DocumentFormatChecker if exists
        if (class_exists('App\Services\DocumentFormatChecker')) {
            $this->formatChecker = app('App\Services\DocumentFormatChecker');
        }
        // Lazy load DocumentProcessor if not provided
        if (!$this->documentProcessor) {
            $this->documentProcessor = app(DocumentProcessor::class);
        }
        // ✅ MỚI: Lazy load PdfTemplateProcessor (chỉ dùng cho PDF templates)
        if (!$this->pdfTemplateProcessor) {
            $this->pdfTemplateProcessor = app(PdfTemplateProcessor::class);
        }
    }
    
    /**
     * Draft a document based on user request
     * 
     * @param string $userRequest User's request for document
     * @param DocumentType $documentType Type of document to draft
     * @param ChatSession $session Chat session
     * @param AiAssistant $assistant AI Assistant
     * @param array $collectedData Collected data from conversation
     * @return array{content: string, file_path: string|null, metadata: array}
     */
    public function draftDocument(
        string $userRequest,
        DocumentType $documentType,
        ChatSession $session,
        AiAssistant $assistant,
        array $collectedData = [],
        ?string $templateSubtype = null,
        ?int $templateId = null
    ): array {
        try {
            Log::info('Drafting document', [
                'document_type' => $documentType->value,
                'template_subtype' => $templateSubtype,
                'template_id' => $templateId,
                'session_id' => $session->id,
                'assistant_id' => $assistant->id,
            ]);
            
            // 1. Try to find template from database
            $template = $this->findTemplate($assistant, $documentType, $templateSubtype, $templateId);
            
            // ✅ LOG: Template finding
            Log::info('🔵 [DocumentDrafting] Template finding', [
                'assistant_id' => $assistant->id,
                'document_type' => $documentType->value,
                'template_subtype' => $templateSubtype,
                'template_found' => $template ? true : false,
                'template_id' => $template?->id,
                'template_name' => $template?->name,
                'template_file_path' => $template?->file_path,
            ]);
            
            // 2. Auto-fill basic information
            $autoFilledData = $this->autoFillBasicInfo($documentType, $assistant, $collectedData);
            
            // ✅ LOG: Auto-filled data
            Log::info('🔵 [DocumentDrafting] Auto-filled data', [
                'assistant_id' => $assistant->id,
                'auto_filled_fields' => array_keys($autoFilledData),
                'auto_filled_data_preview' => array_slice($autoFilledData, 0, 5, true),
            ]);
            
            // 3. Generate content using AI (if needed)
            $aiContent = [];
            if (empty($collectedData) || $this->needsAIContentGeneration($collectedData)) {
                // Mode: AI soạn thảo nội dung
                // ✅ LOG: Starting AI content generation
                Log::info('🔵 [DocumentDrafting] Starting AI content generation', [
                    'assistant_id' => $assistant->id,
                    'has_template' => $template ? true : false,
                    'template_id' => $template?->id,
                    'user_request' => substr($userRequest, 0, 200),
                ]);
                
                try {
                    // ✅ TRUYỀN template vào generateContentWithAI()
                    $aiContent = $this->generateContentWithAI(
                        $userRequest,
                        $documentType,
                        $collectedData,
                        $autoFilledData,
                        $template  // ✅ MỚI: Truyền template để AI biết về template structure
                    );
                    
                    // ✅ LOG: AI content generated
                    Log::info('🔵 [DocumentDrafting] AI content generated', [
                        'assistant_id' => $assistant->id,
                        'ai_content_fields' => array_keys($aiContent),
                        'ai_content_preview' => array_slice($aiContent, 0, 5, true),
                    ]);
                } catch (\Exception $e) {
                    // ⚠️ AI generation failed (timeout, API error, etc.)
                    // → Proceed with template-based generation using only auto-filled data
                    Log::warning('⚠️ [DocumentDrafting] AI content generation failed, proceeding with template-based generation', [
                        'assistant_id' => $assistant->id,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                    ]);
                    $aiContent = []; // Use empty AI content, rely on auto-filled data
                }
            } else {
                // Mode: User cung cấp data → AI chỉ điền (giống report_generator)
                Log::info('🔵 [DocumentDrafting] Using collected data (no AI generation)', [
                    'assistant_id' => $assistant->id,
                    'collected_data_fields' => array_keys($collectedData),
                ]);
                $aiContent = $collectedData;
            }
            
            // 4. Merge auto-filled data with AI content
            $documentData = array_merge($autoFilledData, $aiContent);
            
            // ✅ LOG: Merged document data
            Log::info('🔵 [DocumentDrafting] Merged document data', [
                'assistant_id' => $assistant->id,
                'total_fields' => count($documentData),
                'document_data_preview' => array_slice($documentData, 0, 10, true),
            ]);
            
            // 5. Check format compliance (if checker exists)
            $complianceResult = null;
            if ($this->formatChecker) {
                $complianceResult = $this->formatChecker->check($documentData, $documentType);
            }
            
            // 6. Generate DOCX file (use template if available, otherwise use code generation)
            Log::info('🔵 [DocumentDrafting] Generating DOCX file', [
                'assistant_id' => $assistant->id,
                'has_template' => $template ? true : false,
                'template_id' => $template?->id,
                'method' => $template ? 'generateDocxFromTemplate' : 'generateDocx',
            ]);
            
            if ($template) {
                $filePath = $this->generateDocxFromTemplate($template, $documentData, $session);
                
                // ✅ LOG: DOCX generated from template
                Log::info('✅ [DocumentDrafting] DOCX generated from template', [
                    'assistant_id' => $assistant->id,
                    'template_id' => $template->id,
                    'file_path' => $filePath,
                ]);
            } else {
                $filePath = $this->generateDocx($documentType, $documentData, $session);
                
                // ✅ LOG: DOCX generated from code
                Log::info('✅ [DocumentDrafting] DOCX generated from code (no template)', [
                    'assistant_id' => $assistant->id,
                    'file_path' => $filePath,
                ]);
            }
            
            // ✅ LOG: Final result
            Log::info('✅ [DocumentDrafting] Document drafted successfully', [
                'assistant_id' => $assistant->id,
                'session_id' => $session->id,
                'document_type' => $documentType->value,
                'template_used' => $template ? true : false,
                'template_id' => $template?->id,
                'file_path' => $filePath,
                'file_path_length' => strlen($filePath),
            ]);
            
            return [
                'content' => $this->formatContent($documentData, $documentType),
                'file_path' => $filePath,
                'metadata' => [
                    'document_type' => $documentType->value,
                    'document_type_display' => $documentType->displayName(),
                    'template_used' => $template ? true : false,
                    'template_id' => $template?->id,
                    'compliance_check' => $complianceResult,
                    'auto_filled_fields' => array_keys($autoFilledData),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to draft document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Find template from database
     */
    protected function findTemplate(AiAssistant $assistant, DocumentType $documentType, ?string $subtype = null, ?int $templateId = null): ?DocumentTemplate
    {
        // ✅ MỚI: Nếu có template_id, tìm trực tiếp
        if ($templateId) {
            $template = DocumentTemplate::where('id', $templateId)
                ->where('ai_assistant_id', $assistant->id)
                ->where('is_active', true)
                ->first();
                
            if ($template) {
                Log::info('✅ [DocumentDrafting] Template found by ID', [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                ]);
                return $template;
            }
            
            Log::warning('⚠️ [DocumentDrafting] Template ID provided but not found or inactive', [
                'template_id' => $templateId,
                'assistant_id' => $assistant->id,
            ]);
            // Fallback to normal search if ID not found
        }

        // ✅ DEBUG: Log all templates for this assistant
        $allTemplates = DocumentTemplate::where('ai_assistant_id', $assistant->id)
            ->where('is_active', true)
            ->get();
        
        Log::info('🔵 [DocumentDrafting] Finding template - All templates for assistant', [
            'assistant_id' => $assistant->id,
            'assistant_name' => $assistant->name,
            'document_type' => $documentType->value,
            'subtype' => $subtype,
            'all_templates_count' => $allTemplates->count(),
            'all_templates' => $allTemplates->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'document_type' => $t->document_type,
                'subtype' => $t->template_subtype,
                'is_active' => $t->is_active,
            ])->toArray(),
        ]);
        
        $query = DocumentTemplate::where('ai_assistant_id', $assistant->id)
            ->where('document_type', $documentType->value)
            ->where('is_active', true);
        
        if ($subtype) {
            $query->where('template_subtype', $subtype);
        }
        
        $template = $query->first();
        
        // If no template with subtype, try without subtype
        if (!$template && $subtype) {
            $template = DocumentTemplate::where('ai_assistant_id', $assistant->id)
                ->where('document_type', $documentType->value)
                ->whereNull('template_subtype')
                ->where('is_active', true)
                ->first();
        }
        
        // ✅ FIX: Fallback - Nếu không tìm thấy template match document_type, dùng template đầu tiên của assistant
        // (Useful khi assistant chỉ có 1 template nhưng AI detect sai document_type hoặc user message không rõ ràng)
        if (!$template && $allTemplates->count() > 0) {
            // ✅ Ưu tiên: Nếu assistant chỉ có 1 template, dùng template đó
            if ($allTemplates->count() === 1) {
                $template = $allTemplates->first();
                Log::info('⚠️ [DocumentDrafting] Assistant has only 1 template, using it regardless of detected document_type', [
                    'assistant_id' => $assistant->id,
                    'detected_document_type' => $documentType->value,
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'template_document_type' => $template->document_type,
                    'reason' => 'Single template assistant - using available template',
                ]);
            } else {
                // Nếu có nhiều templates, vẫn dùng template đầu tiên như fallback
                $template = $allTemplates->first();
                Log::info('⚠️ [DocumentDrafting] No exact template match, using first available template as fallback', [
                    'assistant_id' => $assistant->id,
                    'requested_document_type' => $documentType->value,
                    'fallback_template_id' => $template->id,
                    'fallback_template_name' => $template->name,
                    'fallback_template_document_type' => $template->document_type,
                ]);
            }
        }
        
        // ✅ DEBUG: Log template found
        if ($template) {
            Log::info('✅ [DocumentDrafting] Template found', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'template_file_path' => $template->file_path,
                'template_document_type' => $template->document_type,
                'requested_document_type' => $documentType->value,
                'subtype' => $template->template_subtype,
                'is_fallback' => $template->document_type !== $documentType->value,
            ]);
        } else {
            Log::warning('⚠️ [DocumentDrafting] No template found', [
                'assistant_id' => $assistant->id,
                'document_type' => $documentType->value,
                'subtype' => $subtype,
                'total_templates' => $allTemplates->count(),
            ]);
        }
        
        return $template;
    }
    
    /**
     * Check if needs AI content generation
     */
    protected function needsAIContentGeneration(array $collectedData): bool
    {
        // If collectedData has only basic fields (so_van_ban, ngay_thang, etc.), need AI generation
        $basicFields = ['so_van_ban', 'ngay_thang', 'nguoi_ky', 'chuc_vu', 'ten_co_quan'];
        $hasContentFields = false;
        
        foreach ($collectedData as $key => $value) {
            if (!in_array($key, $basicFields) && !empty($value)) {
                $hasContentFields = true;
                break;
            }
        }
        
        // If no content fields, need AI generation
        return !$hasContentFields;
    }
    
    /**
     * Generate DOCX from template file (using TemplateProcessor for DOCX, or code generation for PDF)
     */
    protected function generateDocxFromTemplate(DocumentTemplate $template, array $documentData, ChatSession $session): string
    {
        try {
            // Get template file path
            $templatePath = $this->getTemplatePath($template->file_path);
            
            if (!file_exists($templatePath)) {
                Log::warning('Template file not found, falling back to code generation', [
                    'template_id' => $template->id,
                    'file_path' => $templatePath,
                ]);
                // Fallback to code generation
                return $this->generateDocx(
                    \App\Enums\DocumentType::from($template->document_type),
                    $documentData,
                    $session
                );
            }
            
            // ✅ FIX: Check file_type from database (not just extension)
            $fileType = strtolower($template->file_type ?? '');
            $fileExtension = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
            
            // ✅ MỚI: PDF files - use PdfTemplateProcessor (service riêng, không ảnh hưởng DOCX)
            if ($fileType === 'pdf' || $fileExtension === 'pdf') {
                Log::info('📄 [DocumentDrafting] Template file is PDF, using PdfTemplateProcessor', [
                    'template_id' => $template->id,
                    'file_path' => $templatePath,
                    'file_type' => $fileType,
                    'file_extension' => $fileExtension,
                    'method' => 'PdfTemplateProcessor (PDF→DOCX conversion)',
                    'expected_format_preservation' => '95-98%',
                ]);
                
                try {
                    // ✅ MỚI: Dùng PdfTemplateProcessor service riêng
                    // Service này handle tất cả logic PDF (convert, extract, fill)
                    // KHÔNG ảnh hưởng đến logic DOCX ở dưới
                    return $this->pdfTemplateProcessor->generateDocxFromPdfTemplate(
                        $template,
                        $documentData,
                        $session
                    );
                } catch (\Exception $e) {
                    Log::error('🔴 [DocumentDrafting] PdfTemplateProcessor failed, falling back to code generation', [
                        'template_id' => $template->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Fallback to code generation if PDF processing fails
                    return $this->generateDocx(
                        \App\Enums\DocumentType::from($template->document_type),
                        $documentData,
                        $session
                    );
                }
            }
            
            // ✅ FIX: Check if file is .doc (old format) - TemplateProcessor only supports .docx
            if ($fileType === 'doc' || $fileExtension === 'doc') {
                Log::warning('⚠️ [DocumentDrafting] Template file is .doc format, TemplateProcessor only supports .docx. Falling back to code generation', [
                    'template_id' => $template->id,
                    'file_path' => $templatePath,
                    'file_type' => $fileType,
                    'file_extension' => $fileExtension,
                ]);
                // Fallback to code generation
                return $this->generateDocx(
                    \App\Enums\DocumentType::from($template->document_type),
                    $documentData,
                    $session
                );
            }
            
            // ✅ LOG: Using template file (must be DOCX at this point)
            Log::info('🔵 [DocumentDrafting] Using DOCX template file for DOCX generation', [
                'template_id' => $template->id,
                'template_path' => $templatePath,
                'file_type' => $fileType,
                'file_extension' => $fileExtension,
                'file_exists' => file_exists($templatePath),
                'file_size' => file_exists($templatePath) ? filesize($templatePath) : 0,
            ]);
            
            // Create TemplateProcessor (only for DOCX files)
            $templateProcessor = new TemplateProcessor($templatePath);
            
            // Get placeholders from template
            $placeholders = $template->metadata['placeholders'] ?? [];
            if (empty($placeholders)) {
                // Try to extract from template
                $placeholders = $templateProcessor->getVariables();
            }
            
            // ✅ LOG: Placeholders found
            Log::info('🔵 [DocumentDrafting] Placeholders extracted', [
                'template_id' => $template->id,
                'placeholders_count' => count($placeholders),
                'placeholders' => array_slice($placeholders, 0, 20), // First 20 for logging
            ]);
            
            // Map document data to placeholders
            $mappedData = $this->mapDataToPlaceholders($documentData, $placeholders);
            
            // ✅ LOG: Mapped data
            Log::info('🔵 [DocumentDrafting] Data mapped to placeholders', [
                'template_id' => $template->id,
                'mapped_count' => count($mappedData),
                'mapped_data' => array_slice($mappedData, 0, 20, true), // First 20 for logging
            ]);
            
            // Replace placeholders
            $replacedCount = 0;
            $failedCount = 0;
            foreach ($mappedData as $key => $value) {
                try {
                    $templateProcessor->setValue($key, $value);
                    $replacedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::warning('⚠️ [DocumentDrafting] Failed to replace placeholder', [
                        'template_id' => $template->id,
                        'placeholder' => $key,
                        'value' => mb_substr($value, 0, 50),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // ✅ LOG: Replacement summary
            Log::info('🔵 [DocumentDrafting] Placeholder replacement completed', [
                'template_id' => $template->id,
                'replaced_count' => $replacedCount,
                'failed_count' => $failedCount,
                'total_placeholders' => count($mappedData),
            ]);
            
            // Save file
            $fileName = $this->generateFileName(
                \App\Enums\DocumentType::from($template->document_type),
                $session
            );
            $filePath = storage_path("app/public/documents/{$fileName}");
            
            // Ensure directory exists
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            $templateProcessor->saveAs($filePath);
            
            // Return public URL
            return Storage::disk('public')->url("documents/{$fileName}");
            
        } catch (\Exception $e) {
            Log::error('Failed to generate DOCX from template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            // Fallback to code generation
            return $this->generateDocx(
                \App\Enums\DocumentType::from($template->document_type),
                $documentData,
                $session
            );
        }
    }
    
    /**
     * Get template file path from URL
     */
    protected function getTemplatePath(string $templateUrl): string
    {
        $parsedUrl = parse_url($templateUrl);
        $path = $parsedUrl['path'] ?? $templateUrl;
        $filePath = preg_replace('#^/storage/#', '', $path);
        $filePath = ltrim($filePath, '/');
        return Storage::disk('public')->path($filePath);
    }
    
    /**
     * Map document data to template placeholders
     */
    protected function mapDataToPlaceholders(array $documentData, array $placeholders): array
    {
        $mapped = [];
        
        foreach ($placeholders as $placeholder) {
            // Remove {{ }} or ${ } or [ ] from placeholder
            $cleanKey = preg_replace('/[\[\]{}${}]/', '', $placeholder);
            $cleanKey = trim($cleanKey);
            
            // Try to find matching data
            if (isset($documentData[$cleanKey])) {
                $mapped[$placeholder] = $documentData[$cleanKey];
            } elseif (isset($documentData[$placeholder])) {
                $mapped[$placeholder] = $documentData[$placeholder];
            } else {
                // Try case-insensitive match
                foreach ($documentData as $key => $value) {
                    if (strtolower($key) === strtolower($cleanKey)) {
                        $mapped[$placeholder] = $value;
                        break;
                    }
                }
            }
        }
        
        return $mapped;
    }
    
    /**
     * ✅ MỚI: Extract template structure từ template file
     * 
     * @param DocumentTemplate $template
     * @return array
     */
    protected function extractTemplateStructure(DocumentTemplate $template): array
    {
        try {
            // Lấy placeholders từ metadata nếu có
            $placeholders = $template->metadata['placeholders'] ?? [];
            
            // Nếu không có trong metadata, thử extract từ file (nếu là DOCX)
            if (empty($placeholders) && strtolower($template->file_type) === 'docx') {
                try {
                    $templatePath = $this->getTemplatePath($template->file_path);
                    if (file_exists($templatePath)) {
                        $templateProcessor = new TemplateProcessor($templatePath);
                        $placeholders = $templateProcessor->getVariables();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to extract placeholders from DOCX template', [
                        'template_id' => $template->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Build structure từ placeholders
            // Map placeholders thành structure format giống DocumentType::getTemplateStructure()
            $structure = [];
            
            // Common fields mapping
            $fieldMapping = [
                'so_van_ban' => 'so_van_ban',
                'so' => 'so_van_ban',
                'ngay_thang' => 'ngay_thang',
                'ngay' => 'ngay_thang',
                'nguoi_ky' => 'nguoi_ky',
                'chuc_vu' => 'chuc_vu',
                'noi_nhan' => 'noi_nhan',
                'noi_gui' => 'noi_gui',
                'mo_dau' => 'mo_dau',
                'noi_dung' => 'noi_dung',
                'ket' => 'ket',
                'can_cu' => 'can_cu',
                'quyet_dinh' => 'quyet_dinh',
                'ket_luan' => 'ket_luan',
                'kien_nghi' => 'kien_nghi',
            ];
            
            // Group placeholders into header, body, footer
            $headerFields = [];
            $bodyFields = [];
            $footerFields = [];
            
            foreach ($placeholders as $placeholder) {
                $cleanKey = preg_replace('/[\[\]{}${}]/', '', $placeholder);
                $cleanKey = trim(strtolower($cleanKey));
                
                // Map to standard field name
                $fieldName = $fieldMapping[$cleanKey] ?? $cleanKey;
                
                // Categorize fields
                if (in_array($fieldName, ['so_van_ban', 'ngay_thang', 'noi_nhan', 'noi_gui'])) {
                    $headerFields[] = $fieldName;
                } elseif (in_array($fieldName, ['nguoi_ky', 'chuc_vu'])) {
                    $footerFields[] = $fieldName;
                } else {
                    $bodyFields[] = $fieldName;
                }
            }
            
            if (!empty($headerFields)) {
                $structure['header'] = array_unique($headerFields);
            }
            if (!empty($bodyFields)) {
                $structure['body'] = array_unique($bodyFields);
            }
            if (!empty($footerFields)) {
                $structure['footer'] = array_unique($footerFields);
            }
            
            return $structure;
        } catch (\Exception $e) {
            Log::warning('Failed to extract template structure', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * ✅ MỚI: Extract template content từ template file
     * 
     * @param DocumentTemplate $template
     * @return string|null
     */
    protected function extractTemplateContent(DocumentTemplate $template): ?string
    {
        try {
            $templatePath = $this->getTemplatePath($template->file_path);
            
            if (!file_exists($templatePath)) {
                Log::warning('Template file not found', [
                    'template_id' => $template->id,
                    'file_path' => $templatePath,
                ]);
                return null;
            }
            
            // Extract text từ template file
            $text = $this->documentProcessor->extractText($templatePath);
            
            if (empty(trim($text))) {
                Log::warning('Template file is empty or could not extract text', [
                    'template_id' => $template->id,
                    'file_path' => $templatePath,
                ]);
                return null;
            }
            
            // Clean up text: remove excessive whitespace, normalize line breaks
            $text = preg_replace('/\s+/', ' ', $text); // Replace multiple spaces with single space
            $text = preg_replace('/\n\s*\n/', "\n\n", $text); // Normalize line breaks
            
            Log::info('Template content extracted successfully', [
                'template_id' => $template->id,
                'content_length' => strlen($text),
            ]);
            
            return $text;
        } catch (\Exception $e) {
            Log::warning('Failed to extract template content', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Auto-fill basic information (số văn bản, ngày tháng, nơi nhận, etc.)
     */
    protected function autoFillBasicInfo(
        DocumentType $documentType,
        AiAssistant $assistant,
        array $collectedData
    ): array {
        $data = [];
        
        // Generate số văn bản based on document type
        $data['so_van_ban'] = $this->generateDocumentNumber($documentType, $assistant);
        
        // Current date
        $data['ngay_thang'] = now()->format('d/m/Y');
        $data['ngay'] = now()->format('d');
        $data['thang'] = now()->format('m');
        $data['nam'] = now()->format('Y');
        
        // Get organization info from assistant config or collected data
        $orgName = $assistant->config['organization_name'] ?? $collectedData['organization_name'] ?? 'Cơ quan hành chính';
        $data['ten_co_quan'] = $orgName;
        
        // Get sender info
        $data['nguoi_ky'] = $collectedData['nguoi_ky'] ?? $assistant->config['default_signer'] ?? 'Giám đốc';
        $data['chuc_vu'] = $collectedData['chuc_vu'] ?? $assistant->config['default_position'] ?? 'Giám đốc';
        
        // Merge with collected data (user-provided data takes priority)
        return array_merge($data, $collectedData);
    }
    
    /**
     * Generate document number based on type
     */
    protected function generateDocumentNumber(DocumentType $documentType, AiAssistant $assistant): string
    {
        $prefix = match($documentType) {
            DocumentType::CONG_VAN => 'CV',
            DocumentType::QUYET_DINH => 'QĐ',
            DocumentType::TO_TRINH => 'TTr',
            DocumentType::BAO_CAO => 'BC',
            DocumentType::BIEN_BAN => 'BB',
            DocumentType::THONG_BAO => 'TB',
            DocumentType::NGHI_QUYET => 'NQ',
        };
        
        $orgCode = $assistant->config['organization_code'] ?? 'ABC';
        $year = now()->format('Y');
        $number = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        return "{$number}/{$prefix}-{$orgCode}";
    }
    
    /**
     * Generate content using AI
     * 
     * ✅ MỚI: Nhận template parameter để extract template content và structure
     */
    protected function generateContentWithAI(
        string $userRequest,
        DocumentType $documentType,
        array $collectedData,
        array $autoFilledData,
        ?DocumentTemplate $template = null  // ✅ MỚI: Thêm parameter
    ): array {
        // ✅ Nếu có template, extract structure và content từ template
        $templateStructure = null;
        $templateContent = null;
        
        if ($template) {
            try {
                // ✅ LOG: Extracting template info
                Log::info('🔵 [DocumentDrafting] Extracting template info', [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'template_file_path' => $template->file_path,
                    'template_file_type' => $template->file_type,
                ]);
                
                $templateStructure = $this->extractTemplateStructure($template);
                $templateContent = $this->extractTemplateContent($template);
                
                // ✅ LOG: Template info extracted
                Log::info('✅ [DocumentDrafting] Template info extracted', [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'has_structure' => !empty($templateStructure),
                    'structure_keys' => !empty($templateStructure) ? array_keys($templateStructure) : [],
                    'has_content' => !empty($templateContent),
                    'content_length' => $templateContent ? strlen($templateContent) : 0,
                    'content_preview' => $templateContent ? substr($templateContent, 0, 200) : null,
                ]);
            } catch (\Exception $e) {
                Log::warning('⚠️ [DocumentDrafting] Failed to extract template info, falling back to generic structure', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            Log::info('🔵 [DocumentDrafting] No template provided, using generic structure', [
                'document_type' => $documentType->value,
            ]);
        }
        
        // Fallback: dùng generic structure nếu không có template hoặc extract failed
        if (!$templateStructure) {
            $templateStructure = $documentType->getTemplateStructure();
        }
        
        $prompt = $this->buildPrompt(
            $userRequest, 
            $documentType, 
            $collectedData, 
            $autoFilledData, 
            $templateStructure,
            $templateContent  // ✅ MỚI: Truyền template content vào prompt
        );
        
        $response = OpenAI::chat()->create([
            'model' => config('openai.model', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt($documentType),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
        ]);
        
        $content = json_decode($response->choices[0]->message->content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI response as JSON', [
                'response' => $response->choices[0]->message->content,
            ]);
            // Fallback: return basic structure
            return $this->getFallbackContent($documentType, $userRequest);
        }
        
        return $content;
    }
    
    /**
     * Build prompt for AI
     * 
     * ✅ MỚI: Include template content vào prompt nếu có
     */
    protected function buildPrompt(
        string $userRequest,
        DocumentType $documentType,
        array $collectedData,
        array $autoFilledData,
        array $templateStructure,
        ?string $templateContent = null  // ✅ MỚI: Thêm parameter
    ): string {
        $prompt = "Bạn là chuyên gia soạn thảo văn bản hành chính Việt Nam theo Nghị định 30/2020/NĐ-CP.\n\n";
        $prompt .= "Yêu cầu: {$userRequest}\n\n";
        $prompt .= "Loại văn bản: {$documentType->displayName()}\n\n";
        
        // ✅ MỚI: Include template content nếu có
        if ($templateContent) {
            // ✅ LOG: Including template content in prompt
            Log::info('🔵 [DocumentDrafting] Including template content in AI prompt', [
                'template_content_length' => strlen($templateContent),
                'template_content_preview' => substr($templateContent, 0, 300),
            ]);
            
            $prompt .= "**QUAN TRỌNG:** Bạn PHẢI tạo văn bản theo đúng mẫu template sau:\n\n";
            $prompt .= "--- MẪU TEMPLATE ---\n";
            $prompt .= $templateContent . "\n";
            $prompt .= "--- HẾT MẪU TEMPLATE ---\n\n";
            $prompt .= "Văn bản bạn tạo PHẢI:\n";
            $prompt .= "- Giữ nguyên cấu trúc và format như mẫu template trên\n";
            $prompt .= "- Điền đúng các placeholder trong template (nếu có)\n";
            $prompt .= "- Tuân thủ văn phong và style của template\n";
            $prompt .= "- Giữ nguyên thứ tự các phần như trong template\n\n";
        } else {
            Log::info('🔵 [DocumentDrafting] No template content, using generic prompt', [
                'document_type' => $documentType->value,
            ]);
        }
        
        $prompt .= "Thông tin đã có:\n";
        $prompt .= json_encode($autoFilledData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $prompt .= "Cấu trúc văn bản cần tạo:\n";
        $prompt .= json_encode($templateStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $prompt .= "Hãy tạo nội dung văn bản hành chính với:\n";
        $prompt .= "- Văn phong trang trọng, khách quan\n";
        $prompt .= "- Tuân thủ quy định Nghị định 30/2020/NĐ-CP\n";
        $prompt .= "- Điền đầy đủ các trường trong cấu trúc\n";
        
        if ($templateContent) {
            $prompt .= "- **TUÂN THỦ NGHIÊM NGẶT** cấu trúc và format của template mẫu ở trên\n";
        }
        
        $prompt .= "- Trả về JSON với các key tương ứng với cấu trúc\n";
        
        return $prompt;
    }
    
    /**
     * Get system prompt for document type
     */
    protected function getSystemPrompt(DocumentType $documentType): string
    {
        $basePrompt = "Bạn là chuyên gia soạn thảo văn bản hành chính Việt Nam. ";
        $basePrompt .= "Bạn phải tuân thủ nghiêm ngặt Nghị định 30/2020/NĐ-CP về công tác văn thư.\n\n";
        
        $typeSpecific = match($documentType) {
            DocumentType::CONG_VAN => "Công văn phải có: Số văn bản, ngày tháng, nơi nhận, phần mở đầu, nội dung, phần kết, người ký, chức vụ.",
            DocumentType::QUYET_DINH => "Quyết định phải có: Số quyết định, ngày ký, căn cứ pháp lý, xét đề nghị, quyết định, nhiệm vụ và quyền hạn, hiệu lực thi hành, người ký, chức vụ.",
            DocumentType::TO_TRINH => "Tờ trình phải có: Số tờ trình, ngày, nơi gửi, phần mở đầu, mục đích, thời gian địa điểm, thành phần tham dự, dự toán kinh phí, phần kết, người ký, chức vụ.",
            DocumentType::BAO_CAO => "Báo cáo phải có: Số báo cáo, ngày, nơi nhận, phần mở đầu, nội dung, kết luận, kiến nghị, người ký, chức vụ.",
            DocumentType::BIEN_BAN => "Biên bản phải có: Số biên bản, ngày, địa điểm, thành phần, nội dung, kết luận, chữ ký, chức vụ.",
            DocumentType::THONG_BAO => "Thông báo phải có: Số thông báo, ngày, nơi nhận, phần mở đầu, nội dung, phần kết, người ký, chức vụ.",
            DocumentType::NGHI_QUYET => "Nghị quyết phải có: Số nghị quyết, ngày, nơi nhận, phần mở đầu, căn cứ, nghị quyết, hiệu lực, người ký, chức vụ.",
        };
        
        return $basePrompt . $typeSpecific;
    }
    
    /**
     * Get fallback content if AI fails
     */
    protected function getFallbackContent(DocumentType $documentType, string $userRequest): array
    {
        $structure = $documentType->getTemplateStructure();
        $content = [];
        
        foreach ($structure as $section => $fields) {
            foreach ($fields as $field) {
                $content[$field] = "[Cần điền: {$field}]";
            }
        }
        
        $content['noi_dung'] = $userRequest;
        
        return $content;
    }
    
    /**
     * Format content for display
     */
    protected function formatContent(array $documentData, DocumentType $documentType): string
    {
        $structure = $documentType->getTemplateStructure();
        $formatted = [];
        
        // Header
        if (isset($structure['header'])) {
            foreach ($structure['header'] as $field) {
                if (isset($documentData[$field])) {
                    $formatted[] = $this->formatField($field, $documentData[$field]);
                }
            }
        }
        
        // Body
        if (isset($structure['body'])) {
            foreach ($structure['body'] as $field) {
                if (isset($documentData[$field])) {
                    $formatted[] = $this->formatField($field, $documentData[$field]);
                }
            }
        }
        
        // Footer
        if (isset($structure['footer'])) {
            foreach ($structure['footer'] as $field) {
                if (isset($documentData[$field])) {
                    $formatted[] = $this->formatField($field, $documentData[$field]);
                }
            }
        }
        
        return implode("\n\n", $formatted);
    }
    
    /**
     * Format a field for display
     */
    protected function formatField(string $field, string $value): string
    {
        $fieldLabels = [
            'so_van_ban' => 'Số văn bản',
            'ngay_thang' => 'Ngày tháng',
            'noi_nhan' => 'Nơi nhận',
            'mo_dau' => 'Mở đầu',
            'noi_dung' => 'Nội dung',
            'ket' => 'Kết',
            'nguoi_ky' => 'Người ký',
            'chuc_vu' => 'Chức vụ',
        ];
        
        $label = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        
        return "**{$label}:** {$value}";
    }
    
    /**
     * Generate DOCX file from document data
     */
    protected function generateDocx(DocumentType $documentType, array $documentData, ChatSession $session): string
    {
        $phpWord = new PhpWord();
        
        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('AI Hành chính công');
        $properties->setTitle($documentType->displayName());
        $properties->setDescription('Văn bản hành chính được soạn thảo tự động');
        
        // Add section
        $section = $phpWord->addSection([
            'marginTop' => 1134, // 2cm
            'marginBottom' => 1134,
            'marginLeft' => 1134,
            'marginRight' => 1134,
        ]);
        
        // Add header
        $this->addHeader($section, $documentData, $documentType);
        
        // Add body
        $this->addBody($section, $documentData, $documentType);
        
        // Add footer
        $this->addFooter($section, $documentData, $documentType);
        
        // Save file
        $fileName = $this->generateFileName($documentType, $session);
        $filePath = storage_path("app/public/documents/{$fileName}");
        
        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filePath);
        
        // Return public URL
        return Storage::disk('public')->url("documents/{$fileName}");
    }
    
    /**
     * Add header to document
     */
    protected function addHeader($section, array $data, DocumentType $documentType): void
    {
        $structure = $documentType->getTemplateStructure();
        
        if (!isset($structure['header'])) {
            return;
        }
        
        // Organization name (if available)
        if (isset($data['ten_co_quan'])) {
            $section->addText(
                strtoupper($data['ten_co_quan']),
                ['bold' => true, 'size' => 14],
                ['alignment' => Jc::CENTER]
            );
            $section->addTextBreak(1);
        }
        
        // Document number and date
        $headerText = [];
        if (isset($data['so_van_ban'])) {
            $headerText[] = "Số: {$data['so_van_ban']}";
        }
        if (isset($data['ngay_thang'])) {
            $headerText[] = "Ngày: {$data['ngay_thang']}";
        }
        
        if (!empty($headerText)) {
            $section->addText(
                implode(' | ', $headerText),
                ['size' => 12],
                ['alignment' => Jc::RIGHT]
            );
            $section->addTextBreak(1);
        }
        
        // Document type title
        $section->addText(
            strtoupper($documentType->displayName()),
            ['bold' => true, 'size' => 16],
            ['alignment' => Jc::CENTER]
        );
        $section->addTextBreak(2);
    }
    
    /**
     * Add body to document
     */
    protected function addBody($section, array $data, DocumentType $documentType): void
    {
        $structure = $documentType->getTemplateStructure();
        
        if (!isset($structure['body'])) {
            return;
        }
        
        foreach ($structure['body'] as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $label = $this->getFieldLabel($field);
                
                // Add label if needed
                if ($this->shouldShowLabel($field)) {
                    $section->addText(
                        $label,
                        ['bold' => true, 'size' => 12]
                    );
                }
                
                // Add content
                $section->addText(
                    $data[$field],
                    ['size' => 12],
                    ['spaceAfter' => 240] // 12pt spacing
                );
                
                $section->addTextBreak(1);
            }
        }
    }
    
    /**
     * Add footer to document
     */
    protected function addFooter($section, array $data, DocumentType $documentType): void
    {
        $structure = $documentType->getTemplateStructure();
        
        if (!isset($structure['footer'])) {
            return;
        }
        
        $section->addTextBreak(2);
        
        // Signer info
        $footerText = [];
        if (isset($data['nguoi_ky'])) {
            $footerText[] = $data['nguoi_ky'];
        }
        if (isset($data['chuc_vu'])) {
            $footerText[] = $data['chuc_vu'];
        }
        
        if (!empty($footerText)) {
            $section->addText(
                implode("\n", $footerText),
                ['size' => 12],
                ['alignment' => Jc::RIGHT]
            );
        }
    }
    
    /**
     * Get field label
     */
    protected function getFieldLabel(string $field): string
    {
        $labels = [
            'mo_dau' => 'Mở đầu:',
            'noi_dung' => 'Nội dung:',
            'ket' => 'Kết:',
            'can_cu' => 'Căn cứ:',
            'xet_de_nghi' => 'Xét đề nghị:',
            'quyet_dinh' => 'Quyết định:',
            'nhiem_vu_quyen_han' => 'Nhiệm vụ và quyền hạn:',
            'hieu_luc' => 'Hiệu lực thi hành:',
            'muc_dich' => 'Mục đích:',
            'thoi_gian_dia_diem' => 'Thời gian, địa điểm:',
            'thanh_phan' => 'Thành phần:',
            'du_toan' => 'Dự toán kinh phí:',
            'ket_luan' => 'Kết luận:',
            'kien_nghi' => 'Kiến nghị:',
        ];
        
        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)) . ':';
    }
    
    /**
     * Check if field should show label
     */
    protected function shouldShowLabel(string $field): bool
    {
        $fieldsWithLabels = [
            'mo_dau', 'noi_dung', 'ket', 'can_cu', 'xet_de_nghi',
            'quyet_dinh', 'nhiem_vu_quyen_han', 'hieu_luc', 'muc_dich',
            'thoi_gian_dia_diem', 'thanh_phan', 'du_toan', 'ket_luan', 'kien_nghi',
        ];
        
        return in_array($field, $fieldsWithLabels);
    }
    
    /**
     * Generate file name
     */
    protected function generateFileName(DocumentType $documentType, ChatSession $session): string
    {
        $timestamp = now()->format('YmdHis');
        $type = $documentType->value;
        $sessionId = $session->id;
        
        return "{$type}_{$sessionId}_{$timestamp}.docx";
    }
}

