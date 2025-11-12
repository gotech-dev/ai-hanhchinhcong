# Báo Cáo: Vấn Đề Hiển Thị Template Trên Chatbot

**Ngày:** 09/11/2025  
**Vấn đề:** Admin đã upload file DOCX khi tạo trợ lý, nhưng phía chatbot user vẫn chưa hiển thị được đúng format template trên HTML

---

## 1. TÓM TẮT VẤN ĐỀ

### Hiện Trạng
- ✅ Admin đã upload file template DOCX thành công
- ✅ Template được lưu vào database (`document_templates` table)
- ✅ Template được tìm thấy và extract thành công khi user yêu cầu tạo văn bản
- ✅ DOCX file được generate thành công từ template
- ❌ **VẤN ĐỀ CHÍNH:** HTML preview trên chatbot KHÔNG hiển thị đúng format từ template DOCX

### Nguyên Nhân Gốc Rễ

**VẤN ĐỀ 1: AI Generate Content Không Khớp Với Placeholders Trong Template**

Từ log file:
```
[2025-11-09 04:26:02] Template content extracted successfully {"template_id":15,"content_length":648}
[2025-11-09 04:26:02] Including template content in AI prompt {"template_content_length":648}
[2025-11-09 04:26:06] AI content generated {"ai_content_fields":["header","body","footer"]}
```

**Phân tích:**
1. Template content đã được extract và truyền vào AI prompt
2. **NHƯNG** AI trả về data dạng generic: `header`, `body`, `footer` (dạng array)
3. **KHÔNG phải** các placeholders cụ thể trong template DOCX

**Hệ quả:**
- Khi `TemplateProcessor::setValue()` được gọi, nó cố gắng replace placeholders trong DOCX
- Nhưng AI trả về `header`, `body`, `footer` thay vì các placeholders như `ten_co_quan`, `so_van_ban`, `noi_dung`, etc.
- Kết quả: **Placeholders trong DOCX không được replace đúng**
- File DOCX vẫn còn các placeholders chưa được điền
- HTML preview hiển thị placeholders thay vì content thực tế

---

## 2. PHÂN TÍCH CHI TIẾT

### 2.1. Luồng Xử Lý Hiện Tại

```
1. User yêu cầu: "Tạo 1 mẫu Biên bản"
   ↓
2. DocumentDraftingService::draftDocument()
   - Tìm template từ DB ✅
   - Extract template content ✅
   - Extract template structure (placeholders) ❌ EMPTY
   ↓
3. DocumentDraftingService::generateContentWithAI()
   - Build prompt với template content ✅
   - AI generate content → Trả về: {"header": [...], "body": [...], "footer": [...]} ❌
   ↓
4. DocumentDraftingService::generateDocxFromTemplate()
   - Load template DOCX ✅
   - Get placeholders từ template ✅
   - Map documentData → placeholders ❌ KHÔNG KHỚP
   - Replace placeholders ❌ KHÔNG REPLACE ĐƯỢC
   ↓
5. DocumentController::previewHtml()
   - Load DOCX file ✅
   - Convert DOCX → HTML bằng Pandoc ✅
   - Return HTML ❌ CHỨA PLACEHOLDERS CHƯA ĐƯỢC THAY THẾ
```

### 2.2. Vấn Đề Với AI Response Format

**File:** `app/Services/DocumentDraftingService.php`
**Method:** `generateContentWithAI()` (line 601-681)

**Vấn đề:**
1. AI được yêu cầu trả về JSON với structure generic:
   ```json
   {
     "header": ["...", "..."],
     "body": ["...", "..."],
     "footer": ["..."]
   }
   ```

2. **NHƯNG** template DOCX có placeholders cụ thể như:
   - `${ten_co_quan}`
   - `${so_van_ban}`
   - `${ngay_thang}`
   - `${noi_dung}`
   - etc.

3. **Kết quả:** AI response KHÔNG khớp với placeholders trong template

### 2.3. Vấn Đề Với Template Structure Extraction

**File:** `app/Services/DocumentDraftingService.php`
**Method:** `extractTemplateStructure()` (line 407-492)

**Log cho thấy:**
```
"has_structure": false,
"structure_keys": []
```

