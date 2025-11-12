# 📚 Hướng Dẫn Chi Tiết: Cơ Chế Hoạt Động Của Các Loại Steps

## 🎯 Tổng Quan

Hệ thống Steps cho phép Admin tạo các trợ lý AI với workflow được định nghĩa trước, thực thi tuần tự từng bước để hoàn thành một nhiệm vụ phức tạp. Mỗi Step có một loại (type) cụ thể và được thực thi theo thứ tự (order).

---

## 🔄 Cơ Chế Thực Thi Steps

### Luồng Hoạt Động Tổng Quan

1. **Khởi tạo**: Khi user bắt đầu chat với trợ lý có Steps, hệ thống khởi tạo `workflow_state` với `current_step_index = 0`
2. **Thực thi tuần tự**: Mỗi lần user gửi tin nhắn, hệ thống:
   - Lấy step hiện tại dựa trên `current_step_index`
   - Thực thi step đó dựa trên `type`
   - Cập nhật `collected_data` và `workflow_state`
   - Chuyển sang step tiếp theo nếu step hiện tại `completed = true`
3. **Lưu trữ dữ liệu**: Tất cả dữ liệu thu thập được lưu trong `session.collected_data` và có thể được sử dụng ở các step sau

---

## 📋 Chi Tiết Từng Loại Step

### 1. 🔍 **Thu thập thông tin** (`collect_info`)

#### Cơ Chế Hoạt Động:

**Mục đích**: Thu thập thông tin từ user thông qua câu hỏi hoặc extract tự động.

**Cách hoạt động**:

1. **Chế độ Questions (Hỏi từng câu)**:
   - Hệ thống hỏi từng câu một theo thứ tự trong mảng `questions`
   - Lưu các câu đã hỏi vào `collected_data['_asked_questions']`
   - Khi hỏi hết, sử dụng AI để extract tất cả câu trả lời từ tin nhắn cuối của user
   - Trả về JSON với format: `{"answer_1": "...", "answer_2": "..."}`

2. **Chế độ Fields (Extract tự động)**:
   - Sử dụng AI để extract các field được định nghĩa từ tin nhắn của user
   - Không cần hỏi từng câu, user có thể trả lời tất cả cùng lúc
   - AI sẽ tự động nhận diện và extract các field

**Cấu trúc Config**:

```json
{
  "type": "collect_info",
  "name": "Thu thập thông tin cơ bản",
  "description": "Hỏi user về tiêu đề, mục đích, đối tượng đọc",
  "order": 1,
  "required": true,
  "dependencies": [],
  "config": {
    "questions": [
      "Tiêu đề cuốn sách là gì?",
      "Mục đích viết sách là gì?",
      "Đối tượng đọc giả là ai?"
    ]
    // HOẶC
    "fields": [
      "title",
      "purpose", 
      "target_audience"
    ]
  }
}
```

**Kết quả trả về**:
- `completed: false` khi đang hỏi
- `completed: true` khi đã thu thập đủ
- `data`: Dữ liệu đã thu thập được merge vào `collected_data`

**Ví dụ thực tế**:

```
Step 1: Thu thập thông tin
Config: {
  "questions": ["Tên dự án là gì?", "Ngân sách dự kiến?"]
}

User: "Xin chào"
AI: "Tên dự án là gì?"

User: "Dự án xây dựng trường học"
AI: "Ngân sách dự kiến?"

User: "5 tỷ đồng"
AI: [Extract và lưu: {"answer_1": "Dự án xây dựng trường học", "answer_2": "5 tỷ đồng"}]
→ Step completed, chuyển sang Step 2
```

---

### 2. ✍️ **Tạo nội dung** (`generate`)

#### Cơ Chế Hoạt Động:

**Mục đích**: Sử dụng AI để tạo nội dung dựa trên dữ liệu đã thu thập.

**Cách hoạt động**:

1. Lấy `prompt_template` từ config (hoặc dùng `description` nếu không có)
2. Thay thế các placeholder `{field_name}` bằng giá trị từ `collected_data`
3. Gọi OpenAI API với:
   - System message: `description` của step
   - User message: Prompt đã được build
4. Lưu kết quả vào `collected_data` với key: `{step_id}_result`

**Cấu trúc Config**:

```json
{
  "type": "generate",
  "id": "step_2",
  "name": "Lập dàn ý",
  "description": "Tạo dàn ý chi tiết cho cuốn sách",
  "order": 2,
  "required": true,
  "dependencies": ["step_1"],
  "config": {
    "prompt_template": "Tạo dàn ý chi tiết cho cuốn sách '{answer_1}' với mục đích '{answer_2}' dành cho đối tượng '{answer_3}'. Dàn ý cần có ít nhất 5 chương.",
    "format": "markdown",
    "include_chapters": true
  }
}
```

**Placeholders hỗ trợ**:
- `{field_name}`: Thay bằng giá trị từ `collected_data[field_name]`
- `{user_message}`: Thay bằng tin nhắn hiện tại của user

**Kết quả trả về**:
- `completed: true` (luôn hoàn thành sau khi generate)
- `response`: Nội dung đã được tạo
- `data`: `{"step_2_result": "nội dung đã tạo"}`

**Ví dụ thực tế**:

```
Step 2: Tạo nội dung
Config: {
  "prompt_template": "Viết chương 1 cho cuốn sách '{answer_1}'"
}

collected_data = {
  "answer_1": "Lịch sử Việt Nam"
}

→ Prompt: "Viết chương 1 cho cuốn sách 'Lịch sử Việt Nam'"
→ AI tạo nội dung chương 1
→ Lưu vào collected_data["step_2_result"]
```

---

### 3. 🔎 **Tìm kiếm** (`search`)

#### Cơ Chế Hoạt Động:

**Mục đích**: Tìm kiếm thông tin trong documents đã upload bằng semantic search.

**Cách hoạt động**:

1. Lấy `search_query` từ config (hoặc dùng `userMessage` nếu không có)
2. Gọi `VectorSearchService->search()` với:
   - Query: Câu hỏi tìm kiếm
   - Assistant: Để lấy context và documents
   - Limit: Số kết quả tối đa (mặc định 5)
3. Trả về top 3 kết quả trong response
4. Lưu tất cả kết quả vào `collected_data` với key: `{step_id}_results`

**Cấu trúc Config**:

```json
{
  "type": "search",
  "id": "step_3",
  "name": "Tìm kiếm quy định",
  "description": "Tìm kiếm các quy định liên quan",
  "order": 3,
  "required": false,
  "dependencies": ["step_1"],
  "config": {
    "search_query": "quy định về xây dựng trường học",
    "max_results": 5
  }
}
```

**Lưu ý**:
- Nếu không có `search_query` trong config, hệ thống sẽ dùng `userMessage` hiện tại
- Semantic search hoạt động dựa trên vector embeddings của documents
- Chỉ tìm trong documents đã upload cho assistant đó

**Kết quả trả về**:
- `completed: true`
- `response`: "Đã tìm thấy X kết quả liên quan.\n\n[Top 3 kết quả]"
- `data`: `{"step_3_results": [array of results]}`

**Ví dụ thực tế**:

```
Step 3: Tìm kiếm
Config: {
  "search_query": "quy định về ngân sách"
}

→ VectorSearchService.search("quy định về ngân sách", assistant, 5)
→ Tìm thấy 3 documents liên quan
→ Trả về: "Đã tìm thấy 3 kết quả liên quan.\n\n[Document 1]\n[Document 2]\n[Document 3]"
→ Lưu vào collected_data["step_3_results"]
```

---

### 4. ⚙️ **Xử lý** (`process`)

#### Cơ Chế Hoạt Động:

**Mục đích**: Xử lý, biến đổi dữ liệu đã thu thập (hiện tại là placeholder, có thể mở rộng).

**Cách hoạt động**:

1. Nhận `collected_data` từ các step trước
2. Xử lý dựa trên `config` (hiện tại chưa có logic cụ thể)
3. Có thể mở rộng với các processor như:
   - Format dữ liệu
   - Tính toán
   - Chuyển đổi format
   - Merge dữ liệu

**Cấu trúc Config**:

```json
{
  "type": "process",
  "id": "step_4",
  "name": "Xử lý dữ liệu",
  "description": "Xử lý và format dữ liệu đã thu thập",
  "order": 4,
  "required": false,
  "dependencies": ["step_1", "step_2"],
  "config": {
    "processor": "format_data",
    "format": "json"
  }
}
```

**Kết quả trả về**:
- `completed: true`
- `response`: "Đã xử lý dữ liệu."
- `data`: (tùy vào processor)

**Lưu ý**: Step này hiện tại là placeholder, cần mở rộng thêm logic xử lý cụ thể.

---

### 5. ✅ **Kiểm tra** (`validate`)

