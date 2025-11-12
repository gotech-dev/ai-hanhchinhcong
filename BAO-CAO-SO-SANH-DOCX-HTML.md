# 📋 BÁO CÁO SO SÁNH DOCX GỐC VỚI HTML PREVIEW

## 🎯 Mục Tiêu

So sánh file template DOCX gốc với phần hiển thị trên web để:
1. Tìm ra các điểm khác biệt
2. Hiểu bug và fix
3. Đảm bảo format giống nhau

## 📊 Kết Quả So Sánh

### 1. Test trên Browser

**Kết quả:**
- ✅ Paragraph count giảm từ 63 xuống 3 (sau khi merge TextRun)
- ⚠️ Text vẫn bị tách trong cùng một paragraph (ví dụ: "T" "h" "ời gian")
- ⚠️ Format vẫn chưa đúng (text bị xuống dòng giữa chừng)

**HTML hiện tại:**
```html
<p>
  <span>T</span>
  <span>h</span>
  <span>ời gian bắt đầu: ...............................................</span>
</p>
```

**Vấn đề:** Các `<span>` nằm cạnh nhau nhưng có thể bị xuống dòng do CSS hoặc whitespace.

### 2. Test bằng Command Line

**Command:**
```bash
php artisan docx:compare "storage/app/public/documents/bien_ban_81_20251109093042.docx"
```

**Kết quả:**
- DOCX lines: 61 (mỗi TextRun = 1 line)
- HTML lines: 3 (sau khi merge TextRun)
- Differences: 61

**Phân tích:**
- ✅ Merge TextRun hoạt động (giảm từ 61 xuống 3 paragraphs)
- ⚠️ Text vẫn bị tách vì mỗi TextRun có một Text element riêng
- ⚠️ Cần so sánh text content thay vì line-by-line

### 3. So Sánh Chi Tiết

**Line 1:**
- DOCX: `TÊN CQ, TC CHỦ QUẢN`
- HTML: `TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC2Số:.../BB-...3...CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2Số:.../BB-...3...CỘNBIÊN BẢN...........4.......................4............Thời gian bắt đầu: ...............................................(Chữ ký)`
- **Vấn đề:** Nhiều TextRun đã được merge thành một paragraph, nhưng text bị nối liền nhau (không có space giữa các TextRun)

**Line 2:**
- DOCX: `1`
- HTML: `Họ và tênCHỦ TỌA(Chữ HHọ và tênHỦ TỌA(Chữ ký của người cCCHỦ TỌA(Chữ ký của người có t(Chữ ký của người có Nơi nhận:- ..............;- Lưu: VT,- ..............;- Lưu: VT, Hồ sơ.`
- **Vấn đề:** Text bị nối liền nhau, không có line break giữa các paragraph

## 🔍 Phân Tích Nguyên Nhân

### 1. Vấn Đề Chính

**Logic merge TextRun:**
- ✅ Đã merge các TextRun liên tiếp thành một paragraph
- ⚠️ Nhưng text bị nối liền nhau (không có space/line break)
- ⚠️ Cần thêm space hoặc line break giữa các TextRun trong cùng một paragraph

### 2. Cấu Trúc DOCX

Trong DOCX, một paragraph có thể có nhiều TextRun:
```
<w:p>  <!-- Paragraph -->
  <w:r>  <!-- TextRun 1 -->
    <w:t>T</w:t>
  </w:r>
  <w:r>  <!-- TextRun 2 -->
    <w:t>h</w:t>
  </w:r>
  <w:r>  <!-- TextRun 3 -->
    <w:t>ời gian</w:t>
  </w:r>
</w:p>
```

**PhpWord** đọc thành:
- 1 `Paragraph` chứa 3 `TextRun`
- Mỗi `TextRun` có thể có style khác nhau

**AdvancedDocxToHtmlConverter** hiện tại:
- Merge 3 TextRun thành 1 `<p>` tag
- Nhưng text bị nối liền: "Thời gian" (đúng)
- Vấn đề: Có thể có whitespace hoặc line break giữa các TextRun trong DOCX gốc

### 3. Vấn Đề với Line-by-Line Comparison

**Vấn đề:**
- DOCX có 61 lines (mỗi TextRun = 1 line)
- HTML có 3 lines (sau khi merge)
- So sánh line-by-line không phù hợp

