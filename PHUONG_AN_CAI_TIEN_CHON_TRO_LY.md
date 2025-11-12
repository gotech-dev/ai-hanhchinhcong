# Phương Án Cải Tiến UI/UX - Phần Chọn Trợ Lý

## 📋 Tổng Quan Vấn Đề

### Hiện Trạng
- **Giao diện hiện tại**: Dropdown đơn giản (`<select>`) với danh sách tất cả trợ lý
- **Vấn đề**: Khi có nhiều trợ lý (10+), việc tìm kiếm và chọn trợ lý trở nên bất tiện:
  - Phải scroll qua nhiều options
  - Không có chức năng tìm kiếm
  - Không có phân loại/nhóm theo loại trợ lý
  - Không hiển thị mô tả hoặc avatar để nhận diện
  - Trải nghiệm trên mobile kém

### Dữ Liệu Hiện Có
Từ code hiện tại, mỗi trợ lý có:
- `id`: ID duy nhất
- `name`: Tên trợ lý
- `description`: Mô tả (có thể null)
- `assistant_type`: Loại trợ lý (`document_drafting`, `qa_based_document`, `document_management`)
- `avatar_url`: URL avatar (có thể null)
- `is_active`: Trạng thái hoạt động

---

## 🎯 Mục Tiêu Cải Tiến

1. **Tìm kiếm nhanh**: Người dùng có thể tìm trợ lý bằng tên hoặc mô tả
2. **Phân loại rõ ràng**: Nhóm trợ lý theo loại để dễ định hướng
3. **Hiển thị thông tin đầy đủ**: Avatar, mô tả giúp nhận diện tốt hơn
4. **Responsive**: Hoạt động tốt trên cả desktop và mobile
5. **Trải nghiệm mượt mà**: Tương tác tự nhiên, không gây khó chịu

---

## 💡 Phương Án Đề Xuất

### Phương Án 1: Searchable Dropdown với Autocomplete ⭐ (Khuyến nghị)

**Mô tả**: Nâng cấp dropdown hiện tại thành searchable dropdown với khả năng tìm kiếm real-time.

**Ưu điểm**:
- ✅ Giữ nguyên vị trí UI, không thay đổi layout lớn
- ✅ Dễ implement, sử dụng thư viện có sẵn (Headless UI, Vue Select)
- ✅ Tìm kiếm nhanh, gõ vài ký tự là có kết quả
- ✅ Hỗ trợ keyboard navigation tốt
- ✅ Responsive tốt

**Nhược điểm**:
- ⚠️ Vẫn là dropdown, không hiển thị được nhiều thông tin
- ⚠️ Khó hiển thị avatar trong dropdown

**Thiết kế**:
```
┌─────────────────────────────────────┐
│ 🔍 Tìm kiếm trợ lý...              │
├─────────────────────────────────────┤
│ 📄 Soạn thảo văn bản               │
│   └─ Trợ lý soạn thảo văn bản 1   │
│   └─ Trợ lý soạn thảo văn bản 2   │
│                                     │
│ ❓ Q&A                              │
│   └─ Trợ lý Q&A 1                  │
│   └─ Trợ lý Q&A 2                  │
│                                     │
│ 📁 Quản lý văn bản                 │
│   └─ Trợ lý quản lý 1              │
└─────────────────────────────────────┘
```

**Công nghệ**: Vue Select hoặc Headless UI Combobox

---

### Phương Án 2: Card Grid Layout với Search & Filter

**Mô tả**: Thay dropdown bằng grid layout hiển thị trợ lý dưới dạng cards, có thanh tìm kiếm và filter theo loại.

**Ưu điểm**:
- ✅ Hiển thị đầy đủ thông tin: avatar, tên, mô tả, loại
- ✅ Trực quan, dễ so sánh các trợ lý
- ✅ Có thể filter theo loại trợ lý
- ✅ Trải nghiệm tốt trên desktop

**Nhược điểm**:
- ⚠️ Chiếm nhiều không gian màn hình
- ⚠️ Trên mobile có thể cần scroll nhiều
- ⚠️ Thay đổi layout lớn hơn

**Thiết kế**:
```
┌─────────────────────────────────────────────────────────┐
│ 🔍 Tìm kiếm...                    [Filter: Tất cả ▼]  │
├─────────────────────────────────────────────────────────┤
│ ┌──────────┐  ┌──────────┐  ┌──────────┐              │
│ │  [Avatar]│  │  [Avatar]│  │  [Avatar]│              │
│ │  Tên 1   │  │  Tên 2   │  │  Tên 3   │              │
│ │  Mô tả...│  │  Mô tả...│  │  Mô tả...│              │
│ │  📄 Type │  │  ❓ Type  │  │  📁 Type │              │
│ └──────────┘  └──────────┘  └──────────┘              │
│                                                         │
│ ┌──────────┐  ┌──────────┐  ┌──────────┐              │
│ │  [Avatar]│  │  [Avatar]│  │  [Avatar]│              │
│ │  Tên 4   │  │  Tên 5   │  │  Tên 6   │              │
│ │  Mô tả...│  │  Mô tả...│  │  Mô tả...│              │
│ │  📄 Type │  │  ❓ Type  │  │  📁 Type │              │
│ └──────────┘  └──────────┘  └──────────┘              │
└─────────────────────────────────────────────────────────┘
```

**Công nghệ**: Vue 3 Composition API, Tailwind CSS Grid

---

### Phương Án 3: Modal với Tabs & Search

**Mô tả**: Khi click vào nút "Chọn trợ lý", mở modal với tabs theo loại trợ lý và thanh tìm kiếm.

**Ưu điểm**:
- ✅ Tập trung sự chú ý vào việc chọn trợ lý
- ✅ Phân loại rõ ràng bằng tabs
- ✅ Có thể hiển thị nhiều thông tin chi tiết
- ✅ Không làm thay đổi layout chính

**Nhược điểm**:
- ⚠️ Cần thêm một bước click để mở modal
- ⚠️ Có thể cảm thấy "nặng" nếu chỉ có vài trợ lý

