<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Inertia\AdminController;
use App\Http\Controllers\Inertia\AssistantController;
use App\Http\Controllers\Inertia\ChatController;
use App\Http\Controllers\Inertia\HomeController;
use Illuminate\Support\Facades\Route;

// Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Auth routes - User login
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Auth routes - Admin login (public routes, no auth required)
Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'login']);
Route::post('/admin/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');

// Public routes
Route::get('/assistants', [AssistantController::class, 'index'])->name('assistants.index');

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Chat
    Route::get('/chat', [ChatController::class, 'dashboard'])->name('chat.dashboard');
    Route::get('/chat/{sessionId}', [ChatController::class, 'index'])->name('chat.index');
    
    // Admin (require admin role)
    Route::prefix('admin')->name('admin.')->middleware(\App\Http\Middleware\EnsureAdmin::class)->group(function () {
        Route::get('/assistants', [AdminController::class, 'assistants'])->name('assistants');
        Route::get('/assistants/create', [AdminController::class, 'createAssistant'])->name('assistants.create');
        Route::post('/assistants', [\App\Http\Controllers\AdminController::class, 'createAssistant'])->name('assistants.store');
        Route::get('/assistants/{assistantId}/edit', [AdminController::class, 'editAssistant'])->name('assistants.edit');
        Route::put('/assistants/{assistantId}', [AdminController::class, 'updateAssistant'])->name('assistants.update');
        Route::get('/assistants/{assistantId}/preview', [AdminController::class, 'previewAssistant'])->name('assistants.preview');
        
        // Assistant Types routes
        Route::get('/assistant-types', [AdminController::class, 'assistantTypes'])->name('assistant-types.index');
        Route::get('/assistant-types/create', [AdminController::class, 'createAssistantType'])->name('assistant-types.create');
        Route::post('/assistant-types', [\App\Http\Controllers\AdminController::class, 'storeAssistantType'])->name('assistant-types.store');
        Route::get('/assistant-types/{typeId}/edit', [AdminController::class, 'editAssistantType'])->name('assistant-types.edit');
        Route::put('/assistant-types/{typeId}', [\App\Http\Controllers\AdminController::class, 'updateAssistantType'])->name('assistant-types.update');
        Route::patch('/assistant-types/{typeId}', [\App\Http\Controllers\AdminController::class, 'updateAssistantType']);
        Route::delete('/assistant-types/{typeId}', [\App\Http\Controllers\AdminController::class, 'deleteAssistantType'])->name('assistant-types.destroy');
        
        // Admin API routes (using web routes instead of API routes for session authentication)
        Route::prefix('assistants')->group(function () {
            Route::get('/list', [\App\Http\Controllers\AdminController::class, 'getAssistants'])->name('assistants.list');
            Route::post('/create', [\App\Http\Controllers\AdminController::class, 'createAssistant'])->name('assistants.api.create');
            Route::post('/generate-steps', [\App\Http\Controllers\AdminController::class, 'generateSteps'])->name('assistants.generate-steps');
            Route::post('/{assistantId}/documents', [\App\Http\Controllers\AdminController::class, 'uploadDocuments'])->name('assistants.documents.upload');
            Route::delete('/{assistantId}/documents/{documentId}', [\App\Http\Controllers\AdminController::class, 'deleteDocument'])->name('assistants.documents.delete');
            // Preview route removed - using Inertia route above instead
            Route::get('/dashboard/stats', [\App\Http\Controllers\AdminController::class, 'getDashboardStats'])->name('assistants.dashboard.stats');
        });
    });
});
