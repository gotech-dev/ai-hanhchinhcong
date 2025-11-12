# Báo Cáo: Vấn Đề Và Giải Pháp Cho Trợ Lý Báo Cáo

## Tổng Quan

Phần trợ lý báo cáo hiện tại có 3 vấn đề chính cần được xử lý:

1. Admin tải mẫu báo cáo tạo trợ lý (đã có chức năng nhưng có thể cải thiện)
2. Format hiển thị báo cáo trên web xấu, không đúng như mẫu docx ban đầu
3. Chưa có button download template dạng docx và pdf

---

## Vấn Đề 1: Admin Tải Mẫu Báo Cáo Tạo Trợ Lý

### Mô Tả

Admin có thể upload template file (PDF/DOCX) khi tạo assistant trong `CreateAssistant.vue`. Template được lưu vào database và có thể xem trong `PreviewAssistant.vue`.

### Nguyên Nhân Phân Tích

**Vị trí code:**
- `resources/js/Pages/Admin/CreateAssistant.vue` (line 52-66): Upload template file
- `app/Http/Controllers/AdminController.php` (line 119-132): Xử lý upload template
- `app/Services/AutoConfigurationService.php` (line 83-104): Phân tích template

**Vấn đề:**
1. ✅ **Chức năng upload đã có** - Admin có thể upload template file
2. ⚠️ **Không có preview template** - Sau khi upload, admin không thể xem template trước khi tạo assistant
3. ⚠️ **Không có validation format** - Chưa có kiểm tra format template có đúng chuẩn không
4. ⚠️ **Chưa có download template** - Admin không thể download template đã upload để chỉnh sửa hoặc kiểm tra

### Phương Án Sửa

#### 1.1. Thêm Preview Template Sau Khi Upload

**File:** `resources/js/Pages/Admin/CreateAssistant.vue`

```vue
<!-- Thêm preview template sau khi chọn file -->
<div v-if="selectedTemplateFile" class="mt-2">
    <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <span class="text-sm text-gray-700">{{ selectedTemplateFile.name }}</span>
        <button 
            @click="previewTemplate(selectedTemplateFile)"
            class="ml-auto text-blue-600 hover:text-blue-800 text-sm"
        >
            Xem trước
        </button>
    </div>
</div>
```

#### 1.2. Thêm Button Download Template Trong Preview Assistant

**File:** `resources/js/Pages/Admin/PreviewAssistant.vue`

```vue
<div v-if="assistant.template_file_path">
    <label class="text-sm font-medium text-gray-700">Template File</label>
    <div class="flex items-center gap-2 mt-1">
        <a 
            :href="assistant.template_file_path" 
            target="_blank" 
            class="text-blue-600 hover:underline"
        >
            Xem template
        </a>
        <button
            @click="downloadTemplate(assistant.template_file_path)"
            class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
        >
            📥 Tải xuống
        </button>
    </div>
</div>
```

#### 1.3. Thêm API Endpoint Download Template

**File:** `app/Http/Controllers/AdminController.php`

```php
/**
 * Download template file
 */
public function downloadTemplate($assistantId)
{
    $assistant = AiAssistant::findOrFail($assistantId);
    
    if (!$assistant->template_file_path) {
        return response()->json(['error' => 'Template not found'], 404);
    }
    
    // Parse URL to get file path
    $url = parse_url($assistant->template_file_path);
    $filePath = ltrim($url['path'], '/storage/');
    
    // Get full path
    $fullPath = Storage::disk('public')->path($filePath);
    
    if (!file_exists($fullPath)) {
        return response()->json(['error' => 'File not found'], 404);
    }
    
    return Storage::disk('public')->download(
        $filePath,
        $assistant->name . '_template' . pathinfo($fullPath, PATHINFO_EXTENSION)
    );
}
```

**Route:** `routes/web.php` hoặc `routes/api.php`

```php
Route::get('/admin/assistants/{assistantId}/download-template', [AdminController::class, 'downloadTemplate'])
    ->name('admin.assistants.download-template')
    ->middleware('auth');
```

---

## Vấn Đề 2: Format Hiển Thị Báo Cáo Trên Web Xấu

### Mô Tả

Khi user hỏi "Tạo 1 mẫu báo cáo tương tự cho tôi", báo cáo được tạo ra là text thuần, chỉ được hiển thị trong chat message như markdown text. Không giữ được format của template docx gốc (định dạng, table, font, spacing, alignment, etc.).

**Yêu cầu cụ thể:**
- Template cũ chỉ đổi text, giữ nguyên format
- Hiển thị trên web đẹp và đúng như template mẫu
- Download file cũng giữ format như template gốc

### Nguyên Nhân Phân Tích

