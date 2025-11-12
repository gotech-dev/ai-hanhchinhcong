# 📋 PHƯƠNG ÁN TRIỆT ĐỂ: Fix Paragraph Merging & Text Bị Tách

## 🎯 Mục Tiêu

1. **Paragraph merging:** Giảm từ 32 paragraphs xuống ~16-20 paragraphs
2. **Text bị tách:** Fix triệt để các trường hợp text bị tách

## 🔍 Phân Tích Vấn Đề

### Vấn Đề 1: Paragraph Merging (32 → ~16-20)

**Hiện trạng:**
- Từ 36 → 32 paragraphs (giảm 11.1%)
- Mục tiêu: ~16-20 paragraphs (cần giảm thêm 12-16 paragraphs)

**Vấn đề còn lại:**
1. Vẫn còn nhiều paragraph ngắn chưa được merge:
   - `<p>1 T</p>` (3 ký tự) + `<p><sup>ê</sup></p>` (1 ký tự) + `<p>n cơ quan, tổ chức ch</p>` (21 ký tự)
   - `<p>c</p>` (1 ký tự) + `<p>ơ quan, tổ chức hoặc</p>` (20 ký tự)
   - `<p>ch</p>` (2 ký tự) + `<p>ứ c da nh nhà nướ</p>` (17 ký tự)
   - `<p>TÊN CQ, TC CHỦ QUẢN</p>` (19 ký tự) + `<p><sup>1</sup></p>` (1 ký tự)
   - `<p>TÊN CƠ QUAN, TỔ CHỨC</p>` (20 ký tự) + `<p><sup>2</sup></p>` (1 ký tự)

**Nguyên nhân:**
- Logic merge hiện tại chỉ merge nếu cả 2 đều ≤ 50 ký tự
- Logic merge paragraph có superscript/subscript chỉ merge nếu paragraph trước/sau ≤ 50 ký tự
- Logic merge paragraph ngắn với paragraph dài hơn chỉ merge nếu paragraph ngắn ≤ 5 hoặc ≤ 10 ký tự
- Không có logic merge nhiều paragraph liên tiếp (3+ paragraphs)

### Vấn Đề 2: Text Bị Tách

**Hiện trạng:**
- Vẫn còn: `<p>1 T</p><p><sup>ê</sup></p><p>n cơ quan, tổ chức ch</p>`
- Vẫn còn: `<p>c</p><p>ơ quan, tổ chức hoặc</p>`
- Vẫn còn: `<p>ch</p><p>ứ c da nh nhà nướ</p>`

**Nguyên nhân:**
- Pattern `<p>1 T</p><p><sup>ê</sup></p><p>n cơ quan, tổ chức ch</p>` không match vì:
  - Có 3 paragraph, không phải 2
  - Pattern matching hiện tại chỉ match 2 paragraph
- Pattern `<p>c</p><p>ơ quan, tổ chức hoặc</p>` không match vì:
  - Paragraph thứ 2 có 20 ký tự (vượt quá threshold 30 ký tự trong `mergeSplitTextWithoutSupSub()`)
  - Logic merge chỉ merge nếu cả 2 đều ≤ 30 ký tự
- Pattern `<p>ch</p><p>ứ c da nh nhà nướ</p>` không match vì:
  - Paragraph thứ 2 có 17 ký tự (vượt quá threshold 30 ký tự trong `mergeSplitTextWithoutSupSub()`)
  - Logic merge chỉ merge nếu cả 2 đều ≤ 30 ký tự

## 🛠️ Phương Án Triệt Để

### Giải Pháp 1: Paragraph Merging Triệt Để (32 → ~16-20)

#### 1.1. Merge Paragraph Có Superscript/Subscript Với Paragraph Trước/Sau Bất Kể Độ Dài

