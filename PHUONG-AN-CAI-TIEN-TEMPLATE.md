# 📋 PHƯƠNG ÁN CẢI TIẾN - TEMPLATE HIỂN THỊ GIỐNG HỆT TEMPLATE MẪU

## 🎯 Mục Tiêu

Đảm bảo template hiển thị trên web **giống hệt** template DOCX mẫu về:
- ✅ Format (font, size, color, alignment)
- ✅ Structure (paragraphs, line breaks, spacing)
- ✅ Content (text, không bị tách, không bị mất)
- ✅ Layout (margins, indentation, tables)

## 🔍 Phân Tích Vấn Đề Hiện Tại

### 1. Vấn Đề Chính

**Logic merge TextRun:**
- ✅ Đã merge các TextRun liên tiếp thành một paragraph
- ❌ Nhưng merge **TẤT CẢ** TextRun liên tiếp (kể cả khi chúng không thuộc cùng một paragraph trong DOCX gốc)
- ❌ Không phân biệt paragraph boundaries trong DOCX

**Kết quả:**
- DOCX có 61 TextRun (mỗi TextRun = 1 paragraph trong DOCX gốc)
- HTML có 3 paragraphs (sau khi merge TẤT CẢ TextRun liên tiếp)
- Text bị nối liền: "TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC" (SAI)

### 2. Nguyên Nhân

**PhpWord:**
- Parse DOCX thành `Section → Elements` (TextRun, Table, Image, etc.)
- Không có class `Paragraph` riêng biệt
- Không thể phân biệt TextRun nào thuộc cùng một paragraph

**AdvancedDocxToHtmlConverter:**
- Merge tất cả TextRun liên tiếp thành một paragraph (SAI)
- Không parse DOCX XML trực tiếp để xác định paragraph boundaries

### 3. Cấu Trúc DOCX

Trong DOCX, cấu trúc thường là:
```xml
<w:p>  <!-- Paragraph 1 -->
  <w:r>  <!-- TextRun 1 -->
    <w:t>TÊN CQ, TC CHỦ QUẢN</w:t>
  </w:r>
</w:p>
<w:p>  <!-- Paragraph 2 -->
  <w:r>  <!-- TextRun 2 -->
    <w:t>1</w:t>
  </w:r>
</w:p>
<w:p>  <!-- Paragraph 3 -->
  <w:r>  <!-- TextRun 3 -->
    <w:t>TÊN CƠ QUAN, TỔ CHỨC</w:t>
  </w:r>
</w:p>
```

**PhpWord** đọc thành:
- 3 `TextRun` elements (không có Paragraph class)
- Mỗi TextRun là một element riêng biệt trong section

**AdvancedDocxToHtmlConverter** hiện tại:
- Merge 3 TextRun thành 1 paragraph (SAI)
- Kết quả: "TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC" (SAI)

**Mong muốn:**
- Giữ 3 paragraphs riêng biệt
- Chỉ merge TextRun trong cùng một paragraph

## 🔧 Giải Pháp

### 1. Parse DOCX XML Trực Tiếp

**Cần sửa:** `groupTextRunsIntoParagraphs()` để parse DOCX XML trực tiếp:

```php
protected function groupTextRunsIntoParagraphs(array $elements): array
{
    // ✅ FIX: Parse DOCX XML trực tiếp để xác định paragraph boundaries
    $zip = new ZipArchive();
    if ($zip->open($this->docxPath) !== true) {
        throw new \Exception('Cannot open DOCX as ZIP');
    }
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if ($xml === false) {
        Log::warning('Cannot read document.xml from DOCX');
        // Fallback: merge all TextRuns (current behavior)
        return $this->fallbackMergeTextRuns($elements);
    }
    
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    // Get all paragraphs from XML
    $paragraphs = $xpath->query('//w:p');
    
    $grouped = [];
    $elementIndex = 0;
    
    foreach ($paragraphs as $paragraph) {
        $textRuns = [];
        
        // Get all TextRuns in this paragraph
        $textRunNodes = $xpath->query('.//w:r', $paragraph);
        
        foreach ($textRunNodes as $textRunNode) {
            // Find corresponding PhpWord TextRun element
            if ($elementIndex < count($elements) && $elements[$elementIndex] instanceof TextRun) {
                $textRuns[] = $elements[$elementIndex];
                $elementIndex++;
            }
        }
        
        // If we have TextRuns, create a paragraph group
        if (!empty($textRuns)) {
            $grouped[] = new ParagraphGroup($textRuns);
        } else {
            // Empty paragraph - create empty paragraph group
            $grouped[] = new ParagraphGroup([]);
        }
    }
    
    // Add remaining elements (Table, Image, etc.)
    while ($elementIndex < count($elements)) {
        $grouped[] = $elements[$elementIndex];
        $elementIndex++;
    }
    
    return $grouped;
}

protected function fallbackMergeTextRuns(array $elements): array
{
    // Fallback: merge all TextRuns (current behavior)
    $grouped = [];
    $currentParagraph = [];
    
    foreach ($elements as $element) {
        if ($element instanceof TextRun) {
            $currentParagraph[] = $element;
        } else {
            if (!empty($currentParagraph)) {
                $grouped[] = new ParagraphGroup($currentParagraph);
                $currentParagraph = [];
            }
            $grouped[] = $element;
        }
    }
    
    if (!empty($currentParagraph)) {
        $grouped[] = new ParagraphGroup($currentParagraph);
    }
    
    return $grouped;
}
```

