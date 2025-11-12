# Phân Tích Flow Tạo Báo Cáo: Vấn Đề và Đề Xuất

## 🔴 Vấn Đề Hiện Tại

### Tình Huống:
User: **"tôi muốn tạo 1 báo cáo mẫu"** (request chung chung)

### Flow Hiện Tại (Có Vấn Đề):

```
1. User: "tôi muốn tạo 1 báo cáo mẫu"
   ↓
2. Intent Recognition: `create_report`
   ↓
3. Workflow Planning: Tạo workflow từ template
   ↓
4. Check Next Step:
   - Nếu workflow không có steps hoặc nextStep = null
   - HOẶC collectedData = [] (rỗng)
   - → Rơi vào branch `else` (line 156)
   ↓
5. Generate Report NGAY LẬP TỨC với collectedData = []
   ↓
6. Kết quả: Báo cáo rỗng (chỉ có template với placeholders)
```

### ❌ Vấn Đề:

1. **Tạo báo cáo quá sớm**: Generate report ngay cả khi user chưa cung cấp thông tin
2. **Không có quá trình thu thập thông tin**: Không hỏi user về các field cần thiết
3. **Báo cáo rỗng**: Chỉ có template với placeholders, không có nội dung thực sự
4. **Trải nghiệm người dùng kém**: User không hiểu tại sao báo cáo lại rỗng

## ✅ Flow Mong Muốn

### Tình Huống 1: User Request Chung Chung

```
1. User: "tôi muốn tạo 1 báo cáo mẫu"
   ↓
2. Intent Recognition: `create_report` (vague request)
   ↓
3. System Phân Tích:
   - Analyze template structure
   - Identify required fields
   - Check collectedData: [] (rỗng)
   ↓
4. System Response:
   "Tôi sẽ giúp bạn tạo báo cáo. Để tạo báo cáo phù hợp, tôi cần một số thông tin:
   
   📋 Thông tin cần thiết:
   - Tên công ty/tổ chức: ?
   - Năm báo cáo: ?
   - Loại báo cáo: ?
   - [Các field khác từ template]
   
   Bạn có thể cung cấp thông tin này không?"
   ↓
5. User cung cấp thông tin (có thể một lần hoặc nhiều lần)
   ↓
6. System extract data và lưu vào collectedData
   ↓
7. Check: Đã đủ thông tin chưa?
   - Nếu chưa đủ: Tiếp tục hỏi field còn thiếu
   - Nếu đủ: Chuyển sang bước 8
   ↓
8. AI Generate Content:
   - Analyze template structure
   - Generate content mới dựa trên collectedData và user request
   - Map content vào template (giữ format)
   ↓
9. Tạo DOCX và hiển thị preview
```

### Tình Huống 2: User Request Cụ Thể

```
1. User: "Tạo báo cáo thường niên cho công ty ABC năm 2024"
   ↓
2. Intent Recognition: `create_report` (specific request)
   ↓
3. System Extract Data:
   - Tên công ty: ABC
   - Năm: 2024
   - Loại báo cáo: Thường niên
   ↓
4. System Check:
   - Analyze template để xem còn thiếu field gì
   - Nếu còn thiếu: Hỏi thêm
   - Nếu đủ: Chuyển sang bước 5
   ↓
5. AI Generate Content và tạo báo cáo
```

## 🔧 Đề Xuất Cải Thiện

### 1. Thêm Logic Phân Biệt Request Chung vs Cụ Thể

**File**: `app/Services/SmartAssistantEngine.php`

```php
protected function handleCreateReport(...) {
    $collectedData = $session->collected_data ?? [];
    
    // ✅ MỚI: Phân tích request có đủ thông tin không
    $requestAnalysis = $this->analyzeRequestCompleteness($userMessage, $workflow, $collectedData);
    
    if ($requestAnalysis['is_vague'] && empty($collectedData)) {
        // Request chung chung + chưa có data → Hỏi thông tin
        return $this->askForRequiredInfo($workflow, $assistant);
    }
    
    // Check next step như cũ
    $nextStep = $this->getNextStep($workflow, $collectedData);
    
    if ($nextStep && $nextStep['type'] === 'collect_info') {
        // Extract data và tiếp tục...
    } else {
        // ✅ MỚI: Chỉ generate nếu có đủ data HOẶC user yêu cầu rõ ràng
        if (empty($collectedData) && !$requestAnalysis['has_sufficient_info']) {
            // Chưa có data và request không đủ cụ thể → Hỏi thêm
            return $this->askForRequiredInfo($workflow, $assistant);
        }
        
        // Generate report...
    }
}
```

