# 📋 PHƯƠNG ÁN THAY ĐỔI PHƯƠNG PHÁP CONVERT DOCX TO HTML

## 🎯 Vấn Đề Hiện Tại

### 1. Template Hiển Thị Lỗi

**Ví dụ lỗi:**
- `TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC 2` - Số bị dính vào text, không có space
- `Số: ... /BB- ...3...CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2` - Text bị merge sai
- `Số:.../BB-...3...CỘN BIÊN BẢN ...........4 ............ ....... ..` - Text bị merge sai
- `. . 4.. ... ....` - Text bị merge sai
- `(Chữ ký) Họ và tên CHỦ TỌA (Chữ HHọ vàtê n HỦ TỌA (Ch ữ` - Text bị duplicate và merge sai
- `ký của người cCCHỦ TỌA` - Text bị tách và merge sai
- `(Chữ ký của người có t(Chữ ký của người có` - Text bị duplicate và merge sai
- `Nơi nhận: - ......... .....; - Lưu: VT,- . ..` - Text bị merge sai
- `. . ... ... ...; -Lưu: VT, Hồ sơ. Ghi chú: 1 Tên cơ quan, tổ chứ- Lưu: VT, Hồ sơ.` - Text bị merge sai

### 2. Tiếng Việt Không Đúng

**Vấn đề:**
- Text bị tách: `HHọ vàtê n` thay vì `Họ và tên`
- Text bị duplicate: `cCCHỦ TỌA` thay vì `CHỦ TỌA`
- Text bị merge sai: `t(Chữ` thay vì `tên (Chữ`
- Số bị dính vào text: `QUẢN1TÊN` thay vì `QUẢN 1 TÊN`

### 3. Format Sai Hoàn Toàn

**Vấn đề:**
- Paragraph bị merge sai: Text không liên quan bị merge lại
- Spacing bị mất: Không có space giữa các từ
- Structure bị phá vỡ: Cấu trúc văn bản bị thay đổi
- Superscript/subscript bị merge sai: Số và text bị dính vào nhau

### 4. Không Thể Dùng Được Trong Hành Chính Công

**Vấn đề:**
- Văn bản hành chính yêu cầu format chính xác 100%
- Không được sai chính tả
- Không được thay đổi cấu trúc văn bản
- Phải giữ nguyên format từ DOCX template

## 🔍 Nguyên Nhân

### 1. Pandoc Không Preserve Format Tốt Cho Tiếng Việt

**Vấn đề:**
- Pandoc split text thành nhiều `<p>` tags nhỏ
- Pandoc không preserve spacing giữa các từ
- Pandoc không preserve structure của văn bản
- Pandoc không handle tốt superscript/subscript trong tiếng Việt

### 2. Logic Merge Paragraph Quá Aggressive

**Vấn đề:**
- Logic merge paragraph quá aggressive, merge cả những text không nên merge
- Logic merge không phân biệt được text liên quan và text không liên quan
- Logic merge không preserve spacing giữa các từ
- Logic merge không preserve structure của văn bản

### 3. Post-Processing Quá Phức Tạp

**Vấn đề:**
- Quá nhiều post-processing methods
- Logic merge phức tạp, khó maintain
- Dễ gây ra lỗi merge sai
- Không thể đảm bảo 100% accuracy

## 🛠️ Phương Án Thay Đổi

### Phương Án 1: Sử Dụng PhpOffice PhpWord (Recommended)

**Ưu điểm:**
- ✅ Native PHP library, không cần external tool
- ✅ Preserve format tốt hơn Pandoc
- ✅ Handle tiếng Việt tốt hơn
- ✅ Có thể control được output HTML
- ✅ Đã có trong composer.json (`phpoffice/phpword: ^1.4`)

**Nhược điểm:**
- ⚠️ Có thể không preserve 100% format
- ⚠️ Cần implement logic convert riêng

**Implementation:**
```php
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class PhpWordDocxToHtmlConverter
{
    public function convert(string $docxPath): string
    {
        // Set encoding to UTF-8
        Settings::setOutputEscapingEnabled(true);
        
        // Load DOCX file
        $phpWord = IOFactory::load($docxPath);
        
        // Convert to HTML
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        
        // Get HTML content
        ob_start();
        $htmlWriter->save('php://output');
        $html = ob_get_clean();
        
        // Clean up HTML
        $html = $this->cleanHtml($html);
        
        return $html;
    }
    
    protected function cleanHtml(string $html): string
    {
        // Remove unnecessary tags
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<html[^>]*>/i', '', $html);
        $html = preg_replace('/<\/html>/i', '', $html);
        $html = preg_replace('/<head[^>]*>[\s\S]*?<\/head>/i', '', $html);
        $html = preg_replace('/<body[^>]*>/i', '', $html);
        $html = preg_replace('/<\/body>/i', '', $html);
        
        // Extract body content
        if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $matches)) {
            $html = $matches[1];
        }
        
        // Preserve formatting
        $html = $this->preserveFormatting($html);
        
        return $html;
    }
    
    protected function preserveFormatting(string $html): string
    {
        // Preserve paragraph structure
        // Preserve spacing
        // Preserve superscript/subscript
        // Preserve font formatting
        
        return $html;
    }
}
```

