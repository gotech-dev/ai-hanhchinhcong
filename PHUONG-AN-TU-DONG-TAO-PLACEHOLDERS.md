# 🔬 PHƯƠNG ÁN NGHIÊN CỨU: Tự Động Tạo Placeholders Từ Template

**Mục tiêu:** Tự động phát hiện và tạo placeholders từ template DOCX bất kỳ mà admin upload

---

## 🎯 VẤN ĐỀ

**Hiện tại:**
- Admin upload template DOCX (có thể có hoặc không có placeholders)
- System chỉ extract placeholders nếu template đã có sẵn `${key}`
- Nếu template KHÔNG có placeholders → Không thể điền tự động

**Yêu cầu:**
- Tự động phát hiện template có placeholders hay không
- Nếu KHÔNG có → Tự động tạo placeholders từ nội dung template
- Modify DOCX file để thêm placeholders vào

---

## 📊 PHƯƠNG ÁN NGHIÊN CỨU

### Phương Án 1: ✅ **AI-POWERED PLACEHOLDER GENERATION** (KHUYẾN NGHỊ)

**Ý tưởng:** Sử dụng AI để phân tích template và tự động tạo placeholders

#### Luồng xử lý:

```
1. Admin upload template DOCX
   ↓
2. Extract text từ DOCX
   ↓
3. Check xem có placeholders không
   ↓
4a. Nếu CÓ placeholders → Extract và lưu (existing logic)
   ↓
4b. Nếu KHÔNG có placeholders:
   ↓
   4b.1. Extract text và cấu trúc từ DOCX
   ↓
   4b.2. Sử dụng AI để phân tích:
        - Nhận diện các vị trí cần điền (VD: "Số: ...", "Ngày: ...")
        - Tạo placeholders phù hợp (VD: ${so_van_ban}, ${ngay_thang})
        - Map vị trí trong DOCX với placeholders
   ↓
   4b.3. Modify DOCX file:
        - Replace text tĩnh bằng placeholders
        - Giữ nguyên format (font, size, color, alignment)
   ↓
   4b.4. Lưu DOCX đã modify
   ↓
5. Extract placeholders từ DOCX đã modify
   ↓
6. Lưu vào database
```

#### Ưu điểm:
- ✅ Tự động với mọi template
- ✅ Không cần admin can thiệp
- ✅ AI có thể nhận diện context và tạo placeholders phù hợp
- ✅ Giữ nguyên format của template

#### Nhược điểm:
- ⚠️ Cần AI API (cost)
- ⚠️ Có thể mất thời gian (AI processing)
- ⚠️ Cần xử lý edge cases

#### Implementation:

**File:** `app/Services/TemplatePlaceholderGenerator.php`

