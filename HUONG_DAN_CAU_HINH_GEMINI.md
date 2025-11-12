# Hướng Dẫn Cấu Hình Gemini API cho Web Search

## 📋 Tổng Quan

Hệ thống đã được tích hợp Gemini API với Google Search Integration để Q&A assistant có thể tìm kiếm thông tin trên mạng khi không có documents.

---

## 🔑 Cấu Hình

### 1. Lấy Google AI API Key

1. Truy cập: https://aistudio.google.com/apikey
2. Đăng nhập với Google account
3. Click "Create API Key"
4. Copy API key

### 2. Thêm vào .env

Thêm các dòng sau vào file `.env`:

```env
# Google AI (Gemini) API Configuration
GOOGLE_AI_API_KEY=your_api_key_here
GEMINI_MODEL=gemini-1.5-flash
```

**Lưu ý:**
- `GOOGLE_AI_API_KEY`: API key từ Google AI Studio
- `GEMINI_MODEL`: Model Gemini sử dụng (mặc định: `gemini-1.5-flash`)
  - `gemini-1.5-flash`: Nhanh, rẻ, phù hợp cho web search
  - `gemini-1.5-pro`: Chất lượng cao hơn, đắt hơn

### 3. Pricing

**Gemini 1.5 Flash:**
- Input: $0.075 / 1M tokens
- Output: $0.30 / 1M tokens
- Rất rẻ so với GPT-4o

**Gemini 1.5 Pro:**
- Input: $1.25 / 1M tokens
- Output: $5.00 / 1M tokens

**Google Search Integration:**
- Miễn phí (tích hợp sẵn trong Gemini API)

---

## 🚀 Cách Hoạt Động

### Flow:

1. **User hỏi câu hỏi** → Q&A assistant
2. **Kiểm tra documents:**
   - Có documents → Tìm kiếm trong documents (vector search)
   - Không có documents hoặc không tìm thấy → **Tìm kiếm trên mạng với Gemini**
3. **Gemini tự động:**
   - Search trên Google
   - Tổng hợp thông tin
   - Trả lời câu hỏi
4. **Trả về kết quả** cho user

### Fallback:

- Nếu Gemini API fail → Fallback về ChatGPT với knowledge cutoff
- Nếu không có API key → Fallback về ChatGPT

---

## 📝 Ví Dụ Sử Dụng

### Câu hỏi: "Hà Nội có bao nhiêu tỉnh?"

**Flow:**
1. Không có documents → Gọi Gemini với Google Search
2. Gemini search Google: "Hà Nội có bao nhiêu tỉnh"
3. Gemini trả lời: "Hà Nội là thành phố trực thuộc Trung ương, không phải tỉnh. Hà Nội có 30 quận/huyện..."

**Kết quả:**
- Answer: Câu trả lời từ Gemini
- Sources: Danh sách nguồn từ Google Search

---

## 🔧 Troubleshooting

### Lỗi: "Google AI API key not configured"

**Giải pháp:**
- Kiểm tra `.env` có `GOOGLE_AI_API_KEY` chưa
- Chạy `php artisan config:clear` sau khi thêm key

### Lỗi: "Gemini API error"

**Nguyên nhân có thể:**
- API key không đúng
- Quota đã hết
- Network issue

**Giải pháp:**
- Kiểm tra API key tại https://aistudio.google.com/apikey
- Kiểm tra quota tại Google Cloud Console
- Hệ thống sẽ tự động fallback về ChatGPT

### Lỗi: "Model not found"

**Giải pháp:**
- Kiểm tra `GEMINI_MODEL` trong `.env`
- Sử dụng: `gemini-1.5-flash` hoặc `gemini-1.5-pro`

---

## 📊 Monitoring

Logs được ghi tại:
- `storage/logs/laravel.log`

Tìm kiếm:
- `Gemini web search completed` - Thành công
- `Gemini API error` - Lỗi API
- `Falling back to ChatGPT` - Fallback

---

## ✅ Checklist

- [ ] Đã lấy Google AI API key
- [ ] Đã thêm `GOOGLE_AI_API_KEY` vào `.env`
- [ ] Đã thêm `GEMINI_MODEL` vào `.env` (optional)
- [ ] Đã chạy `php artisan config:clear`
- [ ] Đã test với Q&A assistant (không có documents)

---

*Tài liệu này hướng dẫn cấu hình Gemini API cho tính năng web search trong Q&A assistant.*


