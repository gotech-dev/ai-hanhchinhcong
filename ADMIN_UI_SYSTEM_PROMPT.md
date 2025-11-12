# HƯỚNG DẪN: Tùy chỉnh System Prompt trong Admin UI

## ✅ ĐÃ THÊM VÀO ADMIN UI

### 1. Form Tạo/Sửa Loại Trợ Lý

**Vị trí:** `/admin/assistant-types/create` và `/admin/assistant-types/{id}/edit`

**Field mới đã thêm:**
- **System Prompt** (Textarea, 12 rows)
  - Tùy chọn
  - Có thể dùng placeholders: `{name}` và `{description}`
  - Có preview real-time khi nhập
  - Font monospace để dễ đọc

**Tính năng:**
- ✅ Preview prompt với placeholders đã được replace
- ✅ Hiển thị ngay khi nhập tên và prompt
- ✅ Hướng dẫn sử dụng placeholders

### 2. Backend đã cập nhật

**File:** `app/Http/Controllers/AdminController.php`
- ✅ `storeAssistantType()` - Accept `system_prompt` và `system_prompt_template`
- ✅ `updateAssistantType()` - Accept `system_prompt` và `system_prompt_template`

## 📝 CÁCH SỬ DỤNG

### Bước 1: Vào Admin → Loại Trợ Lý

1. Truy cập: `/admin/assistant-types`
2. Click "Tạo Loại Trợ Lý Mới" hoặc "Sửa" một loại có sẵn

### Bước 2: Nhập System Prompt

Trong form, bạn sẽ thấy field **"System Prompt (Tùy chọn)"**:

```
System Prompt (Tùy chọn)
┌─────────────────────────────────────────┐
│ Bạn là {name}, một trợ lý AI...         │
│                                          │
│ {description}                            │
│                                          │
│ **CHỨC NĂNG CHÍNH:**                    │
│ - ...                                    │
└─────────────────────────────────────────┘

Có thể dùng placeholders: {name} và {description}

Preview (với tên mẫu: "Trả lời Q&A từ tài liệu"):
┌─────────────────────────────────────────┐
│ Bạn là Trả lời Q&A từ tài liệu, một...  │
└─────────────────────────────────────────┘
```

### Bước 3: Sử dụng Placeholders

- `{name}` - Sẽ được thay bằng tên assistant
- `{description}` - Sẽ được thay bằng mô tả assistant

**Ví dụ:**
```
Input:
Bạn là {name}, một trợ lý AI chuyên nghiệp.
{description}

Output (khi assistant có name="PolyPi", description="Học tiếng Anh"):
Bạn là PolyPi, một trợ lý AI chuyên nghiệp.
Học tiếng Anh
```

### Bước 4: Preview

- Preview tự động hiển thị khi bạn nhập cả `name` và `system_prompt`
- Preview sẽ replace placeholders với giá trị thực tế
- Giúp bạn xem prompt sẽ như thế nào khi được sử dụng

## 🎯 VÍ DỤ PROMPT CHO TỪNG LOẠI

### qa_based_document (Trả lời Q&A từ tài liệu)
```
Bạn là {name}, một trợ lý AI chuyên trả lời câu hỏi dựa trên tài liệu đã được upload.

{description}

**CHỨC NĂNG CHÍNH:**
- Trả lời câu hỏi dựa TRỰC TIẾP và CHỈ dựa trên tài liệu được cung cấp
- Đọc kỹ toàn bộ tài liệu tham khảo trước khi trả lời
- Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI trả lời đầy đủ và chi tiết
- KHÔNG được nói "tài liệu không đề cập" nếu thông tin thực sự có trong tài liệu
- Trích dẫn nguồn [Nguồn X] khi có thể

**QUY TẮC:**
- Sử dụng ngôn ngữ tự nhiên, thân thiện, dễ hiểu
- Trả lời chi tiết, có cấu trúc, dễ đọc
```

### document_drafting (Soạn thảo Văn bản Hành chính)
```
Bạn là {name}, một trợ lý AI chuyên soạn thảo văn bản hành chính.

{description}

**CHỨC NĂNG CHÍNH:**
- Soạn thảo các loại văn bản: Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết
- Sử dụng đúng format, ngôn ngữ hành chính, tuân thủ quy định pháp luật
- Thu thập thông tin cần thiết từ người dùng để soạn thảo chính xác

**QUY TẮC GIAO TIẾP:**
- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công
- Xưng hô: "Tôi" để tự xưng, "Quý anh/chị" để gọi người dùng
```

## ⚠️ LƯU Ý

1. **Nếu không nhập System Prompt:**
   - Hệ thống sẽ dùng default prompt theo loại (từ SystemPromptBuilder)
   - Default prompts đã được seed vào database

2. **Priority khi sử dụng:**
   ```
   1. assistant.system_prompt_override (nếu có)
   2. assistant_type.system_prompt (từ form này)
   3. default prompt by type (hardcode)
   ```

3. **Placeholders:**
   - Chỉ có `{name}` và `{description}` được support
   - Sẽ được replace tự động khi build prompt

## 🔍 KIỂM TRA

Sau khi tạo/sửa loại trợ lý với system_prompt:

1. Tạo assistant mới với loại trợ lý đó
2. Test chatbot để xem prompt có đúng không
3. Có thể dùng command: `php artisan test:system-prompt {assistant_id}` để xem prompt được build như thế nào


