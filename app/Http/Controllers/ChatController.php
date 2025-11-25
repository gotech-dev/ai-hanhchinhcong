<?php

namespace App\Http\Controllers;

use App\Models\AiAssistant;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\SmartAssistantEngine;
use App\Services\VectorSearchService;
use App\Services\DocumentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        protected SmartAssistantEngine $assistantEngine,
        protected VectorSearchService $vectorSearchService,
        protected DocumentProcessor $documentProcessor
    ) {}

    /**
     * Get or create chat session
     */
    public function getOrCreateSession(Request $request, int $assistantId)
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $assistantId)
            ->where('is_active', true)
            ->firstOrFail();
        
        // Check if user wants to create a new session (force new)
        $forceNew = $request->boolean('new', false);
        
        if ($forceNew) {
            // Always create a new session
            $session = ChatSession::create([
                'user_id' => $user->id,
                'ai_assistant_id' => $assistantId,
                'title' => $assistant->name,
                'workflow_state' => null,
                'collected_data' => [],
            ]);
        } else {
            // Get existing session or create new one
            $session = ChatSession::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'ai_assistant_id' => $assistantId,
                ],
                [
                    'title' => $assistant->name,
                    'workflow_state' => null,
                    'collected_data' => [],
                ]
            );
        }
        
        // Load messages to check if session is new
        $session->load(['messages' => function ($query) {
            $query->orderBy('created_at');
        }, 'aiAssistant']);
        
        // Get expected greeting message from assistant
        // Special handling for document_drafting and report_assistant: include template list
        $greetingMetadata = null;
        $assistantType = $assistant->getAssistantTypeValue();
        
        if ($assistantType === 'document_drafting' || $assistantType === 'report_assistant') {
            $templates = $assistant->documentTemplates()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
            
            \Log::info('🔵 [ChatController] Checking templates for greeting', [
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistantType,
                'templates_count' => $templates->count(),
                'templates' => $templates->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'has_html_preview' => !empty($t->metadata['html_preview']),
                        'html_preview_length' => isset($t->metadata['html_preview']) ? strlen($t->metadata['html_preview']) : 0,
                    ];
                })->toArray(),
            ]);
            
            if ($templates->isNotEmpty()) {
                $templateNames = $templates->pluck('name')->toArray();
                $templateList = implode(', ', $templateNames);
                
                if ($assistantType === 'document_drafting') {
                    $expectedGreetingMessage = "Xin chào bạn. Mình là {$assistant->name}. Mình có thể giúp bạn tạo nhanh các văn bản mẫu.";
                } else {
                    // report_assistant
                    $expectedGreetingMessage = "Xin chào bạn. Mình là {$assistant->name}. Mình có thể giúp bạn tạo nhanh các mẫu báo cáo.";
                }
                
                // ✅ MỚI: Thêm template info vào metadata để frontend render button (giống document_drafting)
                $primaryTemplate = $templates->count() === 1 ? $templates->first() : null;
                
                $greetingMetadata = [
                    'has_template' => true,
                    'template_count' => $templates->count(),
                    'templates' => $templates->map(function ($template) {
                        return [
                            'id' => $template->id,
                            'name' => $template->name,
                            'document_type' => $template->document_type,
                            'has_html_preview' => !empty($template->metadata['html_preview']),
                        ];
                    })->toArray(),
                    'primary_template' => $primaryTemplate ? [
                        'id' => $primaryTemplate->id,
                        'name' => $primaryTemplate->name,
                        'document_type' => $primaryTemplate->document_type,
                    ] : null,
                ];
            } else {
                $expectedGreetingMessage = $assistant->greeting_message ?? "Xin chào bạn. Mình là {$assistant->name}. Mình rất vui được giúp đỡ bạn.";
                
                \Log::info('🔵 [ChatController] No templates found for assistant', [
                    'assistant_id' => $assistant->id,
                    'assistant_type' => $assistantType,
                ]);
            }
        } else {
            $expectedGreetingMessage = $assistant->greeting_message ?? "Xin chào bạn. Mình là {$assistant->name}. Mình rất vui được giúp đỡ bạn.";
        }
        
        // Create greeting message if session is new and has no messages
        if ($session->wasRecentlyCreated || $session->messages->isEmpty()) {
            // ✅ MỚI: Build metadata với template_info (không thêm template_html cho report_assistant)
            $messageMetadata = null;
            if ($greetingMetadata) {
                $messageMetadata = ['template_info' => $greetingMetadata];
            }
            
            \Log::info('🔵 [ChatController] Creating greeting message with metadata', [
                'session_id' => $session->id,
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistantType,
                'has_template_info' => !empty($messageMetadata['template_info']),
                'has_template_preview' => !empty($messageMetadata['template_preview']),
                'has_template_html' => !empty($messageMetadata['template_html']),
            ]);
            
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'sender' => 'assistant',
                'content' => $expectedGreetingMessage,
                'metadata' => $messageMetadata,
                'created_at' => now(),
            ]);
            
            // Reload messages to include greeting
            $session->load(['messages' => function ($query) {
                $query->orderBy('created_at');
            }, 'aiAssistant']);
        } else {
            // Find first assistant message (should be greeting)
            $firstAssistantMessage = $session->messages
                ->where('sender', 'assistant')
                ->first();
            
            if ($firstAssistantMessage) {
                // ✅ MỚI: Build metadata với template_info (không thêm template_html cho report_assistant)
                $messageMetadata = null;
                if ($greetingMetadata) {
                    $messageMetadata = ['template_info' => $greetingMetadata];
                }
                
                // ✅ MỚI: LUÔN update greeting message với metadata mới nhất (đặc biệt cho report_assistant)
                // Đảm bảo greeting message luôn có metadata template mới nhất
                $currentMetadata = $firstAssistantMessage->metadata ?? [];
                $currentMetadataJson = json_encode($currentMetadata ?? []);
                $newMetadataJson = json_encode($messageMetadata ?? []);
                
                // Update nếu content khác HOẶC metadata khác HOẶC là report_assistant (để đảm bảo metadata luôn được update)
                $needsUpdate = $firstAssistantMessage->content !== $expectedGreetingMessage 
                    || $currentMetadataJson !== $newMetadataJson
                    || ($assistantType === 'report_assistant' && $greetingMetadata && empty($currentMetadata['template_info']));
                
                if ($needsUpdate) {
                    \Log::info('🔵 [ChatController] Updating greeting message with metadata', [
                        'session_id' => $session->id,
                        'assistant_id' => $assistant->id,
                        'assistant_type' => $assistantType,
                        'has_template_info' => !empty($messageMetadata['template_info']),
                        'has_template_preview' => !empty($messageMetadata['template_preview']),
                        'has_template_html' => !empty($messageMetadata['template_html']),
                        'current_metadata' => $currentMetadata,
                        'new_metadata' => $messageMetadata,
                    ]);
                    
                    $firstAssistantMessage->update([
                        'content' => $expectedGreetingMessage,
                        'metadata' => $messageMetadata,
                    ]);
                    
                    // Reload messages
                    $session->load(['messages' => function ($query) {
                        $query->orderBy('created_at');
                    }, 'aiAssistant']);
                } else {
                    \Log::info('🔵 [ChatController] Greeting message already up to date', [
                        'session_id' => $session->id,
                        'assistant_id' => $assistant->id,
                        'assistant_type' => $assistantType,
                        'has_metadata' => !empty($firstAssistantMessage->metadata),
                    ]);
                }
            } else {
                // No assistant greeting message found, insert it at the beginning
                // Get the earliest message timestamp
                $earliestMessage = $session->messages->first();
                $greetingTimestamp = $earliestMessage 
                    ? \Carbon\Carbon::parse($earliestMessage->created_at)->subSecond()
                    : now();
                
                // ✅ MỚI: Build metadata với template_info (không thêm template_html cho report_assistant)
                $messageMetadata = null;
                if ($greetingMetadata) {
                    $messageMetadata = ['template_info' => $greetingMetadata];
                }
                
                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'sender' => 'assistant',
                    'content' => $expectedGreetingMessage,
                    'metadata' => $messageMetadata,
                    'created_at' => $greetingTimestamp,
                ]);
                
                // Reload messages
                $session->load(['messages' => function ($query) {
                    $query->orderBy('created_at');
                }, 'aiAssistant']);
            }
        }
        
        return response()->json([
            'session' => $session,
        ]);
    }

    /**
     * Get chat history
     */
    public function getHistory(Request $request, int $sessionId)
    {
        $user = Auth::user();
        
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at');
            }, 'aiAssistant'])
            ->firstOrFail();
        
        return response()->json([
            'session' => $session,
            'messages' => $session->messages,
        ]);
    }

    /**
     * Send message and get response
     * 
     * ⚠️ DEPRECATED: This endpoint is deprecated in favor of streamChat().
     * For better UX and consistent behavior, all chat should use streaming.
     * This method now redirects to streamChat() for backward compatibility.
     * 
     * @deprecated Use streamChat() instead
     */
    public function sendMessage(Request $request, int $sessionId)
    {
        Log::info('⚠️ [DEPRECATED] sendMessage() called, redirecting to streamChat()', [
            'session_id' => $sessionId,
            'user_id' => Auth::id(),
        ]);
        
        // ✅ Option 3: Redirect to streamChat() for backward compatibility
        // Note: This will return SSE stream, not JSON response
        // Frontend should use streamChat() endpoint directly
        return $this->streamChat($request, $sessionId);
    }

    /**
     * Stream chat response (SSE)
     */
    public function streamChat(Request $request, int $sessionId): StreamedResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array',
            'template_id' => 'nullable|integer|exists:document_templates,id',
        ]);
        
        $user = Auth::user();
        
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->with('aiAssistant')
            ->firstOrFail();
        
        $userMessage = $request->input('message', '');
        $attachments = $request->input('attachments', []);
        $templateId = $request->input('template_id');

        // ✅ MỚI: Nếu có template_id, lưu vào collected_data của session
        if ($templateId) {
            $collectedData = $session->collected_data ?? [];
            $collectedData['template_id'] = $templateId;
            $session->collected_data = $collectedData;
            $session->save();
            
            Log::info('🔵 [ChatController] Template ID saved to session', [
                'session_id' => $session->id,
                'template_id' => $templateId,
            ]);
        }
        
        // Build message content with attachments info
        $messageContent = $userMessage;
        if (!empty($attachments)) {
            $fileNames = array_column($attachments, 'name');
            $messageContent = $messageContent 
                ? $messageContent . "\n\n[Đã đính kèm: " . implode(', ', $fileNames) . "]"
                : "[Đã đính kèm: " . implode(', ', $fileNames) . "]";
        }
        
        // Require either message or attachments
        if (empty($messageContent)) {
            return response()->json([
                'error' => 'Message hoặc file đính kèm là bắt buộc',
            ], 400);
        }
        
        // Save user message with attachments metadata
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'sender' => 'user',
            'content' => $messageContent,
            'message_type' => !empty($attachments) ? 'document' : 'text',
            'metadata' => [
                'attachments' => $attachments,
                'original_message' => $userMessage,
            ],
            'created_at' => now(),
        ]);
        
        $documentProcessor = $this->documentProcessor;
        
        // Note: report_generator has been merged into document_drafting
        // All report generation is now handled by document_drafting assistant
        
        // ✅ FIX: Tắt output buffering và set headers cho SSE
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $response = new StreamedResponse(function () use ($session, $userMessage, $attachments, $documentProcessor) {
            try {
                // ✅ FIX: Tắt output buffering trong callback
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // ✅ DEBUG: Log ngay khi bắt đầu stream
                Log::info('🚀 [STREAM DEBUG] StreamChat started', [
                    'session_id' => $session->id,
                    'timestamp' => microtime(true),
                ]);
                
                // ✅ PHASE 2 FIX: Gửi loading status NGAY LẬP TỨC khi nhận request
                // Không đợi bất cứ xử lý nào, để user thấy feedback ngay
                $loadingStatus = json_encode([
                    'type' => 'status',
                    'status' => 'processing',
                    'message' => 'Đang xử lý yêu cầu của bạn...',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                Log::info('🚀 [STREAM DEBUG] Sending loading status', [
                    'session_id' => $session->id,
                    'timestamp' => microtime(true),
                ]);
                
                echo "data: " . $loadingStatus . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                
                Log::info('🚀 [STREAM DEBUG] Loading status sent and flushed', [
                    'session_id' => $session->id,
                    'timestamp' => microtime(true),
                ]);
                
                // ✅ FIX: Kiểm tra xem assistant có steps không
                // Nếu có steps, luôn gọi SmartAssistantEngine để thực thi steps
                $assistant = $session->aiAssistant;
                $config = $assistant->config ?? [];
                $predefinedSteps = $config['steps'] ?? null;
                $hasSteps = $predefinedSteps && is_array($predefinedSteps) && count($predefinedSteps) > 0;
                
                // ✅ LOG: Check for steps
                Log::info('🔵 [ChatController] Checking for predefined steps', [
                    'session_id' => $session->id,
                    'assistant_id' => $assistant->id,
                    'assistant_type' => $assistant->getAssistantTypeValue() ?? 'unknown',
                    'has_steps' => $hasSteps,
                    'steps_count' => $hasSteps ? count($predefinedSteps) : 0,
                ]);
                
                // ✅ PHASE 2 FIX: LUÔN gọi SmartAssistantEngine với streaming callback
                // Không chỉ khi có steps, mà cho TẤT CẢ các assistant types
                // SmartAssistantEngine sẽ tự xử lý intent recognition và routing
                $shouldUseSmartAssistant = true; // ✅ LUÔN dùng SmartAssistantEngine để có streaming
                
                // Nếu có steps, luôn gọi SmartAssistantEngine
                if ($hasSteps || $shouldUseSmartAssistant) {
                    Log::info('🔵 [ChatController] Assistant has steps, calling SmartAssistantEngine', [
                        'session_id' => $session->id,
                        'assistant_id' => $assistant->id,
                    ]);
                    
                    // ✅ PHASE 2: Gọi SmartAssistantEngine với streaming callback để stream trực tiếp từ OpenAI
                    $responseMessage = '';
                    $chunkCount = 0;
                    
                    Log::info('🚀 [STREAM DEBUG] Calling processMessage with streaming callback', [
                        'session_id' => $session->id,
                        'timestamp' => microtime(true),
                    ]);
                    
                    $result = $this->assistantEngine->processMessage(
                        $userMessage,
                        $session,
                        $assistant,
                        function($chunk) use (&$responseMessage, &$chunkCount, $session) { // ✅ PHASE 2: Streaming callback
                            $chunkCount++;
                            $responseMessage .= $chunk; // Accumulate full response
                            
                            // ✅ DEBUG: Log mỗi chunk
                            if ($chunkCount === 1) {
                                Log::info('🚀 [STREAM DEBUG] First chunk received', [
                                    'session_id' => $session->id,
                                    'chunk_size' => strlen($chunk),
                                    'chunk_preview' => substr($chunk, 0, 50),
                                    'timestamp' => microtime(true),
                                ]);
                            }
                            
                            $chunkData = json_encode([
                                'type' => 'content',
                                'content' => $chunk,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            echo "data: " . $chunkData . "\n\n";
                            if (ob_get_level() > 0) {
                                ob_flush();
                            }
                            flush();
                        }
                    );
                    
                    Log::info('🚀 [STREAM DEBUG] processMessage completed', [
                        'session_id' => $session->id,
                        'chunk_count' => $chunkCount,
                        'total_length' => strlen($responseMessage),
                        'timestamp' => microtime(true),
                    ]);
                    
                    // Update session workflow state
                    if ($result['workflow_state']) {
                        $session->update([
                            'workflow_state' => $result['workflow_state'],
                        ]);
                    }
                    
                    // ✅ PHASE 2: Response đã được stream, không cần fake streaming nữa
                    // $responseMessage đã được accumulate trong callback
                    
                    // Prepare document data for SSE response (if any)
                    $documentData = null;
                    if (isset($result['document'])) {
                        $documentData = [
                            'file_path' => $result['document']['file_path'] ?? null,
                            'document_type' => $result['document']['metadata']['document_type'] ?? null,
                            'document_type_display' => $result['document']['metadata']['document_type_display'] ?? null,
                            'template_used' => $result['document']['metadata']['template_used'] ?? false,
                            'template_id' => $result['document']['metadata']['template_id'] ?? null,
                        ];
                    }
                    
                    // ✅ PHASE 2: Use accumulated response message (đã được stream)
                    $finalResponse = $responseMessage ?: $result['response'];
                    
                    // ✅ FIX: Build metadata including template_html if present
                    $messageMetadata = [
                        'document' => $documentData,
                        'workflow_state' => $result['workflow_state'] ?? null,
                    ];
                    
                    // ✅ FIX: Add template_html from result metadata if present
                    if (isset($result['metadata']['template_html'])) {
                        $messageMetadata['template_html'] = $result['metadata']['template_html'];
                        $messageMetadata['template_preview'] = $result['metadata']['template_preview'] ?? true;
                        $messageMetadata['content_type'] = $result['metadata']['content_type'] ?? 'html';
                        if (isset($result['metadata']['template_id'])) {
                            $messageMetadata['template_id'] = $result['metadata']['template_id'];
                        }
                        if (isset($result['metadata']['template_name'])) {
                            $messageMetadata['template_name'] = $result['metadata']['template_name'];
                        }
                        
                        Log::info('✅ [ChatController] Adding template_html to message metadata', [
                            'session_id' => $session->id,
                            'template_id' => $result['metadata']['template_id'] ?? null,
                            'html_length' => strlen($result['metadata']['template_html']),
                        ]);
                    }
                    
                    // Save assistant message
                    $assistantMessage = ChatMessage::create([
                        'chat_session_id' => $session->id,
                        'sender' => 'assistant',
                        'content' => $finalResponse,
                        'message_type' => 'text',
                        'created_at' => now(),
                        'metadata' => $messageMetadata,
                    ]);
                    
                    // Send completion event
                    $sseData = [
                        'type' => 'done',
                        'message_id' => $assistantMessage->id,
                    ];
                    
                    if ($documentData) {
                        $sseData['document'] = $documentData;
                    }
                    
                    // ✅ FIX: Include metadata in SSE response if template_html is present
                    if (isset($messageMetadata['template_html'])) {
                        $sseData['metadata'] = [
                            'template_html' => $messageMetadata['template_html'],
                            'template_preview' => $messageMetadata['template_preview'] ?? true,
                            'content_type' => $messageMetadata['content_type'] ?? 'html',
                            'template_id' => $messageMetadata['template_id'] ?? null,
                            'template_name' => $messageMetadata['template_name'] ?? null,
                        ];
                        
                        Log::info('✅ [ChatController] Including template_html metadata in SSE response', [
                            'session_id' => $session->id,
                            'message_id' => $assistantMessage->id,
                            'template_id' => $messageMetadata['template_id'] ?? null,
                            'html_length' => strlen($messageMetadata['template_html']),
                        ]);
                    }
                    
                    $jsonData = json_encode($sseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    echo "data: " . $jsonData . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                        flush();
                        
                        return; // Exit early, don't stream from OpenAI
                }
                
                // ✅ MỚI: Kiểm tra nếu là document_drafting assistant và user yêu cầu tạo document
                // Thì gọi SmartAssistantEngine thay vì stream từ OpenAI
                // ✅ MỚI: Cũng xử lý report_assistant với create_report intent
                if (in_array($assistant->getAssistantTypeValue(), ['document_drafting', 'report_assistant'])) {
                    // ✅ LOG: Checking if document drafting or report creation request
                    Log::info('🔵 [ChatController] Checking document/report creation request', [
                        'session_id' => $session->id,
                        'assistant_id' => $assistant->id,
                        'assistant_type' => $assistant->getAssistantTypeValue(),
                        'user_message' => substr($userMessage, 0, 200),
                    ]);
                    
                    // Recognize intent để xem có phải draft_document hoặc create_report không
                    $intentRecognizer = app(\App\Services\IntentRecognizer::class);
                    $context = [
                        'session' => $session,
                        'assistant' => $assistant,
                        'collected_data' => $session->collected_data ?? [],
                        'workflow_state' => $session->workflow_state ?? null,
                    ];
                    $intent = $intentRecognizer->recognize($userMessage, $context);
                    
                    // ✅ LOG: Intent recognized
                    Log::info('🔵 [ChatController] Intent recognized', [
                        'session_id' => $session->id,
                        'assistant_type' => $assistant->getAssistantTypeValue(),
                        'intent_type' => $intent['type'] ?? null,
                        'intent_confidence' => $intent['confidence'] ?? null,
                    ]);
                    
                    // ✅ MỚI: Xử lý cả draft_document (document_drafting) và create_report (report_assistant)
                    $isDocumentDrafting = $assistant->getAssistantTypeValue() === 'document_drafting' && ($intent['type'] ?? null) === 'draft_document';
                    $isReportCreation = $assistant->getAssistantTypeValue() === 'report_assistant' && ($intent['type'] ?? null) === 'create_report';
                    
                    if ($isDocumentDrafting || $isReportCreation) {
                        Log::info('🔵 [ChatController] Calling SmartAssistantEngine for document/report creation', [
                            'session_id' => $session->id,
                            'assistant_id' => $session->aiAssistant->id,
                            'assistant_type' => $assistant->getAssistantTypeValue(),
                            'intent_type' => $intent['type'],
                        ]);
                        
                        // ✅ PHASE 2 FIX: Loading status đã được gửi ở đầu function, không cần gửi lại
                        // Chỉ cần update message nếu cần
                        $statusMessage = $isReportCreation ? 'Đang tạo báo cáo từ mẫu...' : 'Đang soạn thảo văn bản...';
                        $draftingStatus = json_encode([
                            'type' => 'status',
                            'status' => 'processing',
                            'message' => $statusMessage,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        echo "data: " . $draftingStatus . "\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                        
                        // ✅ PHASE 2: Gọi SmartAssistantEngine với streaming callback để stream trực tiếp từ OpenAI
                        $responseMessage = '';
                        $result = $this->assistantEngine->processMessage(
                            $userMessage,
                            $session,
                            $session->aiAssistant,
                            function($chunk) use (&$responseMessage) { // ✅ PHASE 2: Streaming callback
                                $responseMessage .= $chunk; // Accumulate full response
                                $chunkData = json_encode([
                                    'type' => 'content',
                                    'content' => $chunk,
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                echo "data: " . $chunkData . "\n\n";
                                if (ob_get_level() > 0) {
                                    ob_flush();
                                }
                                flush();
                            }
                        );
                        
                        // ✅ LOG: Document/report creation result
                        Log::info('✅ [ChatController] Document/report creation completed', [
                            'session_id' => $session->id,
                            'assistant_type' => $assistant->getAssistantTypeValue(),
                            'has_document' => isset($result['document']),
                            'document_file_path' => $result['document']['file_path'] ?? null,
                            'template_used' => $result['document']['metadata']['template_used'] ?? false,
                        ]);
                        
                        // Update session workflow state
                        if ($result['workflow_state']) {
                            $session->update([
                                'workflow_state' => $result['workflow_state'],
                            ]);
                        }
                        
                        // ✅ PHASE 2: Response đã được stream, không cần fake streaming nữa
                        // $responseMessage đã được accumulate trong callback
                        
                        // Prepare document data for SSE response
                        $documentData = null;
                        if (isset($result['document'])) {
                            $documentData = [
                                'file_path' => $result['document']['file_path'] ?? null,
                                'document_type' => $result['document']['metadata']['document_type'] ?? null,
                                'document_type_display' => $result['document']['metadata']['document_type_display'] ?? null,
                                'template_used' => $result['document']['metadata']['template_used'] ?? false,
                                'template_id' => $result['document']['metadata']['template_id'] ?? null,
                            ];
                            
                            // ✅ LOG: Document data prepared
                            Log::info('✅ [ChatController] Document data prepared for SSE', [
                                'session_id' => $session->id,
                                'file_path' => $documentData['file_path'],
                                'template_used' => $documentData['template_used'],
                            ]);
                        }
                        
                        // ✅ PHASE 2: Use accumulated response message (đã được stream)
                        $finalResponse = $responseMessage ?: $result['response'];
                        
                        // ✅ FIX: Build metadata including template_html if present
                        $messageMetadata = [
                            'document' => $documentData,
                            'workflow_state' => $result['workflow_state'] ?? null,
                        ];
                        
                        // ✅ FIX: Add template_html from result metadata if present
                        if (isset($result['metadata']['template_html'])) {
                            $messageMetadata['template_html'] = $result['metadata']['template_html'];
                            $messageMetadata['template_preview'] = $result['metadata']['template_preview'] ?? true;
                            $messageMetadata['content_type'] = $result['metadata']['content_type'] ?? 'html';
                            if (isset($result['metadata']['template_id'])) {
                                $messageMetadata['template_id'] = $result['metadata']['template_id'];
                            }
                            if (isset($result['metadata']['template_name'])) {
                                $messageMetadata['template_name'] = $result['metadata']['template_name'];
                            }
                            
                            Log::info('✅ [ChatController] Adding template_html to message metadata', [
                                'session_id' => $session->id,
                                'template_id' => $result['metadata']['template_id'] ?? null,
                                'html_length' => strlen($result['metadata']['template_html']),
                            ]);
                        }
                        
                        // Save assistant message với document metadata
                        $assistantMessage = ChatMessage::create([
                            'chat_session_id' => $session->id,
                            'sender' => 'assistant',
                            'content' => $finalResponse,
                            'message_type' => 'text',
                            'created_at' => now(),
                            'metadata' => $messageMetadata,
                        ]);
                        
                        // Send completion event with document data
                        $sseData = [
                            'type' => 'done',
                            'message_id' => $assistantMessage->id,
                        ];
                        
                        if ($documentData) {
                            $sseData['document'] = $documentData;
                            Log::info('✅ [ChatController] Including document in SSE response', [
                                'session_id' => $session->id,
                                'file_path' => $documentData['file_path'],
                            ]);
                        } else {
                            $sseData['document'] = null;
                            Log::warning('⚠️ [ChatController] No document data to include in SSE response', [
                                'session_id' => $session->id,
                            ]);
                        }
                        
                        // ✅ FIX: Include metadata in SSE response if template_html is present
                        if (isset($messageMetadata['template_html'])) {
                            $sseData['metadata'] = [
                                'template_html' => $messageMetadata['template_html'],
                                'template_preview' => $messageMetadata['template_preview'] ?? true,
                                'content_type' => $messageMetadata['content_type'] ?? 'html',
                                'template_id' => $messageMetadata['template_id'] ?? null,
                                'template_name' => $messageMetadata['template_name'] ?? null,
                            ];
                            
                            Log::info('✅ [ChatController] Including template_html metadata in SSE response', [
                                'session_id' => $session->id,
                                'message_id' => $assistantMessage->id,
                                'template_id' => $messageMetadata['template_id'] ?? null,
                                'html_length' => strlen($messageMetadata['template_html']),
                            ]);
                        }
                        
                        // ✅ FIX: Ensure proper JSON encoding and SSE format
                        $jsonData = json_encode($sseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        echo "data: " . $jsonData . "\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                        
                        return; // Exit early, don't stream from OpenAI
                    }
                }
                
                // Build messages for AI with document context if needed
                // Use original message (without attachment info) for AI context
                $aiMessage = $userMessage ?: 'Xem file đính kèm';
                
                Log::info('🔵 [ChatController] About to call buildMessagesWithContext', [
                    'session_id' => $session->id,
                    'assistant_id' => $session->aiAssistant->id,
                    'assistant_type' => $session->aiAssistant->assistant_type,
                    'user_message' => substr($aiMessage, 0, 100),
                ]);
                
                $messages = $this->buildMessagesWithContext($session, $aiMessage);
                
                Log::info('🔵 [ChatController] buildMessagesWithContext returned', [
                    'session_id' => $session->id,
                    'messages_count' => count($messages),
                    'first_message_role' => $messages[0]['role'] ?? 'N/A',
                ]);
                
                // Process attachments: Hybrid approach (Vision API for small images, OCR for large)
                $hasImages = false;
                $imageSizeThreshold = 5 * 1024 * 1024; // 5MB
                $visionApiMaxSize = 20 * 1024 * 1024; // 20MB (OpenAI limit)
                
                if (!empty($attachments)) {
                    $imageContents = [];
                    $fileTexts = [];
                    $smallImages = [];
                    $largeImages = [];
                    $otherFiles = [];
                    
                    // Phân loại attachments
                    foreach ($attachments as $attachment) {
                        $mimeType = $attachment['mime_type'] ?? '';
                        $fileSize = $attachment['size'] ?? 0;
                        $filePath = $attachment['path'] ?? '';
                        
                        if (str_starts_with($mimeType, 'image/')) {
                            // Phân loại ảnh: nhỏ vs lớn
                            if ($fileSize < $imageSizeThreshold && $fileSize < $visionApiMaxSize) {
                                $smallImages[] = $attachment;
                            } else {
                                $largeImages[] = $attachment;
                            }
                        } else {
                            $otherFiles[] = $attachment;
                        }
                    }
                    
                    // Xử lý ảnh nhỏ: Vision API (convert sang base64)
                    foreach ($smallImages as $img) {
                        try {
                            $fullPath = Storage::disk('public')->path($img['path']);
                            if (file_exists($fullPath)) {
                                $imageData = file_get_contents($fullPath);
                                $base64Image = base64_encode($imageData);
                                $mimeType = $img['mime_type'] ?? 'image/jpeg';
                                
                                $imageContents[] = [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64Image}"
                                    ]
                                ];
                                $hasImages = true;
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to process image for Vision API', [
                                'error' => $e->getMessage(),
                                'file' => $img['name'],
                            ]);
                        }
                    }
                    
                    // Xử lý ảnh lớn: OCR
                    foreach ($largeImages as $img) {
                        try {
                            $fullPath = Storage::disk('public')->path($img['path']);
                            if (file_exists($fullPath)) {
                                $text = $documentProcessor->extractText($fullPath);
                                if (!empty(trim($text))) {
                                    $fileTexts[] = "Nội dung ảnh '{$img['name']}':\n{$text}";
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to extract text from image using OCR', [
                                'error' => $e->getMessage(),
                                'file' => $img['name'],
                            ]);
                            $fileTexts[] = "Không thể đọc nội dung ảnh '{$img['name']}' (OCR failed).";
                        }
                    }
                    
                    // Xử lý file khác (PDF, DOCX): Extract text
                    foreach ($otherFiles as $file) {
                        try {
                            $fullPath = Storage::disk('public')->path($file['path']);
                            if (file_exists($fullPath)) {
                                $text = $documentProcessor->extractText($fullPath);
                                if (!empty(trim($text))) {
                                    $fileTexts[] = "Nội dung file '{$file['name']}':\n{$text}";
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to extract text from file', [
                                'error' => $e->getMessage(),
                                'file' => $file['name'],
                            ]);
                        }
                    }
                    
                    // Thêm content vào message cuối cùng
                    if (!empty($messages) && end($messages)['role'] === 'user') {
                        $lastIndex = count($messages) - 1;
                        $content = [];
                        
                        // Text content
                        $textParts = [];
                        if ($userMessage) {
                            $textParts[] = $userMessage;
                        }
                        if (!empty($fileTexts)) {
                            $textParts[] = implode("\n\n", $fileTexts);
                        }
                        
                        if (!empty($textParts)) {
                            $content[] = [
                                'type' => 'text',
                                'text' => implode("\n\n", $textParts)
                            ];
                        }
                        
                        // Image content (Vision API)
                        if (!empty($imageContents)) {
                            $content = array_merge($content, $imageContents);
                        }
                        
                        // Nếu chỉ có ảnh và không có text, thêm prompt
                        if (empty($textParts) && !empty($imageContents)) {
                            $content = array_merge([
                                [
                                    'type' => 'text',
                                    'text' => 'Hãy mô tả và phân tích nội dung các ảnh này.'
                                ]
                            ], $imageContents);
                        }
                        
                        $messages[$lastIndex]['content'] = $content;
                    }
                }
                
                // Chọn model: Vision API nếu có ảnh nhỏ, không thì dùng model mặc định
                $model = $hasImages 
                    ? 'gpt-4o' // Vision API model
                    : ($session->aiAssistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'));
                
                // Stream response from OpenAI
                $response = OpenAI::chat()->createStreamed([
                    'model' => $model,
                    'messages' => $messages,
                ]);
                
                $fullContent = '';
                
                foreach ($response as $chunk) {
                    $delta = $chunk->choices[0]->delta->content ?? '';
                    
                    if ($delta) {
                        $fullContent .= $delta;
                        
                        // Send chunk to client
                        echo "data: " . json_encode([
                            'type' => 'content',
                            'content' => $delta,
                        ]) . "\n\n";
                        
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                }
                
                // Note: report_generator has been merged into document_drafting
                // All report generation is now handled by document_drafting assistant
                $reportData = null;
                
                // ✅ LOG: Before saving message with report
                Log::info('Saving assistant message with report metadata', [
                    'session_id' => $session->id,
                    'has_report_data' => !empty($reportData),
                    'report_id' => $reportData['report_id'] ?? null,
                    'report_file_path' => $reportData['report_file_path'] ?? null,
                ]);
                
                // Save assistant response with report metadata
                // ✅ FIX: Simplify content để tránh MySQL error
                // Chỉ lưu message ngắn, nội dung đầy đủ trong metadata['report']
                $contentToSave = "Báo cáo đã được tạo thành công!\n\n";
                $contentToSave .= "✅ Tên báo cáo: " . ($reportData['report_type'] ?? 'Báo cáo') . "\n";
                $contentToSave .= "📄 File DOCX đã được tạo và sẵn sàng tải xuống.\n";
                $contentToSave .= "👁️ Bạn có thể xem preview bên dưới.\n\n";
                $contentToSave .= "Nhấn vào button 'Tải DOCX' để tải file về máy.";
                
                $assistantMessage = ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'sender' => 'assistant',
                    'content' => $contentToSave,
                    'message_type' => 'text',
                    'created_at' => now(),
                    'metadata' => [
                        'report' => $reportData,
                        'full_content_length' => strlen($fullContent),
                    ],
                ]);
                
                // ✅ LOG: Message saved
                Log::info('Assistant message saved', [
                    'message_id' => $assistantMessage->id,
                    'session_id' => $session->id,
                    'has_metadata' => !empty($assistantMessage->metadata),
                    'metadata_report' => $assistantMessage->metadata['report'] ?? null,
                ]);
                
                // Prepare report data for SSE response
                $reportForResponse = $reportData;
                
                // ✅ LOG: Before sending SSE response
                Log::info('Preparing SSE response with report data', [
                    'session_id' => $session->id,
                    'message_id' => $assistantMessage->id,
                    'has_report_data' => !empty($reportForResponse),
                    'report_id' => $reportForResponse['report_id'] ?? null,
                    'report_file_path' => $reportForResponse['report_file_path'] ?? null,
                ]);
                
                // Send completion event with report data
                // Always send report field, even if null, so frontend knows there's no report
                $sseData = [
                    'type' => 'done',
                    'message_id' => $assistantMessage->id,
                ];
                
                // Only include report if it exists
                if ($reportForResponse) {
                    $sseData['report'] = $reportForResponse;
                    Log::info('Including report in SSE response', [
                        'session_id' => $session->id,
                        'report_id' => $reportForResponse['report_id'] ?? null,
                        'report_file_path' => $reportForResponse['report_file_path'] ?? null,
                    ]);
                } else {
                    // Explicitly set to null so frontend knows
                    $sseData['report'] = null;
                    Log::warning('No report data to include in SSE response', [
                        'session_id' => $session->id,
                        'message_id' => $assistantMessage->id,
                        'assistant_type' => $session->aiAssistant->getAssistantTypeValue() ?? null,
                    ]);
                }
                
                // ✅ LOG: SSE data before sending
                Log::info('Sending SSE done event', [
                    'session_id' => $session->id,
                    'sse_data' => $sseData,
                ]);
                
                // ✅ FIX: Ensure proper JSON encoding
                $jsonData = json_encode($sseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo "data: " . $jsonData . "\n\n";
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } catch (\Exception $e) {
                Log::error('Stream chat error', [
                    'error' => $e->getMessage(),
                    'session_id' => $session->id,
                ]);
                
                // ✅ FIX: Ensure proper JSON encoding
                $errorData = json_encode([
                    'type' => 'error',
                    'content' => 'Đã có lỗi xảy ra. Vui lòng thử lại sau.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo "data: " . $errorData . "\n\n";
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
        
        return $response;
    }

    /**
     * Get user's chat sessions
     */
    public function getSessions(Request $request)
    {
        $user = Auth::user();
        
        $sessions = ChatSession::where('user_id', $user->id)
            ->with(['aiAssistant', 'latestMessage'])
            ->orderBy('updated_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    /**
     * Delete chat session
     */
    public function deleteSession(Request $request, int $sessionId)
    {
        $user = Auth::user();
        
        try {
            // Find the session
            $session = ChatSession::where('id', $sessionId)
                ->where('user_id', $user->id)
                ->firstOrFail();
            
            // Log before deletion
            Log::info('Deleting chat session', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'messages_count' => $session->messages()->count(),
                'reports_count' => $session->reports()->count(),
            ]);
            
            // Store session ID for verification
            $sessionIdToDelete = $session->id;
            
            // Delete within transaction to ensure atomicity
            DB::transaction(function () use ($session) {
                // Delete the session (cascade will handle messages and reports)
                $deleted = $session->delete();
                
                if (!$deleted) {
                    throw new \Exception('Failed to delete session');
                }
            });
            
            // Verify deletion after transaction
            $stillExists = ChatSession::where('id', $sessionIdToDelete)->exists();
            if ($stillExists) {
                Log::error('Session still exists after deletion attempt', [
                    'session_id' => $sessionIdToDelete,
                ]);
                throw new \Exception('Session still exists after deletion');
            }
            
            Log::info('Chat session deleted successfully', [
                'session_id' => $sessionIdToDelete,
            ]);
            
            return response()->json([
                'message' => 'Session deleted successfully',
                'deleted' => true,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Session not found for deletion', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
            ]);
            
            return response()->json([
                'message' => 'Session not found',
                'error' => 'Session not found or you do not have permission to delete it',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting chat session', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to delete session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload file attachments for a chat session
     */
    public function uploadFiles(Request $request, int $sessionId)
    {
        $user = Auth::user();
        
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:pdf,doc,docx,txt,jpg,jpeg,png,gif,xlsx,xls|max:10240', // 10MB max
        ]);
        
        $uploadedFiles = [];
        
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                try {
                    // Store file
                    $path = $file->store('chat-attachments', 'public');
                    $url = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                    
                    $uploadedFiles[] = [
                        'name' => $file->getClientOriginalName(),
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'url' => $url,
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'extension' => $file->getClientOriginalExtension(),
                    ];
                } catch (\Exception $e) {
                    Log::error('File upload error', [
                        'error' => $e->getMessage(),
                        'session_id' => $sessionId,
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                    
                    return response()->json([
                        'error' => 'Không thể upload file: ' . $file->getClientOriginalName(),
                    ], 500);
                }
            }
        }
        
        return response()->json([
            'message' => 'Files uploaded successfully',
            'files' => $uploadedFiles,
        ]);
    }

    /**
     * Build messages for AI
     */
    protected function buildMessages(ChatSession $session, string $newMessage): array
    {
        // ✅ FIX: Build professional system prompt
        $systemPrompt = $this->buildProfessionalSystemPrompt($session->aiAssistant);
        
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];
        
        // Add previous messages (excluding the last user message if it's the same as newMessage)
        $previousMessages = $session->messages()
            ->orderBy('created_at')
            ->get();
        
        // Check if the last message is the same as newMessage to avoid duplicate
        $lastMessage = $previousMessages->last();
        $shouldExcludeLast = $lastMessage && 
                             $lastMessage->sender === 'user' && 
                             $lastMessage->content === $newMessage;
        
        foreach ($previousMessages as $msg) {
            // Skip the last message if it's duplicate
            if ($shouldExcludeLast && $msg->id === $lastMessage->id) {
                continue;
            }
            
            $messages[] = [
                'role' => $msg->sender === 'user' ? 'user' : 'assistant',
                'content' => $msg->content,
            ];
        }
        
        // Add new message
        $messages[] = [
            'role' => 'user',
            'content' => $newMessage,
        ];
        
        return $messages;
    }

    /**
     * Build messages with document context for qa_based_document assistants
     * and template info for document_drafting assistants
     */
    protected function buildMessagesWithContext(ChatSession $session, string $newMessage): array
    {
        $assistant = $session->aiAssistant;
        
        // If assistant is qa_based_document type, search documents and add context
        if ($assistant->assistant_type === 'qa_based_document') {
            try {
                // Check if assistant has indexed documents (check by chunks count or status)
                $documentsCount = $assistant->documents()
                    ->where(function($q) {
                        $q->where('status', 'indexed')
                          ->orWhere('is_indexed', true);
                    })
                    ->whereHas('documentChunks', function($q) {
                        $q->whereNotNull('embedding');
                    })
                    ->count();
                
                if ($documentsCount > 0) {
                    // Search similar documents
                    $searchResults = $this->vectorSearchService->searchSimilar(
                        $newMessage,
                        $assistant->id,
                        5,
                        0.7
                    );
                    
                    if (!empty($searchResults)) {
                        // Build context from search results
                        $contextText = implode("\n\n---\n\n", array_map(function($r, $i) {
                            return "[Nguồn " . ($i + 1) . "]\n" . $r['content'];
                        }, $searchResults, array_keys($searchResults)));
                        
                        // ✅ FIX: Build professional system prompt with document context
                        $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
                        $systemPrompt .= "\n\n**NGUỒN TÀI LIỆU THAM KHẢO:**\n{$contextText}\n\n";
                        $systemPrompt .= "Hãy trả lời câu hỏi của người dùng dựa trên các tài liệu trên. Trả lời chính xác, chi tiết và trích dẫn nguồn khi có thể.";
                        
                        $messages = [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                        ];
                        
                        // Add previous messages
                        $previousMessages = $session->messages()
                            ->orderBy('created_at')
                            ->get();
                        
                        $lastMessage = $previousMessages->last();
                        $shouldExcludeLast = $lastMessage && 
                                             $lastMessage->sender === 'user' && 
                                             $lastMessage->content === $newMessage;
                        
                        foreach ($previousMessages as $msg) {
                            if ($shouldExcludeLast && $msg->id === $lastMessage->id) {
                                continue;
                            }
                            
                            $messages[] = [
                                'role' => $msg->sender === 'user' ? 'user' : 'assistant',
                                'content' => $msg->content,
                            ];
                        }
                        
                        // Add new message with context
                        $messages[] = [
                            'role' => 'user',
                            'content' => "Câu hỏi: {$newMessage}\n\nTài liệu tham khảo:\n{$contextText}\n\nHãy trả lời câu hỏi dựa trên tài liệu trên.",
                        ];
                        
                        Log::info('Added document context to stream chat', [
                            'assistant_id' => $assistant->id,
                            'search_results_count' => count($searchResults),
                        ]);
                        
                        return $messages;
                    } else {
                        Log::info('No search results found for stream chat', [
                            'assistant_id' => $assistant->id,
                            'message' => substr($newMessage, 0, 100),
                        ]);
                    }
                } else {
                    Log::info('No indexed documents for assistant in stream chat', [
                        'assistant_id' => $assistant->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to search documents in stream chat, using generic messages', [
                    'error' => $e->getMessage(),
                    'assistant_id' => $assistant->id,
                ]);
            }
        }
        
        // ✅ NEW: Xử lý report_assistant - search documents and add context (similar to qa_based_document)
        if ($assistant->assistant_type === 'report_assistant') {
            try {
                // Check if assistant has indexed documents
                $documentsCount = $assistant->documents()
                    ->where(function($q) {
                        $q->where('status', 'indexed')
                          ->orWhere('is_indexed', true);
                    })
                    ->whereHas('documentChunks', function($q) {
                        $q->whereNotNull('embedding');
                    })
                    ->count();
                
                Log::info('🔵 [ChatController] Checking documents for report_assistant', [
                    'assistant_id' => $assistant->id,
                    'documents_count' => $documentsCount,
                    'user_message' => substr($newMessage, 0, 100),
                ]);
                
                if ($documentsCount > 0) {
                    // ✅ FIX: Thử với nhiều threshold để đảm bảo tìm được kết quả (giống qa_based_document)
                    $searchResults = null;
                    $thresholds = [0.7, 0.5, 0.3];
                    $usedThreshold = null;
                    
                    foreach ($thresholds as $threshold) {
                        $tempResults = $this->vectorSearchService->searchSimilar(
                            $newMessage,
                            $assistant->id,
                            5,
                            $threshold
                        );
                        
                        if (!empty($tempResults)) {
                            $searchResults = $tempResults;
                            $usedThreshold = $threshold;
                            Log::info('✅ [ChatController] Found search results for report_assistant', [
                                'assistant_id' => $assistant->id,
                                'threshold' => $threshold,
                                'results_count' => count($tempResults),
                            ]);
                            break;
                        }
                    }
                    
                    if (!empty($searchResults)) {
                        // Build context from search results
                        $contextText = implode("\n\n---\n\n", array_map(function($r, $i) {
                            return "[Nguồn " . ($i + 1) . "]\n" . $r['content'];
                        }, $searchResults, array_keys($searchResults)));
                        
                        // Build professional system prompt with document context
                        $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
                        $systemPrompt .= "\n\n**TÀI LIỆU BÁO CÁO THAM KHẢO:**\n{$contextText}\n\n";
                        $systemPrompt .= "Hãy trả lời câu hỏi của người dùng dựa trên các tài liệu báo cáo trên. ";
                        $systemPrompt .= "Trả lời chính xác, chi tiết và trích dẫn nguồn khi có thể. ";
                        $systemPrompt .= "Nếu được yêu cầu tóm tắt, hãy tóm tắt nội dung chính. ";
                        $systemPrompt .= "Nếu được yêu cầu tạo báo cáo mới, hãy phân tích cấu trúc (đầu mục, format) của báo cáo mẫu và tạo báo cáo tương tự.";
                        
                        $messages = [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                        ];
                        
                        // Add previous messages
                        $previousMessages = $session->messages()
                            ->orderBy('created_at')
                            ->get();
                        
                        $lastMessage = $previousMessages->last();
                        $shouldExcludeLast = $lastMessage && 
                                             $lastMessage->sender === 'user' && 
                                             $lastMessage->content === $newMessage;
                        
                        foreach ($previousMessages as $msg) {
                            if ($shouldExcludeLast && $msg->id === $lastMessage->id) {
                                continue;
                            }
                            
                            $messages[] = [
                                'role' => $msg->sender === 'user' ? 'user' : 'assistant',
                                'content' => $msg->content,
                            ];
                        }
                        
                        // Add new message with context
                        $messages[] = [
                            'role' => 'user',
                            'content' => "Câu hỏi: {$newMessage}\n\nTài liệu báo cáo tham khảo:\n{$contextText}\n\nHãy trả lời câu hỏi dựa trên tài liệu báo cáo trên.",
                        ];
                        
                        Log::info('✅ [ChatController] Added report context to stream chat', [
                            'assistant_id' => $assistant->id,
                            'search_results_count' => count($searchResults),
                            'threshold_used' => $usedThreshold,
                        ]);
                        
                        return $messages;
                    } else {
                        Log::warning('⚠️ [ChatController] No search results found for report_assistant after trying all thresholds', [
                            'assistant_id' => $assistant->id,
                            'message' => substr($newMessage, 0, 100),
                            'documents_count' => $documentsCount,
                            'thresholds_tried' => $thresholds,
                        ]);
                        // ✅ FIX: Fallback - vẫn thêm thông tin về documents vào system prompt
                        // Để AI biết rằng có documents nhưng không tìm thấy kết quả phù hợp
                        $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
                        $systemPrompt .= "\n\n**LƯU Ý:** Bạn có {$documentsCount} tài liệu báo cáo đã được upload, nhưng không tìm thấy nội dung phù hợp với câu hỏi. ";
                        $systemPrompt .= "Hãy trả lời dựa trên kiến thức chung về báo cáo kết quả ĐH Đoàn, hoặc yêu cầu người dùng cung cấp thêm thông tin cụ thể.";
                        
                        $messages = [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                        ];
                        
                        // Add previous messages
                        $previousMessages = $session->messages()
                            ->orderBy('created_at')
                            ->get();
                        
                        $lastMessage = $previousMessages->last();
                        $shouldExcludeLast = $lastMessage && 
                                             $lastMessage->sender === 'user' && 
                                             $lastMessage->content === $newMessage;
                        
                        foreach ($previousMessages as $msg) {
                            if ($shouldExcludeLast && $msg->id === $lastMessage->id) {
                                continue;
                            }
                            
                            $messages[] = [
                                'role' => $msg->sender === 'user' ? 'user' : 'assistant',
                                'content' => $msg->content,
                            ];
                        }
                        
                        // Add new message
                        $messages[] = [
                            'role' => 'user',
                            'content' => $newMessage,
                        ];
                        
                        return $messages;
                    }
                } else {
                    Log::warning('⚠️ [ChatController] No indexed documents for report_assistant', [
                        'assistant_id' => $assistant->id,
                        'total_documents' => $assistant->documents()->count(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to search documents for report_assistant, using generic messages', [
                    'error' => $e->getMessage(),
                    'assistant_id' => $assistant->id,
                ]);
            }
        }
        
        // ✅ MỚI: Xử lý document_drafting assistant - search template content + thêm template info
        if ($assistant->assistant_type === 'document_drafting') {
            try {
                // ✅ NEW: Tìm kiếm nội dung template liên quan đến câu hỏi
                $searchResults = [];
                
                // Check if assistant has indexed templates (via AssistantDocuments with source_type='template')
                $hasIndexedTemplates = $assistant->documents()
                    ->where(function($q) {
                        $q->where('status', 'indexed')
                          ->orWhere('is_indexed', true);
                    })
                    ->whereHas('documentChunks', function($q) {
                        $q->whereNotNull('embedding')
                          ->whereJsonContains('metadata->source_type', 'template');
                    })
                    ->exists();
                
                if ($hasIndexedTemplates) {
                    // Search similar template content
                    $searchResults = $this->vectorSearchService->searchSimilar(
                        $newMessage,
                        $assistant->id,
                        3, // Top 3 results
                        0.7, // Min similarity
                        ['source_type' => 'template'] // ✅ Filter by template chunks only
                    );
                }
                
                // Load templates từ database
                $templates = $assistant->documentTemplates()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
                
                // ✅ FIX: Build professional system prompt
                $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
                
                // ✅ NEW: Nếu có search results từ template content, thêm vào context
                if (!empty($searchResults)) {
                    $contextText = implode("\n\n---\n\n", array_map(function($r, $i) {
                        $metadata = $r['metadata'] ?? [];
                        $docType = $metadata['document_type'] ?? '';
                        $subtype = $metadata['template_subtype'] ?? '';
                        $source = $docType . ($subtype ? "/{$subtype}" : '');
                        return "[Template: {$source}]\n" . $r['content'];
                    }, $searchResults, array_keys($searchResults)));
                    
                    $systemPrompt .= "\n\n**NỘI DUNG TEMPLATE THAM KHẢO:**\n{$contextText}\n\n";
                    
                    Log::info('✅ [ChatController] Added template content context to chat', [
                        'assistant_id' => $assistant->id,
                        'search_results_count' => count($searchResults),
                    ]);
                }
                
                // Thêm danh sách template vào system prompt
                if ($templates->isNotEmpty()) {
                    $templateList = $templates->map(function($t) {
                        $subtype = $t->template_subtype ? "/{$t->template_subtype}" : "";
                        return "- {$t->name} ({$t->document_type}{$subtype})";
                    })->implode("\n");
                    
                    $systemPrompt .= "\n\n**CÁC TEMPLATE CÓ SẴN:**\n{$templateList}\n\n";
                    $systemPrompt .= "Khi quý anh/chị yêu cầu tạo văn bản, tôi sẽ sử dụng đúng template tương ứng với loại văn bản được yêu cầu.";
                    $systemPrompt .= " Ví dụ: Nếu quý anh/chị yêu cầu 'tạo quyết định bổ nhiệm', tôi sẽ sử dụng template 'Quyết định bổ nhiệm' (quyet_dinh/bo_nhiem).";
                    
                    Log::info('Added template info to system prompt', [
                        'assistant_id' => $assistant->id,
                        'templates_count' => $templates->count(),
                    ]);
                }
                
                // Nếu có template content context, thêm hướng dẫn trả lời
                if (!empty($searchResults)) {
                    $systemPrompt .= "\n\nKhi được hỏi về nội dung template, hãy trả lời dựa trên **NỘI DUNG TEMPLATE THAM KHẢO** ở trên.";
                }
                
                // Build messages với system prompt mới
                $messages = [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                ];
                
                // Add previous messages
                $previousMessages = $session->messages()
                    ->orderBy('created_at')
                    ->get();
                
                $lastMessage = $previousMessages->last();
                $shouldExcludeLast = $lastMessage && 
                                     $lastMessage->sender === 'user' && 
                                     $lastMessage->content === $newMessage;
                
                foreach ($previousMessages as $msg) {
                    if ($shouldExcludeLast && $msg->id === $lastMessage->id) {
                        continue;
                    }
                    
                    $messages[] = [
                        'role' => $msg->sender === 'user' ? 'user' : 'assistant',
                        'content' => $msg->content,
                    ];
                }
                
                // Add new message
                $messages[] = [
                    'role' => 'user',
                    'content' => $newMessage,
                ];
                
                return $messages;
            } catch (\Exception $e) {
                Log::warning('Failed to load templates/search template content for document_drafting assistant, using generic messages', [
                    'error' => $e->getMessage(),
                    'assistant_id' => $assistant->id,
                ]);
            }
        }
        
        // Fallback to regular buildMessages if no documents or search failed
        return $this->buildMessages($session, $newMessage);
    }
    
    /**
     * Classify document for document_management assistant
     */
    public function classifyDocument(Request $request, int $sessionId)
    {
        $user = Auth::user();
        
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->with('aiAssistant')
            ->firstOrFail();
        
        // Check if assistant is document_management type
        if ($session->aiAssistant->getAssistantTypeValue() !== 'document_management') {
            return response()->json([
                'error' => 'This assistant type does not support document classification',
            ], 400);
        }
        
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'loai_van_ban' => 'nullable|in:van_ban_den,van_ban_di',
        ]);
        
        try {
            $file = $request->file('file');
            $loaiVanBan = $request->input('loai_van_ban', 'van_ban_den');
            
            // Process document using DocumentManagementService
            $documentManagementService = app(\App\Services\DocumentManagementService::class);
            
            if ($loaiVanBan === 'van_ban_den') {
                $document = $documentManagementService->processIncomingDocument(
                    $file,
                    $session->aiAssistant,
                    $user,
                    ['loai_van_ban' => $loaiVanBan]
                );
            } else {
                $document = $documentManagementService->processOutgoingDocument(
                    $file,
                    $session->aiAssistant,
                    $user,
                    ['loai_van_ban' => $loaiVanBan]
                );
            }
            
            return response()->json([
                'message' => 'Document classified successfully',
                'document' => [
                    'id' => $document->id,
                    'so_van_ban' => $document->so_van_ban,
                    'ngay_van_ban' => $document->ngay_van_ban?->format('d/m/Y'),
                    'loai_van_ban' => $document->loai_van_ban,
                    'document_type' => $document->document_type,
                    'noi_gui' => $document->noi_gui,
                    'noi_nhan' => $document->noi_nhan,
                    'trich_yeu' => $document->trich_yeu,
                    'muc_do' => $document->muc_do,
                    'deadline' => $document->deadline?->format('d/m/Y'),
                    'phong_ban_xu_ly' => $document->phong_ban_xu_ly,
                    'trang_thai' => $document->trang_thai,
                    'classification' => $document->classification,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to classify document', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Không thể phân loại văn bản: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get documents for document_management assistant
     */
    public function getDocuments(Request $request, int $sessionId)
    {
        $user = Auth::user();
        
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->with('aiAssistant')
            ->firstOrFail();
        
        // Check if assistant is document_management type
        if ($session->aiAssistant->getAssistantTypeValue() !== 'document_management') {
            return response()->json([
                'error' => 'This assistant type does not support document management',
            ], 400);
        }
        
        $filters = $request->only(['loai_van_ban', 'document_type', 'trang_thai', 'date_from', 'date_to']);
        $limit = $request->input('limit', 20);
        
        try {
            $documentManagementService = app(\App\Services\DocumentManagementService::class);
            
            // If search query provided, use searchDocuments
            if ($request->has('search')) {
                $searchResults = $documentManagementService->searchDocuments(
                    $request->input('search'),
                    $session->aiAssistant,
                    $filters
                );
                
                // Map search results to document format
                $documents = array_map(function ($doc) {
                    return [
                        'id' => $doc['id'],
                        'so_van_ban' => $doc['so_van_ban'],
                        'ngay_van_ban' => $doc['ngay_van_ban'] ? \Carbon\Carbon::parse($doc['ngay_van_ban'])->format('d/m/Y') : null,
                        'loai_van_ban' => $doc['loai_van_ban'],
                        'document_type' => $doc['document_type'],
                        'noi_gui' => $doc['noi_gui'],
                        'noi_nhan' => $doc['noi_nhan'],
                        'trich_yeu' => $doc['trich_yeu'],
                        'muc_do' => $doc['muc_do'],
                        'deadline' => $doc['deadline'] ? \Carbon\Carbon::parse($doc['deadline'])->format('d/m/Y') : null,
                        'phong_ban_xu_ly' => $doc['phong_ban_xu_ly'],
                        'trang_thai' => $doc['trang_thai'],
                        'file_path' => $doc['file_path'],
                        'is_overdue' => $doc['is_overdue'] ?? false,
                        'is_due_today' => $doc['is_due_today'] ?? false,
                        'days_until_deadline' => $doc['days_until_deadline'] ?? null,
                    ];
                }, $searchResults);
            } else {
                // Otherwise, get all documents
                $query = \App\Models\AdministrativeDocument::where('ai_assistant_id', $session->aiAssistant->id);
                
                if (isset($filters['loai_van_ban'])) {
                    $query->where('loai_van_ban', $filters['loai_van_ban']);
                }
                
                if (isset($filters['document_type'])) {
                    $query->where('document_type', $filters['document_type']);
                }
                
                if (isset($filters['trang_thai'])) {
                    $query->where('trang_thai', $filters['trang_thai']);
                }
                
                if (isset($filters['date_from'])) {
                    $query->where('ngay_van_ban', '>=', $filters['date_from']);
                }
                
                if (isset($filters['date_to'])) {
                    $query->where('ngay_van_ban', '<=', $filters['date_to']);
                }
                
                $documents = $query->orderBy('ngay_van_ban', 'desc')
                    ->limit($limit)
                    ->get()
                    ->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'so_van_ban' => $doc->so_van_ban,
                            'ngay_van_ban' => $doc->ngay_van_ban?->format('d/m/Y'),
                            'loai_van_ban' => $doc->loai_van_ban,
                            'document_type' => $doc->document_type,
                            'noi_gui' => $doc->noi_gui,
                            'noi_nhan' => $doc->noi_nhan,
                            'trich_yeu' => $doc->trich_yeu,
                            'muc_do' => $doc->muc_do,
                            'deadline' => $doc->deadline?->format('d/m/Y'),
                            'phong_ban_xu_ly' => $doc->phong_ban_xu_ly,
                            'trang_thai' => $doc->trang_thai,
                            'file_path' => $doc->file_path,
                            'is_overdue' => $doc->isOverdue(),
                            'is_due_today' => $doc->isDueToday(),
                            'days_until_deadline' => $doc->days_until_deadline,
                        ];
                    })
                    ->toArray();
            }
            
            return response()->json([
                'documents' => $documents,
                'count' => count($documents),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get documents', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);
            
            return response()->json([
                'error' => 'Không thể lấy danh sách văn bản: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Get reminders for document_management assistant
     */
    public function getReminders(Request $request, int $sessionId)
    {
        $user = Auth::user();
        
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->with('aiAssistant')
            ->firstOrFail();
        
        // Check if assistant is document_management type
        if ($session->aiAssistant->getAssistantTypeValue() !== 'document_management') {
            return response()->json([
                'error' => 'This assistant type does not support reminders',
            ], 400);
        }
        
        try {
            $documentReminderService = app(\App\Services\DocumentReminderService::class);
            $daysBefore = $request->input('days_before', 1);
            
            $reminders = $documentReminderService->getReminders($session->aiAssistant, $daysBefore);
            $overdue = $documentReminderService->getOverdueDocuments($session->aiAssistant);
            $dueToday = $documentReminderService->getDocumentsDueToday($session->aiAssistant);
            
            return response()->json([
                'reminders' => $reminders,
                'overdue' => $overdue,
                'due_today' => $dueToday,
                'total' => count($reminders) + count($overdue) + count($dueToday),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get reminders', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
            ]);
            
            return response()->json([
                'error' => 'Không thể lấy nhắc nhở: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build professional system prompt for administrative AI
     *
     * @param AiAssistant $assistant
     * @return string
     */
    protected function buildProfessionalSystemPrompt(AiAssistant $assistant): string
    {
        $assistantName = $assistant->name ?? 'Trợ lý AI';
        $assistantDescription = $assistant->description ?? '';
        
        $prompt = "Bạn là {$assistantName}, một trợ lý AI chuyên nghiệp phục vụ trong lĩnh vực hành chính công.\n\n";
        
        if (!empty($assistantDescription)) {
            $prompt .= "**MÔ TẢ CHỨC NĂNG:**\n{$assistantDescription}\n\n";
        }
        
        $prompt .= "**QUY TẮC GIAO TIẾP:**\n";
        $prompt .= "1. Luôn sử dụng ngôn ngữ lịch sự, chuyên nghiệp, phù hợp với môi trường hành chính công\n";
        $prompt .= "2. Xưng hô: Sử dụng \"Tôi\" để tự xưng, \"Quý anh/chị\" hoặc \"Bạn\" để gọi người dùng\n";
        $prompt .= "3. Trả lời rõ ràng, chi tiết, có cấu trúc\n";
        $prompt .= "4. Sử dụng từ ngữ chính thức, tránh ngôn ngữ suồng sã, thân mật quá mức\n";
        $prompt .= "5. Luôn thể hiện sự tôn trọng và sẵn sàng hỗ trợ\n";
        $prompt .= "6. Nếu không chắc chắn, hãy hỏi lại một cách lịch sự\n";
        $prompt .= "7. Khi cần thu thập thông tin, hãy giải thích rõ mục đích và tầm quan trọng\n\n";
        
        $prompt .= "**VÍ DỤ CÁCH TRẢ LỜI:**\n";
        $prompt .= "- ✅ TỐT: \"Xin chào quý anh/chị! Tôi là {$assistantName}. Tôi rất vui được hỗ trợ quý anh/chị. Quý anh/chị cần tôi giúp gì không?\"\n";
        $prompt .= "- ✅ TỐT: \"Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin. Quý anh/chị vui lòng cung cấp...\"\n";
        $prompt .= "- ❌ KHÔNG TỐT: \"Vui lòng cung cấp thông tin cần thiết.\" (quá cộc lốc)\n";
        $prompt .= "- ❌ KHÔNG TỐT: \"Mày cần gì?\" (không lịch sự)\n\n";
        
        $prompt .= "Hãy luôn trả lời một cách chuyên nghiệp, lịch sự và hữu ích.";
        
        return $prompt;
    }
}

