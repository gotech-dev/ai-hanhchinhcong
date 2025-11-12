# Báo Cáo Phân Tích: Vấn Đề Trợ Lý Báo Cáo

## Tổng Quan

Phần trợ lý báo cáo hiện tại có **2 vấn đề chính**:

1. **Tạo báo cáo chưa đúng format của file docx hoặc pdf đã up lên**
2. **Khi tạo báo cáo trong khung chatbox chưa có button download dạng docx**

## Hiểu Đúng Yêu Cầu

### Yêu Cầu Thực Tế

**Flow hoạt động (CHỈ cho trợ lý `report_generator`):**

1. **Admin tạo trợ lý:** 
   - Chọn loại trợ lý: `report_generator` hoặc `qa_based_document`
   - Nếu `report_generator`: Upload template DOCX mẫu (ví dụ: template báo cáo thường niên với placeholders như `[TÊN CÔNG TY]`, `[NĂM]`, `[Ngành]`, v.v.)
   - Nếu `qa_based_document`: Upload documents để trả lời Q&A
   
2. **User chat với trợ lý `report_generator`:**
   - Trợ lý thu thập dữ liệu từ conversation (tên công ty, năm, ngành, địa chỉ, v.v.)
   - Khi đủ dữ liệu: Trợ lý tạo báo cáo mới bằng cách:
     - **Giữ nguyên format** của template DOCX gốc
     - **Điền dữ liệu** đã thu thập vào các placeholders trong template
     - **Tạo file DOCX mới** với nội dung đã được điền đầy đủ

3. **User chat với trợ lý `qa_based_document`:**
   - User hỏi câu hỏi
   - AI search trong documents và trả lời
   - **KHÔNG tạo báo cáo** - không dùng ReportGenerator

**Kết quả mong muốn (CHỈ cho `report_generator`):**
- Báo cáo mới có **format giống hệt** template mẫu (font, size, color, bold, italic, alignment, table, header, footer, v.v.)
- Các placeholders trong template được **thay thế bằng dữ liệu thực tế** từ conversation
- User có thể **download file DOCX** với format đầy đủ

**Quan trọng:**
- ✅ Chỉ áp dụng cho trợ lý loại `report_generator`
- ✅ **KHÔNG ảnh hưởng** đến trợ lý `qa_based_document` (Q&A)
- ✅ Q&A vẫn hoạt động bình thường, không dùng ReportGenerator

---

## Vấn Đề 1: Tạo Báo Cáo Chưa Đúng Format

### Mô Tả Vấn Đề

Khi tạo báo cáo từ template DOCX hoặc PDF đã upload, báo cáo được tạo ra **không giữ nguyên format** của file template gốc. Báo cáo chỉ là text thuần túy, mất đi:
- Font chữ, kích thước, màu sắc
- Định dạng in đậm, in nghiêng, gạch chân
- Căn lề, khoảng cách
- Bảng biểu, hình ảnh
- Header, Footer
- Các style và formatting khác

### Nguyên Nhân Gốc Rễ

Sau khi phân tích code, tôi đã xác định được **4 nguyên nhân chính**:

#### 1. **AI Generate Text Content Mới Thay Vì Điền Data Vào Template**

**File:** `app/Services/ReportGenerator.php`

**Vấn đề:**
- Line 43: `extractTemplateText()` chỉ extract **text thuần túy** từ template, mất hết format
- Line 54: `fillTemplateWithData()` sử dụng **AI để generate text content mới**, không điền data vào placeholders
- Line 199: AI trả về `reportContent` là **text markdown/plain text mới**, không phải template đã được điền data

```php
// Line 43-54
$templateText = $this->extractTemplateText($templatePath); // ❌ Chỉ lấy text, mất format
$reportContent = $this->fillTemplateWithData($templateText, $collectedData, $templateFields, $assistant); 
// ❌ AI generate text mới thay vì điền data vào placeholders trong template
```

**Hậu quả:**
- `report_content` trong database chỉ là text thuần túy mới được AI generate
- **KHÔNG sử dụng template gốc** để điền data vào placeholders
- Format của template gốc bị mất hoàn toàn

