# VẤN ĐỀ: DocumentDraftingService Thiếu Template Files

## 🔍 PHÂN TÍCH VẤN ĐỀ

### Hiện trạng

**ReportGenerator (`report_generator`):**
- ✅ Admin upload **1 template file** (DOCX/PDF)
- ✅ Template có placeholders như `{{field_name}}`
- ✅ AI điền data vào placeholders
- ✅ **Giữ nguyên format** của template gốc
- ✅ Format đúng với format thực tế của cơ quan

**DocumentDraftingService (`document_drafting`):**
- ❌ **KHÔNG có upload template**
- ❌ Chỉ dùng cấu trúc hardcode trong `DocumentType::getTemplateStructure()`
- ❌ Tạo DOCX từ đầu bằng PhpWord (code)
- ❌ Format được tạo bằng code, **không dùng template thực tế**
- ❌ Format có thể không đúng với format thực tế của cơ quan

### Vấn đề cụ thể

1. **Không có template cho các loại văn bản cụ thể:**
   - Quyết định bổ nhiệm
   - Quyết định khen thưởng
   - Quyết định kỷ luật
   - Công văn đi
   - Công văn đến
   - v.v.

2. **AI tạo format "linh tinh":**
   - Không có template mẫu → AI tự tạo format
   - Format có thể không đúng với format thực tế của cơ quan
   - Mỗi cơ quan có format riêng (logo, header, footer, font, spacing, v.v.)

3. **Khác biệt với ReportGenerator:**
   - ReportGenerator: Dùng template file → format đúng
   - DocumentDrafting: Tạo format từ code → format có thể sai

## ✅ GIẢI PHÁP ĐỀ XUẤT

### 1. Cho phép upload nhiều template cho document_drafting

**Cấu trúc:**
```
document_drafting assistant
├── Templates (nhiều template)
│   ├── Quyết định bổ nhiệm.docx
│   ├── Quyết định khen thưởng.docx
│   ├── Quyết định kỷ luật.docx
│   ├── Công văn đi.docx
│   ├── Công văn đến.docx
│   ├── Tờ trình.docx
│   └── ...
```

**Cách lưu trữ:**
- Option 1: Lưu trong `config` của assistant
  ```json
  {
    "templates": {
      "quyet_dinh_bo_nhiem": "/storage/templates/123/quyet_dinh_bo_nhiem.docx",
      "quyet_dinh_khen_thuong": "/storage/templates/123/quyet_dinh_khen_thuong.docx",
      "cong_van_di": "/storage/templates/123/cong_van_di.docx"
    }
  }
  ```

- Option 2: Tạo bảng `document_templates` (recommended)
  ```sql
  CREATE TABLE document_templates (
    id INT PRIMARY KEY,
    ai_assistant_id INT,
    document_type VARCHAR(50), -- 'quyet_dinh', 'cong_van', etc.
    template_subtype VARCHAR(50), -- 'bo_nhiem', 'khen_thuong', 'di', 'den', etc.
    template_file_path VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
  );
  ```

### 2. Cập nhật CreateAssistant.vue

**Khi chọn `document_drafting`:**
- Hiển thị phần upload templates
- Cho phép upload nhiều template cho các loại văn bản khác nhau
- Mỗi template có label: "Quyết định bổ nhiệm", "Quyết định khen thưởng", v.v.

### 3. Cập nhật DocumentDraftingService

**Flow mới:**
1. User yêu cầu: "Tạo quyết định bổ nhiệm ông Nguyễn Văn A..."
2. AI detect: `document_type = quyet_dinh`, `subtype = bo_nhiem`
3. Tìm template phù hợp: `quyet_dinh_bo_nhiem.docx`
4. Nếu có template → Dùng `TemplateProcessor` (giống ReportGenerator)
5. Nếu không có template → Fallback về code generation (hiện tại)

### 4. Sự khác biệt với ReportGenerator

**ReportGenerator:**
- 1 assistant = 1 template
- Template cho 1 loại báo cáo cụ thể
- User cung cấp data → AI điền vào template

**DocumentDrafting (sau khi cải thiện):**
- 1 assistant = nhiều template
- Templates cho nhiều loại văn bản khác nhau
- User yêu cầu → AI chọn template phù hợp → AI soạn thảo nội dung → Điền vào template

**Điểm khác biệt chính:**
- ReportGenerator: User cung cấp data, AI chỉ điền
- DocumentDrafting: User yêu cầu, AI **soạn thảo nội dung** + điền vào template

## 📋 KẾ HOẠCH TRIỂN KHAI

### Phase 1: Database & Models
- [ ] Tạo migration `document_templates` table
- [ ] Tạo model `DocumentTemplate`
- [ ] Cập nhật `AiAssistant` model (relationship)

### Phase 2: Backend
- [ ] Cập nhật `AdminController::createAssistant()` để nhận templates
- [ ] Cập nhật `DocumentDraftingService` để sử dụng template files
- [ ] Tích hợp `TemplateProcessor` (giống ReportGenerator)
- [ ] Fallback về code generation nếu không có template

### Phase 3: Frontend
- [ ] Cập nhật `CreateAssistant.vue` để upload templates
- [ ] Hiển thị danh sách templates đã upload
- [ ] Cho phép xóa/sửa templates

### Phase 4: AI Logic
- [ ] Cập nhật `IntentRecognizer` để detect document subtype
- [ ] Cập nhật `SmartAssistantEngine` để chọn template phù hợp
- [ ] AI soạn thảo nội dung dựa trên template structure

## 🎯 KẾT QUẢ MONG ĐỢI

Sau khi triển khai:
- ✅ Admin có thể upload nhiều template cho document_drafting
- ✅ Format văn bản đúng với format thực tế của cơ quan
- ✅ AI chọn template phù hợp dựa trên yêu cầu
- ✅ Giữ nguyên format của template (logo, header, footer, font, spacing)
- ✅ Khác biệt rõ ràng với ReportGenerator (AI soạn thảo vs chỉ điền data)



