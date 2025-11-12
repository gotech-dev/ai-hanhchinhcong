# 📋 PHƯƠNG ÁN SO SÁNH DOCX GỐC VỚI HTML PREVIEW

## 🎯 Mục Tiêu

So sánh file template DOCX gốc với phần hiển thị trên web để:
1. Tìm ra các điểm khác biệt
2. Hiểu bug và fix
3. Đảm bảo format giống nhau

## 🔍 Phương Án So Sánh

### 1. Extract Text từ DOCX

**Cách 1: Sử dụng PhpWord**
```php
$phpWord = IOFactory::load($docxPath);
$docxText = [];

foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $element) {
        if ($element instanceof TextRun) {
            $text = '';
            foreach ($element->getElements() as $textElement) {
                if ($textElement instanceof Text) {
                    $text .= $textElement->getText();
                }
            }
            $docxText[] = trim($text);
        }
    }
}
```

**Cách 2: Parse DOCX XML trực tiếp**
```php
$zip = new ZipArchive();
$zip->open($docxPath);
$xml = $zip->getFromName('word/document.xml');

$dom = new DOMDocument();
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

$docxText = [];
$paragraphs = $xpath->query('//w:p');
foreach ($paragraphs as $paragraph) {
    $text = '';
    $textNodes = $xpath->query('.//w:t', $paragraph);
    foreach ($textNodes as $textNode) {
        $text .= $textNode->nodeValue;
    }
    $docxText[] = trim($text);
}
```

### 2. Extract Text từ HTML Preview

**Từ Browser:**
```javascript
const preview = document.querySelector('.document-preview, .docx-preview');
const pTags = preview.querySelectorAll('p');
const htmlText = Array.from(pTags).map(p => p.textContent?.trim() || '');
```

**Từ Backend (sau khi convert):**
```php
$html = $converter->convert($docxPath);
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

$htmlText = [];
$paragraphs = $xpath->query('//p');
foreach ($paragraphs as $paragraph) {
    $htmlText[] = trim($paragraph->textContent);
}
```

### 3. So Sánh Từng Dòng

**Algorithm:**
1. Normalize text (remove extra spaces, normalize line breaks)
2. Compare line by line
3. Highlight differences
4. Report mismatches

**Code:**
```php
function compareDocxAndHtml(array $docxText, array $htmlText): array
{
    $differences = [];
    $maxLines = max(count($docxText), count($htmlText));
    
    for ($i = 0; $i < $maxLines; $i++) {
        $docxLine = normalizeText($docxText[$i] ?? '');
        $htmlLine = normalizeText($htmlText[$i] ?? '');
        
        if ($docxLine !== $htmlLine) {
            $differences[] = [
                'line' => $i + 1,
                'docx' => $docxLine,
                'html' => $htmlLine,
                'diff' => computeDiff($docxLine, $htmlLine)
            ];
        }
    }
    
    return $differences;
}

function normalizeText(string $text): string
{
    // Remove extra spaces
    $text = preg_replace('/\s+/', ' ', $text);
    // Trim
    $text = trim($text);
    // Normalize Vietnamese characters
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return $text;
}

function computeDiff(string $docx, string $html): array
{
    // Simple character-by-character diff
    $diff = [];
    $maxLen = max(mb_strlen($docx), mb_strlen($html));
    
    for ($i = 0; $i < $maxLen; $i++) {
        $docxChar = mb_substr($docx, $i, 1);
        $htmlChar = mb_substr($html, $i, 1);
        
        if ($docxChar !== $htmlChar) {
            $diff[] = [
                'position' => $i,
                'docx' => $docxChar,
                'html' => $htmlChar
            ];
        }
    }
    
    return $diff;
}
```

## 🛠️ Tool So Sánh

### 1. Tạo Script PHP để So Sánh

**File: `app/Console/Commands/CompareDocxHtml.php`**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\IOFactory;
use App\Services\AdvancedDocxToHtmlConverter;
use DOMDocument;
use DOMXPath;

class CompareDocxHtml extends Command
{
    protected $signature = 'docx:compare {docx_path}';
    protected $description = 'Compare DOCX file with HTML preview';

