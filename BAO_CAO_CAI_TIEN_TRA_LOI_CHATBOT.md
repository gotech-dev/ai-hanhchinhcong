# BÁO CÁO PHÂN TÍCH VÀ CẢI TIẾN: Chatbot Trả Lời Chưa Tự Nhiên - Giải Pháp Toàn Diện

## 🎯 YÊU CẦU TỔNG QUAN

Chatbot cần:
1. **Hiểu ngữ cảnh** - Thừa nhận những gì user vừa nói, hiểu context cuộc trò chuyện
2. **Trả lời lịch sự, tự nhiên, linh hoạt** - Không cứng nhắc, không máy móc
3. **Có ví dụ gợi ý phù hợp** - Đưa ra ví dụ, gợi ý cụ thể dựa trên nhu cầu user

**Áp dụng cho TẤT CẢ các loại trợ lý**, không chỉ riêng một loại nào.

---

## 🔴 VẤN ĐỀ PHÁT HIỆN

### Mô tả vấn đề

**Ví dụ 1 - Trợ lý lập dàn ý viết tiểu thuyết:**
- **User**: "tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc"
- **Chatbot hiện tại**: "Quý anh/chị vui lòng cho tôi biết: Tiêu đề của tiểu thuyết là gì?"
- **Vấn đề**: Không thừa nhận user vừa nói gì, không có ví dụ gợi ý

**Ví dụ 2 - Trợ lý soạn thảo văn bản:**
- **User**: "tôi muốn soạn công văn"
- **Chatbot hiện tại**: "Quý anh/chị vui lòng cho tôi biết: Loại văn bản là gì?"
- **Vấn đề**: Không có ví dụ về các loại công văn, không giải thích rõ

**Ví dụ 3 - Trợ lý Q&A:**
- **User**: "hà nội có bao nhiêu tỉnh"
- **Chatbot hiện tại**: "Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin..."
- **Vấn đề**: Không trả lời trực tiếp câu hỏi, hỏi lại user

**Vấn đề chung**: 
- ❌ Câu trả lời quá cứng nhắc, máy móc
- ❌ Không thừa nhận ngữ cảnh từ message của user
- ❌ Thiếu ví dụ, gợi ý cụ thể
- ❌ Không tự nhiên, không linh hoạt

**Kỳ vọng**: 
- ✅ "Tuyệt vời! Bạn muốn viết tiểu thuyết kiếm hiệp Trung Quốc. Bạn đã có ý tưởng đặt tên cho tiểu thuyết chưa? Ví dụ tên tiểu thuyết là \"Thiên Long Bát Bộ\" hoặc \"Tiếu Ngạo Giang Hồ\"."
- ✅ "Rất vui được hỗ trợ bạn soạn công văn! Bạn muốn soạn công văn đi hay công văn đến? Ví dụ: Công văn đi thường dùng để gửi yêu cầu, chỉ thị; Công văn đến là văn bản nhận được từ cơ quan khác."
- ✅ "Hà Nội hiện tại là một thành phố trực thuộc Trung ương, không phải tỉnh. Hà Nội có 30 quận/huyện và 584 phường/xã/thị trấn..."

---

## 🔍 PHÂN TÍCH NGUYÊN NHÂN

### 1. Luồng xử lý hiện tại

Khi user gửi message "tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc":

1. **Intent Recognition**: Hệ thống nhận diện intent và xác định cần thu thập thông tin
2. **Workflow Planning**: Xác định cần thực thi step `collect_info` với câu hỏi "Tiêu đề của tiểu thuyết là gì?"
3. **executeCollectInfoStep()**: Thực thi step collect_info
   - Lấy câu hỏi từ config: `"Tiêu đề của tiểu thuyết là gì?"`
   - Gọi `formatQuestionProfessionally()` để format câu hỏi
4. **formatQuestionProfessionally()**: Chỉ thêm prefix "Quý anh/chị vui lòng cho tôi biết: " mà không xem xét:
   - Ngữ cảnh cuộc trò chuyện
   - Message vừa rồi của user
   - Nội dung đã được đề cập

### 2. Code hiện tại

**File**: `app/Services/SmartAssistantEngine.php`

**Method `executeCollectInfoStep()`** (dòng 1835-1935):
```php
if ($nextQuestionIndex < count($questions)) {
    $nextQuestion = $questions[$nextQuestionIndex];
    $askedQuestions[] = $nextQuestion;
    $collectedData['_asked_questions'] = $askedQuestions;

    // ✅ FIX: Format câu hỏi chuyên nghiệp, lịch sự
    $formattedQuestion = $this->formatQuestionProfessionally($nextQuestion, $assistant);
    
    return [
        'response' => $formattedQuestion,
        'completed' => false,
        'data' => $collectedData,
    ];
}
```

**Method `formatQuestionProfessionally()`** (dòng 2653-2676):
```php
protected function formatQuestionProfessionally(string $question, AiAssistant $assistant): string
{
    // Nếu câu hỏi đã có format chuyên nghiệp, giữ nguyên
    if (str_contains($question, 'quý anh/chị') || str_contains($question, 'vui lòng')) {
        return $question;
    }
    
    // Format lại câu hỏi cho chuyên nghiệp
    $formatted = trim($question);
    
    // Thêm prefix lịch sự nếu chưa có
    if (!str_starts_with(mb_strtolower($formatted), 'quý anh/chị') && 
        !str_starts_with(mb_strtolower($formatted), 'bạn') &&
        !str_starts_with(mb_strtolower($formatted), 'anh/chị')) {
        $formatted = "Quý anh/chị vui lòng cho tôi biết: " . $formatted;
    }
    
    // Đảm bảo có dấu chấm hỏi
    if (!str_ends_with($formatted, '?') && !str_ends_with($formatted, '？')) {
        $formatted .= '?';
    }
    
    return $formatted;
}
```

