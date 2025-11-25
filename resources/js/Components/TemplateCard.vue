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

const emit = defineEmits(['create-from-template']);

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

