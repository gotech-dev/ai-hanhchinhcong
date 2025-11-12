# PHASE 3: TÍCH HỢP QUY TRÌNH
## Timeline: 3-4 tuần

---

## 🎯 MỤC TIÊU

Xây dựng tính năng tự động hóa quy trình hành chính, giúp hệ thống có thể tự động xử lý, phê duyệt và định tuyến văn bản theo quy trình hành chính.

**⚠️ VẤN ĐỀ QUAN TRỌNG:** Để tự động hóa quy trình hành chính, hệ thống cần nắm đủ các vị trí công việc trong hành chính công và cấu trúc tổ chức.

---

## 📚 THÔNG TIN VỀ VỊ TRÍ CÔNG VIỆC HÀNH CHÍNH CÔNG

### ⚠️ VẤN ĐỀ QUAN TRỌNG: Lấy thông tin vị trí công việc ở đâu?

### 1. Các vị trí công việc cần thiết

#### 1.1. Cấu trúc tổ chức hành chính công Việt Nam

**Các cấp lãnh đạo:**
- **Cấp cao nhất:** Bộ trưởng, Thứ trưởng
- **Cấp trung:** Cục trưởng, Vụ trưởng, Chánh Văn phòng
- **Cấp phòng:** Trưởng phòng, Phó phòng
- **Cấp cơ sở:** Chuyên viên, Cán bộ

**Các chức danh phổ biến:**
- Giám đốc / Phó Giám đốc
- Trưởng phòng / Phó phòng
- Trưởng ban / Phó ban
- Chánh Văn phòng / Phó Chánh Văn phòng
- Vụ trưởng / Phó Vụ trưởng
- Cục trưởng / Phó Cục trưởng
- Chánh Thanh tra / Phó Chánh Thanh tra

**Các bộ phận/phòng ban:**
- Phòng Tài chính
- Phòng Nhân sự
- Phòng Pháp chế
- Phòng Hành chính
- Phòng Kế hoạch
- Văn phòng
- Thanh tra

#### 1.2. Quy trình phê duyệt theo cấp

**Quy trình phê duyệt điển hình:**
1. **Người tạo** → Tạo văn bản
2. **Trưởng phòng** → Phê duyệt cấp phòng
3. **Phó Giám đốc** → Phê duyệt cấp phó
4. **Giám đốc** → Phê duyệt cuối cùng
5. **Ban hành** → Ký và ban hành

**Quy trình đặc biệt:**
- Văn bản quan trọng: Cần thêm cấp Bộ trưởng
- Văn bản nội bộ: Chỉ cần Trưởng phòng
- Văn bản tài chính: Cần Phòng Tài chính + Giám đốc

### 2. Nguồn thông tin về vị trí công việc

#### 2.1. Nguồn chính thức

**A. Cổng Thông tin điện tử Chính phủ**
- **URL:** https://xaydungchinhsach.chinhphu.vn
- **Mô tả:** Danh mục vị trí việc làm trong cơ quan, tổ chức hành chính
- **Nội dung:**
  - Danh mục vị trí việc làm của công chức lãnh đạo, quản lý cấp xã
  - Danh mục vị trí việc làm của công chức cấp xã
  - Cấu trúc tổ chức hành chính

**B. Thư viện Pháp luật**
- **URL:** https://thuvienphapluat.vn
- **Mô tả:** Danh mục vị trí việc làm trong cơ quan, tổ chức hành chính năm 2023
- **Nội dung:**
  - Danh mục chức danh lãnh đạo và quản lý
  - Các chức danh: Chánh Thanh tra, Chánh Văn phòng, Vụ trưởng, Cục trưởng, v.v.
  - Quy định về phân cấp phê duyệt

**C. Luật Minh Khuê**
- **URL:** https://luatminhkhue.vn
- **Mô tả:** Danh mục chức danh, chức vụ lãnh đạo chính trị hiện hành
- **Nội dung:**
  - Cơ cấu tổ chức hệ thống chính trị
  - Các vị trí trong hệ thống chính trị

**D. Luật Việt Nam**
- **URL:** https://luatvietnam.vn
- **Mô tả:** Danh mục chức vụ lãnh đạo và tương đương của hệ thống chính trị
- **Nội dung:**
  - Chức vụ lãnh đạo các cấp
  - Quy định về phân cấp quản lý

