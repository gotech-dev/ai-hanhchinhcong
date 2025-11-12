# BÁO CÁO CẢI TIẾN SYSTEM PROMPT CHO CÁC LOẠI TRỢ LÝ

## 🔍 PHÂN TÍCH HIỆN TRẠNG

### Cách hiện tại: 1 System Prompt tổng quát

**Cấu trúc:**
1. `buildProfessionalSystemPrompt()` - Tạo prompt cơ bản cho TẤT CẢ loại trợ lý
2. `getAssistantTypeContext()` - Thêm context nhỏ theo từng loại (chỉ vài dòng)
3. `generateAnswerFromContext()` - Prompt riêng cho qa_based_document nhưng quá cụ thể

**Ví dụ prompt hiện tại:**
```php
// buildProfessionalSystemPrompt() - Dùng cho TẤT CẢ
"Bạn là {$assistantName}, một trợ lý AI chuyên nghiệp phục vụ trong lĩnh vực hành chính công.\n\n"
+ "**MÔ TẢ CHỨC NĂNG:**\n{$assistantDescription}\n\n"
+ getAssistantTypeContext() // Chỉ vài dòng
+ "**NHIỆM VỤ CHÍNH:**\n"
+ "1. Trả lời câu hỏi thông thường..."
+ "2. Thu thập thông tin khi cần..."
+ "**QUY TẮC GIAO TIẾP:**\n"
+ "1. Luôn sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công..."
```

**Vấn đề:**
1. ❌ **Quá tập trung vào "hành chính công"** - Không phù hợp với chatbot tiếng Anh PolyPi
2. ❌ **Prompt tổng quát quá dài** - Chứa nhiều quy tắc không liên quan đến một số loại trợ lý
3. ❌ **Context theo loại quá ngắn** - Chỉ vài dòng, không đủ chi tiết
4. ❌ **Không thể tùy chỉnh** - Admin không thể tạo prompt riêng cho từng loại
5. ❌ **Prompt trong generateAnswerFromContext quá cụ thể** - Tập trung vào "Luật Đất đai" thay vì tổng quát

### Các loại trợ lý hiện có:

1. **qa_based_document** - Trả lời Q&A từ tài liệu (ví dụ: PolyPi tiếng Anh)
2. **document_drafting** - Soạn thảo Văn bản Hành chính
3. **document_management** - Quản lý Văn bản và Lưu trữ
4. **hr_management** - Quản lý Nhân sự
5. **finance_management** - Quản lý Tài chính và Ngân sách
6. **project_management** - Quản lý Dự án Đầu tư Công
7. **complaint_management** - Quản lý Khiếu nại và Tố cáo
8. **event_management** - Tổ chức Sự kiện và Hội nghị
9. **asset_management** - Quản lý Tài sản Công

## 📊 SO SÁNH: 1 PROMPT TỔNG QUÁT vs PROMPT THEO TỪNG LOẠI

### ❌ Cách hiện tại: 1 Prompt tổng quát

**Ưu điểm:**
- ✅ Dễ maintain - Chỉ sửa 1 chỗ
- ✅ Đồng nhất - Tất cả trợ lý có cùng style
- ✅ Code đơn giản - Không cần logic phức tạp

**Nhược điểm:**
- ❌ **Không phù hợp với mọi loại** - Prompt "hành chính công" không phù hợp với chatbot tiếng Anh
- ❌ **Quá dài và không tập trung** - Chứa nhiều quy tắc không cần thiết
- ❌ **Khó tùy chỉnh** - Admin không thể tạo prompt riêng
- ❌ **Không linh hoạt** - Không thể có prompt khác nhau cho cùng loại trợ lý
- ❌ **Khó mở rộng** - Thêm loại mới phải sửa code

**Ví dụ vấn đề:**
- Chatbot PolyPi (tiếng Anh) nhận prompt "hành chính công" → Không phù hợp
- Prompt có quy tắc về "Công văn, Quyết định" → Không liên quan đến IELTS
- Prompt có ví dụ về "Hà Nội có bao nhiêu tỉnh" → Không liên quan đến tiếng Anh

### ✅ Cách mới: Prompt theo từng loại trợ lý

**Ưu điểm:**
- ✅ **Phù hợp với từng loại** - Mỗi loại có prompt tối ưu riêng
- ✅ **Ngắn gọn và tập trung** - Chỉ chứa quy tắc cần thiết
- ✅ **Có thể tùy chỉnh** - Admin có thể tạo/sửa prompt khi tạo loại trợ lý
- ✅ **Linh hoạt** - Có thể override prompt ở level assistant
- ✅ **Dễ mở rộng** - Thêm loại mới chỉ cần thêm prompt mới

