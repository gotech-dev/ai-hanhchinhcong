<template>
    <div v-if="templateInfo" class="template-card mt-4 p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
            <span>📋</span>
            <span>Template có sẵn:</span>
        </h4>
        
        <div class="template-info mb-3">
            <div v-for="template in templateInfo.templates" :key="template.id" class="flex items-center gap-2 text-sm text-gray-600">
                <span>📄</span>
                <span>{{ template.name }}</span>
            </div>
        </div>
        
        <div class="template-actions flex gap-2">
            <button
                @click="previewTemplate"
                :disabled="isLoading"
                class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <svg v-if="!isLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isLoading ? 'Đang tải...' : '👁️ Xem Mẫu' }}</span>
            </button>
            
            <button
                @click="createFromTemplate"
                class="flex-1 px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg transition-colors flex items-center justify-center gap-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>✏️ Tạo Từ Mẫu</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    templateInfo: {
        type: Object,
        required: true,
    },
    assistantId: {
        type: [Number, String],
        required: true,
    },
});

const emit = defineEmits(['template-preview', 'create-from-template']);

const isLoading = ref(false);

const previewTemplate = async () => {
    if (isLoading.value) return;
    
    isLoading.value = true;
    
    try {
        const response = await fetch(`/api/assistants/${props.assistantId}/template-preview`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (!response.ok) {
            throw new Error(`Failed to fetch template preview: ${response.statusText}`);
        }
        
        const html = await response.text();
        
        // Emit event với template HTML
        emit('template-preview', html);
    } catch (error) {
        console.error('[TemplateCard] Error loading template preview:', error);
        alert('Không thể tải template preview. Vui lòng thử lại.');
    } finally {
        isLoading.value = false;
    }
};

const createFromTemplate = () => {
    // Emit event để trigger message "Tạo văn bản từ template"
    emit('create-from-template', 'Tạo văn bản từ template');
};
</script>

<style scoped>
.template-card {
    max-width: 100%;
}

.template-actions button {
    min-height: 40px;
}
</style>

