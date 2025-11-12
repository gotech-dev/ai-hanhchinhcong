# Giải Pháp Xử Lý Phần ".............." Trong Template DOCX

## 1. Vấn Đề

Trong template DOCX, có vô vàn format khác nhau của phần ".............." (dots placeholder) cần được AI tạo nội dung và fill vào:

### 1.1. Các Format Khác Nhau

1. **Dots đơn giản:**
   - `..............` (nhiều dấu chấm liên tiếp)
   - `...` (3 dấu chấm)
   - `..........` (10 dấu chấm)

2. **Dots với context:**
   - `BÁO CÁO HOẠT ĐỘNG` → `..............` (nội dung báo cáo)
   - `Nơi nhận: - ..............` (danh sách nơi nhận)
   - `Họ và tên: ..............` (tên người ký)

3. **Dots trong table:**
   - Table cells chứa `..............`
   - Table rows với `..............`

4. **Dots nhiều dòng:**
   - `..............` (dòng 1)
   - `..............` (dòng 2)
   - `..............` (dòng 3)

5. **Dots với format đặc biệt:**
   - `..............` (bold)
   - `..............` (italic)
   - `..............` (underline)

### 1.2. Thách Thức

- **Format đa dạng:** Không thể hardcode pattern matching
- **Context phức tạp:** Cần hiểu context xung quanh để generate đúng nội dung
- **Vị trí khác nhau:** Có thể ở bất kỳ đâu trong document
- **Format preservation:** Phải giữ nguyên format (font, size, style) khi replace

## 2. Phân Tích Chi Tiết

### 2.1. Context Analysis

Cần phân tích context xung quanh ".............." để hiểu nó là phần gì:

1. **Heading context:**
   - Nếu có heading "BÁO CÁO HOẠT ĐỘNG" → Generate nội dung báo cáo
   - Nếu có heading "TÓM TẮT" → Generate tóm tắt
   - Nếu có heading "KẾT LUẬN" → Generate kết luận

2. **Label context:**
   - Nếu có label "Nơi nhận:" → Generate danh sách nơi nhận
   - Nếu có label "Họ và tên:" → Generate tên người ký
   - Nếu có label "Chức vụ:" → Generate chức vụ

3. **Position context:**
   - Nếu ở đầu document → Có thể là tiêu đề hoặc mở đầu
   - Nếu ở giữa document → Có thể là nội dung chính
   - Nếu ở cuối document → Có thể là kết luận hoặc chữ ký

4. **Structure context:**
   - Nếu trong table → Có thể là dữ liệu trong bảng
   - Nếu trong list → Có thể là item trong danh sách
   - Nếu trong paragraph → Có thể là nội dung đoạn văn

### 2.2. Pattern Detection

Cần detect các pattern phổ biến:

1. **Dots pattern:**
   - `\.{3,}` (3+ dots)
   - `\.{10,}` (10+ dots)
   - `\.{20,}` (20+ dots)

2. **Context pattern:**
   - `([A-Z\s]+)\s*\.{3,}` (heading + dots)
   - `([^:]+):\s*\.{3,}` (label + dots)
   - `\.{3,}\s*([A-Z\s]+)` (dots + heading)

3. **Position pattern:**
   - Dots ở đầu dòng
   - Dots ở giữa dòng
   - Dots ở cuối dòng

## 3. Giải Pháp Đề Xuất

### 3.1. Approach 1: Context-Based AI Generation (Recommended)

**Ý tưởng:** Sử dụng AI để analyze context và generate content phù hợp.

**Flow:**
1. Extract text từ template DOCX
2. Detect tất cả các phần ".............." và context xung quanh
3. Group các dots theo context (heading, label, position)
4. Sử dụng AI để generate content cho từng group
5. Replace ".............." bằng content đã generate

**Ưu điểm:**
- Linh hoạt, xử lý được mọi format
- Hiểu được context phức tạp
- Generate content phù hợp với từng trường hợp

**Nhược điểm:**
- Cần nhiều AI calls (có thể optimize bằng batch)
- Chi phí API cao hơn
- Cần xử lý edge cases

