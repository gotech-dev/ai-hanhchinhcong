<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class IntentRecognizer
{
    /**
     * Recognize intent from user message
     *
     * @param string $userMessage
     * @param array $context
     * @return array{type: string, entity: array, confidence: float}
     */
    public function recognize(string $userMessage, array $context = []): array
    {
        // ✅ NEW: ALWAYS use ChatGPT with conversation history
        // No more hardcoded keywords - AI understands context
        
        try {
            // Build conversation history
            $conversationHistory = $this->buildConversationHistory($context);
            
            // Build messages for ChatGPT
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt($context),
                ],
            ];
            
            // Add conversation history (last 5 messages for context)
            if (!empty($conversationHistory)) {
                $recentHistory = array_slice($conversationHistory, -5);
                foreach ($recentHistory as $msg) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content'],
                    ];
                }
            }
            
            // Add current message with analysis instruction
            $messages[] = [
                'role' => 'user',
                'content' => $this->buildAnalysisPrompt($userMessage, $context),
            ];
            
            Log::debug('Intent recognition with conversation context', [
                'user_message' => substr($userMessage, 0, 100),
                'history_count' => count($conversationHistory),
                'assistant_type' => $context['assistant']->getAssistantTypeValue() ?? null,
            ]);
            
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.2, // Lower temperature for more consistent intent recognition
                'response_format' => ['type' => 'json_object'],
            ]);
            
            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);
            
            if (!$result || !isset($result['type'])) {
                throw new \Exception('Invalid response format from AI');
            }
            
            Log::info('Intent recognized with AI', [
                'user_message' => substr($userMessage, 0, 100),
                'intent' => $result['type'],
                'confidence' => $result['confidence'] ?? 0.5,
                'has_history' => !empty($conversationHistory),
            ]);
            
            return [
                'type' => $result['type'] ?? 'unknown',
                'entity' => $result['entity'] ?? [],
                'confidence' => $result['confidence'] ?? 0.5,
            ];
        } catch (\Exception $e) {
            Log::error('Intent recognition failed, using fallback', [
                'error' => $e->getMessage(),
                'message' => substr($userMessage, 0, 100),
            ]);
            
            // Fallback to basic pattern matching
            return $this->fallbackRecognition($userMessage);
        }
    }

    /**
     * ✅ NEW: Build conversation history from context
     *
     * @param array $context
     * @return array
     */
    protected function buildConversationHistory(array $context): array
    {
        $history = [];
        
        // Get messages from session if available
        if (isset($context['session'])) {
            $session = $context['session'];
            $messages = $session->messages()->orderBy('created_at', 'desc')->limit(10)->get();
            
            foreach ($messages->reverse() as $message) {
                $history[] = [
                    'role' => $message->sender === 'user' ? 'user' : 'assistant',
                    'content' => substr($message->content, 0, 500), // Limit length
                ];
            }
        }
        
        return $history;
    }
    
    /**
     * ✅ NEW: Build system prompt with assistant context
     *
     * @param array $context
     * @return string
     */
    protected function buildSystemPrompt(array $context): string
    {
        // ✅ FIX: Convert enum to string
        $assistantTypeEnum = $context['assistant']->assistant_type ?? null;
        $assistantType = $assistantTypeEnum ? (is_object($assistantTypeEnum) ? $assistantTypeEnum->value : $assistantTypeEnum) : 'unknown';
        $assistantName = $context['assistant']->name ?? 'AI Assistant';
        
        $basePrompt = "Bạn là một AI chuyên phân tích ý định (intent) của người dùng trong cuộc hội thoại.\n\n";
        
        $basePrompt .= "**THÔNG TIN TRỢ LÝ:**\n";
        $basePrompt .= "- Tên: {$assistantName}\n";
        $basePrompt .= "- Loại: {$assistantType}\n\n";
        
        // Note: report_generator has been merged into document_drafting
        if ($assistantType === 'qa_based_document') {
            $basePrompt .= "**CHỨC NĂNG CHÍNH:** Trợ lý này chuyên trả lời câu hỏi dựa trên tài liệu đã upload.\n\n";
        } elseif ($assistantType === 'document_drafting') {
            $basePrompt .= "**CHỨC NĂNG CHÍNH:** Trợ lý này chuyên soạn thảo văn bản hành chính (Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết).\n\n";
        } elseif ($assistantType === 'document_management') {
            $basePrompt .= "**CHỨC NĂNG CHÍNH:** Trợ lý này chuyên quản lý văn bản đến/đi, phân loại, lưu trữ, tìm kiếm và nhắc nhở thời hạn xử lý.\n\n";
        }
        
        $basePrompt .= "**CÁC LOẠI INTENT:**\n";
        
        // Intent cho document_drafting
        if ($assistantType === 'document_drafting') {
            $basePrompt .= "1. **draft_document**: User muốn soạn thảo văn bản hành chính\n";
            $basePrompt .= "   - Ví dụ: \"tạo công văn\", \"soạn thảo quyết định\", \"làm tờ trình\", \"tạo báo cáo\", \"viết biên bản\"\n";
            $basePrompt .= "   - Context: User đang cung cấp thông tin (loại văn bản, nội dung, người nhận, v.v.) → vẫn là draft_document\n";
            $basePrompt .= "   - Entity: {\"document_type\": \"cong_van|quyet_dinh|to_trinh|bao_cao|bien_ban|thong_bao|nghi_quyet\"}\n\n";
        } elseif ($assistantType === 'document_management') {
            $basePrompt .= "1. **classify_document**: User muốn phân loại văn bản\n";
            $basePrompt .= "   - Ví dụ: \"phân loại văn bản\", \"phân loại công văn số 123\", \"upload văn bản\"\n\n";
            $basePrompt .= "2. **search_document**: User muốn tìm kiếm văn bản\n";
            $basePrompt .= "   - Ví dụ: \"tìm văn bản về ngân sách\", \"tìm công văn số 123\", \"tìm tất cả văn bản đến\"\n\n";
            $basePrompt .= "3. **get_reminders**: User muốn xem nhắc nhở\n";
            $basePrompt .= "   - Ví dụ: \"nhắc nhở\", \"văn bản cần xử lý\", \"văn bản sắp đến hạn\", \"văn bản quá hạn\"\n\n";
        } else {
            $basePrompt .= "1. **create_report**: User muốn tạo báo cáo/tài liệu mới\n";
            $basePrompt .= "   - Ví dụ: \"tạo báo cáo\", \"làm báo cáo cho tôi\", \"tạo tài liệu\", \"tự điền data mẫu\"\n";
            $basePrompt .= "   - Context: User đang cung cấp thông tin (thời gian, nội dung, đối tượng, v.v.) → vẫn là create_report\n\n";
        }
        
        $basePrompt .= "2. **ask_question**: User hỏi câu hỏi thông thường, cần thông tin hoặc kiến thức\n";
        $basePrompt .= "   - **ĐẶC ĐIỂM:** Câu hỏi về thông tin thực tế, kiến thức, định nghĩa, số lượng, địa lý hành chính\n";
        $basePrompt .= "   - **VÍ DỤ CÂU HỎI THÔNG THƯỜNG:**\n";
        $basePrompt .= "     + \"Hà Nội có bao nhiêu tỉnh?\" → ask_question (hỏi về thông tin thực tế)\n";
        $basePrompt .= "     + \"Việt Nam có bao nhiêu tỉnh thành?\" → ask_question (hỏi về số lượng)\n";
        $basePrompt .= "     + \"Công văn là gì?\" → ask_question (hỏi về định nghĩa)\n";
        $basePrompt .= "     + \"GDP là gì?\" → ask_question (hỏi về kiến thức)\n";
        $basePrompt .= "     + \"Bạn làm được gì?\" → ask_question (hỏi về chức năng)\n";
        $basePrompt .= "     + \"Cách sử dụng hệ thống?\" → ask_question (hỏi về hướng dẫn)\n";
        $basePrompt .= "     + \"Xã A có bao nhiêu dân?\" → ask_question (hỏi về thông tin địa lý)\n";
        $basePrompt .= "   - **KHÔNG PHẢI ask_question:**\n";
        $basePrompt .= "     + \"Tôi muốn soạn thảo công văn\" → draft_document (yêu cầu tạo văn bản)\n";
        $basePrompt .= "     + \"Giúp tôi tạo quyết định\" → draft_document (yêu cầu tạo văn bản)\n";
        $basePrompt .= "     + \"Tôi muốn tạo báo cáo\" → create_report (yêu cầu tạo báo cáo)\n";
        $basePrompt .= "   - **QUY TẮC:** Nếu user chỉ hỏi thông tin/kiến thức → ask_question. Nếu user muốn tạo/tạo ra cái gì đó → workflow intent\n\n";
        
        $basePrompt .= "3. **continue_conversation**: User trả lời câu hỏi của bot, cung cấp thông tin\n";
        $basePrompt .= "   - Context: Bot vừa hỏi \"Bạn muốn tạo báo cáo nào?\" → User: \"Báo cáo năm 2024\" → continue_conversation\n";
        if ($assistantType === 'document_drafting') {
            $basePrompt .= "   - Context: Bot vừa hỏi \"Bạn muốn soạn thảo loại văn bản nào?\" → User: \"Công văn\" → continue_conversation\n";
        }
        $basePrompt .= "\n";
        
        $basePrompt .= "4. **download_file**: Tải file\n";
        $basePrompt .= "5. **update_info**: Cập nhật/sửa thông tin\n";
        $basePrompt .= "6. **search_document**: Tìm kiếm trong tài liệu\n";
        $basePrompt .= "7. **cancel**: Hủy/dừng\n\n";
        
        // ✅ PHASE 4: Thêm section phân biệt general question vs workflow request
        $basePrompt .= "**PHÂN BIỆT CÂU HỎI THÔNG THƯỜNG vs YÊU CẦU CẦN WORKFLOW:**\n\n";
        $basePrompt .= "**CÂU HỎI THÔNG THƯỜNG (ask_question) - KHÔNG cần workflow:**\n";
        $basePrompt .= "- Câu hỏi về thông tin thực tế: \"Hà Nội có bao nhiêu tỉnh?\", \"Việt Nam có bao nhiêu tỉnh thành?\"\n";
        $basePrompt .= "- Câu hỏi về kiến thức: \"GDP là gì?\", \"Công văn là gì?\"\n";
        $basePrompt .= "- Câu hỏi về chức năng: \"Bạn làm được gì?\", \"Tính năng của bạn là gì?\"\n";
        $basePrompt .= "- Câu hỏi về cách sử dụng: \"Làm thế nào để...?\", \"Cách sử dụng...?\"\n";
        $basePrompt .= "- Câu hỏi về địa lý, hành chính: \"Xã A có bao nhiêu dân?\", \"Tỉnh B có bao nhiêu huyện?\"\n";
        $basePrompt .= "- Câu hỏi về số lượng: \"có bao nhiêu\", \"bao nhiêu\"\n";
        $basePrompt .= "- Câu hỏi về định nghĩa: \"là gì\", \"là ai\", \"là như thế nào\"\n\n";
        
        $basePrompt .= "**YÊU CẦU CẦN WORKFLOW (draft_document/create_report) - CẦN trigger steps:**\n";
        if ($assistantType === 'document_drafting') {
            $basePrompt .= "- Yêu cầu tạo văn bản: \"Tôi muốn soạn thảo công văn\", \"Giúp tôi tạo quyết định\"\n";
            $basePrompt .= "- Yêu cầu soạn thảo: \"Soạn thảo tờ trình\", \"Làm biên bản\"\n";
        }
        $basePrompt .= "- Yêu cầu tạo báo cáo: \"Tôi muốn tạo báo cáo\", \"Làm báo cáo thường niên\"\n";
        $basePrompt .= "- Yêu cầu cụ thể cần nhiều bước: \"Tôi muốn viết sách\", \"Tạo kế hoạch dự án\"\n";
        $basePrompt .= "- Yêu cầu có từ khóa: \"tạo\", \"soạn thảo\", \"làm\", \"viết\" + tên văn bản/báo cáo\n\n";
        
        $basePrompt .= "**QUAN TRỌNG:**\n";
        $basePrompt .= "- Phân tích TOÀN BỘ cuộc hội thoại, không chỉ message cuối\n";
        $basePrompt .= "- Nếu bot đang hỏi thông tin (\"Bạn cần gì?\") → User trả lời → Intent phụ thuộc vào context\n";
        $basePrompt .= "- Nếu user đang cung cấp chi tiết cho việc tạo báo cáo → create_report\n";
        $basePrompt .= "- Từ \"mẫu\", \"sample\", \"tự điền\" là context, không thay đổi intent\n";
        $basePrompt .= "- **QUY TẮC VÀNG:** Nếu user chỉ HỎI thông tin → ask_question. Nếu user muốn TẠO/TẠO RA cái gì đó → workflow intent (draft_document/create_report)\n\n";
        
        $basePrompt .= "Trả về JSON format: {\"type\": \"intent_type\", \"entity\": {...}, \"confidence\": 0.0-1.0}";
        
        return $basePrompt;
    }
    
    /**
     * ✅ NEW: Build analysis prompt for current message
     *
     * @param string $userMessage
     * @param array $context
     * @return string
     */
    protected function buildAnalysisPrompt(string $userMessage, array $context): string
    {
        $prompt = "**PHÂN TÍCH MESSAGE HIỆN TẠI:**\n\n";
        $prompt .= "User message: \"{$userMessage}\"\n\n";
        
        // Add workflow state if available
        if (isset($context['workflow_state'])) {
            $workflowState = $context['workflow_state'];
            $prompt .= "Workflow state: " . ($workflowState['current_step'] ?? 'unknown') . "\n";
        }
        
        // Add collected data if available
        if (isset($context['collected_data']) && !empty($context['collected_data'])) {
            $prompt .= "Collected data: " . json_encode($context['collected_data'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        $prompt .= "\n**YÊU CẦU:** Dựa vào lịch sử chat và message hiện tại, xác định intent của user.\n";
        $prompt .= "Trả về JSON format đúng như đã mô tả.";
        
        return $prompt;
    }
    
    /**
     * ❌ DEPRECATED: Old buildPrompt method - no longer used
     */
    protected function buildPrompt(string $userMessage, array $context): string
    {
        // This method is kept for backward compatibility but should not be used
        return $this->buildAnalysisPrompt($userMessage, $context);
    }

    /**
     * Fallback recognition using pattern matching
     *
     * @param string $userMessage
     * @return array{type: string, entity: array, confidence: float}
     */
    protected function fallbackRecognition(string $userMessage): array
    {
        $message = mb_strtolower($userMessage);
        
        $patterns = [
            'create_report' => ['tạo báo cáo', 'tạo file', 'tạo tài liệu', 'tạo báo', 'làm báo cáo', 'tự điền', 'điền data', 'data mẫu', 'tạo mẫu'],
            // ✅ ADDED BACK: 'tự điền', 'data mẫu' keywords for fallback recognition
            // These indicate user wants to create a report with auto-filled data
            'ask_question' => ['hỏi', 'câu hỏi', 'là gì', 'như thế nào', 'thủ tục', 'quy định'],
            'download_file' => ['tải', 'download', 'tải xuống', 'lấy file'],
            'update_info' => ['cập nhật', 'sửa', 'chỉnh sửa', 'thay đổi'],
            'search_document' => ['tìm', 'tìm kiếm', 'tra cứu', 'search'],
        ];
        
        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    return [
                        'type' => $type,
                        'entity' => [],
                        'confidence' => 0.6,
                    ];
                }
            }
        }
        
        return [
            'type' => 'ask_question',
            'entity' => [],
            'confidence' => 0.5,
        ];
    }

    /**
     * ✅ NEW: Detect if user wants to auto-generate/fill data using AI
     * 
     * This method uses AI to intelligently detect if the user wants the system
     * to automatically generate sample data instead of asking for user input.
     *
     * @param string $userMessage
     * @param array $context
     * @return bool
     */
    public function detectAutoFillIntent(string $userMessage, array $context = []): bool
    {
        try {
            // Build conversation history
            $conversationHistory = $this->buildConversationHistory($context);
            
            // Build messages for ChatGPT
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->buildAutoFillSystemPrompt($context),
                ],
            ];
            
            // Add conversation history (last 5 messages for context)
            if (!empty($conversationHistory)) {
                $recentHistory = array_slice($conversationHistory, -5);
                foreach ($recentHistory as $msg) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content'],
                    ];
                }
            }
            
            // Add current message with analysis instruction
            $messages[] = [
                'role' => 'user',
                'content' => $this->buildAutoFillAnalysisPrompt($userMessage, $context),
            ];
            
            Log::debug('Auto-fill intent detection with AI', [
                'user_message' => substr($userMessage, 0, 100),
                'history_count' => count($conversationHistory),
            ]);
            
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);
            
            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);
            
            if (!$result || !isset($result['wants_auto_fill'])) {
                throw new \Exception('Invalid response format from AI');
            }
            
            $wantsAutoFill = (bool) ($result['wants_auto_fill'] ?? false);
            $confidence = $result['confidence'] ?? 0.5;
            
            Log::info('Auto-fill intent detected with AI', [
                'user_message' => substr($userMessage, 0, 100),
                'wants_auto_fill' => $wantsAutoFill,
                'confidence' => $confidence,
                'reasoning' => $result['reasoning'] ?? null,
            ]);
            
            // Only return true if confidence is high enough
            return $wantsAutoFill && $confidence >= 0.6;
        } catch (\Exception $e) {
            Log::error('Auto-fill intent detection failed, using fallback', [
                'error' => $e->getMessage(),
                'message' => substr($userMessage, 0, 100),
            ]);
            
            // Fallback to basic pattern matching
            return $this->fallbackAutoFillDetection($userMessage);
        }
    }
    
    /**
     * Build system prompt for auto-fill intent detection
     *
     * @param array $context
     * @return string
     */
    protected function buildAutoFillSystemPrompt(array $context): string
    {
        // ✅ FIX: Convert enum to string
        $assistantTypeEnum = $context['assistant']->assistant_type ?? null;
        $assistantType = $assistantTypeEnum ? (is_object($assistantTypeEnum) ? $assistantTypeEnum->value : $assistantTypeEnum) : 'unknown';
        $assistantName = $context['assistant']->name ?? 'AI Assistant';
        
        $prompt = "Bạn là một AI chuyên phân tích ý định của người dùng về việc có muốn hệ thống tự động tạo dữ liệu mẫu hay không.\n\n";
        
        $prompt .= "**THÔNG TIN TRỢ LÝ:**\n";
        $prompt .= "- Tên: {$assistantName}\n";
        $prompt .= "- Loại: {$assistantType}\n\n";
        
        $prompt .= "**NHIỆM VỤ:**\n";
        $prompt .= "Phân tích message của user để xác định xem user có muốn hệ thống TỰ ĐỘNG TẠO DỮ LIỆU MẪU để điền vào báo cáo hay không.\n\n";
        
        $prompt .= "**CÁC TRƯỜNG HỢP USER MUỐN TỰ TẠO DATA:**\n";
        $prompt .= "1. User nói rõ: \"tự tạo\", \"tự điền\", \"mày tự tạo\", \"AI tự tạo\", \"tự động điền\"\n";
        $prompt .= "2. User nói: \"data mẫu\", \"dữ liệu mẫu\", \"tạo mẫu\", \"điền mẫu\"\n";
        $prompt .= "3. User nói: \"tạo báo cáo mẫu\", \"báo cáo mẫu\", \"sample report\"\n";
        $prompt .= "4. User nói: \"không cần hỏi\", \"tự làm đi\", \"tự xử lý\"\n";
        $prompt .= "5. User nói: \"điền data giúp tôi\", \"tạo data giúp tôi\"\n";
        $prompt .= "6. User yêu cầu tạo báo cáo nhưng KHÔNG cung cấp thông tin cụ thể và có vẻ muốn hệ thống tự xử lý\n\n";
        
        $prompt .= "**CÁC TRƯỜNG HỢP USER KHÔNG MUỐN TỰ TẠO DATA:**\n";
        $prompt .= "1. User đang cung cấp thông tin cụ thể (tên công ty, địa chỉ, số điện thoại, v.v.)\n";
        $prompt .= "2. User đang trả lời câu hỏi của bot\n";
        $prompt .= "3. User nói: \"tôi sẽ cung cấp\", \"tôi sẽ gửi\", \"để tôi điền\"\n";
        $prompt .= "4. User hỏi: \"cần thông tin gì?\", \"cần data gì?\"\n\n";
        
        $prompt .= "**QUAN TRỌNG:**\n";
        $prompt .= "- Phân tích TOÀN BỘ cuộc hội thoại, không chỉ message cuối\n";
        $prompt .= "- Nếu user nói \"tạo báo cáo\" nhưng KHÔNG cung cấp thông tin → Có thể muốn tự tạo\n";
        $prompt .= "- Nếu user nói \"tạo báo cáo\" và CUNG CẤP thông tin → KHÔNG muốn tự tạo\n";
        $prompt .= "- Phải hiểu ngữ cảnh: \"mày tự tạo\" = user muốn AI tự tạo\n\n";
        
        $prompt .= "Trả về JSON format: {\"wants_auto_fill\": true/false, \"confidence\": 0.0-1.0, \"reasoning\": \"lý do\"}";
        
        return $prompt;
    }
    
    /**
     * Build analysis prompt for auto-fill intent detection
     *
     * @param string $userMessage
     * @param array $context
     * @return string
     */
    protected function buildAutoFillAnalysisPrompt(string $userMessage, array $context): string
    {
        $prompt = "**PHÂN TÍCH MESSAGE HIỆN TẠI:**\n\n";
        $prompt .= "User message: \"{$userMessage}\"\n\n";
        
        // Add workflow state if available
        if (isset($context['workflow_state'])) {
            $workflowState = $context['workflow_state'];
            $prompt .= "Workflow state: " . ($workflowState['current_step'] ?? 'unknown') . "\n";
        }
        
        // Add collected data if available
        if (isset($context['collected_data']) && !empty($context['collected_data'])) {
            $prompt .= "Collected data: " . json_encode($context['collected_data'], JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $prompt .= "Collected data: KHÔNG CÓ (empty)\n";
        }
        
        $prompt .= "\n**YÊU CẦU:** Dựa vào lịch sử chat và message hiện tại, xác định xem user có muốn hệ thống TỰ ĐỘNG TẠO DỮ LIỆU MẪU hay không.\n";
        $prompt .= "Trả về JSON format đúng như đã mô tả.";
        
        return $prompt;
    }
    
    /**
     * Fallback auto-fill detection using pattern matching
     *
     * @param string $userMessage
     * @return bool
     */
    protected function fallbackAutoFillDetection(string $userMessage): bool
    {
        $message = mb_strtolower($userMessage);
        
        $autoFillPatterns = [
            'tự tạo',
            'tự điền',
            'mày tự tạo',
            'ai tự tạo',
            'tự động điền',
            'data mẫu',
            'dữ liệu mẫu',
            'tạo mẫu',
            'điền mẫu',
            'báo cáo mẫu',
            'sample',
            'tự làm',
            'tự xử lý',
            'không cần hỏi',
        ];
        
        foreach ($autoFillPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract entities from message
     *
     * @param string $userMessage
     * @param string $intentType
     * @return array
     */
    public function extractEntities(string $userMessage, string $intentType): array
    {
        $entities = [];
        
        // Extract time entities
        if (preg_match('/(tháng|month)\s*(\d+)/i', $userMessage, $matches)) {
            $entities['month'] = (int) $matches[2];
        }
        
        if (preg_match('/(năm|year)\s*(\d{4})/i', $userMessage, $matches)) {
            $entities['year'] = (int) $matches[2];
        }
        
        // Extract report type
        if (preg_match('/báo cáo\s+(\w+)/i', $userMessage, $matches)) {
            $entities['report_type'] = $matches[1];
        }
        
        // Extract keywords
        $keywords = ['hoạt động', 'kết quả', 'khó khăn', 'giải pháp'];
        foreach ($keywords as $keyword) {
            if (str_contains(mb_strtolower($userMessage), $keyword)) {
                $entities['keywords'][] = $keyword;
            }
        }
        
        return $entities;
    }
}

