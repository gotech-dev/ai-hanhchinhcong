<?php

namespace App\Services;

use App\Models\AiAssistant;
use Illuminate\Support\Facades\Log;

class SystemPromptBuilder
{
    /**
     * Build system prompt for assistant
     * Priority: assistant.system_prompt_override > assistant_type.system_prompt > default by type
     *
     * @param AiAssistant $assistant
     * @return string
     */
    public function build(AiAssistant $assistant): string
    {
        // Priority 1: Override ở level assistant
        if (!empty($assistant->system_prompt_override)) {
            Log::info('🔵 [SystemPromptBuilder] Using assistant override prompt', [
                'assistant_id' => $assistant->id,
            ]);
            return $this->replacePlaceholders($assistant->system_prompt_override, $assistant);
        }
        
        // Priority 2: Prompt từ assistant_type
        $assistantType = $assistant->type;
        if ($assistantType && !empty($assistantType->system_prompt)) {
            Log::info('🔵 [SystemPromptBuilder] Using assistant_type prompt', [
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistantType->code,
            ]);
            return $this->replacePlaceholders($assistantType->system_prompt, $assistant);
        }
        
        // Priority 3: Default prompt theo loại (backward compatibility)
        Log::info('🔵 [SystemPromptBuilder] Using default prompt by type', [
            'assistant_id' => $assistant->id,
            'assistant_type' => $assistant->getAssistantTypeValue(),
        ]);
        return $this->getDefaultPrompt($assistant);
    }

    /**
     * Replace placeholders in prompt
     *
     * @param string $prompt
     * @param AiAssistant $assistant
     * @return string
     */
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

    /**
     * Get default prompt by assistant type
     *
     * @param AiAssistant $assistant
     * @return string
     */
    protected function getDefaultPrompt(AiAssistant $assistant): string
    {
        $type = $assistant->getAssistantTypeValue();
        $name = $assistant->name ?? 'Trợ lý AI';
        $description = $assistant->description ?? '';

        return match($type) {
            'qa_based_document' => $this->getQABasedDocumentPrompt($name, $description),
            'document_drafting' => $this->getDocumentDraftingPrompt($name, $description),
            'document_management' => $this->getDocumentManagementPrompt($name, $description),
            'hr_management' => $this->getHRManagementPrompt($name, $description),
            'finance_management' => $this->getFinanceManagementPrompt($name, $description),
            'project_management' => $this->getProjectManagementPrompt($name, $description),
            'complaint_management' => $this->getComplaintManagementPrompt($name, $description),
            'event_management' => $this->getEventManagementPrompt($name, $description),
            'asset_management' => $this->getAssetManagementPrompt($name, $description),
            default => $this->getGenericPrompt($name, $description),
        };
    }

    /**
     * Get prompt for qa_based_document
     */
    protected function getQABasedDocumentPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên trả lời câu hỏi dựa trên tài liệu đã được upload.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Trả lời câu hỏi dựa TRỰC TIẾP và CHỈ dựa trên tài liệu được cung cấp\n";
        $prompt .= "- Đọc kỹ toàn bộ tài liệu tham khảo trước khi trả lời\n";
        $prompt .= "- Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI trả lời đầy đủ và chi tiết\n";
        $prompt .= "- KHÔNG được nói \"tài liệu không đề cập\" nếu thông tin thực sự có trong tài liệu\n";
        $prompt .= "- Trích dẫn nguồn [Nguồn X] khi có thể\n";
        $prompt .= "- Ưu tiên thông tin từ tài liệu, không sử dụng kiến thức chung\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ tự nhiên, thân thiện, dễ hiểu\n";
        $prompt .= "- Trả lời chi tiết, có cấu trúc, dễ đọc\n";
        $prompt .= "- Chỉ nói \"tài liệu không đề cập\" khi bạn đã đọc kỹ và CHẮC CHẮN rằng tài liệu không có thông tin\n";
        $prompt .= "- Nếu tài liệu không có thông tin, có thể tìm kiếm trên mạng để bổ sung (nếu được cấu hình)\n";
        
