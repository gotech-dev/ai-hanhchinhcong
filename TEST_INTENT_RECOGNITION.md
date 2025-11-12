# Test Intent Recognition - Phase 4

## Mục đích

Test Intent Recognition với các câu hỏi khác nhau để verify accuracy sau khi cải thiện system prompt.

## Test Cases

### Test 1: General Questions → ask_question

**Mục tiêu:** Verify rằng các câu hỏi thông thường được nhận diện là `ask_question`

**Test Cases:**
1. "Hà Nội có bao nhiêu tỉnh?" → `ask_question`
2. "Việt Nam có bao nhiêu tỉnh thành?" → `ask_question`
3. "Công văn là gì?" → `ask_question`
4. "GDP là gì?" → `ask_question`
5. "Bạn làm được gì?" → `ask_question`
6. "Cách sử dụng hệ thống?" → `ask_question`

**Expected:** Tất cả đều trả về `ask_question` với confidence > 0.5

---

### Test 2: Workflow Requests → draft_document/create_report

**Mục tiêu:** Verify rằng các yêu cầu workflow được nhận diện đúng (KHÔNG phải `ask_question`)

**Test Cases:**
1. "Tôi muốn soạn thảo công văn" → `draft_document`
2. "Giúp tôi tạo quyết định" → `draft_document`
3. "Soạn thảo tờ trình" → `draft_document`
4. "Làm biên bản" → `draft_document`
5. "Tạo báo cáo" → `create_report` hoặc `draft_document`

**Expected:** Tất cả đều trả về `draft_document` hoặc `create_report` (KHÔNG phải `ask_question`) với confidence > 0.5

---

### Test 3: Distinguish General Question vs Workflow Request

**Mục tiêu:** Verify rằng system có thể phân biệt rõ ràng giữa câu hỏi thông thường và yêu cầu workflow

**Test Cases:**

**General Questions:**
1. "Công văn là gì?" → `ask_question`
2. "Bạn làm được gì?" → `ask_question`
3. "Hà Nội có bao nhiêu tỉnh?" → `ask_question`

**Workflow Requests:**
1. "Tôi muốn soạn thảo công văn" → `draft_document`
2. "Giúp tôi tạo quyết định" → `draft_document`
3. "Soạn thảo tờ trình" → `draft_document`

**Expected:** Phân biệt chính xác 100%

---

## Cách chạy test

### Option 1: Manual Test Script

```bash
php artisan tinker
```

Sau đó:
```php
require 'tests/IntentRecognitionManualTest.php';
runTestCases();
```

### Option 2: Unit Tests (nếu database đã setup)

```bash
php artisan test --filter=IntentRecognizerTest
```

---

## Expected Results

- **Test 1:** 100% accuracy (6/6 passed)
- **Test 2:** 100% accuracy (5/5 passed)
- **Test 3:** 100% accuracy (6/6 passed)
- **Overall:** > 90% accuracy

---

## Notes

- Tests này gọi OpenAI API thực tế, nên cần có `OPENAI_API_KEY` trong `.env`
- Mỗi test case mất ~2-3 giây để gọi API
- Nếu có lỗi API, sẽ hiển thị warning và tiếp tục test case tiếp theo

---

## Success Criteria

✅ **Pass nếu:**
- Test 1: ≥ 5/6 passed (83%)
- Test 2: ≥ 4/5 passed (80%)
- Test 3: ≥ 5/6 passed (83%)
- Overall accuracy ≥ 85%

---

*Created for Phase 4: Cải thiện Intent Recognition*


