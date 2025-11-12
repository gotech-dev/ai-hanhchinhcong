# Phương Án Cải Tiến: Trả Lời Chatbot Không Phù Hợp

## 📋 Tổng Quan Vấn Đề

### Hiện Trạng
Từ hình ảnh đính kèm, có thể thấy vấn đề rõ ràng:

1. **User hỏi**: "hà nội bây giờ có bao nhiêu tỉnh" (câu hỏi thực tế về địa lý hành chính)
2. **AI trả lời**: "Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin. Quý anh/chị vui lòng cung cấp các thông tin cần thiết để tôi có thể tiếp tục hỗ trợ quý anh/chị."

### Vấn Đề Phát Hiện

1. **Nhận diện sai intent**: Câu hỏi thông thường bị nhận diện nhầm thành yêu cầu cần workflow/steps
2. **Không trả lời trực tiếp**: AI không trả lời câu hỏi mà lại hỏi lại user
3. **Logic xử lý chưa tối ưu**: Khi vào `executeCollectInfoStep()` mà không có questions/fields, trả về message mặc định yêu cầu thông tin
4. **System prompt chưa đủ**: AI không được hướng dẫn rõ ràng về việc trả lời câu hỏi thông thường

---

## 🎯 Mục Tiêu Cải Tiến

1. **Nhận diện chính xác**: Phân biệt rõ câu hỏi thông thường vs yêu cầu cần workflow
2. **Trả lời trực tiếp**: AI phải trả lời câu hỏi thay vì hỏi lại
3. **Cải thiện logic**: Không trigger steps khi là câu hỏi thông thường
4. **System prompt tốt hơn**: Hướng dẫn AI trả lời đúng ngữ cảnh

---

## 💡 Phương Án Cải Tiến

### 0. Tự Động Phân Loại Khi Tạo Assistant (QUAN TRỌNG)

#### 0.1. Vấn Đề Hiện Tại

Khi tạo assistant, hệ thống không tự động phân loại khi nào cần tạo steps và khi nào không cần. Điều này dẫn đến:
- Q&A assistant vẫn có steps không cần thiết
- Các assistant khác có thể thiếu steps cần thiết
- Admin phải tự quyết định, dễ nhầm lẫn

#### 0.2. Quy Tắc Phân Loại

**KHÔNG CẦN STEPS:**
- **Q&A Assistant** (`qa_based_document`): 
  - Có file upload → Trả lời dựa trên file (vector search)
  - Không có file → Tìm thông tin trên mạng → Dùng ChatGPT trả lời
  - Không cần workflow phức tạp, chỉ cần trả lời trực tiếp

**CẦN TỰ ĐỘNG TẠO STEPS:**
- **Document Drafting** (`document_drafting`): Có thể cần steps nếu là workflow phức tạp
- **Custom Assistant** với mô tả yêu cầu workflow: "Viết sách", "Tạo kế hoạch dự án", "Research và báo cáo"
- Các assistant có mô tả chứa từ khóa: "bước", "quy trình", "workflow", "research", "bao quát"

#### 0.3. Implementation

**A. Cải thiện AdminController - Tự động phân loại**

```php
/**
 * Create assistant (minimalist form)
 * ✅ CẢI TIẾN: Tự động phân loại khi nào cần steps
 */
public function createAssistant(Request $request)
{
    // ... existing validation ...
    
    $data = $validator->validated();
    
    DB::beginTransaction();
    
    try {
        // Build config
        $config = [
            'model' => $data['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
        ];

        // ✅ CẢI TIẾN: Tự động phân loại khi nào cần steps
        $shouldHaveSteps = $this->shouldAssistantHaveSteps(
            $data['assistant_type'],
            $data['name'] ?? '',
            $data['description'] ?? ''
        );

        // ✅ CẢI TIẾN: Chỉ thêm steps nếu cần và được cung cấp
        if ($shouldHaveSteps) {
            // Nếu admin cung cấp steps, sử dụng
            if ($request->has('steps') && is_array($request->steps) && !empty($request->steps)) {
                $config['steps'] = $this->formatSteps($request->steps);
            }
            // Nếu không có steps nhưng cần, tự động tạo
            elseif ($this->shouldAutoGenerateSteps($data['assistant_type'], $data['name'] ?? '', $data['description'] ?? '')) {
                $config['steps'] = $this->autoGenerateSteps($data['name'], $data['description'], $data['assistant_type']);
            }
        } else {
            // ✅ QUAN TRỌNG: Q&A assistant KHÔNG có steps
            // Xóa steps nếu có (tránh admin nhầm lẫn)
            if ($request->has('steps')) {
                unset($config['steps']);
            }
            Log::info('Q&A assistant created without steps', [
                'assistant_type' => $data['assistant_type'],
                'name' => $data['name'],
            ]);
        }

        // Create assistant
        $assistant = AiAssistant::create([
            'admin_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'assistant_type' => $data['assistant_type'],
            'config' => $config,
            'is_active' => true,
        ]);
        
        // ... rest of the code ...
    }
}

/**
 * Kiểm tra xem assistant có cần steps không
 * 
 * @param string $assistantType
 * @param string $name
 * @param string $description
 * @return bool
 */
protected function shouldAssistantHaveSteps(string $assistantType, string $name, string $description): bool
{
    // ✅ Q&A assistant KHÔNG cần steps
    if ($assistantType === 'qa_based_document') {
        return false;
    }
    
    // ✅ Document management thường không cần steps phức tạp
    if ($assistantType === 'document_management') {
        return false;
    }
    
    // ✅ Document drafting: Có thể cần steps nếu mô tả yêu cầu workflow
    if ($assistantType === 'document_drafting') {
        // Kiểm tra mô tả có yêu cầu workflow không
        $text = mb_strtolower($name . ' ' . $description);
        $workflowKeywords = ['bước', 'quy trình', 'workflow', 'research', 'bao quát', 'từng bước', 'tuần tự'];
        
        foreach ($workflowKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        
        // Mặc định document_drafting không cần steps (đã có template system)
        return false;
    }
    
    // ✅ Các assistant khác: Phân tích bằng AI
    return $this->analyzeIfNeedsSteps($name, $description);
}

/**
 * Phân tích bằng AI xem assistant có cần steps không
 */
protected function analyzeIfNeedsSteps(string $name, string $description): bool
{
    try {
        $response = OpenAI::chat()->create([
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Bạn là một AI chuyên phân tích xem một trợ lý AI có cần workflow steps (các bước) hay không.\n\n"
                        . "**CẦN STEPS KHI:**\n"
                        . "- Trợ lý cần thực hiện nhiều bước tuần tự: \"Viết sách\", \"Tạo kế hoạch dự án\", \"Research và báo cáo\"\n"
                        . "- Trợ lý cần thu thập thông tin từng bước: \"Tạo báo cáo thường niên\", \"Lập kế hoạch chi tiết\"\n"
                        . "- Trợ lý cần research và tổng hợp: \"Tìm hiểu và báo cáo về...\", \"Phân tích và đưa ra kết luận\"\n\n"
                        . "**KHÔNG CẦN STEPS KHI:**\n"
                        . "- Trợ lý chỉ cần trả lời câu hỏi: \"Q&A\", \"Hỏi đáp\", \"Trả lời câu hỏi\"\n"
                        . "- Trợ lý chỉ cần tạo một loại văn bản đơn giản: \"Soạn thảo công văn\" (đã có template)\n"
                        . "- Trợ lý chỉ cần tìm kiếm và trả lời: \"Tìm kiếm thông tin\"\n\n"
                        . "**YÊU CẦU:**\n"
                        . "Trả về JSON: {\"needs_steps\": true/false, \"confidence\": 0.0-1.0, \"reason\": \"lý do\"}",
                ],
                [
                    'role' => 'user',
                    'content' => "Tên trợ lý: {$name}\nMô tả: {$description}\n\nPhân tích xem trợ lý này có cần steps không?",
                ],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content;
        $result = json_decode($content, true);

        if ($result && isset($result['needs_steps'])) {
            $needsSteps = (bool) $result['needs_steps'];
            $confidence = $result['confidence'] ?? 0.5;
            
            Log::info('Steps analysis with AI', [
                'name' => $name,
                'needs_steps' => $needsSteps,
                'confidence' => $confidence,
                'reason' => $result['reason'] ?? null,
            ]);
            
            return $needsSteps && $confidence >= 0.7;
        }
    } catch (\Exception $e) {
        Log::warning('Steps analysis with AI failed', [
            'error' => $e->getMessage(),
        ]);
    }
    
    // Fallback: Mặc định không cần steps
    return false;
}

/**
 * Kiểm tra xem có nên tự động tạo steps không
 */
protected function shouldAutoGenerateSteps(string $assistantType, string $name, string $description): bool
{
    // Chỉ tự động tạo cho các assistant cần steps
    if (!$this->shouldAssistantHaveSteps($assistantType, $name, $description)) {
        return false;
    }
    
    // Tự động tạo nếu mô tả rõ ràng về workflow
    $text = mb_strtolower($name . ' ' . $description);
    $autoGenerateKeywords = ['viết sách', 'tạo kế hoạch', 'research', 'phân tích', 'báo cáo chi tiết'];
    
    foreach ($autoGenerateKeywords as $keyword) {
        if (str_contains($text, $keyword)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Tự động tạo steps bằng AI
 */
protected function autoGenerateSteps(string $name, string $description, string $assistantType): array
{
    // Sử dụng logic từ PHUONG_AN_CAI_TIEN_CHON_TRO_LY.md
    // Gọi API generate-steps hoặc implement trực tiếp
    // ... (implementation tương tự như trong file PHUONG_AN_CAI_TIEN_CHON_TRO_LY.md)
}
```

