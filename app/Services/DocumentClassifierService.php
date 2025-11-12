<?php

namespace App\Services;

use App\Enums\DocumentType;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class DocumentClassifierService
{
    /**
     * Classify document using AI
     * 
     * @param string $content Document content (from OCR or text extraction)
     * @param string|null $fileName File name (optional)
     * @return array{classification: array, document_type: string|null, urgency: string, processing_time: int|null, suggested_handler: string|null}
     */
    public function classify(string $content, ?string $fileName = null): array
    {
        try {
            $prompt = $this->buildClassificationPrompt($content, $fileName);
            
            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);
            
            $result = json_decode($response->choices[0]->message->content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse AI classification response', [
                    'response' => $response->choices[0]->message->content,
                ]);
                return $this->getFallbackClassification($content);
            }
            
            return [
                'classification' => $result,
                'document_type' => $result['document_type'] ?? null,
                'urgency' => $result['urgency'] ?? 'thuong',
                'processing_time' => $result['processing_time'] ?? null,
                'suggested_handler' => $result['suggested_handler'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to classify document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->getFallbackClassification($content);
        }
    }
    
    /**
     * Build classification prompt
     */
    protected function buildClassificationPrompt(string $content, ?string $fileName): string
    {
        $prompt = "Bạn là chuyên gia phân loại văn bản hành chính Việt Nam.\n\n";
        $prompt .= "Hãy phân loại văn bản sau:\n\n";
        
        if ($fileName) {
            $prompt .= "**Tên file:** {$fileName}\n\n";
        }
        
        $prompt .= "**Nội dung:**\n";
        $prompt .= substr($content, 0, 3000) . "\n\n"; // Limit content to avoid token limit
        
        $prompt .= "Hãy phân tích và trả về JSON với các thông tin:\n";
        $prompt .= "1. **document_type**: Loại văn bản (cong_van, quyet_dinh, to_trinh, bao_cao, bien_ban, thong_bao, nghi_quyet)\n";
        $prompt .= "2. **urgency**: Mức độ khẩn cấp (khan_cap, thuong, khong_khan)\n";
        $prompt .= "3. **processing_time**: Thời hạn xử lý (số ngày làm việc, null nếu không xác định được)\n";
        $prompt .= "4. **suggested_handler**: Phòng ban/người xử lý phù hợp (vd: Phòng Tài chính, Phòng Nhân sự, Giám đốc)\n";
        $prompt .= "5. **sender**: Nơi gửi (nếu là văn bản đến)\n";
        $prompt .= "6. **receiver**: Nơi nhận (nếu là văn bản đi)\n";
        $prompt .= "7. **summary**: Tóm tắt nội dung chính\n";
        $prompt .= "8. **keywords**: Từ khóa chính (array)\n";
        
        return $prompt;
    }
    
    /**
     * Get system prompt for classification
     */
    protected function getSystemPrompt(): string
    {
        return "Bạn là chuyên gia phân loại văn bản hành chính Việt Nam. " .
               "Bạn phải phân tích nội dung văn bản và xác định:\n" .
               "- Loại văn bản (Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết)\n" .
               "- Mức độ khẩn cấp (Khẩn cấp: cần xử lý trong 1 ngày, Thường: 5 ngày, Không khẩn cấp: 10 ngày)\n" .
               "- Thời hạn xử lý theo quy định\n" .
               "- Phòng ban/người xử lý phù hợp dựa trên nội dung\n" .
               "- Nơi gửi/nhận\n" .
               "Trả về kết quả dưới dạng JSON.";
    }
    
    /**
     * Get fallback classification if AI fails
     */
    protected function getFallbackClassification(string $content): array
    {
        // Simple keyword-based fallback
        $contentLower = strtolower($content);
        
        $documentType = null;
        if (str_contains($contentLower, 'công văn') || str_contains($contentLower, 'cong van')) {
            $documentType = 'cong_van';
        } elseif (str_contains($contentLower, 'quyết định') || str_contains($contentLower, 'quyet dinh')) {
            $documentType = 'quyet_dinh';
        } elseif (str_contains($contentLower, 'tờ trình') || str_contains($contentLower, 'to trinh')) {
            $documentType = 'to_trinh';
        } elseif (str_contains($contentLower, 'báo cáo') || str_contains($contentLower, 'bao cao')) {
            $documentType = 'bao_cao';
        } elseif (str_contains($contentLower, 'biên bản') || str_contains($contentLower, 'bien ban')) {
            $documentType = 'bien_ban';
        } elseif (str_contains($contentLower, 'thông báo') || str_contains($contentLower, 'thong bao')) {
            $documentType = 'thong_bao';
        } elseif (str_contains($contentLower, 'nghị quyết') || str_contains($contentLower, 'nghi quyet')) {
            $documentType = 'nghi_quyet';
        }
        
        // Detect urgency keywords
        $urgency = 'thuong';
        if (str_contains($contentLower, 'khẩn cấp') || str_contains($contentLower, 'khan cap') || 
            str_contains($contentLower, 'gấp') || str_contains($contentLower, 'gap')) {
            $urgency = 'khan_cap';
        } elseif (str_contains($contentLower, 'không khẩn') || str_contains($contentLower, 'khong khan')) {
            $urgency = 'khong_khan';
        }
        
        return [
            'classification' => [
                'document_type' => $documentType,
                'urgency' => $urgency,
                'processing_time' => $this->getProcessingTime($urgency),
                'suggested_handler' => null,
                'sender' => null,
                'receiver' => null,
                'summary' => substr($content, 0, 200),
                'keywords' => [],
            ],
            'document_type' => $documentType,
            'urgency' => $urgency,
            'processing_time' => $this->getProcessingTime($urgency),
            'suggested_handler' => null,
        ];
    }
    
    /**
     * Get processing time based on urgency
     */
    protected function getProcessingTime(string $urgency): int
    {
        return match($urgency) {
            'khan_cap' => 1,
            'thuong' => 5,
            'khong_khan' => 10,
            default => 5,
        };
    }
}



