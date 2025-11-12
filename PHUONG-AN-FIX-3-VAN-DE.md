# 📋 PHƯƠNG ÁN CHI TIẾT: Fix 3 Vấn Đề Còn Lại

## 🎯 Mục Tiêu

1. **Paragraph merging:** Giảm từ 43 paragraphs xuống ~16-20 paragraphs
2. **Text bị tách:** Fix các trường hợp text bị tách như `<p>1 T</p><p><sup>ê</sup></p><p>n</p>`
3. **Unicode characters:** Clean up các ký tự lạ như `ࠀ` trong text content

## ⚠️ Lưu Ý: Không Ảnh Hưởng Logic Hiện Tại

- ✅ Giữ nguyên tất cả logic merge hiện tại
- ✅ Chỉ thêm logic mới, không thay đổi logic cũ
- ✅ Thêm method mới, không sửa method cũ
- ✅ Có thể rollback dễ dàng

## 🔍 Phân Tích Vấn Đề

### Vấn Đề 1: Paragraph Merging (43 → ~16-20)

**Hiện trạng:**
- Log cho thấy: Merge 6 iterations, tổng 36 paragraphs được merge
- Từ 79 → 43 paragraphs (giảm 45.6%)
- Mục tiêu: ~16-20 paragraphs (cần giảm thêm 23-27 paragraphs)

**Nguyên nhân:**
- Logic merge hiện tại chỉ merge paragraph ≤ 20 ký tự
- Một số paragraph 21-40 ký tự vẫn có thể merge được
- Logic merge quá strict với paragraph có nội dung thực sự

**Ví dụ từ browser test:**
- `<p>TÊN CQ, TC CHỦ QUẢN</p>` (19 ký tự) - Có thể merge với paragraph ngắn khác
- `<p>TÊN CƠ QUAN, TỔ CHỨC</p>` (20 ký tự) - Có thể merge với paragraph ngắn khác
- `<p>Số: ... /CĐ- ...</p>` (16 ký tự) - Có thể merge với paragraph ngắn khác
- `<p>...CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2</p>` (43 ký tự) - Không merge được (quá dài)
- `<p>Số:.../CĐ-...3...CỘN</p>` (20 ký tự) - Có thể merge với paragraph ngắn khác
- `<p>CÔNG ĐIỆN .........</p>` (19 ký tự) - Có thể merge với paragraph ngắn khác

### Vấn Đề 2: Text Bị Tách

**Hiện trạng:**
- Vẫn còn: `<p>1 T</p><p><sup>ê</sup></p><p>n</p>`
- Vẫn còn: `<p>c</p><p>ơ</p>`
- Vẫn còn: `<p>ch</p><p>ứ</p>`

**Nguyên nhân:**
- Logic merge hiện tại chỉ merge nếu text ≤ 3 ký tự
- Pattern matching chưa cover hết các trường hợp
- Post-processing chưa đủ mạnh để merge text bị tách

**Ví dụ từ browser test:**
- `<p>1 T</p><p><sup>ê</sup></p><p>n</p>` - "1 T" có 3 ký tự nhưng có space, không match pattern hiện tại
- `<p>c</p><p>ơ</p>` - Cả 2 đều ≤ 3 ký tự nhưng không có sup/sub, không merge được
- `<p>n</p>` (1 ký tự) - Có thể merge với paragraph trước/sau
- `<p>ơ</p>` (1 ký tự) - Có thể merge với paragraph trước/sau
- `<p>ba</p>` (2 ký tự) - Có thể merge với paragraph trước/sau

### Vấn Đề 3: Unicode Characters

**Hiện trạng:**
- Vẫn còn: `ࠀ` trong "2 Tên cơ quࠀ2 Tên cơ quࠀ"

**Nguyên nhân:**
- Clean up Unicode chỉ xóa trong HTML output (sau khi Pandoc convert)
- Không clean up trong text content của paragraph (sau khi merge)
- Unicode replacement character xuất hiện trong DOCX gốc

## 🛠️ Phương Án Chi Tiết

### Giải Pháp 1: Cải Thiện Paragraph Merging (43 → ~16-20)

#### 1.1. Tăng Threshold Merge Từ 20 Lên 30 Ký Tự