#### 2. **Template Processor Không Sử Dụng AI-Generated Content**

**File:** `app/Services/ReportFileGenerator.php`

**Vấn đề:**
- Line 39: `prepareDataForTemplate()` chỉ map `collectedData` vào placeholders
- Line 43-54: Chỉ replace placeholders như `{{field_name}}` với giá trị từ `collectedData`
- **KHÔNG sử dụng** `report_content` đã được AI generate để fill vào template

```php
// Line 38-54
$data = $this->prepareDataForTemplate($collectedData); // ❌ Chỉ dùng collectedData
foreach ($data as $key => $value) {
    $templateProcessor->setValue($key, $value ?? ''); // ❌ Chỉ replace placeholders đơn giản
}
```

**Hậu quả:**
- Template DOCX được replace placeholders, nhưng nội dung không phải từ AI-generated content
- Nếu template không có placeholders, báo cáo sẽ giống hệt template gốc (chưa điền data)

#### 3. **Disconnect Giữa AI Content và Template Replacement**

**Luồng xử lý hiện tại:**

```
1. Extract text từ template (mất format) → templateText
2. AI generate content mới từ templateText → reportContent (text thuần)
3. Save reportContent vào database
4. TemplateProcessor replace placeholders trong template DOCX với collectedData
5. ❌ reportContent từ AI KHÔNG được sử dụng để fill vào template
```

**Vấn đề:**
- AI-generated `reportContent` và Template replacement là **2 quy trình độc lập**
- Template replacement chỉ dùng `collectedData` (raw data), không dùng `reportContent` (AI-processed content)
- Kết quả: Báo cáo DOCX có format nhưng nội dung không phải từ AI

## Phương Án Cải Thiện

### Nguyên Tắc Cải Thiện

**Mục tiêu:** Tạo báo cáo mới bằng cách **giữ nguyên format template** và **điền dữ liệu vào placeholders**, không phải generate text mới.

**Quan trọng:** Chỉ áp dụng cho trợ lý loại `report_generator`, **KHÔNG ảnh hưởng** đến trợ lý `qa_based_document` (Q&A).

**Flow đúng:**
```
1. Check assistant_type === 'report_generator' (chỉ xử lý cho report generator)
2. Load template DOCX gốc (có placeholders như [TÊN CÔNG TY], [NĂM], v.v.)
3. Extract placeholders từ template
4. Map dữ liệu đã thu thập vào placeholders
5. Sử dụng TemplateProcessor để replace placeholders → giữ nguyên format
6. Tạo file DOCX mới với format giống hệt template + data đã điền
```

### Phân Loại Trợ Lý

**Hệ thống có 2 loại trợ lý:**

1. **`report_generator`** - Tạo báo cáo từ template
   - Admin upload template DOCX mẫu
   - User chat → thu thập data
   - Tạo báo cáo mới từ template + data
   - **Cần refactor phần này**

2. **`qa_based_document`** - Trả lời Q&A từ tài liệu
   - Admin upload documents
   - User hỏi câu hỏi
   - AI trả lời dựa trên documents
   - **KHÔNG ảnh hưởng** - không dùng ReportGenerator

### Cách Cải Thiện

#### Giải Pháp 1: Sử Dụng Template Processor Trực Tiếp Với Collected Data (Khuyến nghị - Đơn giản nhất)

**Ý tưởng:**
1. **Bỏ qua bước AI generate text mới** - không cần thiết
2. **Load template DOCX gốc** trực tiếp
3. **Extract placeholders** từ template (hỗ trợ `{{key}}`, `${key}`, `[key]` formats)
4. **Map collected data** trực tiếp vào placeholders
5. **Sử dụng TemplateProcessor** để replace placeholders → **giữ nguyên format**

**Ưu điểm:**
- ✅ Đơn giản, không cần AI generate text
- ✅ Giữ nguyên 100% format của template
- ✅ Nhanh hơn (không cần gọi AI)
- ✅ Chính xác hơn (điền đúng data vào đúng placeholders)

**Implementation:**