### 2. Extract Paragraph Properties

**Cần sửa:** `convertParagraphGroup()` để extract paragraph properties từ XML:

```php
protected function convertParagraphGroup(ParagraphGroup $paragraphGroup): string
{
    $textRuns = $paragraphGroup->getTextRuns();
    
    // ✅ FIX: Extract paragraph properties from XML
    $paragraphStyle = $this->extractParagraphStyleFromXml($paragraphGroup);
    $styleAttr = $this->styleArrayToCss($paragraphStyle);
    
    $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
    
    // ✅ FIX: Merge all TextRuns into one <p> tag (preserve individual Text styles)
    foreach ($textRuns as $textRun) {
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= $this->convertText($element);
            }
        }
    }
    
    $html .= '</p>';
    
    return $html;
}

protected function extractParagraphStyleFromXml(ParagraphGroup $paragraphGroup): array
{
    // Parse DOCX XML to extract paragraph properties
    $zip = new ZipArchive();
    if ($zip->open($this->docxPath) !== true) {
        return [];
    }
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if ($xml === false) {
        return [];
    }
    
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    // Find paragraph containing first TextRun
    $paragraphs = $xpath->query('//w:p');
    $style = [];
    
    foreach ($paragraphs as $paragraph) {
        // Check if this paragraph contains our TextRuns
        $textRunNodes = $xpath->query('.//w:r', $paragraph);
        if ($textRunNodes->length > 0) {
            // Extract paragraph properties
            $pPr = $xpath->query('.//w:pPr', $paragraph)->item(0);
            if ($pPr) {
                // Alignment
                $jc = $xpath->query('.//w:jc', $pPr)->item(0);
                if ($jc) {
                    $align = $jc->getAttribute('w:val');
                    $alignMap = [
                        'left' => 'left',
                        'center' => 'center',
                        'right' => 'right',
                        'both' => 'justify',
                    ];
                    $style['text-align'] = $alignMap[$align] ?? 'left';
                }
                
                // Spacing
                $spacing = $xpath->query('.//w:spacing', $pPr)->item(0);
                if ($spacing) {
                    $before = $spacing->getAttribute('w:before');
                    if ($before && is_numeric($before)) {
                        $style['margin-top'] = ($before / 20) . 'pt'; // Twips to pt
                    }
                    $after = $spacing->getAttribute('w:after');
                    if ($after && is_numeric($after)) {
                        $style['margin-bottom'] = ($after / 20) . 'pt';
                    }
                    $line = $spacing->getAttribute('w:line');
                    if ($line && is_numeric($line)) {
                        $style['line-height'] = ($line / 240) . 'em'; // Twips to em
                    }
                }
                
                // Indentation
                $ind = $xpath->query('.//w:ind', $pPr)->item(0);
                if ($ind) {
                    $left = $ind->getAttribute('w:left');
                    if ($left && is_numeric($left)) {
                        $style['margin-left'] = ($left / 20) . 'pt';
                    }
                    $right = $ind->getAttribute('w:right');
                    if ($right && is_numeric($right)) {
                        $style['margin-right'] = ($right / 20) . 'pt';
                    }
                    $firstLine = $ind->getAttribute('w:firstLine');
                    if ($firstLine && is_numeric($firstLine)) {
                        $style['text-indent'] = ($firstLine / 20) . 'pt';
                    }
                }
            }
            
            break; // Found the paragraph
        }
    }
    
    return $style;
}
```

### 3. Preserve TextRun Styles

**Cần sửa:** `convertText()` để preserve TextRun styles (bold, italic, superscript, subscript):

```php
protected function convertText(Text $text): string
{
    $content = htmlspecialchars($text->getText(), ENT_QUOTES, 'UTF-8');
    
    // Get font style
    $fontStyle = $text->getFontStyle();
    $style = [];
    
    if ($fontStyle) {
        // Font family
        if ($font = $fontStyle->getName()) {
            $style['font-family'] = $this->normalizeFontName($font);
        }
        
        // Font size (in points)
        if ($size = $fontStyle->getSize()) {
            $style['font-size'] = $size . 'pt';
        }
        
        // Bold
        if ($fontStyle->isBold()) {
            $style['font-weight'] = 'bold';
        }
        
        // Italic
        if ($fontStyle->isItalic()) {
            $style['font-style'] = 'italic';
        }
        
        // Underline
        if ($fontStyle->getUnderline() && $fontStyle->getUnderline() !== 'none') {
            $style['text-decoration'] = 'underline';
        }
        
        // Color
        if ($color = $fontStyle->getColor()) {
            $style['color'] = '#' . $color;
        }
        
        // Background color
        if ($bgColor = $fontStyle->getBgColor()) {
            $style['background-color'] = '#' . $bgColor;
        }
        
        // ✅ FIX: Superscript/Subscript
        if ($fontStyle->getSuperScript()) {
            return '<sup' . ($style ? ' style="' . $this->styleArrayToCss($style) . '"' : '') . '>' . $content . '</sup>';
        }
        if ($fontStyle->getSubScript()) {
            return '<sub' . ($style ? ' style="' . $this->styleArrayToCss($style) . '"' : '') . '>' . $content . '</sub>';
        }
    }
    
    if (!empty($style)) {
        $styleAttr = $this->styleArrayToCss($style);
        return '<span style="' . $styleAttr . '">' . $content . '</span>';
    }
    
    return $content;
}
```