**Thiết kế**:
```
┌─────────────────────────────────────────────────┐
│  Chọn trợ lý AI                          [X]   │
├─────────────────────────────────────────────────┤
│  🔍 Tìm kiếm...                                 │
├─────────────────────────────────────────────────┤
│  [Tất cả] [📄 Soạn thảo] [❓ Q&A] [📁 Quản lý] │
├─────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐            │
│  │   [Avatar]   │  │   [Avatar]   │            │
│  │   Tên trợ lý │  │   Tên trợ lý │            │
│  │   Mô tả chi  │  │   Mô tả chi  │            │
│  │   tiết...    │  │   tiết...    │            │
│  └──────────────┘  └──────────────┘            │
│                                                 │
│  ┌──────────────┐  ┌──────────────┐            │
│  │   [Avatar]   │  │   [Avatar]   │            │
│  │   Tên trợ lý │  │   Tên trợ lý │            │
│  │   Mô tả chi  │  │   Mô tả chi  │            │
│  │   tiết...    │  │   tiết...    │            │
│  └──────────────┘  └──────────────┘            │
└─────────────────────────────────────────────────┘
```

**Công nghệ**: Headless UI Dialog, Vue Tabs

---

### Phương Án 4: Hybrid - Smart Dropdown với Preview

**Mô tả**: Kết hợp dropdown searchable với preview card khi hover/select.

**Ưu điểm**:
- ✅ Giữ nguyên vị trí UI
- ✅ Có thể xem thông tin chi tiết khi cần
- ✅ Tìm kiếm nhanh
- ✅ Không chiếm nhiều không gian

**Nhược điểm**:
- ⚠️ Phức tạp hơn về implementation
- ⚠️ Trên mobile không có hover

**Thiết kế**:
```
┌─────────────────────────────────────┐
│ 🔍 Tìm kiếm trợ lý...              │
├─────────────────────────────────────┤
│ 📄 Soạn thảo văn bản               │
│   └─ Trợ lý soạn thảo văn bản 1   │
│   └─ Trợ lý soạn thảo văn bản 2   │
└─────────────────────────────────────┘
         │
         ▼ (khi hover/select)
┌─────────────────────────────────────┐
│  [Avatar]  Trợ lý soạn thảo văn bản│
│  Mô tả chi tiết về trợ lý này...   │
│  📄 Loại: Soạn thảo văn bản        │
│  [Chọn trợ lý này]                  │
└─────────────────────────────────────┘
```

---

## 🏆 Phương Án Được Khuyến Nghị

### **Phương Án 1: Searchable Dropdown với Autocomplete** ⭐

**Lý do**:
1. **Cân bằng tốt**: Giữ nguyên vị trí UI, không thay đổi layout lớn nhưng cải thiện đáng kể UX
2. **Dễ implement**: Có thể sử dụng thư viện có sẵn như `@headlessui/vue` hoặc `vue-select`
3. **Hiệu quả**: Giải quyết vấn đề chính là tìm kiếm khi có nhiều trợ lý
4. **Responsive**: Hoạt động tốt trên cả desktop và mobile
5. **Familiar**: Người dùng quen thuộc với pattern này

### Nếu cần hiển thị nhiều thông tin hơn → **Phương Án 2: Card Grid Layout**

**Khi nào nên dùng**:
- Có nhiều trợ lý (20+)
- Cần hiển thị mô tả chi tiết
- Người dùng cần so sánh trợ lý trước khi chọn
- Có avatar và thông tin phong phú

---

## 📐 Chi Tiết Implementation - Phương Án 1

### Component Structure

```vue
<template>
  <div class="w-full max-w-3xl mx-auto px-4">
    <label class="block text-sm font-medium text-gray-700 mb-2">
      Chọn trợ lý
    </label>
    
    <!-- Searchable Dropdown -->
    <Combobox v-model="selectedAssistantId" @update:modelValue="onAssistantChange">
      <div class="relative">
        <ComboboxInput
          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-gray-900"
          :display-value="(assistant) => assistant?.name || '-- Chọn trợ lý --'"
          @change="query = $event.target.value"
          placeholder="Tìm kiếm trợ lý..."
        />
        <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
          <ChevronUpDownIcon class="h-5 w-5 text-gray-400" />
        </ComboboxButton>
      </div>
      
      <ComboboxOptions class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
        <!-- Group by type -->
        <div v-for="(group, type) in groupedAssistants" :key="type">
          <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50">
            {{ getTypeLabel(type) }}
          </div>
          <ComboboxOption
            v-for="assistant in group"
            :key="assistant.id"
            :value="assistant.id"
            v-slot="{ active, selected }"
          >
            <div
              :class="[
                'relative cursor-pointer select-none py-2 pl-10 pr-4',
                active ? 'bg-blue-50 text-blue-900' : 'text-gray-900'
              ]"
            >
              <span :class="['block truncate', selected ? 'font-medium' : 'font-normal']">
                {{ assistant.name }}
              </span>
              <span v-if="assistant.description" class="block text-xs text-gray-500 truncate">
                {{ assistant.description }}
              </span>
            </div>
          </ComboboxOption>
        </div>
      </ComboboxOptions>
    </Combobox>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue'
import { ChevronUpDownIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  assistants: Array
})

const selectedAssistantId = ref(null)
const query = ref('')

const filteredAssistants = computed(() => {
  if (!query.value) return props.assistants || []
  
  return (props.assistants || []).filter(assistant => {
    const searchTerm = query.value.toLowerCase()
    return (
      assistant.name.toLowerCase().includes(searchTerm) ||
      (assistant.description && assistant.description.toLowerCase().includes(searchTerm))
    )
  })
})

const groupedAssistants = computed(() => {
  const groups = {}
  filteredAssistants.value.forEach(assistant => {
    const type = assistant.assistant_type || 'other'
    if (!groups[type]) groups[type] = []
    groups[type].push(assistant)
  })
  return groups
})

const getTypeLabel = (type) => {
  const labels = {
    'document_drafting': '📄 Soạn thảo văn bản',
    'qa_based_document': '❓ Q&A từ tài liệu',
    'document_management': '📁 Quản lý văn bản'
  }
  return labels[type] || 'Trợ lý khác'
})
</script>
```

### Dependencies