**B. Cải thiện UI - Ẩn Steps Manager cho Q&A**

```vue
<!-- resources/js/Pages/Admin/CreateAssistant.vue -->

<template>
    <!-- ... existing code ... -->
    
    <!-- ✅ CẢI TIẾN: Chỉ hiển thị Steps Manager khi cần -->
    <div v-if="shouldShowStepsManager" class="mt-6">
        <AssistantStepsManager
            v-model="form.steps"
            :assistant-name="form.name"
            :assistant-description="form.description"
            :assistant-type="form.assistant_type"
        />
    </div>
    
    <!-- ✅ CẢI TIẾN: Thông báo cho Q&A assistant -->
    <div v-else-if="form.assistant_type === 'qa_based_document'" class="mt-6 p-4 bg-blue-50 rounded-lg">
        <p class="text-sm text-blue-800">
            <strong>Lưu ý:</strong> Trợ lý Q&A không cần tạo steps. 
            Trợ lý sẽ tự động:
            <ul class="list-disc list-inside mt-2">
                <li>Trả lời dựa trên tài liệu đã upload (nếu có)</li>
                <li>Tìm kiếm thông tin trên mạng và trả lời bằng ChatGPT (nếu không có tài liệu)</li>
            </ul>
        </p>
    </div>
    
    <!-- ... rest of the code ... -->
</template>

<script setup>
// ... existing code ...

// ✅ CẢI TIẾN: Computed để xác định khi nào hiển thị Steps Manager
const shouldShowStepsManager = computed(() => {
    // Q&A và Document Management không cần steps
    if (form.value.assistant_type === 'qa_based_document' || 
        form.value.assistant_type === 'document_management') {
        return false;
    }
    
    // Document Drafting: Chỉ hiển thị nếu mô tả yêu cầu workflow
    if (form.value.assistant_type === 'document_drafting') {
        const text = (form.value.name + ' ' + (form.value.description || '')).toLowerCase();
        const workflowKeywords = ['bước', 'quy trình', 'workflow', 'research', 'bao quát'];
        return workflowKeywords.some(keyword => text.includes(keyword));
    }
    
    // Các loại khác: Hiển thị
    return true;
});
</script>
```

**C. Cải thiện SmartAssistantEngine - Q&A không trigger steps**

```php
// Trong SmartAssistantEngine.php

// ✅ CẢI TIẾN: Q&A assistant KHÔNG bao giờ trigger steps
if ($predefinedSteps && is_array($predefinedSteps) && count($predefinedSteps) > 0) {
    // ✅ QUAN TRỌNG: Q&A assistant không dùng steps
    if ($assistant->assistant_type->value === 'qa_based_document') {
        Log::info('🔵 [SmartAssistantEngine] Q&A assistant detected, skipping steps', [
            'session_id' => $session->id,
            'assistant_id' => $assistant->id,
        ]);
        $shouldExecuteSteps = false;
    } else {
        // Logic hiện tại cho các assistant khác
        // ...
    }
}
```

**D. Cải thiện Q&A Assistant - Tìm kiếm trên mạng khi không có file**

```php
/**
 * Handle ask question intent
 * ✅ CẢI TIẾN: Tìm kiếm trên mạng nếu không có documents
 */
protected function handleAskQuestion(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent): array
{
    if ($assistant->assistant_type !== 'qa_based_document') {
        return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
    }
    
    try {
        // Check if assistant has documents
        $documentsCount = $assistant->documents()->where('status', 'indexed')->count();
        
        if ($documentsCount > 0) {
            // ✅ Có documents → Tìm kiếm trong documents
            $searchResults = $this->vectorSearchService->searchSimilar($userMessage, $assistant->id, 5);
            
            if (!empty($searchResults)) {
                $context = array_map(fn($r) => $r['content'], $searchResults);
                $answer = $this->generateAnswerFromContext($userMessage, $context, $assistant);
                
                return [
                    'response' => $answer,
                    'workflow_state' => null,
                    'sources' => array_map(fn($r) => [
                        'content' => substr($r['content'], 0, 200),
                        'similarity' => $r['similarity'],
                    ], $searchResults),
                ];
            }
        }
        
        // ✅ CẢI TIẾN: Không có documents hoặc không tìm thấy → Tìm kiếm trên mạng
        Log::info('No documents or no results, searching web', [
            'assistant_id' => $assistant->id,
            'has_documents' => $documentsCount > 0,
        ]);
        
        // Tìm kiếm trên mạng (có thể dùng Google Search API, Bing API, hoặc web scraping)
        $webResults = $this->searchWeb($userMessage);
        
        // Tạo câu trả lời từ web results + ChatGPT
        $answer = $this->generateAnswerFromWebSearch($userMessage, $webResults, $assistant);
        
        return [
            'response' => $answer,
            'workflow_state' => null,
            'sources' => $webResults,
        ];
        
    } catch (\Exception $e) {
        Log::error('Q&A handling error', [
            'error' => $e->getMessage(),
            'assistant_id' => $assistant->id,
        ]);
        
        // Fallback về generic request
        return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
    }
}

/**
 * Tìm kiếm trên mạng
 * TODO: Implement với Google Search API hoặc Bing API
 */
protected function searchWeb(string $query): array
{
    // TODO: Implement web search
    // Có thể dùng:
    // - Google Custom Search API
    // - Bing Web Search API
    // - SerpAPI
    // - Web scraping (cẩn thận với rate limiting)
    
    return [];
}

/**
 * Tạo câu trả lời từ web search results
 */
protected function generateAnswerFromWebSearch(string $question, array $webResults, AiAssistant $assistant): string
{
    $webContext = '';
    if (!empty($webResults)) {
        $webContext = "Thông tin tìm được trên mạng:\n\n";
        foreach (array_slice($webResults, 0, 5) as $index => $result) {
            $webContext .= ($index + 1) . ". " . ($result['title'] ?? '') . "\n";
            $webContext .= ($result['snippet'] ?? $result['content'] ?? '') . "\n\n";
        }
    }
    
    $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
    $systemPrompt .= "\n\n**NHIỆM VỤ:** Trả lời câu hỏi dựa trên thông tin tìm được trên mạng. Nếu không có thông tin, hãy trả lời dựa trên kiến thức của bạn.";
    
    $response = OpenAI::chat()->create([
        'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => "Câu hỏi: {$question}\n\n{$webContext}\n\nHãy trả lời câu hỏi dựa trên thông tin trên.",
            ],
        ],
        'temperature' => 0.3,
    ]);
    
    return $response->choices[0]->message->content;
}
```

---

### 1. Cải Thiện Nhận Diện Câu Hỏi Thông Thường

#### 1.1. Nâng cấp `isGeneralQuestion()`

**Vấn đề hiện tại**: Chỉ check một số pattern cứng, không đủ để nhận diện các câu hỏi thực tế.

**Giải pháp**: Sử dụng AI để nhận diện câu hỏi thông thường một cách thông minh hơn.