### 3. Vấn đề cụ thể

#### Vấn đề 1: Không có ngữ cảnh
- `formatQuestionProfessionally()` chỉ nhận `$question` và `$assistant`
- Không nhận `$userMessage` (message vừa rồi của user)
- Không nhận `$session` (để lấy conversation history)
- Không nhận `$collectedData` (để biết đã thu thập gì)

#### Vấn đề 2: Format cứng nhắc
- Chỉ thêm prefix "Quý anh/chị vui lòng cho tôi biết: " một cách máy móc
- Không thừa nhận những gì user vừa nói
- Không tạo cảm giác đối thoại tự nhiên

#### Vấn đề 3: Không có ví dụ hoặc gợi ý
- Câu hỏi chỉ đơn giản là "Tiêu đề của tiểu thuyết là gì?"
- Không có ví dụ hoặc gợi ý để user dễ trả lời hơn

---

## 💡 Ý TƯỞNG CẢI TIẾN

### Phương án 1: Sử dụng AI để tạo câu hỏi tự nhiên (KHUYẾN NGHỊ)

**Ý tưởng**: Thay vì chỉ format câu hỏi, sử dụng AI để tạo một câu trả lời tự nhiên, có ngữ cảnh.

**Cách thực hiện**:
1. Tạo method mới `generateContextualQuestion()` sử dụng OpenAI
2. Method này nhận:
   - Câu hỏi cần hỏi (từ step config)
   - User message vừa rồi
   - Conversation history (nếu có)
   - Assistant context
   - Collected data (để biết đã thu thập gì)
3. AI sẽ tạo một câu trả lời tự nhiên:
   - Thừa nhận những gì user vừa nói
   - Đặt câu hỏi một cách tự nhiên
   - Có thể thêm ví dụ hoặc gợi ý

**Ưu điểm**:
- ✅ Tự nhiên, có ngữ cảnh
- ✅ Linh hoạt, có thể thích ứng với nhiều tình huống
- ✅ Có thể thêm ví dụ, gợi ý phù hợp

**Nhược điểm**:
- ⚠️ Tốn thêm 1 API call đến OpenAI
- ⚠️ Có thể chậm hơn một chút

### Phương án 2: Cải thiện formatQuestionProfessionally với template

**Ý tưởng**: Cải thiện method `formatQuestionProfessionally()` để nhận thêm context và sử dụng template thông minh hơn.

**Cách thực hiện**:
1. Thêm parameters: `$userMessage`, `$session`, `$collectedData`
2. Phân tích `$userMessage` để tìm keywords
3. Tạo template phù hợp dựa trên context
4. Ví dụ: Nếu user nói về "tiểu thuyết kiếm hiệp", thì câu hỏi có thể là "Bạn đã có ý tưởng đặt tên cho tiểu thuyết kiếm hiệp chưa?"

**Ưu điểm**:
- ✅ Nhanh, không cần API call
- ✅ Đơn giản, dễ implement

**Nhược điểm**:
- ⚠️ Vẫn cứng nhắc hơn so với AI
- ⚠️ Khó xử lý các tình huống phức tạp

### Phương án 3: Kết hợp cả hai

**Ý tưởng**: 
- Sử dụng template thông minh cho các trường hợp đơn giản
- Sử dụng AI cho các trường hợp phức tạp hoặc khi cần ngữ cảnh cao

**Cách thực hiện**:
1. Kiểm tra độ phức tạp của context
2. Nếu đơn giản → dùng template
3. Nếu phức tạp → dùng AI

---

## ✅ GIẢI PHÁP ĐỀ XUẤT (Phương án 1 - KHUYẾN NGHỊ)

### 1. Tạo method mới `generateContextualQuestion()`

**File**: `app/Services/SmartAssistantEngine.php`

