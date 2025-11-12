# 📋 BÁO CÁO TEST CHI TIẾT - DOCX TO HTML CONVERSION

## 🎯 Mục Tiêu Test

Kiểm tra và so sánh file template DOCX gốc với phần hiển thị trên web để:
1. Xác định các điểm khác biệt
2. Hiểu bug và nguyên nhân
3. Đề xuất giải pháp fix

## 📊 Kết Quả Test

### 1. Browser Test

**File test:** `bien_ban_82_20251109142704.docx`

**Kết quả:**
- ✅ **Paragraph count:** 3 (giảm từ 63 xuống 3 - merge TextRun hoạt động)
- ⚠️ **Text splitting:** Vẫn còn text bị tách trong cùng paragraph
- ⚠️ **Format:** Chưa đúng (text bị xuống dòng giữa chừng)

**HTML Structure:**
```html
<p>
  <span>T</span>
  <span>h</span>
  <span>ời gian bắt đầu: ...............................................</span>
</p>
```

**Phân tích:**
- Các `<span>` nằm cạnh nhau (không có whitespace giữa chúng)
- CSS có thể làm cho các span xuống dòng
- Cần kiểm tra CSS `white-space`, `word-break`, `display`

### 2. Command Line Test - Comparison Tool

**Command:**
```bash
php artisan docx:compare "storage/app/public/documents/bien_ban_82_20251109142704.docx"
```

**Kết quả:**
```
DOCX lines: 61
HTML lines: 3
Differences: 61
```

**Phân tích:**
- ✅ Merge TextRun hoạt động (giảm từ 61 xuống 3 paragraphs)
- ⚠️ So sánh line-by-line không phù hợp (DOCX có 61 TextRun, HTML có 3 paragraphs)
- ⚠️ Cần so sánh text content thay vì line-by-line

**Chi tiết differences:**
- Line 1: DOCX có `TÊN CQ, TC CHỦ QUẢN`, HTML có toàn bộ text nối liền
- Line 2: DOCX có `1`, HTML có text khác hoàn toàn
- Line 3: DOCX có `TÊN CƠ QUAN, TỔ CHỨC`, HTML có text khác

### 3. PhpWord Analysis

**Kết quả:**
```
Total TextRuns: 61
Short TextRuns (<=3 chars): 15
```

**Phân tích:**
- DOCX có 61 TextRun (mỗi TextRun = 1 element trong PhpWord)
- 15 TextRun ngắn (≤3 ký tự) - có thể là text bị tách
- Các TextRun ngắn thường là: "1", "2", "3", "T", "h", "ời", "gian", etc.

**First 20 TextRuns:**
```
 1. [19 chars] TÊN CQ, TC CHỦ QUẢN
 2. [ 1 chars] 1
 3. [20 chars] TÊN CƠ QUAN, TỔ CHỨC
 4. [ 1 chars] 2
 5. [ 3 chars] Số:
 6. [ 3 chars] ...
 7. [ 4 chars] /BB-
 8. [ 3 chars] ...
 9. [ 1 chars] 3
10. [ 3 chars] ...
11. [40 chars] CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2
12. [24 chars] Số:.../BB-...3..._x0007_CỘN
13. [ 8 chars] BIÊN BẢN
14. [10 chars] ..........
15. [ 1 chars] .
16. [ 1 chars] 4
17. [12 chars] ............
18. [ 7 chars] .......
19. [ 2 chars] ..
20. [ 1 chars] .
```

### 4. HTML Analysis

**Kết quả:**
```
Total HTML Paragraphs: 3
Total Spans: 61
```

**Phân tích:**
- HTML có 3 paragraphs (sau khi merge TextRun)
- Tổng cộng 61 spans (mỗi TextRun = 1 span)
- Trung bình: 20.3 spans/paragraph

**First 5 HTML Paragraphs:**
```
1. [248 chars, 61 spans] TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC2Số:.../BB-...3...CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2Số:.../BB-...3...CỘNBIÊN BẢN...........4.......................4............Thời gian bắt đầu: ...............................................(Chữ ký)
2. [177 chars, 61 spans] Họ và tênCHỦ TỌA(Chữ HHọ và tênHỦ TỌA(Chữ ký của người cCCHỦ TỌA(Chữ ký của người có t(Chữ ký của người có Nơi nhận:- ..............;- Lưu: VT,- ..............;- Lưu: VT, Hồ sơ.
3. [ 46 chars, 61 spans] Ghi chú:1 Tên cơ quan, tổ chứ- Lưu: VT, Hồ sơ.
```