```php
// app/Services/ReportGenerator.php

public function generateReport(AiAssistant $assistant, ChatSession $session, array $collectedData): array
{
    try {
        // ✅ QUAN TRỌNG: Chỉ xử lý cho report_generator
        if ($assistant->assistant_type !== 'report_generator') {
            throw new \Exception('ReportGenerator chỉ dùng cho assistant_type = report_generator');
        }
        
        // 1. Get template file path
        $templatePath = $assistant->template_file_path;
        if (!$templatePath) {
            throw new \Exception('Template file not found for assistant');
        }

        // 2. Generate DOCX từ template trực tiếp (giữ format)
        // KHÔNG cần extract text và AI generate text mới
        $docxUrl = null;
        $reportContent = ''; // Text content để hiển thị (optional)
        
        try {
            $reportFileGenerator = app(ReportFileGenerator::class);
            
            // Tạo UserReport tạm thời
            $userReport = UserReport::create([
                'user_id' => $session->user_id,
                'chat_session_id' => $session->id,
                'report_content' => '', // Sẽ generate sau nếu cần
                'report_file_path' => null,
                'file_format' => 'docx',
            ]);
            
            // Generate DOCX từ template với collected data
            $docxUrl = $reportFileGenerator->generateDocxFromTemplate(
                $userReport,
                $assistant,
                $collectedData // ✅ Dùng trực tiếp collected data
            );
            
            // Extract text từ DOCX đã tạo để hiển thị (optional)
            $reportContent = $this->extractTextFromDocx($docxUrl);
            
            // Update report với content
            $userReport->update([
                'report_content' => $reportContent,
                'report_file_path' => $docxUrl,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate DOCX from template', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistant->assistant_type,
            ]);
            throw $e;
        }
        
        return [
            'report_content' => $reportContent,
            'report_file_path' => $docxUrl,
            'report_id' => $userReport->id,
        ];
    } catch (\Exception $e) {
        Log::error('Report generation error', [
            'error' => $e->getMessage(),
            'assistant_id' => $assistant->id,
            'assistant_type' => $assistant->assistant_type,
        ]);
        throw $e;
    }
}

// app/Services/ReportFileGenerator.php

public function generateDocxFromTemplate(
    UserReport $report, 
    AiAssistant $assistant, 
    array $collectedData // ✅ Dùng trực tiếp collected data
): string {
    try {
        // ✅ QUAN TRỌNG: Verify assistant type
        if ($assistant->assistant_type !== 'report_generator') {
            throw new \Exception('ReportFileGenerator chỉ dùng cho assistant_type = report_generator');
        }
        
        // 1. Load template DOCX gốc
        $templatePath = $this->getTemplatePath($assistant->template_file_path);
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Template file not found: {$templatePath}");
        }
        
        // 2. Extract placeholders từ template (hỗ trợ {{key}}, ${key}, [key])
        $templatePlaceholders = $this->extractPlaceholdersFromTemplate($templatePath);
        
        // 3. Sử dụng TemplateProcessor để replace placeholders
        $templateProcessor = new TemplateProcessor($templatePath);
        
        // 4. Map collected data vào placeholders
        $data = $this->prepareDataForTemplate($collectedData);
        
        // 5. Map data với placeholders thực tế trong template
        $mappedData = $this->mapDataToTemplatePlaceholders($data, $templatePlaceholders);
        
        // 6. Replace placeholders (giữ nguyên format)
        foreach ($mappedData as $placeholder => $value) {
            try {
                $cleanValue = $this->cleanValue($value);
                $templateProcessor->setValue($placeholder, $cleanValue);
            } catch (\Exception $e) {
                Log::debug("Placeholder not found: {$placeholder}");
            }
        }
        
        // 7. Save file mới
        $fileName = 'reports/report_' . $report->id . '_' . time() . '.docx';
        $filePath = storage_path('app/public/' . $fileName);
        $templateProcessor->saveAs($filePath);
        
        // 8. Update report
        $report->update([
            'report_file_path' => Storage::disk('public')->url($fileName),
            'file_format' => 'docx',
        ]);
        
        return Storage::disk('public')->url($fileName);
    } catch (\Exception $e) {
        Log::error('Failed to generate DOCX from template', [
            'error' => $e->getMessage(),
            'report_id' => $report->id,
            'assistant_type' => $assistant->assistant_type ?? 'unknown',
        ]);
        throw $e;
    }
}
```

