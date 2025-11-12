<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use ZipArchive;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Template Placeholder Generator
 * 
 * Auto-generate placeholders from DOCX templates when they don't exist
 * Uses AI to identify fillable positions and XML manipulation to add placeholders
 */
class TemplatePlaceholderGenerator
{
    protected DocumentProcessor $documentProcessor;
    
    public function __construct(DocumentProcessor $documentProcessor)
    {
        $this->documentProcessor = $documentProcessor;
    }
    
    /**
     * Auto-generate placeholders for template if not exists
     * 
     * @param string $templatePath Path to template DOCX file
     * @return array Generated placeholders [placeholder => normalized_key]
     */
    public function generatePlaceholders(string $templatePath): array
    {
        $startTime = microtime(true);
        
        Log::info('🔵 [TemplatePlaceholderGenerator] Starting placeholder generation', [
            'template_path' => $templatePath,
        ]);
        
        try {
            // 1. Check if template already has placeholders
            $existingPlaceholders = $this->extractExistingPlaceholders($templatePath);
            if (!empty($existingPlaceholders)) {
                Log::info('✅ [TemplatePlaceholderGenerator] Template already has placeholders', [
                    'template_path' => $templatePath,
                    'placeholders_count' => count($existingPlaceholders),
                    'placeholders' => array_keys($existingPlaceholders),
                ]);
                return $existingPlaceholders;
            }
            
            Log::info('🔵 [TemplatePlaceholderGenerator] Template has no placeholders, generating...', [
                'template_path' => $templatePath,
            ]);
            
            // 2. Extract text and structure from template
            $text = $this->documentProcessor->extractText($templatePath);
            $structure = $this->analyzeStructure($templatePath);
            
            if (empty($text)) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] Template text is empty', [
                    'template_path' => $templatePath,
                ]);
                return [];
            }
            
            // 3. Use AI to identify fillable positions and generate placeholders
            $placeholderMappings = $this->identifyFillablePositionsWithAI($text, $structure);
            
            if (empty($placeholderMappings)) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] No fillable positions identified', [
                    'template_path' => $templatePath,
                ]);
                return [];
            }
            
            // 4. Modify DOCX file to add placeholders
            $modifiedPath = $this->modifyDocxWithPlaceholders($templatePath, $placeholderMappings);
            
            if ($modifiedPath === $templatePath) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] Failed to modify DOCX, returning empty', [
                    'template_path' => $templatePath,
                ]);
                return [];
            }
            
            // 5. Replace original file with modified version
            if (copy($modifiedPath, $templatePath)) {
                unlink($modifiedPath);
                Log::info('✅ [TemplatePlaceholderGenerator] Replaced original template with modified version');
            } else {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] Failed to replace original template', [
                    'modified_path' => $modifiedPath,
                    'original_path' => $templatePath,
                ]);
            }
            
            // 6. Extract placeholders from modified DOCX
            $generatedPlaceholders = $this->extractExistingPlaceholders($templatePath);
            
            $processingTime = round(microtime(true) - $startTime, 2);
            
            Log::info('✅ [TemplatePlaceholderGenerator] Generated placeholders successfully', [
                'template_path' => $templatePath,
                'placeholders_count' => count($generatedPlaceholders),
                'placeholders' => array_keys($generatedPlaceholders),
                'processing_time' => $processingTime . 's',
            ]);
            
            return $generatedPlaceholders;
            
        } catch (\Exception $e) {
            Log::error('❌ [TemplatePlaceholderGenerator] Failed to generate placeholders', [
                'error' => $e->getMessage(),
                'template_path' => $templatePath,
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
    
    /**
     * Extract existing placeholders from template
     * 
     * @param string $templatePath Path to template DOCX file
     * @return array Placeholders [placeholder => normalized_key]
     */
    public function extractExistingPlaceholders(string $templatePath): array
    {
        try {
            $templateProcessor = new TemplateProcessor($templatePath);
            $variables = $templateProcessor->getVariables();
            
            $placeholders = [];
            foreach ($variables as $variable) {
                // Normalize variable name (remove ${} wrapper if present)
                $normalized = preg_replace('/^\$\{?|\}?$/', '', $variable);
                $placeholders[$variable] = $normalized;
            }
            
            // Also try to extract from document XML directly for other formats
            $zip = new ZipArchive();
            if ($zip->open($templatePath) === true) {
                $documentXml = $zip->getFromName('word/document.xml');
                if ($documentXml) {
                    // Extract ${key} format placeholders
                    if (preg_match_all('/\$\{([^}]+)\}/', $documentXml, $matches)) {
                        foreach ($matches[1] as $match) {
                            $placeholder = '${' . trim($match) . '}';
                            $normalized = trim($match);
                            if (!isset($placeholders[$placeholder])) {
                                $placeholders[$placeholder] = $normalized;
                            }
                        }
                    }
                    
                    // Extract {{key}} format placeholders
                    if (preg_match_all('/\{\{([^}]+)\}\}/', $documentXml, $matches)) {
                        foreach ($matches[1] as $match) {
                            $placeholder = '{{' . trim($match) . '}}';
                            $normalized = trim($match);
                            if (!isset($placeholders[$placeholder])) {
                                $placeholders[$placeholder] = $normalized;
                            }
                        }
                    }
                }
                $zip->close();
            }
            
            return $placeholders;
        } catch (\Exception $e) {
            Log::warning('⚠️ [TemplatePlaceholderGenerator] Failed to extract placeholders', [
                'error' => $e->getMessage(),
                'template_path' => $templatePath,
            ]);
            return [];
        }
    }
    
    /**
     * Analyze template structure
     * 
     * @param string $templatePath Path to template DOCX file
     * @return array Structure information
     */
    protected function analyzeStructure(string $templatePath): array
    {
        try {
            $phpWord = IOFactory::load($templatePath);
            $structure = [
                'sections' => [],
                'paragraphs' => [],
            ];
            
            foreach ($phpWord->getSections() as $sectionIndex => $section) {
                $sectionData = [
                    'index' => $sectionIndex,
                    'elements' => [],
                ];
                
                foreach ($section->getElements() as $elementIndex => $element) {
                    $elementData = [
                        'index' => $elementIndex,
                        'type' => get_class($element),
                        'text' => method_exists($element, 'getText') ? $element->getText() : '',
                    ];
                    
                    $sectionData['elements'][] = $elementData;
                }
                
                $structure['sections'][] = $sectionData;
            }
            
            return $structure;
        } catch (\Exception $e) {
            Log::error('❌ [TemplatePlaceholderGenerator] Failed to analyze structure', [
                'error' => $e->getMessage(),
                'template_path' => $templatePath,
            ]);
            return [];
        }
    }
    
    /**
     * Use AI to identify fillable positions and generate placeholders
     * 
     * @param string $text Template text
     * @param array $structure Template structure
     * @return array Mappings [original_text => placeholder_key]
     */
    protected function identifyFillablePositionsWithAI(string $text, array $structure): array
    {
        try {
            $prompt = $this->buildAIPrompt($text);
            
            Log::info('🔵 [TemplatePlaceholderGenerator] Calling AI to identify fillable positions', [
                'text_length' => strlen($text),
                'text_preview' => substr($text, 0, 500),
            ]);
            
            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là chuyên gia phân tích template văn bản hành chính Việt Nam. Nhiệm vụ của bạn là nhận diện các vị trí cần điền trong template và tạo placeholders phù hợp.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);
            
            $result = json_decode($response->choices[0]->message->content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] Failed to parse AI response', [
                    'response' => $response->choices[0]->message->content,
                ]);
                return [];
            }
            
            // Parse AI response
            $mappings = [];
            if (isset($result['placeholders']) && is_array($result['placeholders'])) {
                foreach ($result['placeholders'] as $item) {
                    if (isset($item['original_text']) && isset($item['placeholder_key'])) {
                        $originalText = trim($item['original_text']);
                        $placeholderKey = $this->normalizePlaceholderKey($item['placeholder_key']);
                        
                        if (!empty($originalText) && !empty($placeholderKey)) {
                            $mappings[$originalText] = $placeholderKey;
                        }
                    }
                }
            }
            
            Log::info('✅ [TemplatePlaceholderGenerator] AI identified fillable positions', [
                'mappings_count' => count($mappings),
                'mappings' => $mappings,
            ]);
            
            return $mappings;
        } catch (\Exception $e) {
            Log::error('❌ [TemplatePlaceholderGenerator] Failed to identify fillable positions with AI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
    
    /**
     * Build AI prompt for placeholder generation
     * 
     * @param string $text Template text
     * @return string AI prompt
     */
    protected function buildAIPrompt(string $text): string
    {
        // Limit text length to avoid token limits (keep first 8000 chars)
        $textPreview = mb_substr($text, 0, 8000);
        if (strlen($text) > 8000) {
            $textPreview .= "\n\n[... nội dung còn lại đã được cắt bớt ...]";
        }
        
        $prompt = "Phân tích template văn bản hành chính Việt Nam sau và nhận diện các vị trí cần điền:\n\n";
        $prompt .= "--- NỘI DUNG TEMPLATE ---\n";
        $prompt .= $textPreview . "\n";
        $prompt .= "--- HẾT NỘI DUNG ---\n\n";
        
        $prompt .= "YÊU CẦU:\n";
        $prompt .= "1. Nhận diện các vị trí cần điền trong template\n";
        $prompt .= "   - Các trường có giá trị động (VD: 'Số: ...', 'Ngày: ...', 'Nội dung: ...')\n";
        $prompt .= "   - Các vị trí có dấu '...' hoặc text mẫu (VD: 'TÊN CƠ QUAN', 'ngày... tháng...')\n";
        $prompt .= "   - Các trường có thể thay đổi theo từng văn bản\n";
        $prompt .= "2. Tạo placeholder key phù hợp cho mỗi vị trí\n";
        $prompt .= "   - Placeholder key phải: chỉ dùng chữ thường, số, gạch dưới\n";
        $prompt .= "   - Không có dấu, khoảng trắng, ký tự đặc biệt\n";
        $prompt .= "   - Mô tả rõ ràng nội dung cần điền (VD: so_van_ban, ngay_thang, noi_dung)\n";
        $prompt .= "3. Bỏ qua các phần text tĩnh (VD: 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM')\n\n";
        
        $prompt .= "Trả về JSON với format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"placeholders\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"original_text\": \"Số: ...\",\n";
        $prompt .= "      \"placeholder_key\": \"so_van_ban\",\n";
        $prompt .= "      \"description\": \"Số văn bản\"\n";
        $prompt .= "    },\n";
        $prompt .= "    {\n";
        $prompt .= "      \"original_text\": \"Ngày: ...\",\n";
        $prompt .= "      \"placeholder_key\": \"ngay_thang\",\n";
        $prompt .= "      \"description\": \"Ngày tháng\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        
        $prompt .= "LƯU Ý QUAN TRỌNG:\n";
        $prompt .= "- Chỉ nhận diện các vị trí THỰC SỰ cần điền (không phải text tĩnh)\n";
        $prompt .= "- Placeholder key phải unique và mô tả rõ ràng\n";
        $prompt .= "- original_text phải chính xác như trong template (để replace)\n";
        $prompt .= "- Nếu có nhiều vị trí giống nhau, chỉ tạo một placeholder key\n";
        
        return $prompt;
    }
    
    /**
     * Normalize placeholder key (lowercase, underscore, no special chars)
     * 
     * @param string $key Placeholder key
     * @return string Normalized key
     */
    protected function normalizePlaceholderKey(string $key): string
    {
        // Remove special characters, keep only alphanumeric and spaces
        $key = preg_replace('/[^a-zA-Z0-9\s]/', '', $key);
        
        // Convert to lowercase
        $key = mb_strtolower($key);
        
        // Replace spaces and multiple underscores with single underscore
        $key = preg_replace('/[\s_]+/', '_', $key);
        
        // Remove leading/trailing underscores
        $key = trim($key, '_');
        
        // Remove Vietnamese accents (optional, but helps with consistency)
        $key = $this->removeVietnameseAccents($key);
        
        return $key;
    }
    
    /**
     * Remove Vietnamese accents
     * 
     * @param string $text Text with accents
     * @return string Text without accents
     */
    protected function removeVietnameseAccents(string $text): string
    {
        $accents = [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
            'À' => 'A', 'Á' => 'A', 'Ạ' => 'A', 'Ả' => 'A', 'Ã' => 'A',
            'Â' => 'A', 'Ầ' => 'A', 'Ấ' => 'A', 'Ậ' => 'A', 'Ẩ' => 'A', 'Ẫ' => 'A',
            'Ă' => 'A', 'Ằ' => 'A', 'Ắ' => 'A', 'Ặ' => 'A', 'Ẳ' => 'A', 'Ẵ' => 'A',
            'È' => 'E', 'É' => 'E', 'Ẹ' => 'E', 'Ẻ' => 'E', 'Ẽ' => 'E',
            'Ê' => 'E', 'Ề' => 'E', 'Ế' => 'E', 'Ệ' => 'E', 'Ể' => 'E', 'Ễ' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Ị' => 'I', 'Ỉ' => 'I', 'Ĩ' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ọ' => 'O', 'Ỏ' => 'O', 'Õ' => 'O',
            'Ô' => 'O', 'Ồ' => 'O', 'Ố' => 'O', 'Ộ' => 'O', 'Ổ' => 'O', 'Ỗ' => 'O',
            'Ơ' => 'O', 'Ờ' => 'O', 'Ớ' => 'O', 'Ợ' => 'O', 'Ở' => 'O', 'Ỡ' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Ụ' => 'U', 'Ủ' => 'U', 'Ũ' => 'U',
            'Ư' => 'U', 'Ừ' => 'U', 'Ứ' => 'U', 'Ự' => 'U', 'Ử' => 'U', 'Ữ' => 'U',
            'Ỳ' => 'Y', 'Ý' => 'Y', 'Ỵ' => 'Y', 'Ỷ' => 'Y', 'Ỹ' => 'Y',
            'Đ' => 'D',
        ];
        
        return strtr($text, $accents);
    }
    
    /**
     * Modify DOCX file to add placeholders using XML manipulation
     * 
     * @param string $originalPath Original template path
     * @param array $mappings Mappings [original_text => placeholder_key]
     * @return string Path to modified DOCX file
     */
    protected function modifyDocxWithPlaceholders(string $originalPath, array $mappings): string
    {
        try {
            // Create output path
            $modifiedPath = $this->getModifiedPath($originalPath);
            
            // Copy original file
            if (!copy($originalPath, $modifiedPath)) {
                throw new \Exception("Failed to copy template");
            }
            
            Log::info('🔵 [TemplatePlaceholderGenerator] Modifying DOCX with placeholders', [
                'original_path' => $originalPath,
                'modified_path' => $modifiedPath,
                'mappings_count' => count($mappings),
            ]);
            
            // Open as ZIP
            $zip = new ZipArchive();
            if ($zip->open($modifiedPath) !== true) {
                throw new \Exception("Failed to open DOCX as ZIP");
            }
            
            // Get document.xml
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                $zip->close();
                throw new \Exception("Failed to read document.xml from DOCX");
            }
            
            // Modify XML to add placeholders
            $newXml = $this->addPlaceholdersToXml($xml, $mappings);
            
            // Put back and close
            if (!$zip->addFromString('word/document.xml', $newXml)) {
                $zip->close();
                throw new \Exception("Failed to write document.xml back to DOCX");
            }
            
            $zip->close();
            
            Log::info('✅ [TemplatePlaceholderGenerator] Modified DOCX successfully', [
                'modified_path' => $modifiedPath,
                'original_size' => strlen($xml),
                'new_size' => strlen($newXml),
            ]);
            
            return $modifiedPath;
            
        } catch (\Exception $e) {
            Log::error('❌ [TemplatePlaceholderGenerator] Failed to modify DOCX with placeholders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return original path if modification fails
            return $originalPath;
        }
    }
    
    /**
     * Add placeholders to XML by replacing text
     * 
     * @param string $xml Document XML content
     * @param array $mappings Mappings [original_text => placeholder_key]
     * @return string Modified XML
     */
    protected function addPlaceholdersToXml(string $xml, array $mappings): string
    {
        try {
            // Try simple replacement first (for continuous text)
            $modified = false;
            $newXml = $xml;
            
            foreach ($mappings as $originalText => $placeholderKey) {
                $placeholder = '${' . $placeholderKey . '}';
                
                if (strpos($newXml, $originalText) !== false) {
                    $newXml = str_replace($originalText, $placeholder, $newXml);
                    $modified = true;
                    
                    Log::debug('TemplatePlaceholderGenerator: Simple replacement', [
                        'original' => substr($originalText, 0, 50),
                        'placeholder' => $placeholder,
                    ]);
                }
            }
            
            // If simple replacement worked for all, return
            if ($modified) {
                // Verify all replacements were made
                $allReplaced = true;
                foreach ($mappings as $originalText => $placeholderKey) {
                    if (strpos($newXml, $originalText) !== false) {
                        $allReplaced = false;
                        break;
                    }
                }
                
                if ($allReplaced) {
                    Log::info('TemplatePlaceholderGenerator: All replacements done with simple method');
                    return $newXml;
                }
            }
            
            // If simple replacement didn't work, use advanced method
            Log::info('TemplatePlaceholderGenerator: Using advanced replacement (handling split text)');
            return $this->advancedReplaceInXml($xml, $mappings);
            
        } catch (\Exception $e) {
            Log::error('❌ [TemplatePlaceholderGenerator] Failed to add placeholders to XML', [
                'error' => $e->getMessage(),
            ]);
            return $this->simpleReplaceInXml($xml, $mappings);
        }
    }
    
    /**
     * Advanced replacement - handles text split across XML nodes
     * 
     * @param string $xml Document XML content
     * @param array $mappings Mappings [original_text => placeholder_key]
     * @return string Modified XML
     */
    protected function advancedReplaceInXml(string $xml, array $mappings): string
    {
        try {
            // Parse XML
            $dom = new DOMDocument('1.0', 'UTF-8');
            
            // Suppress warnings for malformed XML
            $prevErrorSetting = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrorSetting);
            
            if (!$loaded) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] Failed to parse XML, falling back to simple replace');
                return $this->simpleReplaceInXml($xml, $mappings);
            }
            
            // Register namespace
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            
            // Get all text nodes
            $textNodes = $xpath->query('//w:t');
            if ($textNodes->length === 0) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] No text nodes found in XML');
                return $xml;
            }
            
            Log::debug('TemplatePlaceholderGenerator: Found text nodes', [
                'count' => $textNodes->length,
            ]);
            
            // Build full text and node mapping
            $fullText = '';
            $nodeMap = [];
            
            foreach ($textNodes as $node) {
                $text = $node->textContent;
                $nodeMap[] = [
                    'node' => $node,
                    'start' => strlen($fullText),
                    'length' => strlen($text),
                    'text' => $text,
                ];
                $fullText .= $text;
            }
            
            Log::debug('TemplatePlaceholderGenerator: Extracted full text', [
                'length' => strlen($fullText),
                'preview' => substr($fullText, 0, 200),
            ]);
            
            // Replace in full text
            $newFullText = $fullText;
            $replacedCount = 0;
            
            foreach ($mappings as $originalText => $placeholderKey) {
                $placeholder = '${' . $placeholderKey . '}';
                
                if (strpos($newFullText, $originalText) !== false) {
                    $newFullText = str_replace($originalText, $placeholder, $newFullText);
                    $replacedCount++;
                    
                    Log::debug('TemplatePlaceholderGenerator: Replaced in full text', [
                        'original' => substr($originalText, 0, 50),
                        'placeholder' => $placeholder,
                    ]);
                }
            }
            
            if ($replacedCount === 0) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] No replacements made in full text');
                return $xml;
            }
            
            // Put new text back to first node, clear others
            if ($newFullText !== $fullText && !empty($nodeMap)) {
                $nodeMap[0]['node']->textContent = $newFullText;
                
                // Clear other nodes
                for ($i = 1; $i < count($nodeMap); $i++) {
                    $nodeMap[$i]['node']->textContent = '';
                }
                
                Log::info('TemplatePlaceholderGenerator: Text distributed to nodes', [
                    'first_node_length' => strlen($newFullText),
                    'cleared_nodes' => count($nodeMap) - 1,
                ]);
            }
            
            // Save XML
            $newXml = $dom->saveXML();
            
            Log::info('TemplatePlaceholderGenerator: Advanced replacement completed', [
                'replaced_count' => $replacedCount,
                'original_length' => strlen($fullText),
                'new_length' => strlen($newFullText),
            ]);
            
            return $newXml;
            
        } catch (\Exception $e) {
            Log::error('❌ [TemplatePlaceholderGenerator] Advanced replacement failed', [
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to simple replace
            return $this->simpleReplaceInXml($xml, $mappings);
        }
    }
    
    /**
     * Simple text replacement in XML (fallback)
     * 
     * @param string $xml XML content
     * @param array $mappings Mappings [original_text => placeholder_key]
     * @return string Modified XML
     */
    protected function simpleReplaceInXml(string $xml, array $mappings): string
    {
        foreach ($mappings as $originalText => $placeholderKey) {
            $placeholder = '${' . $placeholderKey . '}';
            $xml = str_replace($originalText, $placeholder, $xml);
        }
        return $xml;
    }
    
    /**
     * Replace text in nodes (complex - handle split text across nodes)
     * 
     * @param array &$nodeMap Node map reference
     * @param int $position Position in full text
     * @param int $length Length of text to replace
     * @param string $replacement Replacement text
     * @return void
     */
    protected function replaceTextInNodes(array &$nodeMap, int $position, int $length, string $replacement): void
    {
        // Find nodes that contain the text to replace
        $endPosition = $position + $length;
        
        foreach ($nodeMap as $item) {
            $nodeStart = $item['start'];
            $nodeEnd = $nodeStart + $item['length'];
            
            // Check if this node is part of the text to replace
            if ($nodeStart < $endPosition && $nodeEnd > $position) {
                $node = $item['node'];
                $nodeText = $item['text'];
                
                // Calculate overlap
                $overlapStart = max($position, $nodeStart);
                $overlapEnd = min($endPosition, $nodeEnd);
                $overlapLength = $overlapEnd - $overlapStart;
                
                if ($overlapLength > 0) {
                    // Replace overlapping part
                    $nodeOffset = $overlapStart - $nodeStart;
                    $newText = substr($nodeText, 0, $nodeOffset) . 
                              $replacement . 
                              substr($nodeText, $nodeOffset + $overlapLength);
                    $node->nodeValue = $newText;
                }
            }
        }
    }
    
    /**
     * Get path for modified DOCX file
     * 
     * @param string $originalPath Original file path
     * @return string Modified file path
     */
    protected function getModifiedPath(string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        $filename = $pathInfo['filename'] . '_modified_' . uniqid() . '.' . $pathInfo['extension'];
        return $pathInfo['dirname'] . '/' . $filename;
    }
}



