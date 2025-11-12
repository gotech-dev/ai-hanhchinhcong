# Báo Cáo Phân Tích: Vấn Đề Chatbot Không Tạo Theo Template Đã Upload

## Tổng Quan Vấn Đề

**Mô tả:** Khi admin upload nhiều mẫu template cho assistant loại `document_drafting`, nhưng khi user yêu cầu chatbot tạo văn bản, chatbot không tạo theo đúng mẫu template đã upload mà tự ý tạo theo cấu trúc generic.

**Vị trí:** Màn hình chat phía user tại route `/chat`

---

## Phân Tích Nguyên Nhân

### 1. Luồng Xử Lý Hiện Tại

#### 1.1. Admin Upload Template
- **File:** `app/Http/Controllers/AdminController.php`
- **Method:** `processDocumentTemplates()` (line 227-268)
- **Chức năng:** 
  - Admin upload các file template (PDF/DOCX) khi tạo assistant
  - Template được lưu vào bảng `document_templates` với các thông tin:
    - `document_type`: Loại văn bản (cong_van, quyet_dinh, etc.)
    - `template_subtype`: Phân loại nhỏ (bo_nhiem, khen_thuong, di, den, etc.)
    - `file_path`: Đường dẫn file template
    - `metadata`: Chứa placeholders nếu là file DOCX

#### 1.2. User Yêu Cầu Tạo Văn Bản
- **File:** `app/Http/Controllers/ChatController.php`
- **Method:** `streamChat()` (line 259-587)
- **Luồng:**
  1. User gửi message qua frontend (`Dashboard.vue`)
  2. `streamChat()` được gọi
  3. Gọi `buildMessagesWithContext()` để build messages cho AI

#### 1.3. Build Messages Cho AI
- **File:** `app/Http/Controllers/ChatController.php`
- **Method:** `buildMessagesWithContext()` (line 725-820)

**VẤN ĐỀ CHÍNH:**

```php
protected function buildMessagesWithContext(ChatSession $session, string $newMessage): array
{
    $assistant = $session->aiAssistant;
    
    // ❌ CHỈ xử lý cho qa_based_document
    if ($assistant->assistant_type === 'qa_based_document') {
        // ... search documents và add context
    }
    
    // ❌ FALLBACK: document_drafting bị fallback về buildMessages()
    // Fallback to regular buildMessages if no documents or search failed
    return $this->buildMessages($session, $newMessage);
}
```

**Hệ quả:** 
- `document_drafting` assistant KHÔNG được xử lý đặc biệt
- Fallback về `buildMessages()` chỉ dùng `$assistant->description` làm system prompt
- **KHÔNG có thông tin về templates đã upload**

#### 1.4. Build Messages Generic
- **File:** `app/Http/Controllers/ChatController.php`
- **Method:** `buildMessages()` (line 681-720)

```php
protected function buildMessages(ChatSession $session, string $newMessage): array
{
    $messages = [
        [
            'role' => 'system',
            'content' => $session->aiAssistant->description ?? 'Bạn là một trợ lý AI thông minh.',
            // ❌ CHỈ có description, KHÔNG có template info
        ],
    ];
    // ... add previous messages
    return $messages;
}
```

#### 1.5. Xử Lý Document Drafting
- **File:** `app/Services/SmartAssistantEngine.php`
- **Method:** `handleDraftDocument()` (line 373-487)
- **Luồng:**
  1. Detect document type từ user message
  2. Detect template subtype
  3. Gọi `DocumentDraftingService::draftDocument()`

#### 1.6. Document Drafting Service
- **File:** `app/Services/DocumentDraftingService.php`
- **Method:** `draftDocument()` (line 39-111)

**Luồng xử lý:**

