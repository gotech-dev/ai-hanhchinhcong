# PHƯƠNG ÁN TẠO TÍNH NĂNG QUẢN LÝ "LOẠI ASSISTANT" TRONG ADMIN

## TỔNG QUAN
Tạo tính năng CRUD (Create, Read, Update, Delete) để quản lý các loại Assistant trong phần Admin, cho phép admin tạo, sửa và xóa các loại Assistant.

---

## 1. MÀN HÌNH TẠO MỚI (CREATE)

### 1.1. Backend - Controller
**File:** `app/Http/Controllers/Inertia/AdminController.php`

**Thêm method:**
```php
public function createAssistantType(Request $request): Response
{
    return Inertia::render('Admin/CreateAssistantType');
}
```

**File:** `app/Http/Controllers/AdminController.php`

**Thêm method xử lý POST:**
```php
public function storeAssistantType(Request $request)
{
    $validator = Validator::make($request->all(), [
        'code' => 'required|string|max:50|unique:assistant_types,code',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
        'icon' => 'nullable|string|max:100',
        'color' => 'nullable|string|max:7', // Hex color
    ]);
    
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
    
    $assistantType = AssistantType::create($validator->validated());
    
    return response()->json([
        'message' => 'Loại Assistant đã được tạo thành công',
        'assistant_type' => $assistantType,
    ]);
}
```

### 1.2. Frontend - Vue Component
**File:** `resources/js/Pages/Admin/CreateAssistantType.vue`

**Các trường form cần có:**
- **Mã loại (Code)**: Text input, required, unique
- **Tên loại (Name)**: Text input, required
- **Mô tả (Description)**: Textarea, optional
- **Trạng thái (Is Active)**: Checkbox, default true
- **Icon**: Text input, optional (có thể dùng icon picker sau)
- **Màu sắc (Color)**: Color picker hoặc text input, optional

**Chức năng:**
- Validate form trước khi submit
- Hiển thị lỗi validation
- Submit form qua API
- Redirect về danh sách sau khi tạo thành công
- Hiển thị loading state khi đang submit

### 1.3. Routes
**File:** `routes/web.php`

**Thêm routes:**
```php
Route::get('/admin/assistant-types/create', [AdminController::class, 'createAssistantType'])->name('assistant-types.create');
Route::post('/admin/assistant-types', [\App\Http\Controllers\AdminController::class, 'storeAssistantType'])->name('assistant-types.store');
```

---

## 2. MÀN HÌNH CHỈNH SỬA (EDIT)

### 2.1. Backend - Controller
**File:** `app/Http/Controllers/Inertia/AdminController.php`

**Thêm method:**
```php
public function editAssistantType(Request $request, int $typeId): Response
{
    $assistantType = \App\Models\AssistantType::findOrFail($typeId);
    
    return Inertia::render('Admin/EditAssistantType', [
        'assistantType' => $assistantType,
    ]);
}
```

**File:** `app/Http/Controllers/AdminController.php`

**Thêm method xử lý PUT/PATCH:**
```php
public function updateAssistantType(Request $request, int $typeId)
{
    $assistantType = \App\Models\AssistantType::findOrFail($typeId);
    
    $validator = Validator::make($request->all(), [
        'code' => ['required', 'string', 'max:50', Rule::unique('assistant_types', 'code')->ignore($typeId)],
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
        'icon' => 'nullable|string|max:100',
        'color' => 'nullable|string|max:7',
    ]);
    
    if ($validator->fails()) {
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        return redirect()->back()->withErrors($validator->errors());
    }
    
    $assistantType->update($validator->validated());
    
    if ($request->wantsJson() || $request->expectsJson()) {
        return response()->json([
            'message' => 'Loại Assistant đã được cập nhật thành công',
            'assistant_type' => $assistantType,
        ]);
    }
    
    return redirect()->route('admin.assistant-types.index')
        ->with('success', 'Loại Assistant đã được cập nhật thành công');
}
```

### 2.2. Frontend - Vue Component
**File:** `resources/js/Pages/Admin/EditAssistantType.vue`

