# BÁO CÁO VẤN ĐỀ: Steps Không Truyền Biến Từ Step Trước

## 🔴 VẤN ĐỀ PHÁT HIỆN

### 1. **Step 2 - Mô tả sai**
- **Hiện tại**: Step 2 có tên "Lập dàn ý" nhưng mô tả lại là "Viết nội dung cho chương đầu tiên của cuốn sách"
- **Vấn đề**: Mô tả không khớp với tên step, gây nhầm lẫn
- **Nguyên nhân**: Có thể do AI tự động tạo steps không chính xác

### 2. **Step 2 - Không truyền biến từ Step 1** ⚠️ **QUAN TRỌNG**

#### Vấn đề:
- Step 1 (collect_info) thu thập thông tin và lưu vào `collected_data` với keys như `answer_1`, `answer_2`, `answer_3`, etc.
- Step 2 (generate) khi thực thi:
  - Lấy `prompt_template` từ `config['prompt_template']` hoặc fallback về `description`
  - Gọi `buildPromptFromTemplate()` để thay thế placeholders
  - **NHƯNG**: Nếu `prompt_template` không có placeholders `{answer_1}`, `{answer_2}`, etc., thì dữ liệu từ Step 1 sẽ KHÔNG được sử dụng

#### Code hiện tại:

**SmartAssistantEngine.php - executeGenerateStep()**:
```php
protected function executeGenerateStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant): array
{
    $config = $step['config'] ?? [];
    $promptTemplate = $config['prompt_template'] ?? $step['description'] ?? '';
    
    // Build prompt từ template và collected data
    $prompt = $this->buildPromptFromTemplate($promptTemplate, $collectedData, $userMessage);
    // ...
}
```

**buildPromptFromTemplate()**:
```php
protected function buildPromptFromTemplate(string $template, array $data, string $userMessage = ''): string
{
    // Thay thế placeholders trong template
    $prompt = $template;
    foreach ($data as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $prompt = str_replace('{' . $key . '}', (string)$value, $prompt);
        }
    }
    // ...
}
```

#### Vấn đề cụ thể:

1. **buildStepsGenerationPrompt() không hướng dẫn AI tạo prompt_template với variables**:
   - Prompt hiện tại chỉ yêu cầu tạo steps với `config: {}`
   - Không có hướng dẫn để tạo `prompt_template` với placeholders như `{answer_1}`, `{answer_2}`, etc.

2. **Step 2 không có prompt_template**:
   - Nếu AI không tạo `prompt_template`, code sẽ fallback về `description`
   - `description` thường chỉ là text thuần, không có placeholders
   - → Dữ liệu từ Step 1 không được sử dụng

3. **Keys không khớp**:
   - Step 1 lưu data với keys: `answer_1`, `answer_2`, `answer_3`
   - Step 2 cần reference: `{answer_1}`, `{answer_2}`, `{answer_3}`
   - Nếu prompt_template không có các placeholders này, data sẽ bị bỏ qua

## 📋 VÍ DỤ CỤ THỂ

### Tình huống hiện tại (SAI):

**Step 1** (collect_info):
```json
{
  "id": "step_1",
  "type": "collect_info",
  "config": {
    "questions": [
      "Tiêu đề cuốn sách là gì?",
      "Mục đích viết sách là gì?",
      "Đối tượng đọc giả là ai?"
    ]
  }
}
```

→ Thu thập và lưu: `{"answer_1": "Sách về AI", "answer_2": "Giáo dục", "answer_3": "Sinh viên"}`

**Step 2** (generate) - HIỆN TẠI:
```json
{
  "id": "step_2",
  "type": "generate",
  "description": "Tạo dàn ý chi tiết cho cuốn sách",
  "config": {}  // ❌ KHÔNG CÓ prompt_template
}
```

→ Khi thực thi:
- `promptTemplate = ""` (vì config không có prompt_template)
- Fallback về `description = "Tạo dàn ý chi tiết cho cuốn sách"`
- `buildPromptFromTemplate()` không tìm thấy `{answer_1}`, `{answer_2}`, etc.
- → **Dữ liệu từ Step 1 KHÔNG được sử dụng!**

### Tình huống mong muốn (ĐÚNG):

**Step 2** (generate) - NÊN CÓ:
```json
{
  "id": "step_2",
  "type": "generate",
  "description": "Tạo dàn ý chi tiết cho cuốn sách",
  "config": {
    "prompt_template": "Dựa trên thông tin đã thu thập:\n- Tiêu đề: {answer_1}\n- Mục đích: {answer_2}\n- Đối tượng: {answer_3}\n\nHãy tạo dàn ý chi tiết cho cuốn sách này."
  }
}
```