```php
public function draftDocument(...): array
{
    // 1. Tìm template từ database
    $template = $this->findTemplate($assistant, $documentType, $templateSubtype);
    
    // 2. Auto-fill basic info
    $autoFilledData = $this->autoFillBasicInfo(...);
    
    // 3. Generate content using AI
    if (empty($collectedData) || $this->needsAIContentGeneration($collectedData)) {
        // ❌ VẤN ĐỀ: generateContentWithAI() KHÔNG sử dụng template content
        $aiContent = $this->generateContentWithAI(
            $userRequest,
            $documentType,
            $collectedData,
            $autoFilledData
            // ❌ KHÔNG truyền $template vào đây!
        );
    }
    
    // 4. Generate DOCX file
    if ($template) {
        // ✅ Có sử dụng template để generate DOCX
        $filePath = $this->generateDocxFromTemplate($template, $documentData, $session);
    } else {
        // ❌ Fallback: generate từ code
        $filePath = $this->generateDocx($documentType, $documentData, $session);
    }
}
```

**VẤN ĐỀ:** 
- Template được tìm thấy và dùng để generate DOCX file
- **NHƯNG** AI content generation (`generateContentWithAI()`) KHÔNG biết về template
- AI chỉ dùng generic structure từ `$documentType->getTemplateStructure()`

#### 1.7. Generate Content With AI
- **File:** `app/Services/DocumentDraftingService.php`
- **Method:** `generateContentWithAI()` (line 338-375)

```php
protected function generateContentWithAI(
    string $userRequest,
    DocumentType $documentType,
    array $collectedData,
    array $autoFilledData
    // ❌ KHÔNG có tham số $template
): array {
    // ❌ Dùng generic structure, KHÔNG phải template structure
    $templateStructure = $documentType->getTemplateStructure();
    
    $prompt = $this->buildPrompt($userRequest, $documentType, $collectedData, $autoFilledData, $templateStructure);
    
    // ❌ AI prompt KHÔNG có thông tin về template đã upload
    $response = OpenAI::chat()->create([...]);
}
```

#### 1.8. Build Prompt
- **File:** `app/Services/DocumentDraftingService.php`
- **Method:** `buildPrompt()` (line 380-401)

```php
protected function buildPrompt(
    string $userRequest,
    DocumentType $documentType,
    array $collectedData,
    array $autoFilledData,
    array $templateStructure  // ❌ Generic structure, không phải từ template file
): string {
    $prompt = "Bạn là chuyên gia soạn thảo văn bản hành chính Việt Nam theo Nghị định 30/2020/NĐ-CP.\n\n";
    $prompt .= "Yêu cầu: {$userRequest}\n\n";
    $prompt .= "Loại văn bản: {$documentType->displayName()}\n\n";
    $prompt .= "Thông tin đã có:\n";
    $prompt .= json_encode($autoFilledData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $prompt .= "Cấu trúc văn bản cần tạo:\n";
    $prompt .= json_encode($templateStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    // ❌ KHÔNG có thông tin về template file đã upload
    // ❌ KHÔNG có nội dung mẫu từ template
}
```

---

## Nguyên Nhân Gốc Rễ

### Nguyên Nhân 1: System Prompt Không Có Template Info
**Vị trí:** `ChatController::buildMessagesWithContext()` và `buildMessages()`

**Vấn đề:**
- `document_drafting` assistant không được xử lý đặc biệt trong `buildMessagesWithContext()`
- System prompt chỉ có `$assistant->description`, không có thông tin về templates
- AI không biết có những template nào available

**Code hiện tại:**
```php
// ChatController.php line 725-819
protected function buildMessagesWithContext(...): array
{
    // ❌ CHỈ xử lý qa_based_document
    if ($assistant->assistant_type === 'qa_based_document') {
        // ... search documents
    }
    
    // ❌ document_drafting fallback về buildMessages()
    return $this->buildMessages($session, $newMessage);
}

protected function buildMessages(...): array
{
    $messages = [
        [
            'role' => 'system',
            'content' => $session->aiAssistant->description ?? 'Bạn là một trợ lý AI thông minh.',
            // ❌ KHÔNG có template info
        ],
    ];
}
```

### Nguyên Nhân 2: AI Content Generation Không Dùng Template Content
**Vị trí:** `DocumentDraftingService::generateContentWithAI()` và `buildPrompt()`

