<template>
    <AdminLayout :auth="auth">
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Sửa Assistant</h2>
                <p class="text-gray-600 mt-2">Chỉnh sửa thông tin assistant</p>
            </div>

            <form @submit.prevent="updateAssistant" class="bg-white rounded-lg shadow-md p-6 space-y-6">
                <!-- Basic Info -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tên Assistant *
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ví dụ: Trợ lý Báo cáo Hành chính"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Mô tả
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Mô tả về assistant này..."
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Loại Assistant *
                    </label>
                    <select
                        v-model="form.assistant_type"
                        required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @change="onTypeChange"
                    >
                        <option value="">-- Chọn loại trợ lý --</option>
                        <option
                            v-for="type in assistantTypes"
                            :key="type.id"
                            :value="type.code"
                        >
                            {{ type.icon ? type.icon + ' ' : '' }}{{ type.name }}
                        </option>
                    </select>
                </div>

                <!-- Documents Upload (for Q&A based and Report Assistant) -->
                <div v-if="form.assistant_type === 'qa_based_document' || form.assistant_type === 'report_assistant'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Tài liệu Mới (PDF/DOCX)
                    </label>
                    <input
                        ref="documentsFileInput"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        multiple
                        @change="handleDocumentsFiles"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Upload thêm tài liệu mới (tài liệu hiện có sẽ được giữ lại)
                    </p>
                    
                    <!-- Show existing documents -->
                    <div v-if="existingDocuments.length > 0" class="mt-3">
                        <p class="text-sm text-gray-700 mb-2">Tài liệu hiện có ({{ existingDocuments.length }}):</p>
                        <ul class="space-y-1">
                            <li
                                v-for="(doc, index) in existingDocuments"
                                :key="index"
                                class="text-sm text-gray-600 flex items-center justify-between bg-gray-50 px-3 py-2 rounded"
                            >
                                <span>{{ doc.name || doc.path }}</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div v-if="selectedDocuments.length > 0" class="mt-3">
                        <p class="text-sm text-gray-700 mb-2">Tài liệu mới đã chọn ({{ selectedDocuments.length }}):</p>
                        <ul class="space-y-1">
                            <li
                                v-for="(file, index) in selectedDocuments"
                                :key="index"
                                class="text-sm text-gray-600 flex items-center justify-between bg-gray-50 px-3 py-2 rounded"
                            >
                                <span>{{ file.name }}</span>
                                <button
                                    type="button"
                                    @click="removeDocument(index)"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    ✕
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Reference URLs Section (for Q&A based and Report Assistant) -->
                <div v-if="(form.assistant_type === 'qa_based_document' || form.assistant_type === 'report_assistant') && props.referenceUrls && props.referenceUrls.length > 0" class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">URL Tham Khảo</h3>
                    <div class="space-y-3">
                        <div
                            v-for="(refUrl, index) in props.referenceUrls"
                            :key="refUrl.id || index"
                            class="border border-gray-200 rounded-lg p-4"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <a
                                        :href="refUrl.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-blue-600 hover:text-blue-800 font-medium break-all"
                                    >
                                        {{ refUrl.title || refUrl.url }}
                                    </a>
                                    <p v-if="refUrl.description" class="text-sm text-gray-600 mt-1">
                                        {{ refUrl.description }}
                                    </p>
                                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                        <span>Trạng thái: 
                                            <span :class="{
                                                'text-yellow-600': refUrl.status === 'pending',
                                                'text-blue-600': refUrl.status === 'crawling',
                                                'text-green-600': refUrl.status === 'indexed',
                                                'text-red-600': refUrl.status === 'failed',
                                            }">
                                                {{ getStatusLabel(refUrl.status) }}
                                            </span>
                                        </span>
                                        <span v-if="refUrl.content_length">
                                            Nội dung: {{ formatBytes(refUrl.content_length) }}
                                        </span>
                                        <span v-if="refUrl.last_crawled_at">
                                            Crawl lần cuối: {{ formatDate(refUrl.last_crawled_at) }}
                                        </span>
                                    </div>
                                    <p v-if="refUrl.error_message" class="text-sm text-red-600 mt-2">
                                        Lỗi: {{ refUrl.error_message }}
                                    </p>
                                </div>
                                <button
                                    v-if="refUrl.status === 'failed'"
                                    @click="retryCrawl(refUrl.id)"
                                    class="text-blue-600 hover:text-blue-800 text-sm ml-4"
                                >
                                    Thử lại
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Drafting Info -->
                <div v-if="form.assistant_type === 'document_drafting'" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-blue-800 font-medium mb-2">📝 Soạn thảo Văn bản Hành chính</h4>
                    <p class="text-sm text-blue-700 mb-2">
                        Trợ lý này sẽ giúp bạn soạn thảo các loại văn bản hành chính theo Nghị định 30/2020/NĐ-CP:
                    </p>
                    <ul class="text-sm text-blue-700 list-disc list-inside space-y-1">
                        <li>Công văn (đi, đến)</li>
                        <li>Quyết định (bổ nhiệm, khen thưởng, kỷ luật)</li>
                        <li>Tờ trình (xin ý kiến, phê duyệt)</li>
                        <li>Báo cáo (định kỳ, đột xuất)</li>
                        <li>Biên bản (họp, kiểm tra, nghiệm thu)</li>
                        <li>Thông báo</li>
                        <li>Nghị quyết</li>
                    </ul>
                    <p class="text-xs text-blue-600 mt-2">
                        AI sẽ tự động soạn thảo nội dung, kiểm tra format và tuân thủ quy định pháp luật.
                    </p>
                </div>

                <!-- Templates Upload (for document_drafting and report_assistant) -->
                <div v-if="form.assistant_type === 'document_drafting' || form.assistant_type === 'report_assistant'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <span v-if="form.assistant_type === 'document_drafting'">Upload Templates Mới (PDF/DOCX)</span>
                        <span v-else-if="form.assistant_type === 'report_assistant'">Upload Templates Báo cáo Mới (PDF/DOCX)</span>
                    </label>
                    <input
                        ref="templatesFileInput"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        multiple
                        @change="handleTemplatesFiles"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Upload thêm template mới (template hiện có sẽ được giữ lại)
                    </p>
                    
                    <!-- Show existing templates -->
                    <div v-if="existingTemplates.length > 0" class="mt-3">
                        <p class="text-sm text-gray-700 mb-2">Template hiện có ({{ existingTemplates.length }}):</p>
                        <ul class="space-y-1">
                            <li
                                v-for="(template, index) in existingTemplates"
                                :key="index"
                                class="text-sm text-gray-600 flex items-center justify-between bg-gray-50 px-3 py-2 rounded"
                            >
                                <span>{{ template.name }}</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div v-if="selectedTemplates.length > 0" class="mt-3">
                        <p class="text-sm text-gray-700 mb-2">Template mới đã chọn ({{ selectedTemplates.length }}):</p>
                        <ul class="space-y-1">
                            <li
                                v-for="(file, index) in selectedTemplates"
                                :key="index"
                                class="text-sm text-gray-600 flex items-center justify-between bg-gray-50 px-3 py-2 rounded"
                            >
                                <span>{{ file.name }}</span>
                                <button
                                    type="button"
                                    @click="removeTemplate(index)"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    ✕
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Document Management Info -->
                <div v-if="form.assistant_type === 'document_management'" class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="text-green-800 font-medium mb-2">📁 Quản lý Văn bản và Lưu trữ</h4>
                    <p class="text-sm text-green-700 mb-2">
                        Trợ lý này sẽ giúp bạn quản lý văn bản đến/đi với các chức năng:
                    </p>
                    <ul class="text-sm text-green-700 list-disc list-inside space-y-1">
                        <li>Phân loại văn bản tự động (OCR + AI)</li>
                        <li>Xác định mức độ khẩn cấp và thời hạn xử lý</li>
                        <li>Gợi ý người xử lý phù hợp</li>
                        <li>Tìm kiếm văn bản bằng semantic search</li>
                        <li>Nhắc nhở tự động thời hạn xử lý</li>
                        <li>Lưu trữ tự động theo cấu trúc (năm/tháng/nơi gửi)</li>
                    </ul>
                    <p class="text-xs text-green-600 mt-2">
                        AI sẽ tự động đọc văn bản (OCR), phân loại, tính toán thời hạn và nhắc nhở bạn.
                    </p>
                </div>

                <!-- Steps Manager -->
                <AssistantStepsManager
                    v-model="form.steps"
                    :assistant-name="form.name"
                    :assistant-description="form.description"
                    :assistant-type="form.assistant_type"
                />

                <!-- Error Messages -->
                <div v-if="Object.keys(errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h4 class="text-red-800 font-medium mb-2">Có lỗi xảy ra:</h4>
                    <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                        <li v-for="(errorMessages, field) in errors" :key="field">
                            <strong>{{ field }}:</strong>
                            <span v-for="(message, index) in errorMessages" :key="index">
                                {{ message }}<span v-if="index < errorMessages.length - 1">, </span>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Progress Indicator -->
                <div v-if="isUploading" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="animate-spin h-5 w-5 text-blue-500 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div class="flex-1">
                            <span class="text-blue-700 font-medium">{{ uploadStatus }}</span>
                            <p v-if="isGeneratingPlaceholders" class="text-xs text-blue-600 mt-1">
                                Đang phân tích template và tạo placeholders...
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3">
                    <Link
                        href="/admin/assistants"
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
                    >
                        Hủy
                    </Link>
                    <button
                        type="submit"
                        :disabled="isUploading || isGeneratingPlaceholders || !form.name.trim()"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ isUploading ? 'Đang cập nhật...' : 'Cập nhật Assistant' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import AssistantStepsManager from '../../Components/AssistantStepsManager.vue';

