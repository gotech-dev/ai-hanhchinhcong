# 📋 PHƯƠNG ÁN TEST KỸ CÀNG

## 🎯 Mục Tiêu Test

Kiểm tra việc thay Pandoc bằng AdvancedDocxToHtmlConverter có fix được các vấn đề:
1. Format hiển thị giống template
2. Tiếng Việt đúng (không có ký tự lạ, không bị tách text)
3. UI không bị vỡ (không overflow, không resize sau 1s)

## 📝 Checklist Test

### 1. Setup Test Environment

- [ ] Clear browser cache
- [ ] Clear Laravel cache (`php artisan cache:clear`)
- [ ] Restart Laravel server
- [ ] Mở browser console để xem log

### 2. Login & Navigation

- [ ] Login với user: `gotechjsc@gmail.com` / `123456`
- [ ] Navigate đến `/chat`
- [ ] Chọn assistant "Tạo văn bản" (document_drafting)

### 3. Tạo Document

- [ ] Gửi message: "Tạo 1 mẫu Biên bản"
- [ ] Chờ document được tạo
- [ ] Kiểm tra document preview xuất hiện

### 4. Kiểm Tra Format (So sánh với DOCX gốc)

#### 4.1 Font & Typography
- [ ] Font family: Times New Roman (13pt)
- [ ] Font size: 13pt
- [ ] Line height: 1.5
- [ ] Text alignment: justify (căn đều 2 bên)

#### 4.2 Spacing
- [ ] Paragraph spacing: 0.5em
- [ ] Không có paragraph quá ngắn (< 5 ký tự)
- [ ] Không có paragraph trống không cần thiết

#### 4.3 Text Formatting
- [ ] **Bold** text hiển thị đúng
- [ ] *Italic* text hiển thị đúng
- [ ] <u>Underline</u> text hiển thị đúng
- [ ] Superscript (ví dụ: T<sup>ên</sup>) hiển thị đúng
- [ ] Subscript (ví dụ: H<sub>2</sub>O) hiển thị đúng

#### 4.4 Alignment
- [ ] Center alignment cho tiêu đề
- [ ] Justify alignment cho nội dung
- [ ] Right alignment cho chữ ký (nếu có)

### 5. Kiểm Tra Tiếng Việt

- [ ] Không có ký tự lạ (ví dụ: `ࠀ`, `_x0007_`)
- [ ] Không có text bị tách (ví dụ: "T", "h", "ời gian" → "Thời gian")
- [ ] Dấu tiếng Việt hiển thị đúng (ă, â, ê, ô, ơ, ư, đ)
- [ ] Không có duplicate text
- [ ] Không có text bị lặp lại

### 6. Kiểm Tra UI

- [ ] Preview container không overflow
- [ ] Preview không resize sau 1s (không có "jump")
- [ ] Preview responsive (không vỡ trên mobile)
- [ ] Scroll hoạt động tốt
- [ ] Download button hoạt động

### 7. Kiểm Tra Backend Log

- [ ] Log hiển thị: `AdvancedDocxToHtmlConverter (95%+ format, pure PHP)`
- [ ] Log hiển thị: `HTML generated` với `html_length` và `p_tag_count`
- [ ] Không có error trong log
- [ ] Conversion time hợp lý (< 2s)

### 8. So Sánh Với DOCX Gốc

- [ ] Mở DOCX file gốc trong Word
- [ ] So sánh từng phần:
  - [ ] Header/Footer
  - [ ] Tiêu đề
  - [ ] Nội dung chính
  - [ ] Chữ ký
- [ ] Format giống nhau ít nhất 95%

## 🔍 Chi Tiết Test

### Test Case 1: Basic Document

**Input:** "Tạo 1 mẫu Biên bản"

**Expected:**
- Document được tạo thành công
- Preview hiển thị format giống template
- Không có lỗi trong console

**Actual:**
- [ ] Document created: ✅/❌
- [ ] Preview displayed: ✅/❌
- [ ] Format correct: ✅/❌
- [ ] No errors: ✅/❌

### Test Case 2: Text với Superscript/Subscript

**Input:** Document có superscript/subscript (ví dụ: T<sup>ên</sup>, H<sub>2</sub>O)

**Expected:**
- Superscript/subscript hiển thị đúng
- Text không bị tách

**Actual:**
- [ ] Superscript correct: ✅/❌
- [ ] Subscript correct: ✅/❌
- [ ] Text not split: ✅/❌

### Test Case 3: Vietnamese Characters

**Input:** Document có tiếng Việt (ă, â, ê, ô, ơ, ư, đ)

**Expected:**
- Tiếng Việt hiển thị đúng
- Không có ký tự lạ

**Actual:**
- [ ] Vietnamese correct: ✅/❌
- [ ] No weird characters: ✅/❌

### Test Case 4: UI Stability

**Input:** Load document preview

**Expected:**
- Preview không resize sau 1s
- Không overflow
- Responsive

**Actual:**
- [ ] No resize after 1s: ✅/❌
- [ ] No overflow: ✅/❌
- [ ] Responsive: ✅/❌

## 📊 Báo Cáo Test

### Kết Quả Tổng Quan

| Test Case | Status | Notes |
|-----------|--------|-------|
| Basic Document | ⏳ | |
| Superscript/Subscript | ⏳ | |
| Vietnamese Characters | ⏳ | |
| UI Stability | ⏳ | |

### Screenshots

- [ ] Screenshot 1: Document preview
- [ ] Screenshot 2: DOCX gốc (Word)
- [ ] Screenshot 3: Console log
- [ ] Screenshot 4: Backend log

### Logs

- [ ] Backend log (Laravel)
- [ ] Frontend log (Browser console)
- [ ] Network log (Browser DevTools)

## 🎯 Kết Luận

### Pass/Fail Criteria

**PASS nếu:**
- ✅ Format giống template ít nhất 95%
- ✅ Tiếng Việt đúng, không có ký tự lạ
- ✅ UI không bị vỡ
- ✅ Không có lỗi trong log

**FAIL nếu:**
- ❌ Format sai > 5%
- ❌ Tiếng Việt sai hoặc có ký tự lạ
- ❌ UI bị vỡ hoặc overflow
- ❌ Có lỗi trong log

### Next Steps

- [ ] Nếu PASS: Deploy và monitor
- [ ] Nếu FAIL: Fix issues và test lại



