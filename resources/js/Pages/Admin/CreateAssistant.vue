<template>
    <AdminLayout :auth="auth">
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <h2 class="text-2xl font-semibold text-gray-900">Tạo Assistant Mới</h2>
                <p class="text-gray-600 mt-2">Tạo assistant với form đơn giản</p>
            </div>

            <form @submit.prevent="createAssistant" class="bg-white rounded-lg shadow-md p-6 space-y-6">
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
                    <p v-if="assistantTypes.length === 0" class="text-xs text-yellow-600 mt-1">
                        Chưa có loại trợ lý nào. Vui lòng <Link href="/admin/assistant-types/create" class="text-blue-600 hover:underline">tạo loại trợ lý</Link> trước.
                    </p>
                </div>

                <!-- Documents Upload (for Q&A based) -->
                <div v-if="form.assistant_type === 'qa_based_document'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Tài liệu (PDF/DOCX)
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
                        AI sẽ tự động index tài liệu cho semantic search
                    </p>
                    
                    <div v-if="selectedDocuments.length > 0" class="mt-3">
                        <p class="text-sm text-gray-700 mb-2">Đã chọn {{ selectedDocuments.length }} file:</p>
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

                <!-- Reference URLs (for Q&A based) -->
                <div v-if="form.assistant_type === 'qa_based_document'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        URL Tham Khảo (Tùy chọn)
                    </label>
                    <div class="space-y-2">
                        <div
                            v-for="(url, index) in form.reference_urls"
                            :key="index"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="form.reference_urls[index]"
                                type="url"
                                placeholder="https://example.com/page"
                                class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <button
                                type="button"
                                @click="removeReferenceUrl(index)"
                                class="text-red-600 hover:text-red-800 px-2"
                            >
                                ✕
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="addReferenceUrl"
                            class="text-sm text-blue-600 hover:text-blue-800"
                        >
                            + Thêm URL
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Nhập các URL tham khảo đáng tin cậy. AI sẽ tự động crawl và index nội dung từ các URL này để trả lời câu hỏi.
                    </p>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-2">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">Lưu ý về URL tham khảo</p>
                                <ul class="list-disc list-inside mt-1 space-y-1">
                                    <li>Nếu không có tài liệu upload, chatbot sẽ ưu tiên tìm kiếm trong nội dung từ URL tham khảo</li>
                                    <li>Nếu không tìm thấy trong URL tham khảo, chatbot sẽ tìm kiếm trên mạng</li>
                                    <li>Ví dụ URL phù hợp: trang web luật, quy định pháp luật, tài liệu chính thức</li>
                                </ul>
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

                <!-- Templates Upload (for document_drafting) -->
                <div v-if="form.assistant_type === 'document_drafting'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Templates Văn bản (PDF/DOCX) *
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
                        Upload các template mẫu cho các loại văn bản (ví dụ: quyet_dinh_bo_nhiem.docx, cong_van_di.docx)
                    </p>
                    
                    <!-- ✅ Task 4.1: Info message about auto-generate placeholders -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-2">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="text-sm text-blue-700">
                                <p class="font-medium">Tự động tạo placeholders</p>
                                <p class="mt-1">Nếu template chưa có placeholders (${key}), hệ thống sẽ tự động phân tích và tạo placeholders phù hợp.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="selectedTemplates.length > 0" class="mt-3">
                        <p class="text-sm text-gray-700 mb-2">Đã chọn {{ selectedTemplates.length }} template:</p>
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

                <!-- ✅ CẢI TIẾN: Chỉ hiển thị Steps Manager khi cần -->
                <div v-if="shouldShowStepsManager" class="mt-6">
                    <AssistantStepsManager
                        v-model="form.steps"
                        :assistant-name="form.name"
                        :assistant-description="form.description"
                        :assistant-type="form.assistant_type"
                    />
                </div>
                
                <!-- ✅ CẢI TIẾN: Thông báo cho Q&A assistant -->
                <div v-else-if="form.assistant_type === 'qa_based_document'" class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-blue-800">
                            <p class="font-medium mb-2">Lưu ý: Trợ lý Q&A không cần tạo steps</p>
                            <p class="mb-2">Trợ lý sẽ tự động:</p>
                            <ul class="list-disc list-inside space-y-1 text-blue-700">
                                <li>Trả lời dựa trên tài liệu đã upload (nếu có)</li>
                                <li>Tìm kiếm thông tin trên mạng và trả lời bằng ChatGPT (nếu không có tài liệu)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- ✅ CẢI TIẾN: Thông báo cho Document Management assistant -->
                <div v-else-if="form.assistant_type === 'document_management'" class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-sm text-green-800">
                            <p class="font-medium mb-2">Lưu ý: Trợ lý Quản lý Văn bản không cần tạo steps</p>
                            <p class="text-green-700">Trợ lý sẽ tự động xử lý các tác vụ quản lý văn bản mà không cần workflow phức tạp.</p>
                        </div>
                    </div>
                </div>

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
                            <!-- ✅ Task 4.2: Show placeholder generation status -->
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
                        {{ isUploading ? 'Đang tạo...' : 'Tạo Assistant' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import AssistantStepsManager from '../../Components/AssistantStepsManager.vue';

const props = defineProps({
    auth: Object,
    assistantTypes: {
        type: Array,
        default: () => [],
    },
});

const form = ref({
    name: '',
    description: '',
    assistant_type: props.assistantTypes.length > 0 ? props.assistantTypes[0].code : '',
    steps: [],
    reference_urls: [],
});

const templateFileInput = ref(null);
const templatesFileInput = ref(null);
const documentsFileInput = ref(null);
const selectedTemplateFile = ref(null);
const selectedTemplates = ref([]);
const selectedDocuments = ref([]);
const isUploading = ref(false);
const uploadStatus = ref('');
const isGeneratingPlaceholders = ref(false);
const errors = ref({});

// ✅ CẢI TIẾN: Computed để xác định khi nào hiển thị Steps Manager
const shouldShowStepsManager = computed(() => {
    // Q&A và Document Management không cần steps
    if (form.value.assistant_type === 'qa_based_document' || 
        form.value.assistant_type === 'document_management') {
        return false;
    }
    
    // Document Drafting: Chỉ hiển thị nếu mô tả yêu cầu workflow
    if (form.value.assistant_type === 'document_drafting') {
        const text = (form.value.name + ' ' + (form.value.description || '')).toLowerCase();
        const workflowKeywords = ['bước', 'quy trình', 'workflow', 'research', 'bao quát'];
        return workflowKeywords.some(keyword => text.includes(keyword));
    }
    
    // Các loại khác: Hiển thị
    return true;
});

const onTypeChange = () => {
    selectedTemplateFile.value = null;
    selectedTemplates.value = [];
    selectedDocuments.value = [];
    form.value.reference_urls = [];
};

const addReferenceUrl = () => {
    form.value.reference_urls.push('');
};

const removeReferenceUrl = (index) => {
    form.value.reference_urls.splice(index, 1);
};

const handleTemplateFile = (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedTemplateFile.value = file;
    }
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

const createAssistant = async () => {
    if (!form.value.name.trim()) return;
    
    isUploading.value = true;
    uploadStatus.value = 'Đang tạo assistant...';
    errors.value = {};
    
    try {
        const formData = {
            name: form.value.name,
            description: form.value.description || '',
            assistant_type: form.value.assistant_type,
            steps: form.value.steps || [],
        };
        
        // Add files to form data
        if (form.value.assistant_type === 'document_drafting' && selectedTemplates.value.length > 0) {
            formData.templates = selectedTemplates.value;
            uploadStatus.value = 'Đang upload templates...';
            // ✅ Task 4.2: Set placeholder generation status
            isGeneratingPlaceholders.value = true;
        }
        
        if (form.value.assistant_type === 'qa_based_document' && selectedDocuments.value.length > 0) {
            formData.documents = selectedDocuments.value;
            uploadStatus.value = 'Đang upload và index documents...';
        }
        
        // Add reference URLs (filter out empty strings)
        if (form.value.assistant_type === 'qa_based_document' && form.value.reference_urls && form.value.reference_urls.length > 0) {
            formData.reference_urls = form.value.reference_urls.filter(url => url && url.trim() !== '');
        }
        
        // Use Inertia router to submit form (will handle redirect automatically)
        router.post('/admin/assistants', formData, {
            forceFormData: true,
            onProgress: (progress) => {
                if (progress.percentage) {
                    uploadStatus.value = `Đang upload... ${Math.round(progress.percentage)}%`;
                    // Show placeholder generation message after upload starts
                    if (progress.percentage > 50 && form.value.assistant_type === 'document_drafting' && selectedTemplates.value.length > 0) {
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
                    uploadStatus.value = 'Không thể tạo assistant. Vui lòng thử lại.';
                }
                isUploading.value = false;
                isGeneratingPlaceholders.value = false;
            },
        });
    } catch (error) {
        console.error('Error creating assistant:', error);
        uploadStatus.value = 'Không thể tạo assistant. Vui lòng thử lại.';
        isUploading.value = false;
        isGeneratingPlaceholders.value = false;
    }
};
</script>

