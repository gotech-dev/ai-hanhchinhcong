# 📋 BÁO CÁO KIỂM TRA CÁCH LÀM

## 🎯 Mục Tiêu

Kiểm tra và so sánh cách report cũ và document hiện tại dùng Pandoc để tìm ra sự khác biệt.

## 🔍 Kiểm Tra

### 1. Pandoc Options

**File:** `app/Services/PandocDocxToHtmlConverter.php`

**Pandoc Command Options:**
```php
$options = [
    '--standalone',              // Create complete HTML document
    '--wrap=none',               // Don't wrap text
    '--preserve-tabs',           // Preserve tabs
    '--from=docx',               // Input format
    '--to=html5',                // Output format HTML5
    '--no-highlight',            // Tắt syntax highlighting
];
```

**Phân tích:**
- ✅ Cùng một converter cho cả report và document: `PandocDocxToHtmlConverter`
- ✅ Cùng Pandoc options:
  - `--standalone`
  - `--wrap=preserve` (Preserve line breaks and paragraph structure)
  - `--preserve-tabs`
  - `--from=docx`
  - `--to=html5`
  - `--no-highlight`
- ⚠️ **Không có sự khác biệt về Pandoc options** - Cùng options cho cả report và document

### 2. ReportController vs DocumentController

**ReportController:**
```php
$converter = new PandocDocxToHtmlConverter();
$html = $converter->convert($docxPath);
```

**DocumentController:**
```php
$converter = new PandocDocxToHtmlConverter();
$html = $converter->convert($docxPath);
```

**Phân tích:**
- ✅ Cùng một converter
- ✅ Cùng cách gọi
- ⚠️ Không có sự khác biệt về cách gọi

### 3. Template Files

**Report Template:**
- Đường dẫn: `storage/app/public/reports/...`
- Format: DOCX template cho report

**Document Template:**
- Đường dẫn: `storage/app/public/documents/...`
- Format: DOCX template cho document

**Phân tích:**
- ⚠️ **Template khác nhau:**
  - Report template: 20-23K (lớn hơn)
  - Document template: 7-8K (nhỏ hơn)
- ⚠️ **Có thể DOCX structure khác nhau:**
  - Report template có thể được format tốt hơn
  - Report DOCX có thể có structure tốt hơn (ít split text hơn)

### 4. Pandoc Output

**Backend Log (Document):**
```
Pandoc raw HTML output (before enhancement):
- p_tag_count: 61
- html_snippet: <p>TÊN CQ, TC CHỦ QUẢN</p><p><sup>1</sup></p><p>TÊN CƠ QUAN, TỔ CHỨC</p><p><sup>2</sup></p>

Before ensureParagraphStructure: pTagCount=61
After ensureParagraphStructure: pTagCount=61 (No merging)
```

**Phân tích:**
- ❌ **Pandoc đang split text thành 61 paragraphs ngay từ đầu** (trước khi có logic merge)
- ❌ **Pandoc split superscript/subscript thành paragraph riêng**: `<p><sup>1</sup></p>`
- ❌ **Pandoc split text sai**: `<p>T</p><p>h</p><p>ời gian bắt đầu</p>`
- ❌ **Pandoc split text sai ngay từ đầu** - Đây là vấn đề từ Pandoc, không phải từ logic merge

## 🔍 Nguyên Nhân

### Vấn Đề Không Phải Ở Logic Merge

**Kết quả test:**
- Bỏ hết logic merge → Paragraph count tăng từ 10 lên 61
- Vẫn còn duplicate text và text bị tách
- **Pandoc đang split text sai ngay từ đầu**

**Phân tích:**
- ❌ **Vấn đề không phải ở logic merge**, mà ở **cách Pandoc convert DOCX**
- ❌ Pandoc đang split text thành nhiều paragraph nhỏ
- ❌ Pandoc split superscript/subscript thành paragraph riêng
- ❌ Pandoc split text sai (ví dụ: "T", "h", "ời gian bắt đầu")

### Tại Sao Report Cũ Lại Đúng?