**Method mới**:
```php
/**
 * Generate contextual, natural question using AI
 * 
 * @param string $question The question to ask (from step config)
 * @param string $userMessage The user's recent message
 * @param ChatSession|null $session The chat session (for conversation history)
 * @param AiAssistant $assistant The assistant
 * @param array $collectedData Already collected data
 * @return string Natural, contextual question
 */
protected function generateContextualQuestion(
    string $question,
    string $userMessage,
    ?ChatSession $session,
    AiAssistant $assistant,
    array $collectedData = []
): string {
    try {
        // Build system prompt
        $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
        $systemPrompt .= "\n\n**NHIỆM VỤ:**\n";
        $systemPrompt .= "Bạn cần hỏi người dùng một câu hỏi, nhưng hãy làm cho nó tự nhiên và có ngữ cảnh.\n";
        $systemPrompt .= "- Thừa nhận những gì người dùng vừa nói\n";
        $systemPrompt .= "- Đặt câu hỏi một cách tự nhiên, không cứng nhắc\n";
        $systemPrompt .= "- Có thể thêm ví dụ hoặc gợi ý nếu phù hợp\n";
        $systemPrompt .= "- Sử dụng ngôn ngữ thân thiện nhưng vẫn chuyên nghiệp\n";
        $systemPrompt .= "- Tránh các cụm từ quá trang trọng như 'Quý anh/chị vui lòng cho tôi biết'\n\n";
        $systemPrompt .= "**VÍ DỤ TỐT:**\n";
        $systemPrompt .= "- User: 'tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc'\n";
        $systemPrompt .= "  Question cần hỏi: 'Tiêu đề của tiểu thuyết là gì?'\n";
        $systemPrompt .= "  → Trả lời: 'Tuyệt vời! Bạn muốn viết tiểu thuyết kiếm hiệp Trung Quốc. Bạn đã có ý tưởng đặt tên cho tiểu thuyết chưa? Ví dụ tên tiểu thuyết là \"Thiên Long Bát Bộ\" hoặc \"Tiếu Ngạo Giang Hồ\".'\n\n";
        $systemPrompt .= "**VÍ DỤ KHÔNG TỐT:**\n";
        $systemPrompt .= "- 'Quý anh/chị vui lòng cho tôi biết: Tiêu đề của tiểu thuyết là gì?' (quá cứng nhắc, không thừa nhận context)\n\n";

        // Build user prompt
        $userPrompt = "**Câu hỏi cần hỏi:** {$question}\n\n";
        $userPrompt .= "**Tin nhắn vừa rồi của người dùng:** {$userMessage}\n\n";
        
        if (!empty($collectedData)) {
            $userPrompt .= "**Thông tin đã thu thập:**\n";
            foreach ($collectedData as $key => $value) {
                if (!str_starts_with($key, '_')) { // Skip internal keys
                    $userPrompt .= "- {$key}: {$value}\n";
                }
            }
            $userPrompt .= "\n";
        }

        // Add conversation history if available
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        if ($session) {
            // Get last 3 messages for context (excluding current user message)
            $previousMessages = $session->messages()
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->reverse();
            
            foreach ($previousMessages as $msg) {
                if ($msg->content !== $userMessage) { // Skip duplicate
                    $messages[] = [
                        'role' => $msg->sender === 'user' ? 'user' : 'assistant',
                        'content' => substr($msg->content, 0, 500), // Limit length
                    ];
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userPrompt,
        ];

        // Call OpenAI
        $response = OpenAI::chat()->create([
            'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
            'messages' => $messages,
            'temperature' => 0.7, // Slightly creative for natural responses
            'max_tokens' => 200, // Limit response length
        ]);

        $generatedQuestion = trim($response->choices[0]->message->content);
        
        // Fallback nếu response rỗng hoặc quá ngắn
        if (empty($generatedQuestion) || strlen($generatedQuestion) < 10) {
            return $this->formatQuestionProfessionally($question, $assistant);
        }

        return $generatedQuestion;

    } catch (\Exception $e) {
        Log::error('Error generating contextual question', [
            'error' => $e->getMessage(),
            'question' => $question,
        ]);
        
        // Fallback về format cũ
        return $this->formatQuestionProfessionally($question, $assistant);
    }
}
```

### 2. Cập nhật `executeCollectInfoStep()` để sử dụng method mới

**Thay đổi trong `executeCollectInfoStep()`** (dòng 1864-1881):

```php
if ($nextQuestionIndex < count($questions)) {
    $nextQuestion = $questions[$nextQuestionIndex];
    $askedQuestions[] = $nextQuestion;
    $collectedData['_asked_questions'] = $askedQuestions;

    Log::info('🔵 [executeCollectInfoStep] Asking question', [
        'question_index' => $nextQuestionIndex,
        'question' => $nextQuestion,
    ]);

    // ✅ CẢI TIẾN: Sử dụng AI để tạo câu hỏi tự nhiên, có ngữ cảnh
    // Lấy session từ context nếu có
    $session = null; // TODO: Pass session to this method
    
    $formattedQuestion = $this->generateContextualQuestion(
        $nextQuestion,
        $userMessage,
        $session,
        $assistant,
        $collectedData
    );
    
    return [
        'response' => $formattedQuestion,
        'completed' => false,
        'data' => $collectedData,
    ];
}
```

### 3. Cập nhật signature của `executeCollectInfoStep()` để nhận `$session`

**Thay đổi**:
```php
// CŨ:
protected function executeCollectInfoStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant): array

// MỚI:
protected function executeCollectInfoStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant, ?ChatSession $session = null): array
```

**Cập nhật tất cả các nơi gọi method này**:
- Trong `executePredefinedSteps()` (dòng 1782)
- Các nơi khác nếu có

### 4. Tối ưu hóa: Cache hoặc skip AI call trong một số trường hợp

**Có thể thêm logic**:
- Nếu `$userMessage` rỗng hoặc không có context → dùng format cũ
- Nếu câu hỏi đã được format tốt rồi → giữ nguyên
- Có thể cache response cho các câu hỏi tương tự

---

## 📊 SO SÁNH CÁC PHƯƠNG ÁN

| Tiêu chí | Phương án 1 (AI) | Phương án 2 (Template) | Phương án 3 (Kết hợp) |
|----------|------------------|------------------------|----------------------|
| **Tự nhiên** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Có ngữ cảnh** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ |
| **Tốc độ** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Chi phí** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Dễ maintain** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Linh hoạt** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ |

**Kết luận**: Phương án 1 (AI) là tốt nhất về chất lượng, nhưng cần cân nhắc về chi phí và tốc độ. Phương án 3 (kết hợp) là cân bằng tốt nhất.

---