    public function handle()
    {
        $docxPath = $this->argument('docx_path');
        
        if (!file_exists($docxPath)) {
            $this->error("File not found: {$docxPath}");
            return 1;
        }
        
        // Extract text from DOCX
        $docxText = $this->extractTextFromDocx($docxPath);
        
        // Convert to HTML
        $converter = new AdvancedDocxToHtmlConverter();
        $html = $converter->convert($docxPath);
        
        // Extract text from HTML
        $htmlText = $this->extractTextFromHtml($html);
        
        // Compare
        $differences = $this->compare($docxText, $htmlText);
        
        // Report
        $this->report($differences, $docxText, $htmlText);
        
        return 0;
    }
    
    protected function extractTextFromDocx(string $docxPath): array
    {
        $phpWord = IOFactory::load($docxPath);
        $text = [];
        
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $line = '';
                    foreach ($element->getElements() as $textElement) {
                        if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                            $line .= $textElement->getText();
                        }
                    }
                    $text[] = $this->normalizeText($line);
                }
            }
        }
        
        return $text;
    }
    
    protected function extractTextFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $text = [];
        $paragraphs = $xpath->query('//p');
        
        foreach ($paragraphs as $paragraph) {
            $text[] = $this->normalizeText($paragraph->textContent);
        }
        
        return $text;
    }
    
    protected function normalizeText(string $text): string
    {
        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim
        $text = trim($text);
        return $text;
    }
    
    protected function compare(array $docxText, array $htmlText): array
    {
        $differences = [];
        $maxLines = max(count($docxText), count($htmlText));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $docxLine = $docxText[$i] ?? '';
            $htmlLine = $htmlText[$i] ?? '';
            
            if ($docxLine !== $htmlLine) {
                $differences[] = [
                    'line' => $i + 1,
                    'docx' => $docxLine,
                    'html' => $htmlLine,
                    'diff' => $this->computeDiff($docxLine, $htmlLine)
                ];
            }
        }
        
        return $differences;
    }
    
    protected function computeDiff(string $docx, string $html): array
    {
        $diff = [];
        $maxLen = max(mb_strlen($docx), mb_strlen($html));
        
        for ($i = 0; $i < $maxLen; $i++) {
            $docxChar = mb_substr($docx, $i, 1) ?: '';
            $htmlChar = mb_substr($html, $i, 1) ?: '';
            
            if ($docxChar !== $htmlChar) {
                $diff[] = [
                    'position' => $i,
                    'docx' => $docxChar,
                    'html' => $htmlChar
                ];
            }
        }
        
        return $diff;
    }
    
    protected function report(array $differences, array $docxText, array $htmlText): void
    {
        $this->info("=== COMPARISON REPORT ===");
        $this->info("DOCX lines: " . count($docxText));
        $this->info("HTML lines: " . count($htmlText));
        $this->info("Differences: " . count($differences));
        $this->newLine();
        
        if (empty($differences)) {
            $this->info("✅ No differences found!");
            return;
        }
        
        $this->warn("⚠️ Found " . count($differences) . " differences:");
        $this->newLine();
        
        foreach ($differences as $diff) {
            $this->line("Line {$diff['line']}:");
            $this->line("  DOCX: " . $diff['docx']);
            $this->line("  HTML: " . $diff['html']);
            
            if (!empty($diff['diff'])) {
                $this->line("  Diff: " . count($diff['diff']) . " character(s) different");
                foreach (array_slice($diff['diff'], 0, 10) as $charDiff) {
                    $this->line("    Position {$charDiff['position']}: '{$charDiff['docx']}' vs '{$charDiff['html']}'");
                }
            }
            
            $this->newLine();
        }
    }
}
```

### 2. Tạo API Endpoint để So Sánh

**File: `app/Http/Controllers/DocumentController.php`**

```php
public function compareDocxHtml(Request $request, $messageId)
{
    $message = ChatMessage::findOrFail($messageId);
    $documentData = $message->metadata['document'] ?? null;
    
    if (!$documentData) {
        return response()->json(['error' => 'No document found'], 404);
    }
    
    $docxPath = storage_path('app/public/documents/' . basename($documentData['file_path']));
    
    if (!file_exists($docxPath)) {
        return response()->json(['error' => 'DOCX file not found'], 404);
    }
    
    // Get template path
    $template = DocumentTemplate::find($documentData['template_id'] ?? null);
    $templatePath = $template ? storage_path('app/public/' . $template->file_path) : null;
    
    // Extract text from DOCX
    $docxText = $this->extractTextFromDocx($docxPath);
    
    // Convert to HTML
    $converter = new AdvancedDocxToHtmlConverter();
    $html = $converter->convert($docxPath);
    
    // Extract text from HTML
    $htmlText = $this->extractTextFromHtml($html);
    
    // Compare
    $differences = $this->compare($docxText, $htmlText);
    
    return response()->json([
        'docx_lines' => count($docxText),
        'html_lines' => count($htmlText),
        'differences' => count($differences),
        'docx_text' => $docxText,
        'html_text' => $htmlText,
        'differences_detail' => $differences
    ]);
}
```

### 3. Tạo Frontend Tool để So Sánh

**File: `resources/js/Components/DocxHtmlComparator.vue`**

```vue
<template>
  <div class="docx-html-comparator">
    <h3>DOCX vs HTML Comparison</h3>
    
    <div class="comparison-grid">
      <div class="docx-column">
        <h4>DOCX (Original)</h4>
        <div class="text-content">
          <div v-for="(line, index) in docxText" :key="index" 
               :class="{'diff': isDifferent(index)}"
               class="line">
            <span class="line-number">{{ index + 1 }}</span>
            <span class="line-text">{{ line }}</span>
          </div>
        </div>
      </div>
      
      <div class="html-column">
        <h4>HTML (Preview)</h4>
        <div class="text-content">
          <div v-for="(line, index) in htmlText" :key="index"
               :class="{'diff': isDifferent(index)}"
               class="line">
            <span class="line-number">{{ index + 1 }}</span>
            <span class="line-text">{{ line }}</span>
          </div>
        </div>
      </div>
    </div>
    
    <div class="differences-summary">
      <h4>Differences: {{ differences.length }}</h4>
      <div v-for="diff in differences" :key="diff.line" class="difference-item">
        <strong>Line {{ diff.line }}:</strong>
        <div>DOCX: {{ diff.docx }}</div>
        <div>HTML: {{ diff.html }}</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    messageId: {
      type: Number,
      required: true
    }
  },
  data() {
    return {
      docxText: [],
      htmlText: [],
      differences: []
    };
  },
  mounted() {
    this.compare();
  },
  methods: {
    async compare() {
      try {
        const response = await fetch(`/api/documents/${this.messageId}/compare`);
        const data = await response.json();
        
        this.docxText = data.docx_text || [];
        this.htmlText = data.html_text || [];
        this.differences = data.differences_detail || [];
      } catch (error) {
        console.error('Comparison failed:', error);
      }
    },
    isDifferent(index) {
      return this.differences.some(diff => diff.line === index + 1);
    }
  }
};
</script>

