# 🔧 BÁO CÁO: Sửa Lỗi Hiển Thị Template DOCX

## 📋 Tổng Quan Vấn Đề

### 1. Chữ Tiếng Việt Không Đúng
- **Hiện tượng:** 
  - Có ký tự lạ: `_x0007_`, `ࠀ` (Unicode replacement character)
  - Văn bản bị cắt: "1 T <sup>ê</sup> n cơ quan, tổ chức ch <sup>ủ</sup> q 1 Tê n c ơ qu an, tổ"
  - Thiếu khoảng trắng: "CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2"
  - Chữ bị tách: "T <sup>ê</sup> n" thay vì "Tên"

### 2. Format Hiển Thị Không Giống Template Mẫu
- **Hiện tượng:**
  - CSS từ Pandoc bị xóa hoàn toàn (style tag bị remove)
  - Font, spacing, alignment không giống template gốc
  - Thiếu styling cho superscript, subscript
  - Paragraph spacing không đúng

## 🔍 Nguyên Nhân

### Backend (PandocDocxToHtmlConverter.php)

#### 1. Pandoc Command Options
**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Dòng:** 148-171

**Vấn đề:**
- Thiếu option `--extract-media` đúng cách
- Không có option để preserve Vietnamese characters
- Thiếu encoding options cho UTF-8

**Code hiện tại:**
```php
$options = [
    '--standalone',
    '--embed-resources',
    '--self-contained',
    '--metadata title="Document Preview"',
    '--css=' . $this->getInlineCss(),
    '--wrap=preserve',
    '--preserve-tabs',
    '--extract-media=./',
];
```

#### 2. Paragraph Merging Logic
**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Dòng:** ~450-550

**Vấn đề:**
- `mergeShortParagraphs()` đang merge các paragraph ngắn (< 50 ký tự)
- Logic merge có thể làm mất format và spacing
- Không preserve inline formatting khi merge

#### 3. HTML Enhancement
**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Dòng:** 339-450

**Vấn đề:**
- Xóa `<style>` tag hoàn toàn, mất CSS từ Pandoc
- Không preserve inline styles từ DOCX
- Không giữ lại font-family, font-size từ template

### Frontend (DocumentPreview.vue)

#### 1. Style Tag Removal
**File:** `resources/js/Components/DocumentPreview.vue`
**Dòng:** 147-151

**Vấn đề:**
- Xóa `<style>` tag hoàn toàn để tránh CSS conflict
- Nhưng mất hết CSS từ Pandoc (font, spacing, alignment)
- CSS frontend không đủ để thay thế

**Code hiện tại:**
```javascript
cleanedHtml = cleanedHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
```

#### 2. CSS Styling
**File:** `resources/js/Components/DocumentPreview.vue`
**Dòng:** 299-384

**Vấn đề:**
- CSS frontend quá đơn giản, không cover hết format từ template
- Thiếu styling cho superscript, subscript
- Thiếu font-family, font-size từ template gốc
- Thiếu paragraph spacing, line-height chính xác

**Code hiện tại:**
```css
.docx-preview {
    font-family: 'Times New Roman', serif;
    line-height: 1.6;
    color: #333;
    /* ... */
}
```

## 🛠️ Giải Pháp

### Backend Fixes

#### 1. Cải Thiện Pandoc Command
**File:** `app/Services/PandocDocxToHtmlConverter.php`

**Thay đổi:**
```php
$options = [
    '--standalone',
    '--embed-resources',
    '--self-contained',
    '--metadata title="Document Preview"',
    '--css=' . $this->getInlineCss(),
    '--wrap=preserve',
    '--preserve-tabs',
    '--extract-media=./',
    // ✅ FIX: Thêm options cho Vietnamese
    '--from=docx',
    '--to=html5',
    '--standalone',
    '--wrap=none', // Không wrap để preserve format
    '--no-highlight', // Tắt syntax highlighting
];
```

#### 2. Fix Paragraph Merging
**File:** `app/Services/PandocDocxToHtmlConverter.php`

**Thay đổi:**
- Chỉ merge paragraph thực sự rỗng hoặc chỉ có whitespace
- Preserve inline formatting khi merge
- Không merge nếu có superscript/subscript

**Code đề xuất:**
```php
protected function mergeShortParagraphs(string $html): string
{
    // ✅ FIX: Chỉ merge paragraph rỗng hoặc chỉ có whitespace
    // Không merge nếu có superscript/subscript
    $html = preg_replace_callback(
        '/(<p[^>]*>[\s\S]*?<\/p>)\s*(<p[^>]*>[\s\S]*?<\/p>)/i',
        function($matches) {
            $p1 = $matches[1];
            $p2 = $matches[2];
            
            // ✅ FIX: Không merge nếu có superscript/subscript
            if (preg_match('/<sup|<sub/i', $p1) || preg_match('/<sup|<sub/i', $p2)) {
                return $p1 . "\n" . $p2;
            }
            
            $text1 = strip_tags($p1);
            $text2 = strip_tags($p2);
            
            // ✅ FIX: Chỉ merge nếu cả 2 đều rỗng hoặc chỉ có whitespace
            if (trim($text1) === '' && trim($text2) === '') {
                return $p1; // Bỏ p2
            }
            
            // ✅ FIX: Không merge nếu có nội dung thực sự
            if (strlen(trim($text1)) > 0 && strlen(trim($text2)) > 0) {
                return $p1 . "\n" . $p2;
            }
            
            return $p1 . "\n" . $p2;
        },
        $html
    );
    
    return $html;
}
```

