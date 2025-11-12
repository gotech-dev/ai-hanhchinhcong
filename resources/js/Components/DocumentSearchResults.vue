<template>
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-4">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">
            🔍 Kết quả tìm kiếm ({{ documents.length }} văn bản)
        </h3>
        
        <div v-if="documents.length === 0" class="text-center py-4 text-gray-500 text-sm">
            Không tìm thấy văn bản nào
        </div>
        
        <div v-else class="space-y-3">
            <div
                v-for="doc in documents"
                :key="doc.id"
                class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 transition-colors cursor-pointer"
                @click="$emit('view-document', doc)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-semibold text-gray-900">
                                {{ doc.so_van_ban || 'Chưa có số' }}
                            </span>
                            <span
                                :class="[
                                    'text-xs px-2 py-1 rounded',
                                    doc.loai_van_ban === 'van_ban_den' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'
                                ]"
                            >
                                {{ doc.loai_van_ban === 'van_ban_den' ? 'Văn bản đến' : 'Văn bản đi' }}
                            </span>
                            <span
                                v-if="doc.document_type"
                                class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700"
                            >
                                {{ getDocumentTypeLabel(doc.document_type) }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-700 mb-2">
                            {{ doc.trich_yeu || 'N/A' }}
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                            <span v-if="doc.ngay_van_ban">
                                <span class="font-medium">Ngày:</span> {{ doc.ngay_van_ban }}
                            </span>
                            <span v-if="doc.noi_gui">
                                <span class="font-medium">Từ:</span> {{ doc.noi_gui }}
                            </span>
                            <span v-if="doc.noi_nhan">
                                <span class="font-medium">Đến:</span> {{ doc.noi_nhan }}
                            </span>
                            <span v-if="doc.deadline">
                                <span class="font-medium">Hạn:</span> {{ doc.deadline }}
                            </span>
                            <span v-if="doc.phong_ban_xu_ly">
                                <span class="font-medium">Xử lý:</span> {{ doc.phong_ban_xu_ly }}
                            </span>
                        </div>
                    </div>
                    <div class="flex-shrink-0 flex flex-col gap-2">
                        <a
                            v-if="doc.file_path"
                            :href="doc.file_path"
                            target="_blank"
                            @click.stop
                            class="text-blue-600 hover:text-blue-800 text-xs"
                            title="Xem file"
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
defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['view-document']);

const getDocumentTypeLabel = (type) => {
    const labels = {
        'cong_van': 'Công văn',
        'quyet_dinh': 'Quyết định',
        'to_trinh': 'Tờ trình',
        'bao_cao': 'Báo cáo',
        'bien_ban': 'Biên bản',
        'thong_bao': 'Thông báo',
        'nghi_quyet': 'Nghị quyết',
    };
    return labels[type] || type;
};
</script>



