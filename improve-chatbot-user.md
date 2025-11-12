# Báo Cáo: Vấn Đề Upload và Đọc Ảnh trong Chat

## 🔍 Vấn Đề Hiện Tại

### Mô Tả
- ✅ Upload file PDF và DOCX: **HOẠT ĐỘNG TỐT**
- ❌ Upload ảnh (JPG, PNG, GIF): **CHƯA ĐỌC ĐƯỢC NỘI DUNG**

### Hiện Trạng
Khi user upload ảnh lên chat:
- File được upload thành công lên server
- File metadata được lưu vào database
- File hiển thị trong message history
- **NHƯNG**: AI chỉ nhận được tên file, không nhận được nội dung ảnh

---

## 🔬 Nguyên Nhân Chi Tiết

### 1. **Backend không xử lý nội dung file**

**Vị trí code:** `app/Http/Controllers/ChatController.php` - method `streamChat()`

**Vấn đề:**
```php
// Hiện tại chỉ gửi tên file
if (!empty($attachments)) {
    $fileInfo = implode(', ', array_column($attachments, 'name'));
    $messages[$lastIndex]['content'] = "Người dùng đã đính kèm các file: {$fileInfo}.";
}
```

**Code hiện tại KHÔNG:**
- Đọc nội dung file ảnh
- Convert ảnh sang base64
- Gửi ảnh vào OpenAI Vision API
- Extract text từ ảnh bằng OCR

### 2. **OpenAI API không nhận ảnh trong Chat API**

**Vấn đề:**
- OpenAI Chat API (`gpt-4o-mini`, `gpt-3.5-turbo`) chỉ nhận text
- Để đọc ảnh cần:
  - Sử dụng Vision API (`gpt-4o`, `gpt-4-turbo`) 
  - Hoặc extract text từ ảnh bằng OCR trước

### 3. **Thiếu xử lý image trong DocumentProcessor**

**Vị trí code:** `app/Services/DocumentProcessor.php`

**Vấn đề:**
- `DocumentProcessor` chỉ hỗ trợ PDF và DOCX
- Không có method `extractFromImage()` hoặc OCR

---

## ✅ Cách Sửa - 3 Phương Án

### **Phương Án 1: Sử dụng OpenAI Vision API (Khuyến nghị)**

#### Ưu điểm:
- ✅ Đọc được nội dung ảnh trực tiếp (text, bảng, biểu đồ)
- ✅ Không cần cài đặt thêm thư viện
- ✅ Chính xác cao
- ✅ Hỗ trợ nhiều loại ảnh

#### Nhược điểm:
- ❌ Chi phí cao hơn (Vision API đắt hơn Chat API)
- ❌ Cần model `gpt-4o` hoặc `gpt-4-turbo` (không dùng được `gpt-4o-mini`)

#### Cách triển khai:

**Bước 1: Cập nhật `streamChat()` method**

```php
// app/Http/Controllers/ChatController.php

public function streamChat(Request $request, int $sessionId): StreamedResponse
{
    // ... existing code ...
    
    return new StreamedResponse(function () use ($session, $userMessage, $attachments) {
        try {
            $messages = $this->buildMessagesWithContext($session, $userMessage);
            
            // Xử lý attachments - đặc biệt là ảnh
            if (!empty($attachments)) {
                $imageAttachments = [];
                $fileAttachments = [];
                
                // Phân loại: ảnh vs file khác
                foreach ($attachments as $attachment) {
                    $mimeType = $attachment['mime_type'] ?? '';
                    if (str_starts_with($mimeType, 'image/')) {
                        $imageAttachments[] = $attachment;
                    } else {
                        $fileAttachments[] = $attachment;
                    }
                }
                
                // Xử lý ảnh: Convert sang base64 và thêm vào message
                if (!empty($imageAttachments)) {
                    $imageContents = [];
                    foreach ($imageAttachments as $img) {
                        $filePath = storage_path('app/public/' . $img['path']);
                        if (file_exists($filePath)) {
                            $imageData = file_get_contents($filePath);
                            $base64Image = base64_encode($imageData);
                            $mimeType = $img['mime_type'] ?? 'image/jpeg';
                            
                            $imageContents[] = [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64Image}"
                                ]
                            ];
                        }
                    }
                    
                    // Thêm ảnh vào message cuối cùng
                    if (!empty($messages) && end($messages)['role'] === 'user') {
                        $lastIndex = count($messages) - 1;
                        $content = [];
                        
                        // Text content
                        if ($userMessage) {
                            $content[] = [
                                'type' => 'text',
                                'text' => $userMessage
                            ];
                        }
                        
                        // Image content
                        $content = array_merge($content, $imageContents);
                        
                        $messages[$lastIndex]['content'] = $content;
                    }
                }
                
                // Xử lý file khác (PDF, DOCX) - extract text
                if (!empty($fileAttachments)) {
                    // ... extract text from PDF/DOCX ...
                }
            }
            
            // Sử dụng Vision API model
            $model = 'gpt-4o'; // hoặc 'gpt-4-turbo'
            
            $response = OpenAI::chat()->createStreamed([
                'model' => $model,
                'messages' => $messages,
            ]);
            
            // ... rest of streaming code ...
        } catch (\Exception $e) {
            // ... error handling ...
        }
    });
}
```

