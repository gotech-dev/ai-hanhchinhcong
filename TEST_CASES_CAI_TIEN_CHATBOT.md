# Test Cases - Cải Tiến Chatbot

## 📋 Tổng Quan

Test cases cho các tính năng đã cải tiến:
- Phase 0: Tự động phân loại khi tạo Assistant
- Phase 1: Cải thiện nhận diện câu hỏi thông thường
- Phase 2: Cải thiện System Prompt
- Phase 3: Cải thiện xử lý Steps
- Gemini Web Search Integration

---

## 🧪 Test Cases Backend

### TC-001: Tạo Q&A Assistant - Không có Steps

**Mục đích:** Kiểm tra Q&A assistant không tự động tạo steps

**Preconditions:**
- Admin đã đăng nhập
- Có quyền tạo assistant

**Steps:**
1. Tạo assistant mới với:
   - Name: "Trợ lý Q&A Test"
   - Type: `qa_based_document`
   - Description: "Trả lời câu hỏi từ tài liệu"

**Expected Results:**
- Assistant được tạo thành công
- `shouldAssistantHaveSteps()` trả về `false`
- `steps` field là `[]` hoặc `null`
- Không có log về auto-generate steps

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-002: Tạo Document Drafting Assistant - Tự động tạo Steps

**Mục đích:** Kiểm tra assistant "Viết sách" tự động tạo steps

**Preconditions:**
- Admin đã đăng nhập
- OpenAI API key đã cấu hình

**Steps:**
1. Tạo assistant mới với:
   - Name: "Trợ lý Viết Sách"
   - Type: `document_drafting`
   - Description: "Hỗ trợ viết sách, cần research và bao quát hết case"

**Expected Results:**
- Assistant được tạo thành công
- `shouldAssistantHaveSteps()` trả về `true`
- `shouldAutoGenerateSteps()` trả về `true`
- `steps` field có ít nhất 2-3 steps được tự động tạo
- Log có thông tin về auto-generate steps

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-003: Q&A Assistant - Trả lời từ Documents

**Mục đích:** Kiểm tra Q&A assistant trả lời dựa trên documents đã upload

**Preconditions:**
- Q&A assistant đã được tạo
- Assistant có ít nhất 1 document đã được index
- Document chứa thông tin về "Hà Nội"

**Steps:**
1. Gửi message: "Hà Nội có bao nhiêu quận?"
2. Kiểm tra response

**Expected Results:**
- `handleAskQuestion()` được gọi
- `vectorSearchService->searchSimilar()` được gọi
- Response chứa thông tin từ documents
- `sources` field có ít nhất 1 source từ documents
- Không gọi Gemini web search

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-004: Q&A Assistant - Tìm kiếm trên mạng khi không có Documents

**Mục đích:** Kiểm tra Q&A assistant tìm kiếm trên mạng khi không có documents

**Preconditions:**
- Q&A assistant đã được tạo
- Assistant KHÔNG có documents hoặc documents chưa được index
- Gemini API key đã cấu hình

**Steps:**
1. Gửi message: "Hà Nội có bao nhiêu tỉnh?"
2. Kiểm tra response

**Expected Results:**
- `handleAskQuestion()` được gọi
- `vectorSearchService->searchSimilar()` trả về empty hoặc không được gọi
- `geminiWebSearchService->searchAndAnswer()` được gọi
- Response chứa thông tin từ Gemini web search
- `sources` field có ít nhất 1 source từ web search
- Log có thông tin "searching web with Gemini"

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-005: Nhận diện Câu hỏi Thông thường - AI Detection

**Mục đích:** Kiểm tra `isGeneralQuestion()` nhận diện đúng câu hỏi thông thường

**Preconditions:**
- Assistant đã được tạo
- OpenAI API key đã cấu hình

**Test Cases:**

#### TC-005-1: Câu hỏi về số lượng
- Input: "Hà Nội có bao nhiêu tỉnh?"
- Expected: `isGeneralQuestion()` trả về `true`
- Confidence >= 0.7

#### TC-005-2: Câu hỏi về định nghĩa
- Input: "Công văn là gì?"
- Expected: `isGeneralQuestion()` trả về `true`
- Confidence >= 0.7

#### TC-005-3: Yêu cầu tạo văn bản
- Input: "Tôi muốn soạn thảo công văn"
- Expected: `isGeneralQuestion()` trả về `false`

#### TC-005-4: Yêu cầu tạo báo cáo
- Input: "Tôi muốn tạo báo cáo thường niên"
- Expected: `isGeneralQuestion()` trả về `false`

**Actual Results:**
- [ ] TC-005-1: Pass / Fail
- [ ] TC-005-2: Pass / Fail
- [ ] TC-005-3: Pass / Fail
- [ ] TC-005-4: Pass / Fail
- [ ] Notes: _______________

---

### TC-006: Không Trigger Steps cho Câu hỏi Thông thường

**Mục đích:** Kiểm tra steps không được trigger khi là câu hỏi thông thường

**Preconditions:**
- Assistant có predefined steps
- Assistant type không phải `qa_based_document`

