<template>
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-900">
                📄 Danh sách văn bản ({{ documents.length }})
            </h3>
            <div class="flex gap-2">
                <select
                    v-model="filters.loai_van_ban"
                    @change="loadDocuments"
                    class="text-xs border border-gray-300 rounded px-2 py-1"
                >
                    <option value="">Tất cả</option>
                    <option value="van_ban_den">Văn bản đến</option>
                    <option value="van_ban_di">Văn bản đi</option>
                </select>
                <select
                    v-model="filters.trang_thai"
                    @change="loadDocuments"
                    class="text-xs border border-gray-300 rounded px-2 py-1"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="moi">Mới</option>
                    <option value="dang_xu_ly">Đang xử lý</option>
                    <option value="da_xu_ly">Đã xử lý</option>
                    <option value="qua_han">Quá hạn</option>
                </select>
            </div>
        </div>
        
        <div v-if="loading" class="text-center py-4 text-gray-500 text-sm">
            Đang tải...
        </div>
        
        <div v-else-if="documents.length === 0" class="text-center py-4 text-gray-500 text-sm">
            Không có văn bản nào
        </div>
        
        <div v-else class="space-y-2 max-h-96 overflow-y-auto">
            <div
                v-for="doc in documents"
                :key="doc.id"
                :class="[
                    'border rounded-lg p-3 cursor-pointer hover:bg-gray-50 transition-colors',
                    doc.is_overdue ? 'border-red-300 bg-red-50' : doc.is_due_today ? 'border-orange-300 bg-orange-50' : 'border-gray-200'
                ]"
                @click="$emit('view-document', doc)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-700">
                                {{ doc.so_van_ban || 'Chưa có số' }}
                            </span>
                            <span
                                :class="[
                                    'text-xs px-2 py-0.5 rounded',
                                    doc.loai_van_ban === 'van_ban_den' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'
                                ]"
                            >
                                {{ doc.loai_van_ban === 'van_ban_den' ? 'Văn bản đến' : 'Văn bản đi' }}
                            </span>
                            <span
                                v-if="doc.is_overdue"
                                class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-700"
                            >
                                Quá hạn
                            </span>
                            <span
                                v-else-if="doc.is_due_today"
                                class="text-xs px-2 py-0.5 rounded bg-orange-100 text-orange-700"
                            >
                                Hôm nay
                            </span>
                        </div>
                        <div class="text-sm text-gray-900 mb-1 line-clamp-2">
                            {{ doc.trich_yeu || 'N/A' }}
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-500">
                            <span v-if="doc.ngay_van_ban">📅 {{ doc.ngay_van_ban }}</span>
                            <span v-if="doc.noi_gui">📤 {{ doc.noi_gui }}</span>
                            <span v-if="doc.noi_nhan">📥 {{ doc.noi_nhan }}</span>
                            <span v-if="doc.deadline">⏰ {{ doc.deadline }}</span>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <a
                            v-if="doc.file_path"
                            :href="doc.file_path"
                            target="_blank"
                            @click.stop
                            class="text-blue-600 hover:text-blue-800 text-xs"
                        >
                            📄
                        </a>
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
    sessionId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['view-document']);

const documents = ref([]);
const loading = ref(false);
const filters = ref({
    loai_van_ban: '',
    trang_thai: '',
});

const loadDocuments = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filters.value.loai_van_ban) {
            params.append('loai_van_ban', filters.value.loai_van_ban);
        }
        if (filters.value.trang_thai) {
            params.append('trang_thai', filters.value.trang_thai);
        }
        
        const response = await axios.get(`/api/chat/sessions/${props.sessionId}/documents?${params}`);
        documents.value = response.data.documents || [];
    } catch (error) {
        console.error('Error loading documents:', error);
        documents.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadDocuments();
});

defineExpose({
    loadDocuments,
});
</script>