**Logic mới:**
- Merge paragraph ngắn (< 30 ký tự) nếu không có block elements
- Chỉ merge nếu cả 2 đều ≤ 30 ký tự và không có block elements
- **Lưu ý:** Không merge nếu một trong hai > 30 ký tự (giữ spacing)

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph ngắn (< 30 ký tự) nếu không có block elements
// Chỉ merge nếu cả 2 đều ngắn và không có block elements
// Giữ nguyên logic merge paragraph ≤ 20 ký tự (không thay đổi)
if ($textLength1 <= 30 && $textLength2 <= 30 && $textLength1 > 0 && $textLength2 > 0) {
    // Check if they have block elements
    $hasBlock1 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p1);
    $hasBlock2 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p2);
    
    if (!$hasBlock1 && !$hasBlock2) {
        $totalMerged++;
        $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
        return '<p>' . $merged . '</p>';
    }
}
```

**Lưu ý:**
- Giữ nguyên logic merge paragraph ≤ 20 ký tự (không thay đổi)
- Thêm logic mới cho paragraph ≤ 30 ký tự (thêm mới)
- Không ảnh hưởng logic hiện tại
- **Quan trọng:** Chỉ merge nếu cả 2 đều ≤ 30 ký tự, không merge nếu một trong hai > 30 ký tự

#### 1.2. Merge Paragraph Có Pattern Tương Tự

**Logic mới:**
- Merge paragraph có pattern tương tự (ví dụ: cả 2 đều bắt đầu bằng "...")
- Merge paragraph chỉ có dấu chấm câu hoặc số
- Merge paragraph chỉ có superscript/subscript với paragraph trước/sau

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph có pattern tương tự
// Pattern 1: Cả 2 đều bắt đầu bằng "..." hoặc chỉ có dấu chấm câu
if (preg_match('/^\.{3,}/', $text1) && preg_match('/^\.{3,}/', $text2)) {
    $totalMerged++;
    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
    return '<p>' . $merged . '</p>';
}

// Pattern 2: Cả 2 đều chỉ có số hoặc dấu chấm câu
if (preg_match('/^[\d\.\s]+$/', $text1) && preg_match('/^[\d\.\s]+$/', $text2)) {
    $totalMerged++;
    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
    return '<p>' . $merged . '</p>';
}

// Pattern 3: Merge paragraph chỉ có superscript/subscript với paragraph trước/sau
// Ví dụ: <p>TÊN CQ, TC CHỦ QUẢN</p><p><sup>1</sup></p> → <p>TÊN CQ, TC CHỦ QUẢN <sup>1</sup></p>
if (preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2) && $textLength2 === 0 && $textLength1 > 0 && $textLength1 <= 30) {
    $totalMerged++;
    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
    return '<p>' . $content1 . ' ' . $content2 . '</p>';
}
```

**Lưu ý:**
- Chỉ thêm logic mới, không thay đổi logic cũ
- Có thể rollback dễ dàng

#### 1.3. Merge Paragraph Rỗng Hoặc Chỉ Có Whitespace (Cải Thiện)

**Logic mới:**
- Merge paragraph rỗng hoặc chỉ có whitespace → **Đã có, giữ nguyên**
- Merge paragraph chỉ có dấu chấm câu → **Đã có, giữ nguyên**
- **Thêm mới:** Merge paragraph chỉ có số (1-2 chữ số)

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph chỉ có số (1-2 chữ số)
if (preg_match('/^\d{1,2}$/', $text1) || preg_match('/^\d{1,2}$/', $text2)) {
    $totalMerged++;
    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
    return '<p>' . $merged . '</p>';
}
```

### Giải Pháp 2: Fix Text Bị Tách

#### 2.1. Cải Thiện Pattern Matching Cho Text Bị Tách

**Logic mới:**
- Pattern 1: `<p>text (1-5 ký tự, có thể có space)</p><p><sup>...</sup></p><p>text (1-5 ký tự)</p>`
- Pattern 2: `<p>char</p><p>char</p>` (cả 2 đều ≤ 3 ký tự, không có sup/sub)
- Pattern 3: `<p>text</p><p>text</p>` (cả 2 đều ≤ 5 ký tự, không có block elements)

**Code đề xuất:**
```php
/**
 * Post-process HTML to merge text split by superscript/subscript (Cải Thiện)
 * 
 * Pattern: <p>text (1-5 ký tự, có thể có space)</p><p><sup>...</sup></p><p>text (1-5 ký tự)</p> → <p>text<sup>...</sup>text</p>
 * 
 * @param string $html
 * @return string
 */