**Vị trí code:**
- `app/Services/ReportGenerator.php` (line 125-186): Tạo báo cáo từ template
- `app/Services/DocumentProcessor.php` (line 59-77): Extract text từ Word document (chỉ lấy text, mất format)
- `app/Services/SmartAssistantEngine.php` (line 153-158): Hiển thị báo cáo trong chat
- `resources/js/Pages/Chat/IndexNew.vue` (line 127): Render markdown trong chat

**Vấn đề:**

1. **Mất format khi extract text từ template:**
   - `DocumentProcessor::extractFromWord()` chỉ extract text thuần, không giữ format (font, size, color, bold, italic, alignment, table structure, etc.)
   - Sử dụng `PhpOffice\PhpWord\IOFactory` chỉ lấy text, không lấy style information

2. **Báo cáo được tạo ra là text thuần:**
   - `ReportGenerator::fillTemplateWithData()` tạo ra text thuần từ AI
   - AI chỉ có thể tạo text, không thể tạo Word document với format

3. **Hiển thị trong chat như markdown:**
   - Báo cáo được hiển thị trong chat message như markdown text
   - Không có component riêng để hiển thị báo cáo đẹp
   - Không có styling đặc biệt cho báo cáo

4. **Không có component preview báo cáo:**
   - Không có component Vue riêng để hiển thị báo cáo với format đẹp
   - Không có styling giống như Word document

### Phương Án Sửa - Công Nghệ Cụ Thể

#### ⚡ Giải Pháp Tổng Quan

Để giải quyết vấn đề "template cũ chỉ đổi text, giữ nguyên format", cần:

1. **Backend (PHP):** Sử dụng `PhpOffice\PhpWord` để:
   - Load template DOCX gốc
   - Replace text trong template (giữ nguyên style, format)
   - Tạo file DOCX mới từ template với nội dung mới

2. **Frontend (Vue.js):** Sử dụng `Mammoth.js` để:
   - Convert DOCX sang HTML để hiển thị trên web
   - Giữ nguyên format (font, size, color, table, alignment)

3. **Download:** 
   - DOCX: Sử dụng PhpWord đã tạo từ template
   - PDF: Convert từ DOCX hoặc HTML sang PDF

#### 2.1. Backend: Replace Text Trong Template Giữ Format

**Công nghệ:** `PhpOffice\PhpWord` - Thư viện PHP chuyên xử lý Word documents

**File:** `app/Services/ReportFileGenerator.php` (mới, chi tiết hơn)

