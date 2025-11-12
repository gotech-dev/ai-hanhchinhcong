# Frontend Test Guide - Manual Testing

## 🎯 Mục đích

Hướng dẫn test thủ công các tính năng frontend đã cải tiến.

---

## 📋 Preconditions

1. Đã cài đặt và chạy ứng dụng
2. Đã đăng nhập với tài khoản admin
3. Database đã có data (không refresh)

---

## 🧪 Test Cases

### TC-F-001: UI - Ẩn Steps Manager cho Q&A Assistant

**Steps:**
1. Truy cập: `http://localhost/admin/assistants/create`
2. Chọn "Assistant Type" = "Trả lời Q&A từ tài liệu"
3. Quan sát UI

**Expected:**
- ✅ Không thấy component "Steps Manager"
- ✅ Thấy thông báo màu xanh: "Lưu ý: Trợ lý Q&A không cần tạo steps"
- ✅ Thông báo có icon info

**Screenshot:** Chụp màn hình và lưu vào `tests/screenshots/TC-F-001.png`

---

### TC-F-002: UI - Hiển thị Steps Manager cho Document Drafting

**Steps:**
1. Truy cập: `http://localhost/admin/assistants/create`
2. Chọn "Assistant Type" = "Soạn thảo Văn bản Hành chính"
3. Nhập Name: "Trợ lý Viết Sách"
4. Nhập Description: "Hỗ trợ viết sách, cần research và bao quát hết case"
5. Quan sát UI

**Expected:**
- ✅ Thấy component "Steps Manager"
- ✅ Không thấy thông báo về Q&A

**Screenshot:** Chụp màn hình và lưu vào `tests/screenshots/TC-F-002.png`

---

### TC-F-003: Computed Property shouldShowStepsManager

**Test 1: Q&A Assistant**
- Type: `qa_based_document`
- Expected: Steps Manager ẩn

**Test 2: Document Management**
- Type: `document_management`
- Expected: Steps Manager ẩn

**Test 3: Document Drafting với workflow keywords**
- Type: `document_drafting`
- Name: "Trợ lý Research"
- Description: "Cần quy trình và bước nghiên cứu"
- Expected: Steps Manager hiển thị

**Steps:**
1. Mở Developer Console (F12)
2. Vào tab Console
3. Gõ: `window.$vm0.shouldShowStepsManager` (hoặc inspect component)
4. Kiểm tra giá trị

**Expected:**
- Test 1: `false`
- Test 2: `false`
- Test 3: `true`

---

### TC-F-004: Chat UI - Trả lời Câu hỏi Thông thường

**Preconditions:**
- Đã tạo Q&A assistant
- Đã mở chat với assistant

**Steps:**
1. Truy cập chat với Q&A assistant
2. Gửi message: "Hà Nội có bao nhiêu tỉnh?"
3. Quan sát response

**Expected:**
- ✅ Response hiển thị ngay (không loading quá 5 giây)
- ✅ Response trả lời trực tiếp: "Hà Nội hiện tại là một thành phố trực thuộc Trung ương..."
- ✅ Response KHÔNG có: "Để tôi có thể hỗ trợ quý anh/chị tốt nhất..."
- ✅ Response có format đẹp, dễ đọc

**Screenshot:** Chụp màn hình và lưu vào `tests/screenshots/TC-F-004.png`

---

### TC-F-005: Chat UI - Hiển thị Sources

**Preconditions:**
- Q&A assistant có documents HOẶC không có documents (để test web search)

**Test Case 1: Có Documents**
1. Upload document về "Hà Nội"
2. Gửi: "Hà Nội có bao nhiêu quận?"
3. Kiểm tra sources

**Expected:**
- ✅ Response có phần "Nguồn thông tin" hoặc "Sources"
- ✅ Hiển thị danh sách sources từ documents
- ✅ Sources có title, snippet

**Test Case 2: Không có Documents (Web Search)**
1. Tạo Q&A assistant KHÔNG có documents
2. Gửi: "Hà Nội có bao nhiêu tỉnh?"
3. Kiểm tra sources

**Expected:**
- ✅ Response có phần "Nguồn thông tin"
- ✅ Hiển thị sources từ Google Search (qua Gemini)
- ✅ Sources có title, snippet, url (nếu có)

**Screenshot:** Chụp màn hình và lưu vào `tests/screenshots/TC-F-005.png`

---

## 🔍 Browser Console Checks

### Check Vue Component State

1. Mở Developer Tools (F12)
2. Vào tab "Elements" hoặc "Components" (Vue DevTools)
3. Tìm component `CreateAssistant`
4. Kiểm tra:
   - `form.assistant_type`
   - `shouldShowStepsManager` (computed)

### Check Network Requests

1. Mở Developer Tools (F12)
2. Vào tab "Network"
3. Gửi message trong chat
4. Kiểm tra:
   - Request đến `/api/chat` hoặc `/chat`
   - Response có `sources` field
   - Response có `search_results` field (nếu web search)

### Check Console Logs

1. Mở Developer Tools (F12)
2. Vào tab "Console"
3. Kiểm tra:
   - Không có errors
   - Có logs về "searching web with Gemini" (nếu web search)

---

## 📸 Screenshots

Lưu tất cả screenshots vào thư mục: `tests/screenshots/`

- `TC-F-001-qa-assistant-no-steps.png`
- `TC-F-002-document-drafting-with-steps.png`
- `TC-F-003-computed-property.png`
- `TC-F-004-chat-general-question.png`
- `TC-F-005-chat-sources.png`

---

## 🐛 Bug Reporting

Nếu phát hiện bug, ghi lại:

1. **Bug Description:** Mô tả chi tiết
2. **Steps to Reproduce:** Các bước để reproduce
3. **Expected Behavior:** Hành vi mong đợi
4. **Actual Behavior:** Hành vi thực tế
5. **Screenshot/Video:** Chụp màn hình hoặc quay video
6. **Browser/OS:** Chrome 120 / macOS 14.5
7. **Console Errors:** Copy errors từ console

---

## ✅ Test Checklist

- [ ] TC-F-001: Q&A Assistant - Ẩn Steps Manager
- [ ] TC-F-002: Document Drafting - Hiển thị Steps Manager
- [ ] TC-F-003: Computed Property hoạt động đúng
- [ ] TC-F-004: Chat trả lời câu hỏi thông thường
- [ ] TC-F-005: Chat hiển thị sources
- [ ] Screenshots đã chụp
- [ ] Console không có errors
- [ ] Network requests thành công

---

*Tài liệu này hướng dẫn test thủ công các tính năng frontend.*


