# HƯỚNG DẪN MANUAL TESTING: FIX TRỢ LÝ BÁO CÁO

**Ngày tạo:** 7/11/2025  
**Mục đích:** Hướng dẫn chi tiết cách test manual fix trợ lý báo cáo

---

## 📋 PRE-TEST SETUP

### Bước 1: Chuẩn bị Test Templates

Tạo các file DOCX test templates:

#### Template 1: Simple Placeholders (`test-template-1-simple.docx`)

**Nội dung:**
```
CÔNG TY TNHH ABC
PHÒNG KINH DOANH
BÁO CÁO HOẠT ĐỘNG THÁNG [Tháng/Năm]

I. TỔNG QUAN HOẠT ĐỘNG
Mục tiêu tháng: [Mục tiêu]

II. KẾT QUẢ HOẠT ĐỘNG
[Tình hình chung]
```

**Format:**
- Font: Times New Roman, 14pt
- "CÔNG TY TNHH ABC": Bold, Center, 16pt
- "PHÒNG KINH DOANH": Bold, Center, 14pt
- "[Tháng/Năm]": Regular, Center
- Section headings: Bold, 16pt

**Placeholders:**
- `[Tháng/Năm]`
- `[Mục tiêu]`
- `[Tình hình chung]`

#### Template 2: Complex Placeholders (`test-template-2-complex.docx`)

**Nội dung:**
```
CÔNG TY TNHH XYZ
PHÒNG KINH DOANH
BÁO CÁO HOẠT ĐỘNG THÁNG [Tháng/Năm]

I. TỔNG QUAN HOẠT ĐỘNG
Mục tiêu tháng: [Mục tiêu]
Tình hình chung: [Tình hình chung]

II. KẾT QUẢ HOẠT ĐỘNG
1. Doanh số: [Doanh số]
2. Khách hàng mới: [Khách hàng mới]
3. Dự án hoàn thành: [Dự án hoàn thành]

III. KHÓ KHĂN VÀ THÁCH THỨC
[Khó khăn]

IV. GIẢI PHÁP VÀ KẾ HOẠCH
[Giải pháp]

V. KẾT LUẬN
[Kết luận]
```

**Placeholders:**
- `[Tháng/Năm]`
- `[Mục tiêu]`
- `[Tình hình chung]`
- `[Doanh số]`
- `[Khách hàng mới]`
- `[Dự án hoàn thành]`
- `[Khó khăn]`
- `[Giải pháp]`
- `[Kết luận]`

#### Template 3: Multiple Formats (`test-template-3-formats.docx`)

**Nội dung:**
```
CÔNG TY {{Tên công ty}}
Địa chỉ: [Địa chỉ]
Số điện thoại: ${Số điện thoại}
Email: {Email}
```

**Placeholders:**
- `{{Tên công ty}}`
- `[Địa chỉ]`
- `${Số điện thoại}`
- `{Email}`

#### Template 4: No Placeholders (`test-template-4-no-placeholders.docx`)

**Nội dung:**
```
CÔNG TY TNHH ABC
PHÒNG KINH DOANH
BÁO CÁO HOẠT ĐỘNG THÁNG 11/2024

I. TỔNG QUAN HOẠT ĐỘNG
Mục tiêu tháng: Tăng trưởng doanh số 20%

II. KẾT QUẢ HOẠT ĐỘNG
Thị trường ổn định, đối thủ cạnh tranh mạnh
```

**Lưu ý:** Template này KHÔNG có placeholders, chỉ có nội dung cố định.

#### Template 5: Table Placeholders (`test-template-5-table.docx`)

**Nội dung:**
```
CÔNG TY TNHH ABC
BÁO CÁO HOẠT ĐỘNG THÁNG [Tháng/Năm]

| STT | Dự án | Trạng thái | Ghi chú |
|-----|-------|------------|---------|
| 1   | [Dự án 1] | [Trạng thái 1] | [Ghi chú 1] |
| 2   | [Dự án 2] | [Trạng thái 2] | [Ghi chú 2] |
| 3   | [Dự án 3] | [Trạng thái 3] | [Ghi chú 3] |
```

**Placeholders:**
- `[Tháng/Năm]`
- `[Dự án 1]`, `[Dự án 2]`, `[Dự án 3]`
- `[Trạng thái 1]`, `[Trạng thái 2]`, `[Trạng thái 3]`
- `[Ghi chú 1]`, `[Ghi chú 2]`, `[Ghi chú 3]`