const props = defineProps({
    auth: Object,
    assistant: Object,
    referenceUrls: {
        type: Array,
        default: () => [],
    },
    assistantTypes: {
        type: Array,
        default: () => [],
    },
});

const form = ref({
    name: '',
    description: '',
    assistant_type: 'document_drafting',
    steps: [],
});

const templatesFileInput = ref(null);
const documentsFileInput = ref(null);
const selectedTemplates = ref([]);
const selectedDocuments = ref([]);
const existingTemplates = ref([]);
const existingDocuments = ref([]);
const isUploading = ref(false);
const uploadStatus = ref('');
const isGeneratingPlaceholders = ref(false);
const errors = ref({});

// Initialize form with assistant data
onMounted(() => {
    if (props.assistant) {
        form.value = {
            name: props.assistant.name || '',
            description: props.assistant.description || '',
            assistant_type: props.assistant.assistant_type || 'document_drafting',
            steps: props.assistant.config?.steps || [],
        };
        
        // Load existing templates and documents
        if (props.assistant.documentTemplates) {
            existingTemplates.value = props.assistant.documentTemplates || [];
        }
        if (props.assistant.documents) {
            existingDocuments.value = props.assistant.documents || [];
        }
    }
});

const onTypeChange = () => {
    selectedTemplates.value = [];
    selectedDocuments.value = [];
};

