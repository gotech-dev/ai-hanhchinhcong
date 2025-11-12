<template>
    <div class="report-preview bg-white border border-gray-200 rounded-lg shadow-sm p-6 my-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">📄 Báo Cáo</h3>
            <div class="flex gap-2">
                <!-- Edit Request Button -->
                <button
                    v-if="normalizedReportId && !showEditForm"
                    @click="showEditForm = true"
                    :disabled="isGenerating"
                    class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 text-sm font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm hover:shadow-md"
                    title="Yêu cầu chỉnh sửa báo cáo"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Chỉnh sửa
                </button>
                <!-- Always show DOCX button if reportId exists -->
                <button
                    v-if="normalizedReportId"
                    @click="downloadReport('docx')"
                    :disabled="isGenerating"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm hover:shadow-md"
                    title="Tải báo cáo dạng DOCX"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Tải DOCX
                </button>
                <!-- PDF button (optional, can be enabled later) -->
                <button
                    v-if="normalizedReportId"
                    @click="downloadReport('pdf')"
                    :disabled="isGenerating"
                    class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 text-sm font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm hover:shadow-md"
                    title="Tải báo cáo dạng PDF"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Tải PDF
                </button>
            </div>
        </div>
        
        <!-- Edit Request Form -->
        <div v-if="showEditForm" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <h4 class="text-sm font-semibold text-yellow-900 mb-2">Yêu cầu chỉnh sửa báo cáo</h4>
            <textarea
                v-model="editRequest"
                placeholder="Ví dụ: Thêm phần về tài chính, Sửa lại phần kết luận, Thêm số liệu cụ thể..."
                class="w-full px-3 py-2 border border-yellow-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 text-sm"
                rows="3"
            ></textarea>
            <div class="flex gap-2 mt-2">
                <button
                    @click="submitEditRequest"
                    :disabled="!editRequest.trim() || isGenerating"
                    class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    Gửi yêu cầu
                </button>
                <button
                    @click="showEditForm = false; editRequest = ''"
                    :disabled="isGenerating"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    Hủy
                </button>
            </div>
        </div>
        
        <!-- Hiển thị DOCX preview nếu có -->
        <div v-if="docxPreviewHtml && !isGenerating" class="report-content docx-preview" v-html="docxPreviewHtml"></div>
        
        <!-- Fallback: Hiển thị markdown với styling đẹp hơn nếu chưa có DOCX -->
        <div v-else-if="!isGenerating && reportContent" class="report-content markdown-fallback" v-html="formattedContent"></div>
        
        <!-- Loading state -->
        <div v-else-if="isGenerating" class="report-content loading-state">
            <p class="text-gray-500">Đang tạo báo cáo...</p>
        </div>
        
        <div v-if="isGenerating" class="mt-4 text-center text-gray-500">
            <div class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Đang tạo file... Vui lòng đợi</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { marked } from 'marked';
import mammoth from 'mammoth';

const props = defineProps({
    reportContent: String,
    reportId: [Number, String], // Support both Number and String
    docxUrl: String, // URL của file DOCX đã generate
});

const docxPreviewHtml = ref('');
const isGenerating = ref(false);
const showEditForm = ref(false);
const editRequest = ref('');

// Normalize reportId to ensure it's always available
const normalizedReportId = computed(() => {
    if (props.reportId) {
        const numId = Number(props.reportId);
        return isNaN(numId) ? null : numId;
    }
    
    // Try to extract from reportContent if available
    if (props.reportContent) {
        const match = props.reportContent.match(/report[_\s]*id[:\s]*(\d+)/i);
        if (match) {
            return Number(match[1]);
        }
    }
    
    return null;
});

const formattedContent = computed(() => {
    if (!props.reportContent) return '';
    
    marked.use({
        breaks: true,
        gfm: true,
    });
    
    return marked.parse(props.reportContent);
});

/**
 * Load HTML preview from server (95%+ format preservation)
 * Server-side HTML generation with advanced DOCX converter
 * 
 * ORIGINAL METHOD - Không thay đổi
 */