## 🔧 FILES CẦN SỬA

1. **`app/Services/SmartAssistantEngine.php`**
   - Thêm method `generateContextualQuestion()`
   - Cập nhật `executeCollectInfoStep()` để nhận `$session` và sử dụng method mới
   - Cập nhật tất cả các nơi gọi `executeCollectInfoStep()`

2. **Có thể cần test**:
   - Test với các loại assistant khác nhau
   - Test với các tình huống khác nhau
   - Monitor API usage và cost

---

## 📝 VÍ DỤ KẾT QUẢ MONG ĐỢI

### Trước khi cải tiến:
- **User**: "tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc"
- **Chatbot**: "Quý anh/chị vui lòng cho tôi biết: Tiêu đề của tiểu thuyết là gì?"

### Sau khi cải tiến:
- **User**: "tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc"
- **Chatbot**: "Tuyệt vời! Bạn muốn viết tiểu thuyết kiếm hiệp Trung Quốc. Bạn đã có ý tưởng đặt tên cho tiểu thuyết chưa? Ví dụ tên tiểu thuyết là \"Thiên Long Bát Bộ\" hoặc \"Tiếu Ngạo Giang Hồ\"."

### Hoặc:
- **User**: "tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc"
- **Chatbot**: "Rất thú vị! Tiểu thuyết kiếm hiệp Trung Quốc là một thể loại rất hay. Để tôi có thể hỗ trợ bạn tốt hơn, bạn đã nghĩ đến tên cho tiểu thuyết chưa? Nếu chưa, bạn có thể tham khảo các tên như \"Thiên Long Bát Bộ\", \"Tiếu Ngạo Giang Hồ\", hoặc \"Thần Điêu Đại Hiệp\"."

---

## 🎯 KẾT LUẬN

**Vấn đề chính**: Chatbot trả lời quá cứng nhắc, không tự nhiên, thiếu ngữ cảnh khi hỏi thông tin từ user.

**Nguyên nhân**: Method `formatQuestionProfessionally()` chỉ format câu hỏi một cách máy móc mà không xem xét ngữ cảnh cuộc trò chuyện.

**Giải pháp**: Sử dụng AI để tạo câu hỏi tự nhiên, có ngữ cảnh, thừa nhận những gì user vừa nói.

**Mức độ ưu tiên**: ⚠️ **CAO** - Ảnh hưởng trực tiếp đến trải nghiệm người dùng.

---

## 📌 LƯU Ý KHI TRIỂN KHAI

1. **API Cost**: Mỗi lần hỏi sẽ tốn thêm 1 API call. Cần monitor usage.
2. **Performance**: Có thể làm chậm response một chút (thêm ~0.5-1s). Cân nhắc cache hoặc async.
3. **Fallback**: Luôn có fallback về `formatQuestionProfessionally()` nếu AI call fail.
4. **Testing**: Test kỹ với nhiều loại assistant và tình huống khác nhau.
5. **A/B Testing**: Có thể test so sánh giữa phương án cũ và mới để đánh giá hiệu quả.

---

## 🔄 PHƯƠNG ÁN THAY THẾ (Nếu không muốn dùng AI)

Nếu không muốn tốn thêm API call, có thể cải thiện `formatQuestionProfessionally()` với logic đơn giản hơn:

```php
protected function formatQuestionProfessionally(
    string $question, 
    AiAssistant $assistant,
    string $userMessage = '',
    array $collectedData = []
): string {
    // Nếu có userMessage, thử tạo câu hỏi tự nhiên hơn
    if (!empty($userMessage)) {
        // Phân tích userMessage để tìm keywords
        $keywords = $this->extractKeywords($userMessage);
        
        // Tạo câu hỏi dựa trên keywords
        if (!empty($keywords)) {
            $contextualPrefix = $this->buildContextualPrefix($keywords, $question);
            if ($contextualPrefix) {
                return $contextualPrefix . $question . '?';
            }
        }
    }
    
    // Fallback về format cũ
    // ... existing code ...
}
```

Tuy nhiên, phương án này vẫn kém tự nhiên hơn so với dùng AI.

---

## 🚀 GIẢI PHÁP TOÀN DIỆN (KHUYẾN NGHỊ)

### Tổng quan

Tạo một **Response Enhancement Service** chung để xử lý tất cả các loại response, đảm bảo:
1. ✅ Hiểu ngữ cảnh từ conversation history và user message
2. ✅ Trả lời tự nhiên, lịch sự, linh hoạt
3. ✅ Có ví dụ, gợi ý phù hợp với nhu cầu user

### Kiến trúc giải pháp

```
┌─────────────────────────────────────────────────────────┐
│         SmartAssistantEngine / ChatController           │
│  (Tất cả các method tạo response)                      │
└──────────────────┬──────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│         ResponseEnhancementService                      │
│  - enhanceResponse() - Enhance bất kỳ response nào      │
│  - generateContextualQuestion() - Câu hỏi có ngữ cảnh  │
│  - generateContextualAnswer() - Câu trả lời có ngữ cảnh│
└──────────────────┬──────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│              OpenAI API                                  │
│  (Sử dụng để tạo response tự nhiên)                    │
└─────────────────────────────────────────────────────────┘
```

### 1. Tạo ResponseEnhancementService

**File mới**: `app/Services/ResponseEnhancementService.php`

