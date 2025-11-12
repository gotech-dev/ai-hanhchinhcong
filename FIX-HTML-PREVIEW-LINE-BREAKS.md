# Fix HTML Preview Line Breaks Issue

## 🐛 Vấn Đề

**User Report:** "UI vỡ hết. Không có xuống dòng gì cả."

**Symptoms:**
- Tất cả text bị concatenate thành một dòng dài
- Không có line breaks giữa các phần (header, body, footer)
- Các paragraph không được tách riêng
- Document preview hiển thị như một block text dài không có format

**Example:**
```
DOCX Template:
  CÔNG TY TNHH ABC
  (line break)
  Tên cơ quan

HTML Preview:
  CÔNG TY TNHH ABCTên cơ quan (no line break!)
```

---

## 🔍 Root Cause Analysis

### 1. Pandoc Command Options
**Current Command:**
```php
$options = [
    '--standalone',
    '--embed-resources',
    '--self-contained',
    '--metadata title="Document Preview"',
    '--css=' . $this->getInlineCss(),
];
```

**Problem:** Pandoc mặc định có thể wrap text hoặc không preserve line breaks đúng cách.

### 2. HTML Output Structure
**Issue:** Pandoc có thể generate HTML không có proper `<p>` tags hoặc line breaks.

### 3. Frontend Rendering
**Current Code:**
```vue
<div v-html="docxPreviewHtml"></div>
```

**Problem:** CSS có thể không preserve whitespace hoặc line breaks.

---

## 🎯 Giải Pháp

### Approach 1: Fix Pandoc Command Options ✅ RECOMMENDED

**Add options to preserve line breaks:**
```php
$options = [
    '--standalone',
    '--embed-resources',
    '--self-contained',
    '--metadata title="Document Preview"',
    '--css=' . $this->getInlineCss(),
    '--wrap=none',              // ✅ Don't wrap lines
    '--preserve-tabs',          // ✅ Preserve tabs
    '--markdown-headings=atx',  // ✅ Use ATX headings
];
```

**Or use:**
```php
$options = [
    '--standalone',
    '--embed-resources',
    '--self-contained',
    '--metadata title="Document Preview"',
    '--css=' . $this->getInlineCss(),
    '--wrap=preserve',          // ✅ Preserve line breaks
];
```

### Approach 2: Post-Process HTML ✅ RECOMMENDED

**Add post-processing to ensure proper line breaks:**
```php
protected function enhanceHtml(string $html): string
{
    // ... existing code ...
    
    // ✅ FIX: Ensure proper line breaks
    // Replace multiple spaces with single space (except in pre/code)
    $html = preg_replace('/(?<!<pre>)(?<!<code>)\s+/', ' ', $html);
    
    // Add <br> after headings if next element is not a block element
    $html = preg_replace('/(<\/h[1-6]>)\s*(?!<[p|div|ul|ol|table|h])/i', '$1<br>', $html);
    
    // Ensure paragraphs have proper spacing
    $html = preg_replace('/(<\/p>)\s*(<p[^>]*>)/i', '$1<br>$2', $html);
    
    // Add CSS to preserve whitespace
    $html = str_replace(
        '</head>',
        '<style>
            body { white-space: pre-wrap; }
            p { white-space: normal; margin: 0.5em 0; }
            h1, h2, h3, h4, h5, h6 { white-space: normal; margin: 1em 0 0.5em 0; }
        </style></head>',
        $html
    );
    
    return $html;
}
```

### Approach 3: Fix Frontend CSS ✅ RECOMMENDED

**Add CSS to preserve line breaks:**
```vue
<style scoped>
.docx-preview {
    white-space: pre-wrap;  /* ✅ Preserve line breaks */
    word-wrap: break-word;
}

.docx-preview p {
    white-space: normal;    /* ✅ Normal wrapping for paragraphs */
    margin: 0.5em 0;
}

.docx-preview h1,
.docx-preview h2,
.docx-preview h3,
.docx-preview h4,
.docx-preview h5,
.docx-preview h6 {
    white-space: normal;    /* ✅ Normal wrapping for headings */
    margin: 1em 0 0.5em 0;
}
</style>
```

### Approach 4: Use Pandoc with Better Options ✅ BEST

**Combine all approaches:**
1. Fix Pandoc command options
2. Post-process HTML
3. Fix frontend CSS

---

## 📋 Implementation Plan

### Step 1: Fix Pandoc Command Options
**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Method:** `buildPandocCommand()`

**Changes:**
```php
$options = [
    '--standalone',
    '--embed-resources',
    '--self-contained',
    '--metadata title="Document Preview"',
    '--css=' . $this->getInlineCss(),
    '--wrap=none',              // ✅ NEW: Don't wrap lines
    '--preserve-tabs',          // ✅ NEW: Preserve tabs
];
```

### Step 2: Enhance HTML Post-Processing
**File:** `app/Services/PandocDocxToHtmlConverter.php`
**Method:** `enhanceHtml()`

**Changes:**
```php
protected function enhanceHtml(string $html): string
{
    // ... existing code ...
    
    // ✅ FIX: Add CSS to preserve line breaks
    $cssFix = <<<CSS
<style>
    body {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    p {
        white-space: normal;
        margin: 0.5em 0;
    }
    h1, h2, h3, h4, h5, h6 {
        white-space: normal;
        margin: 1em 0 0.5em 0;
    }
</style>
CSS;
    
    // Insert CSS before </head>
    if (strpos($html, '</head>') !== false) {
        $html = str_replace('</head>', $cssFix . '</head>', $html);
    }
    
    return $html;
}
```

### Step 3: Fix Frontend CSS
**File:** `resources/js/Components/ReportPreview.vue`

**Changes:**
```vue
<style scoped>
.docx-preview {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.docx-preview :deep(p) {
    white-space: normal;
    margin: 0.5em 0;
}

.docx-preview :deep(h1),
.docx-preview :deep(h2),
.docx-preview :deep(h3),
.docx-preview :deep(h4),
.docx-preview :deep(h5),
.docx-preview :deep(h6) {
    white-space: normal;
    margin: 1em 0 0.5em 0;
}
</style>
```

---

## ✅ Testing

### Test Cases:
1. **Header Section:** Should have line breaks between company name and address
2. **Body Paragraphs:** Should have proper spacing between paragraphs
3. **Lists:** Should have line breaks between list items
4. **Tables:** Should preserve cell content with line breaks
5. **Footer:** Should have line breaks between contact info

### Expected Result:
- ✅ All line breaks preserved
- ✅ Proper spacing between sections
- ✅ Readable document structure
- ✅ No text concatenation

---

## 🚀 Priority

**HIGH** - This is a critical UX issue that makes the preview unusable.

---

## 📝 Notes

- Pandoc is already installed and working
- The issue is likely with command options or CSS
- Frontend rendering might also need fixes
- Test with actual DOCX file to verify fix