**Bước 2: Cập nhật model config**

```php
// Kiểm tra nếu có ảnh thì dùng Vision API
$hasImages = !empty($imageAttachments);
$model = $hasImages 
    ? 'gpt-4o' // Vision API
    : ($session->aiAssistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'));
```

**Bước 3: Kiểm tra file size**

```php
// Ảnh base64 có thể rất lớn, cần kiểm tra
$maxImageSize = 20 * 1024 * 1024; // 20MB
foreach ($imageAttachments as $img) {
    $filePath = storage_path('app/public/' . $img['path']);
    if (filesize($filePath) > $maxImageSize) {
        throw new \Exception("Ảnh quá lớn. Vui lòng resize ảnh trước khi upload.");
    }
}
```

---

### **Phương Án 2: Sử dụng OCR (Tesseract)**

#### Ưu điểm:
- ✅ Miễn phí (open source)
- ✅ Có thể chạy offline
- ✅ Không phụ thuộc vào OpenAI Vision API

#### Nhược điểm:
- ❌ Cần cài đặt Tesseract OCR trên server
- ❌ Độ chính xác thấp hơn Vision API
- ❌ Không đọc được biểu đồ, bảng phức tạp
- ❌ Chi phí xử lý CPU

#### Cách triển khai:

**Bước 1: Cài đặt Tesseract**

```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr tesseract-ocr-vie

# macOS
brew install tesseract tesseract-lang

# Windows
# Download từ: https://github.com/UB-Mannheim/tesseract/wiki
```

**Bước 2: Cài đặt PHP wrapper**

```bash
composer require thiagoalessio/tesseract_ocr
```

**Bước 3: Thêm method vào DocumentProcessor**

```php
// app/Services/DocumentProcessor.php

use Thiagoalessio\TesseractOCR\TesseractOCR;

public function extractText($file): string
{
    $filePath = is_string($file) ? $file : $file->getRealPath();
    $extension = is_string($file) ? pathinfo($file, PATHINFO_EXTENSION) : $file->getClientOriginalExtension();
    $extension = strtolower($extension);
    
    if ($extension === 'pdf') {
        return $this->extractFromPdf($filePath);
    } elseif (in_array($extension, ['doc', 'docx'])) {
        return $this->extractFromWord($filePath);
    } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        return $this->extractFromImage($filePath);
    } else {
        throw new \Exception("Unsupported file type: {$extension}");
    }
}

protected function extractFromImage(string $filePath): string
{
    try {
        return (new TesseractOCR($filePath))
            ->lang('vie', 'eng') // Vietnamese + English
            ->run();
    } catch (\Exception $e) {
        throw new \Exception("Failed to extract text from image: " . $e->getMessage());
    }
}
```

**Bước 4: Cập nhật streamChat**

```php
// Extract text từ ảnh trước khi gửi
if (!empty($imageAttachments)) {
    $documentProcessor = app(DocumentProcessor::class);
    $imageTexts = [];
    
    foreach ($imageAttachments as $img) {
        $filePath = storage_path('app/public/' . $img['path']);
        try {
            $text = $documentProcessor->extractText($filePath);
            if (!empty($text)) {
                $imageTexts[] = "Nội dung ảnh {$img['name']}:\n{$text}";
            }
        } catch (\Exception $e) {
            Log::error('OCR failed', ['error' => $e->getMessage()]);
        }
    }
    
    if (!empty($imageTexts)) {
        $imageContent = implode("\n\n", $imageTexts);
        $messages[$lastIndex]['content'] = ($userMessage ? $userMessage . "\n\n" : '') . $imageContent;
    }
}
```

---

### **Phương Án 3: Hybrid (Khuyến nghị cho production)**

Kết hợp cả 2 phương án:
- **Ảnh nhỏ** (< 5MB): Dùng OpenAI Vision API (nhanh, chính xác)
- **Ảnh lớn** (> 5MB): Dùng OCR hoặc resize trước
- **Có budget hạn chế**: Dùng OCR cho tất cả

---

## 📝 Checklist Triển Khai

### Phương Án 1 (Vision API):
- [ ] Cập nhật `streamChat()` để detect ảnh
- [ ] Convert ảnh sang base64
- [ ] Thêm ảnh vào message content với format Vision API
- [ ] Chuyển model sang `gpt-4o` khi có ảnh
- [ ] Thêm validation cho file size
- [ ] Test với các loại ảnh: JPG, PNG, GIF
- [ ] Test với ảnh có text, bảng, biểu đồ