```php
/**
 * Check if message is a general question (not requiring workflow)
 * ✅ CẢI TIẾN: Sử dụng AI để nhận diện chính xác hơn
 *
 * @param string $message
 * @param array $intent
 * @return bool
 */
protected function isGeneralQuestion(string $message, array $intent): bool
{
    // ✅ MỚI: Sử dụng AI để detect general question
    try {
        $response = OpenAI::chat()->create([
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Bạn là một AI chuyên phân tích xem một tin nhắn có phải là câu hỏi thông thường (general question) hay không.\n\n"
                        . "**CÂU HỎI THÔNG THƯỜNG BAO GỒM:**\n"
                        . "- Câu hỏi về thông tin thực tế: \"Hà Nội có bao nhiêu tỉnh?\", \"Việt Nam có bao nhiêu tỉnh thành?\"\n"
                        . "- Câu hỏi về kiến thức: \"GDP là gì?\", \"Công văn là gì?\"\n"
                        . "- Câu hỏi về chức năng: \"Bạn làm được gì?\", \"Tính năng của bạn là gì?\"\n"
                        . "- Câu hỏi về cách sử dụng: \"Làm thế nào để...?\", \"Cách sử dụng...?\"\n"
                        . "- Câu hỏi về thông tin địa lý, hành chính: \"Xã A có bao nhiêu dân?\", \"Tỉnh B có bao nhiêu huyện?\"\n\n"
                        . "**KHÔNG PHẢI CÂU HỎI THÔNG THƯỜNG:**\n"
                        . "- Yêu cầu tạo văn bản: \"Tôi muốn soạn thảo công văn\", \"Giúp tôi tạo quyết định\"\n"
                        . "- Yêu cầu thu thập thông tin có workflow: \"Tôi muốn tạo báo cáo\", \"Làm báo cáo thường niên\"\n"
                        . "- Yêu cầu cụ thể cần nhiều bước: \"Tôi muốn viết sách\", \"Tạo kế hoạch dự án\"\n\n"
                        . "**YÊU CẦU:**\n"
                        . "Trả về JSON với format: {\"is_general_question\": true/false, \"confidence\": 0.0-1.0, \"reason\": \"lý do\"}\n"
                        . "Nếu là câu hỏi thông thường chỉ cần trả lời trực tiếp → is_general_question = true\n"
                        . "Nếu là yêu cầu cần workflow/steps → is_general_question = false",
                ],
                [
                    'role' => 'user',
                    'content' => "Tin nhắn cần phân tích: \"{$message}\"\n\nHãy phân tích và trả về JSON.",
                ],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content;
        $result = json_decode($content, true);

        if ($result && isset($result['is_general_question'])) {
            $isGeneralQuestion = (bool) $result['is_general_question'];
            $confidence = $result['confidence'] ?? 0.5;
            
            Log::debug('General question detection with AI', [
                'message' => substr($message, 0, 100),
                'is_general_question' => $isGeneralQuestion,
                'confidence' => $confidence,
                'reason' => $result['reason'] ?? null,
            ]);
            
            // Chỉ tin tưởng nếu confidence >= 0.7
            if ($isGeneralQuestion && $confidence >= 0.7) {
                return true;
            }
        }
    } catch (\Exception $e) {
        Log::warning('General question detection with AI failed, using fallback', [
            'error' => $e->getMessage(),
            'message' => substr($message, 0, 100),
        ]);
    }
    
    // ✅ Fallback: Pattern matching cho các trường hợp rõ ràng
    $normalizedMessage = mb_strtolower(trim($message));
    
    // Các pattern câu hỏi thông thường
    $generalQuestionPatterns = [
        // Câu hỏi về chức năng
        'mày làm được gì',
        'bạn làm được gì',
        'bạn có thể làm gì',
        'chức năng',
        'tính năng',
        'giúp gì',
        'làm gì',
        'là gì',
        'như thế nào',
        'cách sử dụng',
        'hướng dẫn',
        // Câu hỏi về số lượng, thông tin thực tế
        'có bao nhiêu',
        'bao nhiêu',
        'là gì',
        'là ai',
        'ở đâu',
        'khi nào',
        'tại sao',
        // Câu hỏi về địa lý, hành chính
        'có bao nhiêu tỉnh',
        'có bao nhiêu huyện',
        'có bao nhiêu xã',
        'có bao nhiêu dân',
    ];
    
    // Check patterns
    foreach ($generalQuestionPatterns as $pattern) {
        if (str_contains($normalizedMessage, $pattern)) {
            return true;
        }
    }
    
    // Check intent: nếu là ask_question và không có entity cụ thể → general question
    if (($intent['type'] ?? null) === 'ask_question' && empty($intent['entity'] ?? [])) {
        return true;
    }
    
    return false;
}
```

#### 1.2. Cải thiện logic trigger steps

**Vấn đề**: Câu hỏi thông thường vẫn có thể bị trigger vào steps nếu không được detect đúng.

**Giải pháp**: Thêm check bổ sung trước khi trigger steps.

```php
// ✅ CẢI TIẾN: Kiểm tra kỹ hơn trước khi trigger steps
$shouldExecuteSteps = false;

if ($predefinedSteps && is_array($predefinedSteps) && count($predefinedSteps) > 0) {
    // Đã bắt đầu workflow → Tiếp tục
    if ($currentStepIndex > 0 || !empty($collectedData)) {
        $shouldExecuteSteps = true;
    }
    // Chưa bắt đầu → Chỉ trigger nếu có intent rõ ràng cần workflow
    else {
        $isGreeting = $this->isGreetingMessage($userMessage);
        $isGeneralQuestion = $this->isGeneralQuestion($userMessage, $intent);
        
        // ✅ CẢI TIẾN: Thêm check intent type
        $requiresWorkflow = in_array($intent['type'] ?? null, [
            'draft_document',
            'create_report',
            'classify_document',
            'search_document', // Chỉ khi có yêu cầu cụ thể
        ]);
        
        // ✅ CẢI TIẾN: Chỉ trigger nếu:
        // 1. Không phải greeting
        // 2. Không phải general question
        // 3. Có intent rõ ràng cần workflow
        if (!$isGreeting && !$isGeneralQuestion && $requiresWorkflow) {
            $shouldExecuteSteps = true;
        }
    }
}
```

---

### 2. Cải Thiện `handleGenericRequest()`

#### 2.1. System Prompt Tốt Hơn

**Vấn đề**: System prompt hiện tại chưa đủ hướng dẫn AI trả lời câu hỏi thông thường.

**Giải pháp**: Cải thiện system prompt để AI hiểu rõ nhiệm vụ trả lời câu hỏi.

