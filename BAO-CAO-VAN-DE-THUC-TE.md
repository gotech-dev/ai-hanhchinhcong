# 📋 BÁO CÁO VẤN ĐỀ THỰC TẾ - TỪ HÌNH ẢNH

## 🎯 Vấn Đề Từ Hình Ảnh

Từ hình ảnh đính kèm, tôi thấy các vấn đề sau:

### 1. Text Bị Nối Liền ❌

**Vấn đề:**
- "CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2" - Text bị nối liền, không có khoảng trắng
- "Số:.../BB-...3...CỘN" - Text bị nối liền

**Nguyên nhân:**
- Paragraph boundaries không được xác định đúng
- TextRuns không được merge đúng trong cùng một paragraph
- TextRuns từ các paragraphs khác nhau bị nối liền

### 2. Superscript/Subscript Không Được Render ❌

**Vấn đề:**
- Các số "1", "2", "3", "4" đang hiển thị như text thường, không phải superscript
- Trong template gốc, các số này nên là superscript (footnote numbers)

**Nguyên nhân:**
- `convertText()` không detect được superscript/subscript từ PhpWord
- XML parsing cho superscript/subscript không hoạt động
- `isSuperscriptFromXml()` và `isSubscriptFromXml()` đang return false

### 3. Format Không Giống Template Gốc ❌

**Vấn đề:**
- Text bị nối liền thay vì tách thành các paragraphs riêng biệt
- Superscript/subscript không được render
- Spacing và alignment không đúng

## 🔍 Phân Tích Nguyên Nhân

### 1. Mapping TextRuns to Paragraphs

**Vấn đề:**
- `groupTextRunsIntoParagraphs()` đang map TextRuns từ PhpWord với paragraphs từ XML
- Logic mapping có thể sai:
  - PhpWord có 63 TextRuns
  - XML có 75 paragraphs
  - Mapping 1-1 có thể không đúng

**Nguyên nhân:**
- PhpWord parse DOCX thành TextRuns theo cách khác với XML
- Một paragraph trong XML có thể có nhiều TextRuns
- Một TextRun trong PhpWord có thể không tương ứng với một paragraph trong XML

### 2. Superscript/Subscript Detection

**Vấn đề:**
- `convertText()` đang check superscript/subscript nhưng không detect được
- `isSuperscriptFromXml()` và `isSubscriptFromXml()` đang return false (placeholder)

**Nguyên nhân:**
- PhpWord không cung cấp method `getSuperScript()` hoặc `getSubScript()`
- `getVertAlign()` có thể không hoạt động
- XML parsing cho superscript/subscript chưa được implement

## 🛠️ Giải Pháp

### 1. Sửa Mapping TextRuns to Paragraphs

**Cần làm:**
- Parse XML trực tiếp để extract text và styles từ mỗi paragraph
- Không dựa vào PhpWord TextRuns để map
- Extract text trực tiếp từ XML `<w:t>` nodes trong mỗi paragraph

### 2. Sửa Superscript/Subscript Detection

**Cần làm:**
- Parse XML trực tiếp để check `<w:vertAlign w:val="superscript"/>` hoặc `<w:vertAlign w:val="subscript"/>`
- Extract superscript/subscript từ XML trong mỗi TextRun
- Apply `<sup>` hoặc `<sub>` tags trong HTML

### 3. Sửa Text Extraction

**Cần làm:**
- Extract text trực tiếp từ XML `<w:t>` nodes
- Preserve paragraph boundaries từ XML
- Không merge text từ các paragraphs khác nhau

## 📝 Next Steps

1. ✅ **Phân tích:** Đã phân tích vấn đề từ hình ảnh
2. ⏳ **Sửa code:** Cần sửa mapping và superscript/subscript detection
3. ⏳ **Test:** Cần test lại trên browser
4. ⏳ **Verify:** Cần verify với template gốc