```php
<?php

namespace App\Services;

use App\Models\AiAssistant;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ResponseEnhancementService
{
    /**
     * Enhance any response to be more natural, contextual, and helpful
     * 
     * @param string $rawResponse The raw response to enhance
     * @param string $userMessage The user's recent message
     * @param ChatSession|null $session The chat session (for conversation history)
     * @param AiAssistant $assistant The assistant
     * @param array $context Additional context (collected data, intent, etc.)
     * @param string $responseType Type of response: 'question', 'answer', 'info', 'error'
     * @return string Enhanced response
     */
    public function enhanceResponse(
        string $rawResponse,
        string $userMessage,
        ?ChatSession $session,
        AiAssistant $assistant,
        array $context = [],
        string $responseType = 'answer'
    ): string {
        try {
            // Build system prompt for enhancement
            $systemPrompt = $this->buildEnhancementSystemPrompt($assistant, $responseType);
            
            // Build user prompt with context
            $userPrompt = $this->buildEnhancementUserPrompt(
                $rawResponse,
                $userMessage,
                $session,
                $context,
                $responseType
            );
            
            // Call OpenAI
            $response = OpenAI::chat()->create([
                'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
                'temperature' => 0.7, // Slightly creative for natural responses
                'max_tokens' => 500, // Limit response length
            ]);
            
            $enhancedResponse = trim($response->choices[0]->message->content);
            
            // Fallback nếu response rỗng
            if (empty($enhancedResponse) || strlen($enhancedResponse) < 10) {
                Log::warning('Enhanced response is empty, using raw response', [
                    'raw_response' => substr($rawResponse, 0, 100),
                ]);
                return $rawResponse;
            }
            
            return $enhancedResponse;
            
        } catch (\Exception $e) {
            Log::error('Error enhancing response', [
                'error' => $e->getMessage(),
                'raw_response' => substr($rawResponse, 0, 100),
            ]);
            
            // Fallback về raw response
            return $rawResponse;
        }
    }
    
    /**
     * Generate contextual question (specialized for questions)
     */
    public function generateContextualQuestion(
        string $question,
        string $userMessage,
        ?ChatSession $session,
        AiAssistant $assistant,
        array $collectedData = []
    ): string {
        return $this->enhanceResponse(
            $question,
            $userMessage,
            $session,
            $assistant,
            ['collected_data' => $collectedData, 'is_question' => true],
            'question'
        );
    }
    
    /**
     * Build system prompt for enhancement
     */
    protected function buildEnhancementSystemPrompt(AiAssistant $assistant, string $responseType): string
    {
        $assistantName = $assistant->name ?? 'Trợ lý AI';
        $assistantDescription = $assistant->description ?? '';
        
        $prompt = "Bạn là {$assistantName}, một trợ lý AI chuyên nghiệp phục vụ trong lĩnh vực hành chính công.\n\n";
        
        if (!empty($assistantDescription)) {
            $prompt .= "**MÔ TẢ CHỨC NĂNG:**\n{$assistantDescription}\n\n";
        }
        
        $prompt .= "**NHIỆM VỤ CỦA BẠN:**\n";
        $prompt .= "Bạn cần cải thiện một câu trả lời/câu hỏi để làm cho nó:\n";
        $prompt .= "1. **Tự nhiên và lịch sự**: Không cứng nhắc, không máy móc\n";
        $prompt .= "2. **Có ngữ cảnh**: Thừa nhận những gì người dùng vừa nói\n";
        $prompt .= "3. **Có ví dụ, gợi ý**: Đưa ra ví dụ cụ thể, gợi ý phù hợp khi cần\n";
        $prompt .= "4. **Linh hoạt**: Thích ứng với tình huống cụ thể\n\n";
        
        if ($responseType === 'question') {
            $prompt .= "**KHI TẠO CÂU HỎI:**\n";
            $prompt .= "- Thừa nhận những gì người dùng vừa nói trước khi hỏi\n";
            $prompt .= "- Đặt câu hỏi một cách tự nhiên, không dùng cụm từ quá trang trọng như 'Quý anh/chị vui lòng cho tôi biết'\n";
            $prompt .= "- Thêm ví dụ hoặc gợi ý cụ thể để người dùng dễ trả lời\n";
            $prompt .= "- Sử dụng ngôn ngữ thân thiện nhưng vẫn chuyên nghiệp\n\n";
            
            $prompt .= "**VÍ DỤ TỐT:**\n";
            $prompt .= "- User: 'tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc'\n";
            $prompt .= "  Question cần hỏi: 'Tiêu đề của tiểu thuyết là gì?'\n";
            $prompt .= "  → Trả lời: 'Tuyệt vời! Bạn muốn viết tiểu thuyết kiếm hiệp Trung Quốc. Bạn đã có ý tưởng đặt tên cho tiểu thuyết chưa? Ví dụ tên tiểu thuyết là \"Thiên Long Bát Bộ\" hoặc \"Tiếu Ngạo Giang Hồ\".'\n\n";
            
            $prompt .= "**VÍ DỤ KHÔNG TỐT:**\n";
            $prompt .= "- 'Quý anh/chị vui lòng cho tôi biết: Tiêu đề của tiểu thuyết là gì?' (quá cứng nhắc, không thừa nhận context)\n\n";
        } else {
            $prompt .= "**KHI TẠO CÂU TRẢ LỜI:**\n";
            $prompt .= "- Trả lời trực tiếp, rõ ràng, có cấu trúc\n";
            $prompt .= "- Thừa nhận ngữ cảnh từ câu hỏi của người dùng\n";
            $prompt .= "- Thêm ví dụ, gợi ý khi phù hợp\n";
            $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n\n";
        }
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng 'Tôi' để tự xưng, 'Bạn' hoặc 'Quý anh/chị' để gọi người dùng\n";
        $prompt .= "- Tránh các cụm từ quá trang trọng, cứng nhắc\n";
        $prompt .= "- Luôn thừa nhận ngữ cảnh từ tin nhắn của người dùng\n";
        $prompt .= "- Thêm ví dụ, gợi ý khi có thể giúp người dùng\n";
        
        return $prompt;
    }
    
    /**
     * Build user prompt for enhancement
     */
    protected function buildEnhancementUserPrompt(
        string $rawResponse,
        string $userMessage,
        ?ChatSession $session,
        array $context,
        string $responseType
    ): string {
        $prompt = "**Câu trả lời/câu hỏi cần cải thiện:**\n{$rawResponse}\n\n";
        $prompt .= "**Tin nhắn vừa rồi của người dùng:**\n{$userMessage}\n\n";
        
        // Add conversation history if available
        if ($session) {
            $previousMessages = $session->messages()
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->reverse();
            
            if ($previousMessages->isNotEmpty()) {
                $prompt .= "**Lịch sử cuộc trò chuyện (gần đây):**\n";
                foreach ($previousMessages as $msg) {
                    $role = $msg->sender === 'user' ? 'Người dùng' : 'Trợ lý';
                    $prompt .= "- {$role}: " . substr($msg->content, 0, 200) . "\n";
                }
                $prompt .= "\n";
            }
        }
        
        // Add collected data if available
        if (!empty($context['collected_data'])) {
            $collectedData = $context['collected_data'];
            $prompt .= "**Thông tin đã thu thập:**\n";
            foreach ($collectedData as $key => $value) {
                if (!str_starts_with($key, '_')) { // Skip internal keys
                    $prompt .= "- {$key}: {$value}\n";
                }
            }
            $prompt .= "\n";
        }
        
        $prompt .= "**Yêu cầu:**\n";
        if ($responseType === 'question') {
            $prompt .= "Hãy cải thiện câu hỏi trên để:\n";
            $prompt .= "1. Thừa nhận những gì người dùng vừa nói\n";
            $prompt .= "2. Đặt câu hỏi một cách tự nhiên, không cứng nhắc\n";
            $prompt .= "3. Thêm ví dụ hoặc gợi ý cụ thể để người dùng dễ trả lời\n";
        } else {
            $prompt .= "Hãy cải thiện câu trả lời trên để:\n";
            $prompt .= "1. Thừa nhận ngữ cảnh từ câu hỏi của người dùng\n";
            $prompt .= "2. Trả lời tự nhiên, lịch sự, có cấu trúc\n";
            $prompt .= "3. Thêm ví dụ, gợi ý khi phù hợp\n";
        }
        
        $prompt .= "\nChỉ trả về câu trả lời/câu hỏi đã được cải thiện, không cần giải thích thêm.";
        
        return $prompt;
    }
}
```