const loadHtmlPreview = async () => {
    if (!normalizedReportId.value) {
        console.warn('[ReportPreview] Cannot load preview: reportId is missing', {
            reportId: props.reportId,
            docxUrl: props.docxUrl,
        });
        return;
    }
    
    console.log('[ReportPreview] Loading HTML preview (server-side)', {
        reportId: normalizedReportId.value,
        docxUrl: props.docxUrl,
    });
    
    try {
        // ✅ NEW: Use server-side HTML generation (95%+ format preservation)
        const previewUrl = `/api/reports/${normalizedReportId.value}/preview-html`;
        console.log('[ReportPreview] Fetching HTML from server', { previewUrl });
        
        const response = await fetch(previewUrl, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        console.log('[ReportPreview] Server response', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            contentType: response.headers.get('content-type'),
        });
        
        if (!response.ok) {
            // If HTML generation fails, try to generate DOCX first
            if (response.status === 404) {
                console.warn('[ReportPreview] Report not found (404), trying to generate from template');
                await generateDocxFromTemplate();
                return;
            }
            throw new Error(`Failed to fetch HTML preview: ${response.statusText}`);
        }
        
        const html = await response.text();
        console.log('[ReportPreview] Received HTML', {
            size: html.length,
            preview: html.substring(0, 200),
        });
        
        // Set HTML directly (already has full styling from server)
        docxPreviewHtml.value = html;
        
        console.log('[ReportPreview] HTML preview loaded successfully', {
            reportId: normalizedReportId.value,
            htmlLength: html.length,
        });
        
    } catch (error) {
        console.error('[ReportPreview] Failed to load HTML preview:', error, {
            reportId: normalizedReportId.value,
            docxUrl: props.docxUrl,
            errorMessage: error.message,
            errorStack: error.stack,
        });
        // Fallback to markdown
        docxPreviewHtml.value = '';
    }
};

/**
 * ✅ FIX 2: Load HTML preview with cache busting
 * 
 * NEW METHOD - Chỉ dùng sau regenerate
 * Không ảnh hưởng loadHtmlPreview() cũ
 */
const loadHtmlPreviewWithCacheBusting = async () => {
    if (!normalizedReportId.value) {
        console.warn('[ReportPreview] Cannot load preview with cache busting: reportId is missing', {
            reportId: props.reportId,
            docxUrl: props.docxUrl,
        });
        return;
    }
    
    console.log('[ReportPreview] Loading HTML preview with cache busting', {
        reportId: normalizedReportId.value,
        docxUrl: props.docxUrl,
    });
    
    try {
        // ✅ FIX 2: Add cache buster to force fresh fetch
        const cacheBuster = Date.now();
        const previewUrl = `/api/reports/${normalizedReportId.value}/preview-html?_=${cacheBuster}`;
        
        console.log('[ReportPreview] Fetching fresh HTML from server', { 
            previewUrl,
            cacheBuster 
        });
        
        const response = await fetch(previewUrl, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            // Force no cache
            cache: 'no-store',
        });
        
        console.log('[ReportPreview] Server response (cache busted)', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            contentType: response.headers.get('content-type'),
        });
        
        if (!response.ok) {
            throw new Error(`Failed to fetch HTML preview: ${response.statusText}`);
        }
        
        const html = await response.text();
        console.log('[ReportPreview] Received fresh HTML', {
            size: html.length,
            preview: html.substring(0, 200),
        });
        
        // Set HTML directly
        docxPreviewHtml.value = html;
        
        console.log('[ReportPreview] Fresh HTML preview loaded successfully', {
            reportId: normalizedReportId.value,
            htmlLength: html.length,
            cacheBusted: true,
        });
        
    } catch (error) {
        console.error('[ReportPreview] Failed to load fresh HTML preview:', error, {
            reportId: normalizedReportId.value,
            docxUrl: props.docxUrl,
            errorMessage: error.message,
            errorStack: error.stack,
        });
        // Fallback to markdown
        docxPreviewHtml.value = '';
    }
};

/**
 * DEPRECATED: Old Mammoth.js client-side conversion (85-90% format)
 * Kept for reference but no longer used
 */
const loadDocxPreview_OLD = async () => {
    // ... (keep old code commented for reference)
};

/**
 * Generate DOCX từ template (lần đầu)
 */
const generateDocxFromTemplate = async () => {
    isGenerating.value = true;
    
    try {
        // Call API để generate DOCX từ template
        const response = await fetch(`/api/reports/${normalizedReportId.value}/generate-docx`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Failed to generate DOCX');
        }
        
        const data = await response.json();
        
        // Reload preview với DOCX mới
        if (data.docx_url) {
            // Update docxUrl prop (would need to use emit or update parent)
            // For now, just reload with the new URL
            await loadDocxPreviewWithUrl(data.docx_url);
        }
    } catch (error) {
        console.error('Failed to generate DOCX:', error);
        alert('Không thể tạo file DOCX. Vui lòng thử lại.');
    } finally {
        isGenerating.value = false;
    }
};