protected function mergeSplitTextWithSupSub(string $html): string
{
    // ✅ FIX: Merge pattern: <p>text (1-5 ký tự, có thể có space)</p><p><sup>...</sup></p><p>text (1-5 ký tự)</p>
    // Tăng threshold từ 2 ký tự lên 5 ký tự và cho phép space
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]{1,5})\s*<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)\s*(<p[^>]*>([^<]{1,5})<\/p>)/i',
        function($matches) {
            $text1 = trim($matches[2]);
            $pSup = $matches[3];
            $text2 = $matches[6];
            
            // ✅ FIX: Extract sup/sub content
            preg_match('/<(sup|sub)[^>]*>([\s\S]*?)<\/\1>/i', $pSup, $supMatch);
            $supContent = $supMatch ? '<' . $supMatch[1] . '>' . $supMatch[2] . '</' . $supMatch[1] . '>' : '';
            
            // ✅ FIX: Merge thành một paragraph
            $merged = $text1 . $supContent . $text2;
            return '<p>' . $merged . '</p>';
        },
        $html
    );
    
    return $html;
}
```

#### 2.2. Merge Text Không Có Superscript/Subscript

**Logic mới:**
- Merge pattern: `<p>char</p><p>char</p>` (cả 2 đều ≤ 3 ký tự, không có sup/sub)
- Merge pattern: `<p>text</p><p>text</p>` (cả 2 đều ≤ 5 ký tự, không có block elements)
- **Quan trọng:** Chỉ merge nếu cả 2 đều rất ngắn và không có block elements

**Code đề xuất:**
```php
/**
 * Merge text không có superscript/subscript
 * 
 * Pattern: <p>char</p><p>char</p> → <p>charchar</p>
 * Pattern: <p>text</p><p>text</p> → <p>text text</p>
 * 
 * @param string $html
 * @return string
 */
protected function mergeSplitTextWithoutSupSub(string $html): string
{
    // ✅ FIX: Merge pattern: <p>char</p><p>char</p> (cả 2 đều ≤ 3 ký tự, không có sup/sub)
    // Ví dụ: <p>c</p><p>ơ</p> → <p>cơ</p>
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]{1,3})<\/p>)\s*(<p[^>]*>([^<]{1,3})<\/p>)/i',
        function($matches) {
            $p1 = $matches[1];
            $p2 = $matches[3];
            $text1 = trim($matches[2]);
            $text2 = trim($matches[4]);
            
            // ✅ FIX: Chỉ merge nếu không có sup/sub và không có block elements
            if (!preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p1) && 
                !preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p2)) {
                // ✅ FIX: Merge thành một paragraph (không có space nếu cả 2 đều rất ngắn)
                $merged = $text1 . $text2;
                return '<p>' . $merged . '</p>';
            }
            
            return $p1 . "\n" . $p2;
        },
        $html
    );
    
    // ✅ FIX: Merge pattern: <p>text</p><p>text</p> (cả 2 đều ≤ 5 ký tự, không có block elements)
    // Ví dụ: <p>ba</p><p>n</p> → <p>ban</p> hoặc <p>ba n</p>
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]{1,5})<\/p>)\s*(<p[^>]*>([^<]{1,5})<\/p>)/i',
        function($matches) {
            $p1 = $matches[1];
            $p2 = $matches[3];
            $text1 = trim($matches[2]);
            $text2 = trim($matches[4]);
            
            // ✅ FIX: Chỉ merge nếu không có sup/sub và không có block elements
            if (!preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p1) && 
                !preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p2)) {
                // ✅ FIX: Merge không có space nếu cả 2 đều rất ngắn (≤ 2 ký tự)
                if (strlen($text1) <= 2 && strlen($text2) <= 2) {
                    $merged = $text1 . $text2;
                } else {
                    $merged = $text1 . ' ' . $text2;
                }
                return '<p>' . $merged . '</p>';
            }
            
            return $p1 . "\n" . $p2;
        },
        $html
    );
    
    return $html;
}
```

#### 2.3. Merge Text Có Space Trong Pattern

**Logic mới:**
- Pattern: `<p>1 T</p><p><sup>ê</sup></p><p>n</p>` → `<p>1 T<sup>ê</sup>n</p>`
- Pattern: `<p>text </p><p><sup>...</sup></p><p>text</p>` → `<p>text <sup>...</sup>text</p>`
- **Quan trọng:** Pattern này đã được cover trong `mergeSplitTextWithSupSub()` cải thiện, nhưng cần thêm logic riêng cho trường hợp có space

**Code đề xuất:**
```php
/**
 * Merge text có space trong pattern
 * 
 * Pattern: <p>1 T</p><p><sup>ê</sup></p><p>n</p> → <p>1 T<sup>ê</sup>n</p>
 * 
 * @param string $html
 * @return string
 */
