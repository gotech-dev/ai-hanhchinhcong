# 📋 BÁO CÁO FIX FORMAT - TEXT BỊ XUỐNG DÒNG GIỮA CHỪNG

## ✅ Đã Sửa

### 1. Vấn Đề

**AdvancedDocxToHtmlConverter** đang convert mỗi `TextRun` thành một `<p>` tag riêng biệt, dẫn đến text bị tách và xuống dòng giữa chừng.

**Ví dụ:**
- "Thời gian" → `<p>T</p><p>h</p><p>ời gian</p>` (SAI)
- "Họ và tên" → `<p>H</p><p>Họ và</p><p>t</p><p>ê</p><p>n</p>` (SAI)
- "Chữ ký" → `<p>Ch</p><p>ữ</p><p> ký</p>` (SAI)

### 2. Nguyên Nhân

Trong DOCX, một paragraph có thể chứa nhiều `TextRun` (mỗi run có style khác nhau như bold, italic, superscript, subscript). PhpWord có thể parse mỗi `TextRun` thành một element riêng biệt trong section, dẫn đến text bị tách.

### 3. Giải Pháp

**Đã implement:**
1. ✅ Thêm class `ParagraphGroup` để group nhiều TextRun thành một paragraph
2. ✅ Thêm method `groupTextRunsIntoParagraphs()` để merge các TextRun liên tiếp
3. ✅ Thêm method `convertParagraphGroup()` để convert ParagraphGroup thành một `<p>` tag
4. ✅ Sửa `convertElement()` để xử lý `ParagraphGroup`
5. ✅ Sửa `convertToHtml()` để group TextRuns trước khi convert

### 4. Code Changes

**File: `app/Services/AdvancedDocxToHtmlConverter.php`**

**Thêm class `ParagraphGroup`:**
```php
class ParagraphGroup
{
    protected $textRuns;
    
    public function __construct(array $textRuns)
    {
        $this->textRuns = $textRuns;
    }
    
    public function getTextRuns(): array
    {
        return $this->textRuns;
    }
}
```

**Thêm method `groupTextRunsIntoParagraphs()`:**
```php
protected function groupTextRunsIntoParagraphs(array $elements): array
{
    $grouped = [];
    $currentParagraph = [];
    
    foreach ($elements as $element) {
        if ($element instanceof TextRun) {
            // Add TextRun to current paragraph
            $currentParagraph[] = $element;
        } else {
            // If we have accumulated TextRuns, create a paragraph group
            if (!empty($currentParagraph)) {
                // ✅ FIX: Merge ALL consecutive TextRuns into one paragraph
                $grouped[] = new ParagraphGroup($currentParagraph);
                $currentParagraph = [];
            }
            // Add non-TextRun element as-is
            $grouped[] = $element;
        }
    }
    
    // Don't forget the last paragraph if any
    if (!empty($currentParagraph)) {
        $grouped[] = new ParagraphGroup($currentParagraph);
    }
    
    return $grouped;
}
```

**Thêm method `convertParagraphGroup()`:**
```php
protected function convertParagraphGroup(ParagraphGroup $paragraphGroup): string
{
    $textRuns = $paragraphGroup->getTextRuns();
    
    // Get paragraph style from first TextRun (if available)
    $firstTextRun = $textRuns[0];
    $style = $this->extractElementStyle($firstTextRun);
    $styleAttr = $this->styleArrayToCss($style);
    
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
```

**Sửa `convertElement()`:**
```php
protected function convertElement($element): string
{
    $html = '';
    
    // ✅ FIX: Handle ParagraphGroup (multiple TextRuns merged into one paragraph)
    if ($element instanceof ParagraphGroup) {
        $html .= $this->convertParagraphGroup($element);
    } elseif ($element instanceof TextRun) {
        // TextRun độc lập (không thuộc paragraph group)
        $html .= $this->convertTextRun($element);
    } elseif ($element instanceof Text) {
        $html .= $this->convertText($element);
    } elseif ($element instanceof Table) {
        $html .= $this->convertTable($element);
    } elseif ($element instanceof Image) {
        $html .= $this->convertImage($element);
    } elseif (method_exists($element, 'getElements')) {
        // Container elements
        foreach ($element->getElements() as $child) {
            $html .= $this->convertElement($child);
        }
    }
    
    return $html;
}
```

**Sửa `convertToHtml()`:**
```php
protected function convertToHtml(): string
{
    $html = '';
    
    foreach ($this->phpWord->getSections() as $section) {
        $html .= '<div class="docx-section">';
        
        // ✅ FIX: Group consecutive TextRuns into paragraphs
        $elements = $section->getElements();
        $groupedElements = $this->groupTextRunsIntoParagraphs($elements);
        
        foreach ($groupedElements as $element) {
            $html .= $this->convertElement($element);
        }
        
        $html .= '</div>';
    }
    
    return $html;
}
```

## 📊 Kết Quả Mong Đợi

### Trước Fix:
```html
<p>T</p>
<p>h</p>
<p>ời gian bắt đầu: ...............................................</p>
```

### Sau Fix:
```html
<p>
  <span>T</span>
  <span>h</span>
  <span>ời gian bắt đầu: ...............................................</span>
</p>
```

## 🎯 Next Steps

1. ✅ **Code changes:** Hoàn thành
2. ⏳ **Test:** Test lại trên browser để kiểm tra format
3. ⏳ **Verify:** So sánh với template DOCX gốc

## 📝 Notes

- Logic hiện tại merge TẤT CẢ các TextRun liên tiếp thành một paragraph
- Điều này đảm bảo text không bị tách (ví dụ: "T", "h", "ời gian" → "Thời gian")
- Style của từng TextRun vẫn được preserve (bold, italic, superscript, subscript)
- Paragraph style được lấy từ TextRun đầu tiên