**Tương tự CreateAssistantType.vue nhưng:**
- Pre-fill form với dữ liệu từ `props.assistantType`
- Method submit là `updateAssistantType` thay vì `createAssistantType`
- Route PUT/PATCH thay vì POST
- Hiển thị thông báo cập nhật thành công

**Chức năng:**
- Load dữ liệu hiện tại vào form
- Validate form
- Submit cập nhật
- Xử lý lỗi validation
- Redirect về danh sách sau khi cập nhật

### 2.3. Routes
**File:** `routes/web.php`

**Thêm routes:**
```php
Route::get('/admin/assistant-types/{typeId}/edit', [AdminController::class, 'editAssistantType'])->name('assistant-types.edit');
Route::put('/admin/assistant-types/{typeId}', [AdminController::class, 'updateAssistantType'])->name('assistant-types.update');
Route::patch('/admin/assistant-types/{typeId}', [AdminController::class, 'updateAssistantType']);
```

---

## 3. CHỨC NĂNG XÓA (DELETE)

### 3.1. Backend - Controller
**File:** `app/Http/Controllers/AdminController.php`

**Thêm method:**
```php
public function deleteAssistantType(Request $request, int $typeId)
{
    $assistantType = \App\Models\AssistantType::findOrFail($typeId);
    
    // Kiểm tra xem có Assistant nào đang sử dụng loại này không
    $assistantsCount = \App\Models\AiAssistant::where('assistant_type', $assistantType->code)->count();
    
    if ($assistantsCount > 0) {
        return response()->json([
            'error' => 'Không thể xóa loại Assistant này vì đang có ' . $assistantsCount . ' Assistant đang sử dụng.',
        ], 422);
    }
    
    $assistantType->delete();
    
    return response()->json([
        'message' => 'Loại Assistant đã được xóa thành công',
    ]);
}
```

### 3.2. Frontend - Integration
**File:** `resources/js/Pages/Admin/AssistantTypes.vue` (màn hình danh sách)

**Thêm button xóa trong danh sách:**
```vue
<button
    @click="deleteAssistantType(type.id)"
    class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200"
>
    Xóa
</button>
```

**Thêm method xóa:**
```javascript
const deleteAssistantType = async (typeId) => {
    if (!confirm('Bạn có chắc chắn muốn xóa loại Assistant này?')) return;
    
    try {
        await axios.delete(`/admin/assistant-types/${typeId}`);
        router.reload();
    } catch (error) {
        console.error('Error deleting assistant type:', error);
        const message = error.response?.data?.error || 'Không thể xóa loại Assistant. Vui lòng thử lại.';
        alert(message);
    }
};
```

### 3.3. Routes
**File:** `routes/web.php`

**Thêm route:**
```php
Route::delete('/admin/assistant-types/{typeId}', [\App\Http\Controllers\AdminController::class, 'deleteAssistantType'])->name('assistant-types.destroy');
```

---

## 4. DATABASE - MIGRATION & MODEL

