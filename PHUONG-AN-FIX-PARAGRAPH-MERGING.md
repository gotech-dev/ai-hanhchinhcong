# 📋 PHƯƠNG ÁN CHI TIẾT: Fix Paragraph Merging & Text Bị Tách

## 🎯 Mục Tiêu

1. **Giảm số paragraph nhỏ** - Từ 79 paragraphs xuống còn ~16-20 paragraphs
2. **Fix text bị tách** - "T<sup>ê</sup>n" → "Tên" (giữ superscript nhưng không tách chữ)

## 🔍 Phân Tích Vấn Đề Hiện Tại

### Vấn Đề 1: Paragraph Merging Logic

**Hiện trạng:**
- Pandoc tách text thành nhiều `<p>` tags nhỏ (79 paragraphs)
- Logic merge hiện tại:
  - ✅ Không merge nếu có `<sup>` hoặc `<sub>` → **Đúng nhưng quá strict**
  - ✅ Chỉ merge nếu cả 2 đều rỗng → **Đúng**
  - ✅ Không merge nếu có nội dung thực sự → **Đúng nhưng quá strict**
  - ⚠️ Chỉ merge nếu một trong hai rỗng và một cái rất ngắn (< 10 ký tự) → **Quá strict**

**Vấn đề:**
- Logic merge quá strict → không merge được nhiều paragraph nhỏ
- Pandoc tách text thành: `<p>T</p><p><sup>ê</sup></p><p>n</p>` → không merge được vì có `<sup>`

### Vấn Đề 2: Text Bị Tách Khi Có Superscript/Subscript

**Hiện trạng:**
- Pandoc tách text thành: `<p>T</p><p><sup>ê</sup></p><p>n</p>`
- Logic merge hiện tại: Không merge nếu có `<sup>` hoặc `<sub>` → **Quá strict**

**Vấn đề:**
- Text bị tách: "T<sup>ê</sup>n" → `<p>T</p><p><sup>ê</sup></p><p>n</p>`
- Cần merge thành: `<p>T<sup>ê</sup>n</p>`

## 🛠️ Phương Án Chi Tiết

### Giải Pháp 1: Cải Thiện Paragraph Merging Logic

**Vấn đề hiện tại:**
- Log cho thấy: `totalMerged: 0, finalPTagCount: 79` → Logic merge quá strict, không merge được gì
- Logic hiện tại: Không merge nếu có `<sup>` hoặc `<sub>` → **Quá strict**

#### 1.1. Merge Paragraph Có Superscript/Subscript (Nếu Cùng Một Từ)

**Logic mới:**
- Nếu paragraph trước có text (1-3 ký tự) và paragraph sau chỉ có `<sup>` hoặc `<sub>` → merge
- Nếu paragraph trước chỉ có `<sup>` hoặc `<sub>` và paragraph sau có text (1-3 ký tự) → merge
- Nếu cả 2 đều chỉ có `<sup>` hoặc `<sub>` → merge