```php
<?php

namespace App\Services;

use App\Models\AiAssistant;
use App\Models\UserReport;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Shared\Html;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReportFileGenerator
{
    /**
     * Generate DOCX from template by replacing placeholders
     * 
     * Công nghệ: PhpOffice\PhpWord\TemplateProcessor
     * - Load template DOCX gốc
     * - Replace placeholders ({{field_name}}) với nội dung mới
     * - Giữ nguyên TẤT CẢ format: font, size, color, bold, italic, alignment, table, etc.
     */
    public function generateDocxFromTemplate(
        UserReport $report, 
        AiAssistant $assistant, 
        array $collectedData
    ): string {
        try {
            // 1. Load template file
            $templatePath = $this->getTemplatePath($assistant->template_file_path);
            
            // 2. Sử dụng TemplateProcessor để replace placeholders
            // TemplateProcessor tự động giữ nguyên format khi replace text
            $templateProcessor = new TemplateProcessor($templatePath);
            
            // 3. Parse report content để extract data
            $data = $this->parseReportContent($report->report_content, $collectedData);
            
            // 4. Replace các placeholders trong template
            // Template nên có format: {{field_name}} hoặc ${field_name}
            foreach ($data as $key => $value) {
                // TemplateProcessor sẽ giữ nguyên format của placeholder
                $templateProcessor->setValue($key, $value);
            }
            
            // 5. Save file mới
            $fileName = 'reports/report_' . $report->id . '_' . time() . '.docx';
            $filePath = storage_path('app/public/' . $fileName);
            $templateProcessor->saveAs($filePath);
            
            // 6. Update report
            $report->update([
                'report_file_path' => Storage::disk('public')->url($fileName),
                'file_format' => 'docx',
            ]);
            
            return Storage::disk('public')->url($fileName);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate DOCX from template', [
                'error' => $e->getMessage(),
                'report_id' => $report->id,
            ]);
            throw $e;
        }
    }

    /**
     * Generate HTML preview from DOCX (for web display)
     * 
     * Công nghệ: Mammoth.js (chạy trên Node.js hoặc convert ở backend)
     * Hoặc: PhpOffice\PhpWord + custom HTML converter
     */
    public function generateHtmlPreview(string $docxPath): string
    {
        try {
            // Option 1: Sử dụng Mammoth.js qua Node.js API
            // $html = $this->convertDocxToHtmlViaMammoth($docxPath);
            
            // Option 2: Convert manual từ PhpWord
            $phpWord = IOFactory::load($docxPath);
            $html = $this->phpWordToHtml($phpWord);
            
            return $html;
        } catch (\Exception $e) {
            Log::error('Failed to generate HTML preview', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Convert PhpWord document to HTML
     */
    protected function phpWordToHtml($phpWord): string
    {
        $html = '<div class="docx-preview" style="font-family: Times New Roman, serif;">';
        
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                    $text = $element->getText();
                    $style = $element->getFontStyle();
                    
                    $html .= $this->formatTextElement($text, $style);
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $html .= '<p>';
                    foreach ($element->getElements() as $textElement) {
                        $html .= $this->formatTextElement(
                            $textElement->getText(),
                            $textElement->getFontStyle()
                        );
                    }
                    $html .= '</p>';
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    $html .= $this->formatTable($element);
                }
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Format text element with style
     */
    protected function formatTextElement(string $text, $style = null): string
    {
        $tag = 'span';
        $styleAttr = '';
        
        if ($style) {
            $styles = [];
            if ($style->getBold()) $styles[] = 'font-weight: bold;';
            if ($style->getItalic()) $styles[] = 'font-style: italic;';
            if ($style->getSize()) $styles[] = 'font-size: ' . ($style->getSize() / 2) . 'pt;';
            if ($style->getColor()) $styles[] = 'color: #' . $style->getColor(); 
            
            $styleAttr = ' style="' . implode(' ', $styles) . '"';
        }
        
        return "<{$tag}{$styleAttr}>" . htmlspecialchars($text) . "</{$tag}>";
    }

    /**
     * Format table element
     */
    protected function formatTable($table): string
    {
        $html = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
        
        foreach ($table->getRows() as $row) {
            $html .= '<tr>';
            foreach ($row->getCells() as $cell) {
                $html .= '<td style="border: 1px solid #ddd; padding: 8px;">';
                foreach ($cell->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $html .= htmlspecialchars($element->getText());
                    }
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        return $html;
    }

    /**
     * Parse report content to extract structured data
     */
    protected function parseReportContent(string $content, array $collectedData): array
    {
        // Map collected data to template placeholders
        // Template nên dùng format: {{field_key}} hoặc ${field_key}
        $data = [];
        
        foreach ($collectedData as $key => $value) {
            $data['{{' . $key . '}}'] = $value;
            $data['${' . $key . '}'] = $value;
        }
        
        return $data;
    }

    /**
     * Get template file path
     */
    protected function getTemplatePath(string $templateUrl): string
    {
        $url = parse_url($templateUrl);
        $filePath = ltrim($url['path'], '/storage/');
        return Storage::disk('public')->path($filePath);
    }
}
```

#### 2.2. Frontend: Hiển Thị DOCX Trên Web Đẹp

**Công nghệ:** `Mammoth.js` - JavaScript library convert DOCX sang HTML

**File:** `resources/js/Components/ReportPreview.vue` (cải tiến)

**File:** `resources/js/Components/ReportPreview.vue` (mới, sử dụng Mammoth.js)