**Nhược điểm:**
- ⚠️ Cần maintain nhiều prompt
- ⚠️ Code phức tạp hơn một chút
- ⚠️ Cần database để lưu prompt

**Ví dụ prompt theo loại:**

**qa_based_document:**
```
Bạn là {$assistantName}, một trợ lý AI chuyên trả lời câu hỏi dựa trên tài liệu.

**CHỨC NĂNG CHÍNH:**
- Trả lời câu hỏi dựa TRỰC TIẾP trên tài liệu đã được upload
- Đọc kỹ toàn bộ tài liệu trước khi trả lời
- Nếu tài liệu có thông tin, PHẢI trả lời đầy đủ
- Trích dẫn nguồn [Nguồn X] khi có thể
- Chỉ nói "tài liệu không đề cập" khi CHẮC CHẮN không có thông tin

**QUY TẮC:**
- Sử dụng ngôn ngữ tự nhiên, thân thiện
- Trả lời chi tiết, có cấu trúc
- Ưu tiên thông tin từ tài liệu
```

**document_drafting:**
```
Bạn là {$assistantName}, một trợ lý AI chuyên soạn thảo văn bản hành chính.

**CHỨC NĂNG CHÍNH:**
- Soạn thảo các loại văn bản: Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết
- Sử dụng đúng format, ngôn ngữ hành chính
- Tuân thủ quy định pháp luật
- Thu thập thông tin cần thiết từ người dùng

**QUY TẮC:**
- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công
- Xưng hô: "Tôi" để tự xưng, "Quý anh/chị" để gọi người dùng
- Luôn thừa nhận ngữ cảnh từ tin nhắn của người dùng
```

## 🎯 GIẢI PHÁP ĐỀ XUẤT

### Phương án 1: Lưu System Prompt trong bảng `assistant_types` (KHUYẾN NGHỊ)

**Cấu trúc database:**
```sql
ALTER TABLE assistant_types ADD COLUMN system_prompt TEXT NULL COMMENT 'System prompt mặc định cho loại trợ lý này';
ALTER TABLE assistant_types ADD COLUMN system_prompt_template TEXT NULL COMMENT 'Template prompt với placeholders {name}, {description}';
```

**Ưu điểm:**
- ✅ Mỗi loại có prompt riêng
- ✅ Admin có thể tạo/sửa prompt khi tạo loại trợ lý
- ✅ Có thể override ở level assistant nếu cần
- ✅ Dễ maintain - Prompt được lưu trong database

**Cách hoạt động:**
1. Khi tạo loại trợ lý mới trong admin, admin nhập system prompt
2. Prompt được lưu vào `assistant_types.system_prompt`
3. Khi build prompt, lấy từ `assistant_types` và replace placeholders
4. Nếu assistant có `config.system_prompt_override`, dùng prompt đó thay vì prompt mặc định

**Code:**
```php
protected function buildSystemPrompt(AiAssistant $assistant): string
{
    // 1. Kiểm tra override ở level assistant
    if (!empty($assistant->config['system_prompt_override'])) {
        return $this->replacePromptPlaceholders(
            $assistant->config['system_prompt_override'],
            $assistant
        );
    }
    
    // 2. Lấy prompt từ assistant_type
    $assistantType = $assistant->type;
    if ($assistantType && !empty($assistantType->system_prompt)) {
        return $this->replacePromptPlaceholders(
            $assistantType->system_prompt,
            $assistant
        );
    }
    
    // 3. Fallback về prompt mặc định theo loại (hardcode)
    return $this->getDefaultSystemPrompt($assistant);
}

protected function replacePromptPlaceholders(string $prompt, AiAssistant $assistant): string
{
    return str_replace(
        ['{name}', '{description}'],
        [$assistant->name ?? 'Trợ lý AI', $assistant->description ?? ''],
        $prompt
    );
}
```

### Phương án 2: Lưu System Prompt trong bảng `ai_assistants`

**Cấu trúc database:**
```sql
ALTER TABLE ai_assistants ADD COLUMN system_prompt TEXT NULL COMMENT 'System prompt riêng cho assistant này';
```

**Ưu điểm:**
- ✅ Linh hoạt nhất - Mỗi assistant có thể có prompt riêng
- ✅ Không cần sửa bảng assistant_types

**Nhược điểm:**
- ❌ Khó maintain - Mỗi assistant phải tạo prompt riêng
- ❌ Không tái sử dụng - Phải copy/paste prompt cho assistant cùng loại