**Giải pháp:**
- So sánh text content (không phải line-by-line)
- Hoặc so sánh paragraph-by-paragraph (merge TextRun trong DOCX trước khi so sánh)

## 🔧 Giải Pháp

### 1. Sửa Logic Merge TextRun

**Cần sửa:** `convertParagraphGroup()` để thêm space giữa các TextRun nếu cần:

```php
protected function convertParagraphGroup(ParagraphGroup $paragraphGroup): string
{
    $textRuns = $paragraphGroup->getTextRuns();
    
    // Get paragraph style from first TextRun
    $firstTextRun = $textRuns[0];
    $style = $this->extractElementStyle($firstTextRun);
    $styleAttr = $this->styleArrayToCss($style);
    
    $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
    
    // ✅ FIX: Merge all TextRuns into one <p> tag
    foreach ($textRuns as $index => $textRun) {
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= $this->convertText($element);
            }
        }
        
        // ✅ FIX: Add space between TextRuns if needed (except for last one)
        // Note: In DOCX, TextRuns in the same paragraph usually don't need space
        // But if there's a line break in DOCX, we might need to add it
    }
    
    $html .= '</p>';
    
    return $html;
}
```

### 2. So Sánh Text Content (không phải Line-by-Line)

**Cần sửa:** `compareTexts()` để so sánh text content thay vì line-by-line:

```php
protected function compareTexts(array $docxText, array $htmlText): array
{
    // ✅ FIX: Merge TextRuns in DOCX text (similar to HTML)
    $docxMerged = $this->mergeTextRunsInDocx($docxText);
    
    // Compare merged texts
    $differences = [];
    $maxLines = max(count($docxMerged), count($htmlText));
    
    for ($i = 0; $i < $maxLines; $i++) {
        $docxLine = $docxMerged[$i] ?? '';
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

protected function mergeTextRunsInDocx(array $docxText): array
{
    // Merge consecutive TextRuns (similar to ParagraphGroup logic)
    $merged = [];
    $currentParagraph = [];
    
    foreach ($docxText as $line) {
        // If line is very short (≤3 chars), it's likely part of a word
        if (mb_strlen($line) <= 3) {
            $currentParagraph[] = $line;
        } else {
            // If we have accumulated short lines, merge them
            if (!empty($currentParagraph)) {
                $merged[] = implode('', $currentParagraph);
                $currentParagraph = [];
            }
            // Add long line as-is
            $merged[] = $line;
        }
    }
    
    // Don't forget the last paragraph if any
    if (!empty($currentParagraph)) {
        $merged[] = implode('', $currentParagraph);
    }
    
    return $merged;
}
```

## 📊 Kết Quả Test

### Browser Test

**Paragraph count:**
- Trước fix: 63 paragraphs
- Sau fix: 3 paragraphs
- ✅ **Cải thiện:** Giảm 95% (63 → 3)

**Text splitting:**
- Trước fix: "T", "h", "ời gian" (3 paragraphs)
- Sau fix: "T", "h", "ời gian" (1 paragraph, nhưng vẫn bị tách)
- ⚠️ **Vấn đề:** Text vẫn bị tách trong cùng một paragraph

### Command Line Test

**Comparison:**
- DOCX lines: 61
- HTML lines: 3
- Differences: 61

**Phân tích:**
- ✅ Merge TextRun hoạt động
- ⚠️ So sánh line-by-line không phù hợp (cần so sánh text content)

## 🎯 Next Steps

1. ✅ **Code changes:** Hoàn thành (merge TextRun)
2. ⏳ **Fix text splitting:** Cần xử lý whitespace/line break giữa TextRun
3. ⏳ **Fix comparison:** Cần so sánh text content thay vì line-by-line
4. ⏳ **Test:** Test lại trên browser sau khi fix

## 📝 Notes

- Logic merge TextRun đã hoạt động (giảm từ 63 xuống 3 paragraphs)
- Text vẫn bị tách vì mỗi TextRun có một Text element riêng (đây là bình thường trong HTML)
- Vấn đề thực sự có thể là CSS hoặc whitespace giữa các span
- Cần kiểm tra CSS để đảm bảo các span không bị xuống dòng