#### Cơ Chế Hoạt Động:

**Mục đích**: Kiểm tra tính hợp lệ của dữ liệu đã thu thập.

**Cách hoạt động**:

1. Lấy `validation_rules` từ config
2. Kiểm tra từng field trong `collected_data`:
   - Field có tồn tại không?
   - Field có giá trị không rỗng không?
3. Nếu có lỗi, trả về danh sách lỗi và `completed: false`
4. Nếu hợp lệ, trả về `completed: true`

**Cấu trúc Config**:

```json
{
  "type": "validate",
  "id": "step_5",
  "name": "Kiểm tra dữ liệu",
  "description": "Kiểm tra tính hợp lệ của thông tin đã thu thập",
  "order": 5,
  "required": true,
  "dependencies": ["step_1"],
  "config": {
    "validation_rules": {
      "answer_1": "required",
      "answer_2": "required",
      "budget": "required|numeric"
    }
  }
}
```

**Kết quả trả về**:
- `completed: false` nếu có lỗi: `"Có lỗi xảy ra: answer_1 là bắt buộc., budget là bắt buộc."`
- `completed: true` nếu hợp lệ: `"Dữ liệu hợp lệ."`

**Ví dụ thực tế**:

```
Step 5: Kiểm tra
Config: {
  "validation_rules": {
    "title": "required",
    "budget": "required"
  }
}

collected_data = {
  "title": "Dự án A",
  "budget": ""  // Thiếu
}

→ Response: "Có lỗi xảy ra: budget là bắt buộc."
→ completed: false
→ Không chuyển sang step tiếp theo
```

---

### 6. 🔀 **Điều kiện** (`conditional`)

#### Cơ Chế Hoạt Động:

**Mục đích**: Rẽ nhánh workflow dựa trên điều kiện.

**Cách hoạt động**:

1. Lấy `condition` từ config
2. Đánh giá điều kiện bằng `evaluateCondition()`:
   - Hỗ trợ format: `has(field_name)` - kiểm tra field có tồn tại và có giá trị
   - Có thể mở rộng thêm các điều kiện khác
3. Nếu điều kiện đúng (`if_true`):
   - Trả về message và data từ `if_true`
4. Nếu điều kiện sai (`if_false`):
   - Trả về message và data từ `if_false`

**Cấu trúc Config**:

```json
{
  "type": "conditional",
  "id": "step_6",
  "name": "Kiểm tra điều kiện",
  "description": "Rẽ nhánh dựa trên dữ liệu đã thu thập",
  "order": 6,
  "required": false,
  "dependencies": ["step_1"],
  "config": {
    "condition": "has(budget)",
    "if_true": {
      "message": "Dự án có ngân sách, tiếp tục với quy trình A.",
      "data": {
        "workflow_path": "path_a"
      }
    },
    "if_false": {
      "message": "Dự án chưa có ngân sách, sử dụng quy trình B.",
      "data": {
        "workflow_path": "path_b"
      }
    }
  }
}
```

**Cú pháp điều kiện**:
- `has(field_name)`: Kiểm tra field có tồn tại và không rỗng
- Có thể mở rộng: `equals(field, value)`, `greater_than(field, value)`, etc.

**Kết quả trả về**:
- `completed: true` (luôn hoàn thành sau khi đánh giá)
- `response`: Message từ `if_true` hoặc `if_false`
- `data`: Data từ `if_true` hoặc `if_false` được merge vào `collected_data`

**Ví dụ thực tế**:

```
Step 6: Điều kiện
Config: {
  "condition": "has(budget)",
  "if_true": {
    "message": "Dự án có ngân sách, tiếp tục."
  },
  "if_false": {
    "message": "Cần bổ sung ngân sách."
  }
}

collected_data = {
  "budget": "5 tỷ"
}

→ Condition: has(budget) = true
→ Response: "Dự án có ngân sách, tiếp tục."
→ completed: true
```

---

## 🎓 Hướng Dẫn Admin Tạo Trợ Lý Tốt Nhất

### 📝 Quy Trình Thiết Kế Steps

#### Bước 1: Phân Tích Nhiệm Vụ

1. **Xác định mục tiêu**: Trợ lý cần làm gì?
2. **Liệt kê thông tin cần**: User cần cung cấp gì?
3. **Xác định quy trình**: Các bước logic để hoàn thành nhiệm vụ
4. **Xác định điều kiện**: Có nhánh rẽ nào không?