```json
{
  "@headlessui/vue": "^1.7.16",
  "@heroicons/vue": "^2.0.18"
}
```

---

## 📐 Chi Tiết Implementation - Phương Án 2

### Component Structure

```vue
<template>
  <div class="w-full max-w-6xl mx-auto px-4">
    <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">
      Hãy chọn trợ lý và bắt đầu cuộc trò chuyện nhé.
    </h1>
    
    <!-- Search & Filter Bar -->
    <div class="mb-6 space-y-4">
      <div class="relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="🔍 Tìm kiếm trợ lý..."
          class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <svg class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      
      <!-- Filter Chips -->
      <div class="flex flex-wrap gap-2">
        <button
          v-for="type in assistantTypes"
          :key="type.value"
          @click="selectedType = selectedType === type.value ? null : type.value"
          :class="[
            'px-4 py-2 rounded-full text-sm font-medium transition-colors',
            selectedType === type.value
              ? 'bg-blue-500 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          ]"
        >
          {{ type.label }}
        </button>
      </div>
    </div>
    
    <!-- Assistant Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="assistant in filteredAssistants"
        :key="assistant.id"
        @click="selectAssistant(assistant.id)"
        class="bg-white border-2 border-gray-200 rounded-lg p-6 cursor-pointer hover:border-blue-500 hover:shadow-lg transition-all"
      >
        <!-- Avatar -->
        <div class="flex items-center mb-4">
          <img
            v-if="assistant.avatar_url"
            :src="assistant.avatar_url"
            :alt="assistant.name"
            class="w-12 h-12 rounded-full mr-3"
          />
          <div v-else class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-3">
            <span class="text-blue-600 text-xl font-semibold">
              {{ assistant.name.charAt(0).toUpperCase() }}
            </span>
          </div>
          <div>
            <h3 class="font-semibold text-gray-900">{{ assistant.name }}</h3>
            <span class="text-xs text-gray-500">{{ getTypeLabel(assistant.assistant_type) }}</span>
          </div>
        </div>
        
        <!-- Description -->
        <p v-if="assistant.description" class="text-sm text-gray-600 line-clamp-2 mb-4">
          {{ assistant.description }}
        </p>
        
        <!-- Select Button -->
        <button class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition-colors">
          Chọn trợ lý này
        </button>
      </div>
    </div>
    
    <!-- Empty State -->
    <div v-if="filteredAssistants.length === 0" class="text-center py-12">
      <p class="text-gray-500">Không tìm thấy trợ lý nào phù hợp.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  assistants: Array
})

const searchQuery = ref('')
const selectedType = ref(null)

const assistantTypes = [
  { value: 'document_drafting', label: '📄 Soạn thảo văn bản' },
  { value: 'qa_based_document', label: '❓ Q&A từ tài liệu' },
  { value: 'document_management', label: '📁 Quản lý văn bản' }
]

const filteredAssistants = computed(() => {
  let result = props.assistants || []
  
  // Filter by type
  if (selectedType.value) {
    result = result.filter(a => a.assistant_type === selectedType.value)
  }
  
  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(assistant => {
      return (
        assistant.name.toLowerCase().includes(query) ||
        (assistant.description && assistant.description.toLowerCase().includes(query))
      )
    })
  }
  
  return result
})

const getTypeLabel = (type) => {
  const typeObj = assistantTypes.find(t => t.value === type)
  return typeObj ? typeObj.label : 'Trợ lý khác'
}

const selectAssistant = (id) => {
  // Emit event or call parent method
  emit('assistant-selected', id)
}
</script>
```

---

## 📱 Responsive Design Considerations

### Mobile (< 768px)
- **Phương án 1**: Dropdown full-width, danh sách scrollable
- **Phương án 2**: Grid 1 cột, cards stack vertically
- Search bar luôn ở trên cùng
- Filter chips có thể scroll ngang

### Tablet (768px - 1024px)
- **Phương án 1**: Dropdown giữ nguyên
- **Phương án 2**: Grid 2 cột

### Desktop (> 1024px)
- **Phương án 1**: Dropdown giữ nguyên
- **Phương án 2**: Grid 3 cột

---

## 🎨 Design Tokens

### Colors
- Primary: `#3B82F6` (blue-500)
- Hover: `#2563EB` (blue-600)
- Background: `#FFFFFF`
- Border: `#E5E7EB` (gray-200)
- Text: `#111827` (gray-900)
- Text Secondary: `#6B7280` (gray-500)

### Spacing
- Card padding: `1.5rem` (24px)
- Grid gap: `1rem` (16px)
- Search bar padding: `0.75rem 1rem` (12px 16px)

### Typography
- Heading: `text-2xl font-semibold`
- Card title: `font-semibold text-gray-900`
- Description: `text-sm text-gray-600`

---

## ✅ Checklist Implementation

### Phase 1: Phương án 1 (Searchable Dropdown)
- [ ] Cài đặt dependencies (`@headlessui/vue`, `@heroicons/vue`)
- [ ] Tạo component `AssistantSelector.vue`
- [ ] Implement search functionality
- [ ] Implement grouping by type
- [ ] Add keyboard navigation
- [ ] Test trên mobile
- [ ] Test với nhiều trợ lý (20+)

### Phase 2: Nâng cấp (nếu cần)
- [ ] Thêm avatar vào dropdown options
- [ ] Thêm preview card khi hover
- [ ] Thêm recent/favorite assistants
- [ ] Thêm analytics tracking

---

## 📊 So Sánh Các Phương Án

| Tiêu chí | Phương án 1 | Phương án 2 | Phương án 3 | Phương án 4 |
|----------|-------------|-------------|-------------|-------------|
| **Dễ implement** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| **Tìm kiếm nhanh** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Hiển thị thông tin** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ |
| **Responsive** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Không gian màn hình** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **User familiarity** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |

---

## 🚀 Kết Luận

**Khuyến nghị triển khai**: Bắt đầu với **Phương án 1 (Searchable Dropdown)** vì:
1. Cân bằng tốt giữa effort và impact
2. Giải quyết vấn đề chính (tìm kiếm khi có nhiều trợ lý)
3. Dễ maintain và mở rộng
4. Trải nghiệm quen thuộc với người dùng

