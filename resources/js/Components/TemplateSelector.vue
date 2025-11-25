<template>
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="$emit('close')"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Chọn mẫu văn bản
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-4">
                                    Vui lòng chọn mẫu văn bản bạn muốn soạn thảo.
                                </p>

                                <!-- Loading State -->
                                <div v-if="isLoading" class="flex justify-center py-8">
                                    <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>

                                <!-- Empty State -->
                                <div v-else-if="templates.length === 0" class="text-center py-8 text-gray-500">
                                    Không có mẫu văn bản nào cho trợ lý này.
                                </div>

                                <!-- Template Grid -->
                                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[60vh] overflow-y-auto p-1">
                                    <div 
                                        v-for="template in templates" 
                                        :key="template.id"
                                        @click="selectTemplate(template)"
                                        class="border rounded-lg p-4 cursor-pointer hover:border-blue-500 hover:shadow-md transition-all relative group"
                                        :class="{'border-blue-500 ring-2 ring-blue-200': selectedTemplateId === template.id}"
                                    >
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="p-2 bg-blue-100 rounded-lg text-blue-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <span class="text-xs font-medium px-2 py-1 bg-gray-100 rounded-full text-gray-600">
                                                {{ formatDocumentType(template.document_type) }}
                                            </span>
                                        </div>
                                        
                                        <h4 class="font-medium text-gray-900 mb-1 line-clamp-2" :title="template.name">
                                            {{ template.name }}
                                        </h4>
                                        <p class="text-xs text-gray-500 mb-3 truncate">
                                            {{ template.file_name }}
                                        </p>
                                        
                                        <div class="flex justify-between items-center mt-2">
                                            <span class="text-xs text-gray-400">
                                                {{ formatDate(template.created_at) }}
                                            </span>
                                            
                                            <!-- Preview Button -->
                                            <button 
                                                @click.stop="previewTemplate(template)"
                                                class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                Xem trước
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button 
                        type="button" 
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="confirmSelection"
                        :disabled="!selectedTemplateId"
                    >
                        Chọn mẫu này
                    </button>
                    <button 
                        type="button" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        @click="$emit('close')"
                    >
                        Hủy bỏ
                    </button>
                </div>
            </div>
        </div>

        <!-- Template Preview Modal (Nested) -->
        <div v-if="showPreview" class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="preview-modal" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showPreview = false"></div>
                
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Xem trước: {{ previewingTemplate?.name }}
                            </h3>
                            <button @click="showPreview = false" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <div class="border rounded bg-gray-50 p-4 max-h-[70vh] overflow-y-auto">
                            <div v-if="previewHtml" v-html="previewHtml" class="prose max-w-none bg-white p-8 shadow-sm mx-auto"></div>
                            <div v-else class="flex justify-center py-12">
                                <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            type="button" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                            @click="selectFromPreview"
                        >
                            Chọn mẫu này
                        </button>
                        <button 
                            type="button" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            @click="showPreview = false"
                        >
                            Đóng
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    assistantId: {
        type: Number,
        required: true
    }
});

const emit = defineEmits(['close', 'select']);

const templates = ref([]);
const isLoading = ref(true);
const selectedTemplateId = ref(null);
const showPreview = ref(false);
const previewingTemplate = ref(null);
const previewHtml = ref(null);

onMounted(async () => {
    await loadTemplates();
});

const loadTemplates = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get(`/api/assistants/${props.assistantId}/templates`);
        templates.value = response.data.templates || [];
    } catch (error) {
        console.error('Error loading templates:', error);
    } finally {
        isLoading.value = false;
    }
};

const selectTemplate = (template) => {
    selectedTemplateId.value = template.id;
};

const confirmSelection = () => {
    const template = templates.value.find(t => t.id === selectedTemplateId.value);
    if (template) {
        emit('select', template);
    }
};

const selectFromPreview = () => {
    if (previewingTemplate.value) {
        selectedTemplateId.value = previewingTemplate.value.id;
        confirmSelection();
        showPreview.value = false;
    }
};

const previewTemplate = async (template) => {
    previewingTemplate.value = template;
    showPreview.value = true;
    previewHtml.value = null;
    
    try {
        const response = await axios.get(`/api/templates/${template.id}/preview-html`);
        previewHtml.value = response.data;
    } catch (error) {
        console.error('Error loading preview:', error);
        previewHtml.value = '<div class="text-center text-red-500 py-4">Không thể tải xem trước</div>';
    }
};

const formatDocumentType = (type) => {
    const types = {
        'quyet_dinh': 'Quyết định',
        'cong_van': 'Công văn',
        'to_trinh': 'Tờ trình',
        'bao_cao': 'Báo cáo',
        'bien_ban': 'Biên bản',
        'thong_bao': 'Thông báo',
        'nghi_quyet': 'Nghị quyết'
    };
    return types[type] || type;
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('vi-VN');
};
</script>
