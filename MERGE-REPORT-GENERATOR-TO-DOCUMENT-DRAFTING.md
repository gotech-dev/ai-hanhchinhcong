# ĐỀ XUẤT: Merge ReportGenerator vào DocumentDrafting

## 🎯 PHÂN TÍCH

### So sánh chức năng

**ReportGenerator (`report_generator`):**
- ✅ Upload 1 template
- ✅ User cung cấp data
- ✅ AI điền data vào template
- ✅ Giữ nguyên format template

**DocumentDrafting (sau khi cải thiện):**
- ✅ Upload **nhiều template** (cho các loại văn bản khác nhau)
- ✅ User yêu cầu → AI **soạn thảo nội dung**
- ✅ AI điền vào template
- ✅ Giữ nguyên format template

### Kết luận

**DocumentDrafting đã bao gồm ReportGenerator!**

- ReportGenerator: User cung cấp data → AI điền
- DocumentDrafting: User yêu cầu → AI soạn thảo + điền

**DocumentDrafting mạnh hơn vì:**
1. Có thể upload nhiều template (không chỉ 1)
2. AI tự soạn thảo nội dung (không cần user cung cấp data)
3. Có thể làm được tất cả những gì ReportGenerator làm

## ✅ ĐỀ XUẤT

### Option 1: Bỏ ReportGenerator (Recommended)

**Lý do:**
- DocumentDrafting đã bao gồm tất cả chức năng của ReportGenerator
- Tránh trùng lặp code
- Đơn giản hóa hệ thống
- User chỉ cần 1 loại assistant thay vì 2

**Cách làm:**
1. Cập nhật `DocumentDraftingService` để hỗ trợ cả 2 mode:
   - Mode 1: AI soạn thảo (mặc định)
   - Mode 2: User cung cấp data → AI chỉ điền (giống ReportGenerator)
2. Migration: Chuyển tất cả `report_generator` → `document_drafting`
3. Xóa code liên quan đến `report_generator`

### Option 2: Merge ReportGenerator vào DocumentDrafting

**Cách làm:**
1. Giữ `document_drafting` làm loại chính
2. Thêm config option: `mode` (soạn_thảo | điền_data)
3. Nếu `mode = điền_data` → Hoạt động giống ReportGenerator
4. Migration: Chuyển `report_generator` → `document_drafting` với `mode = điền_data`

## 📋 KẾ HOẠCH TRIỂN KHAI

### Phase 1: Cải thiện DocumentDrafting
- [ ] Cho phép upload nhiều template
- [ ] AI chọn template phù hợp
- [ ] Hỗ trợ cả 2 mode: soạn thảo và điền data

### Phase 2: Migration
- [ ] Tạo migration để chuyển `report_generator` → `document_drafting`
- [ ] Chuyển template files
- [ ] Cập nhật config

### Phase 3: Xóa ReportGenerator
- [ ] Xóa `REPORT_GENERATOR` từ `AssistantType` enum
- [ ] Xóa `ReportGenerator` service
- [ ] Xóa `ReportFileGenerator` service (hoặc merge vào DocumentDrafting)
- [ ] Xóa code liên quan trong `SmartAssistantEngine`
- [ ] Xóa code liên quan trong `ChatController`
- [ ] Cập nhật frontend (xóa option `report_generator`)

### Phase 4: Testing
- [ ] Test DocumentDrafting với mode soạn thảo
- [ ] Test DocumentDrafting với mode điền data
- [ ] Test migration từ report_generator
- [ ] Verify không có breaking changes

## 🎯 KẾT QUẢ MONG ĐỢI

Sau khi triển khai:
- ✅ Chỉ còn 1 loại assistant: `document_drafting`
- ✅ Hỗ trợ cả 2 mode: soạn thảo và điền data
- ✅ Upload nhiều template cho các loại văn bản khác nhau
- ✅ Code đơn giản hơn, không trùng lặp
- ✅ User dễ sử dụng hơn (chỉ 1 loại thay vì 2)

## ⚠️ LƯU Ý

1. **Backward compatibility:**
   - Cần migration để chuyển dữ liệu hiện tại
   - Cần test kỹ để đảm bảo không mất dữ liệu

2. **Existing assistants:**
   - Assistants hiện tại dùng `report_generator` cần được migrate
   - Có thể tự động migrate hoặc yêu cầu admin migrate thủ công

3. **Documentation:**
   - Cập nhật tài liệu
   - Hướng dẫn user cách sử dụng DocumentDrafting với 2 mode