protected function mergeSplitTextWithSpace(string $html): string
{
    // ✅ FIX: Merge pattern: <p>text (có thể có space, 1-5 ký tự)</p><p><sup>...</sup></p><p>text (1-5 ký tự)</p>
    // Pattern này cover trường hợp "1 T" có space
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]{1,5})\s*<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)\s*(<p[^>]*>([^<]{1,5})<\/p>)/i',
        function($matches) {
            $text1 = trim($matches[2]);
            $pSup = $matches[3];
            $text2 = trim($matches[6]);
            
            // ✅ FIX: Extract sup/sub content
            preg_match('/<(sup|sub)[^>]*>([\s\S]*?)<\/\1>/i', $pSup, $supMatch);
            $supContent = $supMatch ? '<' . $supMatch[1] . '>' . $supMatch[2] . '</' . $supMatch[1] . '>' : '';
            
            // ✅ FIX: Merge thành một paragraph (giữ space trong text1 nếu có)
            $merged = $text1 . $supContent . $text2;
            return '<p>' . $merged . '</p>';
        },
        $html
    );
    
    return $html;
}
```

**Lưu ý:**
- Pattern này có thể overlap với `mergeSplitTextWithSupSub()` cải thiện
- Cần đảm bảo không merge 2 lần cùng một pattern
- Có thể gọi sau `mergeSplitTextWithSupSub()` để cover các trường hợp còn lại

### Giải Pháp 3: Clean Up Unicode Characters

#### 3.1. Clean Up Unicode Trong Text Content Của Paragraph

**Logic mới:**
- Clean up Unicode replacement character (`ࠀ`) trong text content của paragraph
- Clean up control characters (`_x0007_`) trong text content của paragraph
- Clean up sau khi merge paragraph (trong `ensureParagraphStructure`)

**Code đề xuất:**
```php
/**
 * Clean up Unicode characters trong text content
 * 
 * @param string $html
 * @return string
 */
