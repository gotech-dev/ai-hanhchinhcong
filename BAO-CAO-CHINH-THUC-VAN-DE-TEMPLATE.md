# 📋 BÁO CÁO: Vấn Đề Hiển Thị Template Trên Chatbot

**Ngày:** 09/11/2025  
**Người kiểm tra:** AI Assistant  
**Vấn đề:** Admin đã upload file DOCX template, nhưng chatbot không hiển thị đúng format template

---

## ✅ KẾT QUẢ KIỂM TRA

### 🔍 Phát Hiện Chính

**❌ NGUYÊN NHÂN GỐC RỄ: Template DOCX không có placeholders**

Sau khi kiểm tra:
```
Template ID: 15
Template Name: Biên bản
File Type: docx
Placeholders found: 0
Metadata placeholders: []
```

**Kết luận:** Template DOCX được upload là một văn bản mẫu hoàn chỉnh, KHÔNG CÓ các placeholders dạng `${key}` để replace.

---

## 📊 PHÂN TÍCH CHI TIẾT

### 1. Luồng Xử Lý Hiện Tại

```
Admin Upload Template
   ↓
[Template DOCX - Văn bản mẫu hoàn chỉnh]
   ↓
Lưu vào database (document_templates)
   ↓
User yêu cầu: "Tạo 1 mẫu Biên bản"
   ↓
System tìm template → ✅ Tìm thấy
   ↓
Extract placeholders → ❌ Không có placeholders
   ↓
AI generate content → ✅ OK (nhưng không có gì để replace)
   ↓
TemplateProcessor::setValue() → ❌ Không có placeholders để replace
   ↓
DOCX file = Template gốc (không thay đổi)
   ↓
HTML preview = Template gốc (không có nội dung mới)
```

### 2. So Sánh 2 Loại Template

#### Template Type 1: ❌ Văn Bản Mẫu (Hiện tại)
```
CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
Độc lập - Tự do - Hạnh phúc
----------

BIÊN BẢN

[Nội dung biên bản mẫu đầy đủ]
...
```

**Đặc điểm:**
- Văn bản hoàn chỉnh
- Không có placeholders
- Dùng làm **MẪU THAM KHẢO**, không phải template để điền

#### Template Type 2: ✅ Template Với Placeholders (Cần thiết)
```
${TEN_CO_QUAN}
${DIA_CHI}

CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
Độc lập - Tự do - Hạnh phúc
----------

BIÊN BẢN
${TEN_BIEN_BAN}

Số: ${SO_BIEN_BAN}
Ngày: ${NGAY_THANG}

Thành phần: ${THANH_PHAN}
Nội dung: ${NOI_DUNG}
Kết luận: ${KET_LUAN}

${NGUOI_KY}
${CHUC_VU}
```

**Đặc điểm:**
- Có placeholders dạng `${KEY}`
- Có thể replace bằng TemplateProcessor
- Dùng làm **TEMPLATE ĐỂ ĐIỀN**

---

## 🎯 GIẢI PHÁP

### Giải Pháp 1: ✅ **YÊU CẦU ADMIN TẠO LẠI TEMPLATE VỚI PLACEHOLDERS**

**Ưu điểm:**
- Đúng cách sử dụng TemplateProcessor
- Format preservation 100%
- Performance tốt

**Cách thực hiện:**

#### Bước 1: Hướng dẫn Admin tạo template đúng

**File DOCX cần có placeholders dạng:**
- `${ten_co_quan}`
- `${so_van_ban}`
- `${ngay_thang}`
- `${noi_dung}`
- `${ket_luan}`
- `${nguoi_ky}`
- `${chuc_vu}`
- etc.

**Ví dụ nội dung file DOCX:**
```
${ten_co_quan}
${dia_chi}

CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
Độc lập - Tự do - Hạnh phúc
----------

BIÊN BẢN HỌP

Số: ${so_bien_ban}
Địa điểm: ${dia_diem}
Thời gian: ${thoi_gian}

Thành phần tham dự:
${thanh_phan}

Nội dung cuộc họp:
${noi_dung}

Kết luận:
${ket_luan}

Người ký
${nguoi_ky}
${chuc_vu}
```

#### Bước 2: Upload lại template