### 2. Cập nhật SmartAssistantEngine để sử dụng ResponseEnhancementService

**File**: `app/Services/SmartAssistantEngine.php`

#### 2.1. Inject ResponseEnhancementService

```php
protected ResponseEnhancementService $responseEnhancer;

public function __construct(
    IntentRecognizer $intentRecognizer,
    WorkflowPlanner $workflowPlanner,
    VectorSearchService $vectorSearchService,
    ResponseEnhancementService $responseEnhancer // ✅ MỚI
) {
    $this->intentRecognizer = $intentRecognizer;
    $this->workflowPlanner = $workflowPlanner;
    $this->vectorSearchService = $vectorSearchService;
    $this->responseEnhancer = $responseEnhancer; // ✅ MỚI
}
```

#### 2.2. Cập nhật executeCollectInfoStep()

```php
protected function executeCollectInfoStep(
    array $step, 
    string $userMessage, 
    array $collectedData, 
    AiAssistant $assistant,
    ?ChatSession $session = null // ✅ MỚI: Thêm session parameter
): array {
    $config = $step['config'] ?? [];
    $questions = $config['questions'] ?? [];
    $fields = $config['fields'] ?? [];

    // ... existing code ...

    if ($nextQuestionIndex < count($questions)) {
        $nextQuestion = $questions[$nextQuestionIndex];
        $askedQuestions[] = $nextQuestion;
        $collectedData['_asked_questions'] = $askedQuestions;

        // ✅ CẢI TIẾN: Sử dụng ResponseEnhancementService
        $formattedQuestion = $this->responseEnhancer->generateContextualQuestion(
            $nextQuestion,
            $userMessage,
            $session,
            $assistant,
            $collectedData
        );
        
        return [
            'response' => $formattedQuestion,
            'completed' => false,
            'data' => $collectedData,
        ];
    }
    
    // ... rest of the code ...
}
```

#### 2.3. Cập nhật executePredefinedSteps() để truyền session

```php
protected function executePredefinedSteps(
    array $steps,
    string $userMessage,
    ChatSession $session, // ✅ Đã có session
    AiAssistant $assistant,
    array $intent,
    array $workflow
): array {
    // ... existing code ...
    
    $result = match ($stepType) {
        'collect_info' => $this->executeCollectInfoStep(
            $currentStep, 
            $userMessage, 
            $collectedData, 
            $assistant,
            $session // ✅ Truyền session
        ),
        // ... other cases ...
    };
    
    // ... rest of the code ...
}
```

