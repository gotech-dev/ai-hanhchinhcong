# BÁO CÁO VẤN ĐỀ: Chatbot "Trợ lý học tiếng Anh PolyPi" không sử dụng tài liệu đã tải lên

## 🔴 VẤN ĐỀ

Chatbot "Trợ lý học tiếng Anh PolyPi" thuộc loại `qa_based_document` nhưng không sử dụng dữ liệu từ tài liệu đã tải lên ở admin để trả lời. Thay vào đó, chatbot tự ý trả lời dựa trên kiến thức chung của AI.

## 🔍 NGUYÊN NHÂN

### 1. Logic xử lý trong `handleAskQuestion()` (SmartAssistantEngine.php)

**Vị trí:** `app/Services/SmartAssistantEngine.php`, dòng 752-906

**Vấn đề:**
- Khi có tài liệu đã index, code gọi `searchSimilar()` với threshold 0.7 (dòng 762-768)
- Nếu `searchSimilar()` trả về mảng rỗng (không có chunk nào có similarity >= 0.7), code sẽ **fallback về `handleGenericRequest()`** (dòng 895)
- `handleGenericRequest()` **KHÔNG sử dụng tài liệu** - chỉ gọi OpenAI với messages thông thường
- Điều này khiến chatbot trả lời dựa trên kiến thức chung thay vì tài liệu đã tải lên

**Code hiện tại:**
```php
if ($documentsCount > 0) {
    $searchResults = $this->vectorSearchService->searchSimilar(
        $userMessage,
        $assistant->id,
        5,
        0.7,  // Threshold quá cao
        []
    );
    
    if (!empty($searchResults)) {
        // Sử dụng tài liệu
    }
}

// ❌ VẤN ĐỀ: Nếu searchResults rỗng, fallback về generic
return $this->handleGenericRequest($userMessage, $session, $assistant, $intent, $streamCallback);
```

### 2. Threshold similarity quá cao

- Threshold mặc định là **0.7** có thể quá cao
- Nếu câu hỏi của user không khớp chính xác với nội dung trong tài liệu (similarity < 0.7), sẽ không có kết quả
- Code không thử lại với threshold thấp hơn

### 3. Không có cơ chế fallback thông minh

- Khi không tìm thấy kết quả với threshold 0.7, code không:
  - Thử lại với threshold thấp hơn (0.5, 0.3)
  - Sử dụng top results ngay cả khi similarity thấp
  - Thông báo cho user rằng không tìm thấy trong tài liệu nhưng vẫn cố gắng trả lời dựa trên tài liệu

## ✅ GIẢI PHÁP

### 1. Thử lại với threshold thấp hơn khi không có kết quả

**Sửa trong:** `app/Services/SmartAssistantEngine.php`, method `handleAskQuestion()`

**Thay đổi:**
- Khi `searchSimilar()` với threshold 0.7 trả về rỗng, thử lại với 0.5
- Nếu vẫn rỗng, thử với 0.3
- Chỉ fallback về generic nếu không có tài liệu nào được index

### 2. Luôn ưu tiên sử dụng tài liệu khi có

**Nguyên tắc:**
- Nếu assistant có tài liệu đã index, **LUÔN** cố gắng sử dụng tài liệu
- Chỉ fallback về generic khi:
  - Không có tài liệu nào được index
  - Hoặc tất cả tài liệu đều không liên quan (similarity < 0.3)

### 3. Cải thiện logging để debug

- Log số lượng documents
- Log số lượng chunks
- Log kết quả searchSimilar với các threshold khác nhau
- Log lý do fallback về generic

## 📝 CÁCH FIX

### Bước 1: Sửa method `handleAskQuestion()` trong SmartAssistantEngine.php

Thay đổi logic để:
1. Thử search với threshold 0.7 trước
2. Nếu không có kết quả, thử 0.5
3. Nếu vẫn không có, thử 0.3
4. Nếu có kết quả (dù threshold nào), sử dụng để trả lời
5. Chỉ fallback về generic khi không có documents hoặc similarity quá thấp (< 0.3)

### Bước 2: Thêm logging chi tiết

Log các thông tin:
- Số lượng documents indexed
- Số lượng chunks
- Kết quả search với từng threshold
- Lý do fallback (nếu có)

### Bước 3: Kiểm tra documents có được index đúng không

- Verify documents có status = 'indexed'
- Verify chunks có embedding không null
- Verify embeddings được tạo đúng

## 🔧 CODE FIX CHI TIẾT