Admin cần:
1. Tạo file DOCX mới với placeholders như trên
2. Upload lại template cho assistant
3. System sẽ tự động extract placeholders

---

### Giải Pháp 2: ⚠️ **FALLBACK - SỬ DỤNG TEMPLATE NHƯ REFERENCE**

**Trường hợp:** Template không có placeholders (như hiện tại)

**Cách xử lý:** Sử dụng template content làm reference cho AI, tạo DOCX mới từ code

**File:** `app/Services/DocumentDraftingService.php`

**Thay đổi logic trong `generateDocxFromTemplate()`:**

```php
protected function generateDocxFromTemplate(DocumentTemplate $template, array $documentData, ChatSession $session): string
{
    try {
        $templatePath = $this->getTemplatePath($template->file_path);
        
        if (!file_exists($templatePath)) {
            // Fallback to code generation
            return $this->generateDocx(
                \App\Enums\DocumentType::from($template->document_type),
                $documentData,
                $session
            );
        }
        
        // Check file extension
        $fileExtension = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
        if ($fileExtension === 'doc') {
            // .doc format not supported
            return $this->generateDocx(
                \App\Enums\DocumentType::from($template->document_type),
                $documentData,
                $session
            );
        }
        
        // ✅ NEW: Check if template has placeholders
        $templateProcessor = new TemplateProcessor($templatePath);
        $placeholders = $templateProcessor->getVariables();
        
        if (empty($placeholders)) {
            // ⚠️ Template không có placeholders
            // → Sử dụng template như REFERENCE, tạo DOCX mới từ code
            Log::info('⚠️ [DocumentDrafting] Template has no placeholders, using as reference', [
                'template_id' => $template->id,
                'template_name' => $template->name,
            ]);
            
            // Generate DOCX from code với format giống template
            return $this->generateDocxFromReference($template, $documentData, $session);
        }
        
        // ✅ Template có placeholders → Use TemplateProcessor (existing logic)
        Log::info('✅ [DocumentDrafting] Template has placeholders, using TemplateProcessor', [
            'template_id' => $template->id,
            'placeholders_count' => count($placeholders),
            'placeholders' => $placeholders,
        ]);
        
        // Map document data to placeholders
        $mappedData = $this->mapDataToPlaceholders($documentData, $placeholders);
        
        // Replace placeholders
        foreach ($mappedData as $key => $value) {
            try {
                $templateProcessor->setValue($key, $value);
            } catch (\Exception $e) {
                Log::warning('Failed to replace placeholder', [
                    'placeholder' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
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
 * ✅ NEW: Generate DOCX from template reference (no placeholders)
 * 
 * Sử dụng template như reference để copy styles, format
 * Nhưng tạo nội dung mới từ documentData
 */
protected function generateDocxFromReference(DocumentTemplate $template, array $documentData, ChatSession $session): string
{
    try {
        $templatePath = $this->getTemplatePath($template->file_path);
        
        // 1. Load template để lấy styles
        $templateDoc = IOFactory::load($templatePath);
        
        // 2. Create new PhpWord document
        $phpWord = new PhpWord();
        
        // 3. Copy styles from template (nếu có thể)
        // Note: PhpWord có hạn chế trong việc copy styles
        // Có thể cần implement custom style copying logic
        
        // 4. Add section với style giống template
        $section = $phpWord->addSection([
            'marginLeft' => 1000,
            'marginRight' => 1000,
            'marginTop' => 1000,
            'marginBottom' => 1000,
        ]);
        
        // 5. Generate content từ documentData với format giống template
        $this->addContentToSection($section, $documentData, \App\Enums\DocumentType::from($template->document_type));
        
        // 6. Save file
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
        
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filePath);
        
        Log::info('✅ [DocumentDrafting] Generated DOCX from template reference', [
            'template_id' => $template->id,
            'file_path' => $filePath,
        ]);
        
        return Storage::disk('public')->url("documents/{$fileName}");
        
    } catch (\Exception $e) {
        Log::error('Failed to generate DOCX from reference', [
            'template_id' => $template->id,
            'error' => $e->getMessage(),
        ]);
        
        // Final fallback: pure code generation
        return $this->generateDocx(
            \App\Enums\DocumentType::from($template->document_type),
            $documentData,
            $session
        );
    }
}

/**
 * Add content to section with proper formatting
 */
protected function addContentToSection($section, array $documentData, DocumentType $documentType): void
{
    // Header
    $section->addText(
        $documentData['ten_co_quan'] ?? 'CƠ QUAN HÀNH CHÍNH',
        ['size' => 13, 'bold' => true],
        ['alignment' => Jc::CENTER]
    );
    
    $section->addText(
        'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM',
        ['size' => 13, 'bold' => true],
        ['alignment' => Jc::CENTER]
    );
    
    $section->addText(
        'Độc lập - Tự do - Hạnh phúc',
        ['size' => 13, 'bold' => true],
        ['alignment' => Jc::CENTER]
    );
    
    $section->addText('----------', [], ['alignment' => Jc::CENTER]);
    $section->addTextBreak(1);
    
    // Title
    $section->addText(
        'BIÊN BẢN',
        ['size' => 16, 'bold' => true],
        ['alignment' => Jc::CENTER]
    );
    
    $section->addTextBreak(1);
    
    // Body content
    if (isset($documentData['so_van_ban'])) {
        $section->addText("Số: {$documentData['so_van_ban']}", ['size' => 13]);
    }
    
    if (isset($documentData['ngay_thang'])) {
        $section->addText("Ngày: {$documentData['ngay_thang']}", ['size' => 13]);
    }
    
    $section->addTextBreak(1);
    
    // Main content from AI
    if (isset($documentData['body']) && is_array($documentData['body'])) {
        foreach ($documentData['body'] as $item) {
            $section->addText($item, ['size' => 13], ['alignment' => Jc::BOTH]);
            $section->addTextBreak(1);
        }
    } elseif (isset($documentData['noi_dung'])) {
        $section->addText($documentData['noi_dung'], ['size' => 13], ['alignment' => Jc::BOTH]);
        $section->addTextBreak(1);
    }
    
    // Footer
    $section->addTextBreak(2);
    
    if (isset($documentData['nguoi_ky'])) {
        $section->addText(
            $documentData['nguoi_ky'],
            ['size' => 13, 'bold' => true],
            ['alignment' => Jc::RIGHT]
        );
    }
    
    if (isset($documentData['chuc_vu'])) {
        $section->addText(
            $documentData['chuc_vu'],
            ['size' => 13],
            ['alignment' => Jc::RIGHT]
        );
    }
}
```

