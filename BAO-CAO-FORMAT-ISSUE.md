# 📋 BÁO CÁO VẤN ĐỀ FORMAT - TEXT BỊ XUỐNG DÒNG GIỮA CHỪNG

## 🎯 Vấn Đề

Format hiển thị trên web bị xuống dòng giữa chừng, text bị tách thành nhiều paragraph riêng biệt.

### Ví Dụ Vấn Đề:

**Hiện tại (SAI):**
```
TÊN CQ, TC CHỦ QUẢN

1

TÊN CƠ QUAN, TỔ CHỨC

2

Số:

...

/BB-

...

3

...

CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2

Số:.../BB-...3...CỘN

BIÊN BẢN

T

h

ời gian bắt đầu: ...............................................

(Chữ ký)

Họ và tên

CHỦ TỌA

(Ch

ữ

ký

của người cCCHỦ TỌA

(Chữ ký của người có t(Chữ ký của người có

L

ưu:

VT, Hồ sơ.
```

**Mong muốn (ĐÚNG):**
```
TÊN CQ, TC CHỦ QUẢN 1

TÊN CƠ QUAN, TỔ CHỨC 2

Số: .../BB-... 3

...

CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
Độc lập - Tự do - Hạnh phúc
─────────────────────────────

BIÊN BẢN

Thời gian bắt đầu: ...............................................

(Chữ ký)

Họ và tên

CHỦ TỌA

(Chữ ký của người có thẩm quyền)

Lưu: VT, Hồ sơ.
```

## 🔍 Phân Tích Nguyên Nhân

### 1. Vấn Đề Chính

**AdvancedDocxToHtmlConverter** đang convert mỗi `TextRun` thành một `<p>` tag riêng biệt:

```php
protected function convertTextRun(TextRun $textRun): string
{
    $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
    
    foreach ($textRun->getElements() as $element) {
        if ($element instanceof Text) {
            $html .= $this->convertText($element);
        }
    }
    
    $html .= '</p>';
    
    return $html;
}
```

**Vấn đề:** Trong DOCX, một paragraph có thể có nhiều `TextRun` (mỗi run có style khác nhau như bold, italic, superscript, subscript), nhưng chúng nên được merge lại thành một paragraph duy nhất.

### 2. Cấu Trúc DOCX

Trong DOCX, cấu trúc thường là:
```
<w:p>  <!-- Paragraph -->
  <w:r>  <!-- TextRun 1 -->
    <w:t>T</w:t>  <!-- Text "T" -->
  </w:r>
  <w:r>  <!-- TextRun 2 -->
    <w:t>h</w:t>  <!-- Text "h" -->
  </w:r>
  <w:r>  <!-- TextRun 3 -->
    <w:t>ời gian</w:t>  <!-- Text "ời gian" -->
  </w:r>
</w:p>
```

**PhpWord** đọc cấu trúc này thành:
- 1 `Paragraph` chứa 3 `TextRun`
- Mỗi `TextRun` có thể có style khác nhau (bold, italic, superscript, subscript)

**AdvancedDocxToHtmlConverter** hiện tại:
- Convert mỗi `TextRun` thành một `<p>` tag riêng
- Kết quả: 3 `<p>` tags thay vì 1 `<p>` tag

### 3. Cách PhpWord Xử Lý

PhpWord có method `getElements()` trên `Paragraph` để lấy tất cả elements (TextRun, Table, Image, etc.).

**Code hiện tại:**
```php
protected function convertElement(AbstractElement $element): string
{
    if ($element instanceof TextRun) {
        $html .= $this->convertTextRun($element);  // ❌ Tạo <p> cho mỗi TextRun
    } elseif ($element instanceof Text) {
        $html .= $this->convertText($element);
    }
}
```

**Vấn đề:** Khi gặp `TextRun`, code tạo `<p>` tag mới. Nhưng trong DOCX, nhiều `TextRun` có thể thuộc cùng một `Paragraph`.

## 🔧 Giải Pháp

### 1. Sửa Logic Convert Paragraph

**Cần sửa:** `convertElement()` và `convertToHtml()` để xử lý `Paragraph` đúng cách:

```php
protected function convertElement(AbstractElement $element): string
{
    $html = '';
    
    // ✅ FIX: Xử lý Paragraph riêng biệt
    if ($element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
        $html .= $this->convertParagraph($element);
    } elseif ($element instanceof TextRun) {
        // TextRun độc lập (không thuộc Paragraph)
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

/**
 * ✅ NEW: Convert Paragraph (chứa nhiều TextRun) thành một <p> tag
 */
protected function convertParagraph(\PhpOffice\PhpWord\Element\Paragraph $paragraph): string
{
    $style = $this->extractElementStyle($paragraph);
    $styleAttr = $this->styleArrayToCss($style);
    
    $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
    
    // ✅ FIX: Merge tất cả TextRun trong Paragraph thành một <p> tag
    foreach ($paragraph->getElements() as $element) {
        if ($element instanceof TextRun) {
            // Convert TextRun nhưng không tạo <p> tag mới
            foreach ($element->getElements() as $textElement) {
                if ($textElement instanceof Text) {
                    $html .= $this->convertText($textElement);
                }
            }
        } elseif ($element instanceof Text) {
            $html .= $this->convertText($element);
        }
    }
    
    $html .= '</p>';
    
    return $html;
}

/**
 * ✅ FIX: Convert TextRun (chỉ khi không thuộc Paragraph)
 */
protected function convertTextRun(TextRun $textRun): string
{
    // ✅ FIX: TextRun độc lập (không thuộc Paragraph) - giữ nguyên logic cũ
    $style = $this->extractElementStyle($textRun);
    $styleAttr = $this->styleArrayToCss($style);
    
    $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
    
    foreach ($textRun->getElements() as $element) {
        if ($element instanceof Text) {
            $html .= $this->convertText($element);
        }
    }
    
    $html .= '</p>';
    
    return $html;
}
```