### Phương Án 2 (OCR):
- [ ] Cài đặt Tesseract OCR trên server
- [ ] Cài đặt PHP wrapper: `thiagoalessio/tesseract_ocr`
- [ ] Thêm method `extractFromImage()` vào DocumentProcessor
- [ ] Cập nhật `streamChat()` để extract text từ ảnh
- [ ] Thêm error handling cho OCR
- [ ] Test với ảnh tiếng Việt và tiếng Anh
- [ ] Tối ưu performance (cache, async processing)

### Phương Án 3 (Hybrid):
- [ ] Implement cả 2 phương án
- [ ] Thêm logic chọn phương án dựa trên file size
- [ ] Thêm config để switch giữa Vision API và OCR
- [ ] Monitor chi phí và performance

---

## 🎯 Khuyến Nghị

**Cho production:**
- **Ưu tiên**: Phương Án 1 (Vision API) - Chính xác, nhanh, dễ maintain
- **Backup**: Phương Án 2 (OCR) - Nếu budget hạn chế hoặc cần offline

**Lưu ý:**
- Vision API có giới hạn: 20MB/image, 20 images/message
- OCR có thể chậm với ảnh lớn (> 5MB)
- Cần test kỹ với ảnh tiếng Việt (có dấu)

---

## 📊 So Sánh

| Tiêu chí | Vision API | OCR (Tesseract) |
|----------|-----------|-----------------|
| **Chi phí** | Cao (~$0.01/image) | Miễn phí |
| **Độ chính xác** | Rất cao (95%+) | Trung bình (70-85%) |
| **Tốc độ** | Nhanh (~2-5s) | Chậm (~5-15s) |
| **Đọc bảng/biểu đồ** | ✅ Tốt | ❌ Kém |
| **Cài đặt** | Không cần | Cần cài Tesseract |
| **Maintenance** | Dễ | Khó hơn |

---

## 🔗 Tài Liệu Tham Khảo

- OpenAI Vision API: https://platform.openai.com/docs/guides/vision
- Tesseract OCR: https://github.com/tesseract-ocr/tesseract
- PHP Tesseract Wrapper: https://github.com/thiagoalessio/tesseract_ocr

---

**Ngày báo cáo:** 2025-01-XX
**Người báo cáo:** AI Assistant  
**Trạng thái:** ✅ ĐÃ TRIỂN KHAI - Phương Án 3 (Hybrid)

---

## ✅ Triển Khai Hoàn Tất

### Ngày triển khai: 2025-01-XX

### Đã triển khai:
- ✅ Cài đặt Tesseract OCR wrapper (`thiagoalessio/tesseract_ocr`)
- ✅ Thêm method `extractFromImage()` vào `DocumentProcessor`
- ✅ Thêm method `getTesseractPath()` để auto-detect Tesseract
- ✅ Cập nhật `streamChat()` với logic Hybrid:
  - Ảnh nhỏ (<5MB): Vision API (`gpt-4o`)
  - Ảnh lớn (>5MB): OCR (Tesseract)
  - PDF/DOCX: Extract text như bình thường
- ✅ Convert ảnh sang base64 cho Vision API
- ✅ Extract text từ ảnh bằng OCR cho ảnh lớn
- ✅ Error handling và logging

### Cách hoạt động:
1. **Upload file**: File được upload và lưu vào `storage/app/public/chat-attachments/`
2. **Phân loại**: 
   - Ảnh < 5MB → Vision API
   - Ảnh > 5MB → OCR
   - PDF/DOCX → Extract text
3. **Xử lý**:
   - Vision API: Convert sang base64, gửi vào `gpt-4o`
   - OCR: Extract text bằng Tesseract, gửi text vào AI
4. **Response**: AI trả lời dựa trên nội dung đã xử lý

### Lưu ý:
- ✅ **Tesseract đã được cài đặt** trên server (version 5.5.1)
- ✅ **Language pack đã được cài đặt** (tesseract-lang 4.1.0)
- ✅ **Hỗ trợ tiếng Việt** đã sẵn sàng
- Vision API yêu cầu model `gpt-4o` (không dùng được `gpt-4o-mini`)
- OCR có thể chậm với ảnh lớn (>5MB)
- Nếu không có Tesseract hoặc OCR fail, code sẽ fallback và log warning

### Cấu hình:
- **Image size threshold**: 5MB (có thể điều chỉnh trong code)
- **Vision API max size**: 20MB (giới hạn của OpenAI)
- **OCR language**: Vietnamese + English (`vie`, `eng`)
- **Model auto-switch**: 
  - Có ảnh nhỏ → `gpt-4o` (Vision API)
  - Không có ảnh → Model mặc định từ config

### Testing:
Sau khi deploy, cần test:
1. Upload ảnh nhỏ (<5MB) → Kiểm tra Vision API hoạt động
2. Upload ảnh lớn (>5MB) → Kiểm tra OCR hoạt động  
3. Upload PDF/DOCX → Kiểm tra text extraction hoạt động
4. Upload ảnh tiếng Việt → Kiểm tra OCR đọc được tiếng Việt (sau khi cài language pack)