**Steps:**
1. Gửi message: "Hà Nội có bao nhiêu tỉnh?"
2. Kiểm tra workflow state

**Expected Results:**
- `isGeneralQuestion()` trả về `true`
- `shouldExecuteSteps` = `false`
- `executePredefinedSteps()` KHÔNG được gọi
- `handleGenericRequest()` được gọi
- Response trả lời trực tiếp câu hỏi
- `workflow_state` không thay đổi

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-007: Trigger Steps cho Yêu cầu Cụ thể

**Mục đích:** Kiểm tra steps được trigger khi có yêu cầu cụ thể

**Preconditions:**
- Assistant có predefined steps
- Assistant type = `document_drafting`

**Steps:**
1. Gửi message: "Tôi muốn soạn thảo công văn"
2. Kiểm tra workflow state

**Expected Results:**
- `isGeneralQuestion()` trả về `false`
- `requiresWorkflow` = `true` (intent = `draft_document`)
- `shouldExecuteSteps` = `true`
- `executePredefinedSteps()` được gọi
- Workflow bắt đầu
- `workflow_state` có `current_step_index` = 0

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-008: System Prompt với Context theo Loại Assistant

**Mục đích:** Kiểm tra system prompt có context đúng theo loại assistant

**Preconditions:**
- Các loại assistant đã được tạo

**Test Cases:**

#### TC-008-1: Q&A Assistant
- Assistant type: `qa_based_document`
- Expected: System prompt chứa "Trả lời câu hỏi dựa trên tài liệu", "Tìm kiếm trên mạng"

#### TC-008-2: Document Drafting
- Assistant type: `document_drafting`
- Expected: System prompt chứa "Soạn thảo văn bản hành chính", "Công văn, Quyết định"

#### TC-008-3: Document Management
- Assistant type: `document_management`
- Expected: System prompt chứa "Quản lý văn bản", "Phân loại văn bản"

**Actual Results:**
- [ ] TC-008-1: Pass / Fail
- [ ] TC-008-2: Pass / Fail
- [ ] TC-008-3: Pass / Fail
- [ ] Notes: _______________

---

### TC-009: Build Chat Messages với Context

**Mục đích:** Kiểm tra `buildChatMessages()` truyền context đầy đủ

**Preconditions:**
- Session có workflow_state
- Session có collected_data

**Steps:**
1. Gọi `buildChatMessages()` với session có workflow
2. Kiểm tra system prompt

**Expected Results:**
- System prompt chứa "TRẠNG THÁI HIỆN TẠI"
- System prompt chứa tên bước hiện tại
- System prompt chứa số lượng collected data
- Messages có đúng format

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-010: Execute Collect Info Step - Fallback cho Câu hỏi Thông thường

**Mục đích:** Kiểm tra `executeCollectInfoStep()` fallback khi là câu hỏi thông thường

**Preconditions:**
- Step có type = `collect_info`
- Step không có questions và fields

**Steps:**
1. Gọi `executeCollectInfoStep()` với message: "Hà Nội có bao nhiêu tỉnh?"
2. Kiểm tra response

**Expected Results:**
- `isGeneralQuestion()` trả về `true`
- Response có `should_fallback` = `true`
- `executePredefinedSteps()` gọi `handleGenericRequest()`
- Response trả lời trực tiếp câu hỏi

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-011: Error Handling trong Steps

**Mục đích:** Kiểm tra error handling trong các steps

**Test Cases:**

#### TC-011-1: Generate Step Error
- Mock OpenAI API throw exception
- Expected: Response lịch sự, có log error

#### TC-011-2: Search Step - Empty Query
- Search query = ""
- Expected: Response yêu cầu từ khóa, không throw exception

#### TC-011-3: Search Step Error
- Mock VectorSearchService throw exception
- Expected: Response lịch sự, có log error

**Actual Results:**
- [ ] TC-011-1: Pass / Fail
- [ ] TC-011-2: Pass / Fail
- [ ] TC-011-3: Pass / Fail
- [ ] Notes: _______________

---

## 🎨 Test Cases Frontend

### TC-F-001: UI - Ẩn Steps Manager cho Q&A Assistant

**Mục đích:** Kiểm tra Steps Manager bị ẩn khi chọn Q&A assistant

**Preconditions:**
- Đã đăng nhập admin
- Truy cập trang Create Assistant

**Steps:**
1. Chọn Assistant Type = "Trả lời Q&A từ tài liệu"
2. Kiểm tra UI

**Expected Results:**
- `AssistantStepsManager` component KHÔNG hiển thị
- Hiển thị thông báo: "Lưu ý: Trợ lý Q&A không cần tạo steps"
- Thông báo có màu xanh (bg-blue-50)

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-F-002: UI - Hiển thị Steps Manager cho Document Drafting

**Mục đích:** Kiểm tra Steps Manager hiển thị cho Document Drafting

**Preconditions:**
- Đã đăng nhập admin
- Truy cập trang Create Assistant