**Logic mới:**
- Merge paragraph chỉ có superscript/subscript với paragraph trước/sau bất kể độ dài
- Chỉ cần paragraph trước/sau > 0 ký tự (không rỗng)

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph chỉ có superscript/subscript với paragraph trước/sau bất kể độ dài
// Ví dụ: <p>TÊN CQ, TC CHỦ QUẢN</p><p><sup>1</sup></p> → <p>TÊN CQ, TC CHỦ QUẢN <sup>1</sup></p>
// Ví dụ: <p>1 T</p><p><sup>ê</sup></p><p>n cơ quan, tổ chức ch</p> → <p>1 T<sup>ê</sup>n cơ quan, tổ chức ch</p>
if (preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2) && $textLength2 === 0 && $textLength1 > 0) {
    $totalMerged++;
    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
    return '<p>' . $content1 . ' ' . $content2 . '</p>';
}
```

#### 1.2. Merge Paragraph Ngắn Với Paragraph Dài Hơn Bất Kể Độ Dài

**Logic mới:**
- Merge paragraph ngắn (≤ 10 ký tự) với paragraph dài hơn bất kể độ dài
- Chỉ cần paragraph ngắn ≤ 10 ký tự và paragraph dài hơn > 0 ký tự

**Code đề xuất:**
```php
// ✅ FIX: Merge paragraph ngắn (≤ 10 ký tự) với paragraph dài hơn bất kể độ dài
// Ví dụ: <p>c</p><p>ơ quan, tổ chức hoặc</p> → <p>cơ quan, tổ chức hoặc</p>
// Ví dụ: <p>ch</p><p>ứ c da nh nhà nướ</p> → <p>chứ c da nh nhà nướ</p>
if ($textLength1 <= 10 && $textLength2 > 10 && $textLength1 > 0 && $textLength2 > 0) {
    $hasBlock1 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p1);
    $hasBlock2 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p2);
    
    if (!$hasBlock1 && !$hasBlock2) {
        $totalMerged++;
        $merged = $content1 . $content2; // Không có space vì merge text cùng một từ
        return '<p>' . $merged . '</p>';
    }
}
```

#### 1.3. Merge Nhiều Paragraph Liên Tiếp (3+ Paragraphs)

**Logic mới:**
- Merge nhiều paragraph liên tiếp nếu chúng đều ngắn (≤ 30 ký tự)
- Merge nhiều paragraph liên tiếp nếu có paragraph chỉ có superscript/subscript

**Code đề xuất:**
```php
// ✅ FIX: Merge nhiều paragraph liên tiếp (3+ paragraphs) nếu chúng đều ngắn
// Pattern: <p>text1</p><p>text2</p><p>text3</p> → <p>text1 text2 text3</p>
// Chỉ merge nếu cả 3 đều ≤ 30 ký tự và không có block elements
$html = preg_replace_callback(
    '/(<p[^>]*>([^<]{1,30})<\/p>)\s*(<p[^>]*>([^<]{1,30})<\/p>)\s*(<p[^>]*>([^<]{1,30})<\/p>)/i',
    function($matches) {
        $p1 = $matches[1];
        $p2 = $matches[3];
        $p3 = $matches[5];
        $text1 = trim($matches[2]);
        $text2 = trim($matches[4]);
        $text3 = trim($matches[6]);
        
        // ✅ FIX: Chỉ merge nếu không có block elements
        if (!preg_match('/<table|<ul|<ol|<div|<h[1-6]/i', $p1) && 
            !preg_match('/<table|<ul|<ol|<div|<h[1-6]/i', $p2) &&
            !preg_match('/<table|<ul|<ol|<div|<h[1-6]/i', $p3)) {
            $merged = $text1 . ' ' . $text2 . ' ' . $text3;
            return '<p>' . $merged . '</p>';
        }
        
        return $p1 . "\n" . $p2 . "\n" . $p3;
    },
    $html
);
```

### Giải Pháp 2: Fix Text Bị Tách Triệt Để

#### 2.1. Merge Pattern 3 Paragraphs Với Superscript/Subscript

**Logic mới:**
- Merge pattern: `<p>text1</p><p><sup>...</sup></p><p>text2</p>` → `<p>text1<sup>...</sup>text2</p>`
- Bất kể độ dài của text1 và text2

**Code đề xuất:**
```php
/**
 * Merge pattern 3 paragraphs với superscript/subscript
 * 
 * Pattern: <p>text1</p><p><sup>...</sup></p><p>text2</p> → <p>text1<sup>...</sup>text2</p>
 * Bất kể độ dài của text1 và text2
 * 
 * @param string $html
 * @return string
 */