### 4.1. Migration
**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_assistant_types_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Mã loại (ví dụ: qa_based_document)');
            $table->string('name', 255)->comment('Tên hiển thị');
            $table->text('description')->nullable()->comment('Mô tả chi tiết');
            $table->boolean('is_active')->default(true)->comment('Trạng thái hoạt động');
            $table->string('icon', 100)->nullable()->comment('Icon class hoặc emoji');
            $table->string('color', 7)->nullable()->comment('Màu sắc (hex code)');
            $table->integer('sort_order')->default(0)->comment('Thứ tự sắp xếp');
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_types');
    }
};
```

### 4.2. Model
**File:** `app/Models/AssistantType.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'icon',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all assistants of this type
     */
    public function assistants(): HasMany
    {
        return $this->hasMany(AiAssistant::class, 'assistant_type', 'code');
    }
}
```

---

## 5. MÀN HÌNH DANH SÁCH (INDEX) - BỔ SUNG

### 5.1. Backend - Controller
**File:** `app/Http/Controllers/Inertia/AdminController.php`

**Thêm method:**
```php
public function assistantTypes(Request $request): Response
{
    $assistantTypes = \App\Models\AssistantType::orderBy('sort_order')
        ->orderBy('name')
        ->get();
    
    return Inertia::render('Admin/AssistantTypes', [
        'assistantTypes' => $assistantTypes,
    ]);
}
```

### 5.2. Frontend - Vue Component
**File:** `resources/js/Pages/Admin/AssistantTypes.vue`

**Hiển thị:**
- Bảng danh sách các loại Assistant
- Cột: Mã, Tên, Mô tả, Trạng thái, Icon, Màu sắc, Thao tác
- Button "Tạo mới" ở header
- Button "Sửa" và "Xóa" cho mỗi item

### 5.3. Routes
**File:** `routes/web.php`

**Thêm route:**
```php
Route::get('/admin/assistant-types', [AdminController::class, 'assistantTypes'])->name('assistant-types.index');
```

---

## 6. TÍCH HỢP VỚI HỆ THỐNG HIỆN TẠI

### 6.1. Cập nhật AiAssistant Model
**File:** `app/Models/AiAssistant.php`

- Có thể thêm relationship với AssistantType model (nếu chuyển từ enum sang database)
- Hoặc giữ nguyên enum và chỉ dùng AssistantType model để quản lý metadata

### 6.2. Cập nhật CreateAssistant/EditAssistant
**File:** `resources/js/Pages/Admin/CreateAssistant.vue` và `EditAssistant.vue`

- Load danh sách loại Assistant từ API `/admin/assistant-types/list`
- Hiển thị dropdown với dữ liệu từ database thay vì hardcode

### 6.3. API Endpoint cho Dropdown
**File:** `app/Http/Controllers/AdminController.php`

```php
public function getAssistantTypesList(Request $request)
{
    $types = \App\Models\AssistantType::where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get(['id', 'code', 'name', 'description', 'icon', 'color']);
    
    return response()->json(['types' => $types]);
}
```

**Route:**
```php
Route::get('/admin/assistant-types/list', [\App\Http\Controllers\AdminController::class, 'getAssistantTypesList'])->name('assistant-types.list');
```

---

## 7. VALIDATION & SECURITY

### 7.1. Validation Rules
- Code: required, unique, max 50 chars, alphanumeric + underscore
- Name: required, max 255 chars
- Description: optional, text
- Is Active: boolean
- Icon: optional, max 100 chars
- Color: optional, hex color format (#RRGGBB)

### 7.2. Authorization
- Tất cả routes phải có middleware `EnsureAdmin`
- Kiểm tra quyền admin trước khi cho phép CRUD

### 7.3. Soft Delete (Tùy chọn)
- Có thể thêm soft delete nếu muốn giữ lại lịch sử
- Thêm `deleted_at` column vào migration

---

## 8. UI/UX CONSIDERATIONS

### 8.1. Form Design
- Sử dụng AdminLayout giống các màn hình khác
- Responsive design
- Validation messages rõ ràng
- Loading states khi submit

### 8.2. Color Picker
- Có thể dùng thư viện color picker hoặc input type="color"
- Preview màu sắc ngay trên form

### 8.3. Icon Picker (Tùy chọn)
- Có thể tích hợp icon picker (FontAwesome, Heroicons, etc.)
- Hoặc để text input cho icon class name

### 8.4. Confirmation Dialogs
- Xác nhận trước khi xóa
- Hiển thị cảnh báo nếu có Assistant đang sử dụng loại này

---

## 9. TESTING CHECKLIST

### 9.1. Create
- [ ] Tạo mới thành công với dữ liệu hợp lệ
- [ ] Validation khi thiếu required fields
- [ ] Validation khi code trùng
- [ ] Hiển thị thông báo lỗi rõ ràng
- [ ] Redirect về danh sách sau khi tạo thành công

### 9.2. Edit
- [ ] Load dữ liệu hiện tại vào form
- [ ] Cập nhật thành công
- [ ] Validation khi code trùng (trừ chính nó)
- [ ] Giữ nguyên giá trị khi validation fail

### 9.3. Delete
- [ ] Xóa thành công khi không có Assistant nào sử dụng
- [ ] Không cho xóa khi có Assistant đang sử dụng
- [ ] Hiển thị thông báo lỗi rõ ràng
- [ ] Xác nhận trước khi xóa

### 9.4. Integration
- [ ] Dropdown trong CreateAssistant hiển thị đúng danh sách
- [ ] Dropdown chỉ hiển thị loại active
- [ ] Sắp xếp theo sort_order

---

## 10. THỨ TỰ TRIỂN KHAI

1. **Bước 1:** Tạo Migration và Model
2. **Bước 2:** Tạo Controller methods (Backend)
3. **Bước 3:** Tạo Routes
4. **Bước 4:** Tạo Vue Components (Frontend)
5. **Bước 5:** Tích hợp với CreateAssistant/EditAssistant
6. **Bước 6:** Testing
7. **Bước 7:** Migration dữ liệu từ enum sang database (nếu cần)

---

## 11. LƯU Ý QUAN TRỌNG

1. **Migration dữ liệu:** Nếu muốn chuyển từ enum sang database, cần migration script để import các loại hiện có
2. **Backward compatibility:** Đảm bảo code cũ vẫn hoạt động nếu vẫn dùng enum
3. **Performance:** Index các trường thường query (code, is_active, sort_order)
4. **Cascade delete:** Quyết định xử lý như thế nào khi xóa loại đang được sử dụng (không cho xóa hoặc set null)

---

## 12. MENU BAR TRONG ADMIN LAYOUT

### 12.1. Cập nhật AdminLayout
**File:** `resources/js/Layouts/AdminLayout.vue`

**Đã thêm menu bar với 2 menu items:**
- **"➕ Tạo loại trợ lý"** → Link đến `/admin/assistant-types/create`
- **"📋 List loại trợ lý"** → Link đến `/admin/assistant-types`

**Tính năng:**
- Menu bar hiển thị ngay dưới header "Admin Panel"
- Active state: Menu item hiện tại sẽ có background màu xanh nhạt và border
- Hover effect: Màu nền xám nhạt khi hover
- Responsive design
- Sử dụng computed properties để kiểm tra active state chính xác

**Code đã thêm:**
```vue
<!-- Menu Bar -->
<div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="flex items-center space-x-1 p-2">
        <Link
            href="/admin/assistant-types/create"
            class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
            :class="isCreatePage 
                ? 'bg-blue-50 text-blue-700 border border-blue-200' 
                : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'"
        >
            ➕ Tạo loại trợ lý
        </Link>
        <Link
            href="/admin/assistant-types"
            class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
            :class="isListPage 
                ? 'bg-blue-50 text-blue-700 border border-blue-200' 
                : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'"
        >
            📋 List loại trợ lý
        </Link>
    </div>