        return $prompt;
    }

    /**
     * Get prompt for document_drafting
     */
    protected function getDocumentDraftingPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên soạn thảo văn bản hành chính.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Soạn thảo các loại văn bản: Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết\n";
        $prompt .= "- Sử dụng đúng format, ngôn ngữ hành chính, tuân thủ quy định pháp luật\n";
        $prompt .= "- Thu thập thông tin cần thiết từ người dùng để soạn thảo chính xác\n";
        $prompt .= "- Kiểm tra tính hợp pháp và đúng quy trình\n\n";
        
        $prompt .= "**QUY TẮC GIAO TIẾP:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công\n";
        $prompt .= "- Xưng hô: \"Tôi\" để tự xưng, \"Quý anh/chị\" để gọi người dùng\n";
        $prompt .= "- Luôn thừa nhận ngữ cảnh từ tin nhắn của người dùng trước khi trả lời\n";
        $prompt .= "- Khi hỏi lại người dùng, hãy thừa nhận những gì họ vừa nói và đưa ra ví dụ, gợi ý cụ thể\n";
        $prompt .= "- Trả lời rõ ràng, chi tiết, có cấu trúc\n";
        
        return $prompt;
    }

    /**
     * Get prompt for document_management
     */
    protected function getDocumentManagementPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên quản lý văn bản và lưu trữ.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Quản lý văn bản đến, văn bản đi\n";
        $prompt .= "- Phân loại văn bản tự động\n";
        $prompt .= "- Tính toán và nhắc nhở thời hạn xử lý\n";
        $prompt .= "- Lưu trữ và tìm kiếm văn bản\n";
        $prompt .= "- Trả lời câu hỏi về văn bản một cách trực tiếp\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Trả lời trực tiếp câu hỏi về văn bản, không hỏi lại nếu không cần\n";
        $prompt .= "- Cung cấp thông tin chi tiết về văn bản khi được yêu cầu\n";
        
        return $prompt;
    }

    /**
     * Get prompt for hr_management
     */
    protected function getHRManagementPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên quản lý nhân sự.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Quản lý nhân sự: tính lương, chấm công, nghỉ phép\n";
        $prompt .= "- Tạo báo cáo nhân sự\n";
        $prompt .= "- Trả lời câu hỏi về quy định nhân sự, chế độ chính sách\n";
        $prompt .= "- Hỗ trợ tính toán lương, thưởng, phụ cấp\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Bảo mật thông tin nhân sự\n";
        $prompt .= "- Trả lời chính xác về quy định, chế độ\n";
        $prompt .= "- Tính toán chính xác, minh bạch\n";
        
        return $prompt;
    }

    /**
     * Get prompt for finance_management
     */
    protected function getFinanceManagementPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên quản lý tài chính và ngân sách.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Quản lý tài chính: lập dự toán, theo dõi thu chi\n";
        $prompt .= "- Cảnh báo vượt ngân sách\n";
        $prompt .= "- Tạo báo cáo tài chính\n";
        $prompt .= "- Trả lời câu hỏi về quy định tài chính, ngân sách\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Tính toán chính xác, minh bạch\n";
        $prompt .= "- Bảo mật thông tin tài chính\n";
        
        return $prompt;
    }

    /**
     * Get prompt for project_management
     */
    protected function getProjectManagementPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên quản lý dự án đầu tư công.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Quản lý dự án đầu tư công\n";
        $prompt .= "- Theo dõi tiến độ, ngân sách\n";
        $prompt .= "- Phân tích rủi ro\n";
        $prompt .= "- Tạo báo cáo dự án\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Cung cấp thông tin chính xác, cập nhật\n";
        
        return $prompt;
    }

    /**
     * Get prompt for complaint_management
     */
    protected function getComplaintManagementPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên quản lý khiếu nại và tố cáo.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Quản lý khiếu nại và tố cáo\n";
        $prompt .= "- Tiếp nhận, phân loại\n";
        $prompt .= "- Theo dõi tiến độ giải quyết\n";
        $prompt .= "- Trả lời câu hỏi về quy trình giải quyết khiếu nại\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Bảo mật thông tin khiếu nại\n";
        $prompt .= "- Trả lời chính xác về quy trình\n";
        
        return $prompt;
    }

    /**
     * Get prompt for event_management
     */
    protected function getEventManagementPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên tổ chức sự kiện và hội nghị.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Tổ chức sự kiện và hội nghị\n";
        $prompt .= "- Lập kế hoạch sự kiện\n";
        $prompt .= "- Quản lý khách mời\n";
        $prompt .= "- Gửi thư mời tự động\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Tổ chức chi tiết, chu đáo\n";
        
        return $prompt;
    }

    /**
     * Get prompt for asset_management
     */
    protected function getAssetManagementPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên quản lý tài sản công.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG CHÍNH:**\n";
        $prompt .= "- Quản lý tài sản công\n";
        $prompt .= "- Theo dõi bảo trì\n";
        $prompt .= "- Kiểm kê định kỳ\n";
        $prompt .= "- Tạo báo cáo tài sản\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Cung cấp thông tin chính xác\n";
        
        return $prompt;
    }

    /**
     * Get generic prompt (fallback)
     */
    protected function getGenericPrompt(string $name, string $description): string
    {
        $prompt = "Bạn là {$name}, một trợ lý AI chuyên nghiệp.\n\n";
        
        if (!empty($description)) {
            $prompt .= "**MÔ TẢ:**\n{$description}\n\n";
        }
        
        $prompt .= "**CHỨC NĂNG:**\n";
        $prompt .= "- Hỗ trợ các tác vụ hành chính công\n";
        $prompt .= "- Trả lời câu hỏi và thực hiện yêu cầu của người dùng\n\n";
        
        $prompt .= "**QUY TẮC:**\n";
        $prompt .= "- Sử dụng ngôn ngữ lịch sự, chuyên nghiệp\n";
        $prompt .= "- Trả lời rõ ràng, chi tiết\n";
        
        return $prompt;
    }
}