**Steps:**
1. Chọn Assistant Type = "Soạn thảo Văn bản Hành chính"
2. Nhập Name: "Trợ lý Viết Sách"
3. Nhập Description: "Hỗ trợ viết sách, cần research và bao quát hết case"
4. Kiểm tra UI

**Expected Results:**
- `AssistantStepsManager` component HIỂN THỊ
- Không có thông báo về Q&A

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-F-003: UI - Computed Property shouldShowStepsManager

**Mục đích:** Kiểm tra computed property hoạt động đúng

**Test Cases:**

#### TC-F-003-1: Q&A Assistant
- Type: `qa_based_document`
- Expected: `shouldShowStepsManager` = `false`

#### TC-F-003-2: Document Management
- Type: `document_management`
- Expected: `shouldShowStepsManager` = `false`

#### TC-F-003-3: Document Drafting với workflow keywords
- Type: `document_drafting`
- Name/Description chứa: "bước", "quy trình", "research"
- Expected: `shouldShowStepsManager` = `true`

#### TC-F-003-4: Document Drafting không có workflow keywords
- Type: `document_drafting`
- Name/Description không chứa workflow keywords
- Expected: `shouldShowStepsManager` = `false` (hoặc `true` tùy logic)

**Actual Results:**
- [ ] TC-F-003-1: Pass / Fail
- [ ] TC-F-003-2: Pass / Fail
- [ ] TC-F-003-3: Pass / Fail
- [ ] TC-F-003-4: Pass / Fail
- [ ] Notes: _______________

---

### TC-F-004: Chat UI - Trả lời Câu hỏi Thông thường

**Mục đích:** Kiểm tra chatbot trả lời trực tiếp câu hỏi thông thường

**Preconditions:**
- Đã tạo Q&A assistant
- Đã mở chat với assistant

**Steps:**
1. Gửi message: "Hà Nội có bao nhiêu tỉnh?"
2. Kiểm tra response

**Expected Results:**
- Response hiển thị ngay (không loading quá lâu)
- Response trả lời trực tiếp: "Hà Nội hiện tại là một thành phố trực thuộc Trung ương..."
- Response KHÔNG hỏi lại: "Để tôi có thể hỗ trợ..."
- Response có format đẹp, dễ đọc

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-F-005: Chat UI - Hiển thị Sources

**Mục đích:** Kiểm tra hiển thị sources từ documents hoặc web search

**Preconditions:**
- Q&A assistant có documents hoặc không có documents
- Đã mở chat với assistant

**Steps:**
1. Gửi message: "Hà Nội có bao nhiêu quận?"
2. Kiểm tra response và sources

**Expected Results:**
- Response có phần "Nguồn thông tin" hoặc "Sources"
- Hiển thị danh sách sources (nếu có)
- Sources có title, snippet, url (nếu có)
- Sources có thể click (nếu có url)

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

## 🔄 Integration Tests

### TC-I-001: End-to-End - Tạo Q&A Assistant và Test Chat

**Mục đích:** Test toàn bộ flow từ tạo assistant đến chat

**Steps:**
1. Tạo Q&A assistant (không có steps)
2. Upload document (optional)
3. Mở chat với assistant
4. Gửi câu hỏi: "Hà Nội có bao nhiêu tỉnh?"
5. Kiểm tra response

**Expected Results:**
- Assistant được tạo thành công
- UI không hiển thị Steps Manager
- Chat response trả lời trực tiếp
- Response có sources (nếu có documents hoặc web search)

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

### TC-I-002: End-to-End - Tạo Document Drafting với Steps

**Mục đích:** Test toàn bộ flow với steps

**Steps:**
1. Tạo Document Drafting assistant với description có "research"
2. Kiểm tra steps được tự động tạo
3. Mở chat với assistant
4. Gửi: "Tôi muốn soạn thảo công văn"
5. Kiểm tra workflow bắt đầu

**Expected Results:**
- Assistant được tạo với steps
- UI hiển thị Steps Manager
- Chat bắt đầu workflow
- Response hỏi thông tin cần thiết

**Actual Results:**
- [ ] Pass
- [ ] Fail
- [ ] Notes: _______________

---

## 📊 Test Summary

### Backend Tests
- Total: 11 test cases
- Passed: ___
- Failed: ___
- Skipped: ___

### Frontend Tests
- Total: 5 test cases
- Passed: ___
- Failed: ___
- Skipped: ___

### Integration Tests
- Total: 2 test cases
- Passed: ___
- Failed: ___
- Skipped: ___

### Overall
- Total: 18 test cases
- Pass Rate: ___%

---

## 🐛 Bugs Found

1. **Bug #1:**
   - Description: _______________
   - Severity: High / Medium / Low
   - Status: Open / Fixed / Won't Fix

2. **Bug #2:**
   - Description: _______________
   - Severity: High / Medium / Low
   - Status: Open / Fixed / Won't Fix

---

## 📝 Notes

- Database không được refresh - sử dụng data có sẵn
- Test với transactions để rollback nếu cần
- Logs được ghi tại `storage/logs/laravel.log`

---

*Tài liệu này được tạo để test các tính năng cải tiến chatbot.*