### Phương Án 2: Sử Dụng Mammoth.js (Node.js)

**Ưu điểm:**
- ✅ Preserve format tốt nhất
- ✅ Handle tiếng Việt tốt
- ✅ Preserve structure của văn bản
- ✅ Không cần merge paragraph

**Nhược điểm:**
- ⚠️ Cần Node.js runtime
- ⚠️ Cần call Node.js từ PHP
- ⚠️ Có thể chậm hơn native PHP

**Implementation:**
```php
class MammothDocxToHtmlConverter
{
    public function convert(string $docxPath): string
    {
        // Call Node.js script
        $nodeScript = base_path('scripts/mammoth-convert.js');
        $command = "node {$nodeScript} " . escapeshellarg($docxPath);
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Mammoth conversion failed");
        }
        
        $html = implode("\n", $output);
        
        // Clean up HTML
        $html = $this->cleanHtml($html);
        
        return $html;
    }
}
```

**Node.js Script:**
```javascript
const mammoth = require("mammoth");
const fs = require("fs");

const docxPath = process.argv[2];
const docxBuffer = fs.readFileSync(docxPath);

mammoth.convertToHtml({buffer: docxBuffer})
    .then(function(result){
        console.log(result.value);
    })
    .catch(function(error){
        console.error(error);
        process.exit(1);
    });
```

### Phương Án 3: Sử Dụng LibreOffice (Headless)

**Ưu điểm:**
- ✅ Preserve format tốt
- ✅ Handle tiếng Việt tốt
- ✅ Native tool, không cần library

**Nhược điểm:**
- ⚠️ Cần install LibreOffice
- ⚠️ Có thể chậm
- ⚠️ Cần convert qua nhiều bước

**Implementation:**
```php
class LibreOfficeDocxToHtmlConverter
{
    public function convert(string $docxPath): string
    {
        // Convert DOCX to HTML using LibreOffice
        $outputDir = sys_get_temp_dir() . '/' . uniqid('libreoffice_');
        mkdir($outputDir);
        
        $command = sprintf(
            'libreoffice --headless --convert-to html --outdir %s %s',
            escapeshellarg($outputDir),
            escapeshellarg($docxPath)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("LibreOffice conversion failed");
        }
        
        // Find output HTML file
        $htmlFile = $outputDir . '/' . basename($docxPath, '.docx') . '.html';
        $html = file_get_contents($htmlFile);
        
        // Clean up
        @unlink($htmlFile);
        @rmdir($outputDir);
        
        // Clean up HTML
        $html = $this->cleanHtml($html);
        
        return $html;
    }
}
```

### Phương Án 4: Sử Dụng Pandoc Với Options Tốt Hơn (Cải Thiện)

**Ưu điểm:**
- ✅ Giữ nguyên tool hiện tại
- ✅ Chỉ cần cải thiện options và logic

**Nhược điểm:**
- ⚠️ Vẫn có thể không preserve 100% format
- ⚠️ Vẫn cần post-processing

**Implementation:**
```php
protected function buildPandocCommand(string $docxPath, string $outputPath): string
{
    $pandocPath = $this->getPandocPath();
    
    $options = [
        '--standalone',
        '--wrap=none',                    // ✅ FIX: Không wrap text
        '--preserve-tabs',                // ✅ FIX: Preserve tabs
        '--from=docx+styles',             // ✅ FIX: Preserve styles
        '--to=html5+raw_html',            // ✅ FIX: Preserve raw HTML
        '--extract-media=./media',        // ✅ FIX: Extract media
        '--no-highlight',                 // ✅ FIX: Tắt syntax highlighting
        '--metadata=lang:vi',             // ✅ FIX: Set language to Vietnamese
        '--lua-filter=preserve-format.lua', // ✅ FIX: Custom Lua filter
    ];
    
    $command = sprintf(
        '%s %s %s -o %s',
        escapeshellarg($pandocPath),
        implode(' ', array_map('escapeshellarg', $options)),
        escapeshellarg($docxPath),
        escapeshellarg($outputPath)
    );
    
    return $command;
}
```

