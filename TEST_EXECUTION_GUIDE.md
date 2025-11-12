# Test Execution Guide

## 📋 Tổng Quan

Hướng dẫn chạy tests cho các tính năng cải tiến chatbot.

**⚠️ QUAN TRỌNG: Database KHÔNG được refresh - sử dụng data có sẵn**

---

## 🚀 Quick Start

### 1. Chạy Backend Tests

```bash
# Chạy tất cả tests
./run_tests.sh

# Hoặc chạy trực tiếp
php artisan test --filter=ChatbotImprovementTest

# Chạy test cụ thể
php artisan test --filter=test_create_qa_assistant_without_steps
```

### 2. Chạy Frontend Tests (Manual)

Xem hướng dẫn chi tiết tại: `tests/Manual/FrontendTestGuide.md`

---

## 📝 Test Files

### Backend Tests
- **File:** `tests/Feature/ChatbotImprovementTest.php`
- **Test Cases:** 8 test cases
- **Database:** Sử dụng transaction để rollback (không refresh)

### Frontend Tests
- **File:** `tests/Manual/FrontendTestGuide.md`
- **Test Cases:** 5 test cases
- **Type:** Manual testing

### Test Cases Document
- **File:** `TEST_CASES_CAI_TIEN_CHATBOT.md`
- **Total:** 18 test cases (11 backend + 5 frontend + 2 integration)

---

## 🔧 Setup

### Preconditions

1. **Database:**
   ```bash
   # Đảm bảo database đã có data
   php artisan db:show
   ```

2. **Environment:**
   ```bash
   # Kiểm tra .env có đủ config
   - OPENAI_API_KEY
   - GOOGLE_AI_API_KEY (optional, cho Gemini)
   - DB_CONNECTION, DB_DATABASE, etc.
   ```

3. **Dependencies:**
   ```bash
   composer install
   npm install
   ```

---

## 🧪 Running Tests

### Backend Tests

#### Option 1: Chạy tất cả
```bash
./run_tests.sh
```

#### Option 2: Chạy từng test
```bash
# Test tạo Q&A assistant
php artisan test --filter=test_create_qa_assistant_without_steps

# Test web search
php artisan test --filter=test_qa_assistant_searches_web_when_no_documents

# Test system prompt
php artisan test --filter=test_system_prompt_with_assistant_type_context
```

#### Option 3: Chạy với coverage
```bash
php artisan test --coverage --filter=ChatbotImprovementTest
```

### Frontend Tests

1. **Start development server:**
   ```bash
   php artisan serve
   npm run dev
   ```

2. **Follow guide:**
   - Mở: `tests/Manual/FrontendTestGuide.md`
   - Thực hiện từng test case
   - Chụp screenshots và ghi lại kết quả

---

## 📊 Test Results

### Expected Results

#### Backend Tests
- ✅ TC-001: Q&A Assistant không có steps
- ✅ TC-002: Document Drafting tự động tạo steps
- ✅ TC-003: Q&A trả lời từ documents
- ✅ TC-004: Q&A tìm kiếm trên mạng
- ✅ TC-005: Nhận diện câu hỏi thông thường
- ✅ TC-006: Không trigger steps cho câu hỏi thông thường
- ✅ TC-007: Trigger steps cho yêu cầu cụ thể
- ✅ TC-008: System prompt với context

#### Frontend Tests
- ✅ TC-F-001: Ẩn Steps Manager cho Q&A
- ✅ TC-F-002: Hiển thị Steps Manager cho Document Drafting
- ✅ TC-F-003: Computed property hoạt động đúng
- ✅ TC-F-004: Chat trả lời câu hỏi thông thường
- ✅ TC-F-005: Chat hiển thị sources

---

## 🐛 Troubleshooting

### Database Issues

**Problem:** Tests fail với database errors

**Solution:**
```bash
# Kiểm tra database connection
php artisan db:show

# Kiểm tra migrations
php artisan migrate:status

# Nếu cần, chạy migrations (KHÔNG refresh)
php artisan migrate
```

### Mock Issues

**Problem:** Tests fail vì mock không hoạt động

**Solution:**
- Kiểm tra Mockery đã được install: `composer require mockery/mockery`
- Kiểm tra mock setup trong test
- Xem logs: `storage/logs/laravel.log`

### API Key Issues

**Problem:** Tests fail vì thiếu API keys

**Solution:**
- Kiểm tra `.env` có `OPENAI_API_KEY`
- Cho Gemini tests, cần `GOOGLE_AI_API_KEY`
- Hoặc mock API calls trong tests

---

## 📸 Screenshots

Lưu screenshots vào: `tests/screenshots/`

- `TC-F-001-qa-assistant-no-steps.png`
- `TC-F-002-document-drafting-with-steps.png`
- `TC-F-003-computed-property.png`
- `TC-F-004-chat-general-question.png`
- `TC-F-005-chat-sources.png`

---

## 📝 Test Report Template

Sau khi chạy tests, điền vào `TEST_CASES_CAI_TIEN_CHATBOT.md`:

1. Đánh dấu Pass/Fail cho từng test case
2. Ghi Notes nếu có
3. Ghi Bugs Found
4. Tính Pass Rate

---

## ✅ Checklist

Trước khi chạy tests:
- [ ] Database đã có data
- [ ] .env đã cấu hình đúng
- [ ] Dependencies đã install
- [ ] Development server đã chạy (cho frontend tests)

Sau khi chạy tests:
- [ ] Tất cả backend tests pass
- [ ] Tất cả frontend tests pass
- [ ] Screenshots đã chụp
- [ ] Test report đã điền
- [ ] Bugs đã được ghi lại

---

## 🎯 Next Steps

Sau khi tests pass:
1. Review test results
2. Fix bugs nếu có
3. Update documentation
4. Deploy to staging
5. Run tests trên staging environment

---

*Tài liệu này hướng dẫn chạy tests cho các tính năng cải tiến chatbot.*