```php
/**
 * Build professional system prompt for administrative AI
 * ✅ CẢI TIẾN: Thêm hướng dẫn về trả lời câu hỏi thông thường
 *
 * @param AiAssistant $assistant
 * @return string
 */
protected function buildProfessionalSystemPrompt(AiAssistant $assistant): string
{
    $assistantName = $assistant->name ?? 'Trợ lý AI';
    $assistantDescription = $assistant->description ?? '';
    
    $prompt = "Bạn là {$assistantName}, một trợ lý AI chuyên nghiệp phục vụ trong lĩnh vực hành chính công.\n\n";
    
    if (!empty($assistantDescription)) {
        $prompt .= "**MÔ TẢ CHỨC NĂNG:**\n{$assistantDescription}\n\n";
    }
    
    // ✅ CẢI TIẾN: Thêm hướng dẫn về trả lời câu hỏi
    $prompt .= "**NHIỆM VỤ CHÍNH:**\n";
    $prompt .= "1. **Trả lời câu hỏi thông thường**: Khi người dùng hỏi về thông tin thực tế, kiến thức, địa lý hành chính, bạn PHẢI trả lời trực tiếp dựa trên kiến thức của bạn. KHÔNG hỏi lại người dùng.\n";
    $prompt .= "   - Ví dụ: \"Hà Nội có bao nhiêu tỉnh?\" → Trả lời: \"Hà Nội hiện tại là một thành phố trực thuộc Trung ương, không phải tỉnh. Hà Nội có 30 quận/huyện...\"\n";
    $prompt .= "   - Ví dụ: \"Công văn là gì?\" → Trả lời định nghĩa công văn\n";
    $prompt .= "   - Ví dụ: \"Việt Nam có bao nhiêu tỉnh thành?\" → Trả lời số lượng tỉnh thành\n\n";
    $prompt .= "2. **Thu thập thông tin khi cần**: Chỉ hỏi lại người dùng khi bạn CẦN thông tin cụ thể để thực hiện một tác vụ (ví dụ: soạn thảo văn bản, tạo báo cáo).\n";
    $prompt .= "   - Ví dụ: \"Tôi muốn soạn thảo công văn\" → Có thể hỏi: \"Quý anh/chị muốn soạn công văn đi hay công văn đến?\"\n\n";
    
    $prompt .= "**QUY TẮC GIAO TIẾP:**\n";
    $prompt .= "1. Luôn sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công\n";
    $prompt .= "2. Xưng hô: Sử dụng \"Tôi\" để tự xưng, \"Quý anh/chị\" hoặc \"Bạn\" để gọi người dùng\n";
    $prompt .= "3. Trả lời rõ ràng, chi tiết, có cấu trúc\n";
    $prompt .= "4. Sử dụng từ ngữ chính thức, tránh ngôn ngữ suồng sã, thân mật quá mức\n";
    $prompt .= "5. Luôn thể hiện sự tôn trọng và sẵn sàng hỗ trợ\n";
    $prompt .= "6. **QUAN TRỌNG**: Khi người dùng hỏi câu hỏi thông thường, TRẢ LỜI TRỰC TIẾP, không hỏi lại\n";
    $prompt .= "7. Khi cần thu thập thông tin để thực hiện tác vụ, hãy giải thích rõ mục đích và tầm quan trọng\n\n";
    
    $prompt .= "**VÍ DỤ CÁCH TRẢ LỜI:**\n";
    $prompt .= "- ✅ TỐT (Câu hỏi thông thường): \"Hà Nội hiện tại là một thành phố trực thuộc Trung ương, không phải tỉnh. Hà Nội có 30 quận/huyện và 584 phường/xã/thị trấn...\"\n";
    $prompt .= "- ✅ TỐT (Cần thông tin): \"Để tôi có thể soạn thảo công văn cho quý anh/chị, tôi cần một số thông tin. Quý anh/chị muốn soạn công văn đi hay công văn đến?\"\n";
    $prompt .= "- ❌ KHÔNG TỐT: \"Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin...\" (khi user chỉ hỏi câu hỏi thông thường)\n";
    $prompt .= "- ❌ KHÔNG TỐT: \"Vui lòng cung cấp thông tin cần thiết.\" (quá cộc lốc)\n\n";
    
    $prompt .= "Hãy luôn trả lời một cách chuyên nghiệp, lịch sự và hữu ích. Ưu tiên trả lời trực tiếp câu hỏi của người dùng.";
    
    return $prompt;
}
```

#### 2.2. Cải thiện xử lý conversation context

**Vấn đề**: Khi build messages, có thể thiếu context quan trọng.

**Giải pháp**: Đảm bảo conversation history được truyền đầy đủ.

```php
/**
 * Build chat messages for AI
 * ✅ CẢI TIẾN: Đảm bảo context đầy đủ cho câu hỏi thông thường
 *
 * @param ChatSession $session
 * @param string $newMessage
 * @param AiAssistant $assistant
 * @return array
 */
protected function buildChatMessages(ChatSession $session, string $newMessage, AiAssistant $assistant): array
{
    // ✅ FIX: Build system prompt chuyên nghiệp, lịch sự cho hành chính công
    $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
    
    $messages = [
        [
            'role' => 'system',
            'content' => $systemPrompt,
        ],
    ];
    
    // ✅ CẢI TIẾN: Thêm context về assistant type nếu cần
    if ($assistant->assistant_type) {
        $typeContext = $this->getAssistantTypeContext($assistant->assistant_type);
        if ($typeContext) {
            $messages[0]['content'] .= "\n\n" . $typeContext;
        }
    }
    
    // Add previous messages (giữ nguyên logic hiện tại)
    $previousMessages = $session->messages()->orderBy('created_at')->get();
    foreach ($previousMessages as $msg) {
        $messages[] = [
            'role' => $msg->sender === 'user' ? 'user' : 'assistant',
            'content' => $msg->content,
        ];
    }
    
    // Add new message
    $messages[] = [
        'role' => 'user',
        'content' => $newMessage,
    ];
    
    return $messages;
}

/**
 * Get context for assistant type
 */
protected function getAssistantTypeContext($assistantType): ?string
{
    $contexts = [
        'qa_based_document' => "**LƯU Ý**: Bạn là trợ lý Q&A. Khi người dùng hỏi câu hỏi, hãy trả lời dựa trên tài liệu đã được index. Nếu không có tài liệu liên quan, hãy trả lời dựa trên kiến thức chung của bạn.",
        'document_drafting' => "**LƯU Ý**: Bạn là trợ lý soạn thảo văn bản. Khi người dùng hỏi câu hỏi thông thường về văn bản, hãy trả lời trực tiếp. Khi người dùng yêu cầu soạn thảo, hãy thu thập thông tin cần thiết.",
        'document_management' => "**LƯU Ý**: Bạn là trợ lý quản lý văn bản. Khi người dùng hỏi câu hỏi thông thường, hãy trả lời trực tiếp. Khi người dùng yêu cầu quản lý văn bản, hãy thực hiện tác vụ tương ứng.",
    ];
    
    $typeValue = is_object($assistantType) ? $assistantType->value : $assistantType;
    return $contexts[$typeValue] ?? null;
}
```

---

### 3. Cải Thiện `executeCollectInfoStep()`

#### 3.1. Xử lý trường hợp không có questions/fields

**Vấn đề**: Khi không có questions/fields, trả về message mặc định yêu cầu thông tin, gây nhầm lẫn.

**Giải pháp**: Kiểm tra kỹ hơn và fallback về `handleGenericRequest()` nếu cần.

```php
/**
 * Execute collect_info step
 * ✅ CẢI TIẾN: Xử lý tốt hơn khi không có questions/fields
 */
protected function executeCollectInfoStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant): array
{
    $config = $step['config'] ?? [];
    $questions = $config['questions'] ?? [];
    $fields = $config['fields'] ?? [];

    // ✅ LOG: Debug collect_info step
    Log::info('🔵 [executeCollectInfoStep] Executing collect_info step', [
        'step_id' => $step['id'] ?? null,
        'step_name' => $step['name'] ?? null,
        'has_questions' => !empty($questions),
        'questions_count' => is_array($questions) ? count($questions) : 0,
        'has_fields' => !empty($fields),
        'fields_count' => is_array($fields) ? count($fields) : 0,
        'user_message' => substr($userMessage, 0, 100),
        'collected_data_keys' => array_keys($collectedData),
    ]);

    // ✅ CẢI TIẾN: Nếu không có questions và fields, kiểm tra xem có phải câu hỏi thông thường không
    if (empty($questions) && empty($fields)) {
        Log::warning('🔵 [executeCollectInfoStep] No questions or fields configured', [
            'step' => $step,
            'user_message' => substr($userMessage, 0, 100),
        ]);
        
        // ✅ CẢI TIẾN: Kiểm tra xem có phải câu hỏi thông thường không
        // Nếu là câu hỏi thông thường, fallback về handleGenericRequest
        $intent = $this->intentRecognizer->recognize($userMessage, [
            'session' => $session ?? null,
            'assistant' => $assistant,
            'collected_data' => $collectedData,
        ]);
        
        $isGeneralQuestion = $this->isGeneralQuestion($userMessage, $intent);
        
        if ($isGeneralQuestion) {
            Log::info('🔵 [executeCollectInfoStep] Detected general question, falling back to handleGenericRequest', [
                'user_message' => substr($userMessage, 0, 100),
            ]);
            
            // Fallback về handleGenericRequest để trả lời trực tiếp
            return $this->handleGenericRequest($userMessage, $session ?? null, $assistant, $intent);
        }
        
        // Nếu không phải câu hỏi thông thường, mới hỏi lại
        $professionalResponse = "Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin. "
            . "Quý anh/chị vui lòng cung cấp các thông tin cần thiết để tôi có thể tiếp tục hỗ trợ quý anh/chị.";

        return [
            'response' => $professionalResponse,
            'completed' => false,
        ];
    }

    // ... (giữ nguyên logic hiện tại cho questions và fields)
}
```

---