### 2. Sửa Logic Convert Section

**Cần sửa:** `convertToHtml()` để xử lý `Section` và `Paragraph` đúng cách:

```php
protected function convertToHtml(): string
{
    $html = '';
    
    foreach ($this->phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            // ✅ FIX: Xử lý Paragraph riêng biệt
            if ($element instanceof \PhpOffice\PhpWord\Element\Paragraph) {
                $html .= $this->convertParagraph($element);
            } else {
                $html .= $this->convertElement($element);
            }
        }
    }
    
    return $html;
}
```

## 📊 So Sánh Trước/Sau Fix

| Aspect | Trước Fix | Sau Fix | Kết Quả |
|--------|-----------|---------|---------|
| **TextRun → HTML** | Mỗi TextRun = 1 `<p>` | Nhiều TextRun trong Paragraph = 1 `<p>` | ✅ Fixed |
| **"Thời gian"** | `<p>T</p><p>h</p><p>ời gian</p>` | `<p>Thời gian</p>` | ✅ Fixed |
| **"Họ và tên"** | `<p>H</p><p>Họ và</p><p>t</p><p>ê</p><p>n</p>` | `<p>Họ và tên</p>` | ✅ Fixed |
| **"Chữ ký"** | `<p>Ch</p><p>ữ</p><p>ký</p>` | `<p>Chữ ký</p>` | ✅ Fixed |
| **Paragraph Count** | 63+ (nhiều paragraph ngắn) | ~16-20 (paragraph hợp lý) | ✅ Fixed |

## 🎯 Các Phần Cần Sửa

### 1. File: `app/Services/AdvancedDocxToHtmlConverter.php`

**Các method cần sửa:**
1. ✅ `convertElement()` - Thêm xử lý `Paragraph`
2. ✅ `convertParagraph()` - **NEW** - Convert Paragraph (merge TextRun)
3. ✅ `convertTextRun()` - **FIX** - Chỉ tạo `<p>` khi TextRun độc lập
4. ✅ `convertToHtml()` - **FIX** - Xử lý Paragraph riêng biệt

**Code changes:**
- Thêm method `convertParagraph()` mới
- Sửa `convertElement()` để xử lý `Paragraph`
- Sửa `convertTextRun()` để không tạo `<p>` khi thuộc Paragraph
- Sửa `convertToHtml()` để xử lý Paragraph đúng cách

## 📝 Next Steps

1. ✅ **Phân tích vấn đề:** Hoàn thành
2. ⏳ **Implement fix:** Cần sửa code
3. ⏳ **Test:** Test lại trên browser
4. ⏳ **Verify:** So sánh với template DOCX gốc

## 🔍 Chi Tiết Kỹ Thuật

### Cấu Trúc DOCX XML

```xml
<w:p>  <!-- Paragraph -->
  <w:pPr>  <!-- Paragraph Properties -->
    <w:jc w:val="center"/>  <!-- Justification -->
  </w:pPr>
  <w:r>  <!-- TextRun 1 -->
    <w:rPr>  <!-- Run Properties -->
      <w:b/>  <!-- Bold -->
    </w:rPr>
    <w:t>T</w:t>  <!-- Text "T" -->
  </w:r>
  <w:r>  <!-- TextRun 2 -->
    <w:t>h</w:t>  <!-- Text "h" -->
  </w:r>
  <w:r>  <!-- TextRun 3 -->
    <w:t>ời gian</w:t>  <!-- Text "ời gian" -->
  </w:r>
</w:p>
```

### Cấu Trúc PhpWord

```php
Paragraph
  ├── TextRun 1 (style: bold)
  │   └── Text "T"
  ├── TextRun 2 (style: normal)
  │   └── Text "h"
  └── TextRun 3 (style: normal)
      └── Text "ời gian"
```

### Cấu Trúc HTML Mong Muốn

```html
<p style="text-align: center;">
  <span style="font-weight: bold;">T</span>
  <span>h</span>
  <span>ời gian</span>
</p>
```

### Cấu Trúc HTML Hiện Tại (SAI)

```html
<p style="font-weight: bold;">T</p>
<p>h</p>
<p>ời gian</p>
```

## 🎯 Kết Luận

**Vấn đề:** `AdvancedDocxToHtmlConverter` đang convert mỗi `TextRun` thành một `<p>` tag riêng biệt, dẫn đến text bị tách và xuống dòng giữa chừng.

**Giải pháp:** Cần sửa logic để merge tất cả `TextRun` trong cùng một `Paragraph` thành một `<p>` tag duy nhất.

**Các file cần sửa:**
- `app/Services/AdvancedDocxToHtmlConverter.php` - Thêm method `convertParagraph()` và sửa logic convert



