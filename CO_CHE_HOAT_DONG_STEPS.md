# 🔍 Cơ Chế Hoạt Động Của Các Loại Steps - Giải Thích Chi Tiết

## ❓ Câu Hỏi Trọng Tâm

**Khi admin chọn một step type, chatbot sẽ làm gì CỤ THỂ?**  
**Nếu admin không cung cấp tài liệu, chatbot thu thập thông tin từ đâu?**

---

## 📋 Danh Sách 6 Loại Steps

Hệ thống có **6 loại steps** (không có "Điều tra", có thể bạn nhầm với "Kiểm tra"):

1. **Thu thập thông tin** (`collect_info`)
2. **Tạo nội dung** (`generate`)
3. **Tìm kiếm** (`search`)
4. **Xử lý** (`process`)
5. **Kiểm tra** (`validate`) - có thể bạn gọi là "Điều tra"
6. **Điều kiện** (`conditional`)

---

## 1. 🔍 THU THẬP THÔNG TIN (`collect_info`)

### ⚙️ Cơ Chế Hoạt Động Khi Admin Chọn Step Này:

**Khi admin chọn step type = "Thu thập thông tin"**, chatbot sẽ:

1. **Đọc config từ step**:
   - Nếu có `config.questions` → Hỏi từng câu một
   - Nếu có `config.fields` → Extract tự động từ tin nhắn user

2. **Hành động cụ thể**:
   - **Chế độ Questions**: Chatbot hỏi user từng câu theo thứ tự
   - **Chế độ Fields**: Chatbot dùng AI để extract thông tin từ tin nhắn của user

### 📍 Nguồn Dữ Liệu:

**❌ KHÔNG CẦN TÀI LIỆU!**

**Chatbot thu thập thông tin từ:**
- ✅ **Tin nhắn của USER** (user gửi trong chat)
- ✅ **AI Extract** từ câu trả lời của user

**Ví dụ cụ thể:**

```
Admin cấu hình:
{
  "type": "collect_info",
  "config": {
    "questions": [
      "Tên dự án là gì?",
      "Ngân sách dự kiến?"
    ]
  }
}

Khi user chat:
User: "Xin chào"
Chatbot: "Tên dự án là gì?"  ← Hỏi câu đầu tiên

User: "Dự án xây dựng trường học"
Chatbot: "Ngân sách dự kiến?"  ← Hỏi câu thứ 2

User: "5 tỷ đồng"
Chatbot: [Dùng AI extract] → Lưu: {"answer_1": "Dự án xây dựng trường học", "answer_2": "5 tỷ đồng"}
```

**Kết luận**: Step này **KHÔNG CẦN TÀI LIỆU**, chỉ cần user trả lời câu hỏi.

---

## 2. ✍️ TẠO NỘI DUNG (`generate`)

### ⚙️ Cơ Chế Hoạt Động Khi Admin Chọn Step Này:

**Khi admin chọn step type = "Tạo nội dung"**, chatbot sẽ:

1. **Đọc `prompt_template` từ config**
2. **Thay thế placeholders** `{field_name}` bằng dữ liệu từ `collected_data`
3. **Gọi OpenAI API** để tạo nội dung
4. **Trả về nội dung đã tạo** cho user

### 📍 Nguồn Dữ Liệu:

**❌ KHÔNG CẦN TÀI LIỆU!**

**Chatbot tạo nội dung từ:**
- ✅ **Dữ liệu đã thu thập** từ các step trước (`collected_data`)
- ✅ **Prompt template** mà admin đã cấu hình
- ✅ **Kiến thức của AI** (OpenAI GPT)

**Ví dụ cụ thể:**

```
Admin cấu hình:
{
  "type": "generate",
  "config": {
    "prompt_template": "Viết báo cáo về dự án '{answer_1}' với ngân sách {answer_2}"
  }
}

collected_data = {
  "answer_1": "Dự án xây dựng trường học",
  "answer_2": "5 tỷ đồng"
}

→ Prompt gửi cho AI: "Viết báo cáo về dự án 'Dự án xây dựng trường học' với ngân sách 5 tỷ đồng"
→ AI tạo nội dung báo cáo
→ Chatbot trả về nội dung cho user
```

**Kết luận**: Step này **KHÔNG CẦN TÀI LIỆU**, chỉ cần dữ liệu từ step trước và AI sẽ tự tạo.

---

## 3. 🔎 TÌM KIẾM (`search`)

### ⚙️ Cơ Chế Hoạt Động Khi Admin Chọn Step Này:

**Khi admin chọn step type = "Tìm kiếm"**, chatbot sẽ:

1. **Lấy `search_query` từ config** (hoặc dùng `userMessage` nếu không có)
2. **Gọi VectorSearchService** để tìm kiếm semantic
3. **Tìm trong documents đã upload** cho assistant đó
4. **Trả về top kết quả** (mặc định 5, hiển thị top 3)

### 📍 Nguồn Dữ Liệu:

**✅ CẦN CÓ TÀI LIỆU!**

**Chatbot tìm kiếm trong:**
- ✅ **Documents đã upload** cho assistant (PDF, DOCX, TXT)
- ✅ **Vector embeddings** của documents (đã được index trước)

**Nếu KHÔNG có tài liệu:**
- ❌ **Không tìm thấy gì** → Trả về: "Đã tìm thấy 0 kết quả liên quan."
- ⚠️ **Step vẫn hoàn thành** nhưng không có dữ liệu hữu ích

**Ví dụ cụ thể:**

```
Admin cấu hình:
{
  "type": "search",
  "config": {
    "search_query": "quy định về ngân sách",
    "max_results": 5
  }
}

Admin đã upload documents:
- document1.pdf: "Quy định về quản lý ngân sách..."
- document2.pdf: "Hướng dẫn chi tiêu ngân sách..."

→ VectorSearchService.search("quy định về ngân sách", assistant, 5)
→ Tìm thấy 2 documents liên quan
→ Trả về: "Đã tìm thấy 2 kết quả liên quan.\n\n[Document 1]\n[Document 2]"
```

**Kết luận**: Step này **CẦN CÓ TÀI LIỆU** đã upload. Nếu không có → không tìm thấy gì.

---

## 4. ⚙️ XỬ LÝ (`process`)

### ⚙️ Cơ Chế Hoạt Động Khi Admin Chọn Step Này:

**Khi admin chọn step type = "Xử lý"**, chatbot sẽ:

1. **Nhận `collected_data` từ các step trước**
2. **Xử lý dữ liệu** (hiện tại là placeholder, chưa có logic cụ thể)
3. **Trả về**: "Đã xử lý dữ liệu."

### 📍 Nguồn Dữ Liệu:

**❌ KHÔNG CẦN TÀI LIỆU!**

**Chatbot xử lý:**
- ✅ **Dữ liệu từ `collected_data`** (từ các step trước)
- ⚠️ **Hiện tại chưa có logic xử lý cụ thể** (có thể mở rộng sau)

**Ví dụ cụ thể:**

```
Admin cấu hình:
{
  "type": "process",
  "config": {
    "processor": "format_data"
  }
}

collected_data = {
  "answer_1": "Dự án A",
  "answer_2": "5 tỷ"
}

→ [Xử lý dữ liệu] → Trả về: "Đã xử lý dữ liệu."
```

**Kết luận**: Step này **KHÔNG CẦN TÀI LIỆU**, chỉ xử lý dữ liệu từ step trước (hiện tại là placeholder).

---

## 5. ✅ KIỂM TRA (`validate`) - Có thể bạn gọi là "Điều tra"

### ⚙️ Cơ Chế Hoạt Động Khi Admin Chọn Step Này:

**Khi admin chọn step type = "Kiểm tra"**, chatbot sẽ:

1. **Đọc `validation_rules` từ config**
2. **Kiểm tra từng field** trong `collected_data`:
   - Field có tồn tại không?
   - Field có giá trị không rỗng không?
3. **Nếu có lỗi**: Trả về danh sách lỗi, `completed = false`
4. **Nếu hợp lệ**: Trả về "Dữ liệu hợp lệ.", `completed = true`

### 📍 Nguồn Dữ Liệu:

**❌ KHÔNG CẦN TÀI LIỆU!**

**Chatbot kiểm tra:**
- ✅ **Dữ liệu từ `collected_data`** (từ các step trước)
- ✅ **Validation rules** mà admin đã cấu hình

**Ví dụ cụ thể:**

```
Admin cấu hình:
{
  "type": "validate",
  "config": {
    "validation_rules": {
      "answer_1": "required",
      "answer_2": "required",
      "budget": "required|numeric"
    }
  }
}

collected_data = {
  "answer_1": "Dự án A",
  "answer_2": "",  // Thiếu
  "budget": "5 tỷ"
}

→ Kiểm tra: answer_2 thiếu
→ Trả về: "Có lỗi xảy ra: answer_2 là bắt buộc."
→ completed = false → Không chuyển sang step tiếp theo
```