### 4. Cải Thiện Intent Recognition

#### 4.1. Nhận diện tốt hơn các câu hỏi thông thường

**Vấn đề**: Intent recognizer có thể nhận diện sai câu hỏi thông thường thành intent khác.

**Giải pháp**: Cải thiện system prompt trong IntentRecognizer.

```php
// Trong IntentRecognizer.php

protected function buildSystemPrompt(array $context): string
{
    $assistant = $context['assistant'] ?? null;
    $assistantType = $assistant?->assistant_type?->value ?? 'unknown';
    
    $prompt = "Bạn là một AI chuyên phân tích intent (ý định) của người dùng trong hệ thống hành chính công.\n\n";
    
    // ✅ CẢI TIẾN: Thêm hướng dẫn về câu hỏi thông thường
    $prompt .= "**PHÂN LOẠI INTENT:**\n\n";
    
    $prompt .= "1. **ask_question** (Câu hỏi thông thường):\n";
    $prompt .= "   - Câu hỏi về thông tin thực tế: \"Hà Nội có bao nhiêu tỉnh?\", \"Việt Nam có bao nhiêu tỉnh thành?\"\n";
    $prompt .= "   - Câu hỏi về kiến thức: \"GDP là gì?\", \"Công văn là gì?\"\n";
    $prompt .= "   - Câu hỏi về địa lý, hành chính: \"Xã A có bao nhiêu dân?\", \"Tỉnh B có bao nhiêu huyện?\"\n";
    $prompt .= "   - Câu hỏi về chức năng: \"Bạn làm được gì?\"\n";
    $prompt .= "   → **Lưu ý**: Câu hỏi thông thường KHÔNG cần workflow/steps, chỉ cần trả lời trực tiếp\n\n";
    
    $prompt .= "2. **draft_document** (Soạn thảo văn bản):\n";
    $prompt .= "   - \"Tôi muốn soạn thảo công văn\"\n";
    $prompt .= "   - \"Giúp tôi tạo quyết định\"\n";
    $prompt .= "   → **Lưu ý**: Cần workflow để thu thập thông tin\n\n";
    
    $prompt .= "3. **create_report** (Tạo báo cáo):\n";
    $prompt .= "   - \"Tôi muốn tạo báo cáo\"\n";
    $prompt .= "   - \"Làm báo cáo thường niên\"\n";
    $prompt .= "   → **Lưu ý**: Cần workflow để thu thập thông tin\n\n";
    
    // ... (các intent khác)
    
    $prompt .= "**QUAN TRỌNG**:\n";
    $prompt .= "- Nếu người dùng hỏi câu hỏi thông thường về thông tin thực tế → intent = 'ask_question', KHÔNG có entity cụ thể\n";
    $prompt .= "- Nếu người dùng yêu cầu thực hiện tác vụ → intent tương ứng với tác vụ, có thể có entity\n";
    
    return $prompt;
}
```

---

## 📐 Implementation Checklist

### Phase 0: Tự động phân loại khi tạo Assistant (ƯU TIÊN)
- [ ] Implement `shouldAssistantHaveSteps()` trong AdminController
- [ ] Implement `analyzeIfNeedsSteps()` với AI detection
- [ ] Implement `shouldAutoGenerateSteps()` 
- [ ] Implement `autoGenerateSteps()` (hoặc tích hợp API generate-steps)
- [ ] Cập nhật `createAssistant()` để tự động phân loại
- [ ] Cải thiện UI: Ẩn Steps Manager cho Q&A assistant
- [ ] Thêm thông báo cho Q&A assistant trong UI
- [ ] Cải thiện SmartAssistantEngine: Q&A không trigger steps
- [ ] Implement `searchWeb()` cho Q&A assistant (tìm kiếm trên mạng)
- [ ] Implement `generateAnswerFromWebSearch()` 
- [ ] Test tạo Q&A assistant → Không có steps
- [ ] Test tạo assistant "Viết sách" → Tự động tạo steps
- [ ] Test Q&A assistant: Có file → Trả lời dựa trên file
- [ ] Test Q&A assistant: Không có file → Tìm trên mạng → ChatGPT

### Phase 1: Cải thiện nhận diện câu hỏi
- [ ] Nâng cấp `isGeneralQuestion()` với AI detection
- [ ] Thêm pattern matching cho các câu hỏi thông thường
- [ ] Cải thiện logic trigger steps
- [ ] Test với các câu hỏi khác nhau

### Phase 2: Cải thiện system prompt
- [ ] Cập nhật `buildProfessionalSystemPrompt()` với hướng dẫn rõ ràng
- [ ] Thêm `getAssistantTypeContext()` để cung cấp context theo loại assistant
- [ ] Cải thiện `buildChatMessages()` để truyền context đầy đủ
- [ ] Test với các loại assistant khác nhau

### Phase 3: Cải thiện xử lý steps
- [ ] Cải thiện `executeCollectInfoStep()` để fallback về `handleGenericRequest()` khi cần
- [ ] Thêm check câu hỏi thông thường trong steps
- [ ] Test với các trường hợp edge case

### Phase 4: Cải thiện Intent Recognition
- [ ] Cập nhật system prompt trong `IntentRecognizer`
- [ ] Thêm hướng dẫn về phân biệt câu hỏi thông thường vs yêu cầu workflow
- [ ] Test với các câu hỏi khác nhau

### Phase 5: Testing & Refinement
- [ ] Test end-to-end với câu hỏi: "Hà Nội có bao nhiêu tỉnh?"
- [ ] Test với các câu hỏi thông thường khác
- [ ] Test với yêu cầu cần workflow để đảm bảo không bị ảnh hưởng
- [ ] Thu thập feedback và điều chỉnh

---

## 🎯 Ví Dụ Cải Tiến

### Trước khi cải tiến:
- **User**: "hà nội bây giờ có bao nhiêu tỉnh"
- **AI**: "Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin. Quý anh/chị vui lòng cung cấp các thông tin cần thiết để tôi có thể tiếp tục hỗ trợ quý anh/chị."

### Sau khi cải tiến:
- **User**: "hà nội bây giờ có bao nhiêu tỉnh"
- **AI**: "Xin chào quý anh/chị! Hà Nội hiện tại là một thành phố trực thuộc Trung ương, không phải tỉnh. Hà Nội có 30 quận/huyện và 584 phường/xã/thị trấn. Nếu quý anh/chị muốn biết về số lượng tỉnh thành của Việt Nam, hiện tại Việt Nam có 63 tỉnh thành (58 tỉnh và 5 thành phố trực thuộc Trung ương). Quý anh/chị có cần tôi hỗ trợ thêm điều gì không?"

---

## 🚀 Kết Luận

Phương án này tập trung vào:

1. ✅ **Tự động phân loại**: Khi tạo assistant, tự động xác định khi nào cần steps và khi nào không cần
2. ✅ **Q&A Assistant thông minh**: Không có steps, tự động tìm kiếm trên mạng khi không có file
3. ✅ **Nhận diện chính xác**: Sử dụng AI để phân biệt rõ câu hỏi thông thường vs yêu cầu workflow
4. ✅ **Trả lời trực tiếp**: Cải thiện system prompt để AI trả lời câu hỏi thay vì hỏi lại
5. ✅ **Logic tối ưu**: Không trigger steps khi không cần thiết
6. ✅ **Fallback thông minh**: Khi vào steps nhưng không có config, fallback về generic request

**Lợi ích**:
- Trải nghiệm người dùng tốt hơn: AI trả lời trực tiếp câu hỏi
- Giảm nhầm lẫn: Không hỏi lại khi không cần
- Linh hoạt: Vẫn hỗ trợ workflow khi cần thiết
- Dễ maintain: Code rõ ràng, có logging đầy đủ

---

*Phương án này được thiết kế để giải quyết vấn đề chatbot không trả lời câu hỏi thông thường một cách hiệu quả.*

---

## 🌐 Phương Án: Thêm URL Tham Khảo Cho Q&A Assistant

### 📋 Tổng Quan

**Vấn đề hiện tại:**
- Khi tạo Q&A assistant (ví dụ: Trợ lý luật đất đai) mà không có tài liệu upload, hệ thống sẽ tìm kiếm trên mạng với Gemini Web Search
- Tuy nhiên, kết quả tìm kiếm có thể không chính xác hoặc không phù hợp với lĩnh vực cụ thể
- Admin không thể chỉ định các nguồn tham khảo cụ thể, đáng tin cậy