protected function cleanUpUnicodeInText(string $html): string
{
    // ✅ FIX: Clean up Unicode replacement character trong text content
    // Pattern: Tìm và xóa `ࠀ` trong text content của paragraph
    $html = preg_replace_callback(
        '/<p[^>]*>([\s\S]*?)<\/p>/i',
        function($matches) {
            $content = $matches[1];
            
            // ✅ FIX: Clean up Unicode replacement character
            $content = preg_replace('/[\x{FFFD}]/u', '', $content);
            
            // ✅ FIX: Clean up control characters
            $content = preg_replace('/_x000[0-9a-fA-F]+_/i', '', $content);
            
            return '<p>' . $content . '</p>';
        },
        $html
    );
    
    return $html;
}
```

#### 3.2. Clean Up Unicode Sau Khi Merge Paragraph

**Logic mới:**
- Clean up Unicode sau khi merge paragraph (trong `ensureParagraphStructure`)
- Clean up Unicode sau khi post-process HTML

**Code đề xuất:**
```php
protected function ensureParagraphStructure(string $html): string
{
    // ... existing code (remove header, count p tags) ...
    
    if ($pTagCount > 5) {
        // Merge consecutive short <p> tags into single paragraphs
        Log::info('🔵 [PandocDocxToHtmlConverter] Merging short paragraphs', [
            'pTagCount' => $pTagCount,
            'htmlLength' => strlen($html),
        ]);
        $html = $this->mergeShortParagraphs($html);
        
        // ✅ FIX: Post-process để merge text cùng một từ
        $html = $this->mergeSplitTextWithSupSub($html);
        $html = $this->mergeTextWithSupSubPattern2($html);
        $html = $this->mergeSplitTextWithoutSupSub($html);
        $html = $this->mergeSplitTextWithSpace($html);
        
        // ✅ FIX: Clean up Unicode trong text content
        $html = $this->cleanUpUnicodeInText($html);
        
        $newPTagCount = substr_count($html, '<p');
        Log::info('🔵 [PandocDocxToHtmlConverter] After merging', [
            'newPTagCount' => $newPTagCount,
            'htmlLength' => strlen($html),
        ]);
        return $html;
    }
    
    // ... rest of existing code ...
}
```

## 📝 Implementation Plan

### Step 1: Cải Thiện Paragraph Merging Logic

**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Method:** `mergeShortParagraphs()`

**Changes:**
1. ✅ Thêm logic merge paragraph ≤ 30 ký tự (thêm mới, không thay đổi logic ≤ 20 ký tự)
2. ✅ Thêm logic merge paragraph có pattern tương tự (thêm mới)
3. ✅ Thêm logic merge paragraph chỉ có số (thêm mới)
4. ✅ Thêm logic merge paragraph chỉ có superscript/subscript với paragraph trước/sau (thêm mới)

**Code changes:**
```php
protected function mergeShortParagraphs(string $html): string
{
    // ... existing code (protect block elements) ...
    
    while ($iteration < $maxIterations) {
        $originalHtml = $html;
        
        $html = preg_replace_callback('/(<p[^>]*>[\s\S]*?<\/p>)\s*(<p[^>]*>[\s\S]*?<\/p>)/i', function($matches) use (&$totalMerged) {
            // ... existing code (extract content, text length) ...
            
            // ✅ FIX 1: Merge paragraph ≤ 30 ký tự (thêm mới, không thay đổi logic ≤ 20 ký tự)
            if ($textLength1 <= 30 && $textLength2 <= 30 && $textLength1 > 0 && $textLength2 > 0) {
                // ... new logic ...
            }
            
            // ✅ FIX 2: Merge paragraph có pattern tương tự (thêm mới)
            if (preg_match('/^\.{3,}/', $text1) && preg_match('/^\.{3,}/', $text2)) {
                // ... new logic ...
            }
            
            if (preg_match('/^[\d\.\s]+$/', $text1) && preg_match('/^[\d\.\s]+$/', $text2)) {
                // ... new logic ...
            }
            
            // ✅ FIX 3: Merge paragraph chỉ có số (thêm mới)
            if (preg_match('/^\d{1,2}$/', $text1) || preg_match('/^\d{1,2}$/', $text2)) {
                // ... new logic ...
            }
            
            // ... rest of existing logic (giữ nguyên) ...
        }, $html);
        
        // ... rest of existing code ...
    }
    
    return $html;
}
```

### Step 2: Cải Thiện Post-Processing Cho Text Bị Tách

**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Method:** `ensureParagraphStructure()`

**Changes:**
1. ✅ Cải thiện `mergeSplitTextWithSupSub()` - Tăng threshold từ 3 lên 5 ký tự
2. ✅ Thêm method `mergeSplitTextWithoutSupSub()` - Merge text không có sup/sub
3. ✅ Thêm method `mergeSplitTextWithSpace()` - Merge text có space trong pattern
4. ✅ Gọi 3 method mới trong `ensureParagraphStructure()`

**Code changes:**
```php
protected function ensureParagraphStructure(string $html): string
{
    // ... existing code ...
    
    if ($pTagCount > 5) {
        $html = $this->mergeShortParagraphs($html);
        
        // ✅ FIX: Post-process để merge text cùng một từ
        $html = $this->mergeSplitTextWithSupSub($html); // Cải thiện
        $html = $this->mergeTextWithSupSubPattern2($html); // Giữ nguyên
        $html = $this->mergeSplitTextWithoutSupSub($html); // Thêm mới
        $html = $this->mergeSplitTextWithSpace($html); // Thêm mới
        
        // ✅ FIX: Clean up Unicode trong text content
        $html = $this->cleanUpUnicodeInText($html); // Thêm mới
        
        // ... rest of existing code ...
    }
    
    // ... rest of existing code ...
}
```

### Step 3: Clean Up Unicode Characters

**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Method:** `cleanUpUnicodeInText()` (mới)

**Changes:**
1. ✅ Thêm method `cleanUpUnicodeInText()` - Clean up Unicode trong text content
2. ✅ Gọi method mới trong `ensureParagraphStructure()` sau khi merge paragraph

**Code changes:**
```php
/**
 * Clean up Unicode characters trong text content
 * 
 * @param string $html
 * @return string
 */