**Ví dụ:**
```html
<!-- Trước -->
<p>T</p>
<p><sup>ê</sup></p>
<p>n</p>

<!-- Sau -->
<p>T<sup>ê</sup>n</p>
```

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph có superscript/subscript nếu cùng một từ
if (preg_match('/<sup|<sub/i', $p1) || preg_match('/<sup|<sub/i', $p2)) {
    // Extract text content (strip HTML tags)
    $text1 = strip_tags($p1);
    $text2 = strip_tags($p2);
    $textLength1 = strlen(trim($text1));
    $textLength2 = strlen(trim($text2));
    
    // ✅ FIX: Merge nếu p1 chỉ có text (1-3 ký tự) và p2 chỉ có sup/sub
    if ($textLength1 > 0 && $textLength1 <= 3 && $textLength2 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2)) {
        $totalMerged++;
        $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
        $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
        return '<p>' . $content1 . $content2 . '</p>';
    }
    
    // ✅ FIX: Merge nếu p1 chỉ có sup/sub và p2 chỉ có text (1-3 ký tự)
    if ($textLength1 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p1) && $textLength2 > 0 && $textLength2 <= 3) {
        $totalMerged++;
        $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
        $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
        return '<p>' . $content1 . $content2 . '</p>';
    }
    
    // ✅ FIX: Merge nếu cả 2 đều chỉ có superscript/subscript
    if ($textLength1 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p1) && 
        $textLength2 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2)) {
        $totalMerged++;
        $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
        $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
        return '<p>' . $content1 . ' ' . $content2 . '</p>';
    }
    
    // Otherwise, keep as is
    return $p1 . "\n" . $p2;
}
```

#### 1.2. Merge Paragraph Ngắn (< 20 ký tự) Nếu Không Có Block Elements

**Logic mới:**
- Merge paragraph ngắn (< 20 ký tự) nếu không có block elements (table, list, div, heading)
- Không merge nếu có block elements
- **Quan trọng:** Chỉ merge nếu cả 2 đều ngắn, không merge nếu một trong hai dài

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph ngắn (< 20 ký tự) nếu không có block elements
// Chỉ merge nếu cả 2 đều ngắn và không có block elements
if ($textLength1 <= 20 && $textLength2 <= 20 && $textLength1 > 0 && $textLength2 > 0) {
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

#### 1.3. Merge Paragraph Rỗng Hoặc Chỉ Có Whitespace

**Logic mới:**
- Merge paragraph rỗng hoặc chỉ có whitespace → **Đã có, giữ nguyên**
- Merge paragraph chỉ có dấu chấm câu (`.`, `,`, `;`, `:`, `!`, `?`) → **Thêm mới**

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph rỗng hoặc chỉ có whitespace (giữ nguyên logic cũ)
if (trim($text1) === '' && trim($text2) === '') {
    $totalMerged++;
    return $p1; // Bỏ p2
}

// ✅ FIX: Merge paragraph chỉ có dấu chấm câu (thêm mới)
if (preg_match('/^[.,;:!?\s]+$/', $text1) || preg_match('/^[.,;:!?\s]+$/', $text2)) {
    $totalMerged++;
    $merged = $content1 . ($content1 && $content2 ? '' : '') . $content2;
    return '<p>' . $merged . '</p>';
}
```

### Giải Pháp 2: Fix Text Bị Tách Khi Có Superscript/Subscript

#### 2.1. Post-Process HTML Để Merge Text Cùng Một Từ

**Logic mới:**
- Sau khi merge paragraph, post-process HTML để merge text cùng một từ
- Pattern: `<p>T</p><p><sup>ê</sup></p><p>n</p>` → `<p>T<sup>ê</sup>n</p>`
- **Quan trọng:** Chỉ merge nếu cả 3 paragraph đều rất ngắn (< 3 ký tự)

**Code đề xuất:**
```php
/**
 * Post-process HTML to merge text split by superscript/subscript
 * 
 * Pattern: <p>char</p><p><sup>...</sup></p><p>char</p> → <p>char<sup>...</sup>char</p>
 * 
 * @param string $html
 * @return string
 */
protected function mergeSplitTextWithSupSub(string $html): string
{
    // ✅ FIX: Merge pattern: <p>char</p><p><sup>...</sup></p><p>char</p>
    // Chỉ merge nếu cả 3 đều rất ngắn (< 3 ký tự)
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]{1,2})<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)\s*(<p[^>]*>([^<]{1,2})<\/p>)/i',
        function($matches) {
            $text1 = $matches[2];
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

#### 2.2. Merge Text Cùng Một Từ (Pattern 2)

**Logic mới:**
- Pattern khác: `<p>TÊN CQ</p><p><sup>1</sup></p>` → `<p>TÊN CQ <sup>1</sup></p>`
- Merge nếu paragraph trước có text và paragraph sau chỉ có sup/sub

**Code đề xuất:**
```php
/**
 * Merge text with superscript/subscript (Pattern 2)
 * 
 * Pattern: <p>text</p><p><sup>...</sup></p> → <p>text <sup>...</sup></p>
 * 
 * @param string $html
 * @return string
 */