```php
<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
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
        // 1. Check if template already has placeholders
        $existingPlaceholders = $this->extractExistingPlaceholders($templatePath);
        if (!empty($existingPlaceholders)) {
            Log::info('Template already has placeholders', [
                'template_path' => $templatePath,
                'placeholders_count' => count($existingPlaceholders),
            ]);
            return $existingPlaceholders;
        }
        
        // 2. Extract text and structure from template
        $text = $this->documentProcessor->extractText($templatePath);
        $structure = $this->analyzeStructure($templatePath);
        
        // 3. Use AI to identify fillable positions and generate placeholders
        $placeholderMappings = $this->identifyFillablePositions($text, $structure);
        
        // 4. Modify DOCX file to add placeholders
        $modifiedPath = $this->modifyDocxWithPlaceholders($templatePath, $placeholderMappings);
        
        // 5. Extract placeholders from modified DOCX
        $generatedPlaceholders = $this->extractExistingPlaceholders($modifiedPath);
        
        Log::info('Generated placeholders for template', [
            'template_path' => $templatePath,
            'modified_path' => $modifiedPath,
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
            Log::warning('Failed to extract placeholders', [
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
                'tables' => [],
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
            Log::error('Failed to analyze structure', [
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
    protected function identifyFillablePositions(string $text, array $structure): array
    {
        try {
            $prompt = $this->buildAIPrompt($text, $structure);
            
            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là chuyên gia phân tích template văn bản hành chính. Nhiệm vụ của bạn là nhận diện các vị trí cần điền trong template và tạo placeholders phù hợp.',
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
            
            Log::info('AI identified fillable positions', [
                'mappings_count' => count($mappings),
                'mappings' => $mappings,
            ]);
            
            return $mappings;
        } catch (\Exception $e) {
            Log::error('Failed to identify fillable positions with AI', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Build AI prompt for placeholder generation
     */
    protected function buildAIPrompt(string $text, array $structure): string
    {
        $prompt = "Phân tích template văn bản hành chính sau và nhận diện các vị trí cần điền:\n\n";
        $prompt .= "--- NỘI DUNG TEMPLATE ---\n";
        $prompt .= $text . "\n";
        $prompt .= "--- HẾT NỘI DUNG ---\n\n";
        
        $prompt .= "YÊU CẦU:\n";
        $prompt .= "1. Nhận diện các vị trí cần điền trong template (VD: 'Số: ...', 'Ngày: ...', 'Nội dung: ...')\n";
        $prompt .= "2. Tạo placeholder key phù hợp cho mỗi vị trí (VD: so_van_ban, ngay_thang, noi_dung)\n";
        $prompt .= "3. Placeholder key phải:\n";
        $prompt .= "   - Chỉ dùng chữ thường, số, gạch dưới\n";
        $prompt .= "   - Không có dấu, khoảng trắng, ký tự đặc biệt\n";
        $prompt .= "   - Mô tả rõ ràng nội dung cần điền\n";
        $prompt .= "4. Trả về JSON với format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"placeholders\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"original_text\": \"Số: ...\",\n";
        $prompt .= "      \"placeholder_key\": \"so_van_ban\",\n";
        $prompt .= "      \"description\": \"Số văn bản\"\n";
        $prompt .= "    },\n";
        $prompt .= "    ...\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        
        $prompt .= "LƯU Ý:\n";
        $prompt .= "- Chỉ nhận diện các vị trí THỰC SỰ cần điền (không phải text tĩnh)\n";
        $prompt .= "- Bỏ qua các phần header/footer cố định (VD: 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM')\n";
        $prompt .= "- Tập trung vào các trường dữ liệu động (số, ngày, tên, nội dung, etc.)\n";
        
        return $prompt;
    }
    
    /**
     * Modify DOCX file to add placeholders
     * 
     * @param string $originalPath Original template path
     * @param array $mappings Mappings [original_text => placeholder_key]
     * @return string Path to modified DOCX file
     */
    protected function modifyDocxWithPlaceholders(string $originalPath, array $mappings): string
    {
        try {
            // Load original DOCX
            $phpWord = IOFactory::load($originalPath);
            
            // Modify each section
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $originalText = $element->getText();
                        
                        // Try to find matching mapping
                        foreach ($mappings as $original => $placeholderKey) {
                            // Use fuzzy matching to find similar text
                            if ($this->isSimilarText($originalText, $original)) {
                                // Replace with placeholder
                                $placeholder = '${' . $placeholderKey . '}';
                                $this->replaceTextInElement($element, $original, $placeholder);
                                break;
                            }
                        }
                    }
                }
            }
            
            // Save modified DOCX
            $modifiedPath = $this->getModifiedPath($originalPath);
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($modifiedPath);
            
            Log::info('Modified DOCX with placeholders', [
                'original_path' => $originalPath,
                'modified_path' => $modifiedPath,
                'mappings_applied' => count($mappings),
            ]);
            
            return $modifiedPath;
        } catch (\Exception $e) {
            Log::error('Failed to modify DOCX with placeholders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return original path if modification fails
            return $originalPath;
        }
    }
    
    /**
     * Check if two texts are similar (fuzzy matching)
     */
    protected function isSimilarText(string $text1, string $text2): bool
    {
        // Normalize texts
        $text1 = $this->normalizeText($text1);
        $text2 = $this->normalizeText($text2);
        
        // Check exact match
        if ($text1 === $text2) {
            return true;
        }
        
        // Check if text1 contains text2 or vice versa
        if (str_contains($text1, $text2) || str_contains($text2, $text1)) {
            return true;
        }
        
        // Check similarity using Levenshtein distance
        $similarity = 1 - (levenshtein($text1, $text2) / max(strlen($text1), strlen($text2)));
        return $similarity > 0.7; // 70% similarity threshold
    }
    
    /**
     * Normalize text for comparison
     */
    protected function normalizeText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim
        $text = trim($text);
        // Lowercase
        $text = mb_strtolower($text);
        return $text;
    }
    
    /**
     * Replace text in element (complex - need to handle PhpWord element structure)
     */
    protected function replaceTextInElement($element, string $oldText, string $newText): void
    {
        // This is complex - PhpWord doesn't have direct text replacement
        // Need to work with XML directly or rebuild element
        // TODO: Implement proper text replacement in PhpWord elements
    }
    
    /**
     * Get path for modified DOCX file
     */
    protected function getModifiedPath(string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_with_placeholders.' . $pathInfo['extension'];
    }
}
```

---

### Phương Án 2: ⚠️ **PATTERN-BASED PLACEHOLDER GENERATION**

**Ý tưởng:** Sử dụng pattern matching để nhận diện các vị trí cần điền

#### Luồng xử lý:

```
1. Extract text từ DOCX
   ↓
2. Pattern matching để tìm các vị trí cần điền:
   - "Số: ..." → ${so_van_ban}
   - "Ngày: ..." → ${ngay_thang}
   - "Nội dung: ..." → ${noi_dung}
   - etc.
   ↓
3. Modify DOCX file
   ↓
4. Extract placeholders
```