/**
 * Load DOCX preview với URL cụ thể
 * ✅ Fix: Sử dụng API endpoint thay vì fetch trực tiếp
 */
const loadDocxPreviewWithUrl = async (url) => {
    if (!normalizedReportId.value) {
        console.error('Cannot load DOCX preview: reportId is missing');
        return;
    }
    
    try {
        // ✅ Fix CORS: Sử dụng API endpoint thay vì fetch trực tiếp từ storage
        const response = await fetch(`/api/reports/${normalizedReportId.value}/preview`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        
        if (!response.ok) {
            throw new Error(`Failed to fetch DOCX file: ${response.statusText}`);
        }
        
        const arrayBuffer = await response.arrayBuffer();
        const result = await mammoth.convertToHtml(
            { arrayBuffer },
            {
                styleMap: [
                    "p[style-name='Heading 1'] => h1:fresh",
                    "p[style-name='Heading 2'] => h2:fresh",
                ],
            }
        );
        
        docxPreviewHtml.value = result.value;
    } catch (error) {
        console.error('Failed to load DOCX preview:', error);
    }
};

const downloadReport = async (format) => {
    // Check if reportId is available
    if (!normalizedReportId.value) {
        alert('Không tìm thấy ID báo cáo. Vui lòng thử lại sau.');
        return;
    }
    
    isGenerating.value = true;
    
    try {
        // Call API để generate và download file
        const response = await fetch(`/api/reports/${normalizedReportId.value}/download?format=${format}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': format === 'docx' 
                    ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    : 'application/pdf',
            },
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `Failed to download ${format.toUpperCase()}`);
        }
        
        // Get filename from Content-Disposition header
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = contentDisposition
            ? contentDisposition.split('filename=')[1]?.replace(/"/g, '')
            : `report_${normalizedReportId.value}.${format}`;
        
        // Ensure filename has correct extension
        if (!filename.endsWith(`.${format}`)) {
            filename = `report_${normalizedReportId.value}.${format}`;
        }
        
        // Create blob and download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (error) {
        console.error('Failed to download:', error);
        alert(`Không thể tải file ${format.toUpperCase()}. ${error.message || 'Vui lòng thử lại.'}`);
    } finally {
        isGenerating.value = false;
    }
};

/**
 * Submit edit request to regenerate report
 */
const submitEditRequest = async () => {
    console.log('🔵 [submitEditRequest] START', {
        editRequest: editRequest.value,
        reportId: normalizedReportId.value,
        currentHtmlLength: docxPreviewHtml.value ? docxPreviewHtml.value.length : 0,
    });
    
    if (!editRequest.value.trim() || !normalizedReportId.value) {
        console.warn('🔴 [submitEditRequest] ABORT - Missing data', {
            hasEditRequest: !!editRequest.value.trim(),
            hasReportId: !!normalizedReportId.value,
        });
        return;
    }
    
    isGenerating.value = true;
    showEditForm.value = false;
    
    try {
        console.log('🔵 [submitEditRequest] Calling regenerate API', {
            url: `/api/reports/${normalizedReportId.value}/regenerate`,
            editRequest: editRequest.value.trim(),
        });
        
        // Call API to regenerate report with edit request
        const response = await fetch(`/api/reports/${normalizedReportId.value}/regenerate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                edit_request: editRequest.value.trim(),
            }),
        });
        
        console.log('🔵 [submitEditRequest] API response received', {
            status: response.status,
            ok: response.ok,
            statusText: response.statusText,
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            console.error('🔴 [submitEditRequest] API error', {
                status: response.status,
                errorData,
            });
            throw new Error(errorData.error || 'Failed to regenerate report');
        }
        
        const data = await response.json();
        console.log('🔵 [submitEditRequest] API success response', {
            data,
            hasReport: !!data.report,
            reportId: data.report?.report_id,
            reportFilePath: data.report?.report_file_path,
        });
        
        console.log('🔵 [submitEditRequest] BEFORE reload - Current HTML length:', docxPreviewHtml.value?.length || 0);
        
        // ✅ FIX 2: Reload preview với cache busting (force fresh fetch)
        console.log('🔵 [submitEditRequest] Calling loadHtmlPreviewWithCacheBusting...');
        await loadHtmlPreviewWithCacheBusting();
        
        console.log('🔵 [submitEditRequest] AFTER reload - New HTML length:', docxPreviewHtml.value?.length || 0);
        
        // Clear edit request
        editRequest.value = '';
        
        console.log('✅ [submitEditRequest] SUCCESS - Report regenerated', {
            newHtmlLength: docxPreviewHtml.value?.length || 0,
        });
        
        // Show success message
        alert('Báo cáo đã được cập nhật theo yêu cầu của bạn!');
    } catch (error) {
        console.error('Failed to regenerate report:', error);
        alert('Không thể cập nhật báo cáo. Vui lòng thử lại.');
        showEditForm.value = true; // Show form again
    } finally {
        isGenerating.value = false;
    }
};