**Yêu cầu:**
- Thêm ô input để admin nhập URL tham khảo khi tạo assistant
- Chatbot sẽ crawl/index nội dung từ các URL này
- Khi không có tài liệu, chatbot sẽ ưu tiên sử dụng nội dung từ URL tham khảo để trả lời
- Nếu không tìm thấy trong URL tham khảo, mới fallback sang web search

---

### 🎯 Mục Tiêu

1. **Cho phép admin chỉ định nguồn tham khảo**: Admin có thể nhập các URL đáng tin cậy (ví dụ: trang web luật, quy định pháp luật)
2. **Tự động crawl và index**: Hệ thống tự động crawl nội dung từ URL và index vào vector database
3. **Ưu tiên nguồn tham khảo**: Khi trả lời, ưu tiên tìm kiếm trong nội dung đã crawl từ URL tham khảo
4. **Fallback thông minh**: Nếu không tìm thấy trong URL tham khảo, mới tìm kiếm trên mạng

---

### 💡 Phương Án Chi Tiết

#### 1. Database Schema

**Option 1: Lưu trong config (Đơn giản, nhanh)**
- Thêm trường `reference_urls` vào `config` JSON của `ai_assistants`
- Format: `{"reference_urls": ["url1", "url2", ...]}`

**Option 2: Tạo bảng riêng (Linh hoạt, mở rộng tốt) - KHUYẾN NGHỊ**

Tạo migration mới:

```php
// database/migrations/xxxx_create_assistant_reference_urls_table.php
Schema::create('assistant_reference_urls', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ai_assistant_id')->constrained('ai_assistants')->onDelete('cascade');
    $table->string('url', 500);
    $table->string('title')->nullable()->comment('Tiêu đề trang web (tự động crawl)');
    $table->text('description')->nullable()->comment('Mô tả ngắn (tự động crawl)');
    $table->enum('status', ['pending', 'crawling', 'indexed', 'failed'])->default('pending');
    $table->text('crawled_content')->nullable()->comment('Nội dung đã crawl (có thể lưu tạm)');
    $table->integer('content_length')->nullable()->comment('Độ dài nội dung (bytes)');
    $table->timestamp('last_crawled_at')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();
    
    $table->index('ai_assistant_id');
    $table->index('status');
});
```

**Lợi ích:**
- Dễ quản lý và theo dõi trạng thái crawl
- Có thể crawl lại khi cần (update nội dung)
- Có thể thêm metadata (title, description)
- Dễ debug khi có lỗi

---

#### 2. Model và Relationship

```php
// app/Models/AiAssistant.php

public function referenceUrls(): HasMany
{
    return $this->hasMany(AssistantReferenceUrl::class);
}

// app/Models/AssistantReferenceUrl.php
class AssistantReferenceUrl extends Model
{
    protected $fillable = [
        'ai_assistant_id',
        'url',
        'title',
        'description',
        'status',
        'crawled_content',
        'content_length',
        'last_crawled_at',
        'error_message',
    ];
    
    protected $casts = [
        'last_crawled_at' => 'datetime',
    ];
    
    public function aiAssistant(): BelongsTo
    {
        return $this->belongsTo(AiAssistant::class);
    }
}
```

---

#### 3. UI - Form Tạo Assistant

**Thêm vào `resources/js/Pages/Admin/CreateAssistant.vue`:**

```vue
<!-- Sau phần Documents Upload, thêm: -->
<div v-if="form.assistant_type === 'qa_based_document'">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        URL Tham Khảo (Tùy chọn)
    </label>
    <div class="space-y-2">
        <div
            v-for="(url, index) in form.reference_urls"
            :key="index"
            class="flex items-center gap-2"
        >
            <input
                v-model="form.reference_urls[index]"
                type="url"
                placeholder="https://example.com/page"
                class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button
                type="button"
                @click="removeReferenceUrl(index)"
                class="text-red-600 hover:text-red-800 px-2"
            >
                ✕
            </button>
        </div>
        <button
            type="button"
            @click="addReferenceUrl"
            class="text-sm text-blue-600 hover:text-blue-800"
        >
            + Thêm URL
        </button>
    </div>
    <p class="text-xs text-gray-500 mt-1">
        Nhập các URL tham khảo đáng tin cậy. AI sẽ tự động crawl và index nội dung từ các URL này để trả lời câu hỏi.
    </p>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-2">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <div class="text-sm text-blue-700">
                <p class="font-medium">Lưu ý về URL tham khảo</p>
                <ul class="list-disc list-inside mt-1 space-y-1">
                    <li>Nếu không có tài liệu upload, chatbot sẽ ưu tiên tìm kiếm trong nội dung từ URL tham khảo</li>
                    <li>Nếu không tìm thấy trong URL tham khảo, chatbot sẽ tìm kiếm trên mạng</li>
                    <li>Ví dụ URL phù hợp: trang web luật, quy định pháp luật, tài liệu chính thức</li>
                </ul>
            </div>
        </div>
    </div>
</div>
```

**Thêm vào script:**

```javascript
const form = ref({
    name: '',
    description: '',
    assistant_type: 'qa_based_document',
    steps: [],
    reference_urls: [], // Thêm dòng này
});

const addReferenceUrl = () => {
    form.value.reference_urls.push('');
};

const removeReferenceUrl = (index) => {
    form.value.reference_urls.splice(index, 1);
};
```

---

#### 4. Backend - Controller

**Cập nhật `app/Http/Controllers/AdminController.php` hoặc `AssistantController.php`:**

```php
public function store(Request $request)
{
    // ... existing validation ...
    
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'assistant_type' => ['required', 'string', Rule::in(\App\Enums\AssistantType::all())],
        'documents' => 'nullable|array',
        'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
        'reference_urls' => 'nullable|array',
        'reference_urls.*' => 'url|max:500',
        // ... other fields ...
    ]);
    
    // ... existing code ...
    
    DB::beginTransaction();
    try {
        // Create assistant
        $assistant = AiAssistant::create([
            'admin_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'assistant_type' => $data['assistant_type'],
            'config' => $config,
            'is_active' => true,
        ]);
        
        // ✅ MỚI: Lưu reference URLs
        if (!empty($data['reference_urls'])) {
            foreach ($data['reference_urls'] as $url) {
                if (!empty(trim($url))) {
                    $assistant->referenceUrls()->create([
                        'url' => trim($url),
                        'status' => 'pending',
                    ]);
                }
            }
            
            // ✅ MỚI: Queue job để crawl URLs (async)
            dispatch(new CrawlReferenceUrlsJob($assistant->id));
        }
        
        // ... rest of the code ...
        
        DB::commit();
        
        return redirect()->route('admin.assistants.index')
            ->with('success', 'Assistant đã được tạo thành công!');
            
    } catch (\Exception $e) {
        DB::rollBack();
        // ... error handling ...
    }
}
```

---

#### 5. Service - Web Crawler

**Tạo `app/Services/WebCrawlerService.php`:**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\AssistantReferenceUrl;

