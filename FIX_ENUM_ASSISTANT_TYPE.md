# FIX: Lỗi Enum AssistantType

## 🔴 Vấn đề

Khi tạo trợ lý với `assistant_type = "loai_tro_ly_sang_tao_noi_dung"` (từ bảng `assistant_types`), hệ thống báo lỗi:

```
ValueError: "loai_tro_ly_sang_tao_noi_dung" is not a valid backing value for enum App\Enums\AssistantType
```

## 🔍 Nguyên nhân

1. **Model `AiAssistant` đang cast `assistant_type` thành enum cố định** (`App\Enums\AssistantType`)
2. **Hệ thống đã chuyển sang dùng bảng `assistant_types`** để quản lý các loại trợ lý động (có thể tạo mới qua admin)
3. **Enum chỉ có các giá trị cố định** như `qa_based_document`, `document_drafting`, etc.
4. **Giá trị từ bảng `assistant_types`** (ví dụ: `loai_tro_ly_sang_tao_noi_dung`) không có trong enum → Lỗi

## ✅ Giải pháp

### 1. Bỏ cast enum trong `AiAssistant` model
- **File**: `app/Models/AiAssistant.php`
- Bỏ `'assistant_type' => AssistantType::class` khỏi `casts()`
- Giờ `assistant_type` là string thuần túy

### 2. Thêm relationship với bảng `assistant_types`
- **File**: `app/Models/AiAssistant.php`
- Thêm method `type()` để lấy thông tin từ bảng `assistant_types`
- Cập nhật accessor `getTypeDisplayNameAttribute()` và `getTypeDescriptionAttribute()` để:
  - Ưu tiên lấy từ relationship (bảng `assistant_types`)
  - Fallback về enum nếu là giá trị cũ (backward compatibility)

### 3. Thêm helper method `getAssistantTypeValue()`
- **File**: `app/Models/AiAssistant.php`
- Trả về giá trị string của `assistant_type`
- Thay thế cho `->value` khi dùng enum

### 4. Cập nhật tất cả các nơi đang dùng `assistant_type->value`
- **Files đã sửa**:
  - `app/Services/SmartAssistantEngine.php` - 13 chỗ
  - `app/Http/Controllers/ChatController.php` - 6 chỗ
  - `app/Services/WorkflowPlanner.php` - 1 chỗ
  - `app/Services/IntentRecognizer.php` - 1 chỗ
  - `app/Services/ReportGenerator.php` - 2 chỗ

- **Thay đổi**: `$assistant->assistant_type->value` → `$assistant->getAssistantTypeValue()`

## 📋 Chi tiết thay đổi

### `app/Models/AiAssistant.php`

```php
// ❌ TRƯỚC
protected function casts(): array
{
    return [
        'assistant_type' => AssistantType::class, // Enum cast
        // ...
    ];
}

// ✅ SAU
protected function casts(): array
{
    return [
        // Bỏ cast enum - dùng string
        // 'assistant_type' => AssistantType::class,
        // ...
    ];
}

// ✅ MỚI: Relationship
public function type(): BelongsTo
{
    return $this->belongsTo(AssistantType::class, 'assistant_type', 'code');
}

// ✅ MỚI: Helper method
public function getAssistantTypeValue(): string
{
    return $this->attributes['assistant_type'] ?? '';
}
```

### Các file khác

```php
// ❌ TRƯỚC
if ($assistant->assistant_type->value === 'document_drafting') {
    // ...
}

// ✅ SAU
if ($assistant->getAssistantTypeValue() === 'document_drafting') {
    // ...
}
```

## 🎯 Kết quả

1. ✅ **Hệ thống chấp nhận bất kỳ giá trị `assistant_type` nào** từ bảng `assistant_types`
2. ✅ **Backward compatibility**: Các giá trị enum cũ vẫn hoạt động
3. ✅ **Relationship**: Có thể truy cập thông tin đầy đủ từ bảng `assistant_types` qua `$assistant->type`
4. ✅ **Không còn lỗi ValueError** khi tạo trợ lý với loại mới

## ⚠️ Lưu ý

- Enum `App\Enums\AssistantType` vẫn tồn tại và có thể được dùng cho các mục đích khác (ví dụ: validation, constants)
- Các accessor `type_display_name` và `type_description` vẫn hoạt động, ưu tiên lấy từ relationship
- Code cũ vẫn hoạt động nhờ backward compatibility

## 🧪 Testing

Sau khi fix, test:
1. ✅ Tạo trợ lý với loại từ bảng `assistant_types` (ví dụ: `loai_tro_ly_sang_tao_noi_dung`)
2. ✅ Tạo trợ lý với loại enum cũ (ví dụ: `qa_based_document`)
3. ✅ Truy cập `$assistant->type` để lấy thông tin từ bảng
4. ✅ Truy cập `$assistant->type_display_name` để lấy tên hiển thị