---

## 🧪 TEST EXECUTION

### Test Case 1: Simple Placeholders

#### Steps:

1. **Login vào Admin Panel**
   - URL: `/admin/assistants/create`
   - Login với admin account

2. **Tạo Test Assistant**
   - Name: `Test Assistant - Simple Placeholders`
   - Description: `Test assistant với placeholders đơn giản`
   - Type: `report_generator`
   - Upload: `test-template-1-simple.docx`
   - Click: `Tạo Assistant`

3. **Verify Assistant Created**
   - Check assistant được tạo thành công
   - Check template file được upload
   - Check assistant type = `report_generator`

4. **Login vào User Panel**
   - URL: `/chat` hoặc `/assistants`
   - Login với user account

5. **Chọn Assistant**
   - Chọn `Test Assistant - Simple Placeholders`

6. **Chat với Assistant**
   ```
   User: Tạo báo cáo hoạt động tháng 11/2024
   Assistant: [Hỏi thông tin nếu cần]
   User: Mục tiêu: Tăng trưởng doanh số 20%, mở rộng thị trường
   User: Tình hình: Thị trường ổn định, đối thủ cạnh tranh mạnh
   ```

7. **Chờ Assistant Tạo Báo Cáo**
   - Wait for response
   - Check có button "Tải DOCX"

8. **Download DOCX File**
   - Click button "Tải DOCX"
   - Save file: `test-report-1-simple.docx`

9. **Verify DOCX File**
   - Mở file bằng Microsoft Word hoặc LibreOffice Writer
   - So sánh với template gốc

#### Expected Results:

**✅ PASS nếu:**
- [ ] DOCX file được tạo thành công
- [ ] Format giống hệt template:
  - [ ] Font Times New Roman, 14pt
  - [ ] "CÔNG TY TNHH ABC" Bold, Center
  - [ ] "PHÒNG KINH DOANH" Bold, Center
  - [ ] Section headings Bold, 16pt
- [ ] Placeholders được điền đúng:
  - [ ] `[Tháng/Năm]` → `11/2024`
  - [ ] `[Mục tiêu]` → `Tăng trưởng doanh số 20%, mở rộng thị trường`
  - [ ] `[Tình hình chung]` → `Thị trường ổn định, đối thủ cạnh tranh mạnh`
- [ ] Nội dung cố định KHÔNG thay đổi:
  - [ ] "CÔNG TY TNHH ABC" vẫn giữ nguyên
  - [ ] "PHÒNG KINH DOANH" vẫn giữ nguyên
  - [ ] "I. TỔNG QUAN HOẠT ĐỘNG" vẫn giữ nguyên
  - [ ] "II. KẾT QUẢ HOẠT ĐỘNG" vẫn giữ nguyên

**❌ FAIL nếu:**
- DOCX file không được tạo
- Format khác template
- Placeholders không được điền hoặc điền sai
- Nội dung cố định bị thay đổi

#### Screenshots:

- [ ] Screenshot template gốc
- [ ] Screenshot báo cáo được tạo
- [ ] Screenshot so sánh side-by-side

---

### Test Case 2: Complex Placeholders

#### Steps:

1. **Tạo Test Assistant**
   - Name: `Test Assistant - Complex Placeholders`
   - Type: `report_generator`
   - Upload: `test-template-2-complex.docx`

2. **Chat với Assistant**
   ```
   User: Tạo báo cáo hoạt động tháng 11/2024
   User: Mục tiêu: Tăng trưởng doanh số 20%
   User: Tình hình: Thị trường ổn định
   User: Doanh số: 500 triệu VNĐ
   User: Khách hàng mới: 25 khách hàng
   User: Dự án hoàn thành: 3 dự án
   User: Khó khăn: Thiếu nhân lực, cạnh tranh mạnh
   User: Giải pháp: Tuyển dụng thêm, cải thiện chất lượng dịch vụ
   User: Kết luận: Tháng 11 đạt kết quả tốt
   ```

3. **Download và Verify DOCX File**

#### Expected Results:

**✅ PASS nếu:**
- [ ] Tất cả 9 placeholders được điền đúng
- [ ] Format giữ nguyên cho tất cả sections
- [ ] Nội dung cố định KHÔNG thay đổi

---

### Test Case 3: Multiple Formats