### 2. Thêm Method Phân Tích Request

```php
/**
 * Phân tích request có đủ thông tin để tạo báo cáo không
 */
protected function analyzeRequestCompleteness(
    string $userMessage, 
    array $workflow, 
    array $collectedData
): array {
    // Check collectedData
    $hasData = !empty($collectedData);
    
    // Check user message có chứa thông tin cụ thể không
    $hasSpecificInfo = $this->extractSpecificInfo($userMessage, $workflow);
    
    // Check workflow có required fields không
    $requiredFields = $this->getRequiredFields($workflow);
    $hasRequiredFields = !empty($requiredFields);
    
    return [
        'is_vague' => !$hasData && !$hasSpecificInfo,
        'has_sufficient_info' => $hasData || $hasSpecificInfo,
        'has_required_fields' => $hasRequiredFields,
        'missing_fields' => $this->getMissingFields($workflow, $collectedData),
    ];
}
```

### 3. Thêm Method Hỏi Thông Tin

```php
/**
 * Hỏi user về thông tin cần thiết
 */
protected function askForRequiredInfo(array $workflow, AiAssistant $assistant): array
{
    // Get required fields từ workflow
    $requiredFields = $this->getRequiredFields($workflow);
    
    if (empty($requiredFields)) {
        // Không có required fields → Có thể generate với AI
        return [
            'response' => "Tôi sẽ tạo báo cáo cho bạn. Bạn có muốn tôi tạo báo cáo mẫu với nội dung mặc định không?",
            'workflow_state' => [
                'current_step' => 'waiting_confirmation',
                'workflow' => $workflow,
            ],
        ];
    }
    
    // Tạo câu hỏi thông minh
    $questions = [];
    foreach ($requiredFields as $field) {
        $question = $this->generateQuestion($field, $assistant);
        $questions[] = "- {$field['label']}: {$question}";
    }
    
    $response = "Tôi sẽ giúp bạn tạo báo cáo. Để tạo báo cáo phù hợp, tôi cần một số thông tin:\n\n";
    $response .= "📋 Thông tin cần thiết:\n";
    $response .= implode("\n", $questions);
    $response .= "\n\nBạn có thể cung cấp thông tin này không?";
    
    return [
        'response' => $response,
        'workflow_state' => [
            'current_step' => 'collecting_info',
            'workflow' => $workflow,
            'required_fields' => $requiredFields,
        ],
    ];
}
```

### 4. Cải Thiện Logic Generate Report

```php
// ✅ CHỈ generate report khi:
// 1. Có đủ collectedData HOẶC
// 2. User request cụ thể và AI có thể generate content HOẶC
// 3. User xác nhận tạo báo cáo mẫu

if ($allCollected || $requestAnalysis['has_sufficient_info'] || $userConfirmed) {
    // Generate report...
} else {
    // Hỏi thêm thông tin...
}
```

## 📋 Implementation Plan

### Phase 1: Thêm Logic Phân Tích Request
- [ ] Thêm method `analyzeRequestCompleteness()`
- [ ] Thêm method `extractSpecificInfo()`
- [ ] Thêm method `getRequiredFields()`
- [ ] Thêm method `getMissingFields()`

### Phase 2: Cải Thiện Flow Thu Thập Thông Tin
- [ ] Thêm method `askForRequiredInfo()`
- [ ] Cải thiện method `generateQuestion()`
- [ ] Thêm logic xác nhận khi request chung chung

### Phase 3: Cải Thiện Logic Generate Report
- [ ] Chỉ generate khi có đủ thông tin
- [ ] Support generate với AI khi request cụ thể nhưng thiếu data
- [ ] Thêm confirmation step cho request chung chung

### Phase 4: Testing & Refinement
- [ ] Test với request chung chung
- [ ] Test với request cụ thể
- [ ] Test với request có một phần thông tin
- [ ] Refine prompts và questions

## 🎯 Kết Luận

**Flow hiện tại không ổn** vì:
- Tạo báo cáo quá sớm khi user request chung chung
- Không có quá trình thu thập thông tin
- Báo cáo rỗng không có giá trị

**Flow mong muốn**:
- Phân tích request trước khi generate
- Hỏi thông tin cần thiết khi request chung chung
- Chỉ generate khi có đủ thông tin hoặc user xác nhận
- AI generate content mới dựa trên thông tin đã thu thập






