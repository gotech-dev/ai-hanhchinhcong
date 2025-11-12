# 🔍 Debug: Vấn Đề Steps Không Hoạt Động

## ❌ Vấn Đề

Chatbot không thực thi step "Thu thập thông tin" mặc dù admin đã cấu hình.

## ✅ Đã Thêm Logging

Đã thêm logging chi tiết vào `SmartAssistantEngine.php` để debug:

1. **Log khi check steps** (dòng 68-76):
   - Kiểm tra xem có config không
   - Kiểm tra xem có steps không
   - Đếm số lượng steps
   - Hiển thị toàn bộ steps

2. **Log khi execute collect_info step** (dòng 1524-1576):
   - Kiểm tra questions/fields
   - Log từng bước xử lý
   - Log khi hỏi câu hỏi

## 🔍 Cách Debug

### Bước 1: Kiểm Tra Logs

Xem logs trong `storage/logs/laravel.log`:

```bash
tail -f storage/logs/laravel.log | grep "🔵"
```

Tìm các log:
- `[SmartAssistantEngine] Checking predefined steps` - Xem có steps không
- `[SmartAssistantEngine] Executing predefined steps` - Xem có thực thi không
- `[executeCollectInfoStep]` - Xem chi tiết step

### Bước 2: Kiểm Tra Database

Kiểm tra xem steps có được lưu vào database không:

```sql
SELECT id, name, config FROM ai_assistants WHERE id = <assistant_id>;
```

Kiểm tra:
- `config` có field `steps` không?
- `steps` có là array không?
- Mỗi step có `type`, `config.questions` hoặc `config.fields` không?

### Bước 3: Kiểm Tra Frontend

Kiểm tra xem frontend có gửi steps đúng format không:

1. Mở DevTools → Network
2. Tìm request `POST /admin/assistants` hoặc `PUT /admin/assistants/{id}`
3. Xem payload có field `steps` không
4. Xem format của `steps` có đúng không

### Bước 4: Test Thủ Công

Tạo một test script để kiểm tra:

```php
// test_steps.php
$assistant = \App\Models\AiAssistant::find(<assistant_id>);
$config = $assistant->config ?? [];
$steps = $config['steps'] ?? null;

echo "Config: " . json_encode($config, JSON_PRETTY_PRINT) . "\n";
echo "Steps: " . json_encode($steps, JSON_PRETTY_PRINT) . "\n";
echo "Steps count: " . (is_array($steps) ? count($steps) : 0) . "\n";
```

## 🐛 Các Vấn Đề Có Thể Xảy Ra

### 1. Steps Không Được Lưu

**Nguyên nhân**: Frontend không gửi `steps` hoặc format sai

**Giải pháp**: 
- Kiểm tra `AssistantStepsManager.vue` có emit `steps` không
- Kiểm tra `formatSteps()` có được gọi không

### 2. Config Không Được Đọc Đúng

**Nguyên nhân**: Model cast không đúng hoặc database lưu sai format

**Giải pháp**:
- Kiểm tra `AiAssistant` model có `'config' => 'array'` trong `casts()` không
- Kiểm tra database xem `config` có là JSON không

### 3. Steps Rỗng Hoặc Không Có Questions/Fields

**Nguyên nhân**: Admin chưa cấu hình `questions` hoặc `fields` trong step

**Giải pháp**:
- Kiểm tra step có `config.questions` hoặc `config.fields` không
- Nếu không có → Step sẽ trả về "Vui lòng cung cấp thông tin cần thiết."

### 4. Logic Check Steps Sai

**Nguyên nhân**: Điều kiện `if ($predefinedSteps && !empty($predefinedSteps))` không đúng

**Giải pháp**: Đã sửa thành `if ($predefinedSteps && is_array($predefinedSteps) && count($predefinedSteps) > 0)`

## ✅ Checklist Debug

- [ ] Kiểm tra logs xem có log `Checking predefined steps` không
- [ ] Kiểm tra logs xem `has_steps` có là `true` không
- [ ] Kiểm tra logs xem `steps_count` có > 0 không
- [ ] Kiểm tra database xem `config.steps` có tồn tại không
- [ ] Kiểm tra step đầu tiên có `type = "collect_info"` không
- [ ] Kiểm tra step có `config.questions` hoặc `config.fields` không
- [ ] Kiểm tra frontend có gửi `steps` trong request không

## 🔧 Fix Đã Áp Dụng

1. ✅ Thêm logging chi tiết
2. ✅ Sửa điều kiện check steps: `is_array($predefinedSteps) && count($predefinedSteps) > 0`
3. ✅ Thêm validation cho `questions` và `fields` phải là array
4. ✅ Thêm warning log nếu step không có questions/fields

## 📝 Format Steps Đúng

Steps phải có format:

```json
{
  "steps": [
    {
      "id": "step_1",
      "order": 1,
      "name": "Thu thập thông tin xã, phường",
      "description": "Thu thập thông tin cần thiết...",
      "type": "collect_info",
      "action": "",
      "required": true,
      "dependencies": [],
      "config": {
        "questions": [
          "Câu hỏi 1?",
          "Câu hỏi 2?"
        ]
        // HOẶC
        "fields": ["field1", "field2"]
      }
    }
  ]
}
```

## 🎯 Next Steps

1. Chạy lại chatbot và xem logs
2. Kiểm tra logs để xác định vấn đề cụ thể
3. Nếu vẫn không hoạt động, kiểm tra:
   - Database có lưu steps không
   - Frontend có gửi steps không
   - Format steps có đúng không