onMounted(async () => {
    // ✅ LOG: Component mounted
    console.log('[ReportPreview] Component mounted', {
        reportId: props.reportId,
        normalizedReportId: normalizedReportId.value,
        docxUrl: props.docxUrl,
        reportContent: props.reportContent ? props.reportContent.substring(0, 100) : null,
    });
    
    // ✅ NEW: Always try to load HTML preview from server (95%+ format preservation)
    if (normalizedReportId.value) {
        console.log('[ReportPreview] Loading HTML preview from server');
        await loadHtmlPreview();
    } else {
        console.warn('[ReportPreview] No reportId, cannot load preview');
    }
});
</script>

<style scoped>
/* Styling cho DOCX preview - Mammoth.js sẽ generate HTML với inline styles */
.docx-preview {
    font-family: 'Times New Roman', serif;
    line-height: 1.6;
    color: #333;
    max-width: 100%;
    overflow-x: auto;
    white-space: pre-wrap;  /* ✅ FIX: Preserve line breaks */
    word-wrap: break-word;  /* ✅ FIX: Break long words */
}

/* Preserve table formatting */
.docx-preview :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.docx-preview :deep(table th),
.docx-preview :deep(table td) {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

/* Preserve heading styles */
.docx-preview :deep(h1) {
    font-size: 18pt;
    font-weight: bold;
    margin: 20px 0;
    white-space: normal;  /* ✅ FIX: Normal wrapping for headings */
}

.docx-preview :deep(h2) {
    font-size: 16pt;
    font-weight: bold;
    margin: 15px 0;
    white-space: normal;  /* ✅ FIX: Normal wrapping for headings */
}

.docx-preview :deep(h3),
.docx-preview :deep(h4),
.docx-preview :deep(h5),
.docx-preview :deep(h6) {
    white-space: normal;  /* ✅ FIX: Normal wrapping for headings */
    margin: 1em 0 0.5em 0;
}

/* Preserve paragraph formatting */
.docx-preview :deep(p) {
    margin: 10px 0;
    white-space: normal;  /* ✅ FIX: Normal wrapping for paragraphs */
    line-height: 1.5;
}

/* Fallback markdown styling - Cải thiện để đẹp hơn */
.markdown-fallback {
    font-family: 'Times New Roman', serif;
    line-height: 1.8;
    color: #1a1a1a;
    font-size: 14px;
    padding: 20px;
    background: #fafafa;
    border-radius: 8px;
}

.markdown-fallback :deep(h1) {
    font-size: 20px;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
    margin: 20px 0 15px 0;
    color: #000;
    letter-spacing: 1px;
}

.markdown-fallback :deep(h2) {
    font-size: 16px;
    font-weight: bold;
    margin: 15px 0 10px 0;
    color: #000;
    text-transform: uppercase;
}

.markdown-fallback :deep(h3) {
    font-size: 14px;
    font-weight: bold;
    margin: 12px 0 8px 0;
    color: #333;
}

.markdown-fallback :deep(p) {
    margin: 10px 0;
    text-align: justify;
    line-height: 1.8;
}

.markdown-fallback :deep(strong) {
    font-weight: bold;
    color: #000;
}

.markdown-fallback :deep(ul),
.markdown-fallback :deep(ol) {
    margin: 10px 0 10px 20px;
    padding-left: 20px;
}

.markdown-fallback :deep(li) {
    margin: 5px 0;
    line-height: 1.8;
}

.markdown-fallback :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    border: 1px solid #ddd;
}

.markdown-fallback :deep(table th),
.markdown-fallback :deep(table td) {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: left;
    vertical-align: top;
}

.markdown-fallback :deep(table th) {
    background-color: #f5f5f5;
    font-weight: bold;
    text-align: center;
}

.markdown-fallback :deep(code) {
    background-color: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.markdown-fallback :deep(pre) {
    background-color: #f4f4f4;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
    margin: 15px 0;
}

.markdown-fallback :deep(blockquote) {
    border-left: 4px solid #ddd;
    padding-left: 15px;
    margin: 15px 0;
    font-style: italic;
    color: #666;
}

/* Loading state */
.loading-state {
    padding: 20px;
    text-align: center;
    color: #666;
}
</style>