**Nguyên nhân:**
- Hàm `extractTemplateStructure()` cố gắng extract placeholders từ template
- **NHƯNG** trả về empty structure
- Có thể do:
  1. Template không có placeholders dạng `${key}` hoặc `{{key}}`
  2. Hoặc extraction logic không detect được placeholders trong template DOCX này

### 2.4. Vấn Đề Với Placeholder Mapping

**File:** `app/Services/DocumentDraftingService.php`
**Method:** `mapDataToPlaceholders()` (line 373-399)

**Code:**
```php
foreach ($placeholders as $placeholder) {
    $cleanKey = preg_replace('/[\[\]{}${}]/', '', $placeholder);
    
    // Try to find matching data
    if (isset($documentData[$cleanKey])) {
        $mapped[$placeholder] = $documentData[$cleanKey];
    }
}
```

**Vấn đề:**
- Hàm này map `documentData` (có keys như `header`, `body`, `footer`) 
- Sang `placeholders` (như `ten_co_quan`, `so_van_ban`, `noi_dung`)
- **KHÔNG KHỚP** → Không replace được

---

## 3. GIẢI PHÁP ĐỀ XUẤT

### Giải Pháp 1: ✅ **SỬA AI PROMPT ĐỂ TRẢ VỀ ĐÚNG PLACEHOLDERS**

**Ưu điểm:**
- Fix được nguyên nhân gốc rễ
- AI sẽ trả về data khớp với template placeholders
- Không cần thay đổi logic TemplateProcessor

**Cách sửa:**

#### Bước 1: Cải thiện `extractTemplateStructure()` để extract đúng placeholders

**File:** `app/Services/DocumentDraftingService.php`
**Method:** `extractTemplateStructure()`

```php
protected function extractTemplateStructure(DocumentTemplate $template): array
{
    try {
        // 1. Lấy placeholders từ metadata nếu có
        $placeholders = $template->metadata['placeholders'] ?? [];
        
        // 2. Nếu không có, extract từ DOCX file
        if (empty($placeholders)) {
            $templatePath = $this->getTemplatePath($template->file_path);
            if (file_exists($templatePath) && strtolower($template->file_type) === 'docx') {
                $templateProcessor = new TemplateProcessor($templatePath);
                $placeholders = $templateProcessor->getVariables();
                
                // ✅ LOG placeholders được extract
                Log::info('🔵 [DocumentDrafting] Extracted placeholders from DOCX', [
                    'template_id' => $template->id,
                    'placeholders' => $placeholders,
                    'count' => count($placeholders),
                ]);
            }
        }
        
        // 3. Build structure từ placeholders
        // Trả về array với keys là placeholders
        $structure = [];
        foreach ($placeholders as $placeholder) {
            $cleanKey = preg_replace('/[\[\]{}$]/', '', $placeholder);
            $cleanKey = trim($cleanKey);
            $structure[$cleanKey] = ''; // Empty value, AI sẽ điền
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
```

#### Bước 2: Sửa `buildPrompt()` để yêu cầu AI trả về đúng placeholders

**File:** `app/Services/DocumentDraftingService.php`
**Method:** `buildPrompt()`