#### Giải Pháp 2: Sử Dụng AI Để Xử Lý Dữ Liệu Phức Tạp (Nếu cần)

**Ý tưởng:**
1. Nếu collected data cần xử lý phức tạp (ví dụ: format ngày tháng, tính toán, v.v.)
2. Sử dụng AI để **xử lý và format data** trước khi điền vào template
3. AI trả về **structured data** đã được format đúng
4. Map structured data vào placeholders trong template

**Khi nào dùng:**
- Collected data cần xử lý phức tạp (tính toán, format, validation)
- Cần AI để hiểu context và format data đúng cách
- Template có nhiều placeholders phức tạp

**Implementation:**

```php
// app/Services/ReportGenerator.php

public function generateReport(AiAssistant $assistant, ChatSession $session, array $collectedData): array
{
    // ✅ QUAN TRỌNG: Chỉ xử lý cho report_generator
    if ($assistant->assistant_type !== 'report_generator') {
        throw new \Exception('ReportGenerator chỉ dùng cho assistant_type = report_generator');
    }
    
    // 1. Extract template text để AI hiểu context
    $templateText = $this->extractTemplateText($assistant->template_file_path);
    
    // 2. Sử dụng AI để xử lý và format data
    $processedData = $this->processDataWithAI($templateText, $collectedData, $assistant);
    // AI trả về structured data đã được format: ['ten_cong_ty' => 'Công ty ABC', 'nam' => '2024', ...]
    
    // 3. Generate DOCX từ template với processed data
    $docxUrl = $this->reportFileGenerator->generateDocxFromTemplate(
        $userReport,
        $assistant,
        $processedData // ✅ Dùng processed data từ AI
    );
    
    return [
        'report_content' => $this->extractTextFromDocx($docxUrl),
        'report_file_path' => $docxUrl,
        'report_id' => $userReport->id,
    ];
}

protected function processDataWithAI(string $templateText, array $collectedData, AiAssistant $assistant): array
{
    $prompt = "Bạn là chuyên gia xử lý dữ liệu báo cáo. Hãy xử lý và format dữ liệu sau để điền vào template.\n\n";
    $prompt .= "TEMPLATE:\n" . substr($templateText, 0, 2000) . "\n\n";
    $prompt .= "DỮ LIỆU:\n";
    foreach ($collectedData as $key => $value) {
        $prompt .= "- {$key}: {$value}\n";
    }
    $prompt .= "\nYÊU CẦU:\n";
    $prompt .= "1. Xử lý và format dữ liệu phù hợp với template\n";
    $prompt .= "2. Trả về JSON với format: {\"field_name\": \"formatted_value\", ...}\n";
    $prompt .= "3. Giữ nguyên key names để map vào placeholders\n";
    
    $response = OpenAI::chat()->create([
        'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
        'messages' => [
            ['role' => 'system', 'content' => 'Bạn là chuyên gia xử lý dữ liệu. Trả về JSON format.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'response_format' => ['type' => 'json_object'],
    ]);
    
    $processedData = json_decode($response->choices[0]->message->content, true);
    
    // Merge với collected data (ưu tiên processed data)
    return array_merge($collectedData, $processedData ?? []);
}
```

#### Giải Pháp 3: Hỗ Trợ Nhiều Format Placeholders

**Ý tưởng:**
1. Template có thể dùng nhiều format placeholders: `{{key}}`, `${key}`, `[key]`, `{key}`
2. Cần extract và map đúng với format trong template
3. Hỗ trợ cả uppercase, lowercase, Vietnamese variations

**Implementation:**

