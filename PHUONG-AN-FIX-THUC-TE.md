# 📋 PHƯƠNG ÁN FIX - VẤN ĐỀ THỰC TẾ

## 🎯 Vấn Đề Từ Hình Ảnh

Từ hình ảnh đính kèm, tôi thấy các vấn đề sau:

### 1. Text Bị Nối Liền ❌

**Vấn đề:**
- "CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2" - Text bị nối liền
- "Số:.../BB-...3...CỘN" - Text bị nối liền

**Nguyên nhân:**
- Text đã bị nối liền trong XML gốc (không phải do code)
- Paragraph boundaries không được xác định đúng
- TextRuns không được merge đúng trong cùng một paragraph

### 2. Superscript/Subscript Không Được Render ❌

**Vấn đề:**
- Các số "1", "2", "3", "4" đang hiển thị như text thường, không phải superscript
- Trong template gốc, các số này nên là superscript (footnote numbers)

**Nguyên nhân:**
- `isSuperscriptFromXml()` đang return false (placeholder)
- XML parsing cho superscript/subscript chưa được implement
- PhpWord không cung cấp method để check superscript/subscript

## 🔍 Phân Tích

### 1. Text Bị Nối Liền Trong XML

**Từ test:**
- Paragraph 11: "CỘNG HÒA XÃ HỘI CHỦTÊN CƠ QUAN, TỔ CHỨC2" - Text đã bị nối liền trong XML
- Paragraph 12: "Số:.../BB-...3..._x0007_CỘN" - Text đã bị nối liền trong XML

**Nguyên nhân:**
- Text đã bị nối liền trong XML gốc (không phải do code)
- Có thể do cách tạo template DOCX
- Cần parse XML trực tiếp để extract text từ mỗi TextRun riêng biệt

### 2. Superscript Không Được Render

**Từ test:**
- Paragraph 2: "1" có SUP (superscript) trong XML
- Paragraph 4: "2" có SUP
- Paragraph 9: "3" có SUP
- Paragraph 15: "." có SUP
- Paragraph 19: ".." có SUP

**Nhưng trong HTML:**
- Tất cả đều là NORM (không có superscript)

**Nguyên nhân:**
- `isSuperscriptFromXml()` đang return false (placeholder)
- XML parsing cho superscript/subscript chưa được implement
- Cần parse XML trực tiếp để check `<w:vertAlign w:val="superscript"/>`

## 🛠️ Giải Pháp

### 1. Parse XML Trực Tiếp Thay Vì Dùng PhpWord

**Cần làm:**
- Parse XML trực tiếp để extract text và styles từ mỗi paragraph
- Không dựa vào PhpWord TextRuns để map
- Extract text trực tiếp từ XML `<w:t>` nodes trong mỗi paragraph
- Extract styles trực tiếp từ XML `<w:rPr>` nodes trong mỗi TextRun

### 2. Implement Superscript/Subscript Detection

**Cần làm:**
- Parse XML trực tiếp để check `<w:vertAlign w:val="superscript"/>` hoặc `<w:vertAlign w:val="subscript"/>`
- Extract superscript/subscript từ XML trong mỗi TextRun
- Apply `<sup>` hoặc `<sub>` tags trong HTML

### 3. Extract Text Trực Tiếp Từ XML

**Cần làm:**
- Extract text trực tiếp từ XML `<w:t>` nodes
- Preserve paragraph boundaries từ XML
- Không merge text từ các paragraphs khác nhau

## 📝 Implementation Plan

### 1. Sửa `convertToHtml()` để Parse XML Trực Tiếp

**Thay đổi:**
- Không dùng PhpWord để parse DOCX
- Parse XML trực tiếp từ `word/document.xml`
- Extract text và styles từ mỗi paragraph

### 2. Implement `convertParagraphFromXml()`

**Mới:**
- Parse XML trực tiếp để extract text và styles từ mỗi paragraph
- Extract text từ `<w:t>` nodes
- Extract styles từ `<w:rPr>` nodes
- Extract superscript/subscript từ `<w:vertAlign>` nodes

### 3. Implement `convertTextRunFromXml()`

**Mới:**
- Parse XML trực tiếp để extract text và styles từ mỗi TextRun
- Extract text từ `<w:t>` nodes
- Extract styles từ `<w:rPr>` nodes
- Extract superscript/subscript từ `<w:vertAlign>` nodes

## 🎯 Next Steps

1. ✅ **Phân tích:** Đã phân tích vấn đề từ hình ảnh
2. ⏳ **Sửa code:** Cần sửa để parse XML trực tiếp
3. ⏳ **Test:** Cần test lại trên browser
4. ⏳ **Verify:** Cần verify với template gốc