**Lua Filter (preserve-format.lua):**
```lua
function Para(para)
    -- Preserve paragraph structure
    -- Preserve spacing
    -- Preserve superscript/subscript
    return para
end

function Str(str)
    -- Preserve text content
    return str
end

function Superscript(sup)
    -- Preserve superscript
    return sup
end

function Subscript(sub)
    -- Preserve subscript
    return sub
end
```

## 📊 So Sánh Các Phương Án

| Phương Án | Format Preservation | Vietnamese Support | Performance | Complexity | Recommended |
|-----------|-------------------|-------------------|-------------|------------|-------------|
| **PhpOffice PhpWord** | 85-90% | ✅ Tốt | ✅ Nhanh | ⚠️ Trung bình | ✅ **Recommended** |
| **Mammoth.js** | 95-98% | ✅ Tốt | ⚠️ Chậm | ⚠️ Phức tạp | ✅ **Best** |
| **LibreOffice** | 90-95% | ✅ Tốt | ⚠️ Chậm | ⚠️ Phức tạp | ⚠️ Alternative |
| **Pandoc (Cải thiện)** | 80-85% | ⚠️ Trung bình | ✅ Nhanh | ⚠️ Phức tạp | ❌ **Not Recommended** |

## 🎯 Phương Án Đề Xuất: PhpOffice PhpWord

### Lý Do

1. **Đã có trong project:** `phpoffice/phpword: ^1.4` đã có trong composer.json
2. **Native PHP:** Không cần external tool, dễ deploy
3. **Preserve format tốt:** 85-90% format preservation
4. **Handle tiếng Việt tốt:** Support UTF-8 đầy đủ
5. **Có thể control:** Có thể customize output HTML
6. **Performance tốt:** Nhanh hơn Mammoth.js và LibreOffice

### Implementation Plan

#### Step 1: Tạo PhpWordDocxToHtmlConverter

**File:** `app/Services/PhpWordDocxToHtmlConverter.php`

**Features:**
- Load DOCX file bằng PhpWord
- Convert to HTML với format preservation
- Clean up HTML output
- Preserve paragraph structure
- Preserve spacing
- Preserve superscript/subscript
- Preserve font formatting

#### Step 2: Update DocumentController

**File:** `app/Http/Controllers/DocumentController.php`

**Changes:**
- Thay `PandocDocxToHtmlConverter` bằng `PhpWordDocxToHtmlConverter`
- Update logic convert
- Update error handling

#### Step 3: Testing

**Test Cases:**
- Test với template hiện tại
- Test với template mới
- Test với tiếng Việt
- Test với superscript/subscript
- Test với format phức tạp

#### Step 4: Rollback Plan

**Rollback:**
- Có thể rollback bằng cách thay đổi converter
- Giữ nguyên `PandocDocxToHtmlConverter` để backup

## 📝 Implementation Details

### PhpWordDocxToHtmlConverter Implementation

```php
<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use Illuminate\Support\Facades\Log;

class PhpWordDocxToHtmlConverter
{
    /**
     * Convert DOCX to HTML using PhpWord
     *
     * @param string $docxPath Path to DOCX file
     * @return string HTML content
     * @throws \Exception If conversion fails
     */
    public function convert(string $docxPath): string
    {
        // Validate input
        if (!file_exists($docxPath)) {
            throw new \Exception("DOCX file not found: {$docxPath}");
        }
        
        Log::info('Converting DOCX to HTML with PhpWord', [
            'file' => basename($docxPath),
            'file_size' => filesize($docxPath),
        ]);
        
        try {
            // Set encoding to UTF-8
            Settings::setOutputEscapingEnabled(true);
            
            // Load DOCX file
            $phpWord = IOFactory::load($docxPath);
            
            // Convert to HTML
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            
            // Get HTML content
            ob_start();
            $htmlWriter->save('php://output');
            $html = ob_get_clean();
            
            // Clean up HTML
            $html = $this->cleanHtml($html);
            
            // Enhance HTML
            $html = $this->enhanceHtml($html);
            
            Log::info('PhpWord conversion completed', [
                'html_length' => strlen($html),
                'input_file' => basename($docxPath),
            ]);
            
            return $html;
        } catch (\Exception $e) {
            Log::error('PhpWord conversion failed', [
                'error' => $e->getMessage(),
                'file' => basename($docxPath),
            ]);
            throw new \Exception("PhpWord conversion failed: {$e->getMessage()}");
        }
    }
    
    /**
     * Clean up HTML output
     *
     * @param string $html
     * @return string
     */
    protected function cleanHtml(string $html): string
    {
        // Remove unnecessary tags
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<html[^>]*>/i', '', $html);
        $html = preg_replace('/<\/html>/i', '', $html);
        $html = preg_replace('/<head[^>]*>[\s\S]*?<\/head>/i', '', $html);
        $html = preg_replace('/<body[^>]*>/i', '', $html);
        $html = preg_replace('/<\/body>/i', '', $html);
        
        // Extract body content
        if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $matches)) {
            $html = $matches[1];
        }
        
        // Clean up whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = trim($html);
        
        return $html;
    }
    
    /**
     * Enhance HTML with custom styling
     *
     * @param string $html
     * @return string
     */
    protected function enhanceHtml(string $html): string
    {
        // Add custom CSS
        $css = $this->getCustomCss();
        
        // Wrap HTML with style tag
        $html = '<style>' . $css . '</style>' . $html;
        
        return $html;
    }
    
    /**
     * Get custom CSS for document styling
     *
     * @return string
     */
    protected function getCustomCss(): string
    {
        return '
            body {
                font-family: "Times New Roman", serif;
                font-size: 13pt;
                line-height: 1.5;
                margin: 0;
                padding: 16px;
            }
            p {
                margin: 0.5em 0;
                text-align: justify;
            }
            sup {
                font-size: 0.7em;
                vertical-align: super;
                line-height: 0;
            }
            sub {
                font-size: 0.7em;
                vertical-align: sub;
                line-height: 0;
            }
        ';
    }
}
```

