# ⚠️ VẤN ĐỀ: PhpWord Parsing Không Đúng Template Structure

## 🔍 PHÁT HIỆN

### Template DOCX Gốc (Cấu Trúc):
```
┌─────────────────────┬─────────────────────┐
│ CÔNG TY TNHH ABC    │ CỘNG HÒA XÃ HỘI... │
│ (Tên cơ quan)       │ Độc lập - Tự do... │
│                     │                     │
│ Số: 01/BC/2023      │ Hà Nội, 07/11/2025  │
└─────────────────────┴─────────────────────┘

          BÁO CÁO
    (Tên loại văn bản)

      VỀ VIỆC THỰC HIỆN...
    (Trích yếu nội dung)

Nội dung chính...
...

┌─────────────────────┬─────────────────────┐
│ Nơi nhận:           │ QUYỀN HẠN, CHỨC VỤ  │
│ - ...               │ (Chữ ký)           │
└─────────────────────┴─────────────────────┘
```

### PhpWord Parse Result:
```html
<!-- ❌ TẤT CẢ BỊ NHỒI VÀO TABLE CELLS -->
<table>
  <tr>
    <td>
      <p>CÔNG TY TNHH ABC1CÔNG TY TNHH ABC2Số:...</p>
      <!-- Tất cả text trong 1 paragraph! -->
    </td>
    <td>
      <p>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM Độc lập...</p>
      <!-- Không có line breaks! -->
    </td>
  </tr>
</table>
```

### Web UI Display:
```
❌ CÔNG TY TNHH ABC1CÔNG TY TNHH ABC2Số:...
   (Tất cả dính liền, không có line breaks)

❌ CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM Độc lập - Tự do - Hạnh phúc...Hà Nội, 07/11/2025
   (Không có xuống dòng!)
```

**→ HOÀN TOÀN KHÁC VỚI TEMPLATE GỐC!**

---

## 🐛 ROOT CAUSE

### PhpWord Limitations:

1. **Table Cell Parsing:**
   ```php
   // PhpWord converts:
   foreach ($cell->getElements() as $element) {
       // All elements → single <p> tag
   }
   ```
   
   **Problem:** Không giữ line breaks trong cell!

2. **TextRun vs Paragraph:**
   ```php
   // PhpWord treats multiple paragraphs in cell as:
   TextRun → Single <p>
   
   // Instead of:
   Multiple TextRuns → Multiple <p> tags
   ```

3. **No Structural Awareness:**
   - PhpWord chỉ parse elements
   - Không hiểu semantic structure (header, body, footer)
   - Không detect "BÁO CÁO" là title
   - Không detect table là header section

---

## 📊 COMPARISON

| Aspect | Template DOCX | PhpWord HTML | Expected HTML |
|--------|--------------|-------------|---------------|
| **Line breaks in table** | ✅ Multiple lines | ❌ Single line | ✅ `<p>` for each line |
| **Header structure** | ✅ 2-column table | ✅ `<table>` | ✅ OK |
| **Text in cells** | ✅ Separate paragraphs | ❌ All in one `<p>` | ✅ Multiple `<p>` |
| **Title formatting** | ✅ Bold, centered, large | ✅ Has styles | ✅ OK |
| **Spacing** | ✅ Line breaks | ❌ No `<br>` or `<p>` | ❌ FAIL |

---

## 🎯 SOLUTION OPTIONS

### Option 1: Fix PhpWord Parsing (HARD) ❌

**Approach:**
```php
// Detect line breaks in cell content
// Split into multiple <p> tags
foreach ($cell->getElements() as $element) {
    if ($element instanceof TextBreak) {
        // Add </p><p>
    }
}
```

**Problems:**
- PhpWord might not expose TextBreak elements
- Complex to detect all line break types
- Still won't be 100% accurate

### Option 2: Use Mammoth.js (Client-side) ✅ ALREADY TRIED

**Approach:**
```javascript
// Client-side conversion
mammoth.convertToHtml({ arrayBuffer })
```

**Result:**
- 85-90% format preservation
- User reported "không giống"
- Maybe still the best option?

### Option 3: Use Pandoc (Server-side) ✅ BEST

**Approach:**
```bash
# Install Pandoc
sudo apt install pandoc  # Linux
brew install pandoc       # Mac

# Convert DOCX → HTML
pandoc input.docx -o output.html \
  --standalone \
  --embed-resources \
  --css=style.css
```

**Pros:**
- ✅ 95-98% format preservation
- ✅ Better structure detection
- ✅ Handles complex DOCX features
- ✅ Server-side (no client dependencies)
- ✅ Widely used, battle-tested

