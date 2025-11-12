# INTELLIGENT DOCUMENT PROCESSING (IDP)
## Xử lý văn bản thông minh với AI

---

## 🎯 MỤC TIÊU

Xây dựng hệ thống AI tự động phân tích, xử lý và tạo văn bản hành chính một cách thông minh, không chỉ crawl data mà sử dụng AI để hiểu và xử lý văn bản.

---

## 🛠️ CÔNG NGHỆ SỬ DỤNG

### 1. AI/ML Services

**OpenAI API:**
- **GPT-4o / GPT-4o-mini** - Chat completion, document analysis
- **GPT-4 Turbo** - Complex reasoning, long context
- **Text Embeddings (ada-002)** - Vector search, semantic understanding
- **JSON Mode** - Structured data extraction
- **Function Calling** - Tool use, structured output

**Các tính năng OpenAI API:**
- `response_format: { type: 'json_object' }` - Structured output
- `temperature` - Control creativity (0.3 cho chính xác, 0.7 cho sáng tạo)
- `max_tokens` - Control output length
- `stream: true` - Streaming response

### 2. Document Processing Libraries

**PHP:**
- `phpoffice/phpword` - Xử lý DOCX
- `spatie/pdf-to-text` - Extract text từ PDF
- `symfony/dom-crawler` - Parse HTML (nếu cần)

**Python (nếu cần):**
- `python-docx` - Xử lý DOCX
- `PyPDF2` / `pdfplumber` - Extract PDF
- `beautifulsoup4` - Parse HTML

### 3. Database & Storage

- **MySQL/PostgreSQL** - Lưu trữ văn bản, metadata
- **Vector Database** - Semantic search (embeddings)
- **File Storage** - Lưu trữ file DOCX, PDF

---

## 📋 CÁC TÍNH NĂNG VÀ CÁCH TRIỂN KHAI

### 1. AI-Powered Document Analyzer

#### 1.1. Tự động phát hiện loại văn bản

**Công nghệ:** OpenAI GPT-4o với JSON Mode

**Cách làm:**
```php
// app/Services/IntelligentDocumentAnalyzer.php
namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class IntelligentDocumentAnalyzer
{
    /**
     * Phân tích văn bản và phát hiện loại văn bản
     */
    public function analyzeDocument(string $text): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Bạn là chuyên gia phân tích văn bản hành chính Việt Nam. 
                                 Phân tích văn bản và trả về kết quả dưới dạng JSON.',
                ],
                [
                    'role' => 'user',
                    'content' => "Phân tích văn bản sau và xác định:\n\n{$text}\n\n" .
                                "Hãy trả về JSON với các trường:\n" .
                                "- document_type: Loại văn bản (Báo cáo, Quyết định, Công văn, ...)\n" .
                                "- has_issues: Có vấn đề không (true/false)\n" .
                                "- issues: Danh sách vấn đề\n" .
                                "- suggestions: Đề xuất cải thiện\n" .
                                "- priority: Mức độ ưu tiên (high/medium/low)",
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3, // Thấp để chính xác
        ]);
        
        $result = json_decode($response->choices[0]->message->content, true);
        
        return [
            'document_type' => $result['document_type'] ?? 'Unknown',
            'has_issues' => $result['has_issues'] ?? false,
            'issues' => $result['issues'] ?? [],
            'suggestions' => $result['suggestions'] ?? [],
            'priority' => $result['priority'] ?? 'medium',
        ];
    }
}
```

**Ví dụ output:**
```json
{
  "document_type": "Báo cáo",
  "has_issues": true,
  "issues": [
    "Thiếu số và ký hiệu văn bản",
    "Nội dung quá ngắn",
    "Thiếu phần kết luận"
  ],
  "suggestions": [
    "Thêm 'Số: 01/BC-ABC' theo quy định",
    "Bổ sung thông tin chi tiết hơn",
    "Thêm phần 'Kết luận và kiến nghị'"
  ],
  "priority": "high"
}
```

#### 1.2. Tự động phát hiện vấn đề