#### Bước 2: Thiết Kế Workflow

**Ví dụ: Trợ lý Soạn Thảo Văn Bản**

```
Step 1 (collect_info): Thu thập thông tin cơ bản
  → Tiêu đề, loại văn bản, người gửi/nhận

Step 2 (validate): Kiểm tra thông tin bắt buộc
  → Đảm bảo có đủ thông tin

Step 3 (search): Tìm kiếm template/văn bản mẫu
  → Tìm trong documents đã upload

Step 4 (generate): Tạo nội dung văn bản
  → Sử dụng template và thông tin đã thu thập

Step 5 (conditional): Kiểm tra có cần chỉnh sửa không
  → Nếu có → quay lại Step 4
  → Nếu không → hoàn thành
```

#### Bước 3: Cấu Hình Chi Tiết

### ✅ Best Practices

#### 1. **Thu thập thông tin (collect_info)**

**DO**:
- ✅ Đặt câu hỏi rõ ràng, dễ hiểu
- ✅ Sắp xếp câu hỏi theo thứ tự logic
- ✅ Sử dụng `questions` cho flow hỏi từng câu
- ✅ Sử dụng `fields` nếu user có thể trả lời tất cả cùng lúc

**DON'T**:
- ❌ Hỏi quá nhiều câu (tối đa 5-7 câu)
- ❌ Câu hỏi mơ hồ, không rõ ràng
- ❌ Hỏi thông tin không cần thiết

**Ví dụ tốt**:

```json
{
  "type": "collect_info",
  "name": "Thu thập thông tin dự án",
  "config": {
    "questions": [
      "Tên dự án là gì?",
      "Mục đích của dự án?",
      "Ngân sách dự kiến (VNĐ)?",
      "Thời gian thực hiện (tháng)?"
    ]
  }
}
```

#### 2. **Tạo nội dung (generate)**

**DO**:
- ✅ Viết `prompt_template` chi tiết, rõ ràng
- ✅ Sử dụng placeholders `{field_name}` để tham chiếu dữ liệu
- ✅ Mô tả rõ format mong muốn (markdown, JSON, văn bản)
- ✅ Đặt `description` làm system message cho AI

**DON'T**:
- ❌ Prompt quá ngắn, không đủ context
- ❌ Không sử dụng dữ liệu đã thu thập
- ❌ Không chỉ rõ format output

**Ví dụ tốt**:

```json
{
  "type": "generate",
  "name": "Tạo báo cáo",
  "description": "Bạn là chuyên gia viết báo cáo chuyên nghiệp. Viết báo cáo chi tiết, có cấu trúc rõ ràng.",
  "config": {
    "prompt_template": "Viết báo cáo về dự án '{answer_1}' với mục đích '{answer_2}'. Báo cáo cần có:\n1. Tổng quan dự án\n2. Mục tiêu\n3. Phương án thực hiện\n4. Ngân sách: {answer_3} VNĐ\n5. Thời gian: {answer_4} tháng\n\nFormat: Markdown với headings và bullet points."
  }
}
```

#### 3. **Tìm kiếm (search)**

**DO**:
- ✅ Đặt `search_query` cụ thể, có từ khóa quan trọng
- ✅ Upload documents liên quan trước khi user sử dụng
- ✅ Sử dụng `max_results` hợp lý (3-5 kết quả)

**DON'T**:
- ❌ Query quá chung chung
- ❌ Không upload documents trước
- ❌ Lấy quá nhiều kết quả (làm rối user)

**Ví dụ tốt**:

```json
{
  "type": "search",
  "name": "Tìm quy định",
  "config": {
    "search_query": "quy định về {answer_1}",
    "max_results": 3
  }
}
```

#### 4. **Kiểm tra (validate)**

**DO**:
- ✅ Kiểm tra các field bắt buộc
- ✅ Đặt validation ngay sau step thu thập
- ✅ Thông báo lỗi rõ ràng

**DON'T**:
- ❌ Bỏ qua validation
- ❌ Validate quá muộn (sau khi đã generate)

**Ví dụ tốt**:

```json
{
  "type": "validate",
  "name": "Kiểm tra thông tin",
  "dependencies": ["step_1"],
  "config": {
    "validation_rules": {
      "answer_1": "required",
      "answer_2": "required",
      "answer_3": "required|numeric"
    }
  }
}
```

#### 5. **Điều kiện (conditional)**

