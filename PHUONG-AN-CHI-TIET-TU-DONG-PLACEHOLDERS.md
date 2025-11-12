# 🔬 PHƯƠNG ÁN CHI TIẾT: Tự Động Tạo Placeholders Từ Template

**Dựa trên:** Codebase hiện có (`SmartDocxReplacer`, `DocumentProcessor`)

---

## 🎯 MỤC TIÊU

Tự động phát hiện và tạo placeholders từ template DOCX bất kỳ mà admin upload, KHÔNG cần admin can thiệp.

---

## 📊 PHƯƠNG ÁN CHI TIẾT

### Luồng Xử Lý Tổng Quan

```
Admin Upload Template DOCX
   ↓
1. Extract text từ DOCX (dùng DocumentProcessor)
   ↓
2. Check xem có placeholders không (dùng TemplateProcessor)
   ↓
3a. Nếu CÓ placeholders → Extract và lưu (existing logic)
   ↓
3b. Nếu KHÔNG có placeholders:
   ↓
   3b.1. Extract text và structure từ DOCX
   ↓
   3b.2. Sử dụng AI để phân tích và tạo placeholders
   ↓
   3b.3. Modify DOCX file để thêm placeholders (dùng SmartDocxReplacer logic)
   ↓
   3b.4. Replace original template với modified version
   ↓
4. Extract placeholders từ DOCX (modified hoặc original)
   ↓
5. Lưu vào database
```

---

## 🔧 IMPLEMENTATION

### Service Mới: `TemplatePlaceholderGenerator`

**File:** `app/Services/TemplatePlaceholderGenerator.php`

```php
<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use ZipArchive;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

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
        Log::info('🔵 [TemplatePlaceholderGenerator] Starting placeholder generation', [
            'template_path' => $templatePath,
        ]);
        
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
        
        Log::info('✅ [TemplatePlaceholderGenerator] Generated placeholders successfully', [
            'template_path' => $templatePath,
            'placeholders_count' => count($generatedPlaceholders),
            'placeholders' => array_keys($generatedPlaceholders),
        ]);
        
        return $generatedPlaceholders;
    }
    
    /**
     * Extract existing placeholders from template
     */
    protected function extractExistingPlaceholders(string $templatePath): array
    {
        try {
            $templateProcessor = new TemplateProcessor($templatePath);
            $variables = $templateProcessor->getVariables();
            
            $placeholders = [];
            foreach ($variables as $variable) {
                $placeholders[$variable] = $variable;
            }
            
            return $placeholders;
        } catch (\Exception $e) {
            Log::warning('⚠️ [TemplatePlaceholderGenerator] Failed to extract placeholders', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Analyze template structure
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
            
            // Parse AI response
            $mappings = [];
            if (isset($result['placeholders']) && is_array($result['placeholders'])) {
                foreach ($result['placeholders'] as $item) {
                    if (isset($item['original_text']) && isset($item['placeholder_key'])) {
                        $mappings[$item['original_text']] = $item['placeholder_key'];
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
     */
    protected function buildAIPrompt(string $text): string
    {
        $prompt = "Phân tích template văn bản hành chính Việt Nam sau và nhận diện các vị trí cần điền:\n\n";
        $prompt .= "--- NỘI DUNG TEMPLATE ---\n";
        $prompt .= $text . "\n";
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
        
        return $prompt;
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
            // Parse XML
            $dom = new DOMDocument('1.0', 'UTF-8');
            
            // Suppress warnings for malformed XML
            $prevErrorSetting = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrorSetting);
            
            if (!$loaded) {
                Log::warning('⚠️ [TemplatePlaceholderGenerator] Failed to parse XML, using simple replace');
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
            
            // Find and replace text with placeholders
            foreach ($mappings as $originalText => $placeholderKey) {
                $placeholder = '${' . $placeholderKey . '}';
                $position = strpos($fullText, $originalText);
                
                if ($position !== false) {
                    // Find nodes that contain this text
                    $this->replaceTextInNodes($nodeMap, $position, strlen($originalText), $placeholder);
                }
            }
            
            // Return modified XML
            return $dom->saveXML();
            
        } catch (\Exception $e) {
            Log::error('❌ [TemplatePlaceholderGenerator] Failed to add placeholders to XML', [
                'error' => $e->getMessage(),
            ]);
            return $this->simpleReplaceInXml($xml, $mappings);
        }
    }
    
    /**
     * Simple text replacement in XML (fallback)
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
     */
    protected function getModifiedPath(string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_modified.' . $pathInfo['extension'];
    }
}
```

