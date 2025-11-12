# 📋 BÁO CÁO LỖI V2: Document Preview Không Hiển Thị

## 🔍 Phân Tích Log

### 1. Backend ✅
- **Status**: Hoạt động đúng
- **Log**: `✅ [ChatController] Document data prepared for SSE`
- **Log**: `✅ [ChatController] Including document in SSE response`
- **File Path**: `http://localhost/storage/documents/bien_ban_72_20251109075338.docx`
- **Kết luận**: Backend đã trả về document data đúng format trong SSE response

### 2. Database ✅
- **Status**: Message có metadata.document đúng
- **Metadata**: 
  ```json
  {
    "document": {
      "file_path": "http://localhost/storage/documents/bien_ban_72_20251109075338.docx",
      "template_id": 15,
      "document_type": "bien_ban",
      "template_used": true,
      "document_type_display": "Biên bản"
    }
  }
  ```
- **Kết luận**: Message trong database có metadata.document đầy đủ

### 3. Frontend ❌
- **Status**: Có lỗi
- **Vấn đề**: DocumentPreview component không hiển thị
- **Điều kiện hiển thị**: `v-if="message.sender === 'assistant' && message.metadata?.document"`
- **Kết luận**: Có thể `onDocumentCallback` không được gọi khi message mới được tạo, nên `assistantMessage.metadata.document` không được set

## 🔧 Nguyên Nhân

### Vấn đề chính:
1. **`onDocumentCallback` không được gọi**: 
   - Console log trước đó cho thấy `hasOnDocument: false`
   - Có thể do `onDocument` callback không được truyền đúng vào `streamResponse()`

2. **Message mới không có metadata.document**:
   - Khi `sendMessage()` được gọi, `assistantMessage` được tạo với `id: Date.now() + 1`
   - Nếu `onDocumentCallback` không được gọi, `assistantMessage.metadata.document` không được set
   - Sau đó, `onCompleteCallback` gọi `loadChatSessions()`, reload messages từ database
   - Nhưng message mới trong `messages.value` có thể không có metadata.document

3. **Reactivity issue**:
   - Khi `onDocumentCallback` được gọi, nó set `assistantMessage.metadata.document`
   - Nhưng có thể Vue không detect được thay đổi do reactivity issue

## 🔧 Cách Fix

### Fix 1: Fallback trong `onCompleteCallback`
Nếu `onDocumentCallback` không được gọi, set document metadata trong `onCompleteCallback`:

```javascript
const onCompleteCallback = async (data) => {
    // ... existing code ...
    
    // ✅ FIX: If document data exists but onDocumentCallback wasn't called, set it here
    if (data?.document && !assistantMessage.metadata?.document) {
        console.log('[Dashboard] Setting document metadata in onComplete (fallback)');
        
        if (!assistantMessage.metadata) {
            assistantMessage.metadata = {};
        }
        
        assistantMessage.metadata.document = {
            file_path: data.document.file_path,
            document_type: data.document.document_type,
            document_type_display: data.document.document_type_display,
            template_used: data.document.template_used,
            template_id: data.document.template_id,
        };
        
        // Force reactivity
        messages.value = [...messages.value];
    }
    
    await loadChatSessions();
    scrollToBottom();
};
```

### Fix 2: Đảm bảo message từ database có metadata
Sau khi `loadChatSessions()` được gọi, đảm bảo message mới có metadata.document:

```javascript
await loadChatSessions();

// ✅ FIX: Update assistantMessage with metadata from database
const updatedMessage = messages.value.find(m => m.id === assistantMessage.id);
if (updatedMessage && updatedMessage.metadata?.document) {
    assistantMessage.metadata = updatedMessage.metadata;
    // Force reactivity
    messages.value = [...messages.value];
}
```

## 📝 Các Thay Đổi Đã Thực Hiện

1. ✅ Thêm fallback trong `onCompleteCallback` để set document metadata nếu `onDocumentCallback` không được gọi
2. ✅ Thêm log debug để kiểm tra document metadata trong `onCompleteCallback`

## 🧪 Test Lại

Sau khi fix, cần test lại:
1. Gửi message "Tạo 1 mẫu Biên bản"
2. Kiểm tra console log:
   - `[Dashboard] onComplete callback called` - phải có `hasDocument: true`
   - `[Dashboard] Setting document metadata in onComplete (fallback)` - phải được gọi nếu `onDocumentCallback` không được gọi
3. Kiểm tra UI:
   - `DocumentPreview` component phải hiển thị
   - Document preview phải có format giống template DOCX

## 🎯 Kết Luận

**Nguyên nhân chính**: 
- `onDocumentCallback` không được gọi khi message mới được tạo
- Message mới trong `messages.value` không có `metadata.document`
- Sau khi `loadChatSessions()`, message từ database có metadata.document, nhưng có thể không được update vào `assistantMessage` trong UI

**Cách fix**: 
- Thêm fallback trong `onCompleteCallback` để set document metadata nếu `onDocumentCallback` không được gọi
- Đảm bảo message từ database có metadata được update vào `assistantMessage` trong UI