### Đã sửa trong: `app/Services/SmartAssistantEngine.php`

**Thay đổi chính:**

1. **Thử nhiều threshold khi tìm kiếm documents** (dòng 760-835):
   - Trước: Chỉ thử với threshold 0.7, nếu không có kết quả thì bỏ qua
   - Sau: Thử lần lượt với thresholds [0.7, 0.5, 0.3] cho đến khi tìm thấy kết quả
   - Đảm bảo luôn sử dụng documents nếu có kết quả phù hợp (dù similarity thấp)

2. **Cải thiện logging**:
   - Log threshold được sử dụng
   - Log min/max similarity của kết quả
   - Log chi tiết khi không tìm thấy kết quả (số documents, số chunks)
   - Phân biệt log giữa "không có documents" và "có documents nhưng không tìm thấy kết quả"

3. **Logic fallback rõ ràng hơn**:
   - Chỉ fallback về generic khi:
     - Không có documents VÀ không có reference URLs
     - Hoặc có documents nhưng không tìm thấy kết quả nào (ngay cả với threshold 0.3)

### Code thay đổi:

```php
// TRƯỚC (dòng 760-789):
if ($documentsCount > 0) {
    $searchResults = $this->vectorSearchService->searchSimilar(
        $userMessage, $assistant->id, 5, 0.7, []
    );
    // Filter và sử dụng nếu có kết quả
    // Nếu không có → bỏ qua, tiếp tục check reference URLs
}

// SAU (dòng 760-835):
if ($documentsCount > 0) {
    $searchResults = null;
    $thresholds = [0.7, 0.5, 0.3];
    
    foreach ($thresholds as $threshold) {
        $tempResults = $this->vectorSearchService->searchSimilar(
            $userMessage, $assistant->id, 5, $threshold, []
        );
        // Filter...
        if (!empty($tempResults)) {
            $searchResults = $tempResults;
            break; // Dừng khi tìm thấy
        }
    }
    
    if (!empty($searchResults)) {
        // Sử dụng documents để trả lời
    } else {
        // Log chi tiết để debug
    }
}
```

## ✅ KẾT QUẢ SAU KHI FIX

1. **Chatbot sẽ luôn ưu tiên sử dụng tài liệu** khi có documents đã index
2. **Tìm kiếm linh hoạt hơn** với nhiều threshold, tăng khả năng tìm thấy kết quả phù hợp
3. **Logging chi tiết** giúp debug dễ dàng hơn khi có vấn đề
4. **Fallback rõ ràng** chỉ khi thực sự không có tài liệu hoặc không tìm thấy kết quả phù hợp

## 🧪 KIỂM TRA SAU KHI FIX

1. **Kiểm tra documents có được index đúng không:**
   ```sql
   SELECT COUNT(*) FROM assistant_documents 
   WHERE ai_assistant_id = [ID] AND status = 'indexed';
   
   SELECT COUNT(*) FROM document_chunks dc
   JOIN assistant_documents ad ON dc.assistant_document_id = ad.id
   WHERE ad.ai_assistant_id = [ID] AND dc.embedding IS NOT NULL;
   ```

2. **Test với câu hỏi trong tài liệu:**
   - Đặt câu hỏi về nội dung có trong tài liệu đã tải lên
   - Kiểm tra log để xem threshold nào được sử dụng
   - Verify response có sử dụng nội dung từ tài liệu

3. **Test với câu hỏi ngoài tài liệu:**
   - Đặt câu hỏi không liên quan đến tài liệu
   - Verify chatbot vẫn trả lời (fallback về generic) nhưng có log warning

## 🔴 PHÁT HIỆN THÊM: Vấn đề với status của documents

### Vấn đề thực tế khi kiểm tra:

Khi kiểm tra trực tiếp assistant "Trợ lý học tiếng Anh PolyPi", phát hiện:

1. **Document có status = 'error'** thay vì 'indexed'
2. **Document có is_indexed = true** và có 5 chunks với embeddings
3. **Code chỉ check `status = 'indexed'`** → Không tìm thấy documents
4. **Khi test search trực tiếp** (không filter theo status) → Tìm thấy 5 kết quả với similarity cao (0.826, 0.819, etc.)

### Nguyên nhân:

- Code trong `handleAskQuestion()` chỉ check `status = 'indexed'`
- Nhưng document có thể có `status = 'error'` mặc dù đã được index thành công (`is_indexed = true` và có embeddings)
- Điều này xảy ra khi quá trình index gặp lỗi nhỏ nhưng vẫn tạo được embeddings