#### Steps:

1. **Tạo Test Assistant**
   - Name: `Test Assistant - Multiple Formats`
   - Type: `report_generator`
   - Upload: `test-template-3-formats.docx`

2. **Chat với Assistant**
   ```
   User: Tạo báo cáo
   User: Tên công ty: CÔNG TY TNHH ABC
   User: Địa chỉ: 123 Đường X, Quận Y, TP.HCM
   User: Số điện thoại: 0123456789
   User: Email: contact@abc.com
   ```

3. **Download và Verify DOCX File**

#### Expected Results:

**✅ PASS nếu:**
- [ ] Tất cả 4 format placeholders được điền đúng:
  - [ ] `{{Tên công ty}}` → `CÔNG TY TNHH ABC`
  - [ ] `[Địa chỉ]` → `123 Đường X, Quận Y, TP.HCM`
  - [ ] `${Số điện thoại}` → `0123456789`
  - [ ] `{Email}` → `contact@abc.com`

---

### Test Case 4: No Placeholders

#### Steps:

1. **Tạo Test Assistant**
   - Name: `Test Assistant - No Placeholders`
   - Type: `report_generator`
   - Upload: `test-template-4-no-placeholders.docx`

2. **Chat với Assistant**
   ```
   User: Tạo báo cáo
   ```

3. **Download và Verify DOCX File**

#### Expected Results:

**✅ PASS nếu:**
- [ ] DOCX file được tạo thành công
- [ ] Template giữ nguyên 100% (không có thay đổi gì)
- [ ] Format giữ nguyên
- [ ] Nội dung cố định giữ nguyên

---

### Test Case 5: Table Placeholders

#### Steps:

1. **Tạo Test Assistant**
   - Name: `Test Assistant - Table Placeholders`
   - Type: `report_generator`
   - Upload: `test-template-5-table.docx`

2. **Chat với Assistant**
   ```
   User: Tạo báo cáo tháng 11/2024
   User: Dự án 1: Dự án A, Trạng thái 1: Hoàn thành, Ghi chú 1: Đạt mục tiêu
   User: Dự án 2: Dự án B, Trạng thái 2: Đang thực hiện, Ghi chú 2: Tiến độ 80%
   User: Dự án 3: Dự án C, Trạng thái 3: Chưa bắt đầu, Ghi chú 3: Chờ phê duyệt
   ```

3. **Download và Verify DOCX File**

#### Expected Results:

**✅ PASS nếu:**
- [ ] Table format giữ nguyên (border, alignment, header)
- [ ] Tất cả placeholders trong table được điền đúng
- [ ] Table structure giữ nguyên

---

### Test Case 6: Q&A Assistant (Verify Không Bị Ảnh Hưởng)

#### Steps:

1. **Tạo Q&A Assistant**
   - Name: `Test Q&A Assistant`
   - Type: `qa_based_document`
   - Upload documents (PDF/DOCX)

2. **Chat với Assistant**
   ```
   User: Xin chào
   Assistant: [Chào lại]
   
   User: [Câu hỏi về nội dung trong documents]
   Assistant: [Trả lời dựa trên documents]
   ```

3. **Verify Assistant Trả Lời**
   - Check assistant trả lời đúng
   - Check KHÔNG có button "Tải DOCX"
   - Check KHÔNG có report được tạo

#### Expected Results:

**✅ PASS nếu:**
- [ ] Q&A assistant trả lời câu hỏi dựa trên documents
- [ ] KHÔNG có report được tạo (không có button download DOCX)
- [ ] KHÔNG có lỗi liên quan đến ReportGenerator
- [ ] Logs không có warning về "handleCreateReport called for non-report_generator"

---

### Test Case 7: Missing Data

#### Steps:

1. **Tạo Test Assistant**
   - Name: `Test Assistant - Missing Data`
   - Type: `report_generator`
   - Upload: `test-template-1-simple.docx`

2. **Chat với Assistant (CHỈ cung cấp một phần thông tin)**
   ```
   User: Tạo báo cáo tháng 11/2024
   User: Mục tiêu: Tăng trưởng doanh số 20%
   // KHÔNG cung cấp "Tình hình chung"
   ```

3. **Download và Verify DOCX File**

#### Expected Results:

**✅ PASS nếu:**
- [ ] DOCX file được tạo thành công
- [ ] Placeholders có data được điền đúng:
  - [ ] `[Tháng/Năm]` → `11/2024`
  - [ ] `[Mục tiêu]` → `Tăng trưởng doanh số 20%`
- [ ] Placeholders không có data:
  - [ ] `[Tình hình chung]` → để trống hoặc giữ nguyên placeholder
- [ ] Không có lỗi

---

## 📊 TEST RESULTS TEMPLATE

### Test Case: [Tên Test Case]

**Date:** [Ngày test]  
**Tester:** [Tên người test]  
**Status:** ✅ PASS / ❌ FAIL

**Steps:**
1. [Step 1]
2. [Step 2]
3. ...

**Results:**
- [ ] [Checklist item 1]
- [ ] [Checklist item 2]
- [ ] ...

**Screenshots:**
- [Link to screenshot 1]
- [Link to screenshot 2]

**Issues Found:**
- [Issue 1]
- [Issue 2]

**Notes:**
[Ghi chú thêm]

---

## 🔍 VERIFICATION METHODS

### 1. Visual Comparison

**Tool:** Microsoft Word hoặc LibreOffice Writer

**Steps:**
1. Mở template gốc
2. Mở báo cáo được tạo
3. So sánh side-by-side:
   - Format (font, size, color, bold, italic)
   - Alignment (left, center, right)
   - Structure (sections, headings)
   - Content (placeholders được điền đúng)

### 2. Log Analysis

**Check Laravel logs:**
```bash
tail -f storage/logs/laravel.log | grep "Report generated"
```

**Look for:**
- `Report generated successfully (direct template fill)`
- `Template placeholders replaced`
- `replaced` count vs `failed` count
- Any errors or warnings

### 3. Database Check

**Check UserReport table:**
```sql
SELECT * FROM user_reports ORDER BY created_at DESC LIMIT 10;
```

**Verify:**
- `report_file_path` không null
- `file_format` = 'docx'
- `report_content` có nội dung

---

## ✅ ACCEPTANCE CRITERIA

### Must Pass (P0)

- [ ] Test Case 1: Simple Placeholders ✅ PASS
- [ ] Test Case 2: Complex Placeholders ✅ PASS
- [ ] Test Case 6: Q&A Assistant ✅ PASS (không bị ảnh hưởng)

### Should Pass (P1)

- [ ] Test Case 3: Multiple Formats ✅ PASS
- [ ] Test Case 5: Table Placeholders ✅ PASS

### Nice to Have (P2)

- [ ] Test Case 4: No Placeholders ✅ PASS
- [ ] Test Case 7: Missing Data ✅ PASS

---

## 🐛 BUG REPORT TEMPLATE

### Bug: [Tên Bug]

**Test Case:** [Test Case số]  
**Severity:** Critical / High / Medium / Low  
**Priority:** P0 / P1 / P2

**Description:**
[Mô tả bug chi tiết]

**Steps to Reproduce:**
1. [Step 1]
2. [Step 2]
3. ...

**Expected Result:**
[Kết quả mong đợi]

**Actual Result:**
[Kết quả thực tế]

**Screenshots/Logs:**
[Link to screenshots/logs]

**Environment:**
- PHP Version: [version]
- Laravel Version: [version]
- OS: [OS]
- Browser: [Browser]

---

## 📈 METRICS TO TRACK

### Success Rate
- **Target:** 100% cho P0 test cases
- **Current:** [%]

### Performance
- **Target:** < 30 giây cho template lớn
- **Current:** [seconds]

### Placeholder Replacement Rate
- **Target:** 100% placeholders được điền (nếu có data)
- **Current:** [%]

### Format Preservation Rate
- **Target:** 100% format giữ nguyên
- **Current:** [%]

---

## 🚀 NEXT STEPS

Sau khi manual test xong:

1. **Nếu tất cả P0 test cases PASS:**
   - ✅ Deploy lên staging
   - ✅ Test lại trên staging
   - ✅ Deploy lên production

2. **Nếu có P0 test cases FAIL:**
   - ❌ Fix bugs
   - ❌ Test lại
   - ❌ Repeat until all P0 pass

3. **Nếu có P1/P2 test cases FAIL:**
   - ⚠️ Document issues
   - ⚠️ Prioritize fixes
   - ⚠️ Plan for next release

---

**Người tạo:** AI Assistant  
**Ngày tạo:** 7/11/2025  
**Status:** Ready for manual testing