```php
// app/Services/ReportFileGenerator.php

protected function extractPlaceholdersFromTemplate(string $templatePath): array
{
    $placeholders = [];
    
    // 1. Extract từ TemplateProcessor (${key} format)
    $templateProcessor = new TemplateProcessor($templatePath);
    $variables = $templateProcessor->getVariables();
    foreach ($variables as $variable) {
        $normalized = preg_replace('/^\$\{?|\}?$/', '', $variable);
        $placeholders[$variable] = $normalized;
    }
    
    // 2. Extract từ XML trực tiếp (hỗ trợ [key], {{key}} formats)
    $zip = new \ZipArchive();
    if ($zip->open($templatePath) === true) {
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml) {
            // Extract [key] format
            if (preg_match_all('/\[([^\]]+)\]/', $documentXml, $matches)) {
                foreach (array_unique($matches[1]) as $match) {
                    $placeholder = '[' . trim($match) . ']';
                    $placeholders[$placeholder] = trim($match);
                }
            }
            
            // Extract {{key}} format
            if (preg_match_all('/\{\{([^}]+)\}\}/', $documentXml, $matches)) {
                foreach (array_unique($matches[1]) as $match) {
                    $placeholder = '{{' . trim($match) . '}}';
                    $placeholders[$placeholder] = trim($match);
                }
            }
        }
        $zip->close();
    }
    
    return $placeholders;
}

protected function prepareDataForTemplate(array $collectedData): array
{
    $data = [];
    
    foreach ($collectedData as $key => $value) {
        $cleanValue = $this->cleanValue($value);
        
        // Generate all placeholder variations
        $variations = [
            $key,
            str_replace('_', ' ', $key),
            str_replace('_', '-', $key),
            strtolower($key),
            strtoupper($key),
            ucfirst($key),
            ucwords(str_replace('_', ' ', $key)),
        ];
        
        foreach ($variations as $variation) {
            // Support multiple formats
            $data['{{' . $variation . '}}'] = $cleanValue;
            $data['${' . $variation . '}'] = $cleanValue;
            $data['{' . $variation . '}'] = $cleanValue;
            $data['[' . $variation . ']'] = $cleanValue;
            $data['[[' . $variation . ']]'] = $cleanValue;
        }
    }
    
    return $data;
}
```

### Khuyến Nghị

**Giải pháp tốt nhất:** **Giải pháp 1** (Đơn giản và hiệu quả nhất):
1. ✅ **Bỏ qua bước AI generate text mới** - không cần thiết
2. ✅ **Load template DOCX gốc** trực tiếp
3. ✅ **Map collected data** trực tiếp vào placeholders
4. ✅ **Sử dụng TemplateProcessor** để replace → giữ nguyên format
5. ✅ **Hỗ trợ nhiều format placeholders** ({{key}}, ${key}, [key])

**Nếu cần xử lý data phức tạp:** Kết hợp **Giải pháp 1** và **Giải pháp 2**:
1. Sử dụng AI để xử lý và format data (nếu cần)
2. Map processed data vào placeholders trong template
3. Giữ nguyên format của template DOCX gốc

### Lợi Ích Của Phương Án Mới

1. ✅ **Giữ nguyên 100% format** của template (font, size, color, bold, italic, table, header, footer)
2. ✅ **Đơn giản hơn** - không cần AI generate text mới
3. ✅ **Nhanh hơn** - không cần gọi AI để generate text
4. ✅ **Chính xác hơn** - điền đúng data vào đúng placeholders
5. ✅ **Dễ maintain** - code đơn giản, dễ hiểu
6. ✅ **Hỗ trợ nhiều format** - {{key}}, ${key}, [key], {key}

---

## Vấn Đề 2: Thiếu Button Download DOCX Trong Chatbox

### Mô Tả Vấn Đề

Khi báo cáo được tạo trong khung chatbox, component `ReportPreview` **có button download** nhưng có thể:
- Không hiển thị đúng cách
- Props không được truyền đúng
- Component không render khi có report data

### Nguyên Nhân Gốc Rễ

#### 1. **Report Data Structure Không Khớp**

**File:** `resources/js/Pages/Chat/IndexNew.vue` (Line 128-134)

```vue
<ReportPreview 
    :report-content="message.report.report_content"
    :report-id="message.report.report_id"
    :docx-url="message.report.report_file_path"
/>
```