```php
protected function buildPrompt(
    string $userRequest,
    DocumentType $documentType,
    array $collectedData,
    array $autoFilledData,
    array $templateStructure,
    ?string $templateContent = null
): string {
    $prompt = "Bạn là chuyên gia soạn thảo văn bản hành chính Việt Nam theo Nghị định 30/2020/NĐ-CP.\n\n";
    $prompt .= "Yêu cầu: {$userRequest}\n\n";
    $prompt .= "Loại văn bản: {$documentType->displayName()}\n\n";
    
    // ✅ MỚI: Include template content nếu có
    if ($templateContent) {
        $prompt .= "**QUAN TRỌNG:** Bạn PHẢI tạo văn bản theo đúng mẫu template sau:\n\n";
        $prompt .= "--- MẪU TEMPLATE ---\n";
        $prompt .= $templateContent . "\n";
        $prompt .= "--- HẾT MẪU TEMPLATE ---\n\n";
    }
    
    $prompt .= "Thông tin đã có:\n";
    $prompt .= json_encode($autoFilledData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // ✅ MỚI: Nếu có template structure (placeholders), yêu cầu AI trả về ĐÚNG keys
    if (!empty($templateStructure)) {
        $prompt .= "**CÁC TRƯỜNG DỮ LIỆU CẦN ĐIỀN (PLACEHOLDERS):**\n";
        $prompt .= json_encode(array_keys($templateStructure), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $prompt .= "**YÊU CẦU QUAN TRỌNG:**\n";
        $prompt .= "- Bạn PHẢI trả về JSON với ĐÚNG các keys trong danh sách placeholders trên\n";
        $prompt .= "- KHÔNG được tạo thêm keys khác như 'header', 'body', 'footer'\n";
        $prompt .= "- Mỗi key phải có giá trị là string (không phải array)\n";
        $prompt .= "- Giá trị phải phù hợp với nội dung template mẫu\n\n";
        
        $prompt .= "Ví dụ format JSON trả về:\n";
        $exampleKeys = array_slice(array_keys($templateStructure), 0, 5);
        $example = [];
        foreach ($exampleKeys as $key) {
            $example[$key] = "[Giá trị cho {$key}]";
        }
        $prompt .= json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        // Fallback: Dùng generic structure
        $prompt .= "Cấu trúc văn bản cần tạo:\n";
        $prompt .= json_encode($templateStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
    
    $prompt .= "Hãy tạo nội dung văn bản hành chính với:\n";
    $prompt .= "- Văn phong trang trọng, khách quan\n";
    $prompt .= "- Tuân thủ quy định Nghị định 30/2020/NĐ-CP\n";
    
    if ($templateContent) {
        $prompt .= "- **TUÂN THỦ NGHIÊM NGẶT** cấu trúc và format của template mẫu\n";
    }
    
    if (!empty($templateStructure)) {
        $prompt .= "- **PHẢI trả về JSON với ĐÚNG keys như đã nêu ở trên**\n";
    }
    
    return $prompt;
}
```

#### Bước 3: Cải thiện `mapDataToPlaceholders()` để handle cả generic và specific keys

**File:** `app/Services/DocumentDraftingService.php`
**Method:** `mapDataToPlaceholders()`

```php
protected function mapDataToPlaceholders(array $documentData, array $placeholders): array
{
    $mapped = [];
    
    // ✅ LOG: Input data và placeholders
    Log::info('🔵 [DocumentDrafting] Mapping data to placeholders', [
        'document_data_keys' => array_keys($documentData),
        'placeholders' => $placeholders,
        'placeholders_count' => count($placeholders),
    ]);
    
    foreach ($placeholders as $placeholder) {
        // Remove {{ }} or ${ } or [ ] from placeholder
        $cleanKey = preg_replace('/[\[\]{}$]/', '', $placeholder);
        $cleanKey = trim($cleanKey);
        
        $value = null;
        
        // 1. Try exact match
        if (isset($documentData[$cleanKey])) {
            $value = $documentData[$cleanKey];
        } 
        // 2. Try with placeholder format
        elseif (isset($documentData[$placeholder])) {
            $value = $documentData[$placeholder];
        } 
        // 3. Try case-insensitive match
        else {
            foreach ($documentData as $key => $val) {
                if (strtolower($key) === strtolower($cleanKey)) {
                    $value = $val;
                    break;
                }
            }
        }
        
        // ✅ Handle array values (từ AI response cũ có thể trả về array)
        if (is_array($value)) {
            // Convert array to string
            if (isset($value[0]) && is_string($value[0])) {
                $value = implode("\n", $value);
            } else {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        
        // Only map if value is not null
        if ($value !== null) {
            $mapped[$placeholder] = $value;
            
            Log::debug('✅ Mapped placeholder', [
                'placeholder' => $placeholder,
                'clean_key' => $cleanKey,
                'value_preview' => is_string($value) ? substr($value, 0, 100) : gettype($value),
            ]);
        } else {
            Log::warning('⚠️ No value found for placeholder', [
                'placeholder' => $placeholder,
                'clean_key' => $cleanKey,
                'available_keys' => array_keys($documentData),
            ]);
        }
    }
    
    // ✅ LOG: Mapping result
    Log::info('✅ [DocumentDrafting] Placeholder mapping completed', [
        'total_placeholders' => count($placeholders),
        'mapped_count' => count($mapped),
        'unmapped_count' => count($placeholders) - count($mapped),
        'mapped_keys' => array_keys($mapped),
    ]);
    
    return $mapped;
}
```