**Công nghệ:** OpenAI GPT-4o với structured prompt

**Cách làm:**
```php
/**
 * Phát hiện vấn đề trong văn bản
 */
public function detectIssues(string $text, array $context = []): array
{
    $contextText = '';
    if (!empty($context['previous_documents'])) {
        $contextText = "\n\nVăn bản trước đó:\n" . 
                      implode("\n---\n", $context['previous_documents']);
    }
    
    $response = OpenAI::chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là chuyên gia kiểm tra văn bản hành chính Việt Nam. ' .
                           'Phát hiện các vấn đề về format, nội dung, tuân thủ quy định.',
            ],
            [
                'role' => 'user',
                'content' => "Kiểm tra văn bản sau có vấn đề gì:\n\n{$text}{$contextText}\n\n" .
                            "Trả về JSON với:\n" .
                            "- format_issues: Vấn đề về format\n" .
                            "- content_issues: Vấn đề về nội dung\n" .
                            "- compliance_issues: Vấn đề về tuân thủ\n" .
                            "- missing_info: Thông tin thiếu\n" .
                            "- severity: Mức độ nghiêm trọng (critical/high/medium/low)",
            ],
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.2, // Rất thấp để chính xác
    ]);
    
    return json_decode($response->choices[0]->message->content, true);
}
```

#### 1.3. Tự động đề xuất cải thiện

**Công nghệ:** OpenAI GPT-4o với context-aware prompt

**Cách làm:**
```php
/**
 * Đề xuất cách cải thiện văn bản
 */
public function suggestImprovements(string $text, array $issues): array
{
    $issuesText = implode("\n- ", $issues);
    
    $response = OpenAI::chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là chuyên gia cải thiện văn bản hành chính. ' .
                           'Đề xuất cách sửa lỗi và cải thiện văn bản.',
            ],
            [
                'role' => 'user',
                'content' => "Văn bản:\n{$text}\n\n" .
                            "Vấn đề phát hiện:\n- {$issuesText}\n\n" .
                            "Hãy đề xuất cách sửa lỗi và cải thiện. " .
                            "Trả về JSON với:\n" .
                            "- fixes: Cách sửa lỗi cụ thể\n" .
                            "- improvements: Cách cải thiện\n" .
                            "- examples: Ví dụ cụ thể",
            ],
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.4, // Vừa phải để có sáng tạo
    ]);
    
    return json_decode($response->choices[0]->message->content, true);
}
```

---

### 2. Intelligent Content Generator

#### 2.1. Tự động tạo nội dung dựa trên context

**Công nghệ:** OpenAI GPT-4o với long context