**File:** `app/Services/ReportGenerator.php` (Line 94-98)

```php
return [
    'report_content' => $reportContent,
    'report_file_path' => $docxUrl,
    'report_id' => $userReport->id,
];
```

**Vấn đề:**
- Component expect `message.report.report_id` nhưng data có thể là `message.report.report_id` hoặc `message.report.id`
- Component expect `message.report.report_file_path` nhưng có thể là `message.report.report_file_path` hoặc `message.report.docx_url`

#### 2. **Report Data Không Được Pass Đúng Từ Stream**

**File:** `resources/js/Pages/Chat/IndexNew.vue` (Line 507-513)

```javascript
// Handle report data
(reportData, messageId) => {
    if (reportData) {
        assistantMessage.report = reportData;
        assistantMessage.id = messageId || assistantMessage.id;
        console.log('Report data received:', reportData);
    }
}
```

**Vấn đề:**
- Report data được set vào `assistantMessage.report`
- Nhưng khi reload messages, report data có thể nằm trong `metadata.report`
- Component có thể không tìm thấy report data

#### 3. **Component ReportPreview Có Điều Kiện Render**

**File:** `resources/js/Components/ReportPreview.vue` (Line 1-44)

```vue
<template>
    <div class="report-preview">
        <!-- Buttons -->
        <button @click="downloadReport('docx')">📥 DOCX</button>
        <button @click="downloadReport('pdf')">📥 PDF</button>
    </div>
</template>
```

**Vấn đề:**
- Component có buttons, nhưng có thể không render nếu:
  - `reportId` không có
  - `reportContent` không có
  - Component bị lỗi trong quá trình mount

### Cách Cải Thiện

#### Giải Pháp 1: Đảm Bảo Report Data Structure Đúng

**File:** `resources/js/Pages/Chat/IndexNew.vue`

```vue
<!-- Report Preview Component -->
<div v-if="message.report && message.sender === 'assistant'" class="mt-2">
    <ReportPreview 
        :report-content="message.report.report_content || message.report.content"
        :report-id="message.report.report_id || message.report.id || message.report_id"
        :docx-url="message.report.report_file_path || message.report.docx_url || message.report.file_path"
    />
</div>
```

#### Giải Pháp 2: Normalize Report Data Khi Load Messages

**File:** `resources/js/Pages/Chat/IndexNew.vue`

```javascript
const loadMessages = async () => {
    // ... existing code ...
    
    // Normalize report data structure
    messages.value.forEach(msg => {
        if (msg.metadata?.report) {
            // Convert metadata.report to msg.report
            msg.report = {
                report_id: msg.metadata.report.report_id || msg.metadata.report.id,
                report_content: msg.metadata.report.report_content || msg.metadata.report.content,
                report_file_path: msg.metadata.report.report_file_path || msg.metadata.report.docx_url || msg.metadata.report.file_path,
            };
        }
    });
};
```

#### Giải Pháp 3: Cải Thiện ReportPreview Component

**File:** `resources/js/Components/ReportPreview.vue`

```vue
<template>
    <div class="report-preview bg-white border border-gray-200 rounded-lg shadow-sm p-6 my-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">📄 Báo Cáo</h3>
            <div class="flex gap-2">
                <!-- Always show DOCX button if reportId exists -->
                <button
                    v-if="reportId"
                    @click="downloadReport('docx')"
                    :disabled="isGenerating"
                    class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Tải DOCX
                </button>
                <!-- PDF button (optional) -->
                <button
                    v-if="reportId"
                    @click="downloadReport('pdf')"
                    :disabled="isGenerating"
                    class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm flex items-center gap-1 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Tải PDF
                </button>
            </div>
        </div>
        
        <!-- Report content preview -->
        <div v-if="reportContent" class="report-content" v-html="formattedContent"></div>
        <div v-else class="text-gray-500">Đang tải nội dung báo cáo...</div>
    </div>
</template>

<script setup>
// ... existing code ...

// Ensure reportId is always available
const normalizedReportId = computed(() => {
    return props.reportId || props.report?.id || props.report?.report_id;
});

// Update downloadReport to use normalized ID
const downloadReport = async (format) => {
    if (!normalizedReportId.value) {
        alert('Không tìm thấy ID báo cáo. Vui lòng thử lại.');
        return;
    }
    
    isGenerating.value = true;
    
    try {
        const response = await fetch(`/api/reports/${normalizedReportId.value}/download?format=${format}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Failed to download');
        }
        
        // Download file
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `report_${normalizedReportId.value}.${format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (error) {
        console.error('Failed to download:', error);
        alert('Không thể tải file. Vui lòng thử lại.');
    } finally {
        isGenerating.value = false;
    }
};
</script>
```