class WebCrawlerService
{
    /**
     * Crawl content from URL
     */
    public function crawlUrl(string $url): array
    {
        try {
            Log::info('Starting to crawl URL', ['url' => $url]);
            
            // Fetch HTML với User-Agent hợp lệ
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get($url);
            
            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }
            
            $html = $response->body();
            
            // Parse HTML
            $crawler = new Crawler($html);
            
            // Extract title
            $title = $this->extractTitle($crawler, $url);
            
            // Extract main content
            $content = $this->extractContent($crawler);
            
            // Extract description (meta description hoặc first paragraph)
            $description = $this->extractDescription($crawler);
            
            Log::info('Successfully crawled URL', [
                'url' => $url,
                'title' => $title,
                'content_length' => strlen($content),
            ]);
            
            return [
                'success' => true,
                'title' => $title,
                'description' => $description,
                'content' => $content,
                'content_length' => strlen($content),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to crawl URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Extract title from HTML
     */
    protected function extractTitle(Crawler $crawler, string $url): string
    {
        // Try multiple selectors
        $selectors = [
            'h1',
            'title',
            '.title',
            '.document-title',
            'h2.title',
            '[class*="title"]',
        ];
        
        foreach ($selectors as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $title = trim($nodes->first()->text());
                    if (!empty($title) && strlen($title) > 5) {
                        return $title;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback: Extract from URL
        $parsedUrl = parse_url($url);
        return $parsedUrl['host'] ?? 'Untitled';
    }
    
    /**
     * Extract main content from HTML
     */
    protected function extractContent(Crawler $crawler): string
    {
        // Remove script, style, nav, footer, etc.
        $crawler->filter('script, style, nav, footer, header, .sidebar, .menu, .navigation')->each(function (Crawler $node) {
            $node->getNode(0)->parentNode->removeChild($node->getNode(0));
        });
        
        // Try to find main content area
        $contentSelectors = [
            'main',
            'article',
            '.content',
            '.main-content',
            '.post-content',
            '.document-content',
            '[class*="content"]',
            'body',
        ];
        
        foreach ($contentSelectors as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $content = trim($nodes->first()->text());
                    if (strlen($content) > 200) {
                        return $content;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback: Get all text
        return trim($crawler->text());
    }
    
    /**
     * Extract description
     */
    protected function extractDescription(Crawler $crawler): ?string
    {
        // Try meta description
        try {
            $metaDesc = $crawler->filter('meta[name="description"]');
            if ($metaDesc->count() > 0) {
                $desc = $metaDesc->attr('content');
                if (!empty($desc)) {
                    return trim($desc);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Try first paragraph
        try {
            $paragraphs = $crawler->filter('p');
            if ($paragraphs->count() > 0) {
                $firstPara = trim($paragraphs->first()->text());
                if (strlen($firstPara) > 50 && strlen($firstPara) < 300) {
                    return $firstPara;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return null;
    }
}
```

---

#### 6. Job - Crawl URLs Async

**Tạo `app/Jobs/CrawlReferenceUrlsJob.php`:**

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AiAssistant;
use App\Services\WebCrawlerService;
use App\Services\VectorSearchService;
use Illuminate\Support\Facades\Log;

class CrawlReferenceUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $assistantId;

    public function __construct(int $assistantId)
    {
        $this->assistantId = $assistantId;
    }

    public function handle(
        WebCrawlerService $crawlerService,
        VectorSearchService $vectorSearchService
    ): void {
        $assistant = AiAssistant::find($this->assistantId);
        
        if (!$assistant) {
            Log::warning('Assistant not found for crawling', [
                'assistant_id' => $this->assistantId,
            ]);
            return;
        }
        
        $referenceUrls = $assistant->referenceUrls()
            ->where('status', 'pending')
            ->orWhere('status', 'failed')
            ->get();
        
        foreach ($referenceUrls as $referenceUrl) {
            try {
                // Update status to crawling
                $referenceUrl->update([
                    'status' => 'crawling',
                    'error_message' => null,
                ]);
                
                // Crawl URL
                $result = $crawlerService->crawlUrl($referenceUrl->url);
                
                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'Unknown error');
                }
                
                // Save crawled content
                $referenceUrl->update([
                    'title' => $result['title'] ?? null,
                    'description' => $result['description'] ?? null,
                    'crawled_content' => $result['content'],
                    'content_length' => $result['content_length'],
                    'last_crawled_at' => now(),
                    'status' => 'indexed',
                ]);
                
                // ✅ Index vào vector database
                // Chia content thành chunks và index
                $chunks = $this->chunkContent($result['content'], $referenceUrl->url);
                
                foreach ($chunks as $chunk) {
                    $vectorSearchService->indexChunk(
                        $chunk['content'],
                        $assistant->id,
                        [
                            'source_type' => 'reference_url',
                            'source_url' => $referenceUrl->url,
                            'title' => $result['title'] ?? null,
                            'chunk_index' => $chunk['index'],
                        ]
                    );
                }
                
                Log::info('Successfully crawled and indexed reference URL', [
                    'assistant_id' => $assistant->id,
                    'url' => $referenceUrl->url,
                    'chunks_count' => count($chunks),
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to crawl reference URL', [
                    'assistant_id' => $assistant->id,
                    'url' => $referenceUrl->url,
                    'error' => $e->getMessage(),
                ]);
                
                $referenceUrl->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
            
            // Rate limiting: delay 2-5 seconds between URLs
            sleep(rand(2, 5));
        }
    }
    
    /**
     * Chunk content for vector indexing
     */
    protected function chunkContent(string $content, string $url): array
    {
        // Chia content thành chunks ~500-1000 ký tự
        $chunkSize = 800;
        $chunks = [];
        $contentLength = mb_strlen($content);
        
        for ($i = 0; $i < $contentLength; $i += $chunkSize) {
            $chunk = mb_substr($content, $i, $chunkSize);
            if (mb_strlen(trim($chunk)) > 100) { // Chỉ lấy chunks có nội dung đủ dài
                $chunks[] = [
                    'index' => count($chunks),
                    'content' => trim($chunk),
                ];
            }
        }
        
        return $chunks;
    }
}
```

---

#### 7. Cập Nhật SmartAssistantEngine - Ưu Tiên Reference URLs

**Cập nhật `app/Services/SmartAssistantEngine.php`:**

```php
protected function handleAskQuestion(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent): array
{
    if ($assistant->assistant_type !== 'qa_based_document') {
        return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
    }
    
    try {
        // ✅ BƯỚC 1: Check if assistant has documents
        $documentsCount = $assistant->documents()->where('status', 'indexed')->count();
        
        if ($documentsCount > 0) {
            // ✅ Có documents → Tìm kiếm trong documents
            $searchResults = $this->vectorSearchService->searchSimilar($userMessage, $assistant->id, 5);
            
            if (!empty($searchResults)) {
                $context = array_map(fn($r) => $r['content'], $searchResults);
                $answer = $this->generateAnswerFromContext($userMessage, $context, $assistant);
                
                return [
                    'response' => $answer,
                    'workflow_state' => null,
                    'sources' => array_map(fn($r) => [
                        'content' => substr($r['content'], 0, 200),
                        'similarity' => $r['similarity'],
                        'source_type' => $r['metadata']['source_type'] ?? 'document',
                    ], $searchResults),
                ];
            }
        }
        
        // ✅ BƯỚC 2: Check if assistant has reference URLs (MỚI)
        $referenceUrlsCount = $assistant->referenceUrls()
            ->where('status', 'indexed')
            ->count();
        
        if ($referenceUrlsCount > 0) {
            // ✅ Có reference URLs → Tìm kiếm trong nội dung đã crawl
            // Filter chỉ lấy chunks từ reference URLs
            $searchResults = $this->vectorSearchService->searchSimilar(
                $userMessage,
                $assistant->id,
                5,
                0.7,
                ['source_type' => 'reference_url'] // Filter by source type
            );
            
            if (!empty($searchResults)) {
                $context = array_map(fn($r) => $r['content'], $searchResults);
                $answer = $this->generateAnswerFromContext($userMessage, $context, $assistant);
                
                // Get source URLs
                $sourceUrls = array_unique(array_map(function($r) {
                    return $r['metadata']['source_url'] ?? null;
                }, $searchResults));
                $sourceUrls = array_filter($sourceUrls);
                
                return [
                    'response' => $answer,
                    'workflow_state' => null,
                    'sources' => array_map(fn($r) => [
                        'content' => substr($r['content'], 0, 200),
                        'similarity' => $r['similarity'],
                        'source_type' => 'reference_url',
                        'source_url' => $r['metadata']['source_url'] ?? null,
                    ], $searchResults),
                    'reference_urls' => array_values($sourceUrls), // Thêm thông tin URL tham khảo
                ];
            }
        }
        
        // ✅ BƯỚC 3: Không có documents và reference URLs → Tìm kiếm trên mạng với Gemini
        Log::info('No documents or reference URLs, searching web with Gemini', [
            'assistant_id' => $assistant->id,
            'has_documents' => $documentsCount > 0,
            'has_reference_urls' => $referenceUrlsCount > 0,
        ]);
        
        $webSearchResult = $this->geminiWebSearchService->searchAndAnswer($userMessage, [
            'assistant_name' => $assistant->name,
            'assistant_description' => $assistant->description,
            'assistant' => $assistant,
        ]);
        
        return [
            'response' => $webSearchResult['answer'],
            'workflow_state' => null,
            'sources' => $webSearchResult['sources'],
            'search_results' => $webSearchResult['search_results'],
        ];
        
    } catch (\Exception $e) {
        // ... error handling ...
    }
}
```

**Lưu ý:** Cần cập nhật `VectorSearchService` để hỗ trợ filter theo metadata:

```php
// app/Services/VectorSearchService.php

public function searchSimilar(
    string $query,
    int $assistantId,
    int $limit = 5,
    float $minSimilarity = 0.7,
    array $metadataFilter = [] // Thêm parameter này
): array {
    // ... existing code ...
    
    // Apply metadata filter if provided
    if (!empty($metadataFilter)) {
        $results = array_filter($results, function($result) use ($metadataFilter) {
            $metadata = $result['metadata'] ?? [];
            foreach ($metadataFilter as $key => $value) {
                if (($metadata[$key] ?? null) !== $value) {
                    return false;
                }
            }
            return true;
        });
    }
    
    // ... rest of the code ...
}
```

---

#### 8. UI - Hiển Thị Trạng Thái Crawl

**Thêm vào trang Edit Assistant (`resources/js/Pages/Admin/EditAssistant.vue`):**

```vue
<!-- Hiển thị danh sách reference URLs và trạng thái -->
<div v-if="assistant.reference_urls && assistant.reference_urls.length > 0" class="mt-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">URL Tham Khảo</h3>
    <div class="space-y-3">
        <div
            v-for="(refUrl, index) in assistant.reference_urls"
            :key="index"
            class="border border-gray-200 rounded-lg p-4"
        >
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <a
                        :href="refUrl.url"
                        target="_blank"
                        class="text-blue-600 hover:text-blue-800 font-medium"
                    >
                        {{ refUrl.title || refUrl.url }}
                    </a>
                    <p v-if="refUrl.description" class="text-sm text-gray-600 mt-1">
                        {{ refUrl.description }}
                    </p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                        <span>Trạng thái: 
                            <span :class="{
                                'text-yellow-600': refUrl.status === 'pending',
                                'text-blue-600': refUrl.status === 'crawling',
                                'text-green-600': refUrl.status === 'indexed',
                                'text-red-600': refUrl.status === 'failed',
                            }">
                                {{ getStatusLabel(refUrl.status) }}
                            </span>
                        </span>
                        <span v-if="refUrl.content_length">
                            Nội dung: {{ formatBytes(refUrl.content_length) }}
                        </span>
                        <span v-if="refUrl.last_crawled_at">
                            Crawl lần cuối: {{ formatDate(refUrl.last_crawled_at) }}
                        </span>
                    </div>
                    <p v-if="refUrl.error_message" class="text-sm text-red-600 mt-2">
                        Lỗi: {{ refUrl.error_message }}
                    </p>
                </div>
                <button
                    v-if="refUrl.status === 'failed'"
                    @click="retryCrawl(refUrl.id)"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                >
                    Thử lại
                </button>
            </div>
        </div>
    </div>
</div>
```

---

### 📐 Implementation Checklist

#### Phase 1: Database & Model
- [ ] Tạo migration cho bảng `assistant_reference_urls`
- [ ] Tạo model `AssistantReferenceUrl`
- [ ] Thêm relationship `referenceUrls()` vào `AiAssistant` model
- [ ] Test migration và model

#### Phase 2: UI - Form Tạo Assistant
- [ ] Thêm input field cho reference URLs vào `CreateAssistant.vue`
- [ ] Thêm logic add/remove URLs
- [ ] Thêm validation cho URL format
- [ ] Thêm thông báo hướng dẫn cho user
- [ ] Test UI

#### Phase 3: Backend - Controller
- [ ] Cập nhật validation trong `store()` method
- [ ] Lưu reference URLs khi tạo assistant
- [ ] Queue crawl job sau khi tạo assistant
- [ ] Test API endpoint

#### Phase 4: Web Crawler Service
- [ ] Tạo `WebCrawlerService` class
- [ ] Implement `crawlUrl()` method
- [ ] Implement `extractTitle()`, `extractContent()`, `extractDescription()`
- [ ] Test với các URL khác nhau
- [ ] Xử lý lỗi và edge cases

#### Phase 5: Job - Async Crawling
- [ ] Tạo `CrawlReferenceUrlsJob`
- [ ] Implement crawl và index logic
- [ ] Implement chunking content
- [ ] Test job với queue
- [ ] Xử lý retry khi failed

#### Phase 6: Vector Search Integration
- [ ] Cập nhật `VectorSearchService` để hỗ trợ metadata filter
- [ ] Test search với filter `source_type = 'reference_url'`
- [ ] Đảm bảo chunks từ reference URLs được index đúng

#### Phase 7: SmartAssistantEngine Integration
- [ ] Cập nhật `handleAskQuestion()` để ưu tiên reference URLs
- [ ] Thêm logic fallback: documents → reference URLs → web search
- [ ] Test với các scenarios khác nhau
- [ ] Đảm bảo sources được trả về đúng

#### Phase 8: UI - Hiển Thị Trạng Thái
- [ ] Thêm hiển thị reference URLs trong Edit Assistant page
- [ ] Hiển thị trạng thái crawl (pending, crawling, indexed, failed)
- [ ] Thêm nút retry khi failed
- [ ] Test UI

#### Phase 9: Testing & Refinement
- [ ] Test end-to-end: Tạo assistant với reference URLs
- [ ] Test crawl với các loại URL khác nhau
- [ ] Test search và trả lời dựa trên reference URLs
- [ ] Test fallback khi không tìm thấy
- [ ] Performance testing
- [ ] Fix bugs và optimize

---

### 🎯 Ví Dụ Sử Dụng

**Scenario:** Admin tạo "Trợ lý luật đất đai"

1. **Tạo Assistant:**
   - Tên: "Trợ lý luật đất đai"
   - Loại: Q&A từ tài liệu
   - Không upload tài liệu
   - Thêm URL tham khảo:
     - `https://thuvienphapluat.vn/van-ban/Dat-dai-Xay-dung/Luat-Dat-dai-2013-45-2013-QH13-158617.aspx`
     - `https://chinhphu.vn/portal/page/portal/chinhphu/hethongvanban?class_id=1&mode=detail&document_id=202010`

2. **Hệ thống tự động:**
   - Crawl nội dung từ 2 URL
   - Index vào vector database
   - Hiển thị trạng thái: "Đã index"

3. **User hỏi:**
   - "Quy định về thời hạn sử dụng đất là gì?"

4. **Chatbot trả lời:**
   - Tìm kiếm trong nội dung đã crawl từ URL tham khảo
   - Trả lời dựa trên nội dung đó
   - Hiển thị source: URL tham khảo

---

### ⚠️ Lưu Ý Quan Trọng

1. **Rate Limiting:**
   - Delay 2-5 giây giữa các URL khi crawl
   - Tránh bị block bởi server

2. **Error Handling:**
   - Xử lý các trường hợp: URL không hợp lệ, timeout, 404, 403, etc.
   - Lưu error message để admin biết lý do failed

3. **Content Quality:**
   - Chỉ index nội dung có độ dài > 100 ký tự
   - Loại bỏ script, style, navigation, footer
   - Ưu tiên main content area

4. **Privacy & Legal:**
   - Chỉ crawl các trang công khai
   - Tuân thủ robots.txt
   - Không crawl dữ liệu cá nhân
   - Tôn trọng bản quyền

5. **Performance:**
   - Crawl async với queue để không block request
   - Cache crawled content nếu cần
   - Có thể crawl lại định kỳ để update nội dung

---

### 🚀 Kết Luận

Phương án này cho phép:
- ✅ Admin chỉ định nguồn tham khảo đáng tin cậy
- ✅ Chatbot trả lời chính xác hơn dựa trên nguồn cụ thể
- ✅ Giảm phụ thuộc vào web search không kiểm soát được
- ✅ Linh hoạt: Có thể thêm/sửa/xóa URL tham khảo
- ✅ Tự động hóa: Crawl và index không cần can thiệp thủ công

**Lợi ích:**
- Trả lời chính xác hơn với nguồn đáng tin cậy
- Kiểm soát được nguồn tham khảo
- Dễ mở rộng và maintain
- Phù hợp với các lĩnh vực chuyên sâu (luật, quy định, etc.)

---

*Phương án này bổ sung tính năng URL tham khảo cho Q&A Assistant, giúp chatbot trả lời chính xác và đáng tin cậy hơn.*