**Vấn đề:**
- `generateContentWithAI()` không nhận tham số `$template`
- `buildPrompt()` chỉ dùng generic structure từ `DocumentType::getTemplateStructure()`
- Không extract và include nội dung từ template file vào prompt

**Code hiện tại:**
```php
// DocumentDraftingService.php line 338-375
protected function generateContentWithAI(
    string $userRequest,
    DocumentType $documentType,
    array $collectedData,
    array $autoFilledData
    // ❌ KHÔNG có $template parameter
): array {
    // ❌ Dùng generic structure
    $templateStructure = $documentType->getTemplateStructure();
    
    // ❌ Prompt không có template content
    $prompt = $this->buildPrompt(...);
}
```

### Nguyên Nhân 3: Template Chỉ Được Dùng Cho DOCX Generation
**Vị trí:** `DocumentDraftingService::draftDocument()`

**Vấn đề:**
- Template được tìm thấy và dùng để generate DOCX file (`generateDocxFromTemplate()`)
- **NHƯNG** AI content generation xảy ra TRƯỚC khi generate DOCX
- AI generate content dựa trên generic structure → sau đó mới điền vào template
- Kết quả: Content không khớp với template structure

**Code hiện tại:**
```php
// DocumentDraftingService.php line 39-111
public function draftDocument(...): array
{
    // 1. Tìm template
    $template = $this->findTemplate($assistant, $documentType, $templateSubtype);
    
    // 2. Generate AI content (KHÔNG dùng template)
    $aiContent = $this->generateContentWithAI(...);  // ❌ Không có $template
    
    // 3. Merge data
    $documentData = array_merge($autoFilledData, $aiContent);
    
    // 4. Generate DOCX (CÓ dùng template)
    if ($template) {
        $filePath = $this->generateDocxFromTemplate($template, $documentData, $session);
    }
}
```

---

## Giải Pháp Đề Xuất

### Giải Pháp 1: Thêm Template Info Vào System Prompt
**File:** `app/Http/Controllers/ChatController.php`
**Method:** `buildMessagesWithContext()`

**Thay đổi:**
1. Xử lý `document_drafting` assistant trong `buildMessagesWithContext()`
2. Load danh sách templates từ database
3. Include template info vào system prompt

**Code đề xuất:**
```php
protected function buildMessagesWithContext(ChatSession $session, string $newMessage): array
{
    $assistant = $session->aiAssistant;
    
    // Xử lý qa_based_document (giữ nguyên)
    if ($assistant->assistant_type === 'qa_based_document') {
        // ... existing code
    }
    
    // ✅ MỚI: Xử lý document_drafting
    if ($assistant->assistant_type === 'document_drafting') {
        // Load templates
        $templates = $assistant->documentTemplates()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        
        // Build system prompt với template info
        $systemPrompt = $assistant->description ?? 'Bạn là trợ lý soạn thảo văn bản.';
        
        if ($templates->isNotEmpty()) {
            $templateList = $templates->map(function($t) {
                return "- {$t->name} ({$t->document_type}" . 
                       ($t->template_subtype ? "/{$t->template_subtype}" : "") . ")";
            })->implode("\n");
            
            $systemPrompt .= "\n\n**CÁC TEMPLATE CÓ SẴN:**\n{$templateList}\n\n";
            $systemPrompt .= "Khi user yêu cầu tạo văn bản, bạn PHẢI sử dụng đúng template tương ứng.";
        }
        
        // Build messages với system prompt mới
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        
        // Add previous messages và new message
        // ... (tương tự buildMessages)
        
        return $messages;
    }
    
    // Fallback
    return $this->buildMessages($session, $newMessage);
}
```

### Giải Pháp 2: Include Template Content Vào AI Prompt
**File:** `app/Services/DocumentDraftingService.php`
**Method:** `generateContentWithAI()` và `buildPrompt()`

**Thay đổi:**
1. Truyền `$template` vào `generateContentWithAI()`
2. Extract nội dung từ template file (nếu có)
3. Include template content vào prompt