<style scoped>
.comparison-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.text-content {
  max-height: 600px;
  overflow-y: auto;
  border: 1px solid #ddd;
  padding: 10px;
}

.line {
  display: flex;
  padding: 2px 0;
}

.line.diff {
  background-color: #ffebee;
}

.line-number {
  min-width: 40px;
  color: #999;
  font-size: 12px;
}

.line-text {
  flex: 1;
}
</style>
```

## 📊 Workflow So Sánh

### 1. Test trên Browser
- Mở browser và test document preview
- Extract text từ HTML preview
- Log ra console

### 2. Extract Text từ DOCX
- Sử dụng PhpWord hoặc parse XML trực tiếp
- Normalize text (remove extra spaces, normalize line breaks)
- Store vào array

### 3. So Sánh
- Compare line by line
- Highlight differences
- Report mismatches

### 4. Fix Bug
- Dựa vào differences để hiểu bug
- Fix code
- Test lại

## 🎯 Next Steps

1. ✅ **Tạo script so sánh:** `app/Console/Commands/CompareDocxHtml.php`
2. ✅ **Tạo API endpoint:** `/api/documents/{messageId}/compare`
3. ✅ **Tạo frontend component:** `DocxHtmlComparator.vue`
4. ⏳ **Test:** Test trên browser và so sánh