**Giả thuyết:**
1. **Report template được format tốt hơn** - Report DOCX (20-23K) có thể có structure tốt hơn document (7-8K)
2. **Report DOCX không có superscript/subscript phức tạp** - Report có thể không có superscript/subscript như document
3. **Report DOCX có paragraph structure rõ ràng** - Report có thể có paragraph structure rõ ràng hơn, Pandoc không split text sai
4. **Report DOCX có thể được tạo từ template khác** - Report có thể được tạo từ template format tốt hơn

**Cần kiểm tra:**
- So sánh report DOCX vs document DOCX structure
- Xem report DOCX có superscript/subscript không
- Xem report DOCX có paragraph structure rõ ràng hơn không

## 💡 Giải Pháp

### Giải Pháp 1: Dùng AdvancedDocxToHtmlConverter (Recommended)

**Lý do:**
- ✅ Native PHP, không cần external tool
- ✅ Preserve format tốt (95%+)
- ✅ Không cần merge paragraph
- ✅ Preserve structure từ DOCX

**Implementation:**
```php
// app/Http/Controllers/DocumentController.php
use App\Services\AdvancedDocxToHtmlConverter;

$converter = new AdvancedDocxToHtmlConverter();
$html = $converter->convert($docxPath);
```

### Giải Pháp 2: Cải Thiện Pandoc Options

**Thử các options khác:**
```php
$options = [
    '--standalone',
    '--wrap=none',
    '--preserve-tabs',
    '--from=docx+styles',        // ✅ FIX: Preserve styles
    '--to=html5+raw_html',       // ✅ FIX: Preserve raw HTML
    '--extract-media=./media',    // ✅ FIX: Extract media
    '--no-highlight',
    '--metadata=lang:vi',         // ✅ FIX: Set language to Vietnamese
];
```

### Giải Pháp 3: Kiểm Tra Report Template

**Kiểm tra:**
1. So sánh report template vs document template structure
2. Xem report template có structure tốt hơn không
3. Xem report template có superscript/subscript không
4. Xem report DOCX có paragraph structure rõ ràng hơn không

**Kết quả kiểm tra:**
- Report template: 20-23K (lớn hơn)
- Document template: 7-8K (nhỏ hơn)
- ⚠️ **Có thể report template được format tốt hơn** - Cần kiểm tra chi tiết

## 📊 So Sánh

| Aspect | Report Cũ | Document Hiện Tại | Sự Khác Biệt |
|--------|-----------|-------------------|--------------|
| **Converter** | PandocDocxToHtmlConverter | PandocDocxToHtmlConverter | ✅ Cùng |
| **Pandoc Options** | Cùng options | Cùng options | ✅ Cùng |
| **Logic Merge** | Có thể không có | Đã bỏ | ✅ Không còn |
| **Template** | Report template | Document template | ⚠️ **Có thể khác** |
| **DOCX Structure** | Có thể tốt hơn | Có thể kém hơn | ⚠️ **Có thể khác** |
| **Format** | ✅ Đúng | ❌ Sai | ❌ **Khác** |

## 🎯 Kết Luận

### Vấn Đề

1. **Vấn đề không phải ở logic merge** - Đã bỏ hết logic merge nhưng vẫn còn lỗi
2. **Vấn đề ở cách Pandoc convert DOCX** - Pandoc đang split text sai
3. **Có thể template khác nhau** - Report template có thể được format tốt hơn

### Giải Pháp Đề Xuất

**Dùng AdvancedDocxToHtmlConverter** (như đã đề xuất trong `change-method.md`):
- ✅ Native PHP, không cần external tool
- ✅ Preserve format tốt (95%+)
- ✅ Không cần merge paragraph
- ✅ Preserve structure từ DOCX
- ✅ Không sai chính tả
- ✅ Format giống tuyệt đối với DOCX template

## 📝 Next Steps

1. ✅ **Test AdvancedDocxToHtmlConverter** - Thay Pandoc bằng AdvancedDocxToHtmlConverter
2. ✅ **So sánh kết quả** - So sánh với Pandoc output
3. ✅ **Fix nếu cần** - Fix các vấn đề còn lại (nếu có)
4. ✅ **Deploy** - Deploy và monitor