</div>
```

**Script:**
```javascript
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const isCreatePage = computed(() => {
    return page.url === '/admin/assistant-types/create';
});

const isListPage = computed(() => {
    const url = page.url;
    return url === '/admin/assistant-types' || 
           (url.startsWith('/admin/assistant-types/') && url !== '/admin/assistant-types/create');
});
```

### 12.2. Lưu ý
- Menu bar sẽ hiển thị trên tất cả các trang admin (vì nằm trong AdminLayout)
- Active state tự động cập nhật dựa trên URL hiện tại
- Có thể mở rộng thêm menu items khác trong tương lai

---

## 13. FILES CẦN TẠO/SỬA

### Files mới:
- `database/migrations/YYYY_MM_DD_HHMMSS_create_assistant_types_table.php`
- `app/Models/AssistantType.php`
- `resources/js/Pages/Admin/AssistantTypes.vue`
- `resources/js/Pages/Admin/CreateAssistantType.vue`
- `resources/js/Pages/Admin/EditAssistantType.vue`

### Files cần sửa:
- `routes/web.php` - Thêm routes
- `app/Http/Controllers/Inertia/AdminController.php` - Thêm methods render
- `app/Http/Controllers/AdminController.php` - Thêm methods xử lý CRUD
- `resources/js/Pages/Admin/CreateAssistant.vue` - Load types từ API
- `resources/js/Pages/Admin/EditAssistant.vue` - Load types từ API
- `resources/js/Layouts/AdminLayout.vue` - ✅ **ĐÃ THÊM MENU BAR** (hoàn thành)

---

**Kết thúc phương án**