**Code đề xuất:**
```php
// Method draftDocument() - line 39
public function draftDocument(...): array
{
    // 1. Tìm template
    $template = $this->findTemplate($assistant, $documentType, $templateSubtype);
    
    // 2. Generate AI content (✅ TRUYỀN template vào)
    if (empty($collectedData) || $this->needsAIContentGeneration($collectedData)) {
        $aiContent = $this->generateContentWithAI(
            $userRequest,
            $documentType,
            $collectedData,
            $autoFilledData,
            $template  // ✅ MỚI: Truyền template
        );
    }
    
    // ... rest of code
}

// Method generateContentWithAI() - line 338
protected function generateContentWithAI(
    string $userRequest,
    DocumentType $documentType,
    array $collectedData,
    array $autoFilledData,
    ?DocumentTemplate $template = null  // ✅ MỚI: Thêm parameter
): array {
    // ✅ Nếu có template, extract structure từ template
    if ($template) {
        $templateStructure = $this->extractTemplateStructure($template);
        $templateContent = $this->extractTemplateContent($template);
    } else {
        // Fallback: dùng generic structure
        $templateStructure = $documentType->getTemplateStructure();
        $templateContent = null;
    }
    
    $prompt = $this->buildPrompt(
        $userRequest,
        $documentType,
        $collectedData,
        $autoFilledData,
        $templateStructure,
        $templateContent  // ✅ MỚI: Truyền template content
    );
    
    // ... rest of code
}

// Method buildPrompt() - line 380
protected function buildPrompt(
    string $userRequest,
    DocumentType $documentType,
    array $collectedData,
    array $autoFilledData,
    array $templateStructure,
    ?string $templateContent = null  // ✅ MỚI: Thêm parameter
): string {
    $prompt = "Bạn là chuyên gia soạn thảo văn bản hành chính Việt Nam theo Nghị định 30/2020/NĐ-CP.\n\n";
    $prompt .= "Yêu cầu: {$userRequest}\n\n";
    $prompt .= "Loại văn bản: {$documentType->displayName()}\n\n";
    
    // ✅ MỚI: Include template content nếu có
    if ($templateContent) {
        $prompt .= "**QUAN TRỌNG:** Bạn PHẢI tạo văn bản theo đúng mẫu template sau:\n\n";
        $prompt .= "--- MẪU TEMPLATE ---\n";
        $prompt .= $templateContent . "\n";
        $prompt .= "--- HẾT MẪU TEMPLATE ---\n\n";
        $prompt .= "Văn bản bạn tạo PHẢI:\n";
        $prompt .= "- Giữ nguyên cấu trúc và format như mẫu template\n";
        $prompt .= "- Điền đúng các placeholder trong template\n";
        $prompt .= "- Tuân thủ văn phong và style của template\n\n";
    }
    
    $prompt .= "Thông tin đã có:\n";
    $prompt .= json_encode($autoFilledData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $prompt .= "Cấu trúc văn bản cần tạo:\n";
    $prompt .= json_encode($templateStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // ... rest of prompt
}

// ✅ MỚI: Extract template structure từ template file
protected function extractTemplateStructure(DocumentTemplate $template): array
{
    // Extract placeholders từ template metadata hoặc từ file
    $placeholders = $template->metadata['placeholders'] ?? [];
    
    // Build structure từ placeholders
    $structure = [];
    foreach ($placeholders as $placeholder) {
        // Map placeholder to structure field
        // ...
    }
    
    return $structure;
}

// ✅ MỚI: Extract template content từ template file
protected function extractTemplateContent(DocumentTemplate $template): ?string
{
    try {
        $templatePath = $this->getTemplatePath($template->file_path);
        
        if (!file_exists($templatePath)) {
            return null;
        }
        
        // Extract text từ template file
        $text = $this->documentProcessor->extractText($templatePath);
        
        return $text;
    } catch (\Exception $e) {
        Log::warning('Failed to extract template content', [
            'template_id' => $template->id,
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}
```

### Giải Pháp 3: Cải Thiện Template Detection
**File:** `app/Services/SmartAssistantEngine.php`
**Method:** `handleDraftDocument()`

**Thay đổi:**
- Khi detect document type và subtype, log template được chọn
- Đảm bảo template được truyền đúng vào `DocumentDraftingService`

