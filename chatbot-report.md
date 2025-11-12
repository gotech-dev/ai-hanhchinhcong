# Phân Tích Flow Tạo Báo Cáo Mới: AI-Generated Content với Template Format

## Tổng Quan

Báo cáo này phân tích flow hiện tại và đề xuất flow mới để tạo báo cáo với nội dung được AI generate nhưng vẫn giữ nguyên format của template.

## Flow Hiện Tại (Vấn Đề)

### Mô Tả Flow Hiện Tại

1. **User chat với trợ lý `report_generator`**
   - User: "Tạo báo cáo theo mẫu"
   - Intent recognition: `create_report`
   - Workflow planning: Thu thập data từ template fields

2. **Thu thập thông tin (Collect Data)**
   - System hỏi từng field trong template (tên công ty, năm, địa chỉ, v.v.)
   - User trả lời từng câu hỏi
   - Data được lưu vào `session.collected_data`

3. **Generate Report (Khi đủ data)**
   - Load template DOCX gốc
   - Map `collected_data` vào placeholders trong template
   - Replace placeholders → Giữ format template
   - Tạo file DOCX mới
   - Trả về `report_content` (text) và `report_file_path` (DOCX URL)

4. **Frontend hiển thị**
   - Component `ReportPreview` hiển thị report
   - Button download DOCX

### Vấn Đề Của Flow Hiện Tại

❌ **Chỉ replace placeholders, không tạo nội dung mới**
- Flow hiện tại chỉ điền data vào placeholders như `[TÊN CÔNG TY]`, `[NĂM]`
- Không có AI generate nội dung mới dựa trên yêu cầu của user
- Nếu template không có placeholders, báo cáo sẽ giống hệt template gốc

❌ **Không có preview trên web**
- Chỉ có button download DOCX
- User không thể xem preview trước khi download
- Không thể chỉnh sửa hoặc yêu cầu thay đổi

❌ **Flow cứng nhắc**
- Phải hỏi từng field một cách tuần tự
- Không linh hoạt với yêu cầu của user
- Không thể xử lý yêu cầu phức tạp

## Flow Mong Muốn

### Mô Tả Flow Mới

1. **User chat với trợ lý `report_generator`**
   - User: "Tạo báo cáo thường niên cho công ty ABC năm 2024"
   - AI hiểu yêu cầu và phân tích:
     - Loại báo cáo: Báo cáo thường niên
     - Công ty: ABC
     - Năm: 2024
     - Cần thêm thông tin gì?

2. **AI phân tích và thu thập thông tin**
   - AI phân tích template để hiểu cấu trúc
   - AI xác định thông tin còn thiếu
   - AI hỏi user về thông tin còn thiếu (nếu có)
   - User cung cấp thông tin bổ sung

3. **AI Generate Content Mới**
   - AI đọc template để hiểu format và cấu trúc
   - AI tạo nội dung mới dựa trên:
     - Yêu cầu của user
     - Thông tin đã thu thập
     - Format và cấu trúc của template
   - AI tạo nội dung hoàn chỉnh, không chỉ replace placeholders

4. **Map Content vào Template (Giữ Format)**
   - Parse AI-generated content
   - Map content vào template structure
   - Giữ nguyên format: font, size, color, bold, italic, table, header, footer
   - Tạo DOCX với format giống hệt template

5. **Preview trên Web**
   - Hiển thị preview DOCX trên web (convert DOCX → HTML)
   - User có thể xem và chỉnh sửa (nếu cần)
   - User có thể yêu cầu thay đổi: "Thêm phần về tài chính"

6. **Download DOCX**
   - User xác nhận và download DOCX
   - File DOCX có format đầy đủ

## Phân Tích Kỹ Thuật

### 1. AI Generate Content Mới

#### Vấn Đề
- Cần AI tạo nội dung mới, không chỉ replace placeholders
- Nội dung phải phù hợp với format và cấu trúc của template
- Cần hiểu context và yêu cầu của user

#### Giải Pháp

**Bước 1: Extract Template Structure**
```php
// app/Services/TemplateAnalyzer.php
public function analyzeTemplateStructure(string $templatePath): array
{
    // 1. Extract text từ template
    $text = $this->extractText($templatePath);
    
    // 2. Phân tích cấu trúc (headings, sections, tables, lists)
    $structure = [
        'sections' => $this->extractSections($text),
        'headings' => $this->extractHeadings($text),
        'tables' => $this->extractTables($text),
        'placeholders' => $this->extractPlaceholders($text),
        'format_info' => $this->extractFormatInfo($templatePath), // font, size, color, etc.
    ];
    
    return $structure;
}
```