**Nếu sau này cần hiển thị nhiều thông tin hơn**, có thể nâng cấp lên **Phương án 2 (Card Grid)** hoặc kết hợp cả hai (dropdown compact + modal với cards khi cần).

---

*Tài liệu này được tạo để hỗ trợ quyết định cải tiến UI/UX cho phần chọn trợ lý trong ứng dụng AI Hành chính công.*

---

# Phương Án Cải Tiến: Quản Lý Steps (Các Bước) Cho Trợ Lý

## 📋 Tổng Quan Vấn Đề

### Hiện Trạng
- **Workflow hiện tại**: WorkflowPlanner tự động tạo workflow dựa trên intent và assistant config
- **Vấn đề**: 
  - Không có cách để admin định nghĩa trước các bước (steps) cụ thể cho từng trợ lý
  - Workflow được tạo động mỗi lần, không nhất quán
  - Khó kiểm soát quy trình làm việc của trợ lý
  - Ví dụ: Trợ lý "Viết sách" cần có các bước: 1) Thu thập ý tưởng, 2) Lập dàn ý, 3) Viết chương 1, 4) Viết chương 2, ... nhưng hiện tại không có cách định nghĩa

### Yêu Cầu
1. **Admin có thể định nghĩa steps**: Khi tạo/sửa trợ lý, admin có thể tạo các steps cụ thể
2. **AI tự động phân tích**: Dựa trên mô tả trợ lý, AI sẽ tự động đề xuất các steps phù hợp
3. **Admin chỉnh sửa được**: Admin có thể thêm, bớt, sửa, sắp xếp lại các steps
4. **Chatbot chạy tuần tự**: Phía user, chatbot sẽ chạy tuần tự các steps (không hiển thị steps ra user)
5. **Linh hoạt**: Steps có thể có dependencies, điều kiện, và các loại khác nhau

---

## 🎯 Mục Tiêu

1. **Định nghĩa workflow rõ ràng**: Mỗi trợ lý có các steps được định nghĩa trước
2. **Tự động hóa thông minh**: AI phân tích và đề xuất steps phù hợp
3. **Kiểm soát tốt**: Admin có toàn quyền chỉnh sửa steps
4. **Trải nghiệm mượt mà**: User không thấy steps, chỉ thấy kết quả cuối cùng
5. **Linh hoạt và mở rộng**: Dễ dàng thêm các loại steps mới

---

## 💡 Phương Án Đề Xuất

### Cấu Trúc Dữ Liệu Steps

Steps sẽ được lưu trong field `config` của bảng `ai_assistants`:

```json
{
  "model": "gpt-4o-mini",
  "template_fields": [...],
  "steps": [
    {
      "id": "step_1",
      "order": 1,
      "name": "Thu thập thông tin cơ bản",
      "description": "Hỏi user về tiêu đề, mục đích, đối tượng đọc",
      "type": "collect_info",
      "action": "ask_questions",
      "required": true,
      "dependencies": [],
      "config": {
        "questions": [
          "Tiêu đề cuốn sách là gì?",
          "Mục đích viết sách là gì?",
          "Đối tượng đọc giả là ai?"
        ]
      }
    },
    {
      "id": "step_2",
      "order": 2,
      "name": "Lập dàn ý",
      "description": "Tạo dàn ý chi tiết cho cuốn sách",
      "type": "generate",
      "action": "create_outline",
      "required": true,
      "dependencies": ["step_1"],
      "config": {
        "format": "markdown",
        "include_chapters": true
      }
    },
    {
      "id": "step_3",
      "order": 3,
      "name": "Viết chương 1",
      "description": "Viết nội dung chương đầu tiên",
      "type": "generate",
      "action": "write_chapter",
      "required": true,
      "dependencies": ["step_2"],
      "config": {
        "chapter_number": 1,
        "min_words": 1000
      }
    }
  ]
}
```

### Các Loại Steps

1. **collect_info**: Thu thập thông tin từ user
   - Ví dụ: Hỏi tên, email, yêu cầu cụ thể
   - Config: `questions`, `fields`, `validation_rules`

2. **generate**: Tạo nội dung bằng AI
   - Ví dụ: Viết chương, tạo báo cáo, soạn thảo văn bản
   - Config: `prompt_template`, `format`, `length`

3. **search**: Tìm kiếm thông tin
   - Ví dụ: Tìm trong documents, tìm kiếm semantic
   - Config: `search_query`, `sources`, `max_results`

4. **process**: Xử lý dữ liệu
   - Ví dụ: Phân tích, tính toán, chuyển đổi format
   - Config: `processor_type`, `input_fields`, `output_fields`

5. **validate**: Kiểm tra và xác thực
   - Ví dụ: Kiểm tra format, validate dữ liệu
   - Config: `validation_rules`, `error_messages`

6. **conditional**: Điều kiện rẽ nhánh
   - Ví dụ: Nếu có đủ thông tin thì bỏ qua bước thu thập
   - Config: `condition`, `if_true`, `if_false`

---

## 📐 Chi Tiết Implementation

### Phần 1: Admin - Tạo/Sửa Steps

#### 1.1. UI Component - Steps Manager

**Vị trí**: Thêm vào form `/admin/assistants/create` và `/admin/assistants/{id}/edit`

**Thiết kế**:
```
┌─────────────────────────────────────────────────────────┐
│  Quản lý Steps (Các bước)                               │
├─────────────────────────────────────────────────────────┤
│  [🤖 Tự động tạo Steps bằng AI]                         │
│                                                         │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Step 1: Thu thập thông tin cơ bản        [↑] [↓] │ │
│  │ Type: collect_info | Required: ✓          [✎] [✕]│ │
│  │ Dependencies: Không                            │ │
│  │ └─ Hỏi user về tiêu đề, mục đích...            │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Step 2: Lập dàn ý                        [↑] [↓] │ │
│  │ Type: generate | Required: ✓              [✎] [✕]│ │
│  │ Dependencies: Step 1                            │ │
│  │ └─ Tạo dàn ý chi tiết cho cuốn sách            │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  [+ Thêm Step Mới]                                     │
└─────────────────────────────────────────────────────────┘
```

