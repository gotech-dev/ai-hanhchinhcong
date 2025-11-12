# 🔍 PHƯƠNG ÁN DEBUG: VẤN ĐỀ HIỂN THỊ TEMPLATE

**Ngày:** 2025-11-09  
**Vấn đề:** Template hiển thị trên chat format hoàn toàn không giống với template mẫu

---

## 🎯 MỤC TIÊU DEBUG

Tìm ra nguyên nhân tại sao format hiển thị trên browser không giống với template gốc, mặc dù:
- Text content giống nhau (96.72% match rate)
- HTML paragraphs = DOCX lines (61 = 61)
- Text content đúng

---

## 🔍 PHƯƠNG ÁN DEBUG

### 1. Debug Backend: So Sánh Template Gốc vs Generated DOCX vs HTML

**Script:** `debug-template-display.php`

**Cách chạy:**
```bash
php debug-template-display.php {message_id}
```

**Output:**
- So sánh template gốc vs generated DOCX
- So sánh generated DOCX vs HTML
- Extract HTML paragraphs với styles
- Phân tích cấu trúc HTML

**Mục tiêu:**
- Tìm ra differences giữa template gốc và generated DOCX
- Tìm ra differences giữa generated DOCX và HTML
- Kiểm tra styles được apply đúng chưa

### 2. Debug Backend: Log HTML Output Chi Tiết

**File:** `app/Http/Controllers/DocumentController.php`

**Đã thêm:**
- Log first 10 paragraphs với text, length, HTML, styles
- So sánh với template gốc nếu có
- Log template comparison

**Cách xem log:**
```bash
tail -f storage/logs/laravel.log | grep "DocumentController"
```

**Mục tiêu:**
- Xem HTML output chi tiết từ backend
- So sánh với template gốc
- Kiểm tra styles được apply

### 3. Debug Frontend: Log HTML Rendering Chi Tiết

**File:** `resources/js/Components/DocumentPreview.vue`

**Đã thêm:**
- Log first 10 paragraphs với text, length, HTML, computed styles
- Log CSS applied
- Log HTML structure

**Cách xem log:**
- Mở browser console
- Tìm log `[DocumentPreview]`

**Mục tiêu:**
- Xem HTML rendering trên frontend
- Kiểm tra CSS được apply đúng chưa
- So sánh với backend output

### 4. Debug: So Sánh HTML Output vs Template Gốc

**Script:** `analyze-docx-structure.php`

**Cách chạy:**
```bash
php analyze-docx-structure.php {template_path}
```

**Output:**
- Phân tích cấu trúc DOCX XML
- Extract paragraphs với text, styles, alignment
- Tìm problematic paragraphs (concatenated text)

**Mục tiêu:**
- Hiểu cấu trúc DOCX gốc
- Tìm ra vấn đề text concatenation
- So sánh với HTML output

---

## 📊 CHECKLIST DEBUG

### Backend Debug

- [ ] **Chạy debug script:**
  ```bash
  php debug-template-display.php {message_id}
  ```
  
- [ ] **Kiểm tra log backend:**
  ```bash
  tail -f storage/logs/laravel.log | grep "DocumentController"
  ```
  
- [ ] **So sánh template gốc vs generated DOCX:**
  - Số dòng giống nhau?
  - Text content giống nhau?
  - Format giống nhau?
  
- [ ] **So sánh generated DOCX vs HTML:**
  - Số dòng giống nhau?
  - Text content giống nhau?
  - Styles được preserve đúng?

### Frontend Debug

- [ ] **Mở browser console:**
  - Tìm log `[DocumentPreview]`
  - Kiểm tra first 10 paragraphs
  - Kiểm tra computed styles
  
- [ ] **So sánh frontend vs backend:**
  - HTML giống nhau?
  - Styles giống nhau?
  - Format giống nhau?

### DOCX Structure Debug

- [ ] **Phân tích DOCX structure:**
  ```bash
  php analyze-docx-structure.php {template_path}
  ```
  
- [ ] **Tìm problematic paragraphs:**
  - Text concatenation
  - Empty paragraphs
  - Format issues

---

## 🔧 CÁC BƯỚC DEBUG

### Bước 1: Chạy Debug Script

```bash
php debug-template-display.php {message_id}
```

**Kiểm tra:**
- Template gốc vs Generated DOCX differences
- Generated DOCX vs HTML differences
- HTML paragraphs với styles

### Bước 2: Kiểm Tra Log Backend

```bash
tail -f storage/logs/laravel.log | grep "DocumentController"
```

**Kiểm tra:**
- HTML output chi tiết
- First 10 paragraphs
- Template comparison

### Bước 3: Kiểm Tra Log Frontend

**Mở browser console:**
- Tìm log `[DocumentPreview]`
- Kiểm tra first 10 paragraphs
- Kiểm tra computed styles

### Bước 4: So Sánh Kết Quả

**So sánh:**
- Backend HTML vs Frontend HTML
- Backend styles vs Frontend computed styles
- Template gốc vs HTML output

### Bước 5: Phân Tích Vấn Đề

**Tìm ra:**
- Vấn đề ở đâu? (Backend, Frontend, hoặc cả hai)
- Format nào bị mất?
- Styles nào không được apply?

---

## 📝 KẾT QUẢ DEBUG

### Template Gốc vs Generated DOCX

**Kết quả:**
- [ ] Số dòng: Template: X, Generated: Y
- [ ] Differences: Z differences
- [ ] Match rate: X%

**Phân tích:**
- [ ] Text content giống nhau?
- [ ] Format giống nhau?
- [ ] Styles giống nhau?

### Generated DOCX vs HTML

**Kết quả:**
- [ ] Số dòng: Generated: X, HTML: Y
- [ ] Differences: Z differences
- [ ] Match rate: X%

**Phân tích:**
- [ ] Text content giống nhau?
- [ ] Format giống nhau?
- [ ] Styles giống nhau?

### Backend vs Frontend

**Kết quả:**
- [ ] HTML giống nhau?
- [ ] Styles giống nhau?
- [ ] Format giống nhau?

**Phân tích:**
- [ ] Vấn đề ở Backend hay Frontend?
- [ ] CSS được apply đúng chưa?
- [ ] Format được preserve đúng chưa?

---

## 🎯 KẾT LUẬN

Sau khi debug, cần xác định:

1. **Vấn đề ở đâu?**
   - Backend: HTML output không đúng?
   - Frontend: CSS không apply đúng?
   - Cả hai?

2. **Format nào bị mất?**
   - Alignment?
   - Spacing?
   - Font?
   - Color?

3. **Giải pháp:**
   - Fix Backend?
   - Fix Frontend?
   - Fix cả hai?

---

## 📚 TÀI LIỆU THAM KHẢO

- `debug-template-display.php` - Debug script
- `analyze-docx-structure.php` - DOCX structure analysis
- `app/Http/Controllers/DocumentController.php` - Backend logging
- `resources/js/Components/DocumentPreview.vue` - Frontend logging