**Kết luận**: Step này **KHÔNG CẦN TÀI LIỆU**, chỉ kiểm tra tính hợp lệ của dữ liệu đã thu thập.

---

## 6. 🔀 ĐIỀU KIỆN (`conditional`)

### ⚙️ Cơ Chế Hoạt Động Khi Admin Chọn Step Này:

**Khi admin chọn step type = "Điều kiện"**, chatbot sẽ:

1. **Đọc `condition` từ config** (ví dụ: `has(budget)`)
2. **Đánh giá điều kiện** bằng `evaluateCondition()`
3. **Nếu điều kiện đúng**: Trả về message/data từ `if_true`
4. **Nếu điều kiện sai**: Trả về message/data từ `if_false`

### 📍 Nguồn Dữ Liệu:

**❌ KHÔNG CẦN TÀI LIỆU!**

**Chatbot đánh giá:**
- ✅ **Dữ liệu từ `collected_data`** (từ các step trước)
- ✅ **Điều kiện** mà admin đã cấu hình

**Ví dụ cụ thể:**

```
Admin cấu hình:
{
  "type": "conditional",
  "config": {
    "condition": "has(budget)",
    "if_true": {
      "message": "Dự án có ngân sách, tiếp tục."
    },
    "if_false": {
      "message": "Cần bổ sung ngân sách."
    }
  }
}

collected_data = {
  "budget": "5 tỷ"
}

→ Đánh giá: has(budget) = true
→ Trả về: "Dự án có ngân sách, tiếp tục."
```

**Kết luận**: Step này **KHÔNG CẦN TÀI LIỆU**, chỉ đánh giá điều kiện dựa trên dữ liệu đã thu thập.

---

## 📊 TÓM TẮT: NGUỒN DỮ LIỆU CỦA TỪNG STEP

| Step Type | Cần Tài Liệu? | Nguồn Dữ Liệu |
|-----------|---------------|---------------|
| **Thu thập thông tin** | ❌ KHÔNG | User trả lời trong chat |
| **Tạo nội dung** | ❌ KHÔNG | Dữ liệu từ step trước + AI |
| **Tìm kiếm** | ✅ CÓ | Documents đã upload |
| **Xử lý** | ❌ KHÔNG | Dữ liệu từ step trước |
| **Kiểm tra** | ❌ KHÔNG | Dữ liệu từ step trước |
| **Điều kiện** | ❌ KHÔNG | Dữ liệu từ step trước |

---

## 🎯 TRẢ LỜI CÂU HỎI CỤ THỂ

### ❓ "Nếu admin không cung cấp tài liệu, chatbot thu thập thông tin ở đâu?"

**Trả lời:**

1. **Step "Thu thập thông tin"**: 
   - ✅ **KHÔNG CẦN TÀI LIỆU**
   - ✅ Chatbot **hỏi user trực tiếp** trong chat
   - ✅ User trả lời → Chatbot lưu vào `collected_data`

2. **Step "Tìm kiếm"**:
   - ⚠️ **CẦN CÓ TÀI LIỆU**
   - ❌ Nếu không có tài liệu → Không tìm thấy gì
   - ✅ Nếu có tài liệu → Tìm trong documents đã upload

3. **Các step khác**:
   - ✅ **KHÔNG CẦN TÀI LIỆU**
   - ✅ Sử dụng dữ liệu từ `collected_data` (từ step trước)

---

## 💡 KẾT LUẬN

**Hầu hết các steps KHÔNG CẦN TÀI LIỆU**, chỉ có step **"Tìm kiếm"** cần có tài liệu đã upload.

**Luồng dữ liệu điển hình:**
```
Step 1 (Thu thập): User trả lời → Lưu vào collected_data
Step 2 (Tìm kiếm): Tìm trong documents (nếu có) → Lưu kết quả
Step 3 (Tạo nội dung): Dùng collected_data → Tạo nội dung
Step 4 (Kiểm tra): Kiểm tra collected_data → Xác nhận hợp lệ
```

**Admin cần làm gì:**
- ✅ Cấu hình `questions` hoặc `fields` cho step "Thu thập thông tin"
- ✅ Upload documents nếu muốn dùng step "Tìm kiếm"
- ✅ Cấu hình `prompt_template` cho step "Tạo nội dung"
- ✅ Cấu hình `validation_rules` cho step "Kiểm tra"
- ✅ Cấu hình `condition` cho step "Điều kiện"