#### 2.2. Quy định pháp luật

**Các văn bản quy định:**
- Nghị định về tổ chức cơ quan hành chính nhà nước
- Thông tư hướng dẫn về phân cấp phê duyệt
- Quy định về quy trình xử lý văn bản
- Quy định về phân quyền trong cơ quan hành chính

### 3. Cách thu thập thông tin

#### 3.1. Phương án 1: Manual Input (Khuyến nghị ban đầu)

**Cách làm:**
- Admin nhập thông tin cấu trúc tổ chức của cơ quan
- Admin nhập các vị trí công việc và quyền hạn
- Admin nhập quy trình phê duyệt theo từng loại văn bản

**Ưu điểm:**
- ✅ Chính xác 100% (theo cơ quan cụ thể)
- ✅ Linh hoạt (mỗi cơ quan có cấu trúc khác nhau)
- ✅ Không vi phạm pháp luật

**Nhược điểm:**
- ❌ Tốn thời gian nhập liệu
- ❌ Cần người hiểu rõ cấu trúc tổ chức

**Flow:**
```
Admin → Nhập cấu trúc tổ chức → 
Nhập vị trí công việc → 
Nhập quy trình phê duyệt → 
Lưu vào database
```

#### 3.2. Phương án 2: Import từ quy định pháp luật

**Cách làm:**
- Crawl hoặc download quy định về cấu trúc tổ chức
- Parse và extract thông tin vị trí công việc
- Tạo template cấu trúc tổ chức chuẩn
- Admin chỉnh sửa theo cơ quan cụ thể

**Ưu điểm:**
- ✅ Có template chuẩn
- ✅ Đảm bảo tuân thủ quy định

**Nhược điểm:**
- ❌ Cần parse quy định phức tạp
- ❌ Vẫn cần admin chỉnh sửa

#### 3.3. Phương án 3: Tích hợp với hệ thống HR hiện có

**Cách làm:**
- Tích hợp với hệ thống quản lý nhân sự (nếu có)
- Import cấu trúc tổ chức từ HR system
- Import danh sách nhân viên và vị trí

**Ưu điểm:**
- ✅ Tự động cập nhật
- ✅ Đồng bộ với hệ thống hiện có

**Nhược điểm:**
- ❌ Phụ thuộc vào hệ thống HR
- ❌ Cần API hoặc database access

### 4. Database Schema cho Organizational Structure

```php
// database/migrations/xxxx_create_organizational_units_table.php
Schema::create('organizational_units', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // Tên đơn vị (vd: Phòng Tài chính)
    $table->string('code')->nullable(); // Mã đơn vị
    $table->string('type'); // Loại (Phòng, Ban, Vụ, Cục, Văn phòng)
    $table->unsignedBigInteger('parent_id')->nullable(); // Đơn vị cha
    $table->integer('level')->default(1); // Cấp độ (1, 2, 3, ...)
    $table->text('description')->nullable();
    $table->json('metadata')->nullable(); // Thông tin bổ sung
    $table->timestamps();
    
    $table->foreign('parent_id')->references('id')->on('organizational_units');
    $table->index('type');
    $table->index('level');
});

// database/migrations/xxxx_create_positions_table.php
Schema::create('positions', function (Blueprint $table) {
    $table->id();
    $table->string('title'); // Chức danh (vd: Trưởng phòng)
    $table->string('code')->nullable(); // Mã chức danh
    $table->string('level'); // Cấp độ (Trưởng phòng, Phó Giám đốc, Giám đốc)
    $table->unsignedBigInteger('organizational_unit_id')->nullable(); // Thuộc đơn vị nào
    $table->integer('approval_level')->default(1); // Cấp phê duyệt (1, 2, 3, ...)
    $table->json('permissions')->nullable(); // Quyền hạn
    $table->text('description')->nullable();
    $table->timestamps();
    
    $table->foreign('organizational_unit_id')->references('id')->on('organizational_units');
    $table->index('level');
    $table->index('approval_level');
});

// database/migrations/xxxx_create_users_positions_table.php
Schema::create('user_positions', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('position_id');
    $table->unsignedBigInteger('organizational_unit_id');
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->foreign('user_id')->references('id')->on('users');
    $table->foreign('position_id')->references('id')->on('positions');
    $table->foreign('organizational_unit_id')->references('id')->on('organizational_units');
    $table->unique(['user_id', 'position_id', 'organizational_unit_id']);
});

// database/migrations/xxxx_create_approval_workflows_table.php
Schema::create('approval_workflows', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // Tên quy trình (vd: Phê duyệt báo cáo)
    $table->string('document_type'); // Loại văn bản (Báo cáo, Quyết định, Công văn)
    $table->json('steps'); // Các bước phê duyệt
    // [
    //   {"level": 1, "position": "Trưởng phòng", "required": true},
    //   {"level": 2, "position": "Phó Giám đốc", "required": true},
    //   {"level": 3, "position": "Giám đốc", "required": true}
    // ]
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index('document_type');
});
```

