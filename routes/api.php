<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::get('/assistants', [AssistantController::class, 'index']);
Route::get('/assistants/{id}', [AssistantController::class, 'show']);
Route::get('/assistants/{id}/templates', [AssistantController::class, 'getTemplates']);
Route::get('/assistants/{id}/statistics', [AssistantController::class, 'getStatistics']);

// Authenticated routes
// Note: Using 'auth:web' to use session-based authentication
Route::middleware('auth:web')->group(function () {
    
    // Chat routes
    Route::prefix('chat')->group(function () {
        // Get or create session
        Route::post('/sessions/assistant/{assistantId}', [ChatController::class, 'getOrCreateSession']);
        
        // Get user's chat sessions
        Route::get('/sessions', [ChatController::class, 'getSessions']);
        
        // Get chat history
        Route::get('/sessions/{sessionId}/history', [ChatController::class, 'getHistory']);
        
        // Send message
        Route::post('/sessions/{sessionId}/message', [ChatController::class, 'sendMessage']);
        
        // Stream chat response (SSE)
        Route::post('/sessions/{sessionId}/stream', [ChatController::class, 'streamChat']);
        
        // Delete session
        Route::delete('/sessions/{sessionId}', [ChatController::class, 'deleteSession']);
        
        // Upload file attachments
        Route::post('/sessions/{sessionId}/upload', [ChatController::class, 'uploadFiles']);
        
        // Document Management routes
        Route::post('/sessions/{sessionId}/documents/classify', [ChatController::class, 'classifyDocument']);
        Route::get('/sessions/{sessionId}/documents', [ChatController::class, 'getDocuments']);
        Route::get('/sessions/{sessionId}/reminders', [ChatController::class, 'getReminders']);
    });
    
    // Report routes
    Route::prefix('reports')->group(function () {
        // Generate DOCX from template
        Route::post('/{reportId}/generate-docx', [\App\Http\Controllers\ReportController::class, 'generateDocx']);
        
        // Regenerate report with edit request
        Route::post('/{reportId}/regenerate', [\App\Http\Controllers\ReportController::class, 'regenerate']);
        
        // Preview as HTML (95%+ format preservation)
        Route::get('/{reportId}/preview-html', [\App\Http\Controllers\ReportController::class, 'previewHtml']);
        
        // Preview DOCX file (with CORS headers)
        Route::get('/{reportId}/preview', [\App\Http\Controllers\ReportController::class, 'preview']);
        
        // Download report
        Route::get('/{reportId}/download', [\App\Http\Controllers\ReportController::class, 'download']);
    });
    
    // ✅ MỚI: Document routes (for document_drafting assistant)
    Route::prefix('documents')->group(function () {
        // Preview document as HTML (95%+ format preservation)
        Route::get('/{messageId}/preview-html', [\App\Http\Controllers\DocumentController::class, 'previewHtml']);
        
        // Update document HTML preview (for editing)
        Route::put('/{messageId}/html-preview', [\App\Http\Controllers\DocumentController::class, 'updateDocumentHtmlPreview']);
        
        // Download document
        Route::get('/{messageId}/download', [\App\Http\Controllers\DocumentController::class, 'download']);
        
        // Compare DOCX with HTML preview
        Route::get('/{messageId}/compare', [\App\Http\Controllers\DocumentController::class, 'compare']);
    });
    
    // ✅ MỚI: AI Text Processing routes (for rewrite, summarize, expand, fix-grammar)
    Route::prefix('ai')->group(function () {
        Route::post('/rewrite', [\App\Http\Controllers\Api\AiRewriteController::class, 'rewrite']);
        Route::post('/summarize', [\App\Http\Controllers\Api\AiRewriteController::class, 'summarize']);
        Route::post('/expand', [\App\Http\Controllers\Api\AiRewriteController::class, 'expand']);
        Route::post('/fix-grammar', [\App\Http\Controllers\Api\AiRewriteController::class, 'fixGrammar']);
    });
    
    // ✅ MỚI: Template routes (for template HTML preview)
    Route::prefix('templates')->group(function () {
        // Preview template as HTML (from saved HTML in metadata)
        Route::get('/{templateId}/preview-html', [\App\Http\Controllers\DocumentController::class, 'previewTemplateHtml']);
        
        // Update template HTML preview
        Route::put('/{templateId}/html-preview', [\App\Http\Controllers\DocumentController::class, 'updateHtmlPreview']);
        
        // ✅ DEBUG: Check template HTML for assistant
        Route::get('/debug/assistant/{assistantId}', function ($assistantId) {
            $assistant = \App\Models\AiAssistant::find($assistantId);
            if (!$assistant) {
                return response()->json(['error' => 'Assistant not found'], 404);
            }
            
            $templates = \App\Models\DocumentTemplate::where('ai_assistant_id', $assistantId)
                ->where('is_active', true)
                ->get();
            
            $result = [
                'assistant' => [
                    'id' => $assistant->id,
                    'name' => $assistant->name,
                    'type' => $assistant->assistant_type,
                ],
                'templates' => [],
            ];
            
            foreach ($templates as $template) {
                $metadata = $template->metadata ?? [];
                $htmlPreview = $metadata['html_preview'] ?? null;
                
                $templateInfo = [
                    'id' => $template->id,
                    'name' => $template->name,
                    'document_type' => $template->document_type,
                    'file_name' => $template->file_name,
                    'file_type' => $template->file_type,
                    'file_path' => $template->file_path,
                    'has_html_preview' => !empty($htmlPreview),
                    'html_preview_length' => $htmlPreview ? strlen($htmlPreview) : 0,
                    'cached_at' => $metadata['html_preview_cached_at'] ?? null,
                ];
                
                if ($htmlPreview) {
                    // Extract sample text
                    $textOnly = strip_tags($htmlPreview);
                    $templateInfo['html_preview_sample'] = mb_substr($textOnly, 0, 500);
                    
                    // Count paragraphs
                    $templateInfo['p_tag_count'] = substr_count($htmlPreview, '<p');
                    
                    // Extract first 5 paragraphs
                    preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $htmlPreview, $matches);
                    $firstParagraphs = [];
                    foreach (array_slice($matches[1], 0, 5) as $pContent) {
                        $text = trim(strip_tags($pContent));
                        $text = preg_replace('/\s+/', ' ', $text);
                        if (!empty($text)) {
                            $firstParagraphs[] = mb_substr($text, 0, 200);
                        }
                    }
                    $templateInfo['first_5_paragraphs'] = $firstParagraphs;
                    
                    // ✅ FULL HTML để inspect
                    $templateInfo['html_preview_full'] = $htmlPreview;
                }
                
                $result['templates'][] = $templateInfo;
            }
            
            return response()->json($result, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        });
        
        // ✅ DEBUG: Check specific template by ID
        Route::get('/debug/template/{templateId}', function ($templateId) {
            $template = \App\Models\DocumentTemplate::find($templateId);
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }
            
            $metadata = $template->metadata ?? [];
            $htmlPreview = $metadata['html_preview'] ?? null;
            
            $result = [
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'document_type' => $template->document_type,
                    'file_name' => $template->file_name,
                    'file_type' => $template->file_type,
                    'file_path' => $template->file_path,
                    'assistant_id' => $template->ai_assistant_id,
                ],
                'html_preview' => [
                    'exists' => !empty($htmlPreview),
                    'length' => $htmlPreview ? strlen($htmlPreview) : 0,
                    'cached_at' => $metadata['html_preview_cached_at'] ?? null,
                ],
            ];
            
            if ($htmlPreview) {
                $textOnly = strip_tags($htmlPreview);
                $result['html_preview']['sample_text'] = mb_substr($textOnly, 0, 500);
                $result['html_preview']['p_tag_count'] = substr_count($htmlPreview, '<p');
                $result['html_preview']['full_html'] = $htmlPreview;
                
                // Extract first 10 paragraphs
                preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $htmlPreview, $matches);
                $paragraphs = [];
                foreach (array_slice($matches[1], 0, 10) as $i => $pContent) {
                    $text = trim(strip_tags($pContent));
                    $text = preg_replace('/\s+/', ' ', $text);
                    if (!empty($text)) {
                        $paragraphs[] = [
                            'index' => $i + 1,
                            'text' => $text,
                            'length' => mb_strlen($text),
                        ];
                    }
                }
                $result['html_preview']['first_10_paragraphs'] = $paragraphs;
            }
            
            return response()->json($result, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        });
    });
    
    // Assistant routes (for authenticated users)
    Route::prefix('assistants')->group(function () {
        // Create assistant (admin only)
        Route::post('/', [AssistantController::class, 'store']);
        
        // Update assistant (admin only)
        Route::put('/{id}', [AssistantController::class, 'update']);
        
        // Delete assistant (admin only)
        Route::delete('/{id}', [AssistantController::class, 'destroy']);
        
        // ✅ MỚI: Preview template của assistant (cho user)
        Route::get('/{assistantId}/template-preview', [\App\Http\Controllers\DocumentController::class, 'previewAssistantTemplate']);
    });
    
    // Admin routes
    Route::prefix('admin')->group(function () {
        // Get all assistants (admin only)
        Route::get('/assistants', [AdminController::class, 'getAssistants']);
        
        // Create assistant (minimalist form)
        Route::post('/assistants', [AdminController::class, 'createAssistant']);
        
        // Upload documents to assistant
        Route::post('/assistants/{assistantId}/documents', [AdminController::class, 'uploadDocuments']);
        
        // Delete document
        Route::delete('/assistants/{assistantId}/documents/{documentId}', [AdminController::class, 'deleteDocument']);
        
        // Preview assistant removed - using Inertia route in web.php instead
        
        // Dashboard statistics
        Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
        
        // Reference URLs
        Route::post('/reference-urls/{referenceUrlId}/retry', [AdminController::class, 'retryCrawlReferenceUrl']);
    });
});