### Phương án 3: Kết hợp cả 2 (TỐI ƯU NHẤT)

**Cấu trúc database:**
```sql
-- Prompt mặc định cho loại trợ lý
ALTER TABLE assistant_types ADD COLUMN system_prompt TEXT NULL;
ALTER TABLE assistant_types ADD COLUMN system_prompt_template TEXT NULL;

-- Prompt override cho assistant cụ thể (optional)
ALTER TABLE ai_assistants ADD COLUMN system_prompt_override TEXT NULL COMMENT 'Override system prompt mặc định của loại';
```

**Cách hoạt động:**
1. Mỗi loại trợ lý có prompt mặc định trong `assistant_types.system_prompt`
2. Khi tạo assistant, tự động dùng prompt mặc định của loại
3. Admin có thể override prompt cho assistant cụ thể nếu cần
4. Priority: `assistant.system_prompt_override` > `assistant_type.system_prompt` > `default hardcode`

**Code:**
```php
protected function buildSystemPrompt(AiAssistant $assistant): string
{
    // Priority 1: Override ở level assistant
    if (!empty($assistant->system_prompt_override)) {
        return $this->replacePromptPlaceholders(
            $assistant->system_prompt_override,
            $assistant
        );
    }
    
    // Priority 2: Prompt từ assistant_type
    $assistantType = $assistant->type;
    if ($assistantType && !empty($assistantType->system_prompt)) {
        return $this->replacePromptPlaceholders(
            $assistantType->system_prompt,
            $assistant
        );
    }
    
    // Priority 3: Default prompt theo loại (backward compatibility)
    return $this->getDefaultSystemPromptByType($assistant->getAssistantTypeValue());
}
```

## 📝 VÍ DỤ PROMPT CHO TỪNG LOẠI

### 1. qa_based_document (Trả lời Q&A từ tài liệu)

```markdown
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
- Ưu tiên thông tin từ tài liệu, không sử dụng kiến thức chung
- Chỉ nói "tài liệu không đề cập" khi bạn đã đọc kỹ và CHẮC CHẮN rằng tài liệu không có thông tin
```

### 2. document_drafting (Soạn thảo Văn bản Hành chính)

```markdown
Bạn là {name}, một trợ lý AI chuyên soạn thảo văn bản hành chính.

{description}

**CHỨC NĂNG CHÍNH:**
- Soạn thảo các loại văn bản: Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết
- Sử dụng đúng format, ngôn ngữ hành chính, tuân thủ quy định pháp luật
- Thu thập thông tin cần thiết từ người dùng để soạn thảo chính xác
- Kiểm tra tính hợp pháp và đúng quy trình

**QUY TẮC GIAO TIẾP:**
- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công
- Xưng hô: "Tôi" để tự xưng, "Quý anh/chị" để gọi người dùng
- Luôn thừa nhận ngữ cảnh từ tin nhắn của người dùng trước khi trả lời
- Khi hỏi lại người dùng, hãy thừa nhận những gì họ vừa nói và đưa ra ví dụ, gợi ý cụ thể
- Trả lời rõ ràng, chi tiết, có cấu trúc
```

### 3. document_management (Quản lý Văn bản)

```markdown
Bạn là {name}, một trợ lý AI chuyên quản lý văn bản và lưu trữ.

{description}

**CHỨC NĂNG CHÍNH:**
- Quản lý văn bản đến, văn bản đi
- Phân loại văn bản tự động
- Tính toán và nhắc nhở thời hạn xử lý
- Lưu trữ và tìm kiếm văn bản
- Trả lời câu hỏi về văn bản một cách trực tiếp

**QUY TẮC:**
- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp
- Trả lời trực tiếp câu hỏi về văn bản, không hỏi lại nếu không cần
- Cung cấp thông tin chi tiết về văn bản khi được yêu cầu
```

### 4. hr_management (Quản lý Nhân sự)

```markdown
Bạn là {name}, một trợ lý AI chuyên quản lý nhân sự.

{description}

**CHỨC NĂNG CHÍNH:**
- Quản lý nhân sự: tính lương, chấm công, nghỉ phép
- Tạo báo cáo nhân sự
- Trả lời câu hỏi về quy định nhân sự, chế độ chính sách
- Hỗ trợ tính toán lương, thưởng, phụ cấp

**QUY TẮC:**
- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp
- Bảo mật thông tin nhân sự
- Trả lời chính xác về quy định, chế độ
- Tính toán chính xác, minh bạch
```

## 🔧 IMPLEMENTATION PLAN

### Bước 1: Tạo migration

