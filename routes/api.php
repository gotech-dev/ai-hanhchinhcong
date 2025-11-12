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
        
        // Download document
        Route::get('/{messageId}/download', [\App\Http\Controllers\DocumentController::class, 'download']);
        
        // Compare DOCX with HTML preview
        Route::get('/{messageId}/compare', [\App\Http\Controllers\DocumentController::class, 'compare']);
    });
    
    // Assistant routes (for authenticated users)
    Route::prefix('assistants')->group(function () {
        // Create assistant (admin only)
        Route::post('/', [AssistantController::class, 'store']);
        
        // Update assistant (admin only)
        Route::put('/{id}', [AssistantController::class, 'update']);
        
        // Delete assistant (admin only)
        Route::delete('/{id}', [AssistantController::class, 'destroy']);
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