protected function mergeTextWithSupSubPattern2(string $html): string
{
    // ✅ FIX: Merge pattern: <p>text</p><p><sup>...</sup></p>
    // Chỉ merge nếu p1 có text và p2 chỉ có sup/sub
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]+)<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)/i',
        function($matches) {
            $p1 = $matches[1];
            $p2 = $matches[3];
            
            // Extract content
            $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
            $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
            
            // ✅ FIX: Merge với space
            $merged = $content1 . ' ' . $content2;
            return '<p>' . $merged . '</p>';
        },
        $html
    );
    
    return $html;
}
```

## 📝 Implementation Plan

### Step 1: Cải Thiện Paragraph Merging Logic

**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Method:** `mergeShortParagraphs()`

**Changes:**
1. ✅ Thêm logic merge paragraph có superscript/subscript nếu cùng một từ
2. ✅ Thêm logic merge paragraph ngắn (< 20 ký tự) nếu không có block elements
3. ✅ Thêm logic merge paragraph chỉ có dấu chấm câu

**Code changes:**
```php
protected function mergeShortParagraphs(string $html): string
{
    // ... existing code (protect block elements) ...
    
    while ($iteration < $maxIterations) {
        $originalHtml = $html;
        
        $html = preg_replace_callback('/(<p[^>]*>[\s\S]*?<\/p>)\s*(<p[^>]*>[\s\S]*?<\/p>)/i', function($matches) use (&$totalMerged) {
            $p1 = $matches[1];
            $p2 = $matches[2];
            
            // Extract content
            preg_match('/<p[^>]*>([\s\S]*?)<\/p>/i', $p1, $m1);
            preg_match('/<p[^>]*>([\s\S]*?)<\/p>/i', $p2, $m2);
            
            $content1 = isset($m1[1]) ? trim($m1[1]) : '';
            $content2 = isset($m2[1]) ? trim($m2[1]) : '';
            
            $text1 = strip_tags($content1);
            $text2 = strip_tags($content2);
            $textLength1 = strlen(trim($text1));
            $textLength2 = strlen(trim($text2));
            
            // ✅ FIX 1: Merge paragraph có superscript/subscript nếu cùng một từ
            if (preg_match('/<sup|<sub/i', $p1) || preg_match('/<sup|<sub/i', $p2)) {
                // ... new logic (xem code đề xuất ở trên) ...
            }
            
            // ✅ FIX 2: Merge paragraph ngắn (< 20 ký tự) nếu không có block elements
            if ($textLength1 <= 20 && $textLength2 <= 20 && $textLength1 > 0 && $textLength2 > 0) {
                // ... new logic (xem code đề xuất ở trên) ...
            }
            
            // ✅ FIX 3: Merge paragraph rỗng hoặc chỉ có dấu chấm câu
            if (trim($text1) === '' && trim($text2) === '') {
                $totalMerged++;
                return $p1; // Bỏ p2 (giữ nguyên logic cũ)
            }
            
            if (preg_match('/^[.,;:!?\s]+$/', $text1) || preg_match('/^[.,;:!?\s]+$/', $text2)) {
                $totalMerged++;
                $merged = $content1 . ($content1 && $content2 ? '' : '') . $content2;
                return '<p>' . $merged . '</p>';
            }
            
            // ✅ FIX 4: Chỉ merge nếu một trong hai rỗng và một cái rất ngắn (< 10 ký tự)
            // Giữ nguyên logic cũ
            if (($textLength1 === 0 && $textLength2 <= 10) || ($textLength2 === 0 && $textLength1 <= 10)) {
                $totalMerged++;
                $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                return '<p>' . $merged . '</p>';
            }
            
            // Otherwise, keep as is
            return $p1 . "\n" . $p2;
        }, $html);
        
        // ... rest of existing code ...
    }
    
    return $html;
}
```

### Step 2: Post-Process HTML Để Merge Text Cùng Một Từ

**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Method:** `ensureParagraphStructure()`

**Changes:**
1. ✅ Thêm method `mergeSplitTextWithSupSub()` để post-process HTML
2. ✅ Gọi method này sau khi merge paragraph

**Code changes:**
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
        
        $newPTagCount = substr_count($html, '<p');
        Log::info('🔵 [PandocDocxToHtmlConverter] After merging', [
            'newPTagCount' => $newPTagCount,
            'htmlLength' => strlen($html),
        ]);
        return $html;
    }
    
    // ... rest of existing code (split content if needed) ...
}
```