**Implementation:**
```php
// 1. Detect dots placeholders với context
$dotsPlaceholders = $this->detectDotsPlaceholders($templateText);

// 2. Group theo context
$groupedDots = $this->groupDotsByContext($dotsPlaceholders);

// 3. Generate content cho từng group
$generatedContent = [];
foreach ($groupedDots as $context => $dots) {
    $content = $this->generateContentForContext($context, $dots, $collectedData);
    $generatedContent[$context] = $content;
}

// 4. Replace dots với generated content
$replacements = $this->buildReplacementsFromDots($dotsPlaceholders, $generatedContent);
```

### 3.2. Approach 2: Pattern-Based Mapping

**Ý tưởng:** Map các pattern phổ biến với data fields hoặc AI-generated content.

**Flow:**
1. Define các pattern phổ biến (heading + dots, label + dots, etc.)
2. Map pattern với data fields hoặc AI generation rules
3. Generate content dựa trên pattern mapping
4. Replace ".............." bằng content

**Ưu điểm:**
- Nhanh, không cần nhiều AI calls
- Chi phí thấp hơn
- Dễ maintain cho các pattern phổ biến

**Nhược điểm:**
- Không xử lý được các format lạ
- Cần maintain pattern list
- Không linh hoạt bằng AI

**Implementation:**
```php
// 1. Define pattern mappings
$patternMappings = [
    'BÁO CÁO HOẠT ĐỘNG.*\.{10,}' => [
        'type' => 'ai_generate',
        'prompt' => 'Tạo nội dung báo cáo hoạt động dựa trên data',
        'data_fields' => ['ten_co_quan', 'thoi_gian', 'ky_bao_cao'],
    ],
    'Nơi nhận:.*\.{10,}' => [
        'type' => 'data_field',
        'field' => 'noi_nhan',
        'fallback' => 'ai_generate',
    ],
    'Họ và tên:.*\.{10,}' => [
        'type' => 'data_field',
        'field' => 'ho_ten',
        'fallback' => 'ai_generate',
    ],
];

// 2. Match patterns và generate content
$replacements = [];
foreach ($patternMappings as $pattern => $mapping) {
    if (preg_match($pattern, $templateText, $matches)) {
        $content = $this->generateContentByMapping($mapping, $collectedData);
        $replacements[$matches[0]] = $content;
    }
}
```

### 3.3. Approach 3: Hybrid Approach (Best)

**Ý tưởng:** Kết hợp pattern-based mapping cho các pattern phổ biến và AI generation cho các pattern lạ.

**Flow:**
1. Detect tất cả các phần ".............."
2. Match với pattern mappings (nếu có)
3. Nếu không match → Sử dụng AI để generate
4. Replace ".............." bằng content

**Ưu điểm:**
- Cân bằng giữa performance và flexibility
- Xử lý được cả pattern phổ biến và lạ
- Chi phí hợp lý

**Nhược điểm:**
- Cần maintain cả pattern list và AI logic
- Phức tạp hơn một chút

**Implementation:**
```php
// 1. Detect dots placeholders
$dotsPlaceholders = $this->detectDotsPlaceholders($templateText);

// 2. Try pattern matching first
$replacements = [];
$unmatchedDots = [];

foreach ($dotsPlaceholders as $dots) {
    $matched = false;
    foreach ($patternMappings as $pattern => $mapping) {
        if (preg_match($pattern, $dots['context'], $matches)) {
            $content = $this->generateContentByMapping($mapping, $collectedData);
            $replacements[$dots['text']] = $content;
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $unmatchedDots[] = $dots;
    }
}

// 3. Use AI for unmatched dots
if (!empty($unmatchedDots)) {
    $aiGeneratedContent = $this->generateContentWithAI($unmatchedDots, $collectedData);
    $replacements = array_merge($replacements, $aiGeneratedContent);
}
```

## 4. Implementation Chi Tiết

### 4.1. Detect Dots Placeholders