---

### Giải Pháp 2: ⚠️ **FALLBACK - Nếu AI không trả về đúng format**

**Trường hợp:** AI vẫn trả về `header`, `body`, `footer` dù đã sửa prompt

**Cách xử lý:** Parse AI response và map sang placeholders

**File:** `app/Services/DocumentDraftingService.php`

**Thêm method mới:**

```php
/**
 * Parse AI response và convert sang placeholder format
 * 
 * @param array $aiContent AI response (có thể có format cũ: header/body/footer)
 * @param array $templateStructure Template structure (placeholders)
 * @return array Parsed data với keys khớp placeholders
 */
protected function parseAIContentToPlaceholders(array $aiContent, array $templateStructure): array
{
    // Nếu AI đã trả về đúng format (có keys khớp với placeholders)
    $hasMatchingKeys = false;
    foreach (array_keys($templateStructure) as $key) {
        if (isset($aiContent[$key])) {
            $hasMatchingKeys = true;
            break;
        }
    }
    
    if ($hasMatchingKeys) {
        Log::info('✅ AI content already has matching placeholder keys');
        return $aiContent;
    }
    
    // ⚠️ FALLBACK: AI trả về format cũ (header/body/footer)
    // → Parse và convert sang placeholder format
    Log::warning('⚠️ AI returned old format (header/body/footer), parsing to placeholders', [
        'ai_content_keys' => array_keys($aiContent),
        'template_structure_keys' => array_keys($templateStructure),
    ]);
    
    $parsed = [];
    
    // Extract header data
    if (isset($aiContent['header']) && is_array($aiContent['header'])) {
        foreach ($aiContent['header'] as $item) {
            // Try to parse "Key: Value" format
            if (preg_match('/^([^:]+):\s*(.+)$/u', $item, $matches)) {
                $key = $this->normalizeKey($matches[1]);
                $value = trim($matches[2]);
                
                // Map to known placeholders
                $mappedKey = $this->mapGenericKeyToPlaceholder($key, $templateStructure);
                if ($mappedKey) {
                    $parsed[$mappedKey] = $value;
                }
            } else {
                // Single value - try to map to first available placeholder
                // ...
            }
        }
    }
    
    // Extract body data
    if (isset($aiContent['body']) && is_array($aiContent['body'])) {
        // Similar logic...
    }
    
    // Extract footer data
    if (isset($aiContent['footer']) && is_array($aiContent['footer'])) {
        // Similar logic...
    }
    
    Log::info('✅ Parsed AI content to placeholders', [
        'original_keys' => array_keys($aiContent),
        'parsed_keys' => array_keys($parsed),
        'mapped_count' => count($parsed),
    ]);
    
    return $parsed;
}

/**
 * Map generic key (từ AI) sang placeholder cụ thể
 */
protected function mapGenericKeyToPlaceholder(string $genericKey, array $templateStructure): ?string
{
    $mapping = [
        'so' => 'so_van_ban',
        'ngay' => 'ngay_thang',
        'dia_diem' => 'dia_diem',
        'noi_dung' => 'noi_dung',
        'ket_luan' => 'ket_luan',
        // ... thêm mappings
    ];
    
    $normalizedKey = $this->normalizeKey($genericKey);
    
    // Try direct mapping
    if (isset($mapping[$normalizedKey])) {
        $placeholder = $mapping[$normalizedKey];
        if (isset($templateStructure[$placeholder])) {
            return $placeholder;
        }
    }
    
    // Try fuzzy match với template placeholders
    foreach (array_keys($templateStructure) as $templateKey) {
        if (str_contains(strtolower($templateKey), $normalizedKey)) {
            return $templateKey;
        }
    }
    
    return null;
}

/**
 * Normalize key (remove special chars, lowercase, etc.)
 */
protected function normalizeKey(string $key): string
{
    $key = preg_replace('/[^a-z0-9_]/iu', '', $key);
    $key = strtolower($key);
    return $key;
}
```

**Sửa trong method `draftDocument()`:**