---

### Integration Vào AdminController

**File:** `app/Http/Controllers/AdminController.php`

**Modify method `processDocumentTemplates()`:**

```php
protected function processDocumentTemplates(array $files, AiAssistant $assistant)
{
    // Inject TemplatePlaceholderGenerator
    $placeholderGenerator = app(\App\Services\TemplatePlaceholderGenerator::class);
    
    foreach ($files as $file) {
        try {
            // ... existing code (convert .doc to .docx, store file, etc.) ...
            
            // Store file
            $path = $file->store('document-templates', 'public');
            $url = Storage::disk('public')->url($path);
            $fullPath = Storage::disk('public')->path($path);
            
            // Extract file name and detect document type
            $documentType = $this->detectDocumentTypeFromFileName($fileName);
            $templateSubtype = $this->detectTemplateSubtypeFromFileName($fileName);
            $templateName = $this->generateTemplateName($documentType, $templateSubtype);
            
            // ✅ NEW: Auto-generate placeholders if not exists
            $metadata = [];
            $finalExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($finalExtension === 'docx') {
                try {
                    // Try to generate placeholders
                    $placeholders = $placeholderGenerator->generatePlaceholders($fullPath);
                    
                    if (!empty($placeholders)) {
                        $metadata['placeholders'] = array_keys($placeholders);
                        $metadata['placeholders_auto_generated'] = true;
                        
                        Log::info('✅ [AdminController] Auto-generated placeholders', [
                            'file_name' => $fileName,
                            'placeholders_count' => count($placeholders),
                            'placeholders' => array_keys($placeholders),
                        ]);
                    } else {
                        Log::warning('⚠️ [AdminController] Failed to generate placeholders', [
                            'file_name' => $fileName,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('❌ [AdminController] Error generating placeholders', [
                        'file_name' => $fileName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Create document template record
            \App\Models\DocumentTemplate::create([
                'ai_assistant_id' => $assistant->id,
                'document_type' => $documentType,
                'template_subtype' => $templateSubtype,
                'name' => $templateName,
                'file_name' => $fileName,
                'file_path' => $url,
                'file_type' => $finalExtension,
                'file_size' => $file->getSize(),
                'metadata' => $metadata,
                'is_active' => true,
            ]);
            
            // ... rest of code ...
            
        } catch (\Exception $e) {
            Log::error('Process document template error', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'assistant_id' => $assistant->id,
            ]);
        }
    }
}
```

---

## 🧪 TEST PLAN

### Test Case 1: Template Có Placeholders

**Input:** Template với `${so_van_ban}`, `${ngay_thang}`
**Expected:** Extract và return placeholders hiện có, KHÔNG modify file

### Test Case 2: Template Không Có Placeholders - Simple

**Input:** Template với "Số: ...", "Ngày: ..."
**Expected:** 
- AI nhận diện và tạo placeholders
- Modify DOCX file
- Extract placeholders từ modified file

### Test Case 3: Template Không Có Placeholders - Complex

**Input:** Template phức tạp với nhiều vị trí cần điền
**Expected:** AI nhận diện đúng và tạo placeholders phù hợp

### Test Case 4: Template Mixed

**Input:** Template có một số placeholders, một số text tĩnh
**Expected:** Giữ placeholders hiện có, tạo thêm cho text tĩnh

---

## 📊 METRICS & SUCCESS CRITERIA

- ✅ 90%+ templates được xử lý thành công
- ✅ 95%+ placeholders được tạo đúng
- ✅ Format preservation 95%+
- ✅ Processing time < 15s per template (bao gồm AI call)

---

## 🚀 NEXT STEPS

1. **Implement:** Tạo `TemplatePlaceholderGenerator` service
2. **Test:** Test với 10+ templates khác nhau
3. **Refine:** Refine AI prompt dựa trên test results
4. **Integrate:** Integrate vào `AdminController`
5. **Deploy:** Deploy và monitor

---

## ⚠️ LƯU Ý

- Cần xử lý edge cases (template corrupt, format lạ, etc.)
- Cần add validation và error handling
- Cần logging chi tiết để debug
- Cần consider performance với large templates
- Cần backup original template trước khi modify



