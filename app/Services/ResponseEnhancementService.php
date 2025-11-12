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
            // Check if we should skip enhancement
            if (!$this->shouldEnhance($rawResponse, $responseType)) {
                return $rawResponse;
            }

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

    /**
     * Check if we should enhance the response
     */
    protected function shouldEnhance(string $rawResponse, string $responseType): bool
    {
        // Skip nếu quá dài
        if (strlen($rawResponse) > 1000) {
            return false;
        }
        
        // Skip nếu đã có format tốt (có ví dụ, có ngữ cảnh)
        if (str_contains($rawResponse, 'ví dụ') || 
            str_contains($rawResponse, 'Ví dụ') ||
            str_contains($rawResponse, 'gợi ý') ||
            str_contains($rawResponse, 'Gợi ý')) {
            // Check if it's already contextual (mentions what user said)
            $contextualIndicators = ['tuyệt vời', 'rất vui', 'bạn muốn', 'bạn đã', 'bạn cần'];
            foreach ($contextualIndicators as $indicator) {
                if (stripos($rawResponse, $indicator) !== false) {
                    return false; // Có thể đã được enhance rồi
                }
            }
        }
        
        // Skip nếu là error message đơn giản
        if ($responseType === 'error' && strlen($rawResponse) < 50) {
            return false;
        }
        
        return true;
    }
}