**Bước 2: AI Generate Content với Template Context**
```php
// app/Services/ReportGenerator.php
protected function generateContentWithAI(
    string $userRequest,
    array $collectedData,
    array $templateStructure,
    AiAssistant $assistant
): string {
    $prompt = "Bạn là chuyên gia tạo báo cáo. Hãy tạo nội dung báo cáo dựa trên yêu cầu và template mẫu.\n\n";
    $prompt .= "YÊU CẦU CỦA USER:\n{$userRequest}\n\n";
    $prompt .= "THÔNG TIN ĐÃ THU THẬP:\n" . json_encode($collectedData, JSON_UNESCAPED_UNICODE) . "\n\n";
    $prompt .= "CẤU TRÚC TEMPLATE:\n";
    $prompt .= "- Sections: " . implode(', ', $templateStructure['sections']) . "\n";
    $prompt .= "- Headings: " . implode(', ', $templateStructure['headings']) . "\n";
    $prompt .= "- Placeholders: " . implode(', ', $templateStructure['placeholders']) . "\n\n";
    $prompt .= "YÊU CẦU:\n";
    $prompt .= "1. Tạo nội dung báo cáo hoàn chỉnh dựa trên yêu cầu\n";
    $prompt .= "2. Giữ nguyên cấu trúc và format của template\n";
    $prompt .= "3. Điền đầy đủ thông tin vào các phần\n";
    $prompt .= "4. Tạo nội dung phù hợp với từng section\n";
    
    $response = OpenAI::chat()->create([
        'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là chuyên gia tạo báo cáo chuyên nghiệp. Bạn tạo nội dung báo cáo dựa trên yêu cầu và template mẫu.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000,
    ]);
    
    return $response->choices[0]->message->content;
}
```

### 2. Map AI Content vào Template (Giữ Format)

#### Vấn Đề
- AI-generated content là text thuần túy
- Cần map vào template structure để giữ format
- Cần parse content để map vào đúng sections

#### Giải Pháp

**Bước 1: Parse AI-Generated Content**
```php
// app/Services/ReportContentParser.php
public function parseContent(string $aiContent, array $templateStructure): array
{
    // Parse content thành structured data
    $parsedContent = [
        'sections' => [],
        'data' => [],
    ];
    
    // Extract sections từ AI content
    foreach ($templateStructure['sections'] as $section) {
        $sectionContent = $this->extractSectionContent($aiContent, $section);
        $parsedContent['sections'][$section] = $sectionContent;
    }
    
    // Extract data points
    $parsedContent['data'] = $this->extractDataPoints($aiContent, $templateStructure['placeholders']);
    
    return $parsedContent;
}
```

**Bước 2: Map Content vào Template với Format**
```php
// app/Services/ReportFileGenerator.php
public function generateDocxWithAIContent(
    UserReport $report,
    AiAssistant $assistant,
    string $aiContent,
    array $parsedContent
): string {
    // 1. Load template
    $templatePath = $this->getTemplatePath($assistant->template_file_path);
    $templateProcessor = new TemplateProcessor($templatePath);
    
    // 2. Map parsed content vào placeholders
    foreach ($parsedContent['data'] as $key => $value) {
        $templateProcessor->setValue($key, $value);
    }
    
    // 3. Map sections vào template structure
    // Nếu template có sections, replace section content
    foreach ($parsedContent['sections'] as $section => $content) {
        // Replace section content trong template
        $this->replaceSectionContent($templateProcessor, $section, $content);
    }
    
    // 4. Save file
    $fileName = 'reports/report_' . $report->id . '_' . time() . '.docx';
    $filePath = storage_path('app/public/' . $fileName);
    $templateProcessor->saveAs($filePath);
    
    return Storage::disk('public')->url($fileName);
}
```

### 3. Preview trên Web

#### Vấn Đề
- Cần hiển thị preview DOCX trên web
- User cần xem trước khi download
- Có thể cần chỉnh sửa hoặc yêu cầu thay đổi

#### Giải Pháp

**Bước 1: Convert DOCX → HTML để Preview**
```javascript
// resources/js/Components/ReportPreview.vue
import mammoth from 'mammoth';

const loadDocxPreview = async (docxUrl) => {
    // Fetch DOCX file
    const response = await fetch(docxUrl);
    const arrayBuffer = await response.arrayBuffer();
    
    // Convert DOCX sang HTML bằng Mammoth.js
    const result = await mammoth.convertToHtml(
        { arrayBuffer },
        {
            styleMap: [
                "p[style-name='Heading 1'] => h1:fresh",
                "p[style-name='Heading 2'] => h2:fresh",
            ],
            convertImage: mammoth.images.imgElement,
        }
    );
    
    return result.value; // HTML content
};
```