#### 1.2. Component Vue: `AssistantStepsManager.vue`

```vue
<template>
  <div class="steps-manager">
    <div class="mb-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-900">Quản lý Steps</h3>
      <button
        @click="generateStepsWithAI"
        :disabled="isGeneratingSteps"
        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50"
      >
        <span v-if="isGeneratingSteps">Đang tạo...</span>
        <span v-else>🤖 Tự động tạo Steps bằng AI</span>
      </button>
    </div>

    <!-- Steps List -->
    <div class="space-y-3">
      <div
        v-for="(step, index) in steps"
        :key="step.id"
        class="border border-gray-300 rounded-lg p-4 bg-white"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-sm font-medium text-gray-500">Step {{ index + 1 }}</span>
              <input
                v-model="step.name"
                type="text"
                placeholder="Tên step..."
                class="flex-1 px-2 py-1 border border-gray-300 rounded text-sm"
              />
            </div>
            <textarea
              v-model="step.description"
              rows="2"
              placeholder="Mô tả step..."
              class="w-full px-2 py-1 border border-gray-300 rounded text-sm mb-2"
            />
            <div class="flex items-center gap-4 text-xs text-gray-600">
              <select v-model="step.type" class="px-2 py-1 border rounded">
                <option value="collect_info">Thu thập thông tin</option>
                <option value="generate">Tạo nội dung</option>
                <option value="search">Tìm kiếm</option>
                <option value="process">Xử lý</option>
                <option value="validate">Kiểm tra</option>
                <option value="conditional">Điều kiện</option>
              </select>
              <label class="flex items-center gap-1">
                <input v-model="step.required" type="checkbox" />
                Bắt buộc
              </label>
            </div>
          </div>
          <div class="flex gap-1 ml-2">
            <button
              @click="moveStep(index, 'up')"
              :disabled="index === 0"
              class="p-1 text-gray-600 hover:text-gray-900 disabled:opacity-30"
            >
              ↑
            </button>
            <button
              @click="moveStep(index, 'down')"
              :disabled="index === steps.length - 1"
              class="p-1 text-gray-600 hover:text-gray-900 disabled:opacity-30"
            >
              ↓
            </button>
            <button
              @click="editStep(index)"
              class="p-1 text-blue-600 hover:text-blue-800"
            >
              ✎
            </button>
            <button
              @click="removeStep(index)"
              class="p-1 text-red-600 hover:text-red-800"
            >
              ✕
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Step Button -->
    <button
      @click="addStep"
      class="mt-4 px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-blue-500 hover:text-blue-500"
    >
      + Thêm Step Mới
    </button>

    <!-- Step Editor Modal -->
    <StepEditorModal
      v-if="editingStepIndex !== null"
      :step="steps[editingStepIndex]"
      @save="saveStep"
      @close="editingStepIndex = null"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import StepEditorModal from './StepEditorModal.vue';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  assistantName: String,
  assistantDescription: String,
  assistantType: String
});

const emit = defineEmits(['update:modelValue']);

const steps = ref([...props.modelValue]);
const isGeneratingSteps = ref(false);
const editingStepIndex = ref(null);

watch(steps, (newSteps) => {
  emit('update:modelValue', newSteps);
}, { deep: true });

const generateStepsWithAI = async () => {
  isGeneratingSteps.value = true;
  try {
    const response = await fetch('/admin/assistants/generate-steps', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        name: props.assistantName,
        description: props.assistantDescription,
        type: props.assistantType
      })
    });
    const data = await response.json();
    if (data.steps) {
      steps.value = data.steps;
    }
  } catch (error) {
    console.error('Error generating steps:', error);
    alert('Không thể tạo steps tự động. Vui lòng thử lại.');
  } finally {
    isGeneratingSteps.value = false;
  }
};

const addStep = () => {
  const newStep = {
    id: `step_${Date.now()}`,
    order: steps.value.length + 1,
    name: '',
    description: '',
    type: 'collect_info',
    action: '',
    required: true,
    dependencies: [],
    config: {}
  };
  steps.value.push(newStep);
};

const removeStep = (index) => {
  if (confirm('Bạn có chắc muốn xóa step này?')) {
    steps.value.splice(index, 1);
    // Update order
    steps.value.forEach((step, i) => {
      step.order = i + 1;
    });
  }
};

const moveStep = (index, direction) => {
  if (direction === 'up' && index > 0) {
    [steps.value[index], steps.value[index - 1]] = [steps.value[index - 1], steps.value[index]];
    steps.value[index].order = index + 1;
    steps.value[index - 1].order = index;
  } else if (direction === 'down' && index < steps.value.length - 1) {
    [steps.value[index], steps.value[index + 1]] = [steps.value[index + 1], steps.value[index]];
    steps.value[index].order = index + 1;
    steps.value[index + 1].order = index + 2;
  }
};

const editStep = (index) => {
  editingStepIndex.value = index;
};

const saveStep = (updatedStep) => {
  if (editingStepIndex.value !== null) {
    steps.value[editingStepIndex.value] = updatedStep;
    editingStepIndex.value = null;
  }
};
</script>
```

#### 1.3. Backend API - Generate Steps với AI

**Route**: `POST /admin/assistants/generate-steps`

**Controller Method**:

```php
public function generateSteps(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'description' => 'nullable|string',
        'type' => 'required|string',
    ]);

    try {
        $prompt = $this->buildStepsGenerationPrompt(
            $request->name,
            $request->description,
            $request->type
        );

        $response = OpenAI::chat()->create([
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Bạn là một AI chuyên phân tích và tạo workflow steps cho trợ lý AI. Phân tích mô tả trợ lý và tạo các steps phù hợp. Trả về JSON với format: {"steps": [...]}',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content;
        $result = json_decode($content, true);

        if (!$result || !isset($result['steps'])) {
            throw new \Exception('Invalid steps response');
        }

        // Format steps với id và order
        $formattedSteps = [];
        foreach ($result['steps'] as $index => $step) {
            $formattedSteps[] = [
                'id' => $step['id'] ?? "step_" . ($index + 1),
                'order' => $index + 1,
                'name' => $step['name'] ?? '',
                'description' => $step['description'] ?? '',
                'type' => $step['type'] ?? 'process',
                'action' => $step['action'] ?? '',
                'required' => $step['required'] ?? true,
                'dependencies' => $step['dependencies'] ?? [],
                'config' => $step['config'] ?? [],
            ];
        }

        return response()->json(['steps' => $formattedSteps]);
    } catch (\Exception $e) {
        Log::error('Generate steps error', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'Không thể tạo steps tự động',
        ], 500);
    }
}

protected function buildStepsGenerationPrompt($name, $description, $type): string
{
    return "Phân tích trợ lý AI sau và tạo các steps (bước) phù hợp:

Tên trợ lý: {$name}
Mô tả: {$description}
Loại: {$type}

Ví dụ: Nếu là trợ lý 'Viết sách', các steps có thể là:
1. Thu thập thông tin: Tiêu đề, mục đích, đối tượng đọc
2. Lập dàn ý: Tạo dàn ý chi tiết
3. Viết chương 1: Viết nội dung chương đầu
4. Viết chương 2: Viết nội dung chương tiếp theo
...

Trả về JSON với format:
{
  \"steps\": [
    {
      \"id\": \"step_1\",
      \"name\": \"Tên step\",
      \"description\": \"Mô tả step\",
      \"type\": \"collect_info|generate|search|process|validate|conditional\",
      \"action\": \"tên_action\",
      \"required\": true,
      \"dependencies\": [],
      \"config\": {}
    }
  ]
}";
}
```

#### 1.4. Cập nhật CreateAssistant.vue

Thêm component `AssistantStepsManager` vào form:

```vue
<!-- Thêm sau phần Document Management Info -->
<AssistantStepsManager
  v-model="form.steps"
  :assistant-name="form.name"
  :assistant-description="form.description"
  :assistant-type="form.assistant_type"
/>
```

Cập nhật form data:

```javascript
const form = ref({
  name: '',
  description: '',
  assistant_type: 'document_drafting',
  steps: [], // Thêm field steps
});
```

#### 1.5. Cập nhật AdminController - Lưu Steps

```php
public function createAssistant(Request $request)
{
    // ... existing validation ...

    $data = $validator->validated();

    DB::beginTransaction();
    try {
        $config = [
            'model' => $data['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
        ];

        // Thêm steps vào config nếu có
        if ($request->has('steps') && is_array($request->steps)) {
            $config['steps'] = $this->formatSteps($request->steps);
        }

        $assistant = AiAssistant::create([
            'admin_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'assistant_type' => $data['assistant_type'],
            'config' => $config,
            'is_active' => true,
        ]);

        // ... rest of the code ...
    }
}

protected function formatSteps(array $steps): array
{
    return array_map(function ($step, $index) {
        return [
            'id' => $step['id'] ?? "step_" . ($index + 1),
            'order' => $step['order'] ?? ($index + 1),
            'name' => $step['name'] ?? '',
            'description' => $step['description'] ?? '',
            'type' => $step['type'] ?? 'process',
            'action' => $step['action'] ?? '',
            'required' => $step['required'] ?? true,
            'dependencies' => $step['dependencies'] ?? [],
            'config' => $step['config'] ?? [],
        ];
    }, $steps, array_keys($steps));
}
```

---

### Phần 2: User/Chatbot - Chạy Steps Tuần Tự

#### 2.1. Cập nhật WorkflowPlanner - Sử dụng Steps từ Config

```php
public function plan(array $intent, AiAssistant $assistant, array $currentData = []): array
{
    // ✅ MỚI: Nếu assistant có steps được định nghĩa, sử dụng chúng
    $config = $assistant->config ?? [];
    $predefinedSteps = $config['steps'] ?? null;

    if ($predefinedSteps && !empty($predefinedSteps)) {
        return $this->planWithPredefinedSteps($predefinedSteps, $intent, $currentData);
    }

    // Fallback về logic cũ nếu không có steps
    // ... existing code ...
}

protected function planWithPredefinedSteps(array $steps, array $intent, array $currentData): array
{
    // Sắp xếp steps theo order
    usort($steps, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    // Lọc steps dựa trên dependencies và collected data
    $filteredSteps = [];
    $completedStepIds = [];

    foreach ($steps as $step) {
        // Kiểm tra dependencies
        $dependencies = $step['dependencies'] ?? [];
        $canExecute = true;
        foreach ($dependencies as $depId) {
            if (!in_array($depId, $completedStepIds)) {
                $canExecute = false;
                break;
            }
        }

        // Kiểm tra điều kiện (nếu là conditional step)
        if ($step['type'] === 'conditional') {
            $condition = $step['config']['condition'] ?? null;
            if ($condition && !$this->evaluateCondition($condition, $currentData)) {
                continue; // Bỏ qua step này
            }
        }

        if ($canExecute) {
            $filteredSteps[] = $step;
        }
    }

    return [
        'steps' => $filteredSteps,
        'estimated_time' => count($filteredSteps) * 30, // 30 seconds per step
    ];
}

protected function evaluateCondition(string $condition, array $data): bool
{
    // Đơn giản hóa: kiểm tra xem field có tồn tại và có giá trị không
    // Có thể mở rộng với expression parser phức tạp hơn
    if (preg_match('/has\((.+)\)/', $condition, $matches)) {
        $field = $matches[1];
        return isset($data[$field]) && !empty($data[$field]);
    }
    return true;
}
```

#### 2.2. Cập nhật SmartAssistantEngine - Thực Thi Steps