**Code đề xuất:**
```php
protected function handleDraftDocument(...): array
{
    // Detect document type
    $documentType = $this->detectDocumentType($userMessage, $intent);
    
    // Detect template subtype
    $templateSubtype = $this->detectTemplateSubtype($userMessage, $documentType);
    
    // ✅ MỚI: Log template detection
    Log::info('Template detection for document drafting', [
        'document_type' => $documentType->value,
        'template_subtype' => $templateSubtype,
        'session_id' => $session->id,
    ]);
    
    // Draft document
    $result = $this->documentDraftingService->draftDocument(
        $userMessage,
        $documentType,
        $session,
        $assistant,
        $collectedData,
        $templateSubtype
    );
    
    // ✅ MỚI: Log template usage
    if (isset($result['metadata']['template_used']) && $result['metadata']['template_used']) {
        Log::info('Template used successfully', [
            'template_id' => $result['metadata']['template_id'] ?? null,
            'session_id' => $session->id,
        ]);
    } else {
        Log::warning('No template used, using generic generation', [
            'document_type' => $documentType->value,
            'template_subtype' => $templateSubtype,
            'session_id' => $session->id,
        ]);
    }
    
    // ... rest of code
}
```

---

## Tham Khảo Code Hiển Thị HTML Trên Vue

**File:** `resources/js/Pages/Chat/Dashboard.vue`
**Method:** `renderMarkdown()` (line 415-435)

**Code hiện tại:**
```javascript
const renderMarkdown = (content) => {
    if (!content) return '';
    try {
        marked.use({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false,
        });
        
        const html = marked.parse(content);
        return html;
    } catch (e) {
        console.error('Error rendering markdown:', e);
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');
    }
};
```

**Sử dụng trong template:**
```vue
<div class="markdown-content" v-html="renderMarkdown(message.content)"></div>
```

**Lib được dùng:** `marked` (Markdown parser)

---

## Tóm Tắt

### Vấn Đề Chính
1. System prompt không có thông tin về templates đã upload
2. AI content generation không sử dụng nội dung từ template file
3. Template chỉ được dùng để generate DOCX, không ảnh hưởng đến AI content generation

### Giải Pháp
1. **Thêm template info vào system prompt** trong `ChatController::buildMessagesWithContext()`
2. **Include template content vào AI prompt** trong `DocumentDraftingService::buildPrompt()`
3. **Extract và sử dụng template content** khi generate AI content

### Ưu Tiên
1. **CAO:** Giải pháp 2 - Include template content vào AI prompt (ảnh hưởng trực tiếp đến output)
2. **TRUNG BÌNH:** Giải pháp 1 - Thêm template info vào system prompt (giúp AI biết có templates nào)
3. **THẤP:** Giải pháp 3 - Cải thiện logging (hỗ trợ debug)

---

## File Cần Sửa

1. `app/Http/Controllers/ChatController.php`
   - Method `buildMessagesWithContext()` - Thêm xử lý cho `document_drafting`

2. `app/Services/DocumentDraftingService.php`
   - Method `draftDocument()` - Truyền `$template` vào `generateContentWithAI()`
   - Method `generateContentWithAI()` - Thêm parameter `$template` và extract template content
   - Method `buildPrompt()` - Include template content vào prompt
   - Method mới `extractTemplateStructure()` - Extract structure từ template
   - Method mới `extractTemplateContent()` - Extract content từ template file

3. `app/Services/SmartAssistantEngine.php`
   - Method `handleDraftDocument()` - Cải thiện logging

---

## Lưu Ý Kỹ Thuật

1. **Extract template content:** Cần dùng `DocumentProcessor::extractText()` để extract text từ template file (PDF/DOCX)

2. **Template structure:** Có thể extract từ:
   - `$template->metadata['placeholders']` (nếu đã extract khi upload)
   - Hoặc extract trực tiếp từ template file bằng `TemplateProcessor::getVariables()`

3. **Performance:** Extract template content có thể tốn thời gian, nên cache nếu có thể

4. **Fallback:** Nếu không extract được template content, fallback về generic structure như hiện tại