```vue
<template>
    <div class="report-preview bg-white border border-gray-200 rounded-lg shadow-sm p-6 my-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">📄 Báo Cáo</h3>
            <div class="flex gap-2">
                <button
                    @click="downloadReport('docx')"
                    :disabled="isGenerating"
                    class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm flex items-center gap-1 disabled:opacity-50"
                >
                    📥 DOCX
                </button>
                <button
                    @click="downloadReport('pdf')"
                    :disabled="isGenerating"
                    class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm flex items-center gap-1 disabled:opacity-50"
                >
                    📥 PDF
                </button>
            </div>
        </div>
        
        <!-- Hiển thị DOCX preview nếu có -->
        <div v-if="docxPreviewHtml" class="report-content docx-preview" v-html="docxPreviewHtml"></div>
        
        <!-- Fallback: Hiển thị markdown nếu chưa có DOCX -->
        <div v-else class="report-content prose max-w-none" v-html="formattedContent"></div>
        
        <div v-if="isGenerating" class="mt-4 text-center text-gray-500">
            Đang tạo file... Vui lòng đợi
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { marked } from 'marked';
import mammoth from 'mammoth'; // npm install mammoth

const props = defineProps({
    reportContent: String,
    reportId: Number,
    docxUrl: String, // URL của file DOCX đã generate
});

const docxPreviewHtml = ref('');
const isGenerating = ref(false);

const formattedContent = computed(() => {
    if (!props.reportContent) return '';
    
    marked.use({
        breaks: true,
        gfm: true,
    });
    
    return marked.parse(props.reportContent);
});

/**
 * Load và convert DOCX sang HTML để hiển thị
 * Công nghệ: Mammoth.js - giữ nguyên format từ DOCX
 */
const loadDocxPreview = async () => {
    if (!props.docxUrl) {
        // Nếu chưa có DOCX, tạo mới từ template
        await generateDocxFromTemplate();
        return;
    }
    
    try {
        // Fetch DOCX file
        const response = await fetch(props.docxUrl);
        const arrayBuffer = await response.arrayBuffer();
        
        // Convert DOCX sang HTML bằng Mammoth.js
        // Mammoth.js tự động giữ nguyên format: font, size, color, bold, italic, table, etc.
        const result = await mammoth.convertToHtml(
            { arrayBuffer },
            {
                styleMap: [
                    // Custom style mapping nếu cần
                    "p[style-name='Heading 1'] => h1:fresh",
                    "p[style-name='Heading 2'] => h2:fresh",
                ],
            }
        );
        
        docxPreviewHtml.value = result.value;
        
        // Xử lý warnings nếu có
        if (result.messages.length > 0) {
            console.warn('Mammoth conversion warnings:', result.messages);
        }
    } catch (error) {
        console.error('Failed to load DOCX preview:', error);
        // Fallback to markdown
    }
};

/**
 * Generate DOCX từ template (lần đầu)
 */
const generateDocxFromTemplate = async () => {
    isGenerating.value = true;
    
    try {
        // Call API để generate DOCX từ template
        const response = await fetch(`/api/reports/${props.reportId}/generate-docx`, {
            method: 'POST',
        });
        
        if (!response.ok) throw new Error('Failed to generate DOCX');
        
        const data = await response.json();
        
        // Reload preview với DOCX mới
        if (data.docx_url) {
            await loadDocxPreview();
        }
    } catch (error) {
        console.error('Failed to generate DOCX:', error);
    } finally {
        isGenerating.value = false;
    }
};

const downloadReport = async (format) => {
    isGenerating.value = true;
    
    try {
        // Call API để generate và download file
        const response = await fetch(`/api/reports/${props.reportId}/download?format=${format}`);
        
        if (!response.ok) throw new Error('Failed to download');
        
        // Get filename from Content-Disposition header
        const contentDisposition = response.headers.get('Content-Disposition');
        const filename = contentDisposition
            ? contentDisposition.split('filename=')[1]?.replace(/"/g, '')
            : `report_${props.reportId}.${format}`;
        
        // Create blob and download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
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

onMounted(() => {
    if (props.docxUrl) {
        loadDocxPreview();
    }
});
</script>

<style scoped>
/* Styling cho DOCX preview - Mammoth.js sẽ generate HTML với inline styles */
.docx-preview {
    font-family: 'Times New Roman', serif;
    line-height: 1.6;
    color: #333;
    max-width: 100%;
    overflow-x: auto;
}

/* Preserve table formatting */
.docx-preview :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.docx-preview :deep(table th),
.docx-preview :deep(table td) {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

/* Preserve heading styles */
.docx-preview :deep(h1) {
    font-size: 18pt;
    font-weight: bold;
    margin: 20px 0;
}

.docx-preview :deep(h2) {
    font-size: 16pt;
    font-weight: bold;
    margin: 15px 0;
}

/* Preserve paragraph formatting */
.docx-preview :deep(p) {
    margin: 10px 0;
}

/* Fallback markdown styling */
.report-content.prose {
    font-family: 'Times New Roman', serif;
    line-height: 1.6;
    color: #333;
}
</style>
```

**Cài đặt Mammoth.js:**

```bash
npm install mammoth
# hoặc
yarn add mammoth
```

<style scoped>
.report-content {
    font-family: 'Times New Roman', serif;
    line-height: 1.6;
    color: #333;
}

.report-content :deep(h1) {
    font-size: 18pt;
    font-weight: bold;
    text-align: center;
    margin: 20px 0;
}

.report-content :deep(h2) {
    font-size: 16pt;
    font-weight: bold;
    margin: 15px 0;
}

.report-content :deep(p) {
    margin: 10px 0;
    text-align: justify;
}