**Bước 2: Hiển thị Preview với Edit Mode**
```vue
<template>
    <div class="report-preview">
        <!-- Preview DOCX -->
        <div class="preview-container" v-html="docxPreviewHtml"></div>
        
        <!-- Action buttons -->
        <div class="actions">
            <button @click="requestEdit">Yêu cầu chỉnh sửa</button>
            <button @click="downloadReport('docx')">Tải DOCX</button>
        </div>
    </div>
</template>
```

**Bước 3: Request Edit từ User**
```javascript
const requestEdit = () => {
    // User có thể chat: "Thêm phần về tài chính"
    // System sẽ regenerate content với yêu cầu mới
    emit('edit-request', {
        reportId: props.reportId,
        editRequest: 'Thêm phần về tài chính',
    });
};
```

### 4. Flow Hoàn Chỉnh

#### Sequence Diagram

```
User → Chat: "Tạo báo cáo thường niên cho công ty ABC năm 2024"
Chat → SmartAssistantEngine: processMessage()
SmartAssistantEngine → IntentRecognizer: recognize()
IntentRecognizer → SmartAssistantEngine: {type: 'create_report', ...}
SmartAssistantEngine → WorkflowPlanner: plan()
WorkflowPlanner → SmartAssistantEngine: {steps: [...], ...}

SmartAssistantEngine → TemplateAnalyzer: analyzeTemplateStructure()
TemplateAnalyzer → SmartAssistantEngine: {sections: [...], placeholders: [...], ...}

SmartAssistantEngine → ReportGenerator: generateContentWithAI()
ReportGenerator → OpenAI: Generate content
OpenAI → ReportGenerator: AI-generated content

ReportGenerator → ReportContentParser: parseContent()
ReportContentParser → ReportGenerator: {sections: {...}, data: {...}}

ReportGenerator → ReportFileGenerator: generateDocxWithAIContent()
ReportFileGenerator → ReportGenerator: DOCX URL

ReportGenerator → SmartAssistantEngine: {report_content, report_file_path, report_id}
SmartAssistantEngine → ChatController: {response, report, ...}
ChatController → Frontend: {report: {...}}

Frontend → ReportPreview: Display preview
User → ReportPreview: View preview
User → ReportPreview: Click "Tải DOCX"
ReportPreview → ReportController: download()
ReportController → User: Download DOCX file
```

## Implementation Plan

### Phase 1: AI Content Generation

1. **Tạo `TemplateAnalyzer` service**
   - Extract template structure
   - Analyze sections, headings, tables
   - Extract format information

2. **Cải thiện `ReportGenerator`**
   - Thêm method `generateContentWithAI()`
   - Sử dụng template structure để generate content
   - Parse AI-generated content

3. **Tạo `ReportContentParser` service**
   - Parse AI-generated content
   - Map content vào template structure
   - Extract data points

### Phase 2: Map Content vào Template

1. **Cải thiện `ReportFileGenerator`**
   - Thêm method `generateDocxWithAIContent()`
   - Map parsed content vào template
   - Giữ nguyên format

2. **Support section replacement**
   - Replace section content trong template
   - Giữ format của từng section

### Phase 3: Preview trên Web

1. **Cải thiện `ReportPreview` component**
   - Load và convert DOCX → HTML
   - Hiển thị preview đẹp
   - Support edit request

2. **Backend support edit request**
   - API endpoint để regenerate report
   - Xử lý edit request từ user

### Phase 4: Testing & Refinement

1. **Test với nhiều loại template**
   - Template đơn giản
   - Template phức tạp (nhiều sections, tables)
   - Template với placeholders

2. **Test với nhiều yêu cầu**
   - Yêu cầu đơn giản
   - Yêu cầu phức tạp
   - Yêu cầu chỉnh sửa

## Kết Luận

Flow mới sẽ:
- ✅ AI generate content mới dựa trên yêu cầu
- ✅ Giữ nguyên format của template
- ✅ Preview trên web trước khi download
- ✅ Support edit request từ user
- ✅ Linh hoạt và thông minh hơn

Flow này sẽ làm cho chức năng tạo báo cáo trở nên hữu ích và thực sự tạo ra giá trị cho user.