```php
public function processMessage(string $userMessage, ChatSession $session, AiAssistant $assistant): array
{
    try {
        $context = [
            'session' => $session,
            'assistant' => $assistant,
            'collected_data' => $session->collected_data ?? [],
            'workflow_state' => $session->workflow_state ?? null,
        ];

        $intent = $this->intentRecognizer->recognize($userMessage, $context);
        $workflow = $this->workflowPlanner->plan($intent, $assistant, $context);

        // ✅ MỚI: Nếu có steps được định nghĩa, thực thi tuần tự
        $config = $assistant->config ?? [];
        $predefinedSteps = $config['steps'] ?? null;

        if ($predefinedSteps && !empty($predefinedSteps)) {
            return $this->executePredefinedSteps($predefinedSteps, $userMessage, $session, $assistant, $intent, $workflow);
        }

        // Fallback về logic cũ
        // ... existing code ...
    }
}

protected function executePredefinedSteps(
    array $steps,
    string $userMessage,
    ChatSession $session,
    AiAssistant $assistant,
    array $intent,
    array $workflow
): array {
    $collectedData = $session->collected_data ?? [];
    $workflowState = $session->workflow_state ?? [];
    $currentStepIndex = $workflowState['current_step_index'] ?? 0;

    // Sắp xếp steps theo order
    usort($steps, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    // Lấy step hiện tại
    if ($currentStepIndex >= count($steps)) {
        // Đã hoàn thành tất cả steps
        return [
            'response' => 'Đã hoàn thành tất cả các bước. Có cần tôi làm gì thêm không?',
            'workflow_state' => null,
        ];
    }

    $currentStep = $steps[$currentStepIndex];
    $stepType = $currentStep['type'] ?? 'process';
    $stepAction = $currentStep['action'] ?? '';

    // Thực thi step dựa trên type
    $result = match ($stepType) {
        'collect_info' => $this->executeCollectInfoStep($currentStep, $userMessage, $collectedData),
        'generate' => $this->executeGenerateStep($currentStep, $userMessage, $collectedData, $assistant),
        'search' => $this->executeSearchStep($currentStep, $userMessage, $collectedData, $assistant),
        'process' => $this->executeProcessStep($currentStep, $userMessage, $collectedData),
        'validate' => $this->executeValidateStep($currentStep, $collectedData),
        'conditional' => $this->executeConditionalStep($currentStep, $collectedData),
        default => ['response' => 'Không thể xử lý step này.', 'completed' => false],
    };

    // Cập nhật collected_data và workflow_state
    if (isset($result['data'])) {
        $collectedData = array_merge($collectedData, $result['data']);
    }

    $nextStepIndex = $result['completed'] ? $currentStepIndex + 1 : $currentStepIndex;
    $workflowState['current_step_index'] = $nextStepIndex;
    $workflowState['completed_steps'] = $workflowState['completed_steps'] ?? [];
    if ($result['completed']) {
        $workflowState['completed_steps'][] = $currentStep['id'];
    }

    // Lưu vào session
    $session->collected_data = $collectedData;
    $session->workflow_state = $workflowState;
    $session->save();

    return [
        'response' => $result['response'],
        'workflow_state' => $workflowState,
    ];
}

protected function executeCollectInfoStep(array $step, string $userMessage, array $collectedData): array
{
    $config = $step['config'] ?? [];
    $questions = $config['questions'] ?? [];
    $fields = $config['fields'] ?? [];

    // Nếu có questions, hỏi từng câu một
    if (!empty($questions)) {
        $askedQuestions = $collectedData['_asked_questions'] ?? [];
        $nextQuestionIndex = count($askedQuestions);

        if ($nextQuestionIndex < count($questions)) {
            $nextQuestion = $questions[$nextQuestionIndex];
            $askedQuestions[] = $nextQuestion;
            $collectedData['_asked_questions'] = $askedQuestions;

            return [
                'response' => $nextQuestion,
                'completed' => false,
                'data' => $collectedData,
            ];
        } else {
            // Đã hỏi hết, cần extract answers từ userMessage
            // Sử dụng AI để extract
            return $this->extractAnswersFromMessage($userMessage, $questions, $collectedData);
        }
    }

    // Nếu có fields, sử dụng AI để extract
    if (!empty($fields)) {
        return $this->extractFieldsFromMessage($userMessage, $fields, $collectedData);
    }

    return [
        'response' => 'Vui lòng cung cấp thông tin cần thiết.',
        'completed' => false,
    ];
}

protected function executeGenerateStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant): array
{
    $config = $step['config'] ?? [];
    $promptTemplate = $config['prompt_template'] ?? '';

    // Build prompt từ template và collected data
    $prompt = $this->buildPromptFromTemplate($promptTemplate, $collectedData);

    try {
        $response = OpenAI::chat()->create([
            'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $step['description'] ?? 'Bạn là một AI assistant chuyên nghiệp.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        $generatedContent = $response->choices[0]->message->content;

        return [
            'response' => $generatedContent,
            'completed' => true,
            'data' => [
                $step['id'] . '_result' => $generatedContent,
            ],
        ];
    } catch (\Exception $e) {
        Log::error('Generate step error', [
            'error' => $e->getMessage(),
            'step' => $step,
        ]);

        return [
            'response' => 'Xin lỗi, không thể tạo nội dung. Vui lòng thử lại.',
            'completed' => false,
        ];
    }
}

protected function executeSearchStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant): array
{
    $config = $step['config'] ?? [];
    $searchQuery = $config['search_query'] ?? $userMessage;

    // Sử dụng VectorSearchService
    $results = $this->vectorSearchService->search($searchQuery, $assistant, 5);

    return [
        'response' => 'Đã tìm thấy ' . count($results) . ' kết quả liên quan.',
        'completed' => true,
        'data' => [
            $step['id'] . '_results' => $results,
        ],
    ];
}

protected function executeProcessStep(array $step, string $userMessage, array $collectedData): array
{
    // Xử lý dữ liệu dựa trên config
    // Có thể mở rộng với các processor khác nhau
    return [
        'response' => 'Đã xử lý dữ liệu.',
        'completed' => true,
    ];
}

protected function executeValidateStep(array $step, array $collectedData): array
{
    $config = $step['config'] ?? [];
    $validationRules = $config['validation_rules'] ?? [];

    $errors = [];
    foreach ($validationRules as $field => $rule) {
        if (!isset($collectedData[$field]) || empty($collectedData[$field])) {
            $errors[] = $field . ' là bắt buộc.';
        }
    }

    if (!empty($errors)) {
        return [
            'response' => 'Có lỗi xảy ra: ' . implode(', ', $errors),
            'completed' => false,
        ];
    }

    return [
        'response' => 'Dữ liệu hợp lệ.',
        'completed' => true,
    ];
}

protected function executeConditionalStep(array $step, array $collectedData): array
{
    $config = $step['config'] ?? [];
    $condition = $config['condition'] ?? '';
    $ifTrue = $config['if_true'] ?? null;
    $ifFalse = $config['if_false'] ?? null;

    $conditionMet = $this->evaluateCondition($condition, $collectedData);

    if ($conditionMet && $ifTrue) {
        return [
            'response' => $ifTrue['message'] ?? 'Điều kiện đúng.',
            'completed' => true,
            'data' => $ifTrue['data'] ?? [],
        ];
    } elseif (!$conditionMet && $ifFalse) {
        return [
            'response' => $ifFalse['message'] ?? 'Điều kiện sai.',
            'completed' => true,
            'data' => $ifFalse['data'] ?? [],
        ];
    }

    return [
        'response' => 'Đã kiểm tra điều kiện.',
        'completed' => true,
    ];
}

protected function buildPromptFromTemplate(string $template, array $data): string
{
    // Thay thế placeholders trong template
    $prompt = $template;
    foreach ($data as $key => $value) {
        $prompt = str_replace('{' . $key . '}', $value, $prompt);
    }
    return $prompt;
}

protected function extractAnswersFromMessage(string $message, array $questions, array $collectedData): array
{
    // Sử dụng AI để extract answers từ user message
    // Implementation tương tự như IntentRecognizer
    // ...

    return [
        'response' => 'Đã thu thập đủ thông tin.',
        'completed' => true,
        'data' => $collectedData,
    ];
}
```