```php
protected function detectDotsPlaceholders(string $templateText): array
{
    $placeholders = [];
    
    // Pattern: 3+ dots
    $pattern = '/\.{3,}/';
    preg_match_all($pattern, $templateText, $matches, PREG_OFFSET_CAPTURE);
    
    foreach ($matches[0] as $match) {
        $dots = $match[0];
        $position = $match[1];
        
        // Extract context (50 chars before and after)
        $contextStart = max(0, $position - 50);
        $contextEnd = min(strlen($templateText), $position + strlen($dots) + 50);
        $context = substr($templateText, $contextStart, $contextEnd - $contextStart);
        
        // Detect context type
        $contextType = $this->detectContextType($context, $position);
        
        $placeholders[] = [
            'text' => $dots,
            'position' => $position,
            'context' => $context,
            'context_type' => $contextType,
            'length' => strlen($dots),
        ];
    }
    
    return $placeholders;
}

protected function detectContextType(string $context, int $position): string
{
    // Check for heading
    if (preg_match('/^[A-Z\s]{5,}$/m', $context)) {
        return 'heading';
    }
    
    // Check for label
    if (preg_match('/([^:]+):\s*\.{3,}/', $context)) {
        return 'label';
    }
    
    // Check for table
    if (preg_match('/\|.*\.{3,}.*\|/', $context)) {
        return 'table';
    }
    
    // Check for list
    if (preg_match('/^[-*]\s*\.{3,}/m', $context)) {
        return 'list';
    }
    
    // Default: paragraph
    return 'paragraph';
}
```

### 4.2. Generate Content by Context

```php
protected function generateContentForContext(
    string $contextType,
    array $dots,
    array $collectedData
): string
{
    switch ($contextType) {
        case 'heading':
            return $this->generateContentForHeading($dots, $collectedData);
        case 'label':
            return $this->generateContentForLabel($dots, $collectedData);
        case 'table':
            return $this->generateContentForTable($dots, $collectedData);
        case 'list':
            return $this->generateContentForList($dots, $collectedData);
        default:
            return $this->generateContentForParagraph($dots, $collectedData);
    }
}

protected function generateContentForHeading(array $dots, array $collectedData): string
{
    // Extract heading text
    $heading = $this->extractHeading($dots['context']);
    
    // Build prompt for AI
    $prompt = "Tạo nội dung cho phần '{$heading}' trong báo cáo. ";
    $prompt .= "Dữ liệu có sẵn: " . json_encode($collectedData, JSON_UNESCAPED_UNICODE);
    
    // Generate with AI
    return $this->generateWithAI($prompt);
}

protected function generateContentForLabel(array $dots, array $collectedData): string
{
    // Extract label
    $label = $this->extractLabel($dots['context']);
    
    // Try to get from collected data first
    $fieldKey = $this->mapLabelToField($label);
    if (isset($collectedData[$fieldKey])) {
        return $collectedData[$fieldKey];
    }
    
    // Generate with AI if not found
    $prompt = "Tạo nội dung cho '{$label}' trong báo cáo. ";
    $prompt .= "Dữ liệu có sẵn: " . json_encode($collectedData, JSON_UNESCAPED_UNICODE);
    
    return $this->generateWithAI($prompt);
}
```

### 4.3. Pattern Mappings

```php
protected function getPatternMappings(): array
{
    return [
        // Heading patterns
        '/BÁO CÁO HOẠT ĐỘNG.*\.{10,}/' => [
            'type' => 'ai_generate',
            'prompt_template' => 'Tạo nội dung báo cáo hoạt động cho {ten_co_quan} trong {ky_bao_cao}',
            'data_fields' => ['ten_co_quan', 'ky_bao_cao', 'thoi_gian'],
        ],
        '/TÓM TẮT.*\.{10,}/' => [
            'type' => 'ai_generate',
            'prompt_template' => 'Tạo tóm tắt báo cáo',
            'data_fields' => [],
        ],
        '/KẾT LUẬN.*\.{10,}/' => [
            'type' => 'ai_generate',
            'prompt_template' => 'Tạo kết luận báo cáo',
            'data_fields' => [],
        ],
        
        // Label patterns
        '/Nơi nhận:.*\.{10,}/' => [
            'type' => 'data_field',
            'field' => 'noi_nhan',
            'fallback' => 'ai_generate',
            'fallback_prompt' => 'Tạo danh sách nơi nhận báo cáo',
        ],
        '/Họ và tên:.*\.{10,}/' => [
            'type' => 'data_field',
            'field' => 'ho_ten',
            'fallback' => 'ai_generate',
            'fallback_prompt' => 'Tạo tên người ký báo cáo',
        ],
        '/Chức vụ:.*\.{10,}/' => [
            'type' => 'data_field',
            'field' => 'chuc_vu',
            'fallback' => 'ai_generate',
            'fallback_prompt' => 'Tạo chức vụ người ký báo cáo',
        ],
        
        // Table patterns
        '/\|.*\.{10,}.*\|/' => [
            'type' => 'ai_generate',
            'prompt_template' => 'Tạo nội dung cho ô trong bảng',
            'data_fields' => [],
        ],
    ];
}
```