**DO**:
- ✅ Sử dụng để rẽ nhánh logic rõ ràng
- ✅ Đặt message dễ hiểu cho từng nhánh
- ✅ Lưu thông tin nhánh vào `data` để step sau sử dụng

**DON'T**:
- ❌ Điều kiện phức tạp quá
- ❌ Không có message rõ ràng

**Ví dụ tốt**:

```json
{
  "type": "conditional",
  "name": "Kiểm tra ngân sách",
  "config": {
    "condition": "has(budget)",
    "if_true": {
      "message": "Dự án có ngân sách, tiếp tục với quy trình chuẩn.",
      "data": {"workflow_type": "standard"}
    },
    "if_false": {
      "message": "Dự án chưa có ngân sách, sẽ sử dụng quy trình đơn giản hơn.",
      "data": {"workflow_type": "simple"}
    }
  }
}
```

### 🔗 Dependencies (Phụ Thuộc)

**Quan trọng**: Luôn đặt `dependencies` đúng để đảm bảo thứ tự thực thi:

```json
{
  "id": "step_1",
  "order": 1,
  "dependencies": []
},
{
  "id": "step_2",
  "order": 2,
  "dependencies": ["step_1"]  // Phải chờ step_1 hoàn thành
},
{
  "id": "step_3",
  "order": 3,
  "dependencies": ["step_1", "step_2"]  // Phải chờ cả 2 step trước
}
```

### 📊 Ví Dụ Hoàn Chỉnh: Trợ Lý Soạn Thảo Báo Cáo

```json
{
  "steps": [
    {
      "id": "step_1",
      "order": 1,
      "name": "Thu thập thông tin cơ bản",
      "description": "Hỏi user về thông tin báo cáo",
      "type": "collect_info",
      "required": true,
      "dependencies": [],
      "config": {
        "questions": [
          "Tên báo cáo là gì?",
          "Kỳ báo cáo (tháng/quý/năm)?",
          "Đơn vị báo cáo?"
        ]
      }
    },
    {
      "id": "step_2",
      "order": 2,
      "name": "Kiểm tra thông tin",
      "description": "Kiểm tra tính hợp lệ",
      "type": "validate",
      "required": true,
      "dependencies": ["step_1"],
      "config": {
        "validation_rules": {
          "answer_1": "required",
          "answer_2": "required",
          "answer_3": "required"
        }
      }
    },
    {
      "id": "step_3",
      "order": 3,
      "name": "Tìm kiếm template",
      "description": "Tìm template báo cáo mẫu",
      "type": "search",
      "required": false,
      "dependencies": ["step_2"],
      "config": {
        "search_query": "template báo cáo {answer_2}",
        "max_results": 3
      }
    },
    {
      "id": "step_4",
      "order": 4,
      "name": "Tạo nội dung báo cáo",
      "description": "Bạn là chuyên gia viết báo cáo. Viết báo cáo chuyên nghiệp, chi tiết.",
      "type": "generate",
      "required": true,
      "dependencies": ["step_3"],
      "config": {
        "prompt_template": "Viết báo cáo '{answer_1}' cho kỳ '{answer_2}' của đơn vị '{answer_3}'. Sử dụng thông tin từ template đã tìm được: {step_3_results}. Báo cáo cần có đầy đủ các phần: Tổng quan, Kết quả thực hiện, Khó khăn vướng mắc, Phương hướng tiếp theo."
      }
    }
  ]
}
```

### ⚠️ Lưu Ý Quan Trọng

1. **Thứ tự Steps**: Luôn đặt `order` đúng và `dependencies` chính xác
2. **Required Steps**: Đánh dấu `required: true` cho các step bắt buộc
3. **Error Handling**: Hệ thống tự động xử lý lỗi, nhưng nên test kỹ
4. **Data Flow**: Dữ liệu từ step trước có thể dùng ở step sau qua `collected_data`
5. **User Experience**: Không nên có quá nhiều steps (tối đa 7-10 steps)

---

## 🎯 Kết Luận

Hệ thống Steps cho phép tạo các trợ lý AI linh hoạt, có quy trình rõ ràng. Admin cần:

✅ Hiểu rõ từng loại step  
✅ Thiết kế workflow logic  
✅ Cấu hình chi tiết, rõ ràng  
✅ Test kỹ trước khi deploy  

Với cách tiếp cận này, Admin có thể tạo ra các trợ lý AI mạnh mẽ, chuyên nghiệp, đáp ứng nhu cầu cụ thể của từng use case.