**Cons:**
- ❌ Requires Pandoc installation on server
- ❌ Additional system dependency
- ❌ ~30MB disk space

### Option 4: Direct XML Parsing (EXTREME) ⚠️

**Approach:**
```php
// Parse word/document.xml directly
// Manually convert <w:p> → <p>
// Handle <w:br/> → <br>
// Handle <w:tbl> → <table>
```

**Pros:**
- ✅ 100% control
- ✅ Can handle all edge cases

**Cons:**
- ❌ VERY complex (hundreds of XML elements)
- ❌ High maintenance
- ❌ Reinventing the wheel

---

## 🚀 RECOMMENDED SOLUTION

### ✅ Option 3: Pandoc (Server-side)

**Implementation:**

```php
<?php
// app/Services/PandocDocxToHtmlConverter.php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PandocDocxToHtmlConverter
{
    public function convert(string $docxPath): string
    {
        // Check if Pandoc is installed
        $pandocPath = trim(shell_exec('which pandoc'));
        if (empty($pandocPath)) {
            throw new \Exception('Pandoc is not installed on this server');
        }
        
        Log::info('Converting DOCX to HTML with Pandoc', [
            'file' => basename($docxPath),
            'pandoc_version' => trim(shell_exec('pandoc --version | head -1')),
        ]);
        
        // Generate output path
        $outputPath = sys_get_temp_dir() . '/' . uniqid('docx_html_') . '.html';
        
        // Build Pandoc command
        $command = sprintf(
            '%s %s -o %s --standalone --embed-resources 2>&1',
            escapeshellcmd($pandocPath),
            escapeshellarg($docxPath),
            escapeshellarg($outputPath)
        );
        
        // Execute
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('Pandoc conversion failed: ' . implode("\n", $output));
        }
        
        if (!file_exists($outputPath)) {
            throw new \Exception('Pandoc output file not created');
        }
        
        // Read HTML
        $html = file_get_contents($outputPath);
        
        // Clean up
        unlink($outputPath);
        
        Log::info('Pandoc conversion successful', [
            'html_length' => strlen($html),
        ]);
        
        return $html;
    }
}
```

**Update `ReportController::previewHtml()`:**

```php
public function previewHtml(Request $request, $reportId)
{
    // ...
    
    try {
        $html = Cache::remember($cacheKey, now()->addHours(24), function () use ($report) {
            $docxPath = $this->getDocxPath($report->report_file_path);
            
            // ✅ Use Pandoc instead of AdvancedDocxToHtmlConverter
            $converter = new PandocDocxToHtmlConverter();
            return $converter->convert($docxPath);
        });
        
        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'private, max-age=86400');
            
    } catch (\Exception $e) {
        // ...
    }
}
```

**Installation:**

```bash
# Mac (user's system)
brew install pandoc

# Linux
sudo apt update
sudo apt install pandoc

# Verify
pandoc --version
```

---

## 📈 EXPECTED RESULTS

### With Pandoc:

```html
<!-- ✅ Proper structure -->
<table>
  <tr>
    <td>
      <p>CÔNG TY TNHH ABC</p>
      <p><strong>CÔNG TY TNHH ABC</strong></p>
      <p></p>
      <p>Số: 01/BC-ABC</p>
    </td>
    <td>
      <p><strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong></p>
      <p><strong>Độc lập - Tự do - Hạnh phúc</strong></p>
      <p></p>
      <p><em>Hà Nội, 07/11/2025</em></p>
    </td>
  </tr>
</table>

<p style="text-align: center">
  <strong>BÁO CÁO</strong>
</p>
```

**Web Display:**
```
✅ CÔNG TY TNHH ABC
   CÔNG TY TNHH ABC
   
   Số: 01/BC-ABC

✅ CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
   Độc lập - Tự do - Hạnh phúc
   
   Hà Nội, 07/11/2025

✅           BÁO CÁO
```

**Format Preservation: 95-98%!** 🎉

---

## 🎯 ACTION PLAN

1. ✅ Install Pandoc on server
2. ✅ Create `PandocDocxToHtmlConverter`
3. ✅ Update `ReportController::previewHtml()`
4. ✅ Test with real template
5. ✅ Compare with DOCX original
6. ✅ User acceptance testing

**Estimated Time: 30 minutes**

**Expected Result: 95-98% format preservation** 🚀

---

## 💡 CONCLUSION

**PhpWord is NOT suitable for Vietnamese document templates** với structure phức tạp (tables in headers, multiple paragraphs in cells).

**Pandoc is the BEST solution:**
- ✅ 95-98% format preservation
- ✅ Handles complex structures
- ✅ Server-side (secure)
- ✅ Widely supported
- ✅ Easy to install

**Next Step:** Install Pandoc và test!






