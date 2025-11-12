<template>
  <div class="steps-manager bg-gray-50 rounded-lg p-6">
    <div class="mb-4 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-900">Quản lý Steps (Các bước)</h3>
      <button
        @click="generateStepsWithAI"
        :disabled="isGeneratingSteps"
        type="button"
        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
      >
        <span v-if="isGeneratingSteps" class="flex items-center gap-2">
          <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Đang tạo...
        </span>
        <span v-else>🤖 Tự động tạo Steps bằng AI</span>
      </button>
    </div>

    <!-- Steps List -->
    <div class="space-y-3" v-if="steps.length > 0">
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
                class="flex-1 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="updateSteps"
              />
            </div>
            <textarea
              v-model="step.description"
              rows="2"
              placeholder="Mô tả step..."
              class="w-full px-2 py-1 border border-gray-300 rounded text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              @input="updateSteps"
            />
            <div class="flex items-center gap-4 text-xs text-gray-600">
              <select 
                v-model="step.type" 
                class="px-2 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="updateSteps"
              >
                <option value="collect_info">Thu thập thông tin</option>
                <option value="generate">Tạo nội dung</option>
                <option value="search">Tìm kiếm</option>
                <option value="process">Xử lý</option>
                <option value="validate">Kiểm tra</option>
                <option value="conditional">Điều kiện</option>
              </select>
              <label class="flex items-center gap-1">
                <input 
                  v-model="step.required" 
                  type="checkbox" 
                  @change="updateSteps"
                />
                Bắt buộc
              </label>
              <span v-if="step.dependencies && step.dependencies.length > 0" class="text-gray-500">
                Dependencies: {{ step.dependencies.join(', ') }}
              </span>
            </div>
          </div>
          <div class="flex gap-1 ml-2">
            <button
              @click="moveStep(index, 'up')"
              :disabled="index === 0"
              type="button"
              class="p-1 text-gray-600 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed"
              title="Di chuyển lên"
            >
              ↑
            </button>
            <button
              @click="moveStep(index, 'down')"
              :disabled="index === steps.length - 1"
              type="button"
              class="p-1 text-gray-600 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed"
              title="Di chuyển xuống"
            >
              ↓
            </button>
            <button
              @click="editStep(index)"
              type="button"
              class="p-1 text-blue-600 hover:text-blue-800"
              title="Chỉnh sửa"
            >
              ✎
            </button>
            <button
              @click="removeStep(index)"
              type="button"
              class="p-1 text-red-600 hover:text-red-800"
              title="Xóa"
            >
              ✕
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-8 text-gray-500">
      <p>Chưa có steps nào. Nhấn nút "Tự động tạo Steps bằng AI" để tạo steps tự động.</p>
    </div>

    <!-- Add Step Button -->
    <button
      @click="addStep"
      type="button"
      class="mt-4 px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-blue-500 hover:text-blue-500 transition-colors"
    >
      + Thêm Step Mới
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  assistantName: {
    type: String,
    default: ''
  },
  assistantDescription: {
    type: String,
    default: ''
  },
  assistantType: {
    type: String,
    default: 'document_drafting'
  }
});

const emit = defineEmits(['update:modelValue']);

const steps = ref([...props.modelValue]);
const isGeneratingSteps = ref(false);

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  steps.value = [...newValue];
}, { deep: true });

const updateSteps = () => {
  // Update order
  steps.value.forEach((step, i) => {
    step.order = i + 1;
  });
  emit('update:modelValue', steps.value);
};

const generateStepsWithAI = async () => {
  if (!props.assistantName.trim()) {
    alert('Vui lòng nhập tên trợ lý trước khi tạo steps tự động.');
    return;
  }

  isGeneratingSteps.value = true;
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
      throw new Error('CSRF token not found');
    }

    const response = await fetch('/admin/assistants/generate-steps', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        name: props.assistantName,
        description: props.assistantDescription || '',
        type: props.assistantType
      })
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.error || 'Không thể tạo steps tự động');
    }

    const data = await response.json();
    if (data.steps && Array.isArray(data.steps)) {
      steps.value = data.steps;
      updateSteps();
    } else {
      throw new Error('Dữ liệu steps không hợp lệ');
    }
  } catch (error) {
    console.error('Error generating steps:', error);
    alert('Không thể tạo steps tự động. Vui lòng thử lại.\n' + error.message);
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
  updateSteps();
};

const removeStep = (index) => {
  if (confirm('Bạn có chắc muốn xóa step này?')) {
    steps.value.splice(index, 1);
    updateSteps();
  }
};

const moveStep = (index, direction) => {
  if (direction === 'up' && index > 0) {
    [steps.value[index], steps.value[index - 1]] = [steps.value[index - 1], steps.value[index]];
    updateSteps();
  } else if (direction === 'down' && index < steps.value.length - 1) {
    [steps.value[index], steps.value[index + 1]] = [steps.value[index + 1], steps.value[index]];
    updateSteps();
  }
};

const editStep = (index) => {
  // For now, just focus on the name input
  // Can be extended with a modal for more detailed editing
  const stepElement = document.querySelectorAll('.steps-manager input[type="text"]')[index * 2];
  if (stepElement) {
    stepElement.focus();
  }
};
</script>

<style scoped>
.steps-manager {
  margin-top: 1.5rem;
}
</style>


