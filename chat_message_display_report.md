# Báo Cáo: Vấn Đề Hiển Thị Tin Nhắn Lộn Xộn và Biến Mất

## Mô Tả Vấn Đề

Trên màn hình `/chat`, tin nhắn hiển thị **lộn xộn** và **biến mất** sau khi hiển thị. Người dùng không thể theo dõi cuộc trò chuyện một cách bình thường.

## Nguyên Nhân

Sau khi kiểm tra code trong `resources/js/Pages/Chat/IndexNew.vue`, tôi đã xác định được **4 nguyên nhân chính**:

### 1. **Hàm `loadMessages()` Thay Thế Toàn Bộ Mảng Messages**

**Vị trí:** Line 270-279 trong `IndexNew.vue`

```javascript
const loadMessages = async () => {
    if (!currentSession.value) return;
    
    try {
        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/history`);
        messages.value = response.data.messages || [];  // ❌ THAY THẾ TOÀN BỘ MẢNG
    } catch (error) {
        console.error('Error loading messages:', error);
    }
};
```

**Vấn đề:**
- Hàm này được gọi sau khi stream hoàn thành (line 242) và sau khi gửi message không streaming (line 259)
- Nó **thay thế hoàn toàn** mảng `messages.value` bằng dữ liệu từ server
- Điều này có thể **xóa các messages tạm thời** đã được push vào mảng trước đó
- Nếu server trả về messages không đúng thứ tự hoặc thiếu messages mới nhất, sẽ gây ra hiển thị lộn xộn

### 2. **Race Condition Giữa Stream và LoadMessages**

**Vị trí:** Line 239-243 trong `IndexNew.vue`

```javascript
async () => {
    isLoading.value = false;
    // Reload messages to get the saved ones
    await loadMessages();  // ❌ ĐƯỢC GỌI NGAY SAU KHI STREAM HOÀN THÀNH
    scrollToBottom();
},
```

**Vấn đề:**
- Khi stream hoàn thành, `onComplete` callback được gọi
- Ngay lập tức gọi `loadMessages()` để reload toàn bộ messages từ server
- Nếu message đang được stream vẫn chưa được lưu vào database hoặc server chưa cập nhật, `loadMessages()` sẽ trả về dữ liệu cũ
- Điều này có thể **xóa message đang được stream** khỏi UI

### 3. **Message ID Không Đảm Bảo Tính Duy Nhất**

**Vị trí:** Line 209-226 trong `IndexNew.vue`

```javascript
messages.value.push({
    id: Date.now(),  // ❌ CÓ THỂ TRÙNG NẾU NHIỀU MESSAGES TRONG CÙNG MILLISECOND
    sender: 'user',
    content: message,
    created_at: new Date().toISOString(),
});

