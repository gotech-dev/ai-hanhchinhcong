<?php

namespace App\Http\Controllers;

use App\Models\AiAssistant;
use App\Models\AssistantDocument;
use App\Services\AutoConfigurationService;
use App\Services\DocumentProcessor;
use App\Services\VectorSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenAI\Laravel\Facades\OpenAI;

class AdminController extends Controller
{
    public function __construct(
        protected AutoConfigurationService $autoConfigurationService,
        protected DocumentProcessor $documentProcessor,
        protected VectorSearchService $vectorSearchService
    ) {}

    /**
     * Get all assistants (admin only)
     */
    public function getAssistants(Request $request)
    {
        $user = Auth::user();
        
        $query = AiAssistant::where('admin_id', $user->id)
            ->with(['admin', 'documents'])
            ->withCount(['chatSessions', 'documents']);
        
        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        
        // Filter by type
        if ($request->has('type')) {
            $query->where('assistant_type', $request->input('type'));
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }
        
        $assistants = $query->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'assistants' => $assistants,
        ]);
    }

    /**
     * Create assistant (minimalist form)
     */
    public function createAssistant(Request $request)
    {
        // Log request details for debugging
        Log::info('Create assistant request received', [
            'has_session' => $request->hasSession(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : 'no-session',
            'auth_check' => Auth::check(),
            'user_id' => Auth::check() ? Auth::id() : null,
            'cookies' => array_keys($request->cookies->all()),
            'headers' => [
                'x-csrf-token' => $request->header('X-CSRF-TOKEN'),
                'x-requested-with' => $request->header('X-Requested-With'),
            ],
        ]);
        
        // Check if user is authenticated
        if (!Auth::check()) {
            Log::warning('Create assistant: User not authenticated');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assistant_type' => [
                'required',
                'string',
                Rule::exists('assistant_types', 'code')->where('is_active', true),
            ],
            'templates' => 'nullable|array',
            'templates.*' => 'file|mimes:pdf,doc,docx|max:10240',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
            'model' => 'nullable|string',
            'steps' => 'nullable|array',
            'reference_urls' => 'nullable|array',
            'reference_urls.*' => 'url|max:500',
        ]);
        
        if ($validator->fails()) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            // For Inertia requests, redirect back with errors
            return redirect()->back()->withErrors($validator->errors());
        }
        
        $data = $validator->validated();
        
        DB::beginTransaction();
        
        try {
            // Build config
            $config = [
                'model' => $data['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
            ];

            // ✅ CẢI TIẾN: Tự động phân loại khi nào cần steps
            $shouldHaveSteps = $this->shouldAssistantHaveSteps(
                $data['assistant_type'],
                $data['name'] ?? '',
                $data['description'] ?? ''
            );

            // ✅ CẢI TIẾN: Chỉ thêm steps nếu cần và được cung cấp
            if ($shouldHaveSteps) {
                // Nếu admin cung cấp steps, sử dụng
                if ($request->has('steps') && is_array($request->steps) && !empty($request->steps)) {
                    $config['steps'] = $this->formatSteps($request->steps);
                }
                // Nếu không có steps nhưng cần, tự động tạo
                elseif ($this->shouldAutoGenerateSteps($data['assistant_type'], $data['name'] ?? '', $data['description'] ?? '')) {
                    try {
                        $config['steps'] = $this->autoGenerateSteps($data['name'], $data['description'] ?? '', $data['assistant_type']);
                        Log::info('Auto-generated steps for assistant', [
                            'assistant_name' => $data['name'],
                            'steps_count' => count($config['steps']),
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to auto-generate steps', [
                            'error' => $e->getMessage(),
                            'assistant_name' => $data['name'],
                        ]);
                        // Continue without steps
                    }
                }
            } else {
                // ✅ QUAN TRỌNG: Q&A assistant KHÔNG có steps
                // Xóa steps nếu có (tránh admin nhầm lẫn)
                if ($request->has('steps')) {
                    unset($config['steps']);
                }
                Log::info('Assistant created without steps (not needed)', [
                    'assistant_type' => $data['assistant_type'],
                    'name' => $data['name'],
                ]);
            }

            // Create assistant
            $assistant = AiAssistant::create([
                'admin_id' => $user->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'assistant_type' => $data['assistant_type'],
                'config' => $config,
                'is_active' => true,
            ]);
            
            // Auto-configure using AutoConfigurationService
            $documentFiles = $request->hasFile('documents') && $data['assistant_type'] === 'qa_based_document'
                ? $request->file('documents')
                : null;
            
            // Auto-configure assistant (only for qa_based_document)
            if ($data['assistant_type'] === 'qa_based_document') {
                $configResult = $this->autoConfigurationService->analyzeAndConfigure(
                    $assistant,
                    null, // No template file for qa_based_document
                    $documentFiles
                );
            }
            
            // Process document templates for document_drafting and report_assistant
            if (in_array($data['assistant_type'], ['document_drafting', 'report_assistant']) && $request->hasFile('templates')) {
                $this->processDocumentTemplates($request->file('templates'), $assistant);
            }
            
            // ✅ MỚI: Process uploaded report documents for report_assistant (khi tạo mới)
            if ($data['assistant_type'] === 'report_assistant' && $request->hasFile('documents')) {
                try {
                    $reportFiles = $request->file('documents');
                    foreach ($reportFiles as $file) {
                        $originalExtension = strtolower($file->getClientOriginalExtension());
                        $fileName = $file->getClientOriginalName();
                        Log::info('📄 [AdminController] Processing report document (create)', [
                            'assistant_id' => $assistant->id,
                            'file_name' => $fileName,
                            'original_ext' => $originalExtension,
                        ]);

                        // Convert PDF → DOCX using Aspose if needed
                        if ($originalExtension === 'pdf') {
                            $aspose = app(\App\Services\AsposeWordsConverter::class);
                            if ($aspose->isConfigured()) {
                                $docxPath = $aspose->convertPdfToDocx($file);
                                if ($docxPath && file_exists($docxPath)) {
                                    $file = new \Illuminate\Http\UploadedFile(
                                        $docxPath,
                                        pathinfo($fileName, PATHINFO_FILENAME) . '.docx',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        null,
                                        true
                                    );
                                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.docx';
                                    Log::info('✅ Converted PDF to DOCX for report (create)', ['file' => $fileName]);
                                } else {
                                    throw new \Exception('Converted DOCX not found');
                                }
                            } else {
                                throw new \Exception('Aspose not configured');
                            }
                        }

                        // Store file
                        $path = $file->store('report-documents', 'public');
                        $url = Storage::disk('public')->url($path);

                        // Extract text
                        $text = $this->documentProcessor->extractText($file);

                        // Create AssistantDocument record
                        $assistantDoc = AssistantDocument::create([
                            'ai_assistant_id' => $assistant->id,
                            'file_name' => $fileName,
                            'file_path' => $url,
                            'file_type' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
                            'file_size' => $file->getSize(),
                            'status' => 'pending',
                        ]);

                        if (!empty($text)) {
                            // Split & embed
                            $chunks = $this->documentProcessor->splitIntoChunks($text);
                            $embeddings = $this->vectorSearchService->createEmbeddings($chunks);
                            foreach ($chunks as $idx => $chunk) {
                                $this->vectorSearchService->saveChunk(
                                    $assistantDoc->id,
                                    $idx,
                                    $chunk,
                                    $embeddings[$idx] ?? [],
                                    ['source_type' => 'report']
                                );
                            }
                            $assistantDoc->update([
                                'is_indexed' => true,
                                'status' => 'indexed',
                                'chunks_count' => count($chunks),
                                'indexed_at' => now(),
                            ]);
                            Log::info('✅ Report document vectorized (create)', [
                                'assistant_id' => $assistant->id,
                                'doc_id' => $assistantDoc->id,
                                'chunks' => count($chunks),
                            ]);
                        } else {
                            Log::warning('⚠️ No text extracted from report document (create)', ['file' => $fileName]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Failed processing report documents (create)', [
                        'assistant_id' => $assistant->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Continue without aborting creation
                }
            }
            
            // ✅ MỚI: Lưu reference URLs cho Q&A assistant
            if ($data['assistant_type'] === 'qa_based_document' && !empty($data['reference_urls'])) {
                foreach ($data['reference_urls'] as $url) {
                    if (!empty(trim($url))) {
                        $assistant->referenceUrls()->create([
                            'url' => trim($url),
                            'status' => 'pending',
                        ]);
                    }
                }
                
                // ✅ MỚI: Queue job để crawl URLs (async)
                // Note: Job sẽ được tạo ở Phase 5
                if (class_exists(\App\Jobs\CrawlReferenceUrlsJob::class)) {
                    dispatch(new \App\Jobs\CrawlReferenceUrlsJob($assistant->id));
                    Log::info('Queued crawl job for reference URLs', [
                        'assistant_id' => $assistant->id,
                        'urls_count' => count(array_filter($data['reference_urls'], fn($url) => !empty(trim($url)))),
                    ]);
                }
            }
            
            DB::commit();
            
            // Always redirect to preview page for web routes (Inertia requests)
            return redirect()->route('admin.assistants.preview', ['assistantId' => $assistant->id])
                ->with('success', 'Assistant đã được tạo và cấu hình thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Create assistant error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'error' => 'Failed to create assistant: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->withErrors(['error' => 'Không thể tạo assistant. Vui lòng thử lại.']);
        }
    }

    /**
     * Update assistant
     */
    public function updateAssistant(Request $request, int $assistantId)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            Log::warning('Update assistant: User not authenticated');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        $user = Auth::user();
        
        // Find the assistant
        $assistant = AiAssistant::where('id', $assistantId)
            ->where('admin_id', $user->id)
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assistant_type' => [
                'required',
                'string',
                Rule::exists('assistant_types', 'code')->where('is_active', true),
            ],
            'templates' => 'nullable|array',
            'templates.*' => 'file|mimes:pdf,doc,docx|max:10240',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
            'model' => 'nullable|string',
            'steps' => 'nullable|array',
        ]);
        
        if ($validator->fails()) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }
            
            // For Inertia requests, redirect back with errors
            return redirect()->back()->withErrors($validator->errors());
        }
        
        $data = $validator->validated();
        
        DB::beginTransaction();
        
        try {
            // Build config
            $config = $assistant->config ?? [];
            $config['model'] = $data['model'] ?? $config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini');

            // ✅ CẢI TIẾN: Tự động phân loại khi nào cần steps (khi update)
            $shouldHaveSteps = $this->shouldAssistantHaveSteps(
                $data['assistant_type'],
                $data['name'] ?? '',
                $data['description'] ?? ''
            );

            // ✅ CẢI TIẾN: Chỉ thêm steps nếu cần và được cung cấp
            if ($shouldHaveSteps) {
                // Nếu admin cung cấp steps, sử dụng
                if ($request->has('steps') && is_array($request->steps) && !empty($request->steps)) {
                    $config['steps'] = $this->formatSteps($request->steps);
                }
                // Nếu không có steps nhưng cần, tự động tạo
                elseif ($this->shouldAutoGenerateSteps($data['assistant_type'], $data['name'] ?? '', $data['description'] ?? '')) {
                    try {
                        $config['steps'] = $this->autoGenerateSteps($data['name'], $data['description'] ?? '', $data['assistant_type']);
                        Log::info('Auto-generated steps for assistant (update)', [
                            'assistant_id' => $assistant->id,
                            'assistant_name' => $data['name'],
                            'steps_count' => count($config['steps']),
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to auto-generate steps (update)', [
                            'error' => $e->getMessage(),
                            'assistant_id' => $assistant->id,
                            'assistant_name' => $data['name'],
                        ]);
                        // Continue without steps
                    }
                }
            } else {
                // ✅ QUAN TRỌNG: Q&A assistant KHÔNG có steps
                // Xóa steps nếu có (tránh admin nhầm lẫn)
                if (isset($config['steps'])) {
                    unset($config['steps']);
                }
                if ($request->has('steps')) {
                    unset($config['steps']);
                }
                Log::info('Assistant updated without steps (not needed)', [
                    'assistant_id' => $assistant->id,
                    'assistant_type' => $data['assistant_type'],
                    'name' => $data['name'],
                ]);
            }

            // Update assistant basic info
            $assistant->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'assistant_type' => $data['assistant_type'],
                'config' => $config,
            ]);
            
            // Auto-configure using AutoConfigurationService for new documents
            $documentFiles = $request->hasFile('documents') && $data['assistant_type'] === 'qa_based_document'
                ? $request->file('documents')
                : null;
            
            // Auto-configure assistant (only for qa_based_document with new documents)
            if ($data['assistant_type'] === 'qa_based_document' && $documentFiles) {
                $configResult = $this->autoConfigurationService->analyzeAndConfigure(
                    $assistant,
                    null, // No template file for qa_based_document
                    $documentFiles
                );
            }
            
            // Process new document templates for document_drafting and report_assistant
            if (in_array($data['assistant_type'], ['document_drafting', 'report_assistant']) && $request->hasFile('templates')) {
                $this->processDocumentTemplates($request->file('templates'), $assistant);
            }

            // ✅ NEW: Process uploaded report documents for report_assistant
            if ($data['assistant_type'] === 'report_assistant' && $request->hasFile('documents')) {
                try {
                    $reportFiles = $request->file('documents');
                    foreach ($reportFiles as $file) {
                        $originalExtension = strtolower($file->getClientOriginalExtension());
                        $fileName = $file->getClientOriginalName();
                        Log::info('📄 [AdminController] Processing report document', [
                            'assistant_id' => $assistant->id,
                            'file_name' => $fileName,
                            'original_ext' => $originalExtension,
                        ]);

                        // Convert PDF → DOCX using Aspose if needed
                        if ($originalExtension === 'pdf') {
                            $aspose = app(\App\Services\AsposeWordsConverter::class);
                            if ($aspose->isConfigured()) {
                                $docxPath = $aspose->convertPdfToDocx($file);
                                if ($docxPath && file_exists($docxPath)) {
                                    $file = new \Illuminate\Http\UploadedFile(
                                        $docxPath,
                                        pathinfo($fileName, PATHINFO_FILENAME) . '.docx',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        null,
                                        true
                                    );
                                    $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.docx';
                                    Log::info('✅ Converted PDF to DOCX for report', ['file' => $fileName]);
                                } else {
                                    throw new \Exception('Converted DOCX not found');
                                }
                            } else {
                                throw new \Exception('Aspose not configured');
                            }
                        }

                        // Store file
                        $path = $file->store('report-documents', 'public');
                        $url = Storage::disk('public')->url($path);

                        // Extract text
                        $text = $this->documentProcessor->extractText($file);

                        // Create AssistantDocument record
                        $assistantDoc = AssistantDocument::create([
                            'ai_assistant_id' => $assistant->id,
                            'file_name' => $fileName,
                            'file_path' => $url,
                            'file_type' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
                            'file_size' => $file->getSize(),
                            'status' => 'pending',
                        ]);

                        if (!empty($text)) {
                            // Split & embed
                            $chunks = $this->documentProcessor->splitIntoChunks($text);
                            $embeddings = $this->vectorSearchService->createEmbeddings($chunks);
                            foreach ($chunks as $idx => $chunk) {
                                $this->vectorSearchService->saveChunk(
                                    $assistantDoc->id,
                                    $idx,
                                    $chunk,
                                    $embeddings[$idx] ?? [],
                                    ['source_type' => 'report']
                                );
                            }
                            $assistantDoc->update([
                                'is_indexed' => true,
                                'status' => 'indexed',
                                'chunks_count' => count($chunks),
                                'indexed_at' => now(),
                            ]);
                            Log::info('✅ Report document vectorized', [
                                'assistant_id' => $assistant->id,
                                'doc_id' => $assistantDoc->id,
                                'chunks' => count($chunks),
                            ]);
                        } else {
                            Log::warning('⚠️ No text extracted from report document', ['file' => $fileName]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Failed processing report documents', [
                        'assistant_id' => $assistant->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without aborting creation
                }
            }
            
            DB::commit();
            
            // Always redirect to preview page for web routes (Inertia requests)
            return redirect()->route('admin.assistants.preview', ['assistantId' => $assistant->id])
                ->with('success', 'Assistant đã được cập nhật thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Update assistant error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'assistant_id' => $assistantId,
            ]);
            
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'error' => 'Failed to update assistant: ' . $e->getMessage(),
                ], 500);
            }
            
            return redirect()->back()->withErrors(['error' => 'Không thể cập nhật assistant. Vui lòng thử lại.']);
        }
    }

    /**
     * Process template file and auto-configure
     */
    protected function processTemplateFile($file, AiAssistant $assistant)
    {
        // Store file
        $path = $file->store('templates', 'public');
        $url = Storage::disk('public')->url($path);
        
        // Extract text from template
        try {
            $text = $this->documentProcessor->extractText($file);
            
            // Analyze template structure (simple version - can be improved with AI)
            $config = $this->analyzeTemplate($text);
            
            $assistant->update([
                'template_file_path' => $url,
                'config' => array_merge($assistant->config ?? [], $config),
            ]);
        } catch (\Exception $e) {
            Log::error('Process template error', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
            ]);
            
            // Still save the file path even if analysis fails
            $assistant->update([
                'template_file_path' => $url,
            ]);
        }
    }

    /**
     * Analyze template structure
     */
    protected function analyzeTemplate(string $text): array
    {
        // Simple template analysis - extract fields based on patterns
        // Can be improved with AI
        $fields = [];
        
        // Common patterns
        $patterns = [
            '/\b(?:hoạt động|activities)\b/i' => 'activities',
            '/\b(?:kết quả|results?)\b/i' => 'results',
            '/\b(?:khó khăn|difficulties?|problems?)\b/i' => 'difficulties',
            '/\b(?:giải pháp|solutions?)\b/i' => 'solutions',
            '/\b(?:thời gian|time|period)\b/i' => 'time_period',
        ];
        
        foreach ($patterns as $pattern => $fieldKey) {
            if (preg_match($pattern, $text)) {
                $fields[] = [
                    'key' => $fieldKey,
                    'label' => ucfirst(str_replace('_', ' ', $fieldKey)),
                    'required' => true,
                ];
            }
        }
        
        return [
            'template_fields' => $fields,
        ];
    }

    /**
     * Process document templates for document_drafting
     */
    protected function processDocumentTemplates(array $files, AiAssistant $assistant)
    {
        foreach ($files as $file) {
            try {
                $originalExtension = strtolower($file->getClientOriginalExtension());
                $fileName = $file->getClientOriginalName();
                
                // ✅ MỚI: AUTO-CONVERT PDF to DOCX using Aspose.Words API
                if ($originalExtension === 'pdf') {
                    Log::info('📄 [AdminController] Converting PDF to DOCX using Aspose.Words API', [
                        'original_file' => $fileName,
                    ]);
                    
                    try {
                        $asposeConverter = app(\App\Services\AsposeWordsConverter::class);
                        
                        if ($asposeConverter->isConfigured()) {
                            // Convert PDF → DOCX
                            $docxPath = $asposeConverter->convertPdfToDocx($file);
                            
                            if ($docxPath && file_exists($docxPath)) {
                                // Create UploadedFile from converted DOCX
                                $file = new \Illuminate\Http\UploadedFile(
                                    $docxPath,
                                    pathinfo($fileName, PATHINFO_FILENAME) . '.docx',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    null,
                                    true // test mode = true (allow temp files)
                                );
                                
                                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.docx';
                                
                                Log::info('✅ [AdminController] Successfully converted PDF to DOCX', [
                                    'original_file' => $fileName,
                                    'new_file' => $fileName,
                                ]);
                            } else {
                                throw new \Exception('Converted DOCX file not found');
                            }
                        } else {
                            throw new \Exception('Aspose.Words API is not configured. Please set ASPOSE_CLIENT_ID and ASPOSE_CLIENT_SECRET in .env file.');
                        }
                    } catch (\Exception $e) {
                        Log::error('🔴 [AdminController] PDF to DOCX conversion failed', [
                            'file' => $fileName,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        // Re-throw exception - don't continue with PDF file
                        throw new \Exception("Failed to convert PDF to DOCX: " . $e->getMessage());
                    }
                }
                
                // ✅ AUTO-CONVERT .doc to .docx
                if ($originalExtension === 'doc') {
                    Log::info('🔄 [AdminController] Converting .doc to .docx', [
                        'original_file' => $fileName,
                    ]);
                    
                    try {
                        // Convert .doc to .docx using LibreOffice (if available) or manual conversion
                        $convertedFile = $this->convertDocToDocx($file);
                        
                        if ($convertedFile) {
                            $file = $convertedFile;
                            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.docx';
                            Log::info('✅ [AdminController] Successfully converted to .docx', [
                                'new_file' => $fileName,
                            ]);
                        } else {
                            Log::warning('⚠️ [AdminController] Failed to convert .doc to .docx, using original file', [
                                'file' => $fileName,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('⚠️ [AdminController] Doc conversion failed', [
                            'file' => $fileName,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with original .doc file
                    }
                }
                
                // Store file
                $path = $file->store('document-templates', 'public');
                $url = Storage::disk('public')->url($path);
                $fullPath = Storage::disk('public')->path($path);
                
                // Extract file name and detect document type
                $documentType = $this->detectDocumentTypeFromFileName($fileName);
                $templateSubtype = $this->detectTemplateSubtypeFromFileName($fileName);
                $templateName = $this->generateTemplateName($documentType, $templateSubtype);
                
                // ✅ NEW: Auto-generate placeholders if not exists (if DOCX)
                $metadata = [];
                $finalExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // ✅ MỚI: Nếu đã convert từ PDF → DOCX, đảm bảo file_type là 'docx'
                if ($originalExtension === 'pdf' && $finalExtension === 'docx') {
                    Log::info('✅ [AdminController] File type updated from PDF to DOCX after conversion', [
                        'original' => $originalExtension,
                        'final' => $finalExtension,
                    ]);
                }
                
                if ($finalExtension === 'docx') {
                    // ✅ FIX: Skip placeholder generation for report_assistant
                    // report_assistant uses rewrite feature (bôi đen + chuột phải) instead of placeholders
                    $shouldGeneratePlaceholders = $assistant->assistant_type !== 'report_assistant';
                    
                    if ($shouldGeneratePlaceholders) {
                        try {
                            // Resolve TemplatePlaceholderGenerator service
                            $placeholderGenerator = app(\App\Services\TemplatePlaceholderGenerator::class);
                            
                            Log::info('🔵 [AdminController] Starting placeholder generation/extraction', [
                                'file' => $fileName,
                                'full_path' => $fullPath,
                                'assistant_type' => $assistant->assistant_type,
                            ]);
                            
                            // Try to generate placeholders (will extract existing if present)
                            $placeholders = $placeholderGenerator->generatePlaceholders($fullPath);
                            
                            if (!empty($placeholders)) {
                                $metadata['placeholders'] = array_keys($placeholders);
                                $metadata['placeholders_auto_generated'] = true;
                                
                                Log::info('✅ [AdminController] Placeholders processed successfully', [
                                    'file' => $fileName,
                                    'placeholders_count' => count($placeholders),
                                    'placeholders' => array_keys($placeholders),
                                ]);
                            } else {
                                Log::warning('⚠️ [AdminController] No placeholders found or generated', [
                                    'file' => $fileName,
                                ]);
                            }
                    } catch (\Exception $e) {
                        Log::error('❌ [AdminController] Error generating/extracting placeholders', [
                            'file' => $fileName,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        // Fallback: Try to extract existing placeholders
                        try {
                            $placeholders = $this->extractPlaceholdersFromTemplate($fullPath);
                            if (!empty($placeholders)) {
                                $metadata['placeholders'] = array_keys($placeholders);
                                Log::info('✅ [AdminController] Fallback: Extracted existing placeholders', [
                                    'file' => $fileName,
                                    'placeholders_count' => count($placeholders),
                                ]);
                            }
                        } catch (\Exception $fallbackException) {
                            Log::warning('⚠️ [AdminController] Fallback extraction also failed', [
                                'file' => $fileName,
                                'error' => $fallbackException->getMessage(),
                            ]);
                        }
                    }
                    } else {
                        // ✅ FIX: Skip placeholder generation for report_assistant
                        Log::info('⏭️ [AdminController] Skipping placeholder generation for report_assistant', [
                            'file' => $fileName,
                            'assistant_type' => $assistant->assistant_type,
                            'reason' => 'report_assistant uses rewrite feature (bôi đen + chuột phải) instead of placeholders',
                        ]);
                    }
                }
                
                // ✅ MỚI: Convert template to HTML và lưu vào metadata (cho document_drafting, report_generator và report_assistant)
                $htmlPreview = null;
                if (in_array($assistant->assistant_type, ['document_drafting', 'report_generator', 'report_assistant'])) {
                    try {
                        $asposeConverter = app(\App\Services\AsposeWordsConverter::class);
                        
                        if ($asposeConverter->isConfigured()) {
                            Log::info('🔵 [AdminController] Converting template to HTML for preview', [
                                'assistant_type' => $assistant->assistant_type,
                                'file_type' => $finalExtension,
                                'file_name' => $fileName,
                            ]);
                            
                            // Convert PDF/DOCX → HTML
                            if ($finalExtension === 'pdf') {
                                // Convert PDF → HTML
                                $htmlPreview = $asposeConverter->convertPdfToHtml($fullPath);
                            } elseif ($finalExtension === 'docx') {
                                // Convert DOCX → HTML
                                $htmlPreview = $asposeConverter->convertDocxToHtml($fullPath);
                            }
                            
                            if ($htmlPreview) {
                                $metadata['html_preview'] = $htmlPreview;
                                Log::info('✅ [AdminController] Template HTML preview saved to metadata', [
                                    'html_length' => strlen($htmlPreview),
                                    'file_name' => $fileName,
                                ]);
                            }
                        } else {
                            Log::warning('⚠️ [AdminController] Aspose API not configured, skipping HTML preview generation', [
                                'file_name' => $fileName,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('⚠️ [AdminController] Failed to generate HTML preview, continuing without it', [
                            'file_name' => $fileName,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue without HTML preview - không block việc tạo template
                    }
                }
                
                // Create document template record
                $templateRecord = \App\Models\DocumentTemplate::create([
                    'ai_assistant_id' => $assistant->id,
                    'document_type' => $documentType,
                    'template_subtype' => $templateSubtype,
                    'name' => $templateName,
                    'file_name' => $fileName,
                    'file_path' => $url,
                    'file_type' => $finalExtension,
                    'file_size' => $file->getSize(),
                    'metadata' => $metadata,
                    'is_active' => true,
                ]);
                
                // ✅ NEW: Vector hóa template để có thể trả lời câu hỏi về nội dung
                try {
                    Log::info('🔵 [AdminController] Starting template vectorization for Q&A', [
                        'template_id' => $templateRecord->id,
                        'file_name' => $fileName,
                    ]);
                    
                    // Extract text từ template file
                    $text = $this->documentProcessor->extractText($file);
                    
                    if (!empty($text)) {
                        // Tạo AssistantDocument record cho template
                        $assistantDoc = AssistantDocument::create([
                            'ai_assistant_id' => $assistant->id,
                            'file_name' => $fileName,
                            'file_path' => $url,
                            'file_type' => $finalExtension,
                            'file_size' => $file->getSize(),
                            'status' => 'pending',
                        ]);
                        
                        // Split into chunks
                        $chunks = $this->documentProcessor->splitIntoChunks($text);
                        
                        // Create embeddings for all chunks
                        $embeddings = $this->vectorSearchService->createEmbeddings($chunks);
                        
                        // Save chunks with embeddings và metadata chỉ rõ đây là template
                        foreach ($chunks as $index => $chunk) {
                            $this->vectorSearchService->saveChunk(
                                $assistantDoc->id,
                                $index,
                                $chunk,
                                $embeddings[$index] ?? [],
                                [
                                    'source_type' => 'template', // ✅ Mark as template content
                                    'template_id' => $templateRecord->id,
                                    'document_type' => $documentType,
                                    'template_subtype' => $templateSubtype,
                                    'chunk_index' => $index,
                                ]
                            );
                        }
                        
                        // Mark as indexed
                        $assistantDoc->update([
                            'is_indexed' => true,
                            'status' => 'indexed',
                            'chunks_count' => count($chunks),
                            'indexed_at' => now(),
                        ]);
                        
                        Log::info('✅ [AdminController] Template vectorized successfully', [
                            'template_id' => $templateRecord->id,
                            'assistant_document_id' => $assistantDoc->id,
                            'chunks_count' => count($chunks),
                            'file_name' => $fileName,
                        ]);
                    } else {
                        Log::warning('⚠️ [AdminController] No text extracted from template, skipping vectorization', [
                            'template_id' => $templateRecord->id,
                            'file_name' => $fileName,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('❌ [AdminController] Failed to vectorize template', [
                        'template_id' => $templateRecord->id,
                        'error' => $e->getMessage(),
                        'file_name' => $fileName,
                    ]);
                    // Don't throw - template is still created, just without vectorization
                }
                
                Log::info('Document template processed', [
                    'assistant_id' => $assistant->id,
                    'file_name' => $fileName,
                    'document_type' => $documentType,
                    'template_subtype' => $templateSubtype,
                    'has_html_preview' => !empty($htmlPreview),
                ]);
                
            } catch (\Exception $e) {
                Log::error('Process document template error', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName(),
                    'assistant_id' => $assistant->id,
                ]);
            }
        }
    }
    
    /**
     * Detect document type from file name
     */
    protected function detectDocumentTypeFromFileName(string $fileName): string
    {
        $fileName = strtolower($fileName);
        
        if (strpos($fileName, 'quyet_dinh') !== false || strpos($fileName, 'quyet-dinh') !== false || strpos($fileName, 'quyetdinh') !== false) {
            return 'quyet_dinh';
        }
        if (strpos($fileName, 'cong_van') !== false || strpos($fileName, 'cong-van') !== false || strpos($fileName, 'congvan') !== false) {
            return 'cong_van';
        }
        if (strpos($fileName, 'to_trinh') !== false || strpos($fileName, 'to-trinh') !== false || strpos($fileName, 'totrinh') !== false) {
            return 'to_trinh';
        }
        if (strpos($fileName, 'bao_cao') !== false || strpos($fileName, 'bao-cao') !== false || strpos($fileName, 'baocao') !== false) {
            return 'bao_cao';
        }
        if (strpos($fileName, 'bien_ban') !== false || strpos($fileName, 'bien-ban') !== false || strpos($fileName, 'bienban') !== false) {
            return 'bien_ban';
        }
        if (strpos($fileName, 'thong_bao') !== false || strpos($fileName, 'thong-bao') !== false || strpos($fileName, 'thongbao') !== false) {
            return 'thong_bao';
        }
        if (strpos($fileName, 'nghi_quyet') !== false || strpos($fileName, 'nghi-quyet') !== false || strpos($fileName, 'nghiquyet') !== false) {
            return 'nghi_quyet';
        }
        
        // Default to cong_van if cannot detect
        return 'cong_van';
    }
    
    /**
     * Detect template subtype from file name
     */
    protected function detectTemplateSubtypeFromFileName(string $fileName): ?string
    {
        $fileName = strtolower($fileName);
        
        // Quyết định subtypes
        if (strpos($fileName, 'bo_nhiem') !== false || strpos($fileName, 'bo-nhiem') !== false || strpos($fileName, 'bonhiem') !== false) {
            return 'bo_nhiem';
        }
        if (strpos($fileName, 'khen_thuong') !== false || strpos($fileName, 'khen-thuong') !== false || strpos($fileName, 'khenthuong') !== false) {
            return 'khen_thuong';
        }
        if (strpos($fileName, 'ky_luat') !== false || strpos($fileName, 'ky-luat') !== false || strpos($fileName, 'kyluat') !== false) {
            return 'ky_luat';
        }
        
        // Công văn subtypes
        if (strpos($fileName, '_di') !== false || strpos($fileName, '-di') !== false || strpos($fileName, 'di.') !== false) {
            return 'di';
        }
        if (strpos($fileName, '_den') !== false || strpos($fileName, '-den') !== false || strpos($fileName, 'den.') !== false) {
            return 'den';
        }
        
        return null;
    }
    
    /**
     * Generate template name from document type and subtype
     */
    protected function generateTemplateName(string $documentType, ?string $subtype): string
    {
        $typeNames = [
            'quyet_dinh' => 'Quyết định',
            'cong_van' => 'Công văn',
            'to_trinh' => 'Tờ trình',
            'bao_cao' => 'Báo cáo',
            'bien_ban' => 'Biên bản',
            'thong_bao' => 'Thông báo',
            'nghi_quyet' => 'Nghị quyết',
        ];
        
        $subtypeNames = [
            'bo_nhiem' => 'Bổ nhiệm',
            'khen_thuong' => 'Khen thưởng',
            'ky_luat' => 'Kỷ luật',
            'di' => 'Đi',
            'den' => 'Đến',
        ];
        
        $name = $typeNames[$documentType] ?? ucfirst(str_replace('_', ' ', $documentType));
        
        if ($subtype && isset($subtypeNames[$subtype])) {
            $name .= ' ' . $subtypeNames[$subtype];
        }
        
        return $name;
    }
    
    /**
     * Extract placeholders from DOCX template
     */
    protected function extractPlaceholdersFromTemplate(string $filePath): array
    {
        try {
            $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($filePath);
            $variables = $templateProcessor->getVariables();
            
            $placeholders = [];
            foreach ($variables as $variable) {
                $placeholders[$variable] = $variable;
            }
            
            return $placeholders;
        } catch (\Exception $e) {
            Log::warning('Failed to extract placeholders from template', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Process documents and index them
     */
    protected function processDocuments(array $files, AiAssistant $assistant)
    {
        foreach ($files as $file) {
            try {
                // Store file
                $path = $file->store('documents', 'public');
                $url = Storage::disk('public')->url($path);
                
                // Extract text
                $text = $this->documentProcessor->extractText($file);
                
                // Count pages (approximate)
                $pageCount = $this->documentProcessor->countPdfPages($file->getRealPath());
                
                // Create document record
                $document = AssistantDocument::create([
                    'ai_assistant_id' => $assistant->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $url,
                    'file_type' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'page_count' => $pageCount,
                    'is_indexed' => false,
                ]);
                
                // Split into chunks and create embeddings
                $this->indexDocument($document, $text);
                
            } catch (\Exception $e) {
                Log::error('Process document error', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName(),
                    'assistant_id' => $assistant->id,
                ]);
            }
        }
    }

    /**
     * Index document with vector embeddings
     */
    protected function indexDocument(AssistantDocument $document, string $text)
    {
        try {
            // Split into chunks
            $chunks = $this->documentProcessor->splitIntoChunks($text);
            
            // Create embeddings for all chunks
            $chunkTexts = array_map(fn($chunk) => $chunk, $chunks);
            $embeddings = $this->vectorSearchService->createEmbeddings($chunkTexts);
            
            // Save chunks with embeddings
            foreach ($chunks as $index => $chunk) {
                $this->vectorSearchService->saveChunk(
                    $document->id,
                    $index,
                    $chunk,
                    $embeddings[$index] ?? [],
                    [
                        'page' => (int) floor($index / 2) + 1, // Approximate page
                        'chunk_index' => $index,
                    ]
                );
            }
            
            // Mark as indexed
            $document->update([
                'is_indexed' => true,
                'status' => 'indexed',
                'chunks_count' => count($chunks),
                'indexed_at' => now(),
            ]);
            
            Log::info('Document indexed successfully', [
                'document_id' => $document->id,
                'chunks_count' => count($chunks),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Index document error', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
            ]);
            
            // Mark as error
            $document->update([
                'status' => 'error',
            ]);
        }
    }

    /**
     * Upload documents to existing assistant
     */
    public function uploadDocuments(Request $request, int $assistantId)
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $assistantId)
            ->where('admin_id', $user->id)
            ->firstOrFail();
        
        // Allow document upload for qa_based_document and report_assistant types
        if (!in_array($assistant->assistant_type, ['qa_based_document', 'report_assistant'])) {
            return response()->json([
                'error' => 'This assistant type does not support document upload',
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'documents' => 'required|array',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $this->processDocuments($request->file('documents'), $assistant);
        
        return response()->json([
            'message' => 'Documents uploaded and indexed successfully',
            'assistant' => $assistant->load('documents'),
        ]);
    }

    /**
     * Delete document
     */
    public function deleteDocument(Request $request, int $assistantId, int $documentId)
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $assistantId)
            ->where('admin_id', $user->id)
            ->firstOrFail();
        
        $document = AssistantDocument::where('id', $documentId)
            ->where('ai_assistant_id', $assistant->id)
            ->firstOrFail();
        
        // Delete file
        if ($document->file_path) {
            $oldPath = str_replace('/storage/', '', parse_url($document->file_path, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }
        
        // Delete chunks (cascade will handle this)
        $document->delete();
        
        return response()->json([
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * Preview assistant
     */
    public function previewAssistant(int $assistantId)
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $assistantId)
            ->where('admin_id', $user->id)
            ->with(['admin', 'documents'])
            ->firstOrFail();
        
        return response()->json([
            'assistant' => $assistant,
            'documents' => $assistant->documents,
            'statistics' => [
                'total_sessions' => $assistant->chatSessions()->count(),
                'total_messages' => $assistant->chatSessions()
                    ->withCount('messages')
                    ->get()
                    ->sum('messages_count'),
                'total_documents' => $assistant->documents()->count(),
            ],
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        $user = Auth::user();
        
        $stats = [
            'total_assistants' => AiAssistant::where('admin_id', $user->id)->count(),
            'active_assistants' => AiAssistant::where('admin_id', $user->id)
                ->where('is_active', true)
                ->count(),
            'total_sessions' => AiAssistant::where('admin_id', $user->id)
                ->withCount('chatSessions')
                ->get()
                ->sum('chat_sessions_count'),
            'total_documents' => AiAssistant::where('admin_id', $user->id)
                ->withCount('documents')
                ->get()
                ->sum('documents_count'),
        ];
        
        return response()->json([
            'statistics' => $stats,
        ]);
    }
    
    /**
     * ✅ NEW: Convert .doc to .docx
     * Tries multiple methods: LibreOffice, unoconv, or PHPWord
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @return \Illuminate\Http\UploadedFile|null
     */
    protected function convertDocToDocx($file)
    {
        try {
            $inputPath = $file->getRealPath();
            $outputPath = sys_get_temp_dir() . '/' . uniqid() . '.docx';
            
            // Method 1: Try LibreOffice (most reliable)
            if ($this->isLibreOfficeAvailable()) {
                $command = sprintf(
                    'libreoffice --headless --convert-to docx --outdir %s %s 2>&1',
                    escapeshellarg(dirname($outputPath)),
                    escapeshellarg($inputPath)
                );
                
                exec($command, $output, $returnCode);
                
                // LibreOffice saves with original name, need to find the converted file
                $convertedFile = dirname($outputPath) . '/' . pathinfo($inputPath, PATHINFO_FILENAME) . '.docx';
                
                if ($returnCode === 0 && file_exists($convertedFile)) {
                    // Rename to our expected path
                    rename($convertedFile, $outputPath);
                    Log::info('✅ [AdminController] Converted using LibreOffice', [
                        'input' => basename($inputPath),
                        'output' => basename($outputPath),
                    ]);
                    
                    // Create UploadedFile from converted file
                    return new \Illuminate\Http\UploadedFile(
                        $outputPath,
                        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.docx',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        null,
                        true // test mode = true (allow temp files)
                    );
                }
            }
            
            // Method 2: Try unoconv
            if ($this->isUnoconvAvailable()) {
                $command = sprintf(
                    'unoconv -f docx -o %s %s 2>&1',
                    escapeshellarg($outputPath),
                    escapeshellarg($inputPath)
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($outputPath)) {
                    Log::info('✅ [AdminController] Converted using unoconv', [
                        'input' => basename($inputPath),
                        'output' => basename($outputPath),
                    ]);
                    
                    return new \Illuminate\Http\UploadedFile(
                        $outputPath,
                        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.docx',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        null,
                        true
                    );
                }
            }
            
            // Method 3: Try PHPWord (less reliable for old .doc format)
            try {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($inputPath, 'MsDoc');
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
                $writer->save($outputPath);
                
                if (file_exists($outputPath)) {
                    Log::info('✅ [AdminController] Converted using PHPWord', [
                        'input' => basename($inputPath),
                        'output' => basename($outputPath),
                    ]);
                    
                    return new \Illuminate\Http\UploadedFile(
                        $outputPath,
                        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.docx',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        null,
                        true
                    );
                }
            } catch (\Exception $e) {
                Log::warning('PHPWord conversion failed', [
                    'error' => $e->getMessage(),
                ]);
            }
            
            Log::warning('⚠️ [AdminController] All conversion methods failed');
            return null;
            
        } catch (\Exception $e) {
            Log::error('Document conversion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
    
    /**
     * Check if LibreOffice is available
     */
    protected function isLibreOfficeAvailable(): bool
    {
        exec('which libreoffice 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Check if unoconv is available
     */
    protected function isUnoconvAvailable(): bool
    {
        exec('which unoconv 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Generate steps for assistant using AI
     */
    public function generateSteps(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'type' => 'required|string',
        ]);

        try {
            $prompt = $this->buildStepsGenerationPrompt(
                $request->name,
                $request->description ?? '',
                $request->type
            );

            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một AI chuyên phân tích và tạo workflow steps cho trợ lý AI. Phân tích mô tả trợ lý và tạo các steps phù hợp. Trả về JSON với format: {"steps": [...]}',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            if (!$result || !isset($result['steps'])) {
                throw new \Exception('Invalid steps response');
            }

            // Format steps với id và order
            $formattedSteps = [];
            foreach ($result['steps'] as $index => $step) {
                $formattedSteps[] = [
                    'id' => $step['id'] ?? "step_" . ($index + 1),
                    'order' => $index + 1,
                    'name' => $step['name'] ?? '',
                    'description' => $step['description'] ?? '',
                    'type' => $step['type'] ?? 'process',
                    'action' => $step['action'] ?? '',
                    'required' => $step['required'] ?? true,
                    'dependencies' => $step['dependencies'] ?? [],
                    'config' => $step['config'] ?? [],
                ];
            }

            return response()->json(['steps' => $formattedSteps]);
        } catch (\Exception $e) {
            Log::error('Generate steps error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Không thể tạo steps tự động: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build prompt for generating steps
     */
    protected function buildStepsGenerationPrompt($name, $description, $type): string
    {
        return "Phân tích trợ lý AI sau và tạo các steps (bước) phù hợp:

Tên trợ lý: {$name}
Mô tả: {$description}
Loại: {$type}

Ví dụ: Nếu là trợ lý 'Viết sách', các steps có thể là:
1. Thu thập thông tin: Tiêu đề, mục đích, đối tượng đọc
2. Lập dàn ý: Tạo dàn ý chi tiết
3. Viết chương 1: Viết nội dung chương đầu
4. Viết chương 2: Viết nội dung chương tiếp theo
...

⚠️ QUAN TRỌNG - Các quy tắc tạo steps:

1. **collect_info steps**: 
   - Phải có `config.questions` (mảng các câu hỏi) hoặc `config.fields` (mảng các field cần extract)
   - Ví dụ: {\"config\": {\"questions\": [\"Tiêu đề là gì?\", \"Mục đích là gì?\"]}}

2. **generate steps** (Tạo nội dung):
   - PHẢI có `config.prompt_template` với placeholders để sử dụng dữ liệu từ step trước
   - Nếu step có dependencies, phải reference các biến từ step trước
   - Biến từ collect_info step sẽ có format: `answer_1`, `answer_2`, `answer_3`, ... (tương ứng với thứ tự câu hỏi)
   - Hoặc nếu dùng fields: `{field_name}` (ví dụ: `{title}`, `{purpose}`)
   - Ví dụ: {\"config\": {\"prompt_template\": \"Dựa trên thông tin: Tiêu đề: {answer_1}, Mục đích: {answer_2}. Hãy tạo dàn ý chi tiết.\"}}

3. **Dependencies**:
   - Nếu step phụ thuộc vào step trước, phải khai báo trong `dependencies` (mảng các step_id)
   - Ví dụ: {\"dependencies\": [\"step_1\"]}

Trả về JSON với format:
{
  \"steps\": [
    {
      \"id\": \"step_1\",
      \"name\": \"Tên step\",
      \"description\": \"Mô tả step\",
      \"type\": \"collect_info|generate|search|process|validate|conditional\",
      \"action\": \"tên_action\",
      \"required\": true,
      \"dependencies\": [],
      \"config\": {
        \"questions\": [\"...\"],  // Nếu type = collect_info
        \"prompt_template\": \"...\"  // Nếu type = generate, PHẢI có để sử dụng dữ liệu từ step trước
      }
    }
  ]
}";
    }

    /**
     * Format steps array
     */
    protected function formatSteps(array $steps): array
    {
        return array_map(function ($step, $index) {
            return [
                'id' => $step['id'] ?? "step_" . ($index + 1),
                'order' => $step['order'] ?? ($index + 1),
                'name' => $step['name'] ?? '',
                'description' => $step['description'] ?? '',
                'type' => $step['type'] ?? 'process',
                'action' => $step['action'] ?? '',
                'required' => $step['required'] ?? true,
                'dependencies' => $step['dependencies'] ?? [],
                'config' => $step['config'] ?? [],
            ];
        }, $steps, array_keys($steps));
    }

    /**
     * ✅ CẢI TIẾN: Kiểm tra xem assistant có cần steps không
     * 
     * @param string $assistantType
     * @param string $name
     * @param string $description
     * @return bool
     */
    protected function shouldAssistantHaveSteps(string $assistantType, string $name, string $description): bool
    {
        // ✅ Q&A assistant KHÔNG cần steps
        if ($assistantType === 'qa_based_document') {
            return false;
        }
        
        // ✅ Document management thường không cần steps phức tạp
        if ($assistantType === 'document_management') {
            return false;
        }
        
        // ✅ Document drafting: Có thể cần steps nếu mô tả yêu cầu workflow
        if ($assistantType === 'document_drafting') {
            // Kiểm tra mô tả có yêu cầu workflow không
            $text = mb_strtolower($name . ' ' . $description);
            $workflowKeywords = ['bước', 'quy trình', 'workflow', 'research', 'bao quát', 'từng bước', 'tuần tự'];
            
            foreach ($workflowKeywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return true;
                }
            }
            
            // Mặc định document_drafting không cần steps (đã có template system)
            return false;
        }
        
        // ✅ Các assistant khác: Phân tích bằng AI
        return $this->analyzeIfNeedsSteps($name, $description);
    }

    /**
     * ✅ CẢI TIẾN: Phân tích bằng AI xem assistant có cần steps không
     */
    protected function analyzeIfNeedsSteps(string $name, string $description): bool
    {
        try {
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Bạn là một AI chuyên phân tích xem một trợ lý AI có cần workflow steps (các bước) hay không.\n\n"
                            . "**CẦN STEPS KHI:**\n"
                            . "- Trợ lý cần thực hiện nhiều bước tuần tự: \"Viết sách\", \"Tạo kế hoạch dự án\", \"Research và báo cáo\"\n"
                            . "- Trợ lý cần thu thập thông tin từng bước: \"Tạo báo cáo thường niên\", \"Lập kế hoạch chi tiết\"\n"
                            . "- Trợ lý cần research và tổng hợp: \"Tìm hiểu và báo cáo về...\", \"Phân tích và đưa ra kết luận\"\n\n"
                            . "**KHÔNG CẦN STEPS KHI:**\n"
                            . "- Trợ lý chỉ cần trả lời câu hỏi: \"Q&A\", \"Hỏi đáp\", \"Trả lời câu hỏi\"\n"
                            . "- Trợ lý chỉ cần tạo một loại văn bản đơn giản: \"Soạn thảo công văn\" (đã có template)\n"
                            . "- Trợ lý chỉ cần tìm kiếm và trả lời: \"Tìm kiếm thông tin\"\n\n"
                            . "**YÊU CẦU:**\n"
                            . "Trả về JSON: {\"needs_steps\": true/false, \"confidence\": 0.0-1.0, \"reason\": \"lý do\"}",
                    ],
                    [
                        'role' => 'user',
                        'content' => "Tên trợ lý: {$name}\nMô tả: {$description}\n\nPhân tích xem trợ lý này có cần steps không?",
                    ],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            if ($result && isset($result['needs_steps'])) {
                $needsSteps = (bool) $result['needs_steps'];
                $confidence = $result['confidence'] ?? 0.5;
                
                Log::info('Steps analysis with AI', [
                    'name' => $name,
                    'needs_steps' => $needsSteps,
                    'confidence' => $confidence,
                    'reason' => $result['reason'] ?? null,
                ]);
                
                return $needsSteps && $confidence >= 0.7;
            }
        } catch (\Exception $e) {
            Log::warning('Steps analysis with AI failed', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);
        }
        
        // Fallback: Mặc định không cần steps
        return false;
    }

    /**
     * ✅ CẢI TIẾN: Kiểm tra xem có nên tự động tạo steps không
     */
    protected function shouldAutoGenerateSteps(string $assistantType, string $name, string $description): bool
    {
        // Chỉ tự động tạo cho các assistant cần steps
        if (!$this->shouldAssistantHaveSteps($assistantType, $name, $description)) {
            return false;
        }
        
        // Tự động tạo nếu mô tả rõ ràng về workflow
        $text = mb_strtolower($name . ' ' . $description);
        $autoGenerateKeywords = ['viết sách', 'tạo kế hoạch', 'research', 'phân tích', 'báo cáo chi tiết'];
        
        foreach ($autoGenerateKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * ✅ CẢI TIẾN: Tự động tạo steps bằng AI
     */
    protected function autoGenerateSteps(string $name, string $description, string $assistantType): array
    {
        try {
            $prompt = $this->buildStepsGenerationPrompt($name, $description, $assistantType);

            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một AI chuyên phân tích và tạo workflow steps cho trợ lý AI. Phân tích mô tả trợ lý và tạo các steps phù hợp. Trả về JSON với format: {"steps": [...]}',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            if (!$result || !isset($result['steps'])) {
                throw new \Exception('Invalid steps response');
            }

            // Format steps với id và order
            $formattedSteps = [];
            foreach ($result['steps'] as $index => $step) {
                $formattedSteps[] = [
                    'id' => $step['id'] ?? "step_" . ($index + 1),
                    'order' => $index + 1,
                    'name' => $step['name'] ?? '',
                    'description' => $step['description'] ?? '',
                    'type' => $step['type'] ?? 'process',
                    'action' => $step['action'] ?? '',
                    'required' => $step['required'] ?? true,
                    'dependencies' => $step['dependencies'] ?? [],
                    'config' => $step['config'] ?? [],
                ];
            }

            return $formattedSteps;
        } catch (\Exception $e) {
            Log::error('Auto-generate steps error', [
                'error' => $e->getMessage(),
                'name' => $name,
            ]);
            throw $e;
        }
    }
    
    /**
     * Store new assistant type
     */
    public function storeAssistantType(Request $request)
    {
        // Generate code from name if not provided
        $name = $request->input('name', '');
        $code = $request->input('code');
        
        if (empty($code) && !empty($name)) {
            $code = $this->generateCodeFromName($name);
        }
        
        // Ensure code is unique by appending number if needed
        $originalCode = $code;
        $counter = 1;
        while (\App\Models\AssistantType::where('code', $code)->exists()) {
            $code = $originalCode . '_' . $counter;
            $counter++;
        }
        
        $validator = Validator::make(array_merge($request->all(), ['code' => $code]), [
            'code' => 'required|string|max:50|unique:assistant_types,code|regex:/^[a-z0-9_]+$/',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string', // ✅ MỚI: System prompt
            'system_prompt_template' => 'nullable|string', // ✅ MỚI: Template prompt
            'is_active' => 'boolean',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator->errors());
        }
        
        $data = $validator->validated();
        $data['code'] = $code; // Use generated code
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        
        $assistantType = \App\Models\AssistantType::create($data);
        
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Loại Assistant đã được tạo thành công',
                'assistant_type' => $assistantType,
            ], 201);
        }
        
        return redirect()->route('admin.assistant-types.index')
            ->with('success', 'Loại Assistant đã được tạo thành công');
    }

    /**
     * Update assistant type
     */
    public function updateAssistantType(Request $request, int $typeId)
    {
        $assistantType = \App\Models\AssistantType::findOrFail($typeId);
        
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:50', Rule::unique('assistant_types', 'code')->ignore($typeId), 'regex:/^[a-z0-9_]+$/'],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string', // ✅ MỚI: System prompt
            'system_prompt_template' => 'nullable|string', // ✅ MỚI: Template prompt
            'is_active' => 'boolean',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator->errors());
        }
        
        $data = $validator->validated();
        $assistantType->update($data);
        
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Loại Assistant đã được cập nhật thành công',
                'assistant_type' => $assistantType,
            ]);
        }
        
        return redirect()->route('admin.assistant-types.index')
            ->with('success', 'Loại Assistant đã được cập nhật thành công');
    }

    /**
     * Delete assistant type
     */
    public function deleteAssistantType(Request $request, int $typeId)
    {
        $assistantType = \App\Models\AssistantType::findOrFail($typeId);
        
        // Kiểm tra xem có Assistant nào đang sử dụng loại này không
        $assistantsCount = \App\Models\AiAssistant::where('assistant_type', $assistantType->code)->count();
        
        if ($assistantsCount > 0) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'error' => 'Không thể xóa loại Assistant này vì đang có ' . $assistantsCount . ' Assistant đang sử dụng.',
                ], 422);
            }
            
            return redirect()->back()
                ->with('error', 'Không thể xóa loại Assistant này vì đang có ' . $assistantsCount . ' Assistant đang sử dụng.');
        }
        
        $assistantType->delete();
        
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Loại Assistant đã được xóa thành công',
            ]);
        }
        
        return redirect()->route('admin.assistant-types.index')
            ->with('success', 'Loại Assistant đã được xóa thành công');
    }

    /**
     * Generate code from Vietnamese name
     */
    protected function generateCodeFromName(string $name): string
    {
        // Convert to lowercase
        $code = mb_strtolower($name, 'UTF-8');
        
        // Remove Vietnamese diacritics
        $code = $this->removeVietnameseDiacritics($code);
        
        // Replace đ with d
        $code = str_replace('đ', 'd', $code);
        
        // Replace special characters and keep only alphanumeric, spaces, and hyphens
        $code = preg_replace('/[^a-z0-9\s-]/', '', $code);
        
        // Replace spaces and hyphens with underscores
        $code = preg_replace('/[\s-]+/', '_', $code);
        
        // Replace multiple underscores with single underscore
        $code = preg_replace('/_+/', '_', $code);
        
        // Remove leading/trailing underscores
        $code = trim($code, '_');
        
        return $code;
    }
    
    /**
     * Remove Vietnamese diacritics
     */
    protected function removeVietnameseDiacritics(string $text): string
    {
        $vietnamese = [
            'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
            'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
            'ì', 'í', 'ị', 'ỉ', 'ĩ',
            'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
            'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
            'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
            'đ',
            'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
            'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
            'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
            'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
            'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
            'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ',
            'Đ'
        ];
        
        $english = [
            'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
            'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
            'i', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
            'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
            'y', 'y', 'y', 'y', 'y',
            'd',
            'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
            'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
            'i', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
            'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
            'y', 'y', 'y', 'y', 'y',
            'd'
        ];
        
        return str_replace($vietnamese, $english, $text);
    }

    /**
     * Retry crawling a reference URL
     */
    public function retryCrawlReferenceUrl(Request $request, int $referenceUrlId)
    {
        $user = Auth::user();
        
        $referenceUrl = \App\Models\AssistantReferenceUrl::where('id', $referenceUrlId)
            ->whereHas('aiAssistant', function ($q) use ($user) {
                $q->where('admin_id', $user->id);
            })
            ->firstOrFail();
        
        // Update status to pending
        $referenceUrl->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
        
        // Queue crawl job
        dispatch(new \App\Jobs\CrawlReferenceUrlsJob($referenceUrl->ai_assistant_id));
        
        Log::info('Retry crawl queued for reference URL', [
            'reference_url_id' => $referenceUrlId,
            'assistant_id' => $referenceUrl->ai_assistant_id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Đã queue job crawl lại. Vui lòng đợi vài phút.',
        ]);
    }
}