protected function mergeSplitTextWithSupSub3Paragraphs(string $html): string
{
    // ✅ FIX: Merge pattern: <p>text1 (bất kể độ dài)</p><p><sup>...</sup></p><p>text2 (bất kể độ dài)</p>
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]+)\s*<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)\s*(<p[^>]*>([^<]+)<\/p>)/i',
        function($matches) {
            $text1 = trim($matches[2]);
            $pSup = $matches[3];
            $text2 = trim($matches[6]);
            
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

#### 2.2. Merge Paragraph Ngắn Với Paragraph Dài Hơn Bất Kể Độ Dài (Trong Post-Processing)

**Logic mới:**
- Merge paragraph ngắn (≤ 10 ký tự) với paragraph dài hơn bất kể độ dài
- Chỉ merge nếu không có block elements

**Code đề xuất:**
```php
/**
 * Merge paragraph ngắn với paragraph dài hơn bất kể độ dài
 * 
 * Pattern: <p>c</p><p>ơ quan, tổ chức hoặc</p> → <p>cơ quan, tổ chức hoặc</p>
 * 
 * @param string $html
 * @return string
 */
protected function mergeShortWithLongParagraph(string $html): string
{
    // ✅ FIX: Merge paragraph ngắn (≤ 10 ký tự) với paragraph dài hơn bất kể độ dài
    $html = preg_replace_callback(
        '/(<p[^>]*>([^<]{1,10})<\/p>)\s*(<p[^>]*>([^<]+)<\/p>)/i',
        function($matches) {
            $p1 = $matches[1];
            $p2 = $matches[3];
            $text1 = trim($matches[2]);
            $text2 = trim($matches[4]);
            
            // ✅ FIX: Chỉ merge nếu không có block elements và không có sup/sub
            if (!preg_match('/<table|<ul|<ol|<div|<h[1-6]/i', $p1) && 
                !preg_match('/<table|<ul|<ol|<div|<h[1-6]/i', $p2) &&
                !preg_match('/<sup|<sub/i', $p1) &&
                !preg_match('/<sup|<sub/i', $p2)) {
                // ✅ FIX: Merge không có space vì merge text cùng một từ
                $merged = $text1 . $text2;
                return '<p>' . $merged . '</p>';
            }
            
            return $p1 . "\n" . $p2;
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
1. ✅ Merge paragraph có superscript/subscript với paragraph trước/sau bất kể độ dài
2. ✅ Merge paragraph ngắn (≤ 10 ký tự) với paragraph dài hơn bất kể độ dài
3. ✅ Merge nhiều paragraph liên tiếp (3+ paragraphs) nếu chúng đều ngắn

### Step 2: Cải Thiện Post-Processing Cho Text Bị Tách

**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Methods:** `ensureParagraphStructure()`

**Changes:**
1. ✅ Thêm method `mergeSplitTextWithSupSub3Paragraphs()` - Merge pattern 3 paragraphs với superscript/subscript
2. ✅ Thêm method `mergeShortWithLongParagraph()` - Merge paragraph ngắn với paragraph dài hơn bất kể độ dài
3. ✅ Gọi các method mới trong `ensureParagraphStructure()`

## ⚠️ Lưu Ý: Không Ảnh Hưởng Logic Hiện Tại

### 1. Backward Compatibility
- ✅ **Giữ nguyên tất cả logic merge hiện tại** - Không thay đổi
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
- 32 paragraphs
- Text bị tách: `<p>1 T</p><p><sup>ê</sup></p><p>n cơ quan, tổ chức ch</p>`
- Text bị tách: `<p>c</p><p>ơ quan, tổ chức hoặc</p>`
- Text bị tách: `<p>ch</p><p>ứ c da nh nhà nướ</p>`

### Sau
- ~16-20 paragraphs (giảm từ 32)
- Text không bị tách: `<p>1 T<sup>ê</sup>n cơ quan, tổ chức ch</p>`
- Text không bị tách: `<p>cơ quan, tổ chức hoặc</p>`
- Text không bị tách: `<p>chứ c da nh nhà nướ</p>`

## 📊 Testing Plan

### Test Case 1: Paragraph Merging
- **Input:** 32 paragraphs
- **Expected:** ~16-20 paragraphs
- **Test:** Count paragraphs sau khi merge

### Test Case 2: Text Bị Tách (3 Paragraphs)
- **Input:** `<p>1 T</p><p><sup>ê</sup></p><p>n cơ quan, tổ chức ch</p>`
- **Expected:** `<p>1 T<sup>ê</sup>n cơ quan, tổ chức ch</p>`
- **Test:** Check HTML output

### Test Case 3: Text Bị Tách (2 Paragraphs - Ngắn + Dài)
- **Input:** `<p>c</p><p>ơ quan, tổ chức hoặc</p>`
- **Expected:** `<p>cơ quan, tổ chức hoặc</p>`
- **Test:** Check HTML output

### Test Case 4: Text Bị Tách (2 Paragraphs - Ngắn + Dài)
- **Input:** `<p>ch</p><p>ứ c da nh nhà nướ</p>`
- **Expected:** `<p>chứ c da nh nhà nướ</p>`
- **Test:** Check HTML output

### Test Case 5: Paragraph Có Superscript/Subscript
- **Input:** `<p>TÊN CQ, TC CHỦ QUẢN</p><p><sup>1</sup></p>`
- **Expected:** `<p>TÊN CQ, TC CHỦ QUẢN <sup>1</sup></p>`
- **Test:** Check HTML output

## 🚀 Next Steps

1. ✅ Implement Step 1: Cải thiện paragraph merging logic
2. ✅ Implement Step 2: Cải thiện post-processing cho text bị tách
3. ✅ Test với template hiện tại
4. ✅ Test với template mới
5. ✅ Monitor performance
6. ✅ Collect feedback