#### 3. Preserve CSS từ Pandoc
**File:** `app/Services/PandocDocxToHtmlConverter.php`

**Thay đổi:**
- Không xóa `<style>` tag hoàn toàn
- Extract CSS từ Pandoc và merge với custom CSS
- Preserve inline styles từ DOCX

**Code đề xuất:**
```php
protected function enhanceHtml(string $html): string
{
    // ✅ FIX: Extract và preserve CSS từ Pandoc
    $pandocStyles = '';
    if (preg_match_all('/<style[^>]*>([\s\S]*?)<\/style>/i', $html, $styleMatches)) {
        foreach ($styleMatches[1] as $styleContent) {
            $pandocStyles .= $styleContent . "\n";
        }
    }
    
    // ✅ FIX: Merge Pandoc CSS với custom CSS
    $mergedStyles = $pandocStyles . "\n" . $this->getLineBreakFixCss();
    
    // Extract body content
    if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $bodyMatches)) {
        $bodyContent = $bodyMatches[1];
        
        // ✅ FIX: Remove header nhưng giữ lại body content
        $bodyContent = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $bodyContent);
        
        // ✅ FIX: Post-process để preserve format
        $bodyContent = $this->ensureParagraphStructure($bodyContent);
        
        // ✅ FIX: Return với merged styles
        return '<style>' . $mergedStyles . '</style>' . $bodyContent;
    }
    
    return $html;
}
```

### Frontend Fixes

#### 1. Preserve CSS từ Backend
**File:** `resources/js/Components/DocumentPreview.vue`

**Thay đổi:**
- Không xóa `<style>` tag hoàn toàn
- Extract CSS và apply vào component
- Override chỉ những CSS conflict

**Code đề xuất:**
```javascript
// ✅ FIX: Extract CSS từ HTML và apply riêng
const styleMatch = html.match(/<style[^>]*>([\s\S]*?)<\/style>/i);
if (styleMatch) {
    const styleContent = styleMatch[1];
    // ✅ FIX: Apply CSS vào component thay vì xóa
    const styleElement = document.createElement('style');
    styleElement.textContent = styleContent;
    styleElement.id = 'pandoc-styles';
    // ✅ FIX: Remove old style nếu có
    const oldStyle = document.getElementById('pandoc-styles');
    if (oldStyle) {
        oldStyle.remove();
    }
    document.head.appendChild(styleElement);
}

// ✅ FIX: Remove style tag từ HTML nhưng đã apply CSS rồi
cleanedHtml = cleanedHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
```

#### 2. Cải Thiện CSS Styling
**File:** `resources/js/Components/DocumentPreview.vue`

**Thay đổi:**
- Thêm CSS cho superscript, subscript
- Preserve font-family, font-size từ template
- Cải thiện paragraph spacing, line-height

**Code đề xuất:**
```css
/* ✅ FIX: Preserve superscript/subscript formatting */
.docx-preview :deep(sup) {
    font-size: 0.7em;
    vertical-align: super;
    line-height: 0;
}

.docx-preview :deep(sub) {
    font-size: 0.7em;
    vertical-align: sub;
    line-height: 0;
}

/* ✅ FIX: Preserve paragraph spacing từ template */
.docx-preview :deep(p) {
    margin: 0.5em 0;
    text-align: justify;
    font-family: 'Times New Roman', serif;
    font-size: 13pt;
    line-height: 1.5;
}

/* ✅ FIX: Preserve font từ template */
.docx-preview :deep(*) {
    font-family: 'Times New Roman', serif;
}
```

## 📝 Checklist Sửa Lỗi

### Backend
- [ ] Thêm Pandoc options cho Vietnamese encoding
- [ ] Fix paragraph merging logic (không merge nếu có superscript/subscript)
- [ ] Preserve CSS từ Pandoc thay vì xóa hoàn toàn
- [ ] Preserve inline styles từ DOCX
- [ ] Fix Unicode character handling

### Frontend
- [ ] Extract và apply CSS từ backend thay vì xóa
- [ ] Cải thiện CSS styling cho superscript/subscript
- [ ] Preserve font-family, font-size từ template
- [ ] Cải thiện paragraph spacing, line-height
- [ ] Override chỉ những CSS conflict, không xóa hết

## 🎯 Kết Quả Mong Đợi

1. **Chữ Tiếng Việt Đúng:**
   - Không còn ký tự lạ (`_x0007_`, `ࠀ`)
   - Không còn chữ bị cắt hoặc tách
   - Khoảng trắng đúng

