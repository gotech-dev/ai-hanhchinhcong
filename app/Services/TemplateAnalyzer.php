<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class TemplateAnalyzer
{
    public function __construct(
        protected DocumentProcessor $documentProcessor
    ) {}

    /**
     * Analyze template file and generate workflow config
     *
     * @param UploadedFile $templateFile
     * @return array
     */
    public function analyzeTemplate(UploadedFile $templateFile): array
    {
        try {
            // Extract text from template
            $text = $this->documentProcessor->extractText($templateFile);
            
            // Extract structure
            $structure = $this->extractStructure($text);
            
            // Identify fields
            $fields = $this->identifyFields($text, $structure);
            
            // Generate smart questions for each field
            $questions = $this->generateSmartQuestions($fields, $text);
            
            // Create workflow config
            $workflowConfig = $this->createWorkflowConfig($fields, $questions);
            
            return [
                'structure' => $structure,
                'fields' => $fields,
                'questions' => $questions,
                'workflow_config' => $workflowConfig,
            ];
        } catch (\Exception $e) {
            Log::error('Template analysis error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return basic structure even if AI analysis fails
            return $this->getBasicStructure($text);
        }
    }

    /**
     * Analyze template structure in detail (for AI content generation)
     * Extract sections, headings, tables, placeholders, and format info
     *
     * @param string $templatePath Full path to template file
     * @return array
     */
    public function analyzeTemplateStructure(string $templatePath): array
    {
        try {
            // Extract text from template
            $text = $this->documentProcessor->extractText($templatePath);
            
            // Extract structure
            $structure = $this->extractStructure($text);
            
            // Extract placeholders
            $placeholders = $this->extractPlaceholders($text);
            
            // Extract format info (if DOCX)
            $formatInfo = $this->extractFormatInfo($templatePath);
            
            return [
                'sections' => $structure['sections'] ?? [],
                'headings' => $structure['headings'] ?? [],
                'tables' => $structure['tables'] ?? [],
                'placeholders' => $placeholders,
                'format_info' => $formatInfo,
                'text_preview' => substr($text, 0, 2000), // First 2000 chars for AI context
            ];
        } catch (\Exception $e) {
            Log::error('Failed to analyze template structure', [
                'error' => $e->getMessage(),
                'template_path' => $templatePath,
            ]);
            
            // Return basic structure
            return [
                'sections' => [],
                'headings' => [],
                'tables' => [],
                'placeholders' => [],
                'format_info' => [],
                'text_preview' => '',
            ];
        }
    }

    /**
     * Extract placeholders from template text
     *
     * @param string $text
     * @return array
     */
    protected function extractPlaceholders(string $text): array
    {
        $placeholders = [];
        
        // Match {{field_name}}, ${field_name}, [field_name] patterns
        preg_match_all('/\{\{([^}]+)\}\}|\$\{([^}]+)\}|\[([^\]]+)\]/', $text, $matches);
        
        if (!empty($matches[0])) {
            $placeholders = array_unique($matches[0]);
        }
        
        return $placeholders;
    }

    /**
     * Extract format information from DOCX template
     *
     * @param string $templatePath
     * @return array
     */
    protected function extractFormatInfo(string $templatePath): array
    {
        $formatInfo = [];
        
        // Only extract format info for DOCX files
        if (!str_ends_with(strtolower($templatePath), '.docx')) {
            return $formatInfo;
        }
        
        try {
            // Use PhpWord to extract format info
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($templatePath);
            $sections = $phpWord->getSections();
            
            foreach ($sections as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                        $font = $element->getFontStyle();
                        if ($font) {
                            $formatInfo[] = [
                                'type' => 'text',
                                'font' => $font->getName(),
                                'size' => $font->getSize(),
                                'color' => $font->getColor(),
                                'bold' => $font->isBold(),
                                'italic' => $font->isItalic(),
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('Failed to extract format info from DOCX', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $formatInfo;
    }

    /**
     * Extract structure from template text
     *
     * @param string $text
     * @return array
     */
    protected function extractStructure(string $text): array
    {
        $structure = [
            'sections' => [],
            'headings' => [],
            'tables' => [],
        ];
        
        $lines = explode("\n", $text);
        $currentSection = null;
        $headings = [];
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $isHeading = false;
            
            // ✅ IMPROVED: Detect multiple heading patterns
            // 1. ALL CAPS: "TÊN LOẠI VĂN BẢN"
            if (preg_match('/^[A-Z][A-Z\s\:]+$/', $line) && strlen($line) > 3) {
                $isHeading = true;
            }
            // 2. Numbered section: "1. Mục đích", "I. Giới thiệu", "A. Phần 1"
            elseif (preg_match('/^[0-9IVXLCDM]+[\.\)]\s+[A-ZÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬÉÈẺẼẸÊẾỀỂỄỆÍÌỈĨỊÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢÚÙỦŨỤƯỨỪỬỮỰÝỲỶỸỴĐ]/', $line)) {
                $isHeading = true;
            }
            // 3. Title case with colon: "Tên công ty:", "Địa chỉ:"
            elseif (preg_match('/^[A-ZÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬÉÈẺẼẸÊẾỀỂỄỆÍÌỈĨỊÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢÚÙỦŨỤƯỨỪỬỮỰÝỲỶỸỴĐ][^:]*:$/u', $line)) {
                $isHeading = true;
            }
            // 4. Short lines in title case: "Mẫu 1.4 - Văn bản có tên loại"
            elseif (preg_match('/^[A-ZÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬÉÈẺẼẸÊẾỀỂỄỆÍÌỈĨỊÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢÚÙỦŨỤƯỨỪỬỮỰÝỲỶỸỴĐ]/', $line) && strlen($line) < 100) {
                // Check if next line is empty or also a heading (likely a title)
                $nextLine = isset($lines[$lineNum + 1]) ? trim($lines[$lineNum + 1]) : '';
                if (empty($nextLine) || preg_match('/^[A-ZÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬÉÈẺẼẸÊẾỀỂỄỆÍÌỈĨỊÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢÚÙỦŨỤƯỨỪỬỮỰÝỲỶỸỴĐ]/', $nextLine)) {
                    $isHeading = true;
                }
            }
            // 5. Lines ending with specific keywords suggesting titles
            elseif (preg_match('/^.*(BÁO CÁO|THÔNG BÁO|QUYẾT ĐỊNH|CÔNG VĂN|TỜ TRÌNH).*$/ui', $line)) {
                $isHeading = true;
            }
            
            if ($isHeading) {
                // Save previous section
                if ($currentSection) {
                    $structure['sections'][] = $currentSection;
                }
                
                // Start new section
                $headings[] = $line;
                $currentSection = [
                    'title' => $line,
                    'content' => '',
                    'level' => $this->detectHeadingLevel($line),
                ];
            } elseif ($currentSection) {
                $currentSection['content'] .= $line . "\n";
            } else {
                // Content before first heading
                if (!isset($structure['sections'][0])) {
                    $structure['sections'][] = [
                        'title' => 'Phần đầu',
                        'content' => '',
                        'level' => 0,
                    ];
                }
                $structure['sections'][0]['content'] .= $line . "\n";
            }
        }
        
        // Add last section
        if ($currentSection) {
            $structure['sections'][] = $currentSection;
        }
        
        $structure['headings'] = $headings;
        
        Log::debug('Extracted structure from template', [
            'headings_count' => count($headings),
            'sections_count' => count($structure['sections']),
            'headings' => array_slice($headings, 0, 5), // Log first 5 headings
        ]);
        
        return $structure;
    }
    
    /**
     * Detect heading level
     *
     * @param string $heading
     * @return int
     */
    protected function detectHeadingLevel(string $heading): int
    {
        // Level 1: ALL CAPS
        if (preg_match('/^[A-Z][A-Z\s:]+$/', $heading)) {
            return 1;
        }
        // Level 2: Roman numerals or single letter
        if (preg_match('/^[IVXLCDM]+[\.\)]\s/', $heading) || preg_match('/^[A-Z][\.\)]\s/', $heading)) {
            return 2;
        }
        // Level 3: Numbers
        if (preg_match('/^\d+[\.\)]\s/', $heading)) {
            return 3;
        }
        // Level 4: Sub-numbers
        if (preg_match('/^\d+\.\d+/', $heading)) {
            return 4;
        }
        
        return 5; // Default for other headings
    }

    /**
     * Identify fields from template text
     *
     * @param string $text
     * @param array $structure
     * @return array
     */
    protected function identifyFields(string $text, array $structure): array
    {
        $fields = [];
        
        // Common field patterns
        $patterns = [
            // Placeholders like {{field_name}}, [FIELD], {field}
            '/\{\{([^}]+)\}\}/' => 'placeholder',
            '/\[([^\]]+)\]/' => 'bracket',
            '/\{([^}]+)\}/' => 'brace',
            
            // Common Vietnamese report fields
            '/\b(?:hoạt động|activities?)\b/i' => 'activities',
            '/\b(?:kết quả|results?)\b/i' => 'results',
            '/\b(?:khó khăn|difficulties?|problems?)\b/i' => 'difficulties',
            '/\b(?:giải pháp|solutions?)\b/i' => 'solutions',
            '/\b(?:thời gian|time|period|tháng|năm)\b/i' => 'time_period',
            '/\b(?:địa điểm|location|place)\b/i' => 'location',
            '/\b(?:người|person|người thực hiện)\b/i' => 'person',
            '/\b(?:ngân sách|budget)\b/i' => 'budget',
            '/\b(?:mục tiêu|objectives?|goals?)\b/i' => 'objectives',
        ];
        
        $foundFields = [];
        
        // Extract placeholders
        foreach (['{{', '[', '{'] as $delimiter) {
            $pattern = $delimiter === '{{' ? '/\{\{([^}]+)\}\}/' : 
                      ($delimiter === '[' ? '/\[([^\]]+)\]/' : '/\{([^}]+)\}/');
            
            preg_match_all($pattern, $text, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $fieldName) {
                    $fieldName = trim($fieldName);
                    if (!empty($fieldName) && !in_array($fieldName, $foundFields)) {
                        $foundFields[] = $fieldName;
                        $fields[] = [
                            'key' => $this->normalizeFieldKey($fieldName),
                            'label' => $this->normalizeFieldLabel($fieldName),
                            'type' => $this->detectFieldType($fieldName),
                            'required' => true,
                            'source' => 'placeholder',
                        ];
                    }
                }
            }
        }
        
        // Extract fields from structure
        foreach ($structure['sections'] as $section) {
            $sectionText = strtolower($section['title'] . ' ' . $section['content']);
            
            foreach ($patterns as $pattern => $fieldKey) {
                if (preg_match($pattern, $sectionText) && !in_array($fieldKey, $foundFields)) {
                    $foundFields[] = $fieldKey;
                    $fields[] = [
                        'key' => $fieldKey,
                        'label' => $this->normalizeFieldLabel($fieldKey),
                        'type' => $this->detectFieldType($fieldKey),
                        'required' => true,
                        'source' => 'structure',
                    ];
                }
            }
        }
        
        return $fields;
    }

    /**
     * Generate smart questions for each field using AI
     *
     * @param array $fields
     * @param string $templateText
     * @return array
     */
    protected function generateSmartQuestions(array $fields, string $templateText): array
    {
        if (empty($fields)) {
            return [];
        }
        
        try {
            // Use AI to generate smart questions
            $prompt = "Dựa trên template báo cáo sau, hãy tạo câu hỏi thông minh và tự nhiên cho từng field để thu thập thông tin từ người dùng.\n\n";
            $prompt .= "Template:\n" . substr($templateText, 0, 2000) . "\n\n";
            $prompt .= "Các fields cần tạo câu hỏi:\n";
            
            foreach ($fields as $field) {
                $prompt .= "- {$field['key']}: {$field['label']}\n";
            }
            
            $prompt .= "\nHãy tạo câu hỏi bằng tiếng Việt, tự nhiên và dễ hiểu cho từng field. Trả về JSON format:\n";
            $prompt .= "{\"questions\": [{\"field_key\": \"...\", \"question\": \"...\", \"hint\": \"...\"}]}\n";
            
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Bạn là chuyên gia tạo câu hỏi thông minh cho thu thập thông tin.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);
            
            $content = $response->choices[0]->message->content;
            
            // Try to extract JSON from response
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $data = json_decode($matches[0], true);
                if (isset($data['questions']) && is_array($data['questions'])) {
                    return $data['questions'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to generate smart questions with AI', [
                'error' => $e->getMessage(),
            ]);
        }
        
        // Fallback: Generate basic questions
        return $this->generateBasicQuestions($fields);
    }

    /**
     * Generate basic questions as fallback
     *
     * @param array $fields
     * @return array
     */
    protected function generateBasicQuestions(array $fields): array
    {
        $questions = [];
        
        $defaultQuestions = [
            'activities' => 'Bạn có thể liệt kê các hoạt động chính đã thực hiện không?',
            'results' => 'Kết quả đạt được là gì? (Số liệu cụ thể nếu có)',
            'difficulties' => 'Có khó khăn gì trong quá trình thực hiện không?',
            'solutions' => 'Giải pháp đã áp dụng để giải quyết khó khăn là gì?',
            'time_period' => 'Thời gian thực hiện là gì?',
            'location' => 'Địa điểm thực hiện ở đâu?',
            'person' => 'Người thực hiện là ai?',
            'budget' => 'Ngân sách đã sử dụng là bao nhiêu?',
            'objectives' => 'Mục tiêu ban đầu là gì?',
        ];
        
        foreach ($fields as $field) {
            $key = $field['key'];
            $question = $defaultQuestions[$key] ?? "Vui lòng cung cấp thông tin về {$field['label']}";
            
            $questions[] = [
                'field_key' => $key,
                'question' => $question,
                'hint' => "Nhập thông tin về {$field['label']}",
            ];
        }
        
        return $questions;
    }

    /**
     * Create workflow config from fields and questions
     *
     * @param array $fields
     * @param array $questions
     * @return array
     */
    protected function createWorkflowConfig(array $fields, array $questions): array
    {
        $workflowSteps = [];
        
        foreach ($fields as $index => $field) {
            $question = collect($questions)->firstWhere('field_key', $field['key']);
            
            $workflowSteps[] = [
                'step_id' => $index + 1,
                'type' => 'collect_info',
                'field_key' => $field['key'],
                'field_label' => $field['label'],
                'field_type' => $field['type'],
                'required' => $field['required'] ?? true,
                'question' => $question['question'] ?? "Vui lòng cung cấp thông tin về {$field['label']}",
                'hint' => $question['hint'] ?? null,
                'order' => $index + 1,
            ];
        }
        
        // Add final step: generate report
        $workflowSteps[] = [
            'step_id' => count($fields) + 1,
            'type' => 'generate_report',
            'order' => count($fields) + 1,
        ];
        
        return [
            'workflow_type' => 'report_generator',
            'steps' => $workflowSteps,
            'total_steps' => count($workflowSteps),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get basic structure if AI analysis fails
     *
     * @param string $text
     * @return array
     */
    protected function getBasicStructure(string $text): array
    {
        $fields = $this->identifyFields($text, ['sections' => [], 'headings' => [], 'tables' => []]);
        $questions = $this->generateBasicQuestions($fields);
        $workflowConfig = $this->createWorkflowConfig($fields, $questions);
        
        return [
            'structure' => ['sections' => [], 'headings' => [], 'tables' => []],
            'fields' => $fields,
            'questions' => $questions,
            'workflow_config' => $workflowConfig,
        ];
    }

    /**
     * Normalize field key
     *
     * @param string $fieldName
     * @return string
     */
    protected function normalizeFieldKey(string $fieldName): string
    {
        // Convert to snake_case
        $key = strtolower($fieldName);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        
        return $key ?: 'field_' . uniqid();
    }

    /**
     * Normalize field label
     *
     * @param string $fieldName
     * @return string
     */
    protected function normalizeFieldLabel(string $fieldName): string
    {
        // Convert snake_case to Title Case
        $label = str_replace('_', ' ', $fieldName);
        $label = ucwords($label);
        
        return $label;
    }

    /**
     * Detect field type
     *
     * @param string $fieldName
     * @return string
     */
    protected function detectFieldType(string $fieldName): string
    {
        $fieldName = strtolower($fieldName);
        
        if (preg_match('/\b(?:thời gian|time|date|ngày|tháng|năm)\b/i', $fieldName)) {
            return 'date';
        } elseif (preg_match('/\b(?:số|number|amount|ngân sách|budget)\b/i', $fieldName)) {
            return 'number';
        } elseif (preg_match('/\b(?:email|email_address)\b/i', $fieldName)) {
            return 'email';
        } elseif (preg_match('/\b(?:địa chỉ|address)\b/i', $fieldName)) {
            return 'address';
        }
        
        return 'text';
    }
}



