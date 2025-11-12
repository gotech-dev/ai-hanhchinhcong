# Cải tiến Chatbot - Tham khảo ChatGPT

## Danh sách cải tiến

### ✅ Đã hoàn thành
- [x] Tạo danh sách cải tiến
- [x] Thêm thumbnail/preview cho lịch sử chat bên trái (avatar assistant với gradient, preview message)
- [x] Thêm chức năng attach file vào input
- [x] Hỗ trợ upload nhiều loại file (PDF, DOCX, images, Excel, etc.)
- [x] Hiển thị preview file đã attach trước khi gửi
- [x] Xử lý file upload ở backend (API endpoint `/api/chat/sessions/{sessionId}/upload`)
- [x] Lưu file metadata vào message (metadata field trong chat_messages)
- [x] Hiển thị file attachments trong message history
- [x] Cải thiện UI/UX của sidebar (hover effects, delete button on hover)
- [x] Cập nhật cả Dashboard.vue và IndexNew.vue với chức năng attach file

### 🔄 Đang làm
- Không có

### 📋 Cần làm trong tương lai

#### 1. Thumbnail lịch sử chat bên trái (cải tiến thêm)
- [ ] Thêm collapse/expand sidebar
- [ ] Thêm search trong lịch sử chat
- [ ] Thêm filter/sort options

#### 2. Attach file (cải tiến thêm)
- [ ] Cho phép download/delete file attachments từ UI
- [ ] Xử lý file extraction và context cho AI (OCR, text extraction từ PDF/DOCX)
- [ ] Preview file (images, PDF viewer)
- [ ] Drag & drop file vào input area
- [ ] Upload progress indicator

#### 3. Các cải tiến khác
- [ ] Copy message
- [ ] Edit message
- [ ] Regenerate response
- [ ] Search trong chat history
- [ ] Pin important chats
- [ ] Export chat history
- [ ] Share chat
- [ ] Voice input/output
- [ ] Code syntax highlighting
- [ ] Better markdown rendering
- [ ] LaTeX support