```php
// database/migrations/xxxx_add_system_prompt_to_assistant_types.php
Schema::table('assistant_types', function (Blueprint $table) {
    $table->text('system_prompt')->nullable()->after('description')
        ->comment('System prompt mặc định cho loại trợ lý này');
    $table->text('system_prompt_template')->nullable()->after('system_prompt')
        ->comment('Template prompt với placeholders {name}, {description}');
});

Schema::table('ai_assistants', function (Blueprint $table) {
    $table->text('system_prompt_override')->nullable()->after('config')
        ->comment('Override system prompt mặc định của loại');
});
```

### Bước 2: Cập nhật Model

```php
// app/Models/AssistantType.php
protected $fillable = [
    'code',
    'name',
    'description',
    'system_prompt',        // ✅ MỚI
    'system_prompt_template', // ✅ MỚI
    'is_active',
    'icon',
    'color',
    'sort_order',
];

// app/Models/AiAssistant.php
protected $fillable = [
    // ... existing fields
    'system_prompt_override', // ✅ MỚI
];
```

### Bước 3: Tạo Service để build prompt

```php
// app/Services/SystemPromptBuilder.php
class SystemPromptBuilder
{
    public function build(AiAssistant $assistant): string
    {
        // Priority 1: Override
        if (!empty($assistant->system_prompt_override)) {
            return $this->replacePlaceholders($assistant->system_prompt_override, $assistant);
        }
        
        // Priority 2: From assistant_type
        $assistantType = $assistant->type;
        if ($assistantType && !empty($assistantType->system_prompt)) {
            return $this->replacePlaceholders($assistantType->system_prompt, $assistant);
        }
        
        // Priority 3: Default by type
        return $this->getDefaultPrompt($assistant);
    }
    
    protected function replacePlaceholders(string $prompt, AiAssistant $assistant): string
    {
        return str_replace(
            ['{name}', '{description}'],
            [
                $assistant->name ?? 'Trợ lý AI',
                $assistant->description ?? ''
            ],
            $prompt
        );
    }
    
    protected function getDefaultPrompt(AiAssistant $assistant): string
    {
        $type = $assistant->getAssistantTypeValue();
        
        return match($type) {
            'qa_based_document' => $this->getQABasedDocumentPrompt($assistant),
            'document_drafting' => $this->getDocumentDraftingPrompt($assistant),
            // ... other types
            default => $this->getGenericPrompt($assistant),
        };
    }
}
```

### Bước 4: Cập nhật SmartAssistantEngine

```php
// app/Services/SmartAssistantEngine.php
protected function buildProfessionalSystemPrompt(AiAssistant $assistant): string
{
    $builder = app(SystemPromptBuilder::class);
    return $builder->build($assistant);
}
```

### Bước 5: Cập nhật Admin UI

- Thêm field `system_prompt` khi tạo/sửa assistant_type
- Thêm field `system_prompt_override` (optional) khi tạo/sửa assistant
- Hiển thị preview prompt

### Bước 6: Seed default prompts

Tạo seeder để thêm prompt mặc định cho các loại trợ lý hiện có.

## 📈 KẾT QUẢ MONG ĐỢI

### Trước khi cải tiến:
- ❌ Chatbot PolyPi nhận prompt "hành chính công" → Không phù hợp
- ❌ Prompt quá dài, chứa nhiều quy tắc không liên quan
- ❌ Không thể tùy chỉnh prompt cho từng loại
- ❌ Phải sửa code mỗi khi thêm loại mới

### Sau khi cải tiến:
- ✅ Mỗi loại trợ lý có prompt tối ưu riêng
- ✅ Prompt ngắn gọn, tập trung vào chức năng cụ thể
- ✅ Admin có thể tạo/sửa prompt khi tạo loại trợ lý
- ✅ Có thể override prompt cho assistant cụ thể
- ✅ Dễ mở rộng - Thêm loại mới chỉ cần thêm prompt mới

## 🎯 KHUYẾN NGHỊ

**Nên chọn Phương án 3 (Kết hợp cả 2):**
- Lưu prompt mặc định trong `assistant_types` - Dễ tái sử dụng
- Cho phép override ở level `ai_assistants` - Linh hoạt khi cần
- Có default prompt hardcode - Backward compatibility

**Lợi ích:**
1. ✅ Mỗi loại có prompt riêng, phù hợp với chức năng
2. ✅ Admin có thể tùy chỉnh khi tạo loại trợ lý
3. ✅ Có thể override cho assistant cụ thể nếu cần
4. ✅ Dễ maintain và mở rộng
5. ✅ Backward compatible với code hiện tại