```php
// Line 131: Sau khi merge auto-filled data với AI content
$documentData = array_merge($autoFilledData, $aiContent);

// ✅ MỚI: Parse AI content nếu không khớp với template structure
if ($template && !empty($templateStructure)) {
    $documentData = $this->parseAIContentToPlaceholders($documentData, $templateStructure);
    
    Log::info('🔵 [DocumentDrafting] Parsed document data to placeholders', [
        'assistant_id' => $assistant->id,
        'template_id' => $template->id,
        'parsed_fields' => array_keys($documentData),
    ]);
}
```

---

## 4. KIỂM TRA VÀ TEST

### Test Case 1: Upload Template DOCX với Placeholders

**Bước 1:** Upload template DOCX có placeholders như `${ten_co_quan}`, `${so_van_ban}`, etc.

**Bước 2:** Kiểm tra log xem placeholders có được extract không:
```bash
tail -f storage/logs/laravel.log | grep "Extracted placeholders"
```

**Kết quả mong đợi:**
```
Extracted placeholders from DOCX {"placeholders": ["ten_co_quan", "so_van_ban", "ngay_thang", ...]}
```

### Test Case 2: Tạo Văn Bản

**Bước 1:** User yêu cầu: "Tạo 1 mẫu Biên bản"

**Bước 2:** Kiểm tra log AI response:
```bash
tail -f storage/logs/laravel.log | grep "AI content generated"
```

**Kết quả mong đợi:**
```
AI content generated {"ai_content_fields": ["ten_co_quan", "so_van_ban", "ngay_thang", "noi_dung", ...]}
```

**KHÔNG phải:**
```
AI content generated {"ai_content_fields": ["header", "body", "footer"]}
```

### Test Case 3: Kiểm Tra Placeholder Mapping

**Kiểm tra log:**
```bash
tail -f storage/logs/laravel.log | grep "Mapping data to placeholders" -A 20
```

**Kết quả mong đợi:**
```
Mapping data to placeholders {
  "document_data_keys": ["so_van_ban", "ngay_thang", "ten_co_quan", "noi_dung", ...],
  "placeholders": ["so_van_ban", "ngay_thang", "ten_co_quan", "noi_dung", ...],
  "mapped_count": 10
}
```

### Test Case 4: Kiểm Tra HTML Preview

**Bước 1:** Mở chatbot, tạo văn bản

**Bước 2:** Kiểm tra HTML preview có hiển thị nội dung thực tế (không phải placeholders)

**Kết quả mong đợi:**
- Hiển thị: "Số: 551/BB-ABC"
- Hiển thị: "Ngày: 09/11/2025"
- Hiển thị: "Nội dung: Cuộc họp diễn ra nhằm..."

**KHÔNG phải:**
- Hiển thị: "Số: ${so_van_ban}"
- Hiển thị: "Ngày: ${ngay_thang}"

---

## 5. TÓM TẮT

### Nguyên Nhân Chính
❌ **AI trả về data format generic (`header`, `body`, `footer`) thay vì các placeholders cụ thể trong template DOCX**

### Giải Pháp
✅ **Sửa AI prompt để yêu cầu trả về đúng keys khớp với placeholders trong template**

### File Cần Sửa
1. `app/Services/DocumentDraftingService.php`
   - Method `extractTemplateStructure()` - Line 407-492
   - Method `buildPrompt()` - Line 696-747
   - Method `mapDataToPlaceholders()` - Line 373-399
   - Method mới `parseAIContentToPlaceholders()` (fallback)

### Ưu Tiên
1. **CAO:** Sửa `buildPrompt()` để yêu cầu AI trả về đúng placeholders
2. **CAO:** Cải thiện `extractTemplateStructure()` để extract đúng placeholders từ DOCX
3. **TRUNG BÌNH:** Cải thiện `mapDataToPlaceholders()` với logging chi tiết
4. **THẤP:** Thêm fallback `parseAIContentToPlaceholders()` để parse AI response cũ

---

## 6. KẾT LUẬN

Vấn đề **KHÔNG PHẢI** do việc hiển thị HTML trên frontend. Vấn đề nằm ở **backend khi generate DOCX từ template**:

1. AI trả về data không khớp với placeholders
2. TemplateProcessor không replace được placeholders
3. DOCX file vẫn chứa placeholders chưa được thay thế
4. HTML preview hiển thị placeholders thay vì content

**Giải pháp:** Sửa AI prompt và cải thiện placeholder extraction/mapping logic.