protected function cleanUpUnicodeInText(string $html): string
{
    // ✅ FIX: Clean up Unicode replacement character trong text content
    $html = preg_replace_callback(
        '/<p[^>]*>([\s\S]*?)<\/p>/i',
        function($matches) {
            $content = $matches[1];
            
            // ✅ FIX: Clean up Unicode replacement character
            $content = preg_replace('/[\x{FFFD}]/u', '', $content);
            
            // ✅ FIX: Clean up control characters
            $content = preg_replace('/_x000[0-9a-fA-F]+_/i', '', $content);
            
            return '<p>' . $content . '</p>';
        },
        $html
    );
    
    return $html;
}
```

## ⚠️ Lưu Ý: Không Ảnh Hưởng Logic Hiện Tại

### 1. Backward Compatibility
- ✅ **Giữ nguyên logic merge paragraph ≤ 20 ký tự** - Không thay đổi
- ✅ **Giữ nguyên logic merge paragraph có superscript/subscript** - Không thay đổi
- ✅ **Giữ nguyên logic merge paragraph rỗng** - Không thay đổi
- ✅ **Chỉ thêm logic mới** - Không thay đổi logic cũ
- ✅ **Thêm method mới** - Không sửa method cũ

### 2. Testing Strategy
- ✅ **Test với template hiện tại** - Đảm bảo không break
- ✅ **Test với template mới** - Đảm bảo hoạt động đúng
- ✅ **Test với các trường hợp edge case:**
  - Paragraph có block elements (table, list, div)
  - Paragraph có nhiều superscript/subscript
  - Paragraph rỗng hoặc chỉ có whitespace
  - Paragraph chỉ có dấu chấm câu hoặc số
  - Text bị tách với nhiều pattern khác nhau
  - Unicode characters trong text content

### 3. Rollback Plan
- ✅ **Có thể rollback bằng cách comment out code mới** - Dễ dàng rollback
- ✅ **Logic cũ vẫn hoạt động bình thường** - Không ảnh hưởng

### 4. Performance
- ✅ **Không ảnh hưởng performance** - Chỉ thêm regex replace
- ✅ **Iterative approach** - Tối đa 10 iterations
- ✅ **Early exit** - Break nếu không có thay đổi

## 🎯 Kết Quả Mong Đợi

### Trước
- 43 paragraphs
- Text bị tách: `<p>1 T</p><p><sup>ê</sup></p><p>n</p>`
- Unicode characters: `ࠀ` trong "2 Tên cơ quࠀ2 Tên cơ quࠀ"

### Sau
- ~16-20 paragraphs (giảm từ 43)
- Text không bị tách: `<p>1 T<sup>ê</sup>n</p>`
- Unicode characters được clean up: "2 Tên cơ qu2 Tên cơ qu"

## 📊 Testing Plan

### Test Case 1: Paragraph Merging
- **Input:** 43 paragraphs
- **Expected:** ~16-20 paragraphs
- **Test:** Count paragraphs sau khi merge

### Test Case 2: Text Bị Tách
- **Input:** `<p>1 T</p><p><sup>ê</sup></p><p>n</p>`
- **Expected:** `<p>1 T<sup>ê</sup>n</p>`
- **Test:** Check HTML output

### Test Case 3: Text Bị Tách (Không Có Sup/Sub)
- **Input:** `<p>c</p><p>ơ</p>`
- **Expected:** `<p>cơ</p>`
- **Test:** Check HTML output

### Test Case 4: Unicode Characters
- **Input:** "2 Tên cơ quࠀ2 Tên cơ quࠀ"
- **Expected:** "2 Tên cơ qu2 Tên cơ qu"
- **Test:** Check HTML output

### Test Case 5: Block Elements
- **Input:** Paragraph có table, list, div
- **Expected:** Không merge paragraph có block elements
- **Test:** Check HTML output

## 🚀 Next Steps

1. ✅ Implement Step 1: Cải thiện paragraph merging logic
2. ✅ Implement Step 2: Cải thiện post-processing cho text bị tách
3. ✅ Implement Step 3: Clean up Unicode characters
4. ✅ Test với template hiện tại
5. ✅ Test với template mới
6. ✅ Monitor performance
7. ✅ Collect feedback