## ⚠️ Lưu Ý: Không Ảnh Hưởng Đến Logic Hiện Tại

### 1. Backward Compatibility
- ✅ **Giữ nguyên logic merge paragraph rỗng** - Không thay đổi
- ✅ **Giữ nguyên logic merge paragraph ngắn (< 10 ký tự)** - Không thay đổi
- ✅ **Chỉ thêm logic mới** - Không thay đổi logic cũ
- ✅ **Thêm method mới** - Không sửa method cũ

### 2. Testing Strategy
- ✅ **Test với template hiện tại** - Đảm bảo không break
- ✅ **Test với template mới** - Đảm bảo hoạt động đúng
- ✅ **Test với các trường hợp edge case:**
  - Paragraph có block elements (table, list, div)
  - Paragraph có nhiều superscript/subscript
  - Paragraph rỗng hoặc chỉ có whitespace
  - Paragraph chỉ có dấu chấm câu

### 3. Rollback Plan
- ✅ **Có thể rollback bằng cách comment out code mới** - Dễ dàng rollback
- ✅ **Logic cũ vẫn hoạt động bình thường** - Không ảnh hưởng

### 4. Performance
- ✅ **Không ảnh hưởng performance** - Chỉ thêm regex replace
- ✅ **Iterative approach** - Tối đa 10 iterations
- ✅ **Early exit** - Break nếu không có thay đổi

## 🎯 Kết Quả Mong Đợi

### Trước
- 79 paragraphs
- Text bị tách: `<p>T</p><p><sup>ê</sup></p><p>n</p>`

### Sau
- ~16-20 paragraphs
- Text không bị tách: `<p>T<sup>ê</sup>n</p>`

## 📊 Testing Plan

### Test Case 1: Paragraph Merging
- **Input:** 79 paragraphs
- **Expected:** ~16-20 paragraphs
- **Test:** Count paragraphs sau khi merge

### Test Case 2: Text Bị Tách
- **Input:** `<p>T</p><p><sup>ê</sup></p><p>n</p>`
- **Expected:** `<p>T<sup>ê</sup>n</p>`
- **Test:** Check HTML output

### Test Case 3: Superscript/Subscript
- **Input:** `<p>TÊN CQ, TC CHỦ QUẢN</p><p><sup>1</sup></p>`
- **Expected:** `<p>TÊN CQ, TC CHỦ QUẢN <sup>1</sup></p>`
- **Test:** Check HTML output

### Test Case 4: Block Elements
- **Input:** Paragraph có table, list, div
- **Expected:** Không merge paragraph có block elements
- **Test:** Check HTML output

## 🚀 Next Steps

1. ✅ Implement Step 1: Cải thiện paragraph merging logic
2. ✅ Implement Step 2: Post-process HTML để merge text cùng một từ
3. ✅ Test với template hiện tại
4. ✅ Test với template mới
5. ✅ Monitor performance
6. ✅ Collect feedback