**Vấn đề:**
- Paragraph 1 có 61 spans nhưng chỉ có 248 ký tự → text bị tách
- Paragraph 2 có 61 spans nhưng chỉ có 177 ký tự → text bị tách
- Paragraph 3 có 61 spans nhưng chỉ có 46 ký tự → text bị tách

## 🔍 Phân Tích Nguyên Nhân

### 1. Vấn Đề Chính

**Logic merge TextRun:**
- ✅ Đã merge các TextRun liên tiếp thành một paragraph
- ⚠️ Nhưng merge TẤT CẢ TextRun liên tiếp (kể cả khi chúng không thuộc cùng một paragraph trong DOCX gốc)
- ⚠️ Cần xác định paragraph boundaries trong DOCX để merge đúng

### 2. Cấu Trúc DOCX

Trong DOCX, cấu trúc thường là:
```
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
- 3 `Paragraph` (mỗi paragraph có 1 TextRun)
- Nhưng PhpWord có thể parse mỗi TextRun thành một element riêng trong section

**AdvancedDocxToHtmlConverter** hiện tại:
- Merge 3 TextRun thành 1 paragraph (SAI)
- Kết quả: "TÊN CQ, TC CHỦ QUẢN1TÊN CƠ QUAN, TỔ CHỨC" (SAI)

**Mong muốn:**
- Giữ 3 paragraphs riêng biệt
- Chỉ merge TextRun trong cùng một paragraph

### 3. Vấn Đề với PhpWord

**PhpWord** có thể parse DOCX thành:
- Section → Elements (TextRun, Table, Image, etc.)
- Không có class `Paragraph` riêng biệt

**Vấn đề:**
- Không thể phân biệt TextRun nào thuộc cùng một paragraph
- Cần parse DOCX XML trực tiếp để xác định paragraph boundaries

## 🔧 Giải Pháp

### 1. Parse DOCX XML Trực Tiếp

**Cần sửa:** `groupTextRunsIntoParagraphs()` để parse DOCX XML trực tiếp:

```php
protected function groupTextRunsIntoParagraphs(array $elements): array
{
    // ✅ FIX: Parse DOCX XML trực tiếp để xác định paragraph boundaries
    $zip = new ZipArchive();
    $zip->open($this->docxPath);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    
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
            $textNodes = $xpath->query('.//w:t', $textRunNode);
            $text = '';
            foreach ($textNodes as $textNode) {
                $text .= $textNode->nodeValue;
            }
            
            // Find corresponding PhpWord TextRun element
            if ($elementIndex < count($elements) && $elements[$elementIndex] instanceof TextRun) {
                $textRuns[] = $elements[$elementIndex];
                $elementIndex++;
            }
        }
        
        // If we have TextRuns, create a paragraph group
        if (!empty($textRuns)) {
            $grouped[] = new ParagraphGroup($textRuns);
        }
    }
    
    // Add remaining elements (Table, Image, etc.)
    while ($elementIndex < count($elements)) {
        $grouped[] = $elements[$elementIndex];
        $elementIndex++;
    }
    
    return $grouped;
}
```

### 2. So Sánh Text Content (không phải Line-by-Line)

**Cần sửa:** `compareTexts()` để so sánh text content:

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
```

## 📊 Kết Quả So Sánh

### Trước Fix:
- Paragraph count: 63
- Text bị tách: "T", "h", "ời gian" (3 paragraphs)
- Format: ❌ Sai

### Sau Fix (Merge TextRun):
- Paragraph count: 3
- Text vẫn bị tách: "T", "h", "ời gian" (1 paragraph, nhưng vẫn bị tách)
- Format: ⚠️ Chưa đúng

### Cần Fix Thêm:
- Parse DOCX XML trực tiếp để xác định paragraph boundaries
- Chỉ merge TextRun trong cùng một paragraph
- So sánh text content thay vì line-by-line

## 🎯 Next Steps

1. ✅ **Merge TextRun:** Hoàn thành (giảm từ 63 xuống 3)
2. ⏳ **Parse DOCX XML:** Cần parse XML trực tiếp để xác định paragraph boundaries
3. ⏳ **Fix comparison:** Cần so sánh text content thay vì line-by-line
4. ⏳ **Test:** Test lại trên browser sau khi fix

## 📝 Notes

- Logic merge TextRun đã hoạt động (giảm từ 63 xuống 3 paragraphs)
- Text vẫn bị tách vì mỗi TextRun có một Text element riêng (đây là bình thường trong HTML)
- Vấn đề thực sự là: Logic merge đang merge TẤT CẢ TextRun liên tiếp, không phân biệt paragraph boundaries
- Cần parse DOCX XML trực tiếp để xác định paragraph boundaries và chỉ merge TextRun trong cùng một paragraph