---

### Giải Pháp 3: ✅ **CẢI THIỆN UX - THÔNG BÁO ADMIN**

**Thêm validation và thông báo khi upload template**

**File:** `app/Http/Controllers/AdminController.php`
**Method:** `processDocumentTemplates()`

```php
protected function processDocumentTemplates(Request $request, AiAssistant $assistant): void
{
    $templates = $request->input('templates', []);
    
    foreach ($templates as $index => $templateData) {
        $file = $request->file("templates.{$index}.file");
        
        if ($file) {
            // Store file
            $path = $file->store('document-templates', 'public');
            $fullPath = Storage::disk('public')->path($path);
            
            // ✅ NEW: Check if DOCX has placeholders
            $hasPlaceholders = false;
            $placeholders = [];
            
            if (strtolower($file->getClientOriginalExtension()) === 'docx') {
                try {
                    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($fullPath);
                    $placeholders = $templateProcessor->getVariables();
                    $hasPlaceholders = !empty($placeholders);
                    
                    // ⚠️ WARNING: Template không có placeholders
                    if (!$hasPlaceholders) {
                        Log::warning('⚠️ Template uploaded without placeholders', [
                            'assistant_id' => $assistant->id,
                            'template_name' => $templateData['name'],
                            'file_name' => $file->getClientOriginalName(),
                        ]);
                        
                        // TODO: Có thể thêm flash message để thông báo admin
                        // session()->flash('warning', "Template '{$templateData['name']}' không có placeholders. Nội dung sẽ được tạo từ mẫu reference.");
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to check template placeholders', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Create template record
            DocumentTemplate::create([
                'ai_assistant_id' => $assistant->id,
                'name' => $templateData['name'],
                'document_type' => $templateData['document_type'],
                'template_subtype' => $templateData['template_subtype'] ?? null,
                'file_path' => Storage::disk('public')->url($path),
                'file_type' => $file->getClientOriginalExtension(),
                'metadata' => [
                    'placeholders' => $placeholders,
                    'has_placeholders' => $hasPlaceholders, // ✅ NEW
                    'original_filename' => $file->getClientOriginalName(),
                ],
                'is_active' => true,
            ]);
        }
    }
}
```