**Cách làm:**
```php
// app/Services/IntelligentContentGenerator.php
namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use App\Models\ChatSession;
use App\Models\UserReport;

class IntelligentContentGenerator
{
    /**
     * Tạo nội dung thông minh dựa trên context
     */
    public function generateContent(
        string $request,
        ChatSession $session,
        array $templateStructure,
        array $collectedData = []
    ): string {
        // Lấy lịch sử công việc
        $history = $this->getWorkHistory($session);
        
        // Lấy văn bản liên quan
        $relatedDocuments = $this->getRelatedDocuments($session);
        
        // Build context
        $context = [
            'user_request' => $request,
            'template_structure' => $templateStructure,
            'collected_data' => $collectedData,
            'work_history' => $history,
            'related_documents' => $relatedDocuments,
        ];
        
        // Generate với AI
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt($context),
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildContentPrompt($context),
                ],
            ],
            'temperature' => 0.7, // Vừa phải để có sáng tạo
            'max_tokens' => 4000,
        ]);
        
        return $response->choices[0]->message->content;
    }
    
    /**
     * Build system prompt với context
     */
    protected function buildSystemPrompt(array $context): string
    {
        return "Bạn là chuyên gia tạo văn bản hành chính Việt Nam.\n" .
               "Bạn tạo nội dung dựa trên:\n" .
               "- Yêu cầu của người dùng\n" .
               "- Cấu trúc template\n" .
               "- Dữ liệu đã thu thập\n" .
               "- Lịch sử công việc trước đó\n" .
               "- Văn bản liên quan\n\n" .
               "Tạo nội dung:\n" .
               "- Phù hợp với template\n" .
               "- Nhất quán với lịch sử\n" .
               "- Văn phong hành chính\n" .
               "- Ngắn gọn, rõ ràng, logic";
    }
    
    /**
     * Build content prompt
     */
    protected function buildContentPrompt(array $context): string
    {
        $prompt = "Yêu cầu: {$context['user_request']}\n\n";
        
        if (!empty($context['template_structure'])) {
            $prompt .= "Cấu trúc template:\n";
            $prompt .= json_encode($context['template_structure'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $prompt .= "\n\n";
        }
        
        if (!empty($context['collected_data'])) {
            $prompt .= "Dữ liệu đã thu thập:\n";
            $prompt .= json_encode($context['collected_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $prompt .= "\n\n";
        }
        
        if (!empty($context['work_history'])) {
            $prompt .= "Lịch sử công việc:\n";
            foreach ($context['work_history'] as $item) {
                $prompt .= "- {$item}\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($context['related_documents'])) {
            $prompt .= "Văn bản liên quan:\n";
            foreach ($context['related_documents'] as $doc) {
                $prompt .= "- {$doc}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Hãy tạo nội dung văn bản phù hợp với yêu cầu và context trên.";
        
        return $prompt;
    }
    
    /**
     * Lấy lịch sử công việc
     */
    protected function getWorkHistory(ChatSession $session): array
    {
        $history = [];
        
        // Lấy các báo cáo trước đó
        $previousReports = UserReport::where('user_id', $session->user_id)
            ->where('id', '<', $session->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($previousReports as $report) {
            $history[] = "Báo cáo {$report->created_at->format('d/m/Y')}: " . 
                        substr($report->report_content, 0, 200);
        }
        
        return $history;
    }
    
    /**
     * Lấy văn bản liên quan
     */
    protected function getRelatedDocuments(ChatSession $session): array
    {
        // Sử dụng VectorSearchService để tìm văn bản liên quan
        $vectorSearch = app(VectorSearchService::class);
        
        // Lấy message cuối cùng
        $lastMessage = $session->messages()->orderBy('created_at', 'desc')->first();
        
        if (!$lastMessage) {
            return [];
        }
        
        // Tìm văn bản liên quan
        $related = $vectorSearch->searchSimilar(
            $lastMessage->content,
            $session->aiAssistant->id,
            3 // Top 3
        );
        
        return array_map(fn($r) => $r['content'], $related);
    }
}
```

#### 2.2. Tự động điều chỉnh văn phong

**Công nghệ:** OpenAI GPT-4o với style transfer

**Cách làm:**
```php
/**
 * Điều chỉnh văn phong sang hành chính
 */
public function adjustTone(string $text, string $targetTone = 'administrative'): string
{
    $toneMap = [
        'administrative' => 'văn phong hành chính (trang trọng, khách quan)',
        'formal' => 'văn phong trang trọng',
        'casual' => 'văn phong thông thường',
    ];
    
    $response = OpenAI::chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là chuyên gia chuyển đổi văn phong. ' .
                           'Chuyển đổi văn bản sang văn phong hành chính Việt Nam.',
            ],
            [
                'role' => 'user',
                'content' => "Văn bản gốc:\n{$text}\n\n" .
                            "Hãy chuyển đổi sang {$toneMap[$targetTone]}.\n" .
                            "Giữ nguyên nội dung, chỉ thay đổi cách diễn đạt.",
            ],
        ],
        'temperature' => 0.5,
    ]);
    
    return $response->choices[0]->message->content;
}
```

#### 2.3. Tự động kiểm tra tính nhất quán

**Công nghệ:** OpenAI GPT-4o với comparison