### 5. Khuyến nghị triển khai

#### Phase 3.1: Manual Input (Tuần 1)
- ✅ Admin nhập cấu trúc tổ chức của cơ quan
- ✅ Admin nhập các vị trí công việc
- ✅ Admin nhập quy trình phê duyệt cơ bản
- ✅ Test với một số quy trình mẫu

#### Phase 3.2: Import Template (Tuần 2)
- ⚠️ Tạo template cấu trúc tổ chức chuẩn từ quy định
- ⚠️ Admin có thể chọn template và chỉnh sửa
- ⚠️ Import từ quy định pháp luật (nếu có)

#### Phase 3.3: Tích hợp HR (Tương lai)
- 🔮 Tích hợp với hệ thống HR (nếu có)
- 🔮 Tự động đồng bộ cấu trúc tổ chức
- 🔮 Tự động cập nhật nhân sự

### 6. Quy trình phê duyệt mẫu

**Quy trình phê duyệt báo cáo:**
1. Người tạo → Tạo báo cáo
2. Trưởng phòng → Phê duyệt nội dung
3. Phòng Tài chính → Kiểm tra tài chính (nếu có)
4. Phó Giám đốc → Phê duyệt cấp phó
5. Giám đốc → Phê duyệt cuối cùng
6. Ban hành → Ký và ban hành

**Quy trình phê duyệt quyết định:**
1. Người tạo → Tạo quyết định
2. Phòng Pháp chế → Kiểm tra pháp lý
3. Trưởng phòng → Phê duyệt nội dung
4. Phó Giám đốc → Phê duyệt cấp phó
5. Giám đốc → Phê duyệt và ký
6. Ban hành → Công bố

**Quy trình phê duyệt công văn:**
1. Người tạo → Tạo công văn
2. Trưởng phòng → Phê duyệt
3. Ban hành → Ký và gửi

---

## 📋 TODO LIST

### 0. Organizational Structure & Positions (QUAN TRỌNG - Phải làm trước)

- [ ] Tạo database schema cho cấu trúc tổ chức
  - [ ] Bảng `organizational_units` (đơn vị tổ chức)
  - [ ] Bảng `positions` (vị trí công việc)
  - [ ] Bảng `user_positions` (người dùng - vị trí)
  - [ ] Bảng `approval_workflows` (quy trình phê duyệt)

- [ ] Tạo models
  - [ ] `OrganizationalUnit` model
  - [ ] `Position` model
  - [ ] `UserPosition` model
  - [ ] `ApprovalWorkflow` model

- [ ] Tạo admin interface để nhập cấu trúc tổ chức
  - [ ] `OrganizationalStructure.vue` - Quản lý cấu trúc tổ chức
  - [ ] `Positions.vue` - Quản lý vị trí công việc
  - [ ] `ApprovalWorkflows.vue` - Quản lý quy trình phê duyệt

- [ ] Tạo seeder cho dữ liệu mẫu
  - [ ] Seeder cấu trúc tổ chức mẫu
  - [ ] Seeder vị trí công việc mẫu
  - [ ] Seeder quy trình phê duyệt mẫu

- [ ] Tích hợp với User system
  - [ ] Gán vị trí cho user
  - [ ] Xác định quyền hạn dựa trên vị trí
  - [ ] Xác định người phê duyệt dựa trên vị trí

### 1. Workflow Automation