### Fix đã áp dụng:

1. **Sửa logic check documents** trong `SmartAssistantEngine.php`:
   ```php
   // TRƯỚC:
   ->where('status', 'indexed')
   
   // SAU:
   ->where(function($q) {
       $q->where('status', 'indexed')
         ->orWhere('is_indexed', true);
   })
   ->whereHas('documentChunks', function($q) {
       $q->whereNotNull('embedding');
   })
   ```

2. **Fix status của document** từ 'error' thành 'indexed' bằng command:
   ```bash
   php artisan fix:polypi-status
   ```

### Kết quả sau khi fix:

- ✅ Code sẽ tìm thấy documents ngay cả khi status = 'error' nhưng is_indexed = true
- ✅ Document PolyPi đã được cập nhật status = 'indexed'
- ✅ Chatbot sẽ sử dụng tài liệu để trả lời thay vì fallback về generic

## 📝 LƯU Ý

- Threshold 0.3 là khá thấp, có thể trả về kết quả không liên quan
- Nếu vẫn không tìm thấy kết quả với threshold 0.3, có thể:
  - Documents chưa được index đúng
  - Embeddings không được tạo đúng
  - Nội dung câu hỏi quá khác biệt với tài liệu
- Cần kiểm tra logs để xác định nguyên nhân cụ thể
- **QUAN TRỌNG**: Luôn check cả `is_indexed = true` và `status = 'indexed'` để tránh bỏ sót documents đã được index nhưng có status lỗi

## 🔴 PHÁT HIỆN THÊM 2: System prompt không phù hợp

### Vấn đề:

Khi test với câu hỏi "IELTS có những chức năng gì":
- ✅ Search tìm thấy kết quả với similarity cao (0.844, 0.843, etc.)
- ✅ Nội dung chunks có đầy đủ thông tin về chức năng IELTS
- ❌ Nhưng chatbot trả lời "Tài liệu tham khảo không đề cập đến chức năng cụ thể của IELTS"

### Nguyên nhân:

System prompt trong `generateAnswerFromContext()` quá tập trung vào:
- "Luật Đất đai" (không phù hợp với chatbot tiếng Anh)
- Các năm cụ thể (2013, 2024, 2025)
- Không nhấn mạnh việc đọc kỹ và trả lời đầy đủ từ tài liệu

### Fix đã áp dụng:

**Sửa system prompt trong `SmartAssistantEngine.php`, method `generateAnswerFromContext()`:**

1. **Loại bỏ các tham chiếu cụ thể về "Luật Đất đai"**
2. **Nhấn mạnh việc đọc kỹ tài liệu:**
   - "Bạn PHẢI đọc kỹ toàn bộ tài liệu tham khảo trước khi trả lời"
   - "Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI sử dụng thông tin đó"
3. **Yêu cầu trả lời đầy đủ:**
   - "Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI trả lời đầy đủ dựa trên tài liệu"
   - "KHÔNG được nói 'tài liệu không đề cập' nếu thông tin thực sự có trong tài liệu"
4. **Yêu cầu đọc lại trước khi từ chối:**
   - "Chỉ nói 'tài liệu không đề cập' khi bạn đã đọc kỹ và CHẮC CHẮN rằng tài liệu không có thông tin"

### Code thay đổi:

```php
// TRƯỚC: System prompt quá cụ thể về "Luật Đất đai"
$systemPrompt .= "2. **CẤM SỬ DỤNG KIẾN THỨC CŨ:** Nếu tài liệu đề cập đến \"Luật Đất đai 2025\"...";

// SAU: System prompt tổng quát và nhấn mạnh đọc kỹ
$systemPrompt .= "2. **ĐỌC KỸ TÀI LIỆU:** Bạn PHẢI đọc kỹ toàn bộ tài liệu tham khảo trước khi trả lời. Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI sử dụng thông tin đó.\n\n";
$systemPrompt .= "4. **TRẢ LỜI ĐẦY ĐỦ:** Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI trả lời đầy đủ dựa trên tài liệu. KHÔNG được nói \"tài liệu không đề cập\" nếu thông tin thực sự có trong tài liệu.\n\n";
```

### Kết quả sau khi fix:

- ✅ System prompt tổng quát, phù hợp với mọi loại tài liệu
- ✅ Nhấn mạnh việc đọc kỹ và trả lời đầy đủ từ tài liệu
- ✅ AI sẽ không từ chối trả lời khi thông tin có trong tài liệu