---

## 📝 TÓM TẮT

### ✅ Vấn Đề Đã Xác Định

1. **Nguyên nhân gốc rễ:** Template DOCX không có placeholders dạng `${key}`
2. **Hệ quả:** TemplateProcessor không thể replace nội dung → DOCX và HTML preview hiển thị template gốc
3. **Vấn đề phụ:** AI trả về format `header/body/footer` thay vì keys cụ thể (nhưng điều này không quan trọng khi template không có placeholders)

### 🎯 Giải Pháp Được Đề Xuất

| Giải pháp | Ưu điểm | Nhược điểm | Ưu tiên |
|-----------|---------|------------|---------|
| **1. Admin tạo lại template với placeholders** | ✅ Đúng cách sử dụng<br>✅ Format 100%<br>✅ Performance tốt | ⚠️ Cần admin làm lại | **CAO** |
| **2. Sử dụng template như reference** | ✅ Không cần admin làm lại<br>✅ Tự động fallback | ⚠️ Format không bảo toàn 100%<br>⚠️ Phức tạp implement | TRUNG BÌNH |
| **3. Thông báo admin khi upload** | ✅ Improve UX<br>✅ Prevent future issues | ➖ Không fix current issue | TRUNG BÌNH |

### 📋 HÀNH ĐỘNG ĐỀ XUẤT

#### Ngay lập tức:
1. ✅ **Hướng dẫn admin tạo lại template DOCX với placeholders**
   - Tạo file DOCX mới
   - Thêm placeholders dạng `${ten_co_quan}`, `${so_van_ban}`, etc.
   - Upload lại template

#### Ngắn hạn (tuần này):
2. ✅ **Implement Giải pháp 2: Fallback logic**
   - Detect template không có placeholders
   - Fallback sang `generateDocxFromReference()`
   - Tạo DOCX mới với content từ AI

3. ✅ **Implement Giải pháp 3: Validation và thông báo**
   - Check placeholders khi upload
   - Thông báo admin nếu template không có placeholders

#### Dài hạn (tháng này):
4. ✅ **Improve AI prompt để trả về đúng keys**
   - Extract placeholders từ template
   - Include trong AI prompt
   - AI trả về JSON với keys khớp placeholders

---

## 🧪 KIỂM TRA

### Test Case 1: Template Với Placeholders ✅

**Template DOCX:**
```
Số: ${so_van_ban}
Ngày: ${ngay_thang}
Nội dung: ${noi_dung}
```

**Kết quả mong đợi:**
- ✅ Extract 3 placeholders: `so_van_ban`, `ngay_thang`, `noi_dung`
- ✅ AI trả về keys: `so_van_ban`, `ngay_thang`, `noi_dung`
- ✅ Replace thành công
- ✅ HTML preview hiển thị: "Số: 551/BB-ABC", "Ngày: 09/11/2025", "Nội dung: ..."

### Test Case 2: Template Không Có Placeholders ⚠️

**Template DOCX:**
```
Số: ...
Ngày: ...
Nội dung: ...
```

**Kết quả mong đợi:**
- ✅ Detect: no placeholders
- ✅ Fallback sang `generateDocxFromReference()`
- ✅ Tạo DOCX mới với content từ AI
- ✅ HTML preview hiển thị nội dung mới

---

## 📞 LIÊN HỆ

Nếu cần hỗ trợ thêm:
1. Kiểm tra log file: `storage/logs/laravel.log`
2. Chạy test script: `php test-template-placeholders.php`
3. Xem file báo cáo này: `BAO-CAO-CHINH-THUC-VAN-DE-TEMPLATE.md`

---

**Kết luận:** Vấn đề đã được xác định rõ ràng. Giải pháp ngắn hạn là yêu cầu admin tạo lại template với placeholders. Giải pháp dài hạn là implement fallback logic để xử lý cả 2 loại template.