**Cách làm:**
```php
/**
 * Kiểm tra tính nhất quán với văn bản trước
 */
public function checkConsistency(string $newText, array $previousTexts): array
{
    $previousText = implode("\n---\n", $previousTexts);
    
    $response = OpenAI::chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là chuyên gia kiểm tra tính nhất quán văn bản. ' .
                           'Phát hiện mâu thuẫn và thiếu nhất quán.',
            ],
            [
                'role' => 'user',
                'content' => "Văn bản mới:\n{$newText}\n\n" .
                            "Văn bản trước đó:\n{$previousText}\n\n" .
                            "Kiểm tra tính nhất quán. Trả về JSON:\n" .
                            "- is_consistent: Có nhất quán không (true/false)\n" .
                            "- contradictions: Mâu thuẫn (nếu có)\n" .
                            "- suggestions: Đề xuất sửa",
            ],
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.2,
    ]);
    
    return json_decode($response->choices[0]->message->content, true);
}
```

---

### 3. Smart Document Comparison

#### 3.1. Tự động so sánh văn bản

**Công nghệ:** OpenAI GPT-4o với structured comparison

**Cách làm:**
```php
// app/Services/SmartDocumentComparator.php
namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class SmartDocumentComparator
{
    /**
     * So sánh hai văn bản thông minh
     */
    public function compareDocuments(string $text1, string $text2): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Bạn là chuyên gia so sánh văn bản hành chính. ' .
                               'So sánh và phân tích sự khác biệt.',
                ],
                [
                    'role' => 'user',
                    'content' => "Văn bản 1:\n{$text1}\n\n" .
                                "Văn bản 2:\n{$text2}\n\n" .
                                "So sánh và phân tích. Trả về JSON:\n" .
                                "- differences: Sự khác biệt\n" .
                                "- changes: Thay đổi (added/removed/modified)\n" .
                                "- impact: Tác động\n" .
                                "- trend: Xu hướng (tăng/giảm/ổn định)\n" .
                                "- suggestions: Đề xuất",
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);
        
        return json_decode($response->choices[0]->message->content, true);
    }
    
    /**
     * Phân tích xu hướng từ nhiều văn bản
     */
    public function analyzeTrend(array $documents): array
    {
        $documentsText = '';
        foreach ($documents as $index => $doc) {
            $documentsText .= "Văn bản " . ($index + 1) . ":\n{$doc}\n\n";
        }
        
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Bạn là chuyên gia phân tích xu hướng. ' .
                               'Phân tích xu hướng từ nhiều văn bản.',
                ],
                [
                    'role' => 'user',
                    'content' => "Các văn bản:\n{$documentsText}\n\n" .
                                "Phân tích xu hướng. Trả về JSON:\n" .
                                "- trend: Xu hướng (tăng/giảm/ổn định)\n" .
                                "- changes: Thay đổi chính\n" .
                                "- insights: Insights\n" .
                                "- predictions: Dự đoán",
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4,
        ]);
        
        return json_decode($response->choices[0]->message->content, true);
    }
}
```

---

## 🔧 TÍCH HỢP VÀO HỆ THỐNG

### 1. Tích hợp vào ReportGenerator

```php
// app/Services/ReportGenerator.php
public function generateReport(...): array
{
    // ... existing code ...
    
    // ✅ NEW: Intelligent analysis
    $analyzer = app(IntelligentDocumentAnalyzer::class);
    $analysis = $analyzer->analyzeDocument($reportContent);
    
    // ✅ NEW: Check issues
    if ($analysis['has_issues']) {
        $suggestions = $analyzer->suggestImprovements($reportContent, $analysis['issues']);
        // Log hoặc hiển thị suggestions
    }
    
    // ✅ NEW: Check consistency
    $previousReports = $this->getPreviousReports($session);
    if (!empty($previousReports)) {
        $consistency = $analyzer->checkConsistency($reportContent, $previousReports);
        // Log hoặc hiển thị consistency check
    }
    
    return [
        'report_content' => $reportContent,
        'analysis' => $analysis,
        'suggestions' => $suggestions ?? [],
        'consistency' => $consistency ?? null,
    ];
}
```