- [ ] Tạo service `WorkflowAutomationService`
  - [ ] Định nghĩa quy trình hành chính (workflow templates)
  - [ ] Tự động xác định quy trình dựa trên loại văn bản
  - [ ] Tự động thực hiện các bước trong quy trình
  - [ ] Theo dõi tiến độ xử lý
  - [ ] Xử lý lỗi và rollback

- [ ] Tích hợp vào `SmartAssistantEngine`
  - [ ] Tự động phát hiện yêu cầu workflow
  - [ ] Tự động khởi tạo workflow
  - [ ] Tự động thực hiện các bước
  - [ ] Thông báo tiến độ cho user

- [ ] Tạo database schema cho workflow
  - [ ] Bảng `workflows` (định nghĩa quy trình)
  - [ ] Bảng `workflow_instances` (phiên bản quy trình đang chạy)
  - [ ] Bảng `workflow_steps` (các bước trong quy trình)
  - [ ] Bảng `workflow_logs` (lịch sử thực thi)

- [ ] Tạo API endpoints
  - [ ] `POST /api/workflows` - Tạo workflow mới
  - [ ] `GET /api/workflows/{id}` - Xem chi tiết workflow
  - [ ] `POST /api/workflows/{id}/execute` - Thực thi workflow
  - [ ] `GET /api/workflows/{id}/status` - Xem trạng thái

- [ ] Frontend components
  - [ ] `WorkflowBuilder.vue` - Tạo/sửa workflow (admin)
  - [ ] `WorkflowViewer.vue` - Xem workflow đang chạy
  - [ ] `WorkflowProgress.vue` - Hiển thị tiến độ

### 2. Multi-level Approval System

- [ ] Tạo service `ApprovalService`
  - [ ] Xác định cấp phê duyệt (Trưởng phòng → Phó Giám đốc → Giám đốc)
  - [ ] Tự động gửi đến đúng người phê duyệt
  - [ ] Theo dõi trạng thái phê duyệt
  - [ ] Xử lý phê duyệt/từ chối
  - [ ] Thông báo khi có phê duyệt

- [ ] Tạo database schema cho approval
  - [ ] Bảng `approvals` (yêu cầu phê duyệt)
  - [ ] Bảng `approval_levels` (các cấp phê duyệt)
  - [ ] Bảng `approval_history` (lịch sử phê duyệt)

- [ ] Tích hợp với User system
  - [ ] Phân quyền theo vai trò
  - [ ] Xác định người phê duyệt dựa trên vai trò
  - [ ] Quản lý chuỗi phê duyệt

- [ ] Tạo API endpoints
  - [ ] `POST /api/approvals` - Tạo yêu cầu phê duyệt
  - [ ] `GET /api/approvals/pending` - Danh sách chờ phê duyệt
  - [ ] `POST /api/approvals/{id}/approve` - Phê duyệt
  - [ ] `POST /api/approvals/{id}/reject` - Từ chối
  - [ ] `GET /api/approvals/{id}/history` - Lịch sử phê duyệt

- [ ] Frontend components
  - [ ] `ApprovalRequest.vue` - Tạo yêu cầu phê duyệt
  - [ ] `ApprovalList.vue` - Danh sách chờ phê duyệt
  - [ ] `ApprovalDetail.vue` - Chi tiết yêu cầu
  - [ ] `ApprovalHistory.vue` - Lịch sử phê duyệt

### 3. Document Routing System

- [ ] Tạo service `DocumentRoutingService`
  - [ ] Phân tích nội dung văn bản (dùng AI)
  - [ ] Xác định người nhận/bộ phận phù hợp
  - [ ] Tự động gửi đến đúng địa chỉ
  - [ ] Theo dõi trạng thái nhận/xử lý
  - [ ] Thông báo khi có văn bản mới

- [ ] Tích hợp AI để phân tích nội dung
  - [ ] Sử dụng OpenAI để phân loại văn bản
  - [ ] Xác định chủ đề (tài chính, nhân sự, pháp lý, ...)
  - [ ] Xác định mức độ ưu tiên
  - [ ] Gợi ý người nhận phù hợp

- [ ] Tạo database schema cho routing
  - [ ] Bảng `document_routes` (định tuyến văn bản)
  - [ ] Bảng `routing_rules` (quy tắc định tuyến)
  - [ ] Bảng `routing_history` (lịch sử gửi/nhận)

