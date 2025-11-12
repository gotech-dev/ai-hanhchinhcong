<?php

namespace App\Services;

use App\Models\AiAssistant;
use App\Models\ChatSession;
use App\Models\UserReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\ReportFileGenerator;
use App\Services\TemplateAnalyzer;
use App\Services\ReportContentParser;

class ReportGenerator
{
    public function __construct(
        protected DocumentProcessor $documentProcessor,
        protected ?ReportFileGenerator $reportFileGenerator = null,
        protected ?TemplateAnalyzer $templateAnalyzer = null
    ) {
        // Lazy load ReportFileGenerator to avoid circular dependency
        if (!$this->reportFileGenerator) {
            $this->reportFileGenerator = app(ReportFileGenerator::class);
        }
        // Lazy load TemplateAnalyzer
        if (!$this->templateAnalyzer) {
            $this->templateAnalyzer = app(TemplateAnalyzer::class);
        }
    }

    /**
     * Generate report from template with collected data
     * 
     * ✅ FLOW MỚI: AI Generate Content với Template Format
     * 1. Analyze template structure (sections, headings, placeholders, format)
     * 2. AI generate content mới dựa trên yêu cầu và template structure
     * 3. Parse AI-generated content thành structured data
     * 4. Map content vào template (giữ format)
     * 5. Tạo DOCX với format giống hệt template + AI-generated content
     *
     * @param AiAssistant $assistant
     * @param ChatSession $session
     * @param array $collectedData
     * @param string|null $userRequest Original user request for context
     * @return array{report_content: string, report_file_path: string|null, report_id: int}
     */
    public function generateReport(AiAssistant $assistant, ChatSession $session, array $collectedData, ?string $userRequest = null): array
    {
        try {
            // ✅ QUAN TRỌNG: Chỉ xử lý cho report_generator
            if ($assistant->assistant_type !== 'report_generator') {
                throw new \Exception('ReportGenerator chỉ dùng cho assistant_type = report_generator');
            }
            
            // Get template file path
            $templateUrl = $assistant->template_file_path;
            if (!$templateUrl) {
                throw new \Exception('Template file not found for assistant');
            }

            // ✅ FIX: Điền data trực tiếp vào template (KHÔNG generate content mới)
            // Flow đúng: collectedData → map vào placeholders → giữ nguyên format template
            $docxUrl = null;
            $reportContent = ''; // Content để hiển thị preview (extract từ DOCX sau)
            
            try {
                // 1. Tạo UserReport tạm thời
                $userReport = UserReport::create([
                    'user_id' => $session->user_id,
                    'chat_session_id' => $session->id,
                    'report_content' => '', // Sẽ extract sau khi generate DOCX
                    'report_file_path' => null,
                    'file_format' => 'docx',
                ]);
                
                // 2. ✅ FIX CHÍNH: Gọi generateDocxFromTemplate trực tiếp với collectedData
                // KHÔNG gọi AI generate content, KHÔNG parse AI content
                // TemplateProcessor sẽ tự động map data vào placeholders và GIỮ NGUYÊN FORMAT
                $docxUrl = $this->reportFileGenerator->generateDocxFromTemplate(
                    $userReport,
                    $assistant,
                    $collectedData // ✅ Dùng trực tiếp collected data
                );
                
                // 3. Extract text từ DOCX đã tạo để hiển thị preview (optional)
                $reportContent = $this->extractTextFromDocx($docxUrl);
                
                // 4. Update report với content
                $userReport->update([
                    'report_content' => $reportContent,
                ]);
                
                Log::info('Report generated successfully (direct template fill)', [
                    'report_id' => $userReport->id,
                    'session_id' => $session->id,
                    'assistant_id' => $assistant->id,
                    'docx_url' => $docxUrl,
                    'collected_fields' => count($collectedData),
                    'content_length' => strlen($reportContent),
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to generate DOCX from template', [
                    'error' => $e->getMessage(),
                    'assistant_id' => $assistant->id,
                    'assistant_type' => $assistant->getAssistantTypeValue(),
                    'template_url' => $templateUrl,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
            
            return [
                'report_content' => $reportContent,
                'report_file_path' => $docxUrl,
                'report_id' => $userReport->id ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Report generation error', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistant->getAssistantTypeValue() ?? 'unknown',
                'session_id' => $session->id,
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Extract text from DOCX file (for display purposes)
     *
     * @param string $docxUrl
     * @return string
     */
    protected function extractTextFromDocx(string $docxUrl): string
    {
        try {
            // Parse URL to get file path
            $parsedUrl = parse_url($docxUrl);
            $filePath = $parsedUrl['path'] ?? $docxUrl;
            
            // Remove /storage prefix if present (use preg_replace instead of ltrim)
            // ltrim() removes characters, not prefix!
            $filePath = preg_replace('#^/storage/#', '', $filePath);
            $filePath = ltrim($filePath, '/');
            
            // Get full path
            $fullPath = Storage::disk('public')->path($filePath);
            
            if (!file_exists($fullPath)) {
                Log::warning('DOCX file not found for text extraction', [
                    'docx_url' => $docxUrl,
                    'file_path' => $fullPath,
                ]);
                return '';
            }
            
            // Extract text using DocumentProcessor
            return $this->documentProcessor->extractText($fullPath);
        } catch (\Exception $e) {
            Log::warning('Failed to extract text from DOCX', [
                'error' => $e->getMessage(),
                'docx_url' => $docxUrl,
            ]);
            return '';
        }
    }

    /**
     * Extract text from template file
     *
     * @param string $templatePath
     * @return string
     */
    protected function extractTemplateText(string $templatePath): string
    {
        try {
            // Parse URL to get file path
            $parsedUrl = parse_url($templatePath);
            $filePath = $parsedUrl['path'] ?? $templatePath;
            
            // Remove /storage prefix if present
            $filePath = ltrim($filePath, '/storage/');
            
            // Get full path
            $fullPath = Storage::disk('public')->path($filePath);
            
            if (!file_exists($fullPath)) {
                throw new \Exception("Template file not found: {$fullPath}");
            }
            
            // Extract text using DocumentProcessor
            return $this->documentProcessor->extractText($fullPath);
        } catch (\Exception $e) {
            Log::error('Failed to extract template text', [
                'error' => $e->getMessage(),
                'template_path' => $templatePath,
            ]);
            
            throw $e;
        }
    }

    /**
     * Fill template with collected data using AI
     * Returns both structured data (for template replacement) and formatted text (for display)
     *
     * @param string $templateText
     * @param array $collectedData
     * @param array $templateFields
     * @param AiAssistant $assistant
     * @return array{formatted_text: string, structured_data: array}
     */
    protected function fillTemplateWithData(
        string $templateText,
        array $collectedData,
        array $templateFields,
        AiAssistant $assistant
    ): array {
        try {
            // Build data summary
            $dataSummary = "Dữ liệu đã thu thập:\n";
            $fieldMapping = [];
            foreach ($collectedData as $key => $value) {
                $field = collect($templateFields)->firstWhere('key', $key);
                $label = $field['label'] ?? $key;
                $dataSummary .= "- {$label}: {$value}\n";
                $fieldMapping[$key] = $label;
            }
            
            // Extract placeholders from template text
            $placeholders = $this->extractPlaceholders($templateText);
            
            // Build prompt for AI - Request both JSON structured data and formatted text
            $prompt = "Bạn là một chuyên gia tạo báo cáo. Hãy điền thông tin vào template báo cáo sau dựa trên dữ liệu đã thu thập.\n\n";
            $prompt .= "TEMPLATE BÁO CÁO:\n";
            $prompt .= substr($templateText, 0, 4000) . "\n\n"; // Limit template text to avoid token limit
            $prompt .= "{$dataSummary}\n\n";
            
            if (!empty($placeholders)) {
                $prompt .= "CÁC PLACEHOLDER TRONG TEMPLATE:\n";
                foreach ($placeholders as $placeholder) {
                    $prompt .= "- {$placeholder}\n";
                }
                $prompt .= "\n";
            }
            
            $prompt .= "YÊU CẦU:\n";
            $prompt .= "1. Phân tích template và dữ liệu đã thu thập\n";
            $prompt .= "2. Tạo báo cáo hoàn chỉnh với nội dung đã được điền đầy đủ thông tin\n";
            $prompt .= "3. Giữ nguyên cấu trúc, phong cách ngôn ngữ và định dạng của template\n";
            $prompt .= "4. Trả về JSON với format sau:\n";
            $prompt .= "{\n";
            $prompt .= "  \"formatted_text\": \"Nội dung báo cáo đã được format (text thuần túy)\",\n";
            $prompt .= "  \"structured_data\": {\n";
            $prompt .= "    \"field_name\": \"giá trị tương ứng\",\n";
            $prompt .= "    ...\n";
            $prompt .= "  }\n";
            $prompt .= "}\n\n";
            $prompt .= "Lưu ý:\n";
            $prompt .= "- formatted_text: Toàn bộ nội dung báo cáo đã được điền đầy đủ, giữ nguyên cấu trúc template\n";
            $prompt .= "- structured_data: Map các field từ collectedData vào các key tương ứng (giữ nguyên key name)\n";
            
            $response = OpenAI::chat()->create([
                'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một chuyên gia tạo báo cáo chuyên nghiệp. Bạn có khả năng điền thông tin vào template một cách chính xác và giữ nguyên format. Bạn luôn trả về JSON format theo yêu cầu.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3, // Lower temperature for more consistent output
                'max_tokens' => 4000, // Allow longer reports
                'response_format' => ['type' => 'json_object'], // Force JSON response
            ]);
            
            $responseContent = trim($response->choices[0]->message->content);
            
            // Try to parse JSON response
            $parsedData = json_decode($responseContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to extract JSON from markdown code blocks
                if (preg_match('/```(?:json)?\s*(\{[\s\S]*\})\s*```/', $responseContent, $matches)) {
                    $parsedData = json_decode($matches[1], true);
                }
                
                // If still not valid JSON, try to extract JSON object directly
                if (json_last_error() !== JSON_ERROR_NONE && preg_match('/\{[\s\S]*\}/', $responseContent, $matches)) {
                    $parsedData = json_decode($matches[0], true);
                }
            }
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($parsedData['formatted_text'])) {
                // Fallback: Treat response as plain text
                Log::warning('AI response is not valid JSON, using as plain text', [
                    'assistant_id' => $assistant->id,
                    'json_error' => json_last_error_msg(),
                    'response_preview' => substr($responseContent, 0, 200),
                ]);
                
                return [
                    'formatted_text' => $responseContent,
                    'structured_data' => $collectedData, // Use collected data as fallback
                ];
            }
            
            // Validate and clean structured_data
            $aiStructuredData = $parsedData['structured_data'] ?? [];
            if (!is_array($aiStructuredData)) {
                $aiStructuredData = [];
            }
            
            // Merge structured_data with collectedData (prioritize AI-generated data)
            // AI-generated data takes precedence, but fallback to collectedData if missing
            $structuredData = array_merge(
                $collectedData, // Base data
                $aiStructuredData // AI-generated data (overwrites base data)
            );
            
            // Ensure formatted_text is a string
            $formattedText = $parsedData['formatted_text'] ?? $responseContent;
            if (!is_string($formattedText)) {
                $formattedText = (string) $formattedText;
            }
            
            Log::info('Report content generated with structured data', [
                'assistant_id' => $assistant->id,
                'content_length' => strlen($formattedText),
                'structured_fields' => count($structuredData),
                'ai_structured_fields' => count($aiStructuredData),
                'collected_fields' => count($collectedData),
            ]);
            
            return [
                'formatted_text' => $formattedText,
                'structured_data' => $structuredData,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fill template with data', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
            ]);
            
            // Fallback: Simple replacement
            $formattedText = $this->simpleTemplateFill($templateText, $collectedData);
            return [
                'formatted_text' => $formattedText,
                'structured_data' => $collectedData,
            ];
        }
    }
    
    /**
     * Extract placeholders from template text
     * 
     * @param string $templateText
     * @return array
     */
    protected function extractPlaceholders(string $templateText): array
    {
        $placeholders = [];
        
        // Match {{field_name}} or ${field_name} patterns
        preg_match_all('/\{\{([^}]+)\}\}|\$\{([^}]+)\}/', $templateText, $matches);
        
        if (!empty($matches[0])) {
            $placeholders = array_unique($matches[0]);
        }
        
        return $placeholders;
    }

    /**
     * Simple template fill as fallback (without AI)
     *
     * @param string $templateText
     * @param array $collectedData
     * @return string
     */
    protected function simpleTemplateFill(string $templateText, array $collectedData): string
    {
        $result = $templateText;
        
        // Replace placeholders: {{field}}, [FIELD], {field}
        foreach ($collectedData as $key => $value) {
            $patterns = [
                '/\{\{' . preg_quote($key, '/') . '\}\}/i',
                '/\[' . preg_quote($key, '/') . '\]/i',
                '/\{' . preg_quote($key, '/') . '\}/i',
            ];
            
            foreach ($patterns as $pattern) {
                $result = preg_replace($pattern, $value, $result);
            }
        }
        
        return $result;
    }

    /**
     * Generate content with AI based on user request and template structure
     * 
     * @param string $userRequest User's request
     * @param array $collectedData Collected data from conversation
     * @param array $templateStructure Template structure (sections, headings, placeholders, etc.)
     * @param AiAssistant $assistant
     * @return string AI-generated content
     */
    protected function generateContentWithAI(
        string $userRequest,
        array $collectedData,
        array $templateStructure,
        AiAssistant $assistant
    ): string {
        try {
            // Build data summary
            $dataSummary = "Dữ liệu đã thu thập:\n";
            foreach ($collectedData as $key => $value) {
                $dataSummary .= "- {$key}: {$value}\n";
            }
            
            // Build sections summary
            $sectionsSummary = '';
            if (!empty($templateStructure['sections'])) {
                $sectionsSummary = "Các phần trong template:\n";
                foreach ($templateStructure['sections'] as $index => $section) {
                    $title = is_array($section) ? ($section['title'] ?? "Phần " . ($index + 1)) : $section;
                    $sectionsSummary .= "- {$title}\n";
                }
            }
            
            // Build headings summary
            $headingsSummary = '';
            if (!empty($templateStructure['headings'])) {
                $headingsSummary = "Các tiêu đề trong template:\n";
                foreach ($templateStructure['headings'] as $heading) {
                    $headingsSummary .= "- {$heading}\n";
                }
            }
            
            // Build placeholders summary
            $placeholdersSummary = '';
            if (!empty($templateStructure['placeholders'])) {
                $placeholdersSummary = "Các placeholder trong template:\n";
                foreach ($templateStructure['placeholders'] as $placeholder) {
                    $placeholdersSummary .= "- {$placeholder}\n";
                }
            }
            
            // Build prompt for AI
            $prompt = "Bạn là chuyên gia tạo báo cáo. Hãy tạo nội dung báo cáo dựa trên yêu cầu và template mẫu.\n\n";
            $prompt .= "YÊU CẦU CỦA USER:\n{$userRequest}\n\n";
            
            if (!empty($dataSummary)) {
                $prompt .= "{$dataSummary}\n";
            }
            
            // Add template context
            // ✅ FIX 1: Sanitize template text to prevent UTF-8 errors
            $templateText = $templateStructure['text_preview'] ?? '';
            $sanitizedTemplateText = $this->sanitizeTextForOpenAI($templateText);
            
            $prompt .= "CẤU TRÚC TEMPLATE:\n";
            $prompt .= $sanitizedTemplateText; // ✅ Use sanitized text
            
            if ($sectionsSummary) {
                $prompt .= "\n\n{$sectionsSummary}";
            }
            
            if ($headingsSummary) {
                $prompt .= "\n{$headingsSummary}";
            }
            
            if ($placeholdersSummary) {
                $prompt .= "\n{$placeholdersSummary}";
            }
            
            $prompt .= "\n\nYÊU CẦU:\n";
            $prompt .= "1. Tạo nội dung báo cáo hoàn chỉnh dựa trên yêu cầu của user\n";
            $prompt .= "2. Giữ nguyên cấu trúc và format của template (sections, headings)\n";
            $prompt .= "3. Điền đầy đủ thông tin vào các phần dựa trên dữ liệu đã thu thập\n";
            $prompt .= "4. Tạo nội dung phù hợp với từng section\n";
            $prompt .= "5. Sử dụng thông tin từ collected data để điền vào placeholders\n";
            $prompt .= "6. Tạo nội dung chuyên nghiệp, đầy đủ và phù hợp với loại báo cáo\n";
            
            $response = OpenAI::chat()->create([
                'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là chuyên gia tạo báo cáo chuyên nghiệp. Bạn tạo nội dung báo cáo dựa trên yêu cầu và template mẫu, giữ nguyên cấu trúc và format.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ]);
            
            $aiContent = trim($response->choices[0]->message->content);
            
            Log::info('AI content generated', [
                'assistant_id' => $assistant->id,
                'content_length' => strlen($aiContent),
                'sections_count' => count($templateStructure['sections'] ?? []),
            ]);
            
            return $aiContent;
        } catch (\Exception $e) {
            Log::error('Failed to generate content with AI', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
            ]);
            
            // Fallback: Simple template fill
            $templateText = $templateStructure['text_preview'] ?? '';
            return $this->simpleTemplateFill($templateText, $collectedData);
        }
    }

    /**
     * ✅ FIX 1: Sanitize text to prevent OpenAI UTF-8 errors
     * 
     * NEW METHOD - Không ảnh hưởng code cũ
     * Chỉ dùng cho regenerate workflow
     * 
     * @param string $text
     * @return string
     */
    protected function sanitizeTextForOpenAI(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        try {
            // Convert to valid UTF-8
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            
            // Remove null bytes and control characters (except newlines/tabs)
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
            
            // Replace superscripts with regular numbers (prevent UTF-8 issues)
            $superscripts = [
                '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4', '⁵' => '5',
                '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9', '⁰' => '0'
            ];
            $text = strtr($text, $superscripts);
            
            // Normalize whitespace (multiple spaces → single space)
            $text = preg_replace('/\s+/u', ' ', $text);
            
            // Trim
            $text = trim($text);
            
            // Limit length to prevent token overflow (OpenAI has limits)
            if (mb_strlen($text) > 3000) {
                $text = mb_substr($text, 0, 3000) . '...';
            }
            
            Log::debug('Text sanitized for OpenAI', [
                'original_length' => strlen($text),
                'sanitized_length' => strlen($text),
            ]);
            
            return $text;
        } catch (\Exception $e) {
            Log::warning('Failed to sanitize text for OpenAI', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: return truncated original text
            return mb_strlen($text) > 3000 ? mb_substr($text, 0, 3000) . '...' : $text;
        }
    }

    /**
     * Get template file path from URL
     *
     * @param string $templateUrl
     * @return string
     */
    protected function getTemplatePath(string $templateUrl): string
    {
        // Parse URL to get path
        $parsedUrl = parse_url($templateUrl);
        $path = $parsedUrl['path'] ?? $templateUrl;
        
        // Remove /storage prefix if present (use preg_replace instead of ltrim)
        $filePath = preg_replace('#^/storage/#', '', $path);
        $filePath = ltrim($filePath, '/');
        
        // Get full path using Storage
        return Storage::disk('public')->path($filePath);
    }
}