### 2. Tích hợp vào SmartAssistantEngine

```php
// app/Services/SmartAssistantEngine.php
public function processMessage(...): array
{
    // ... existing code ...
    
    // ✅ NEW: Intelligent document processing
    if ($intent['type'] === 'create_report') {
        $analyzer = app(IntelligentDocumentAnalyzer::class);
        $contentGenerator = app(IntelligentContentGenerator::class);
        
        // Analyze request
        $analysis = $analyzer->analyzeDocument($userMessage);
        
        // Generate content intelligently
        $content = $contentGenerator->generateContent(
            $userMessage,
            $session,
            $templateStructure,
            $collectedData
        );
        
        // Check consistency
        $consistency = $analyzer->checkConsistency($content, $previousDocuments);
        
        // Return with analysis
        return [
            'response' => $content,
            'analysis' => $analysis,
            'suggestions' => $consistency['suggestions'] ?? [],
        ];
    }
}
```

---

## 📊 WORKFLOW TỔNG QUAN

```
User Request
    ↓
Extract Text (nếu có file)
    ↓
AI Analysis (GPT-4o)
    ├─ Detect Document Type
    ├─ Detect Issues
    ├─ Suggest Improvements
    └─ Check Consistency
    ↓
Generate Content (GPT-4o)
    ├─ Use Context (history, related docs)
    ├─ Adjust Tone
    └─ Optimize Content
    ↓
Post-Processing
    ├─ Format Check
    ├─ Compliance Check
    └─ Final Review
    ↓
Return Result
    ├─ Content
    ├─ Analysis
    ├─ Suggestions
    └─ Warnings
```

---

## 💡 VÍ DỤ SỬ DỤNG

### Ví dụ 1: Phân tích văn bản

```php
$analyzer = app(IntelligentDocumentAnalyzer::class);
$result = $analyzer->analyzeDocument($documentText);

// Output:
// {
//   "document_type": "Báo cáo",
//   "has_issues": true,
//   "issues": ["Thiếu số văn bản", "Nội dung quá ngắn"],
//   "suggestions": ["Thêm 'Số: 01/BC-ABC'", "Bổ sung thông tin"],
//   "priority": "high"
// }
```

### Ví dụ 2: Tạo nội dung thông minh

```php
$generator = app(IntelligentContentGenerator::class);
$content = $generator->generateContent(
    "Tạo báo cáo hoạt động tháng 12",
    $session,
    $templateStructure,
    $collectedData
);

// AI tự động:
// - Lấy dữ liệu từ báo cáo tháng 11
// - So sánh và phân tích xu hướng
// - Tạo nội dung phù hợp
// - Kiểm tra tính nhất quán
```

### Ví dụ 3: So sánh văn bản

```php
$comparator = app(SmartDocumentComparator::class);
$result = $comparator->compareDocuments($reportNov, $reportDec);

// Output:
// {
//   "differences": ["Tăng 20% số lượng công việc"],
//   "changes": {"added": ["3 dự án mới"], "removed": []},
//   "impact": "Tích cực",
//   "trend": "Tăng",
//   "suggestions": ["Tập trung vào dự án mới"]
// }
```

---

## ✅ KẾT QUẢ MONG ĐỢI

Sau khi triển khai IDP, hệ thống có thể:

1. ✅ **Tự động phân tích văn bản** - Phát hiện loại, vấn đề, ưu tiên
2. ✅ **Tự động tạo nội dung thông minh** - Dựa trên context, lịch sử
3. ✅ **Tự động so sánh và phân tích** - Xu hướng, tác động, insights
4. ✅ **Tự động đề xuất cải thiện** - Cách sửa lỗi, cách tối ưu
5. ✅ **Tự động kiểm tra tính nhất quán** - Mâu thuẫn, thiếu nhất quán

---

## 🔗 LIÊN KẾT

- [advanced-feature.md](./advanced-feature.md) - Tài liệu tổng quan về các tính năng nâng cao
- [OpenAI API Documentation](https://platform.openai.com/docs) - Tài liệu OpenAI API