- [ ] Tạo API endpoints
  - [ ] `POST /api/documents/{id}/route` - Định tuyến văn bản
  - [ ] `GET /api/documents/{id}/route` - Xem thông tin định tuyến
  - [ ] `POST /api/documents/{id}/receive` - Xác nhận nhận văn bản
  - [ ] `GET /api/documents/inbox` - Hộp thư đến
  - [ ] `GET /api/documents/sent` - Hộp thư đi

- [ ] Frontend components
  - [ ] `DocumentRouter.vue` - Định tuyến văn bản
  - [ ] `Inbox.vue` - Hộp thư đến
  - [ ] `SentBox.vue` - Hộp thư đi
  - [ ] `DocumentTracking.vue` - Theo dõi văn bản

### 4. Notification System

- [ ] Tạo service `NotificationService`
  - [ ] Gửi email thông báo
  - [ ] Gửi SMS (nếu cần)
  - [ ] Push notification (nếu có app)
  - [ ] In-app notification
  - [ ] Template email hành chính

- [ ] Tích hợp với Email system
  - [ ] Cấu hình SMTP
  - [ ] Template email cho từng loại thông báo
  - [ ] Queue job để gửi email

- [ ] Tạo database schema cho notifications
  - [ ] Bảng `notifications` (thông báo)
  - [ ] Bảng `notification_templates` (template thông báo)

- [ ] Tạo API endpoints
  - [ ] `GET /api/notifications` - Danh sách thông báo
  - [ ] `POST /api/notifications/{id}/read` - Đánh dấu đã đọc
  - [ ] `GET /api/notifications/unread` - Thông báo chưa đọc

- [ ] Frontend components
  - [ ] `NotificationBell.vue` - Icon thông báo
  - [ ] `NotificationList.vue` - Danh sách thông báo
  - [ ] `NotificationItem.vue` - Item thông báo

### 5. Integration & Testing

- [ ] Tích hợp tất cả services
  - [ ] Workflow + Approval + Routing
  - [ ] Notification cho tất cả events
  - [ ] Error handling và rollback

- [ ] Unit tests
  - [ ] Tests cho `WorkflowAutomationService`
  - [ ] Tests cho `ApprovalService`
  - [ ] Tests cho `DocumentRoutingService`
  - [ ] Tests cho `NotificationService`

- [ ] Integration tests
  - [ ] Test workflow end-to-end
  - [ ] Test approval flow
  - [ ] Test routing flow

- [ ] Performance testing
  - [ ] Test với nhiều workflow đồng thời
  - [ ] Test với nhiều approval requests
  - [ ] Optimize database queries

### 6. Documentation

- [ ] Document workflow system
- [ ] Document approval system
- [ ] Document routing system
- [ ] Document API endpoints
- [ ] Update README

---

## 📅 TIMELINE

### Tuần 1: Workflow Automation
- Ngày 1-2: Tạo database schema và models
- Ngày 3-4: Tạo `WorkflowAutomationService`
- Ngày 5: Tích hợp vào `SmartAssistantEngine`

### Tuần 2: Multi-level Approval System
- Ngày 1-2: Tạo database schema và models
- Ngày 3-4: Tạo `ApprovalService`
- Ngày 5: Tích hợp với User system

### Tuần 3: Document Routing System
- Ngày 1-2: Tạo `DocumentRoutingService` với AI
- Ngày 3: Tạo database schema và models
- Ngày 4-5: Tích hợp và test

### Tuần 4: Notification & Integration
- Ngày 1-2: Tạo `NotificationService`
- Ngày 3: Tích hợp tất cả services
- Ngày 4: Testing tổng hợp
- Ngày 5: Fix bugs và documentation

---

## ✅ KẾT QUẢ MONG ĐỢI

Sau khi hoàn thành Phase 3, hệ thống có thể:

1. ✅ Tự động hóa quy trình hành chính
2. ✅ Phê duyệt đa cấp tự động
3. ✅ Định tuyến văn bản thông minh
4. ✅ Thông báo tự động cho mọi sự kiện
5. ✅ Theo dõi tiến độ xử lý văn bản

---

## 🔗 LIÊN KẾT

- [advanced-feature.md](./advanced-feature.md) - Tài liệu tổng quan về các tính năng nâng cao
- [phase1.md](./phase1.md) - Phase 1: Tuân thủ pháp luật