// Create placeholder for assistant response
const assistantMessageId = Date.now() + 1;  // ❌ CÓ THỂ TRÙNG
let assistantMessage = {
    id: assistantMessageId,
    sender: 'assistant',
    content: '',
    created_at: new Date().toISOString(),
};
```

**Vấn đề:**
- Sử dụng `Date.now()` và `Date.now() + 1` làm ID có thể gây trùng lặp nếu nhiều messages được tạo trong cùng millisecond
- Vue có thể không track đúng các messages khi key bị trùng, dẫn đến hiển thị lộn xộn

### 4. **Thiếu Logic Merge Messages**

**Vấn đề:**
- Khi `loadMessages()` được gọi, nó thay thế toàn bộ mảng thay vì merge với messages hiện tại
- Messages đang được stream (chưa lưu vào DB) có thể bị mất
- Messages từ server có thể không có những messages tạm thời đã được push trước đó

## Cách Sửa

### Giải Pháp 1: Không Reload Messages Sau Stream (Khuyến Nghị)

**Thay đổi:** Không gọi `loadMessages()` sau khi stream hoàn thành, vì message đã được stream và hiển thị đầy đủ.

```javascript
async () => {
    isLoading.value = false;
    // ❌ XÓA DÒNG NÀY: await loadMessages();
    // Message đã được stream và hiển thị đầy đủ, không cần reload
    scrollToBottom();
},
```

**Lý do:**
- Message đã được stream và cập nhật vào `assistantMessage.content` trong quá trình stream
- Server đã lưu message vào database trong quá trình stream
- Reload chỉ cần thiết nếu có vấn đề với data integrity

### Giải Pháp 2: Merge Messages Thay Vì Thay Thế

**Thay đổi:** Sửa hàm `loadMessages()` để merge messages thay vì thay thế hoàn toàn.

```javascript
const loadMessages = async () => {
    if (!currentSession.value) return;
    
    try {
        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/history`);
        const serverMessages = response.data.messages || [];
        
        // Merge với messages hiện tại, ưu tiên messages từ server
        const existingIds = new Set(messages.value.map(m => m.id));
        const newMessages = serverMessages.filter(m => !existingIds.has(m.id));
        
        // Thêm messages mới vào cuối
        messages.value.push(...newMessages);
        
        // Sắp xếp lại theo created_at
        messages.value.sort((a, b) => {
            return new Date(a.created_at) - new Date(b.created_at);
        });
    } catch (error) {
        console.error('Error loading messages:', error);
    }
};
```

### Giải Pháp 3: Sử dụng UUID Cho Message ID

**Thay đổi:** Sử dụng UUID hoặc timestamp + random để đảm bảo tính duy nhất.

```javascript
// Thay thế Date.now() bằng:
const generateMessageId = () => {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
};

messages.value.push({
    id: generateMessageId(),  // ✅ ID DUY NHẤT
    sender: 'user',
    content: message,
    created_at: new Date().toISOString(),
});
```

### Giải Pháp 4: Chỉ Reload Khi Cần Thiết

**Thay đổi:** Chỉ reload messages khi có lỗi hoặc khi user yêu cầu refresh, không reload tự động sau mỗi message.

```javascript
// Chỉ reload khi cần thiết, không reload sau mỗi message
// Ví dụ: Chỉ reload khi user click "Refresh" hoặc khi có lỗi
```

## Giải Pháp Tổng Hợp (Áp Dụng Tất Cả)

### 1. Sửa `handleSend()` - Không Reload Sau Stream

```javascript
if (useStreaming.value) {
    let fullContent = '';
    
    streamResponse(
        currentSession.value.id,
        message,
        (chunk) => {
            fullContent += chunk;
            assistantMessage.content = fullContent;
            scrollToBottom();
        },
        async () => {
            isLoading.value = false;
            // ✅ KHÔNG RELOAD - Message đã được stream đầy đủ
            // Chỉ cần cập nhật ID từ server nếu cần
            scrollToBottom();
        },
        (error) => {
            isLoading.value = false;
            assistantMessage.content = error || 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.';
            scrollToBottom();
        }
    );
}
```

### 2. Sửa `loadMessages()` - Merge Thay Vì Thay Thế

```javascript
const loadMessages = async () => {
    if (!currentSession.value) return;
    
    try {
        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/history`);
        const serverMessages = response.data.messages || [];
        
        // Tạo map để track messages theo ID
        const messageMap = new Map();
        
        // Thêm messages hiện tại (ưu tiên messages đang stream)
        messages.value.forEach(msg => {
            if (msg.id && !messageMap.has(msg.id)) {
                messageMap.set(msg.id, msg);
            }
        });
        
        // Merge với messages từ server
        serverMessages.forEach(msg => {
            if (!messageMap.has(msg.id)) {
                messageMap.set(msg.id, msg);
            }
        });
        
        // Chuyển về array và sắp xếp theo created_at
        messages.value = Array.from(messageMap.values()).sort((a, b) => {
            const timeA = new Date(a.created_at || 0).getTime();
            const timeB = new Date(b.created_at || 0).getTime();
            return timeA - timeB;
        });
    } catch (error) {
        console.error('Error loading messages:', error);
    }
};
```

### 3. Sửa Message ID Generation

```javascript
// Helper function để generate unique ID
const generateMessageId = () => {
    return `msg-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
};

// Trong handleSend:
messages.value.push({
    id: generateMessageId(),
    sender: 'user',
    content: message,
    created_at: new Date().toISOString(),
});

const assistantMessageId = generateMessageId();
let assistantMessage = {
    id: assistantMessageId,
    sender: 'assistant',
    content: '',
    created_at: new Date().toISOString(),
};
```

## Tóm Tắt

**Nguyên nhân chính:**
1. ✅ `loadMessages()` thay thế toàn bộ mảng messages thay vì merge
2. ✅ Race condition giữa stream và reload
3. ✅ Message ID có thể trùng lặp
4. ✅ Thiếu logic merge messages

**Giải pháp:**
1. ✅ Không reload messages sau stream (message đã được stream đầy đủ)
2. ✅ Merge messages thay vì thay thế khi cần reload
3. ✅ Sử dụng ID duy nhất cho messages
4. ✅ Chỉ reload khi thực sự cần thiết

## Ưu Tiên Sửa

1. **Cao nhất:** Xóa `await loadMessages()` trong `onComplete` callback của stream
2. **Cao:** Sửa `loadMessages()` để merge thay vì thay thế
3. **Trung bình:** Sử dụng ID duy nhất cho messages
4. **Thấp:** Chỉ reload khi cần thiết (không reload tự động)