### Khuyến Nghị

**Giải pháp tốt nhất:** Kết hợp cả 3 giải pháp:
1. Normalize report data structure khi load messages
2. Cải thiện ReportPreview component để handle các trường hợp edge case
3. Đảm bảo report data được pass đúng từ stream response

---

## Tổng Kết

### Vấn Đề 1: Format Báo Cáo
- **Nguyên nhân:** AI generate text, Template Processor không sử dụng AI content
- **Giải pháp:** Parse AI content → Map vào template placeholders → Giữ format

### Vấn Đề 2: Button Download
- **Nguyên nhân:** Report data structure không khớp, component không render đúng
- **Giải pháp:** Normalize data structure, cải thiện component error handling

### Ưu Tiên Triển Khai

1. **Cao:** Cải thiện format báo cáo (Vấn đề 1) - **Refactor theo Giải pháp 1**
   - Bỏ bước AI generate text mới
   - Sử dụng TemplateProcessor trực tiếp với collected data
   - Giữ nguyên format template
   
2. **Cao:** Fix button download (Vấn đề 2) - Dễ fix, ảnh hưởng trực tiếp UX
   - Normalize report data structure
   - Cải thiện ReportPreview component
   
3. **Trung bình:** Hỗ trợ nhiều format placeholders
   - Extract placeholders từ template ({{key}}, ${key}, [key])
   - Map data với các format variations
   
4. **Thấp:** Tối ưu performance và error handling
   - Caching template placeholders
   - Better error messages
   - Logging improvements

### Kế Hoạch Triển Khai

**Bước 1: Refactor ReportGenerator (CHỈ cho report_generator)**
- ✅ **Thêm check `assistant_type === 'report_generator'`** ở đầu method
- Bỏ `fillTemplateWithData()` - không cần AI generate text mới
- Simplify `generateReport()` - chỉ cần generate DOCX từ template
- Update flow: collected data → map vào placeholders → generate DOCX
- **Đảm bảo:** Chỉ được gọi khi `assistant_type === 'report_generator'`

**Bước 2: Cải thiện ReportFileGenerator (CHỈ cho report_generator)**
- ✅ **Thêm check `assistant_type === 'report_generator'`** ở đầu method
- Improve `extractPlaceholdersFromTemplate()` - hỗ trợ nhiều formats
- Improve `prepareDataForTemplate()` - tạo nhiều variations
- Improve `mapDataToTemplatePlaceholders()` - fuzzy matching tốt hơn
- **Đảm bảo:** Chỉ được gọi khi `assistant_type === 'report_generator'`

**Bước 3: Verify SmartAssistantEngine (Không ảnh hưởng Q&A)**
- ✅ **Verify `handleCreateReport()` chỉ gọi khi `assistant_type === 'report_generator'`**
- ✅ **Verify `handleAskQuestion()` không gọi ReportGenerator** (cho Q&A)
- ✅ **Verify Q&A flow hoạt động bình thường** sau khi refactor
- Test với cả 2 loại trợ lý: `report_generator` và `qa_based_document`

**Bước 4: Test và Verify**
- Test với template thực tế có placeholders (report_generator)
- Verify format giữ nguyên
- Verify data được điền đúng
- Test với nhiều format placeholders khác nhau
- **Test Q&A vẫn hoạt động bình thường** (không bị ảnh hưởng)