→ Khi thực thi:
- `promptTemplate = "Dựa trên thông tin đã thu thập:\n- Tiêu đề: {answer_1}\n..."`
- `buildPromptFromTemplate()` thay thế:
  - `{answer_1}` → "Sách về AI"
  - `{answer_2}` → "Giáo dục"
  - `{answer_3}` → "Sinh viên"
- → **Dữ liệu từ Step 1 ĐƯỢC SỬ DỤNG!**

## ✅ GIẢI PHÁP

### 1. Cải thiện `buildStepsGenerationPrompt()`
- Thêm hướng dẫn rõ ràng cho AI về cách tạo `prompt_template` với variables
- Yêu cầu AI reference các biến từ step trước (ví dụ: `{answer_1}`, `{answer_2}`, etc.)

### 2. Cải thiện `executeGenerateStep()`
- Nếu không có `prompt_template`, tự động build một prompt mặc định bao gồm collected data
- Hoặc cảnh báo/log khi không có prompt_template

### 3. Cải thiện `buildPromptFromTemplate()`
- Thêm logic để tự động include collected data vào prompt nếu template không có placeholders
- Hoặc log warning khi có collected data nhưng không được sử dụng

### 4. Validation khi tạo steps
- Kiểm tra nếu step có `dependencies`, đảm bảo `prompt_template` reference các biến từ step trước

## 🔧 FILES CẦN SỬA

1. `app/Http/Controllers/AdminController.php`
   - Method `buildStepsGenerationPrompt()`: Thêm hướng dẫn tạo prompt_template với variables

2. `app/Services/SmartAssistantEngine.php`
   - Method `executeGenerateStep()`: Cải thiện xử lý khi không có prompt_template
   - Method `buildPromptFromTemplate()`: Tự động include collected data nếu cần

## 📊 KẾT LUẬN

**Vấn đề chính**: Step 2 (và các generate steps khác) không truyền biến từ Step 1 vì:
1. AI không được hướng dẫn tạo `prompt_template` với placeholders
2. Code không có fallback để tự động include collected data
3. Không có validation để đảm bảo prompt_template reference đúng variables

**Mức độ nghiêm trọng**: ⚠️ **CAO** - Steps không hoạt động đúng như mong đợi, dữ liệu từ step trước bị bỏ qua.

---

## ✅ CÁC THAY ĐỔI ĐÃ THỰC HIỆN

### 1. Cải thiện `buildStepsGenerationPrompt()` 
**File**: `app/Http/Controllers/AdminController.php`

- ✅ Thêm hướng dẫn chi tiết cho AI về cách tạo `prompt_template` với placeholders
- ✅ Yêu cầu AI reference các biến từ step trước (ví dụ: `{answer_1}`, `{answer_2}`, etc.)
- ✅ Thêm ví dụ cụ thể về format của `prompt_template`

### 2. Cải thiện `executeGenerateStep()`
**File**: `app/Services/SmartAssistantEngine.php`

- ✅ Thêm logic tự động build prompt từ collected data nếu không có `prompt_template`
- ✅ Thêm logging để cảnh báo khi thiếu `prompt_template` nhưng có collected data
- ✅ Gọi method mới `buildDefaultPromptFromCollectedData()` để tự động tạo prompt

### 3. Cải thiện `buildPromptFromTemplate()`
**File**: `app/Services/SmartAssistantEngine.php`

- ✅ Tự động append collected data vào prompt nếu template không có placeholders
- ✅ Filter out internal keys (bắt đầu bằng `_`)
- ✅ Đảm bảo collected data luôn được sử dụng, kể cả khi template không có placeholders

### 4. Thêm method mới `buildDefaultPromptFromCollectedData()`
**File**: `app/Services/SmartAssistantEngine.php`

- ✅ Tự động tạo prompt từ collected data khi `prompt_template` bị thiếu
- ✅ Format dữ liệu một cách rõ ràng và dễ đọc
- ✅ Đảm bảo AI luôn có context đầy đủ để thực hiện nhiệm vụ

---

## 🎯 KẾT QUẢ

Sau các thay đổi:

1. **AI sẽ được hướng dẫn tốt hơn** khi tạo steps, đảm bảo generate steps có `prompt_template` với placeholders
2. **Code có fallback an toàn**: Ngay cả khi AI không tạo `prompt_template`, code sẽ tự động include collected data
3. **Dữ liệu từ Step 1 luôn được sử dụng** trong Step 2, dù có hoặc không có `prompt_template`

**Lưu ý**: Các steps đã tạo trước đó vẫn có thể gặp vấn đề nếu không có `prompt_template`. Tuy nhiên, với fallback mới, chúng vẫn sẽ hoạt động (mặc dù không tối ưu bằng việc có `prompt_template` đúng cách).