2. **Format Giống Template:**
   - Font, spacing, alignment giống template gốc
   - Superscript/subscript hiển thị đúng
   - Paragraph spacing đúng
   - CSS từ Pandoc được preserve

## 📌 Lưu Ý

- Test kỹ với nhiều template DOCX khác nhau
- Đảm bảo không làm mất format khi merge paragraph
- Preserve tất cả CSS từ Pandoc, chỉ override conflict
- Test với các ký tự đặc biệt trong tiếng Việt

## 🔬 Phân Tích Chi Tiết

### Vấn Đề 1: Ký Tự Lạ `_x0007_` và `ࠀ`

**Nguyên nhân:**
- Pandoc không xử lý đúng các control characters trong DOCX
- Unicode replacement character (`ࠀ`) xuất hiện khi encoding không đúng
- Thiếu option `--from=docx+raw_html` để preserve raw HTML

**Giải pháp:**
```php
// Thêm vào buildPandocCommand()
'--from=docx+raw_html',  // Preserve raw HTML và control characters
'--to=html5+raw_html',  // Output HTML5 với raw HTML
```

### Vấn Đề 2: Chữ Bị Cắt "T <sup>ê</sup> n"

**Nguyên nhân:**
- `mergeShortParagraphs()` đang merge paragraph có superscript/subscript
- Logic merge không preserve inline formatting
- Pandoc tách chữ thành nhiều `<p>` tags nhỏ

**Giải pháp:**
- Không merge nếu có `<sup>` hoặc `<sub>`
- Preserve inline formatting khi merge
- Chỉ merge paragraph thực sự rỗng

### Vấn Đề 3: Format Không Giống Template

**Nguyên nhân:**
- CSS từ Pandoc bị xóa hoàn toàn ở frontend
- CSS frontend quá đơn giản, không cover hết format
- Thiếu font-family, font-size, spacing từ template

**Giải pháp:**
- Extract và apply CSS từ Pandoc vào `<head>`
- Override chỉ những CSS conflict (max-width, padding)
- Preserve tất cả CSS khác từ Pandoc

## 📊 So Sánh Code Hiện Tại vs Code Đề Xuất

### Backend: mergeShortParagraphs()

**Code hiện tại (Dòng 651-730):**
```php
// Merge nếu cả 2 đều < 50 ký tự
if (($textLength1 <= 50 || $textLength1 === 0) && ($textLength2 <= 50 || $textLength2 === 0)) {
    $totalMerged++;
    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
    return '<p>' . $merged . '</p>';
}
```

**Vấn đề:**
- Merge cả paragraph có superscript/subscript → làm mất format
- Merge paragraph có nội dung thực sự → làm mất spacing
- Không preserve inline formatting khi merge

**Code đề xuất:**
```php
// ✅ FIX: Không merge nếu có superscript/subscript
if (preg_match('/<sup|<sub/i', $p1) || preg_match('/<sup|<sub/i', $p2)) {
    return $p1 . "\n" . $p2;
}

// ✅ FIX: Chỉ merge nếu cả 2 đều rỗng hoặc chỉ có whitespace
if (trim($text1) === '' && trim($text2) === '') {
    return $p1; // Bỏ p2
}

// ✅ FIX: Không merge nếu có nội dung thực sự
if (strlen(trim($text1)) > 0 && strlen(trim($text2)) > 0) {
    return $p1 . "\n" . $p2;
}
```

### Frontend: Style Tag Removal

**Code hiện tại (Dòng 147-151):**
```javascript
// ✅ FIX: Remove style tags using regex (preserve <p> tag structure)
cleanedHtml = cleanedHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
```

**Vấn đề:**
- Xóa hoàn toàn CSS từ Pandoc → mất font, spacing, alignment
- CSS frontend không đủ để thay thế

**Code đề xuất:**
```javascript
// ✅ FIX: Extract CSS từ HTML và apply riêng
const styleMatch = html.match(/<style[^>]*>([\s\S]*?)<\/style>/i);
if (styleMatch) {
    const styleContent = styleMatch[1];
    // ✅ FIX: Apply CSS vào component thay vì xóa
    const styleElement = document.createElement('style');
    styleElement.textContent = styleContent;
    styleElement.id = 'pandoc-styles';
    // ✅ FIX: Remove old style nếu có
    const oldStyle = document.getElementById('pandoc-styles');
    if (oldStyle) {
        oldStyle.remove();
    }
    document.head.appendChild(styleElement);
}

// ✅ FIX: Remove style tag từ HTML nhưng đã apply CSS rồi
cleanedHtml = cleanedHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
```

## 🎯 Ưu Tiên Sửa Lỗi

### Priority 1: Chữ Tiếng Việt Không Đúng
1. Fix paragraph merging logic (không merge nếu có superscript/subscript)
2. Thêm Pandoc options cho Vietnamese encoding
3. Fix Unicode character handling

### Priority 2: Format Không Giống Template
1. Preserve CSS từ Pandoc (extract và apply vào `<head>`)
2. Cải thiện CSS styling cho superscript/subscript
3. Preserve font-family, font-size từ template