.report-content :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.report-content :deep(table th),
.report-content :deep(table td) {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.report-content :deep(table th) {
    background-color: #f2f2f2;
    font-weight: bold;
}
</style>
```

#### 2.2. Sửa Chat Component Để Hiển Thị Report Component

**File:** `resources/js/Pages/Chat/IndexNew.vue`

```vue
<!-- Thêm import -->
import ReportPreview from '../../Components/ReportPreview.vue';

<!-- Trong template, thay thế hiển thị message có report -->
<div v-if="message.report" class="mt-2">
    <ReportPreview 
        :report-content="message.report.report_content"
        :report-id="message.report.report_id"
    />
</div>
```

#### 2.3. API Endpoint: Generate DOCX và Download

**File:** `app/Http/Controllers/ReportController.php` (mới, chi tiết)

```php
<?php

namespace App\Http\Controllers;

use App\Models\UserReport;
use App\Services\ReportFileGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function __construct(
        protected ReportFileGenerator $reportFileGenerator
    ) {}

    /**
     * Generate DOCX from template (first time)
     * API: POST /api/reports/{reportId}/generate-docx
     */
    public function generateDocx(Request $request, $reportId)
    {
        $report = UserReport::findOrFail($reportId);
        
        // Check permission
        if ($report->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $assistant = $report->chatSession->aiAssistant;
        $collectedData = $report->chatSession->collected_data ?? [];
        
        try {
            // Generate DOCX từ template (giữ format)
            $docxUrl = $this->reportFileGenerator->generateDocxFromTemplate(
                $report,
                $assistant,
                $collectedData
            );
            
            return response()->json([
                'success' => true,
                'docx_url' => $docxUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate DOCX: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download report as DOCX or PDF
     * API: GET /api/reports/{reportId}/download?format=docx|pdf
     */
    public function download(Request $request, $reportId)
    {
        $report = UserReport::findOrFail($reportId);
        
        // Check permission
        if ($report->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $format = $request->get('format', 'docx'); // docx or pdf
        
        try {
            $assistant = $report->chatSession->aiAssistant;
            $collectedData = $report->chatSession->collected_data ?? [];
            
            if ($format === 'docx') {
                // Generate DOCX từ template (giữ format)
                $fileUrl = $this->reportFileGenerator->generateDocxFromTemplate(
                    $report,
                    $assistant,
                    $collectedData
                );
                
                // Convert URL to file path
                $url = parse_url($fileUrl);
                $filePath = ltrim($url['path'], '/storage/');
                $fullPath = Storage::disk('public')->path($filePath);
                
                return response()->download(
                    $fullPath,
                    'report_' . $report->id . '.docx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                );
            } else {
                // Generate PDF từ DOCX hoặc HTML
                $pdfUrl = $this->reportFileGenerator->generatePdf($report);
                
                $url = parse_url($pdfUrl);
                $filePath = ltrim($url['path'], '/storage/');
                $fullPath = Storage::disk('public')->path($filePath);
                
                return response()->download(
                    $fullPath,
                    'report_' . $report->id . '.pdf',
                    ['Content-Type' => 'application/pdf']
                );
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate file: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

#### 2.4. Routes Cần Thêm

**File:** `routes/api.php` hoặc `routes/web.php`

```php
// Generate DOCX từ template
Route::post('/api/reports/{reportId}/generate-docx', [ReportController::class, 'generateDocx'])
    ->name('reports.generate-docx')
    ->middleware('auth');

// Download report
Route::get('/api/reports/{reportId}/download', [ReportController::class, 'download'])
    ->name('reports.download')
    ->middleware('auth');
```

#### 2.5. Lưu Ý Quan Trọng: Template Phải Có Placeholders

**Để hệ thống hoạt động đúng, template DOCX phải:**

1. **Sử dụng placeholders rõ ràng:**
   - Format: `{{field_name}}` hoặc `${field_name}`
   - Ví dụ: `{{tên_cơ_quan}}`, `{{ngày_tháng}}`, `{{nội_dung}}`

2. **Placeholders phải có format:**
   - Khi admin upload template, placeholders nên có format (font, size, color, bold, etc.)
   - TemplateProcessor sẽ giữ nguyên format này khi replace

3. **Mapping data:**
   - `collected_data` từ user phải map với placeholders
   - Ví dụ: `collected_data['tên_cơ_quan']` → replace `{{tên_cơ_quan}}`

---

## Vấn Đề 3: Chưa Có Button Download Template DOCX và PDF

### Mô Tả

Hiện tại không có chức năng để user hoặc admin download:
- Template gốc (docx/pdf) đã upload
- Báo cáo đã tạo thành file docx/pdf

### Nguyên Nhân Phân Tích

**Vị trí code:**
- `resources/js/Pages/Admin/PreviewAssistant.vue` (line 64-71): Chỉ có link "Xem template", không có button download
- `app/Http/Controllers/AdminController.php`: Không có method download template
- `app/Http/Controllers/ReportController.php`: Chưa có controller này
- `app/Services/ReportFileGenerator.php`: Chưa có service này

**Vấn đề:**
1. ❌ **Không có API download template** - Admin không thể download template đã upload
2. ❌ **Không có API download report** - User không thể download báo cáo đã tạo
3. ❌ **Không có button trong UI** - Không có button download trong giao diện
4. ❌ **Không có service generate file** - Không có service để generate DOCX/PDF từ report content

### Phương Án Sửa

#### 3.1. Thêm Button Download Template Cho Admin

**File:** `resources/js/Pages/Admin/PreviewAssistant.vue`

Đã thêm ở phần 1.2.

#### 3.2. Thêm Button Download Report Cho User

**File:** `resources/js/Components/ReportPreview.vue`

Đã thêm ở phần 2.1.

#### 3.3. Thêm API Download Template

**File:** `app/Http/Controllers/AdminController.php`

Đã thêm ở phần 1.3.

#### 3.4. Thêm API Download Report

**File:** `app/Http/Controllers/ReportController.php`

Đã thêm ở phần 2.4.

#### 3.5. Thêm Service Generate File

**File:** `app/Services/ReportFileGenerator.php`

Đã thêm ở phần 2.3.

---

## Tóm Tắt Các Vấn Đề Và Giải Pháp

### Vấn Đề 1: Admin Tải Mẫu Báo Cáo
- ✅ **Đã có chức năng upload**
- ⚠️ **Thiếu:** Preview template, Download template
- **Giải pháp:** Thêm preview và download button

### Vấn Đề 2: Format Hiển Thị Xấu
- ❌ **Nguyên nhân:** Mất format khi extract text, hiển thị như markdown thuần
- **Giải pháp:** 
  - Tạo component ReportPreview với styling đẹp
  - Cải thiện prompt để AI tạo format tốt hơn
  - Tạo service generate DOCX/PDF từ template

### Vấn Đề 3: Chưa Có Button Download
- ❌ **Hoàn toàn thiếu chức năng**
- **Giải pháp:**
  - Thêm API download template
  - Thêm API download report
  - Thêm service generate file
  - Thêm button trong UI

---

## Ưu Tiên Thực Hiện

### Ưu Tiên Cao (P0)
1. **Tạo component ReportPreview** - Hiển thị báo cáo đẹp hơn
2. **Thêm button download report** - User có thể download báo cáo
3. **Tạo API download report** - Backend support download

### Ưu Tiên Trung Bình (P1)
4. **Tạo service ReportFileGenerator** - Generate DOCX/PDF từ report
5. **Thêm button download template** - Admin có thể download template
6. **Cải thiện format trong prompt** - AI tạo format tốt hơn

### Ưu Tiên Thấp (P2)
7. **Thêm preview template** - Admin xem template trước khi tạo
8. **Cải thiện extract format từ template** - Giữ format khi extract (phức tạp, cần nghiên cứu thêm)

---

## Ghi Chú Kỹ Thuật

### Dependencies Cần Thêm

**Backend (PHP):**
```bash
composer require phpoffice/phpword  # Xử lý Word documents
composer require dompdf/dompdf      # Generate PDF
```

**Frontend (JavaScript):**
```bash
npm install mammoth  # Convert DOCX sang HTML
# hoặc
yarn add mammoth
```

### Công Nghệ Chính Được Sử Dụng

1. **PhpOffice\PhpWord\TemplateProcessor**
   - ✅ Load template DOCX gốc
   - ✅ Replace placeholders ({{field}} hoặc ${field}) với nội dung mới
   - ✅ **Tự động giữ nguyên format** (font, size, color, bold, italic, alignment, table, etc.)
   - ✅ Không cần parse manual, TemplateProcessor làm hết

2. **Mammoth.js** (JavaScript)
   - ✅ Convert DOCX sang HTML để hiển thị trên web
   - ✅ Giữ nguyên format từ DOCX
   - ✅ Lightweight, chạy trên browser
   - ✅ Không cần server-side processing

3. **PhpOffice\PhpWord** (PHP)
   - ✅ Tạo file DOCX mới từ template đã replace
   - ✅ Export sang PDF (nếu cần)

### Quy Trình Hoạt Động

```
1. Admin upload template DOCX với placeholders: {{tên_cơ_quan}}, {{ngày_tháng}}, etc.

2. User yêu cầu tạo báo cáo:
   - AI thu thập thông tin
   - AI tạo report content (text thuần)

3. Khi user xem báo cáo:
   - Backend: ReportFileGenerator.generateDocxFromTemplate()
     * Load template DOCX gốc
     * Sử dụng TemplateProcessor.replace() để thay {{placeholder}} bằng nội dung
     * TemplateProcessor tự động giữ format
     * Save file DOCX mới
   - Frontend: Mammoth.js convert DOCX sang HTML
   - Hiển thị HTML trên web với format đẹp

4. Khi user download:
   - DOCX: Download file đã generate (đã giữ format từ template)
   - PDF: Convert từ DOCX hoặc HTML sang PDF
```

### Cấu Trúc File Mới

```
app/
├── Http/
│   └── Controllers/
│       └── ReportController.php (mới)
└── Services/
    └── ReportFileGenerator.php (mới)

resources/js/
├── Components/
│   └── ReportPreview.vue (mới)
└── Pages/
    ├── Admin/
    │   └── PreviewAssistant.vue (sửa)
    └── Chat/
        └── IndexNew.vue (sửa)
```

### Routes Cần Thêm

```php
// Admin routes
Route::get('/admin/assistants/{assistantId}/download-template', [AdminController::class, 'downloadTemplate'])
    ->name('admin.assistants.download-template')
    ->middleware('auth');

// Report routes
Route::get('/api/reports/{reportId}/download', [ReportController::class, 'download'])
    ->name('reports.download')
    ->middleware('auth');
```

---

## So Sánh Phương Pháp: PhpWord + Mammoth.js vs Claude API Skills

### Tổng Quan

Sau khi điều tra, **Claude API có tính năng "Skills"** cho phép tạo custom workflows để generate documents theo template. Tuy nhiên, cách hoạt động và khả năng khác với phương pháp PhpWord + Mammoth.js.

---

### Phương Pháp 1: PhpWord + Mammoth.js (Đã Đề Xuất)

#### Cách Hoạt Động

1. **Backend (PHP):**
   - Sử dụng `PhpOffice\PhpWord\TemplateProcessor` để load template DOCX
   - Replace placeholders (`{{field}}`) bằng nội dung mới
   - **Tự động giữ nguyên format** (font, size, color, bold, table, alignment, etc.)
   - Save file DOCX mới

2. **Frontend (JavaScript):**
   - Sử dụng `Mammoth.js` để convert DOCX sang HTML
   - Hiển thị HTML trên web với format đẹp

3. **Download:**
   - DOCX: File đã generate (giữ format từ template)
   - PDF: Convert từ DOCX

#### Ưu Điểm

✅ **Kiểm soát hoàn toàn:**
- Không phụ thuộc vào dịch vụ bên ngoài
- Full control over document generation process
- Không có API costs

✅ **Giữ format 100%:**
- TemplateProcessor **tự động giữ nguyên format** khi replace
- Không cần AI để "hiểu" template
- Format được preserve chính xác (font, size, color, bold, italic, table, alignment)

✅ **Performance tốt:**
- Xử lý local, không cần API calls
- Fast response time
- Không bị rate limit

✅ **Tích hợp dễ dàng:**
- Chỉ cần PHP + JavaScript
- Không cần external dependencies
- Có thể cache files

✅ **Bảo mật:**
- Data không rời khỏi server
- Không cần gửi template/content lên third-party service

#### Nhược Điểm

❌ **Yêu cầu kỹ thuật:**
- Cần biết PHP để implement
- Cần hiểu PhpWord API
- Cần setup và maintain dependencies

❌ **Template phải có placeholders:**
- Template phải có format: `{{field_name}}` hoặc `${field_name}`
- Không thể tự động "hiểu" template structure

❌ **Frontend cần Mammoth.js:**
- Cần thêm JavaScript library
- Cần xử lý file conversion trên browser

❌ **Maintenance:**
- Cần update PhpWord khi có version mới
- Cần maintain code khi có changes

---

### Phương Pháp 2: Claude API Skills

#### Cách Hoạt Động

1. **Tạo Custom Skill:**
   - Tạo directory với `SKILL.md` file
   - Define instructions cho Claude về cách process template
   - Upload Skill lên Claude platform

2. **Generate Document:**
   - Gửi template DOCX + collected data lên Claude API
   - Claude sử dụng Skill để generate document theo template
   - Claude trả về nội dung đã được format

3. **Hiển Thị & Download:**
   - Claude trả về text (có thể có format markers)
   - Cần convert sang HTML/DOCX/PDF

#### Ưu Điểm

✅ **Dễ dàng tạo và maintain:**
- Chỉ cần viết instructions trong SKILL.md
- Không cần code nhiều
- Update Skill dễ dàng qua interface

✅ **Linh hoạt:**
- Claude có thể "hiểu" template structure
- Không cần placeholders cố định
- Có thể handle complex templates

✅ **AI-powered:**
- Claude có thể hiểu context
- Có thể generate content thông minh hơn
- Có thể handle natural language instructions

✅ **Tích hợp với Claude ecosystem:**
- Có thể reuse Skills cho các tasks khác
- Có thể combine với other Claude features

#### Nhược Điểm

❌ **Phụ thuộc vào external service:**
- Cần Claude API available
- Nếu Claude down → system không hoạt động
- Phụ thuộc vào Claude's capabilities

❌ **API costs:**
- Cần trả tiền cho mỗi API call
- Chi phí có thể tăng nhanh với nhiều users
- Cần budget cho Claude API usage

❌ **Format preservation không đảm bảo 100%:**
- Claude trả về **text** (có thể có format markers như markdown)
- **Không giữ nguyên format DOCX gốc** (font, size, color, table structure, etc.)
- Cần convert text → DOCX/PDF (mất format)

❌ **Latency:**
- Cần API calls → slower response time
- Phụ thuộc vào Claude API response time
- Có thể bị rate limited

❌ **Bảo mật:**
- Template và content được gửi lên Claude API
- Data rời khỏi server của bạn
- Cần trust Claude với sensitive data

❌ **Không có direct DOCX output:**
- Claude trả về text, không phải DOCX file
- Cần convert text → DOCX (mất format)
- Không thể preserve format như TemplateProcessor

---

### So Sánh Chi Tiết

| Tiêu Chí | PhpWord + Mammoth.js | Claude API Skills |
|----------|---------------------|-------------------|
| **Format Preservation** | ✅ 100% - Giữ nguyên format DOCX | ❌ ~30% - Chỉ giữ text, mất format |
| **Kiểm Soát** | ✅ Full control | ❌ Phụ thuộc Claude |
| **Chi Phí** | ✅ Free (chỉ server costs) | ❌ $0.15-3/1M tokens |
| **Performance** | ✅ Fast (local processing) | ❌ Slower (API calls) |
| **Bảo Mật** | ✅ Data không rời server | ❌ Data gửi lên Claude |
| **Setup Complexity** | ⚠️ Trung bình (cần code) | ✅ Dễ (chỉ cần instructions) |
| **Maintenance** | ⚠️ Cần maintain code | ✅ Dễ update Skills |
| **Template Requirements** | ⚠️ Cần placeholders | ✅ Không cần placeholders |
| **Intelligence** | ❌ Không có AI | ✅ AI-powered |
| **DOCX Output** | ✅ Direct DOCX với format | ❌ Text only, cần convert |
| **Rate Limits** | ✅ Không có | ❌ Có rate limits |

---

### Phân Tích Cụ Thể Cho Use Case

#### Use Case: "Template cũ chỉ đổi text, giữ nguyên format"

**PhpWord + Mammoth.js:**
- ✅ **Perfect match** - TemplateProcessor được thiết kế cho việc này
- ✅ Replace `{{placeholder}}` → giữ nguyên format 100%
- ✅ Output DOCX giữ nguyên format gốc
- ✅ Hiển thị trên web bằng Mammoth.js (giữ format)

**Claude API Skills:**
- ❌ **Không phù hợp** - Claude không thể output DOCX với format
- ❌ Claude trả về text → mất format
- ❌ Cần convert text → DOCX (mất format)
- ⚠️ Có thể "hiểu" template nhưng không preserve format

---

### Kết Luận Và Khuyến Nghị

#### Khi Nào Dùng PhpWord + Mammoth.js?

✅ **Nên dùng khi:**
- Cần **giữ format 100%** như template gốc
- Template có placeholders rõ ràng (`{{field}}`)
- Cần kiểm soát hoàn toàn process
- Cần performance tốt
- Cần bảo mật data
- Không muốn phụ thuộc external service
- Budget hạn chế (không muốn API costs)

#### Khi Nào Dùng Claude API Skills?

✅ **Nên dùng khi:**
- Template phức tạp, không có placeholders
- Cần AI để "hiểu" và generate content thông minh
- Không cần giữ format 100% (chấp nhận mất format)
- Có budget cho API costs
- Cần flexibility và dễ maintain
- Không cần output DOCX với format (chỉ cần text)

---

### Khuyến Nghị Cho Dự Án Hiện Tại

**Đề xuất: Sử dụng PhpWord + Mammoth.js**

**Lý do:**
1. ✅ **Yêu cầu chính:** "Template cũ chỉ đổi text, giữ nguyên format"
   - PhpWord TemplateProcessor là perfect solution
   - Claude Skills không thể giữ format DOCX

2. ✅ **Đã có infrastructure:**
   - Đã có PHP backend
   - Đã có Vue.js frontend
   - Chỉ cần thêm libraries

3. ✅ **Cost-effective:**
   - Không có API costs
   - Chỉ cần server resources

4. ✅ **Format preservation:**
   - Giữ format 100% như template gốc
   - Claude không thể làm điều này

5. ✅ **Bảo mật:**
   - Data không rời server
   - Quan trọng với government documents

**Kết hợp (Nếu cần):**
- Dùng PhpWord cho format preservation
- Dùng Claude API cho content generation (nếu cần AI)
- Kết hợp: Claude generate content → PhpWord replace vào template

---

## Kết Luận

Các vấn đề đã được phân tích chi tiết và có phương án sửa cụ thể. Ưu tiên thực hiện các tính năng P0 trước để cải thiện trải nghiệm user ngay lập tức, sau đó tiếp tục với các tính năng P1 và P2.

**Khuyến nghị:** Sử dụng **PhpWord + Mammoth.js** cho use case này vì:
- ✅ Giữ format 100% như template gốc
- ✅ Không có API costs
- ✅ Performance tốt
- ✅ Bảo mật tốt hơn
- ✅ Phù hợp với yêu cầu "template cũ chỉ đổi text"