**Bước 5: Fix Frontend**
- Normalize report data structure
- Cải thiện ReportPreview component
- Test download DOCX button
- **Đảm bảo:** ReportPreview chỉ hiển thị cho `report_generator`

### Đảm Bảo Không Ảnh Hưởng Q&A

**Các điểm cần kiểm tra:**

1. ✅ **ReportGenerator chỉ được gọi khi `assistant_type === 'report_generator'`**
   - Check ở đầu `generateReport()`
   - Check ở đầu `generateDocxFromTemplate()`
   - Throw exception nếu không phải report_generator

2. ✅ **SmartAssistantEngine phân biệt rõ 2 loại trợ lý**
   - `handleCreateReport()` - chỉ cho report_generator
   - `handleAskQuestion()` - chỉ cho qa_based_document
   - Không gọi ReportGenerator cho Q&A

3. ✅ **ChatController check assistant_type trước khi gọi**
   - Line 293: `if ($session->aiAssistant->assistant_type === 'report_generator')`
   - Line 484: `if ($session->aiAssistant->assistant_type === 'report_generator')`
   - Đảm bảo chỉ gọi ReportGenerator cho report_generator

4. ✅ **Test cả 2 loại trợ lý**
   - Test report_generator: tạo báo cáo từ template
   - Test qa_based_document: trả lời Q&A từ documents
   - Verify Q&A không bị ảnh hưởng

**Code Example:**

```php
// app/Services/SmartAssistantEngine.php

protected function handleCreateReport(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent, array $workflow): array
{
    // ✅ QUAN TRỌNG: Chỉ xử lý cho report_generator
    if ($assistant->assistant_type !== 'report_generator') {
        Log::warning('handleCreateReport called for non-report_generator assistant', [
            'assistant_id' => $assistant->id,
            'assistant_type' => $assistant->assistant_type,
        ]);
        return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
    }
    
    // ... existing code để thu thập data ...
    
    if ($allCollected) {
        // Actually generate report (chỉ cho report_generator)
        try {
            $reportGenerator = app(ReportGenerator::class);
            $reportResult = $reportGenerator->generateReport(
                $assistant,
                $session,
                $collectedData
            );
            
            return [
                'response' => "Báo cáo đã được tạo thành công!",
                'workflow_state' => [
                    'current_step' => 'completed',
                    'workflow' => $workflow,
                ],
                'report' => $reportResult,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate report', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistant->assistant_type,
            ]);
            // ... error handling ...
        }
    }
}

protected function handleAskQuestion(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent): array
{
    // ✅ QUAN TRỌNG: Chỉ xử lý cho qa_based_document
    if ($assistant->assistant_type !== 'qa_based_document') {
        // Nếu không phải qa_based_document, fallback to generic request
        return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
    }
    
    // ... existing code cho Q&A ...
    // ✅ KHÔNG gọi ReportGenerator - chỉ dùng cho report_generator
    // Q&A chỉ cần search documents và generate answer
}
```

```php
// app/Http/Controllers/ChatController.php

// ✅ Check assistant_type trước khi gọi ReportGenerator
if ($session->aiAssistant->assistant_type === 'report_generator') {
    try {
        // Process with SmartAssistantEngine to generate report if needed
        $result = $this->assistantEngine->processMessage(
            $userMessage,
            $session,
            $session->aiAssistant
        );
        
        // Get report data if exists
        if (isset($result['report'])) {
            $reportData = $result['report'];
        }
    } catch (\Exception $e) {
        Log::warning('Failed to process with SmartAssistantEngine', [
            'error' => $e->getMessage(),
            'assistant_type' => $session->aiAssistant->assistant_type,
        ]);
    }
}
// ✅ Q&A assistants không vào block này - không bị ảnh hưởng
```

---

## Tài Liệu Tham Khảo

- PhpOffice PhpWord Documentation: https://phpword.readthedocs.io/
- TemplateProcessor API: https://github.com/PHPOffice/PHPWord/blob/develop/src/PhpWord/TemplateProcessor.php
- Mammoth.js (DOCX to HTML): https://github.com/mwilliamson/mammoth.js