## 5. Integration với SmartDocxReplacer

### 5.1. Update buildReplacementsFromData

```php
protected function buildReplacementsFromData(array $data, string $templateText = ''): array
{
    $replacements = [];
    
    // 1. Build replacements từ data (existing logic)
    $dataReplacements = $this->buildDataReplacements($data);
    $replacements = array_merge($replacements, $dataReplacements);
    
    // 2. Detect và generate content cho dots placeholders
    if (!empty($templateText)) {
        $dotsReplacements = $this->buildDotsReplacements($templateText, $data);
        $replacements = array_merge($replacements, $dotsReplacements);
    }
    
    return $replacements;
}

protected function buildDotsReplacements(string $templateText, array $data): array
{
    $replacements = [];
    
    // 1. Detect dots placeholders
    $dotsPlaceholders = $this->detectDotsPlaceholders($templateText);
    
    if (empty($dotsPlaceholders)) {
        return $replacements;
    }
    
    // 2. Group by context
    $groupedDots = $this->groupDotsByContext($dotsPlaceholders);
    
    // 3. Generate content for each group
    foreach ($groupedDots as $contextType => $dots) {
        $content = $this->generateContentForContext($contextType, $dots, $data);
        
        // Add replacement for each dots in group
        foreach ($dots as $dot) {
            $replacements[$dot['text']] = $content;
        }
    }
    
    return $replacements;
}
```

### 5.2. Update generateWithSmartReplacer

```php
protected function generateWithSmartReplacer(
    UserReport $report,
    AiAssistant $assistant,
    string $templatePath,
    array $parsedContent,
    array $collectedData
): string {
    // ... existing code ...
    
    // Extract template text for dots detection
    $templateText = $this->documentProcessor->extractText($templatePath);
    
    // Build replacements array (including dots replacements)
    $replacements = $this->buildReplacementsFromData($allData, $templateText);
    
    // ... rest of code ...
}
```

## 6. Testing Strategy

### 6.1. Unit Tests

- Test detectDotsPlaceholders với các format khác nhau
- Test detectContextType với các context khác nhau
- Test generateContentForContext với các context types
- Test pattern matching với các patterns phổ biến

### 6.2. Integration Tests

- Test với template thực tế (Nghị định 30)
- Test với các format dots khác nhau
- Test với các context khác nhau
- Test format preservation sau khi replace

### 6.3. Edge Cases

- Dots ở đầu document
- Dots ở cuối document
- Dots trong table
- Dots với format đặc biệt (bold, italic)
- Dots nhiều dòng
- Dots không có context rõ ràng

## 7. Performance Considerations

### 7.1. Optimization

- **Batch AI calls:** Group các dots có cùng context để generate một lần
- **Cache generated content:** Cache content đã generate cho các context tương tự
- **Pattern matching first:** Ưu tiên pattern matching trước khi dùng AI

### 7.2. Cost Estimation

- **Pattern matching:** Free (local processing)
- **AI generation:** ~$0.01-0.05 per dots placeholder (depending on context length)
- **Average template:** 5-10 dots placeholders → ~$0.05-0.50 per report

## 8. Next Steps

1. **Phase 1:** Implement dots detection và context analysis
2. **Phase 2:** Implement pattern mappings cho các pattern phổ biến
3. **Phase 3:** Implement AI generation cho unmatched dots
4. **Phase 4:** Testing và optimization
5. **Phase 5:** Integration với SmartDocxReplacer

## 9. Conclusion

Giải pháp Hybrid Approach (kết hợp pattern-based mapping và AI generation) là tốt nhất vì:
- Xử lý được cả pattern phổ biến và lạ
- Cân bằng giữa performance và flexibility
- Chi phí hợp lý
- Dễ maintain và extend