#### 2.4. Cập nhật handleGenericRequest() để enhance response

```php
protected function handleGenericRequest(
    string $userMessage, 
    ChatSession $session, 
    AiAssistant $assistant, 
    array $intent
): array {
    $messages = $this->buildChatMessages($session, $userMessage, $assistant);
    
    $response = OpenAI::chat()->create([
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'messages' => $messages,
        'temperature' => 0.7,
    ]);
    
    $rawResponse = $response->choices[0]->message->content;
    
    // ✅ CẢI TIẾN: Enhance response để tự nhiên hơn
    $enhancedResponse = $this->responseEnhancer->enhanceResponse(
        $rawResponse,
        $userMessage,
        $session,
        $assistant,
        ['intent' => $intent],
        'answer'
    );
    
    return [
        'response' => $enhancedResponse,
        'workflow_state' => null,
    ];
}
```

#### 2.5. Cập nhật các handler khác (tùy chọn)

Có thể enhance response ở các handler khác như:
- `handleDraftDocument()` - Enhance response khi soạn thảo văn bản
- `handleClassifyDocument()` - Enhance response khi phân loại
- `handleSearchDocument()` - Enhance response khi tìm kiếm
- `generateAnswerFromContext()` - Enhance answer từ context

**Lưu ý**: Không cần enhance tất cả, chỉ enhance những response quan trọng hoặc có vấn đề.

### 3. Cải thiện buildProfessionalSystemPrompt()

**File**: `app/Services/SmartAssistantEngine.php`

Cập nhật `buildProfessionalSystemPrompt()` để hướng dẫn AI tự nhiên hơn:

```php
protected function buildProfessionalSystemPrompt(AiAssistant $assistant): string
{
    // ... existing code ...
    
    // ✅ CẢI TIẾN: Thêm hướng dẫn về trả lời tự nhiên
    $prompt .= "**QUY TẮC GIAO TIẾP:**\n";
    $prompt .= "1. Luôn sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công\n";
    $prompt .= "2. Xưng hô: Sử dụng \"Tôi\" để tự xưng, \"Bạn\" hoặc \"Quý anh/chị\" để gọi người dùng\n";
    $prompt .= "3. Trả lời rõ ràng, chi tiết, có cấu trúc\n";
    $prompt .= "4. **QUAN TRỌNG**: Luôn thừa nhận ngữ cảnh từ tin nhắn của người dùng trước khi trả lời\n";
    $prompt .= "5. **QUAN TRỌNG**: Khi hỏi lại người dùng, hãy thừa nhận những gì họ vừa nói và đưa ra ví dụ, gợi ý cụ thể\n";
    $prompt .= "6. Tránh các cụm từ quá trang trọng, cứng nhắc như 'Quý anh/chị vui lòng cho tôi biết'\n";
    $prompt .= "7. Thêm ví dụ, gợi ý khi có thể giúp người dùng hiểu rõ hơn\n";
    $prompt .= "8. Sử dụng ngôn ngữ tự nhiên, linh hoạt nhưng vẫn chuyên nghiệp\n\n";
    
    $prompt .= "**VÍ DỤ CÁCH TRẢ LỜI TỐT:**\n";
    $prompt .= "- ✅ 'Tuyệt vời! Bạn muốn viết tiểu thuyết kiếm hiệp Trung Quốc. Bạn đã có ý tưởng đặt tên cho tiểu thuyết chưa? Ví dụ tên tiểu thuyết là \"Thiên Long Bát Bộ\" hoặc \"Tiếu Ngạo Giang Hồ\".'\n";
    $prompt .= "- ✅ 'Rất vui được hỗ trợ bạn soạn công văn! Bạn muốn soạn công văn đi hay công văn đến? Ví dụ: Công văn đi thường dùng để gửi yêu cầu, chỉ thị; Công văn đến là văn bản nhận được từ cơ quan khác.'\n";
    $prompt .= "- ✅ 'Hà Nội hiện tại là một thành phố trực thuộc Trung ương, không phải tỉnh. Hà Nội có 30 quận/huyện và 584 phường/xã/thị trấn...'\n\n";
    
    $prompt .= "**VÍ DỤ CÁCH TRẢ LỜI KHÔNG TỐT:**\n";
    $prompt .= "- ❌ 'Quý anh/chị vui lòng cho tôi biết: Tiêu đề của tiểu thuyết là gì?' (quá cứng nhắc, không thừa nhận context)\n";
    $prompt .= "- ❌ 'Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin...' (khi user chỉ hỏi câu hỏi thông thường)\n\n";
    
    // ... rest of the code ...
}
```

### 4. Tối ưu hóa Performance

#### 4.1. Cache responses

Có thể cache enhanced responses cho các câu hỏi tương tự:

```php
// Trong ResponseEnhancementService
protected function getCacheKey(string $rawResponse, string $userMessage): string
{
    return 'response_enhancement:' . md5($rawResponse . $userMessage);
}

public function enhanceResponse(...): string
{
    $cacheKey = $this->getCacheKey($rawResponse, $userMessage);
    
    // Try cache first
    $cached = Cache::get($cacheKey);
    if ($cached) {
        return $cached;
    }
    
    // ... enhance response ...
    
    // Cache for 1 hour
    Cache::put($cacheKey, $enhancedResponse, 3600);
    
    return $enhancedResponse;
}
```

#### 4.2. Skip enhancement cho một số trường hợp