const handleTemplatesFiles = (event) => {
    const files = Array.from(event.target.files);
    selectedTemplates.value = files;
};

const removeTemplate = (index) => {
    selectedTemplates.value.splice(index, 1);
};

const handleDocumentsFiles = (event) => {
    const files = Array.from(event.target.files);
    selectedDocuments.value = files;
};

const removeDocument = (index) => {
    selectedDocuments.value.splice(index, 1);
};

const updateAssistant = async () => {
    if (!form.value.name.trim()) return;
    
    isUploading.value = true;
    uploadStatus.value = 'Đang cập nhật assistant...';
    errors.value = {};
    
    try {
        const formData = {
            name: form.value.name,
            description: form.value.description || '',
            assistant_type: form.value.assistant_type,
            steps: form.value.steps || [],
            _method: 'PUT', // Laravel method spoofing for PUT request
        };
        
        // Add files to form data
        if ((form.value.assistant_type === 'document_drafting' || form.value.assistant_type === 'report_assistant') && selectedTemplates.value.length > 0) {
            formData.templates = selectedTemplates.value;
            uploadStatus.value = 'Đang upload templates...';
            isGeneratingPlaceholders.value = true;
        }
        
        if ((form.value.assistant_type === 'qa_based_document' || form.value.assistant_type === 'report_assistant') && selectedDocuments.value.length > 0) {
            formData.documents = selectedDocuments.value;
            uploadStatus.value = 'Đang upload và index documents...';
        }
        
        // Use Inertia router to submit form
        router.post(`/admin/assistants/${props.assistant.id}`, formData, {
            forceFormData: true,
            onProgress: (progress) => {
                if (progress.percentage) {
                    uploadStatus.value = `Đang upload... ${Math.round(progress.percentage)}%`;
                    if (progress.percentage > 50 && (form.value.assistant_type === 'document_drafting' || form.value.assistant_type === 'report_assistant') && selectedTemplates.value.length > 0) {
                        isGeneratingPlaceholders.value = true;
                    }
                }
            },
            onSuccess: () => {
                uploadStatus.value = 'Hoàn thành!';
                isUploading.value = false;
                isGeneratingPlaceholders.value = false;
            },
            onError: (page) => {
                if (page.errors) {
                    errors.value = page.errors;
                    uploadStatus.value = 'Có lỗi xảy ra. Vui lòng kiểm tra lại.';
                } else {
                    uploadStatus.value = 'Không thể cập nhật assistant. Vui lòng thử lại.';
                }
                isUploading.value = false;
                isGeneratingPlaceholders.value = false;
            },
        });
    } catch (error) {
        console.error('Error updating assistant:', error);
        uploadStatus.value = 'Không thể cập nhật assistant. Vui lòng thử lại.';
        isUploading.value = false;
        isGeneratingPlaceholders.value = false;
    }
};

// Helper functions for reference URLs
const getStatusLabel = (status) => {
    const labels = {
        'pending': 'Đang chờ',
        'crawling': 'Đang crawl',
        'indexed': 'Đã index',
        'failed': 'Thất bại',
    };
    return labels[status] || status;
};

const formatBytes = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const retryCrawl = async (referenceUrlId) => {
    if (!confirm('Bạn có chắc chắn muốn thử lại crawl URL này?')) return;
    
    try {
        const response = await fetch(`/api/admin/reference-urls/${referenceUrlId}/retry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        });
        
        if (response.ok) {
            alert('Đã queue job crawl lại. Vui lòng đợi vài phút.');
            // Reload page after a delay to see updated status
            setTimeout(() => {
                router.reload();
            }, 2000);
        } else {
            throw new Error('Failed to retry crawl');
        }
    } catch (error) {
        console.error('Error retrying crawl:', error);
        alert('Không thể thử lại crawl. Vui lòng thử lại sau.');
    }
};
</script>