### 4. Preserve Line Breaks

**Cần sửa:** `convertParagraphGroup()` để preserve line breaks trong paragraph:

```php
protected function convertParagraphGroup(ParagraphGroup $paragraphGroup): string
{
    $textRuns = $paragraphGroup->getTextRuns();
    
    // Extract paragraph properties from XML
    $paragraphStyle = $this->extractParagraphStyleFromXml($paragraphGroup);
    $styleAttr = $this->styleArrayToCss($paragraphStyle);
    
    $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
    
    // ✅ FIX: Merge all TextRuns into one <p> tag
    // Preserve line breaks if they exist in DOCX
    foreach ($textRuns as $index => $textRun) {
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= $this->convertText($element);
            }
        }
        
        // ✅ FIX: Add line break if TextRun has line break property
        // Note: In DOCX, line breaks are usually represented as <w:br/> in TextRun
        // We need to check XML for line breaks
        if ($this->hasLineBreak($textRun, $index)) {
            $html .= '<br/>';
        }
    }
    
    $html .= '</p>';
    
    return $html;
}

protected function hasLineBreak(TextRun $textRun, int $index): bool
{
    // Parse DOCX XML to check for line breaks
    $zip = new ZipArchive();
    if ($zip->open($this->docxPath) !== true) {
        return false;
    }
    
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if ($xml === false) {
        return false;
    }
    
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    
    // Find TextRun in XML
    $textRuns = $xpath->query('//w:r');
    if ($index < $textRuns->length) {
        $textRunNode = $textRuns->item($index);
        $lineBreaks = $xpath->query('.//w:br', $textRunNode);
        return $lineBreaks->length > 0;
    }
    
    return false;
}
```

## 📊 Kết Quả Mong Đợi

### Trước Fix:
- Paragraph count: 3 (merge TẤT CẢ TextRun)
- Text: "TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC" (SAI)
- Format: ❌ Sai

### Sau Fix:
- Paragraph count: 61 (giữ nguyên paragraph boundaries từ DOCX)
- Text: "TÊN CQ, TC CHỦ QUẢN" (paragraph 1), "1" (paragraph 2), "TÊN CƠ QUAN, TỔ CHỨC" (paragraph 3) (ĐÚNG)
- Format: ✅ Đúng

## 🎯 Implementation Steps

### Step 1: Parse DOCX XML Trực Tiếp
1. ✅ Sửa `groupTextRunsIntoParagraphs()` để parse DOCX XML
2. ✅ Xác định paragraph boundaries từ XML
3. ✅ Chỉ merge TextRun trong cùng một paragraph

### Step 2: Extract Paragraph Properties
1. ✅ Sửa `extractParagraphStyleFromXml()` để extract paragraph properties
2. ✅ Preserve alignment, spacing, indentation
3. ✅ Apply paragraph styles to `<p>` tag

### Step 3: Preserve TextRun Styles
1. ✅ Sửa `convertText()` để preserve TextRun styles
2. ✅ Preserve bold, italic, underline, color
3. ✅ Preserve superscript/subscript

### Step 4: Preserve Line Breaks
1. ✅ Sửa `convertParagraphGroup()` để preserve line breaks
2. ✅ Check XML for `<w:br/>` tags
3. ✅ Add `<br/>` to HTML if needed

### Step 5: Test và Verify
1. ✅ Test trên browser
2. ✅ So sánh với template DOCX gốc
3. ✅ Verify format giống hệt

## 📝 Notes

- Parse DOCX XML trực tiếp là cách duy nhất để xác định paragraph boundaries chính xác
- PhpWord không cung cấp Paragraph class, nên cần parse XML
- Preserve tất cả styles (paragraph và text run) để đảm bảo format giống hệt
- Test kỹ với nhiều template khác nhau để đảm bảo tính tương thích

## 🔍 Testing Checklist

- [ ] Paragraph count giống DOCX gốc
- [ ] Text content giống DOCX gốc (không bị tách, không bị mất)
- [ ] Format giống DOCX gốc (font, size, color, alignment)
- [ ] Spacing giống DOCX gốc (margins, line height, indentation)
- [ ] Styles giống DOCX gốc (bold, italic, underline, superscript, subscript)
- [ ] Line breaks giống DOCX gốc
- [ ] Tables giống DOCX gốc (nếu có)
- [ ] Images giống DOCX gốc (nếu có)