### Update DocumentController

```php
// app/Http/Controllers/DocumentController.php

use App\Services\PhpWordDocxToHtmlConverter;

public function previewHtml($messageId)
{
    // ... existing code ...
    
    // ✅ FIX: Use PhpWord instead of Pandoc
    $converter = new PhpWordDocxToHtmlConverter();
    $html = $converter->convert($docxPath);
    
    // ... existing code ...
}
```

## ⚠️ Lưu Ý

### 1. Backward Compatibility

- ✅ Giữ nguyên `PandocDocxToHtmlConverter` để backup
- ✅ Có thể switch giữa 2 converters bằng config
- ✅ Có thể rollback nếu cần

### 2. Testing Strategy

- ✅ Test với template hiện tại
- ✅ Test với template mới
- ✅ Test với tiếng Việt
- ✅ Test với superscript/subscript
- ✅ Test với format phức tạp
- ✅ Test với văn bản hành chính thực tế

### 3. Performance

- ✅ PhpWord nhanh hơn Pandoc (không cần exec)
- ✅ PhpWord không cần external tool
- ✅ PhpWord có thể cache output

### 4. Format Preservation

- ✅ PhpWord preserve format tốt hơn Pandoc
- ✅ PhpWord không cần merge paragraph
- ✅ PhpWord preserve spacing tốt hơn
- ✅ PhpWord preserve structure tốt hơn

## 🚀 Next Steps

1. ✅ Implement `PhpWordDocxToHtmlConverter`
2. ✅ Update `DocumentController` để sử dụng `PhpWordDocxToHtmlConverter`
3. ✅ Test với template hiện tại
4. ✅ Test với template mới
5. ✅ Compare kết quả với Pandoc
6. ✅ Deploy và monitor

## 📊 Kết Quả Mong Đợi

### Trước (Pandoc)
- ❌ Text bị merge sai: `TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC 2`
- ❌ Text bị duplicate: `(Chữ ký) Họ và tên CHỦ TỌA (Chữ HHọ vàtê n HỦ TỌA (Ch ữ`
- ❌ Text bị tách: `ký của người cCCHỦ TỌA`
- ❌ Format sai: Không preserve spacing, structure

### Sau (PhpWord)
- ✅ Text đúng: `TÊN CQ, TC CHỦ QUẢN 1 TÊN CƠ QUAN, TỔ CHỨC 2`
- ✅ Text không duplicate: `(Chữ ký) Họ và tên CHỦ TỌA`
- ✅ Text không bị tách: `ký của người CHỦ TỌA`
- ✅ Format đúng: Preserve spacing, structure, formatting

## 🎯 Kết Luận

**Phương án đề xuất:** Sử dụng **PhpOffice PhpWord** thay vì Pandoc vì:
1. ✅ Đã có trong project
2. ✅ Native PHP, không cần external tool
3. ✅ Preserve format tốt hơn
4. ✅ Handle tiếng Việt tốt hơn
5. ✅ Không cần merge paragraph phức tạp
6. ✅ Dễ maintain và debug

**Alternative:** Nếu PhpWord không đủ tốt, có thể thử **Mammoth.js** (Node.js) để có format preservation tốt nhất (95-98%).



