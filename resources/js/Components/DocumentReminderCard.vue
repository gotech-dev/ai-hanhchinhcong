<template>
    <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-500 rounded-lg p-4 mb-4 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-semibold text-yellow-800 mb-2">
                    ⏰ Nhắc nhở: {{ total }} văn bản cần xử lý
                </h3>
                
                <!-- Due Today -->
                <div v-if="dueToday.length > 0" class="mb-3">
                    <div class="text-xs font-medium text-orange-700 mb-1">
                        📅 Hôm nay ({{ dueToday.length }} văn bản):
                    </div>
                    <div class="space-y-1">
                        <div
                            v-for="doc in dueToday"
                            :key="doc.id"
                            class="bg-white rounded p-2 text-xs border border-orange-200"
                        >
                            <div class="font-medium text-gray-900">
                                {{ doc.so_van_ban || 'Chưa có số' }}
                            </div>
                            <div class="text-gray-600 mt-1">
                                {{ doc.trich_yeu || 'N/A' }}
                            </div>
                            <div class="text-gray-500 mt-1">
                                <span v-if="doc.noi_gui">Từ: {{ doc.noi_gui }}</span>
                                <span v-if="doc.noi_nhan">Đến: {{ doc.noi_nhan }}</span>
                                <span v-if="doc.phong_ban_xu_ly" class="ml-2">→ {{ doc.phong_ban_xu_ly }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overdue -->
                <div v-if="overdue.length > 0" class="mb-3">
                    <div class="text-xs font-medium text-red-700 mb-1">
                        ⚠️ Quá hạn ({{ overdue.length }} văn bản):
                    </div>
                    <div class="space-y-1">
                        <div
                            v-for="doc in overdue"
                            :key="doc.id"
                            class="bg-white rounded p-2 text-xs border border-red-200"
                        >
                            <div class="font-medium text-gray-900">
                                {{ doc.so_van_ban || 'Chưa có số' }}
                            </div>
                            <div class="text-gray-600 mt-1">
                                {{ doc.trich_yeu || 'N/A' }}
                            </div>
                            <div class="text-red-600 mt-1">
                                Quá hạn {{ doc.days_overdue }} ngày
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming -->
                <div v-if="reminders.length > 0">
                    <div class="text-xs font-medium text-yellow-700 mb-1">
                        📋 Sắp đến hạn ({{ reminders.length }} văn bản):
                    </div>
                    <div class="space-y-1">
                        <div
                            v-for="doc in reminders"
                            :key="doc.id"
                            class="bg-white rounded p-2 text-xs border border-yellow-200"
                        >
                            <div class="font-medium text-gray-900">
                                {{ doc.so_van_ban || 'Chưa có số' }}
                            </div>
                            <div class="text-gray-600 mt-1">
                                {{ doc.trich_yeu || 'N/A' }}
                            </div>
                            <div class="text-gray-500 mt-1">
                                Còn {{ doc.days_until_deadline }} ngày
                            </div>
                        </div>
                    </div>
                </div>
                
                <button
                    @click="$emit('view-all')"
                    class="mt-3 text-xs text-yellow-700 hover:text-yellow-900 underline"
                >
                    Xem tất cả văn bản →
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    reminders: {
        type: Array,
        default: () => [],
    },
    overdue: {
        type: Array,
        default: () => [],
    },
    dueToday: {
        type: Array,
        default: () => [],
    },
    total: {
        type: Number,
        default: 0,
    },
});

defineEmits(['view-all']);
</script>



