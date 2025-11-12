# ✅ CHỐT LẠI CÁCH LÀM

## 🎯 Câu Hỏi

1. Parse file template thành dạng Placeholders → dùng PHP?
2. Hiển thị trên HTML (để giống template nhất) → dùng Pandoc?

## ✅ Xác Nhận

### 1. Parse Template Thành Placeholders

**✅ ĐÚNG - Dùng PHP thuần**

**File:** `app/Services/TemplatePlaceholderGenerator.php`

**Công nghệ:**
- ✅ **PHP Native:**
  - `ZipArchive` - Mở DOCX file (DOCX là ZIP)
  - `DOMDocument` - Parse XML
  - `DOMXPath` - Query XML
- ✅ **PHP Library:**
  - `PhpOffice\PhpWord\TemplateProcessor` - Extract placeholders
  - `PhpOffice\PhpWord\IOFactory` - Load DOCX

**Code:**
```php
// app/Services/TemplatePlaceholderGenerator.php
use ZipArchive;
use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;

// Extract placeholders từ DOCX XML
$zip = new ZipArchive();
if ($zip->open($templatePath) === true) {
    $documentXml = $zip->getFromName('word/document.xml');
    // Parse XML để tìm ${key}, {{key}}, [key]
    // ...
}
```

**File liên quan:**
- `app/Services/SmartDocxReplacer.php` - Cũng dùng PHP thuần (ZipArchive, DOMDocument)

**Kết luận:**
- ✅ **Dùng PHP thuần** - Không dùng Pandoc hay Mammoth
- ✅ **Parse XML trực tiếp** - DOCX là ZIP chứa XML

### 2. Hiển Thị Template Lên HTML

**✅ ĐÚNG - Dùng Pandoc**

**File:** `app/Services/PandocDocxToHtmlConverter.php`

**Công nghệ:**
- ✅ **Pandoc** - External tool (command line)
- ✅ **Fallback:** `AdvancedDocxToHtmlConverter` (PHP thuần với PhpWord)

**Code:**
```php
// app/Http/Controllers/DocumentController.php
try {
    $converter = new PandocDocxToHtmlConverter();
    $html = $converter->convert($docxPath);
} catch (\Exception $e) {
    // Fallback to PhpWord
    $converter = new AdvancedDocxToHtmlConverter();
    $html = $converter->convert($docxPath);
}
```

**Routes:**
- `GET /api/documents/{messageId}/preview-html` - Document preview
- `GET /api/reports/{reportId}/preview-html` - Report preview

**Kết luận:**
- ✅ **Dùng Pandoc** - Để giữ format tốt nhất (95-98%)
- ✅ **Fallback PHP** - Nếu Pandoc không có

## 📊 Tóm Tắt

| Chức Năng | Công Nghệ | File |
|-----------|-----------|------|
| **Parse Template → Placeholders** | ✅ PHP thuần | `TemplatePlaceholderGenerator.php`<br>`SmartDocxReplacer.php` |
| **Hiển Thị HTML** | ✅ Pandoc | `PandocDocxToHtmlConverter.php` |

## 🎯 Kết Luận

### ✅ ĐÚNG

1. **Parse file template thành dạng Placeholders:**
   - ✅ **Dùng PHP thuần**
   - ✅ Không dùng Pandoc hay Mammoth
   - ✅ Parse XML trực tiếp từ DOCX (ZIP)

2. **Hiển thị trên HTML (để giống template nhất):**
   - ✅ **Dùng Pandoc**
   - ✅ Fallback: PHP thuần (PhpWord) nếu Pandoc không có

## 💡 Lý Do

### Tại Sao Parse Placeholders Dùng PHP?

1. **Cần modify DOCX file** - Thêm placeholders vào XML
2. **Cần control chính xác** - Parse và modify XML trực tiếp
3. **Không cần format preservation** - Chỉ cần extract/modify text
4. **PHP native** - ZipArchive, DOMDocument có sẵn

### Tại Sao Hiển Thị HTML Dùng Pandoc?

1. **Cần format preservation tốt** - Pandoc giữ 95-98% format
2. **Không cần modify** - Chỉ convert DOCX → HTML
3. **Pandoc tốt nhất** - Format preservation tốt hơn PhpWord (85-90%)
4. **External tool** - Không cần control chi tiết như parse placeholders

## 📝 Ghi Chú

- **Parse placeholders:** Cần modify DOCX → Dùng PHP để control chính xác
- **Hiển thị HTML:** Chỉ cần convert → Dùng Pandoc để format tốt nhất



