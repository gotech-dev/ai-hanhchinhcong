<template>
    <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 mb-4">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-green-800 mb-2">
                    ✅ Văn bản đã được phân loại thành công
                </h3>
                
                <div class="space-y-2 text-sm">
                    <div v-if="document.so_van_ban" class="flex items-center gap-2">
                        <span class="font-medium text-gray-700">Số văn bản:</span>
                        <span class="text-gray-900">{{ document.so_van_ban }}</span>
                    </div>
                    
                    <div v-if="document.ngay_van_ban" class="flex items-center gap-2">
                        <span class="font-medium text-gray-700">Ngày văn bản:</span>
                        <span class="text-gray-900">{{ document.ngay_van_ban }}</span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-700">Loại:</span>
                        <span
                            :class="[
                                'px-2 py-1 rounded text-xs',
                                document.loai_van_ban === 'van_ban_den' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'
                            ]"
                        >
                            {{ document.loai_van_ban === 'van_ban_den' ? 'Văn bản đến' : 'Văn bản đi' }}
                        </span>
                        <span
                            v-if="document.document_type"
                            class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700"
                        >
                            {{ getDocumentTypeLabel(document.document_type) }}
                        </span>
                    </div>
                    
                    <div v-if="document.trich_yeu" class="mt-2">
                        <span class="font-medium text-gray-700">Trích yếu:</span>
                        <p class="text-gray-900 mt-1">{{ document.trich_yeu }}</p>
                    </div>
                    
                    <div v-if="document.noi_gui || document.noi_nhan" class="flex items-center gap-2">
                        <span class="font-medium text-gray-700">
                            {{ document.loai_van_ban === 'van_ban_den' ? 'Nơi gửi:' : 'Nơi nhận:' }}
                        </span>
                        <span class="text-gray-900">{{ document.noi_gui || document.noi_nhan }}</span>
                    </div>
                    
                    <div class="flex items-center gap-4 mt-3">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-700">Mức độ:</span>
                            <span
                                :class="[
                                    'px-2 py-1 rounded text-xs',
                                    document.muc_do === 'khan_cap' ? 'bg-red-100 text-red-700' :
                                    document.muc_do === 'thuong' ? 'bg-yellow-100 text-yellow-700' :
                                    'bg-gray-100 text-gray-700'
                                ]"
                            >
                                {{ getUrgencyLabel(document.muc_do) }}
                            </span>
                        </div>
                        
                        <div v-if="document.deadline" class="flex items-center gap-2">
                            <span class="font-medium text-gray-700">Hạn xử lý:</span>
                            <span class="text-gray-900">{{ document.deadline }}</span>
                        </div>
                    </div>
                    
                    <div v-if="document.phong_ban_xu_ly" class="mt-2">
                        <span class="font-medium text-gray-700">Gợi ý xử lý:</span>
                        <span class="text-gray-900 ml-2">{{ document.phong_ban_xu_ly }}</span>
                    </div>
                    
                    <div class="flex items-center gap-2 mt-3">
                        <span class="font-medium text-gray-700">Trạng thái:</span>
                        <span
                            :class="[
                                'px-2 py-1 rounded text-xs',
                                document.trang_thai === 'moi' ? 'bg-blue-100 text-blue-700' :
                                document.trang_thai === 'dang_xu_ly' ? 'bg-yellow-100 text-yellow-700' :
                                document.trang_thai === 'da_xu_ly' ? 'bg-green-100 text-green-700' :
                                'bg-red-100 text-red-700'
                            ]"
                        >
                            {{ getStatusLabel(document.trang_thai) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    document: {
        type: Object,
        required: true,
    },
});

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

const getUrgencyLabel = (mucDo) => {
    const labels = {
        'khan_cap': 'Khẩn cấp',
        'thuong': 'Thường',
        'khong_khan': 'Không khẩn',
    };
    return labels[mucDo] || mucDo;
};

const getStatusLabel = (trangThai) => {
    const labels = {
        'moi': 'Mới',
        'dang_xu_ly': 'Đang xử lý',
        'da_xu_ly': 'Đã xử lý',
        'qua_han': 'Quá hạn',
        'huy': 'Hủy',
    };
    return labels[trangThai] || trangThai;
};
</script>