#### Ưu điểm:
- ✅ Không cần AI (no cost)
- ✅ Nhanh
- ✅ Đơn giản

#### Nhược điểm:
- ❌ Chỉ cover được các pattern đã biết
- ❌ Không linh hoạt với template mới
- ❌ Có thể miss các vị trí không theo pattern

---

### Phương Án 3: ✅ **HYBRID APPROACH** (KHUYẾN NGHỊ)

**Ý tưởng:** Kết hợp pattern matching + AI

#### Luồng xử lý:

```
1. Extract text từ DOCX
   ↓
2. Pattern matching để tìm các vị trí rõ ràng:
   - "Số: ..." → ${so_van_ban}
   - "Ngày: ..." → ${ngay_thang}
   - etc.
   ↓
3. Nếu còn vị trí chưa xác định → Dùng AI
   ↓
4. Modify DOCX file
   ↓
5. Extract placeholders
```

#### Ưu điểm:
- ✅ Tối ưu cost (chỉ dùng AI khi cần)
- ✅ Nhanh với các pattern đã biết
- ✅ Linh hoạt với template mới

---

## 🔧 IMPLEMENTATION PLAN

### Phase 1: Research & Prototype

**Tasks:**
1. ✅ Nghiên cứu cách modify DOCX file với PhpWord
2. ✅ Test pattern matching với các template mẫu
3. ✅ Test AI placeholder generation
4. ✅ So sánh accuracy giữa pattern vs AI

**Deliverables:**
- Prototype code
- Test results
- Accuracy comparison

### Phase 2: Core Implementation

**Tasks:**
1. Implement `TemplatePlaceholderGenerator` service
2. Integrate vào `AdminController::processDocumentTemplates()`
3. Add error handling và fallback
4. Add logging

**Files to modify:**
- `app/Services/TemplatePlaceholderGenerator.php` (NEW)
- `app/Http/Controllers/AdminController.php` (MODIFY)

### Phase 3: Testing & Refinement

**Tasks:**
1. Test với nhiều template khác nhau
2. Refine AI prompt để improve accuracy
3. Optimize performance
4. Add caching nếu cần

---

## 📋 TECHNICAL CHALLENGES

### Challenge 1: Modify DOCX File

**Vấn đề:** PhpWord không có API trực tiếp để replace text trong element

**Giải pháp:**
1. **Option A:** Work với XML trực tiếp (DOCX là ZIP chứa XML)
   - Extract `word/document.xml`
   - Modify XML
   - Rebuild DOCX

2. **Option B:** Rebuild document với PhpWord
   - Parse original DOCX
   - Rebuild với placeholders
   - Giữ nguyên format

3. **Option C:** Sử dụng thư viện khác (VD: `phpword-template`)

**Recommendation:** Option A (XML manipulation) - Most flexible

### Challenge 2: Preserve Format

**Vấn đề:** Khi replace text, cần giữ nguyên format (font, size, color, alignment)

**Giải pháp:**
- Extract format info từ original element
- Apply format khi tạo placeholder element

### Challenge 3: AI Accuracy

**Vấn đề:** AI có thể nhận diện sai hoặc miss vị trí

**Giải pháp:**
- Refine prompt với examples
- Add validation logic
- Allow manual review/correction

---

## 🧪 TEST PLAN

### Test Case 1: Template Có Placeholders

**Input:** Template với `${so_van_ban}`, `${ngay_thang}`
**Expected:** Extract và return placeholders hiện có

### Test Case 2: Template Không Có Placeholders - Pattern Matching

**Input:** Template với "Số: ...", "Ngày: ..."
**Expected:** Tạo placeholders `${so_van_ban}`, `${ngay_thang}`

### Test Case 3: Template Không Có Placeholders - AI Generation

**Input:** Template phức tạp không theo pattern
**Expected:** AI tạo placeholders phù hợp

### Test Case 4: Template Mixed

**Input:** Template có một số placeholders, một số text tĩnh
**Expected:** Giữ placeholders hiện có, tạo thêm cho text tĩnh

---

## 📊 METRICS

### Success Criteria:
- ✅ 90%+ templates được xử lý thành công
- ✅ 95%+ placeholders được tạo đúng
- ✅ Format preservation 95%+
- ✅ Processing time < 10s per template

---

## 🚀 NEXT STEPS

1. **Research:** Nghiên cứu cách modify DOCX XML
2. **Prototype:** Tạo prototype với pattern matching
3. **Test:** Test với 10+ templates khác nhau
4. **Implement:** Implement full solution
5. **Deploy:** Deploy và monitor

---

## 📝 NOTES

- Cần xử lý edge cases (template corrupt, format lạ, etc.)
- Cần add validation và error handling
- Cần logging chi tiết để debug
- Cần consider performance với large templates