Không cần enhance nếu:
- Response đã quá dài (> 1000 ký tự)
- Response đã có format tốt rồi
- Response là error message đơn giản

```php
protected function shouldEnhance(string $rawResponse, string $responseType): bool
{
    // Skip nếu quá dài
    if (strlen($rawResponse) > 1000) {
        return false;
    }
    
    // Skip nếu đã có format tốt (có ví dụ, có ngữ cảnh)
    if (str_contains($rawResponse, 'ví dụ') || 
        str_contains($rawResponse, 'Ví dụ') ||
        str_contains($rawResponse, 'gợi ý')) {
        return false; // Có thể đã được enhance rồi
    }
    
    return true;
}
```

---

## 📊 SO SÁNH GIẢI PHÁP

| Tiêu chí | Giải pháp cũ | Giải pháp mới (Toàn diện) |
|----------|--------------|---------------------------|
| **Phạm vi** | Chỉ một số điểm | Tất cả các điểm tạo response |
| **Hiểu ngữ cảnh** | ❌ Không | ✅ Có (từ conversation history) |
| **Tự nhiên** | ❌ Cứng nhắc | ✅ Tự nhiên, linh hoạt |
| **Ví dụ, gợi ý** | ❌ Không có | ✅ Có, phù hợp với nhu cầu |
| **Áp dụng** | Chỉ collect_info | Tất cả: questions, answers, info, errors |
| **Maintainability** | ⭐⭐ | ⭐⭐⭐⭐⭐ (Centralized service) |
| **Performance** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ (Có thể cache) |
| **Cost** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ (Thêm API calls) |

---

## 🎯 KẾT QUẢ MONG ĐỢI

### Trước khi cải tiến:
- **User**: "tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc"
- **Chatbot**: "Quý anh/chị vui lòng cho tôi biết: Tiêu đề của tiểu thuyết là gì?"

### Sau khi cải tiến:
- **User**: "tôi muốn viết 1 tiểu thuyết kiếm hiệp trung quốc"
- **Chatbot**: "Tuyệt vời! Bạn muốn viết tiểu thuyết kiếm hiệp Trung Quốc. Bạn đã có ý tưởng đặt tên cho tiểu thuyết chưa? Ví dụ tên tiểu thuyết là \"Thiên Long Bát Bộ\" hoặc \"Tiếu Ngạo Giang Hồ\"."

### Hoặc:
- **User**: "tôi muốn soạn công văn"
- **Chatbot**: "Rất vui được hỗ trợ bạn soạn công văn! Bạn muốn soạn công văn đi hay công văn đến? Ví dụ: Công văn đi thường dùng để gửi yêu cầu, chỉ thị; Công văn đến là văn bản nhận được từ cơ quan khác."

---

## 📋 CHECKLIST TRIỂN KHAI

### Phase 1: Tạo Service (Ưu tiên cao)
- [ ] Tạo `app/Services/ResponseEnhancementService.php`
- [ ] Implement `enhanceResponse()` method
- [ ] Implement `generateContextualQuestion()` method
- [ ] Implement helper methods

### Phase 2: Tích hợp vào SmartAssistantEngine (Ưu tiên cao)
- [ ] Inject ResponseEnhancementService vào SmartAssistantEngine
- [ ] Cập nhật `executeCollectInfoStep()` để sử dụng service
- [ ] Cập nhật `executePredefinedSteps()` để truyền session
- [ ] Cập nhật `handleGenericRequest()` để enhance response

### Phase 3: Cải thiện System Prompt (Ưu tiên trung bình)
- [ ] Cập nhật `buildProfessionalSystemPrompt()` với hướng dẫn mới
- [ ] Test với các loại assistant khác nhau

### Phase 4: Tối ưu hóa (Ưu tiên thấp)
- [ ] Implement caching cho enhanced responses
- [ ] Implement `shouldEnhance()` logic
- [ ] Monitor API usage và cost

### Phase 5: Testing (Quan trọng)
- [ ] Test với "Trợ lý lập dàn ý viết tiểu thuyết"
- [ ] Test với "Trợ lý soạn thảo văn bản"
- [ ] Test với "Trợ lý Q&A"
- [ ] Test với các loại assistant khác
- [ ] Test performance và cost

---

## ⚠️ LƯU Ý QUAN TRỌNG

1. **API Cost**: Mỗi lần enhance sẽ tốn thêm 1 API call. Cần monitor usage và cost.
2. **Performance**: Có thể làm chậm response một chút (thêm ~0.5-1s). Cân nhắc cache hoặc async.
3. **Fallback**: Luôn có fallback về raw response nếu AI call fail.
4. **Testing**: Test kỹ với nhiều loại assistant và tình huống khác nhau.
5. **Gradual Rollout**: Có thể triển khai từng phần, không cần làm tất cả cùng lúc.

---

## 🎉 KẾT LUẬN

Giải pháp toàn diện này sẽ:
- ✅ Cải thiện trải nghiệm người dùng cho TẤT CẢ các loại trợ lý
- ✅ Đảm bảo chatbot hiểu ngữ cảnh, trả lời tự nhiên, có ví dụ gợi ý
- ✅ Dễ maintain và mở rộng (centralized service)
- ✅ Có thể tối ưu hóa performance và cost

**Mức độ ưu tiên**: ⚠️ **CAO** - Ảnh hưởng trực tiếp đến trải nghiệm người dùng và chất lượng chatbot.

