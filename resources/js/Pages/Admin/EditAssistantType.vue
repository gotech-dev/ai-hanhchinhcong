<template>
    <AdminLayout :auth="auth">
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Chỉnh Sửa Loại Trợ Lý</h2>
                <p class="text-gray-600 mt-2">Cập nhật thông tin loại trợ lý</p>
            </div>

            <form @submit.prevent="updateAssistantType" class="bg-white rounded-lg shadow-md p-6 space-y-6">
                <!-- Code (Read-only) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Mã loại (Code)
                    </label>
                    <input
                        v-model="form.code"
                        type="text"
                        disabled
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 bg-gray-100 text-gray-600 cursor-not-allowed"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Mã loại không thể thay đổi sau khi đã tạo
                    </p>
                    <div v-if="errors.code" class="mt-1 text-sm text-red-600">
                        {{ errors.code }}
                    </div>
                </div>

                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tên loại (Name) *
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ví dụ: Trả lời Q&A từ tài liệu"
                    />
                    <div v-if="errors.name" class="mt-1 text-sm text-red-600">
                        {{ errors.name }}
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Mô tả
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Mô tả chi tiết về loại trợ lý này..."
                    ></textarea>
                    <div v-if="errors.description" class="mt-1 text-sm text-red-600">
                        {{ errors.description }}
                    </div>
                </div>

                <!-- System Prompt -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        System Prompt (Tùy chọn)
                    </label>
                    <textarea
                        v-model="form.system_prompt"
                        rows="12"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        placeholder="Bạn là {name}, một trợ lý AI...&#10;&#10;{description}&#10;&#10;**CHỨC NĂNG CHÍNH:**&#10;- ..."
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        System prompt mặc định cho loại trợ lý này. Có thể dùng placeholders: <code class="bg-gray-100 px-1 rounded">{name}</code> và <code class="bg-gray-100 px-1 rounded">{description}</code>
                    </p>
                    <div v-if="errors.system_prompt" class="mt-1 text-sm text-red-600">
                        {{ errors.system_prompt }}
                    </div>
                    
                    <!-- Preview -->
                    <div v-if="form.system_prompt && form.name" class="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <label class="block text-xs font-medium text-gray-500 mb-2">
                            Preview (với tên hiện tại: "{{ form.name }}"):
                        </label>
                        <div class="text-sm text-gray-700 whitespace-pre-wrap font-mono bg-white p-3 rounded border border-gray-200 max-h-60 overflow-y-auto">
                            {{ previewPrompt }}
                        </div>
                    </div>
                </div>

                <!-- Icon -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Icon (Tùy chọn)
                    </label>
                    <input
                        v-model="form.icon"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ví dụ: 📝, 💬, hoặc icon class name"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Có thể dùng emoji hoặc icon class name (ví dụ: fa-file-text)
                    </p>
                    <div v-if="errors.icon" class="mt-1 text-sm text-red-600">
                        {{ errors.icon }}
                    </div>
                </div>

                <!-- Color -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Màu sắc (Tùy chọn)
                    </label>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="form.color"
                            type="color"
                            class="h-10 w-20 rounded border border-gray-300 cursor-pointer"
                        />
                        <input
                            v-model="form.color"
                            type="text"
                            pattern="^#[0-9A-Fa-f]{6}$"
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="#3B82F6"
                        />
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Màu sắc để hiển thị loại trợ lý (hex code)
                    </p>
                    <div v-if="errors.color" class="mt-1 text-sm text-red-600">
                        {{ errors.color }}
                    </div>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Thứ tự sắp xếp
                    </label>
                    <input
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="0"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Số càng nhỏ sẽ hiển thị trước. Mặc định: 0
                    </p>
                    <div v-if="errors.sort_order" class="mt-1 text-sm text-red-600">
                        {{ errors.sort_order }}
                    </div>
                </div>

                <!-- Is Active -->
                <div class="flex items-center">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        id="is_active"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label for="is_active" class="ml-2 block text-sm text-gray-700">
                        Kích hoạt loại trợ lý này
                    </label>
                </div>

                <!-- Error Messages -->
                <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-sm text-red-600">{{ errorMessage }}</p>
                </div>

                <!-- Success Message -->
                <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-sm text-green-600">{{ successMessage }}</p>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-3 pt-4">
                    <Link
                        href="/admin/assistant-types"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                    >
                        Hủy
                    </Link>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span v-if="isSubmitting">Đang cập nhật...</span>
                        <span v-else>Cập Nhật</span>
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import axios from 'axios';

const props = defineProps({
    auth: Object,
    assistantType: Object,
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const form = reactive({
    code: '',
    name: '',
    description: '',
    system_prompt: '',
    icon: '',
    color: '#3B82F6',
    sort_order: 0,
    is_active: true,
});

// Pre-fill form với dữ liệu hiện tại
onMounted(() => {
    if (props.assistantType) {
        form.code = props.assistantType.code || '';
        form.name = props.assistantType.name || '';
        form.description = props.assistantType.description || '';
        form.system_prompt = props.assistantType.system_prompt || '';
        form.icon = props.assistantType.icon || '';
        form.color = props.assistantType.color || '#3B82F6';
        form.sort_order = props.assistantType.sort_order || 0;
        form.is_active = props.assistantType.is_active !== undefined ? props.assistantType.is_active : true;
    }
});

// Preview prompt với placeholders đã được replace
const previewPrompt = computed(() => {
    if (!form.system_prompt) return '';
    return form.system_prompt
        .replace(/{name}/g, form.name || 'Trợ lý AI')
        .replace(/{description}/g, form.description || '');
});

const isSubmitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const updateAssistantType = async () => {
    isSubmitting.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await axios.put(`/admin/assistant-types/${props.assistantType.id}`, form);
        
        successMessage.value = response.data.message || 'Loại trợ lý đã được cập nhật thành công!';
        
        // Redirect sau 1 giây
        setTimeout(() => {
            router.visit('/admin/assistant-types');
        }, 1000);
    } catch (error) {
        console.error('Error updating assistant type:', error);
        
        if (error.response?.data?.errors) {
            // Validation errors sẽ được hiển thị qua props.errors
            errorMessage.value = 'Vui lòng kiểm tra lại các trường bên dưới.';
        } else {
            errorMessage.value = error.response?.data?.message || 'Không thể cập nhật loại trợ lý. Vui lòng thử lại.';
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