#### 2.3. Lưu ý về UX

- **Không hiển thị steps**: User không thấy các steps, chỉ thấy kết quả cuối cùng
- **Trả lời tự nhiên**: Mỗi step trả lời như một cuộc hội thoại bình thường
- **Xử lý lỗi**: Nếu step fail, thông báo lỗi rõ ràng và cho phép retry
- **Progress tracking**: Có thể thêm progress indicator (tùy chọn, không bắt buộc)

---

## 📊 Database Schema

Không cần thay đổi database schema. Steps được lưu trong field `config` (JSON) của bảng `ai_assistants`:

```sql
-- Không cần migration mới
-- Sử dụng field config hiện có
UPDATE ai_assistants 
SET config = JSON_SET(
    config,
    '$.steps',
    '[{"id":"step_1","order":1,"name":"...","type":"collect_info",...}]'
)
WHERE id = ?;
```

---

## ✅ Checklist Implementation

### Phase 1: Backend - Generate Steps API
- [ ] Tạo route `POST /admin/assistants/generate-steps`
- [ ] Tạo method `generateSteps()` trong AdminController
- [ ] Implement `buildStepsGenerationPrompt()`
- [ ] Test với các loại assistant khác nhau

### Phase 2: Frontend - Steps Manager Component
- [ ] Tạo component `AssistantStepsManager.vue`
- [ ] Tạo component `StepEditorModal.vue`
- [ ] Tích hợp vào `CreateAssistant.vue`
- [ ] Tích hợp vào `EditAssistant.vue` (nếu có)
- [ ] Test UI/UX

### Phase 3: Backend - Save Steps
- [ ] Cập nhật `createAssistant()` để lưu steps
- [ ] Cập nhật `updateAssistant()` để lưu steps
- [ ] Validate steps format
- [ ] Test save/load steps

### Phase 4: Backend - Execute Steps
- [ ] Cập nhật `WorkflowPlanner::plan()` để sử dụng predefined steps
- [ ] Cập nhật `SmartAssistantEngine` để execute steps
- [ ] Implement các method execute cho từng loại step
- [ ] Test với các loại steps khác nhau

### Phase 5: Testing & Refinement
- [ ] Test end-to-end: Tạo assistant với steps → Chat với user
- [ ] Test với các edge cases (dependencies, conditional, errors)
- [ ] Optimize performance
- [ ] Update documentation

---

## 🎯 Ví Dụ Sử Dụng

### Ví dụ 1: Trợ lý "Viết sách"

**Steps được tạo tự động**:
1. **Step 1**: Thu thập thông tin cơ bản (tiêu đề, mục đích, đối tượng)
2. **Step 2**: Lập dàn ý chi tiết
3. **Step 3**: Viết chương 1
4. **Step 4**: Viết chương 2
5. **Step 5**: Tổng hợp và hoàn thiện

**User experience**:
- User: "Tôi muốn viết một cuốn sách về AI"
- Assistant: "Tuyệt vời! Để tôi giúp bạn. Tiêu đề cuốn sách là gì?"
- User: "AI trong Hành chính công"
- Assistant: "Mục đích viết sách là gì?"
- User: "Giới thiệu ứng dụng AI trong hành chính công"
- ... (tiếp tục các bước)
- Assistant: "Đã tạo dàn ý chi tiết. Bạn có muốn tôi bắt đầu viết chương 1 không?"

### Ví dụ 2: Trợ lý "Soạn thảo văn bản"

**Steps được tạo tự động**:
1. **Step 1**: Xác định loại văn bản (công văn, quyết định, tờ trình...)
2. **Step 2**: Thu thập thông tin cơ bản (số, ngày, nơi gửi/nhận)
3. **Step 3**: Thu thập nội dung chính
4. **Step 4**: Tạo văn bản từ template
5. **Step 5**: Kiểm tra format và quy định

---

## 🚀 Kết Luận

Phương án này cho phép:
1. ✅ Admin định nghĩa steps rõ ràng cho mỗi trợ lý
2. ✅ AI tự động phân tích và đề xuất steps phù hợp
3. ✅ Admin có toàn quyền chỉnh sửa steps
4. ✅ Chatbot chạy tuần tự steps một cách tự nhiên
5. ✅ User không thấy steps, chỉ thấy kết quả

**Lợi ích**:
- Kiểm soát tốt workflow của trợ lý
- Nhất quán trong quy trình xử lý
- Dễ dàng mở rộng với các loại steps mới
- Trải nghiệm user mượt mà và tự nhiên

---

*Phương án này được thiết kế để tích hợp vào hệ thống hiện tại mà không cần thay đổi database schema lớn.*

