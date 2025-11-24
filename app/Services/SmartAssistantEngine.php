<?php

namespace App\Services;

use App\Models\AiAssistant;
use App\Models\ChatSession;
use App\Models\DocumentChunk;
use App\Services\GeminiWebSearchService;
use App\Services\ResponseEnhancementService; // ✅ MỚI: Response Enhancement Service
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class SmartAssistantEngine
{
    public function __construct(
        protected IntentRecognizer $intentRecognizer,
        protected WorkflowPlanner $workflowPlanner,
        protected VectorSearchService $vectorSearchService,
        protected ?DocumentDraftingService $documentDraftingService = null,
        protected ?DocumentManagementService $documentManagementService = null,
        protected ?DocumentReminderService $documentReminderService = null,
        protected ?GeminiWebSearchService $geminiWebSearchService = null,
        protected ?ResponseEnhancementService $responseEnhancer = null // ✅ MỚI: Response Enhancement Service
    ) {
        // Lazy load DocumentDraftingService
        if (!$this->documentDraftingService) {
            $this->documentDraftingService = app(DocumentDraftingService::class);
        }
        // Lazy load DocumentManagementService
        if (!$this->documentManagementService) {
            $this->documentManagementService = app(DocumentManagementService::class);
        }
        // Lazy load DocumentReminderService
        if (!$this->documentReminderService) {
            $this->documentReminderService = app(DocumentReminderService::class);
        }
        // ✅ CẢI TIẾN: Lazy load GeminiWebSearchService
        if (!$this->geminiWebSearchService) {
            $this->geminiWebSearchService = app(GeminiWebSearchService::class);
        }
        // ✅ MỚI: Lazy load ResponseEnhancementService
        if (!$this->responseEnhancer) {
            $this->responseEnhancer = app(ResponseEnhancementService::class);
        }
    }

    /**
     * Process user message and generate response
     *
     * @param string $userMessage
     * @param ChatSession $session
     * @param AiAssistant $assistant
     * @param callable|null $streamCallback Callback function to stream chunks: function(string $chunk): void
     * @return array{response: string, workflow_state: array|null}
     */
    public function processMessage(string $userMessage, ChatSession $session, AiAssistant $assistant, ?callable $streamCallback = null): array
    {
        try {
            // ✅ PHASE 2: Gửi signal để frontend biết đang bắt đầu xử lý (nếu có callback)
            // Note: ChatController sẽ gửi loading status, nhưng có thể gửi thêm signal ở đây nếu cần
            
            // Recognize intent with full context (session, assistant, workflow state)
            $context = [
                'session' => $session,
                'assistant' => $assistant,
                'collected_data' => $session->collected_data ?? [],
                'workflow_state' => $session->workflow_state ?? null,
            ];
            
            $intent = $this->intentRecognizer->recognize($userMessage, $context);
            
            Log::info('Intent recognized', [
                'intent' => $intent,
                'session_id' => $session->id,
            ]);
            
            // Plan workflow if needed
            $workflow = $this->workflowPlanner->plan($intent, $assistant, $context);
            
            // ✅ MỚI: Nếu có steps được định nghĩa, kiểm tra xem có nên thực thi không
            $config = $assistant->config ?? [];
            $predefinedSteps = $config['steps'] ?? null;
            $workflowState = $session->workflow_state ?? [];
            $collectedData = $session->collected_data ?? [];
            $currentStepIndex = $workflowState['current_step_index'] ?? 0;

            // ✅ LOG: Debug steps
            Log::info('🔵 [SmartAssistantEngine] Checking predefined steps', [
                'session_id' => $session->id,
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistant->getAssistantTypeValue() ?? 'unknown',
                'has_config' => !empty($config),
                'has_steps' => !empty($predefinedSteps),
                'steps_count' => is_array($predefinedSteps) ? count($predefinedSteps) : 0,
                'current_step_index' => $currentStepIndex,
                'has_collected_data' => !empty($collectedData),
                'intent_type' => $intent['type'] ?? null,
            ]);

            // ✅ FIX: Chỉ thực thi steps khi:
            // 1. Đã bắt đầu workflow (có collected_data hoặc currentStepIndex > 0)
            // 2. HOẶC user có intent rõ ràng cần workflow (không phải greeting, không phải câu hỏi thông thường)
            // ✅ QUAN TRỌNG: Q&A assistant KHÔNG bao giờ trigger steps
            $shouldExecuteSteps = false;
            
            if ($predefinedSteps && is_array($predefinedSteps) && count($predefinedSteps) > 0) {
                // ✅ CẢI TIẾN: Q&A assistant KHÔNG bao giờ dùng steps
                if ($assistant->getAssistantTypeValue() === 'qa_based_document') {
                    Log::info('🔵 [SmartAssistantEngine] Q&A assistant detected, skipping steps', [
                        'session_id' => $session->id,
                        'assistant_id' => $assistant->id,
                    ]);
                    $shouldExecuteSteps = false;
                } else {
                    // Đã bắt đầu workflow → Tiếp tục
                    if ($currentStepIndex > 0 || !empty($collectedData)) {
                        $shouldExecuteSteps = true;
                        Log::info('🔵 [SmartAssistantEngine] Workflow already started, continuing steps', [
                            'session_id' => $session->id,
                            'current_step_index' => $currentStepIndex,
                        ]);
                    }
                    // Chưa bắt đầu → Chỉ trigger nếu có intent rõ ràng cần workflow
                    else {
                        // ✅ CẢI TIẾN: Kiểm tra kỹ hơn trước khi trigger steps
                        $isGreeting = $this->isGreetingMessage($userMessage);
                        $isGeneralQuestion = $this->isGeneralQuestion($userMessage, $intent);
                        
                        // ✅ CẢI TIẾN: Thêm check intent type
                        $requiresWorkflow = in_array($intent['type'] ?? null, [
                            'draft_document',
                            'create_report',
                            'classify_document',
                            // search_document chỉ khi có yêu cầu cụ thể (không phải câu hỏi thông thường)
                        ]);
                        
                        // ✅ CẢI TIẾN: Chỉ trigger nếu:
                        // 1. Không phải greeting
                        // 2. Không phải general question
                        // 3. Có intent rõ ràng cần workflow
                        if (!$isGreeting && !$isGeneralQuestion && $requiresWorkflow) {
                            $shouldExecuteSteps = true;
                            Log::info('🔵 [SmartAssistantEngine] User has specific request, starting workflow', [
                                'session_id' => $session->id,
                                'intent_type' => $intent['type'] ?? null,
                            ]);
                        } else {
                            Log::info('🔵 [SmartAssistantEngine] Greeting or general question, using ChatGPT directly', [
                                'session_id' => $session->id,
                                'is_greeting' => $isGreeting,
                                'is_general_question' => $isGeneralQuestion,
                                'requires_workflow' => $requiresWorkflow,
                                'intent_type' => $intent['type'] ?? null,
                            ]);
                        }
                    }
                }
            }

            if ($shouldExecuteSteps) {
                Log::info('🔵 [SmartAssistantEngine] Executing predefined steps', [
                    'session_id' => $session->id,
                    'steps_count' => count($predefinedSteps),
                ]);
                return $this->executePredefinedSteps($predefinedSteps, $userMessage, $session, $assistant, $intent, $workflow, $streamCallback);
            }
            
            // Execute workflow based on intent and assistant type
            $result = match (true) {
                // Document drafting assistant
                $assistant->getAssistantTypeValue() === 'document_drafting' && $intent['type'] === 'draft_document' 
                    => $this->handleDraftDocument($userMessage, $session, $assistant, $intent, $workflow, $streamCallback),
                
                // Document management assistant
                $assistant->getAssistantTypeValue() === 'document_management' && $intent['type'] === 'classify_document' 
                    => $this->handleClassifyDocument($userMessage, $session, $assistant, $intent),
                
                $assistant->getAssistantTypeValue() === 'document_management' && $intent['type'] === 'search_document' 
                    => $this->handleSearchDocument($userMessage, $session, $assistant, $intent),
                
                $assistant->getAssistantTypeValue() === 'document_management' && $intent['type'] === 'get_reminders' 
                    => $this->handleGetReminders($userMessage, $session, $assistant, $intent),
                
                // Q&A assistant - ✅ QUAN TRỌNG: Luôn ưu tiên handleAskQuestion cho Q&A assistant
                $assistant->getAssistantTypeValue() === 'qa_based_document' 
                    => $this->handleAskQuestion($userMessage, $session, $assistant, $intent, $streamCallback),
                
                // Search document (generic)
                $intent['type'] === 'search_document' 
                    => $this->handleSearchDocument($userMessage, $session, $assistant, $intent),
                
                default => $this->handleGenericRequest($userMessage, $session, $assistant, $intent, $streamCallback),
            };
            
            return $result;
        } catch (\Exception $e) {
            Log::error('SmartAssistantEngine error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'response' => 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.',
                'workflow_state' => null,
            ];
        }
    }

    /**
     * Handle create report intent
     * 
     * ⚠️ DEPRECATED: report_generator has been merged into document_drafting
     * This method is kept for backward compatibility but should not be called
     *
     * @param string $userMessage
     * @param ChatSession $session
     * @param AiAssistant $assistant
     * @param array $intent
     * @param array $workflow
     * @return array
     * @deprecated Use document_drafting assistant instead
     */
    protected function handleCreateReport(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent, array $workflow): array
    {
        // ✅ QUAN TRỌNG: Chỉ xử lý cho report_generator
        if ($assistant->assistant_type !== 'report_generator') {
            Log::warning('handleCreateReport called for non-report_generator assistant', [
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistant->getAssistantTypeValue(),
            ]);
            return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
        }
        
        $collectedData = $session->collected_data ?? [];
        
        // ✅ FIX: Phân tích request có đủ thông tin không
        $requestAnalysis = $this->analyzeRequestCompleteness($userMessage, $workflow, $collectedData);
        
        // ✅ LOG: Request analysis
        Log::info('Request analysis', [
            'session_id' => $session->id,
            'is_vague' => $requestAnalysis['is_vague'],
            'has_sufficient_info' => $requestAnalysis['has_sufficient_info'],
            'has_required_fields' => $requestAnalysis['has_required_fields'],
            'collected_data_count' => count($collectedData),
            'user_message' => substr($userMessage, 0, 100),
        ]);
        
        // ✅ FIX: Use AI to detect if user wants auto-fill (thay vì keywords cứng)
        $contextForAutoFill = [
            'session' => $session,
            'assistant' => $assistant,
            'collected_data' => $collectedData,
            'workflow_state' => $session->workflow_state ?? null,
        ];
        
        $shouldAutoFill = $this->intentRecognizer->detectAutoFillIntent($userMessage, $contextForAutoFill);
        
        if ($shouldAutoFill) {
            Log::info('User wants auto-fill (detected by AI)', [
                'session_id' => $session->id,
                'message' => substr($userMessage, 0, 100),
            ]);
        }
        
        // ✅ FIX: Nếu request chung chung và chưa có data
        // - AI detect user muốn tự tạo → Generate với sample data
        // - AI detect user KHÔNG muốn tự tạo → Hỏi thông tin
        if ($requestAnalysis['is_vague'] && empty($collectedData) && !$shouldAutoFill) {
            Log::info('Vague request with no data and no auto-fill intent, asking for required info', [
                'session_id' => $session->id,
                'workflow_steps' => count($workflow['steps'] ?? []),
            ]);
            return $this->askForRequiredInfo($workflow, $assistant, $collectedData);
        }
        
        // ✅ NEW: If user wants auto-fill (detected by AI), generate sample data
        if ($shouldAutoFill && empty($collectedData)) {
            Log::info('Generating sample data for auto-fill request (AI detected)', [
                'session_id' => $session->id,
                'assistant_id' => $assistant->id,
            ]);
            $collectedData = $this->generateSampleDataForTemplate($assistant, $workflow);
            $session->update(['collected_data' => $collectedData]);
            
            Log::info('Sample data generated', [
                'session_id' => $session->id,
                'sample_data_count' => count($collectedData),
                'sample_data' => $collectedData,
            ]);
        }
        
        // Check if we need to collect more information
        $nextStep = $this->getNextStep($workflow, $collectedData);
        
        // ✅ LOG: Next step
        Log::info('Next step check', [
            'session_id' => $session->id,
            'has_next_step' => !!$nextStep,
            'next_step_type' => $nextStep['type'] ?? null,
            'next_step_field' => $nextStep['field'] ?? $nextStep['field_key'] ?? null,
        ]);
        
        if ($nextStep && $nextStep['type'] === 'collect_info') {
            // Extract information from user message
            $extractedData = $this->extractDataFromMessage($userMessage, $nextStep, $assistant);
            
            // Merge with collected data
            $collectedData = array_merge($collectedData, $extractedData);
            
            // Update session
            $session->update(['collected_data' => $collectedData]);
            
            // Check if we have all required data
            $allCollected = $this->checkAllDataCollected($workflow, $collectedData);
            
            if ($allCollected) {
                // Actually generate report (chỉ cho report_generator)
                try {
                    $reportGenerator = app(ReportGenerator::class);
                    $reportResult = $reportGenerator->generateReport(
                        $assistant,
                        $session,
                        $collectedData,
                        $userMessage // ✅ Pass user request for AI context
                    );
                    
                    // ✅ LOG: Report generated successfully
                    Log::info('Report generated in SmartAssistantEngine', [
                        'session_id' => $session->id,
                        'assistant_id' => $assistant->id,
                        'report_id' => $reportResult['report_id'] ?? null,
                        'report_file_path' => $reportResult['report_file_path'] ?? null,
                        'report_content_length' => strlen($reportResult['report_content'] ?? ''),
                    ]);
                    
                    return [
                        'response' => "Báo cáo đã được tạo thành công!\n\n" . 
                                     "Bạn có thể xem nội dung báo cáo dưới đây:\n\n" . 
                                     "---\n" . 
                                     substr($reportResult['report_content'], 0, 2000) . 
                                     (strlen($reportResult['report_content']) > 2000 ? "\n\n... (báo cáo đã được lưu)" : ""),
                        'workflow_state' => [
                            'current_step' => 'completed',
                            'workflow' => $workflow,
                        ],
                        'report' => $reportResult, // ✅ Ensure report data is included
                    ];
                } catch (\Exception $e) {
                    Log::error('Failed to generate report', [
                        'error' => $e->getMessage(),
                        'assistant_id' => $assistant->id,
                        'assistant_type' => $assistant->getAssistantTypeValue(),
                        'session_id' => $session->id,
                    ]);
                    
                    return [
                        'response' => 'Xin lỗi, đã có lỗi xảy ra khi tạo báo cáo. Vui lòng thử lại sau.',
                        'workflow_state' => [
                            'current_step' => 'generate_report',
                            'workflow' => $workflow,
                        ],
                    ];
                }
            } else {
                // Ask for next missing field
                $nextMissingField = $this->getNextMissingField($workflow, $collectedData);
                $question = $this->generateQuestion($nextMissingField, $assistant);
                
                // ✅ LOG: Asking for missing field
                Log::info('Asking for next missing field', [
                    'session_id' => $session->id,
                    'missing_field' => $nextMissingField['field'] ?? $nextMissingField['field_key'] ?? null,
                    'question' => substr($question, 0, 100),
                ]);
                
                return [
                    'response' => $question,
                    'workflow_state' => [
                        'current_step' => $nextStep['id'],
                        'workflow' => $workflow,
                    ],
                ];
            }
        } else {
            // ✅ LOG: No next step, checking if should generate or ask
            Log::info('No next step in workflow', [
                'session_id' => $session->id,
                'has_collected_data' => !empty($collectedData),
                'has_sufficient_info' => $requestAnalysis['has_sufficient_info'],
            ]);
            
            // ✅ FIX: Nếu không có required fields → Tạo báo cáo ngay (với template, giữ nguyên format)
            // Nếu có required fields nhưng chưa có data → Hỏi user
            $requiredFields = $this->getRequiredFields($workflow);
            $missingFields = $this->getMissingFields($workflow, $collectedData);
            
            if (empty($requiredFields) && empty($missingFields)) {
                // Không có required fields → Tạo báo cáo ngay với template
                Log::info('No required fields, generating report directly with template', [
                    'session_id' => $session->id,
                    'collected_data_count' => count($collectedData),
                ]);
                // Continue to generate report below
            } elseif (empty($collectedData) && !$requestAnalysis['has_sufficient_info']) {
                // Có required fields nhưng chưa có data → Hỏi user
                Log::info('No data and insufficient info, asking for required info', [
                    'session_id' => $session->id,
                    'user_message' => substr($userMessage, 0, 100),
                    'required_fields_count' => count($requiredFields),
                    'missing_fields_count' => count($missingFields),
                ]);
                return $this->askForRequiredInfo($workflow, $assistant, $collectedData);
            }
            
            // Generate report (when all data is already collected OR request has sufficient info)
            try {
                $reportGenerator = app(ReportGenerator::class);
                $reportResult = $reportGenerator->generateReport(
                    $assistant,
                    $session,
                    $collectedData,
                    $userMessage // ✅ Pass user request for AI context
                );
                
                // ✅ LOG: Report generated successfully (else branch)
                Log::info('Report generated in SmartAssistantEngine (else branch)', [
                    'session_id' => $session->id,
                    'assistant_id' => $assistant->id,
                    'report_id' => $reportResult['report_id'] ?? null,
                    'report_file_path' => $reportResult['report_file_path'] ?? null,
                    'report_content_length' => strlen($reportResult['report_content'] ?? ''),
                ]);
                
                return [
                    'response' => "Báo cáo đã được tạo thành công!\n\n" . 
                                 "Bạn có thể xem nội dung báo cáo dưới đây:\n\n" . 
                                 "---\n" . 
                                 substr($reportResult['report_content'], 0, 2000) . 
                                 (strlen($reportResult['report_content']) > 2000 ? "\n\n... (báo cáo đã được lưu)" : ""),
                    'workflow_state' => [
                        'current_step' => 'completed',
                        'workflow' => $workflow,
                    ],
                    'report' => $reportResult, // ✅ Ensure report data is included
                ];
            } catch (\Exception $e) {
                Log::error('Failed to generate report', [
                    'error' => $e->getMessage(),
                    'assistant_id' => $assistant->id,
                    'assistant_type' => $assistant->getAssistantTypeValue(),
                    'session_id' => $session->id,
                ]);
                
                return [
                    'response' => 'Xin lỗi, đã có lỗi xảy ra khi tạo báo cáo. Vui lòng thử lại sau.',
                    'workflow_state' => [
                        'current_step' => 'generate_report',
                        'workflow' => $workflow,
                    ],
                ];
            }
        }
    }

    /**
     * Handle draft document intent
     * 
     * ✅ QUAN TRỌNG: Chỉ xử lý cho assistant_type = 'document_drafting'
     *
     * @param string $userMessage
     * @param ChatSession $session
     * @param AiAssistant $assistant
     * @param array $intent
     * @param array $workflow
     * @return array
     */
    protected function handleDraftDocument(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent, array $workflow, ?callable $streamCallback = null): array
    {
        // ✅ QUAN TRỌNG: Chỉ xử lý cho document_drafting
        if ($assistant->getAssistantTypeValue() !== 'document_drafting') {
            Log::warning('handleDraftDocument called for non-document_drafting assistant', [
                'assistant_id' => $assistant->id,
                'assistant_type' => $assistant->getAssistantTypeValue(),
            ]);
            return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
        }
        
        try {
            $collectedData = $session->collected_data ?? [];
            
            // ✅ MỚI: Log template detection
            Log::info('Starting document drafting', [
                'session_id' => $session->id,
                'assistant_id' => $assistant->id,
                'user_message' => substr($userMessage, 0, 200),
            ]);
            
            // Detect document type from user request
            $documentType = $this->detectDocumentType($userMessage, $intent, $assistant);
            
            if (!$documentType) {
                Log::info('Document type not detected, asking user', [
                    'session_id' => $session->id,
                    'user_message' => substr($userMessage, 0, 100),
                ]);
                return [
                    'response' => "Tôi cần biết loại văn bản bạn muốn soạn thảo. Ví dụ: Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, hoặc Nghị quyết.",
                    'workflow_state' => [
                        'current_step' => 'ask_document_type',
                        'workflow' => $workflow,
                    ],
                ];
            }
            
            // Detect template subtype from user request (bổ nhiệm, khen thưởng, di, den, etc.)
            $templateSubtype = $this->detectTemplateSubtype($userMessage, $documentType);
            
            // ✅ MỚI: Log template detection
            Log::info('Template detection for document drafting', [
                'session_id' => $session->id,
                'assistant_id' => $assistant->id,
                'document_type' => $documentType->value,
                'template_subtype' => $templateSubtype,
                'user_message' => substr($userMessage, 0, 200),
            ]);
            
            // Draft document using DocumentDraftingService
            $result = $this->documentDraftingService->draftDocument(
                $userMessage,
                $documentType,
                $session,
                $assistant,
                $collectedData,
                $templateSubtype
            );
            
            // ✅ MỚI: Log template usage
            if (isset($result['metadata']['template_used']) && $result['metadata']['template_used']) {
                Log::info('Template used successfully for document drafting', [
                    'session_id' => $session->id,
                    'assistant_id' => $assistant->id,
                    'template_id' => $result['metadata']['template_id'] ?? null,
                    'document_type' => $documentType->value,
                    'template_subtype' => $templateSubtype,
                ]);
            } else {
                Log::warning('No template used, using generic generation', [
                    'session_id' => $session->id,
                    'assistant_id' => $assistant->id,
                    'document_type' => $documentType->value,
                    'template_subtype' => $templateSubtype,
                    'reason' => 'Template not found or not applicable',
                ]);
            }
            
            // Update session with collected data
            $session->update([
                'collected_data' => array_merge($collectedData, $result['metadata']),
            ]);
            
            // Build response message
            $response = "✅ Đã soạn thảo {$documentType->displayName()} thành công!\n\n";
            $response .= "**Nội dung văn bản:**\n\n";
            $response .= $result['content'] . "\n\n";
            
            if (isset($result['file_path'])) {
                $response .= "📄 **File DOCX:** " . $result['file_path'] . "\n\n";
            }
            
            // Add compliance check results if available
            if (isset($result['metadata']['compliance_check'])) {
                $compliance = $result['metadata']['compliance_check'];
                if (!$compliance['is_valid']) {
                    $response .= "⚠️ **Cảnh báo:**\n";
                    foreach ($compliance['errors'] as $error) {
                        $response .= "- {$error}\n";
                    }
                    foreach ($compliance['warnings'] as $warning) {
                        $response .= "- ⚠️ {$warning}\n";
                    }
                    if (!empty($compliance['suggestions'])) {
                        $response .= "\n💡 **Gợi ý:**\n";
                        foreach ($compliance['suggestions'] as $suggestion) {
                            $response .= "- {$suggestion}\n";
                        }
                    }
                } else {
                    $response .= "✅ Văn bản đã được kiểm tra và tuân thủ quy định.\n";
                }
            }
            
            // Add template info to response if template was used
            if (isset($result['metadata']['template_used']) && $result['metadata']['template_used']) {
                $response .= "\n📋 **Template đã sử dụng:** Có";
                if (isset($result['metadata']['template_id'])) {
                    $response .= " (ID: {$result['metadata']['template_id']})";
                }
                $response .= "\n";
            } else {
                $response .= "\n📋 **Template đã sử dụng:** Không (tạo từ cấu trúc mặc định)\n";
            }
            
            Log::info('Document drafted successfully', [
                'session_id' => $session->id,
                'assistant_id' => $assistant->id,
                'document_type' => $documentType->value,
                'template_subtype' => $templateSubtype,
                'template_used' => $result['metadata']['template_used'] ?? false,
                'template_id' => $result['metadata']['template_id'] ?? null,
                'file_path' => $result['file_path'] ?? null,
            ]);
            
            return [
                'response' => $response,
                'workflow_state' => [
                    'current_step' => 'completed',
                    'workflow' => $workflow,
                ],
                'document' => $result,
            ];
        } catch (\Exception $e) {
            $documentTypeValue = isset($documentType) && $documentType ? $documentType->value : null;
            Log::error('Failed to draft document', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
                'session_id' => $session->id,
                'document_type' => $documentTypeValue,
                'template_subtype' => $templateSubtype ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'response' => 'Xin lỗi, đã có lỗi xảy ra khi soạn thảo văn bản. Vui lòng thử lại sau.',
                'workflow_state' => [
                    'current_step' => 'draft_document',
                    'workflow' => $workflow,
                ],
            ];
        }
    }
    
    /**
     * Detect document type from user request
     */
    protected function detectDocumentType(string $userMessage, array $intent, ?\App\Models\AiAssistant $assistant = null): ?\App\Enums\DocumentType
    {
        $message = strtolower($userMessage);
        
        // ✅ MỚI: Nếu assistant có template, ưu tiên dùng document_type của template
        if ($assistant) {
            $templates = \App\Models\DocumentTemplate::where('ai_assistant_id', $assistant->id)
                ->where('is_active', true)
                ->get();
            
            // Nếu assistant chỉ có 1 template, ưu tiên dùng document_type của template đó
            if ($templates->count() === 1) {
                $template = $templates->first();
                try {
                    $templateDocType = \App\Enums\DocumentType::from($template->document_type);
                    Log::info('✅ [SmartAssistantEngine] Using template document_type (single template assistant)', [
                        'assistant_id' => $assistant->id,
                        'template_id' => $template->id,
                        'template_document_type' => $template->document_type,
                        'user_message' => substr($userMessage, 0, 100),
                    ]);
                    return $templateDocType;
                } catch (\ValueError $e) {
                    // Invalid type, continue to normal detection
                }
            }
        }
        
        // Check intent entity first
        if (isset($intent['entity']['document_type'])) {
            $type = $intent['entity']['document_type'];
            try {
                $detectedType = \App\Enums\DocumentType::from($type);
                
                // ✅ MỚI: Nếu assistant có template, verify detected type matches template
                if ($assistant && $templates->count() > 0) {
                    $hasMatchingTemplate = $templates->contains(function ($t) use ($type) {
                        return $t->document_type === $type;
                    });
                    
                    if (!$hasMatchingTemplate && $templates->count() === 1) {
                        // AI detect sai, dùng template document_type thay thế
                        $template = $templates->first();
                        try {
                            $templateDocType = \App\Enums\DocumentType::from($template->document_type);
                            Log::warning('⚠️ [SmartAssistantEngine] AI detected wrong document_type, using template document_type instead', [
                                'assistant_id' => $assistant->id,
                                'ai_detected' => $type,
                                'template_document_type' => $template->document_type,
                                'user_message' => substr($userMessage, 0, 100),
                            ]);
                            return $templateDocType;
                        } catch (\ValueError $e) {
                            // Invalid type, use AI detected
                        }
                    }
                }
                
                return $detectedType;
            } catch (\ValueError $e) {
                // Invalid type, continue to keyword detection
            }
        }
        
        // Keyword detection
        $keywords = [
            'cong van' => \App\Enums\DocumentType::CONG_VAN,
            'công văn' => \App\Enums\DocumentType::CONG_VAN,
            'quyet dinh' => \App\Enums\DocumentType::QUYET_DINH,
            'quyết định' => \App\Enums\DocumentType::QUYET_DINH,
            'to trinh' => \App\Enums\DocumentType::TO_TRINH,
            'tờ trình' => \App\Enums\DocumentType::TO_TRINH,
            'bao cao' => \App\Enums\DocumentType::BAO_CAO,
            'báo cáo' => \App\Enums\DocumentType::BAO_CAO,
            'bien ban' => \App\Enums\DocumentType::BIEN_BAN,
            'biên bản' => \App\Enums\DocumentType::BIEN_BAN,
            'thong bao' => \App\Enums\DocumentType::THONG_BAO,
            'thông báo' => \App\Enums\DocumentType::THONG_BAO,
            'nghi quyet' => \App\Enums\DocumentType::NGHI_QUYET,
            'nghị quyết' => \App\Enums\DocumentType::NGHI_QUYET,
        ];
        
        foreach ($keywords as $keyword => $type) {
            if (str_contains($message, $keyword)) {
                return $type;
            }
        }
        
        return null;
    }
    
    /**
     * Detect template subtype from user message
     */
    protected function detectTemplateSubtype(string $userMessage, \App\Enums\DocumentType $documentType): ?string
    {
        $message = strtolower($userMessage);
        
        // Quyết định subtypes
        if ($documentType === \App\Enums\DocumentType::QUYET_DINH) {
            if (str_contains($message, 'bổ nhiệm') || str_contains($message, 'bo nhiem') || str_contains($message, 'bonhiem')) {
                return 'bo_nhiem';
            }
            if (str_contains($message, 'khen thưởng') || str_contains($message, 'khen thuong') || str_contains($message, 'khenthuong')) {
                return 'khen_thuong';
            }
            if (str_contains($message, 'kỷ luật') || str_contains($message, 'ky luat') || str_contains($message, 'kyluat')) {
                return 'ky_luat';
            }
        }
        
        // Công văn subtypes
        if ($documentType === \App\Enums\DocumentType::CONG_VAN) {
            if (str_contains($message, ' công văn đi') || str_contains($message, 'cong van di') || 
                str_contains($message, 'gửi') || str_contains($message, 'gui')) {
                return 'di';
            }
            if (str_contains($message, ' công văn đến') || str_contains($message, 'cong van den') || 
                str_contains($message, 'nhận') || str_contains($message, 'nhan')) {
                return 'den';
            }
        }
        
        return null;
    }
    
    /**
     * Handle ask question intent
     * 
     * ✅ QUAN TRỌNG: Chỉ xử lý cho assistant_type = 'qa_based_document'
     * KHÔNG gọi ReportGenerator - chỉ dùng cho report_generator
     *
     * @param string $userMessage
     * @param ChatSession $session
     * @param AiAssistant $assistant
     * @param array $intent
     * @return array
     */
    protected function handleAskQuestion(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent, ?callable $streamCallback = null): array
    {
        Log::info('🔵 [handleAskQuestion] Called', [
            'assistant_id' => $assistant->id,
            'assistant_type' => $assistant->getAssistantTypeValue() ?? 'unknown',
            'user_message' => substr($userMessage, 0, 100),
        ]);
        
        // ✅ QUAN TRỌNG: Chỉ xử lý cho qa_based_document
        $assistantTypeValue = $assistant->getAssistantTypeValue();
        if ($assistantTypeValue !== 'qa_based_document') {
            Log::warning('🔵 [handleAskQuestion] Not Q&A assistant, falling back to generic', [
                'assistant_type' => $assistantTypeValue,
                'has_stream_callback' => !!$streamCallback,
            ]);
            // ✅ FIX: Truyền streamCallback vào fallback
            return $this->handleGenericRequest($userMessage, $session, $assistant, $intent, $streamCallback);
        }
        
        if ($assistantTypeValue === 'qa_based_document') {
            try {
                // ✅ BƯỚC 1: Check if assistant has documents
                // ✅ FIX: Check cả status='indexed' HOẶC is_indexed=true (vì có thể status='error' nhưng vẫn có embeddings)
                $documentsCount = $assistant->documents()
                    ->where(function($q) {
                        $q->where('status', 'indexed')
                          ->orWhere('is_indexed', true);
                    })
                    ->where('file_type', '!=', 'url') // Exclude reference URL documents
                    ->whereHas('documentChunks', function($q) {
                        $q->whereNotNull('embedding');
                    })
                    ->count();
                
                if ($documentsCount > 0) {
                    // ✅ Có documents → Tìm kiếm trong documents (exclude reference URLs)
                    // ✅ FIX: Thử với nhiều threshold để đảm bảo tìm được kết quả
                    $searchResults = null;
                    $thresholds = [0.7, 0.5, 0.3];
                    $usedThreshold = null;
                    
                    foreach ($thresholds as $threshold) {
                        $tempResults = $this->vectorSearchService->searchSimilar(
                            $userMessage,
                            $assistant->id,
                            5,
                            $threshold,
                            [] // No filter, but we'll filter by file_type in query
                        );
                        
                        // Filter out reference URL chunks
                        $tempResults = array_filter($tempResults, function($result) {
                            $metadata = $result['metadata'] ?? [];
                            return ($metadata['source_type'] ?? null) !== 'reference_url';
                        });
                        
                        if (!empty($tempResults)) {
                            $searchResults = $tempResults;
                            $usedThreshold = $threshold;
                            Log::info('🔵 [handleAskQuestion] Found documents with threshold', [
                                'assistant_id' => $assistant->id,
                                'threshold' => $threshold,
                                'results_count' => count($searchResults),
                                'min_similarity' => min(array_column($searchResults, 'similarity')),
                                'max_similarity' => max(array_column($searchResults, 'similarity')),
                            ]);
                            break;
                        }
                    }
                    
                    if (!empty($searchResults)) {
                        $context = array_map(fn($r) => $r['content'], $searchResults);
                        $answer = $this->generateAnswerFromContext($userMessage, $context, $assistant, $streamCallback);
                        
                        Log::info('🔵 [handleAskQuestion] Using documents for answer', [
                            'assistant_id' => $assistant->id,
                            'threshold_used' => $usedThreshold,
                            'results_count' => count($searchResults),
                            'answer_length' => strlen($answer),
                        ]);
                        
                        return [
                            'response' => $answer,
                            'workflow_state' => null,
                            'sources' => array_map(fn($r) => [
                                'content' => substr($r['content'], 0, 200),
                                'similarity' => $r['similarity'],
                                'source_type' => $r['metadata']['source_type'] ?? 'document',
                            ], $searchResults),
                        ];
                    } else {
                        // ✅ FIX: Log chi tiết khi không tìm thấy kết quả
                        $totalChunks = DocumentChunk::query()
                            ->whereHas('assistantDocument', function ($q) use ($assistant) {
                                $q->where('ai_assistant_id', $assistant->id)
                                  ->where(function($q2) {
                                      $q2->where('status', 'indexed')
                                         ->orWhere('is_indexed', true);
                                  })
                                  ->where('file_type', '!=', 'url');
                            })
                            ->whereNotNull('embedding')
                            ->count();
                        
                        Log::warning('🔵 [handleAskQuestion] Documents exist but no search results found', [
                            'assistant_id' => $assistant->id,
                            'documents_count' => $documentsCount,
                            'total_chunks' => $totalChunks,
                            'user_message' => substr($userMessage, 0, 100),
                            'tried_thresholds' => $thresholds,
                        ]);
                    }
                }
                
                // ✅ BƯỚC 2: Check if assistant has reference URLs
                $referenceUrlsCount = $assistant->referenceUrls()
                    ->where('status', 'indexed')
                    ->count();
                
                Log::info('🔵 [handleAskQuestion] Checking reference URLs', [
                    'assistant_id' => $assistant->id,
                    'reference_urls_count' => $referenceUrlsCount,
                    'user_message' => substr($userMessage, 0, 100),
                ]);
                
                if ($referenceUrlsCount > 0) {
                    // ✅ Có reference URLs → Tìm kiếm trong nội dung đã crawl
                    // Thử với threshold 0.7 trước, nếu không có thì giảm xuống 0.5
                    $searchResults = $this->vectorSearchService->searchSimilar(
                        $userMessage,
                        $assistant->id,
                        5,
                        0.7,
                        ['source_type' => 'reference_url'] // Filter by source type
                    );
                    
                    // Nếu không tìm thấy với threshold 0.7, thử với 0.5
                    if (empty($searchResults)) {
                        Log::info('🔵 [handleAskQuestion] No results with 0.7 threshold, trying 0.5', [
                            'assistant_id' => $assistant->id,
                        ]);
                        $searchResults = $this->vectorSearchService->searchSimilar(
                            $userMessage,
                            $assistant->id,
                            5,
                            0.5,
                            ['source_type' => 'reference_url']
                        );
                    }
                    
                    Log::info('🔵 [handleAskQuestion] Reference URL search results', [
                        'assistant_id' => $assistant->id,
                        'results_count' => count($searchResults),
                        'results' => array_map(fn($r) => [
                            'similarity' => $r['similarity'],
                            'content_preview' => substr($r['content'], 0, 100),
                            'source_url' => $r['metadata']['source_url'] ?? null,
                        ], $searchResults),
                    ]);
                    
                    if (!empty($searchResults)) {
                        $context = array_map(fn($r) => $r['content'], $searchResults);
                        
                        // Log context để debug
                        $contextText = implode(' ', $context);
                        Log::info('🔵 [handleAskQuestion] Context before generating answer', [
                            'assistant_id' => $assistant->id,
                            'context_length' => strlen($contextText),
                            'contains_2025' => strpos($contextText, '2025') !== false,
                            'contains_2013' => strpos($contextText, '2013') !== false,
                            'context_preview' => substr($contextText, 0, 300),
                        ]);
                        
                        $answer = $this->generateAnswerFromContext($userMessage, $context, $assistant, $streamCallback);
                        
                        Log::info('🔵 [handleAskQuestion] Using reference URLs for answer', [
                            'assistant_id' => $assistant->id,
                            'context_length' => strlen($contextText),
                            'answer_length' => strlen($answer),
                            'answer_contains_2025' => strpos($answer, '2025') !== false,
                            'answer_contains_2013' => strpos($answer, '2013') !== false,
                            'answer_preview' => substr($answer, 0, 300),
                        ]);
                        
                        // Get source URLs
                        $sourceUrls = array_unique(array_filter(array_map(function($r) {
                            return $r['metadata']['source_url'] ?? null;
                        }, $searchResults)));
                        
                        return [
                            'response' => $answer,
                            'workflow_state' => null,
                            'sources' => array_map(fn($r) => [
                                'content' => substr($r['content'], 0, 200),
                                'similarity' => $r['similarity'],
                                'source_type' => 'reference_url',
                                'source_url' => $r['metadata']['source_url'] ?? null,
                            ], $searchResults),
                            'reference_urls' => array_values($sourceUrls), // Thêm thông tin URL tham khảo
                        ];
                    } else {
                        Log::warning('🔵 [handleAskQuestion] Reference URLs found but no search results', [
                            'assistant_id' => $assistant->id,
                            'user_message' => substr($userMessage, 0, 100),
                        ]);
                    }
                }
                
                // ✅ BƯỚC 3: Không có documents và reference URLs HOẶC không tìm thấy kết quả → Fallback
                // ✅ FIX: Chỉ fallback khi thực sự không có documents hoặc không tìm thấy kết quả phù hợp
                if ($documentsCount === 0 && $referenceUrlsCount === 0) {
                    Log::info('🔵 [handleAskQuestion] No documents or reference URLs, falling back to generic', [
                        'assistant_id' => $assistant->id,
                        'has_documents' => false,
                        'has_reference_urls' => false,
                        'has_stream_callback' => !!$streamCallback,
                    ]);
                } else {
                    // Có documents nhưng không tìm thấy kết quả phù hợp
                    Log::warning('🔵 [handleAskQuestion] Documents/URLs exist but no relevant results found, falling back to generic', [
                        'assistant_id' => $assistant->id,
                        'has_documents' => $documentsCount > 0,
                        'has_reference_urls' => $referenceUrlsCount > 0,
                        'user_message' => substr($userMessage, 0, 100),
                        'has_stream_callback' => !!$streamCallback,
                    ]);
                }
                
                // ✅ FIX: Fallback về handleGenericRequest với streamCallback để có streaming
                // ✅ LƯU Ý: Nên thông báo cho user rằng không tìm thấy trong tài liệu
                return $this->handleGenericRequest($userMessage, $session, $assistant, $intent, $streamCallback);
            } catch (\Exception $e) {
                // If vector search fails, fallback to generic question
                Log::warning('Vector search failed, falling back to generic question', [
                    'error' => $e->getMessage(),
                    'assistant_id' => $assistant->id,
                    'has_stream_callback' => !!$streamCallback,
                ]);
                
                // ✅ FIX: Truyền streamCallback vào fallback
                return $this->handleGenericRequest($userMessage, $session, $assistant, $intent, $streamCallback);
            }
        }
        
        // Generic question - ✅ FIX: Truyền streamCallback
        return $this->handleGenericRequest($userMessage, $session, $assistant, $intent, $streamCallback);
    }

    /**
     * Handle classify document intent (for document_management)
     */
    protected function handleClassifyDocument(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent): array
    {
        // ✅ QUAN TRỌNG: Chỉ xử lý cho document_management
        if ($assistant->getAssistantTypeValue() !== 'document_management') {
            return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
        }
        
        try {
            // Check if user uploaded a file
            // For now, we'll handle text-based classification
            // File upload will be handled via API endpoint
            
            $response = "Để phân loại văn bản, vui lòng upload file PDF/DOCX hoặc cung cấp nội dung văn bản.\n\n";
            $response .= "Tôi có thể giúp bạn:\n";
            $response .= "- Phân loại văn bản đến/văn bản đi\n";
            $response .= "- Xác định loại văn bản (Công văn, Quyết định, Tờ trình, ...)\n";
            $response .= "- Xác định mức độ khẩn cấp\n";
            $response .= "- Tính toán thời hạn xử lý\n";
            $response .= "- Gợi ý người xử lý phù hợp\n";
            $response .= "- Tự động lưu trữ theo cấu trúc";
            
            return [
                'response' => $response,
                'workflow_state' => [
                    'current_step' => 'waiting_for_document',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to handle classify document', [
                'error' => $e->getMessage(),
            ]);
            return [
                'response' => 'Xin lỗi, đã có lỗi xảy ra khi phân loại văn bản.',
                'workflow_state' => null,
            ];
        }
    }
    
    /**
     * Handle get reminders intent (for document_management)
     */
    protected function handleGetReminders(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent): array
    {
        // ✅ QUAN TRỌNG: Chỉ xử lý cho document_management
        if ($assistant->getAssistantTypeValue() !== 'document_management') {
            return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
        }
        
        try {
            // Get reminders
            $reminders = $this->documentReminderService->getReminders($assistant, 1);
            $overdue = $this->documentReminderService->getOverdueDocuments($assistant);
            $dueToday = $this->documentReminderService->getDocumentsDueToday($assistant);
            
            // Format reminder message
            $response = $this->documentReminderService->formatReminderMessage($reminders, $overdue, $dueToday);
            
            return [
                'response' => $response,
                'workflow_state' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get reminders', [
                'error' => $e->getMessage(),
            ]);
            return [
                'response' => 'Xin lỗi, đã có lỗi xảy ra khi lấy nhắc nhở.',
                'workflow_state' => null,
            ];
        }
    }
    
    /**
     * Handle search document intent
     *
     * @param string $userMessage
     * @param ChatSession $session
     * @param AiAssistant $assistant
     * @param array $intent
     * @return array
     */
    protected function handleSearchDocument(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent): array
    {
        // For document_management, search in administrative documents
        if ($assistant->getAssistantTypeValue() === 'document_management') {
            try {
                $filters = [];
                
                // Extract filters from user message
                if (str_contains(strtolower($userMessage), 'văn bản đến') || str_contains(strtolower($userMessage), 'van ban den')) {
                    $filters['loai_van_ban'] = 'van_ban_den';
                } elseif (str_contains(strtolower($userMessage), 'văn bản đi') || str_contains(strtolower($userMessage), 'van ban di')) {
                    $filters['loai_van_ban'] = 'van_ban_di';
                }
                
                // Search documents
                $documents = $this->documentManagementService->searchDocuments($userMessage, $assistant, $filters);
                
                if (empty($documents)) {
                    return [
                        'response' => 'Không tìm thấy văn bản phù hợp.',
                        'workflow_state' => null,
                    ];
                }
                
                $response = "📄 **Tìm thấy " . count($documents) . " văn bản:**\n\n";
                foreach ($documents as $index => $doc) {
                    $response .= ($index + 1) . ". ";
                    if ($doc['so_van_ban']) {
                        $response .= "Số: " . $doc['so_van_ban'] . " | ";
                    }
                    if ($doc['ngay_van_ban']) {
                        $response .= "Ngày: " . $doc['ngay_van_ban'] . " | ";
                    }
                    $response .= ($doc['loai_van_ban'] === 'van_ban_den' ? 'Văn bản đến' : 'Văn bản đi') . "\n";
                    if ($doc['trich_yeu']) {
                        $response .= "   " . substr($doc['trich_yeu'], 0, 150) . "...\n";
                    }
                    if ($doc['noi_gui']) {
                        $response .= "   Từ: " . $doc['noi_gui'] . "\n";
                    }
                    if ($doc['noi_nhan']) {
                        $response .= "   Đến: " . $doc['noi_nhan'] . "\n";
                    }
                    $response .= "\n";
                }
                
                return [
                    'response' => $response,
                    'workflow_state' => null,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to search documents', [
                    'error' => $e->getMessage(),
                ]);
                // Fallback to generic search
            }
        }
        
        // Generic search (for qa_based_document)
        $searchResults = $this->vectorSearchService->searchSimilar($userMessage, $assistant->id, 10);
        
        if (empty($searchResults)) {
            return [
                'response' => 'Không tìm thấy kết quả phù hợp.',
                'workflow_state' => null,
            ];
        }
        
        $response = "Tìm thấy " . count($searchResults) . " kết quả:\n\n";
        foreach ($searchResults as $index => $result) {
            $response .= ($index + 1) . ". " . substr($result['content'], 0, 200) . "...\n";
        }
        
        return [
            'response' => $response,
            'workflow_state' => null,
            'search_results' => $searchResults,
        ];
    }

    /**
     * Handle generic request
     *
     * @param string $userMessage
     * @param ChatSession $session
     * @param AiAssistant $assistant
     * @param array $intent
     * @param callable|null $streamCallback
     * @return array
     */
    protected function handleGenericRequest(string $userMessage, ChatSession $session, AiAssistant $assistant, array $intent, ?callable $streamCallback = null): array
    {
        $messages = $this->buildChatMessages($session, $userMessage, $assistant);
        
        // ✅ PHASE 2: Stream từ OpenAI nếu có callback
        if ($streamCallback) {
            $fullContent = '';
            $chunkCount = 0;
            
            Log::info('🚀 [STREAM DEBUG] handleGenericRequest: Starting OpenAI stream', [
                'session_id' => $session->id ?? null,
                'timestamp' => microtime(true),
            ]);
            
            $response = OpenAI::chat()->createStreamed([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.7,
            ]);
            
            Log::info('🚀 [STREAM DEBUG] handleGenericRequest: OpenAI stream created, starting to read chunks', [
                'session_id' => $session->id ?? null,
                'timestamp' => microtime(true),
            ]);
            
            foreach ($response as $chunk) {
                $delta = $chunk->choices[0]->delta->content ?? '';
                if ($delta) {
                    $chunkCount++;
                    $fullContent .= $delta;
                    
                    // ✅ DEBUG: Log chunk đầu tiên
                    if ($chunkCount === 1) {
                        Log::info('🚀 [STREAM DEBUG] handleGenericRequest: First chunk from OpenAI', [
                            'session_id' => $session->id ?? null,
                            'chunk_size' => strlen($delta),
                            'chunk_preview' => substr($delta, 0, 50),
                            'timestamp' => microtime(true),
                        ]);
                    }
                    
                    $streamCallback($delta); // ✅ Stream ngay lập tức
                }
            }
            
            Log::info('🚀 [STREAM DEBUG] handleGenericRequest: OpenAI stream completed', [
                'session_id' => $session->id ?? null,
                'total_chunks' => $chunkCount,
                'total_length' => strlen($fullContent),
                'timestamp' => microtime(true),
            ]);
            
            // ✅ PHASE 2: Skip enhancement khi streaming để tránh delay
            // Enhancement sẽ làm chậm streaming, nên bỏ qua khi có streaming
            return [
                'response' => $fullContent,
                'workflow_state' => null,
            ];
        } else {
            // Fallback: Non-streaming mode (cho backward compatibility)
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.7,
            ]);
            
            $rawResponse = $response->choices[0]->message->content;
            
            // ✅ CẢI TIẾN: Enhance response để tự nhiên hơn (tùy chọn, có thể skip nếu response đã tốt)
            // Note: handleGenericRequest đã sử dụng system prompt tốt, nên có thể skip enhance để tiết kiệm API call
            // Chỉ enhance nếu response ngắn và có thể cải thiện
            $enhancedResponse = $rawResponse;
            if (strlen($rawResponse) < 500) { // Chỉ enhance response ngắn
                try {
                    $enhancedResponse = $this->responseEnhancer->enhanceResponse(
                        $rawResponse,
                        $userMessage,
                        $session,
                        $assistant,
                        ['intent' => $intent],
                        'answer'
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to enhance response in handleGenericRequest, using raw response', [
                        'error' => $e->getMessage(),
                    ]);
                    $enhancedResponse = $rawResponse;
                }
            }
            
            return [
                'response' => $enhancedResponse,
                'workflow_state' => null,
            ];
        }
    }

    /**
     * Generate answer from context using AI
     *
     * @param string $question
     * @param array $context
     * @param AiAssistant $assistant
     * @param callable|null $streamCallback
     * @return string
     */
    protected function generateAnswerFromContext(string $question, array $context, AiAssistant $assistant, ?callable $streamCallback = null): string
    {
        $contextText = implode("\n\n---\n\n", array_map(fn($c, $i) => "[Nguồn " . ($i + 1) . "]\n" . $c, $context, array_keys($context)));
        
        // ✅ Extract years from context để nhấn mạnh (nếu cần)
        preg_match_all('/\b(20\d{2})\b/', $contextText, $years);
        $yearsInContext = array_unique($years[1] ?? []);
        $latestYear = !empty($yearsInContext) ? max($yearsInContext) : null;
        
        // ✅ MỚI: Sử dụng SystemPromptBuilder để lấy prompt phù hợp với loại trợ lý
        $builder = app(\App\Services\SystemPromptBuilder::class);
        $baseSystemPrompt = $builder->build($assistant);
        
        // ✅ Thêm quy tắc cụ thể cho việc trả lời từ context
        $systemPrompt = $baseSystemPrompt . "\n\n";
        $systemPrompt .= "⚠️ **QUY TẮC ĐẶC BIỆT KHI TRẢ LỜI TỪ TÀI LIỆU:**\n\n";
        $systemPrompt .= "1. **CHỈ SỬ DỤNG TÀI LIỆU ĐƯỢC CUNG CẤP:** Bạn PHẢI chỉ sử dụng thông tin từ tài liệu tham khảo được cung cấp bên dưới. KHÔNG được sử dụng bất kỳ kiến thức nào từ training data hoặc kiến thức chung.\n\n";
        $systemPrompt .= "2. **ĐỌC KỸ TÀI LIỆU:** Bạn PHẢI đọc kỹ toàn bộ tài liệu tham khảo trước khi trả lời. Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI sử dụng thông tin đó.\n\n";
        
        if ($latestYear) {
            $systemPrompt .= "3. **SỬ DỤNG ĐÚNG THÔNG TIN TRONG TÀI LIỆU:** Tài liệu đề cập đến năm {$latestYear} hoặc các thông tin cụ thể khác. Bạn PHẢI sử dụng đúng thông tin trong tài liệu, không được thay thế bằng thông tin cũ hoặc khác.\n\n";
        } else {
            $systemPrompt .= "3. **SỬ DỤNG ĐÚNG THÔNG TIN:** Nếu tài liệu đề cập đến thông tin cụ thể (năm, số liệu, tên, v.v.), bạn PHẢI sử dụng đúng thông tin đó.\n\n";
        }
        
        $systemPrompt .= "4. **TRẢ LỜI ĐẦY ĐỦ:** Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI trả lời đầy đủ dựa trên tài liệu. KHÔNG được nói \"tài liệu không đề cập\" nếu thông tin thực sự có trong tài liệu.\n\n";
        $systemPrompt .= "5. **TRÍCH DẪN NGUỒN:** Trả lời chính xác, chi tiết, có cấu trúc và trích dẫn nguồn [Nguồn X] khi có thể.\n\n";
        $systemPrompt .= "6. **NẾU THÔNG TIN KHÔNG ĐỦ:** Chỉ khi tài liệu THỰC SỰ không có thông tin về câu hỏi, bạn mới được nói rằng tài liệu không đề cập. Nhưng trước đó, hãy đọc lại tài liệu một lần nữa để chắc chắn.\n\n";
        
        $userPrompt = "**CÂU HỎI:** {$question}\n\n";
        $userPrompt .= "**TÀI LIỆU THAM KHẢO (ĐÂY LÀ NGUỒN DUY NHẤT - CHỈ SỬ DỤNG THÔNG TIN TỪ ĐÂY):**\n{$contextText}\n\n";
        $userPrompt .= "**YÊU CẦU BẮT BUỘC:**\n";
        $userPrompt .= "1. Đọc kỹ TẤT CẢ tài liệu tham khảo trên\n";
        $userPrompt .= "2. Tìm kiếm thông tin liên quan đến câu hỏi trong tài liệu\n";
        $userPrompt .= "3. Trả lời câu hỏi CHỈ dựa trên thông tin tìm thấy trong tài liệu\n";
        $userPrompt .= "4. Nếu tài liệu có thông tin về câu hỏi, bạn PHẢI trả lời đầy đủ và chi tiết\n";
        $userPrompt .= "5. KHÔNG được sử dụng kiến thức chung hoặc kiến thức từ training data\n";
        $userPrompt .= "6. Chỉ nói \"tài liệu không đề cập\" khi bạn đã đọc kỹ và CHẮC CHẮN rằng tài liệu không có thông tin về câu hỏi\n";
        
        // ✅ PHASE 2: Stream từ OpenAI nếu có callback
        if ($streamCallback) {
            $fullAnswer = '';
            $response = OpenAI::chat()->createStreamed([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
                'temperature' => 0.1,
            ]);
            
            foreach ($response as $chunk) {
                $delta = $chunk->choices[0]->delta->content ?? '';
                if ($delta) {
                    $fullAnswer .= $delta;
                    $streamCallback($delta); // ✅ Stream ngay lập tức
                }
            }
            
            $answer = $fullAnswer;
        } else {
            // Fallback: Non-streaming mode
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
                'temperature' => 0.1, // Giảm xuống 0.1 để chính xác tối đa
            ]);
            
            $answer = $response->choices[0]->message->content;
        }
        
        // ✅ Post-processing: Kiểm tra và cảnh báo nếu answer chứa năm cũ
        if ($latestYear && (int)$latestYear >= 2024) {
            // Nếu context có năm 2024/2025 nhưng answer có 2013, có thể là lỗi
            if (preg_match('/\b2013\b/', $answer) && !preg_match('/\b(2024|2025)\b/', $answer)) {
                Log::warning('🔴 [generateAnswerFromContext] Answer contains old year 2013 but context has newer year', [
                    'latest_year_in_context' => $latestYear,
                    'answer_preview' => substr($answer, 0, 200),
                ]);
                // Không tự động sửa vì có thể context thực sự đề cập đến cả 2
            }
        }
        
        return $answer;
    }

    /**
     * Get next step in workflow
     *
     * @param array $workflow
     * @param array $collectedData
     * @return array|null
     */
    protected function getNextStep(array $workflow, array $collectedData): ?array
    {
        foreach ($workflow['steps'] ?? [] as $step) {
            if ($step['type'] === 'collect_info') {
                // Support both 'field' (from WorkflowPlanner) and 'field_key' (from TemplateAnalyzer)
                $field = $step['field'] ?? $step['field_key'] ?? null;
                if ($field && !isset($collectedData[$field])) {
                    return $step;
                }
            }
        }
        
        return null;
    }

    /**
     * Extract data from user message
     *
     * @param string $message
     * @param array $step
     * @param AiAssistant $assistant
     * @return array
     */
    protected function extractDataFromMessage(string $message, array $step, AiAssistant $assistant): array
    {
        // Support both 'field' (from WorkflowPlanner) and 'field_key' (from TemplateAnalyzer)
        $field = $step['field'] ?? $step['field_key'] ?? null;
        if (!$field) {
            return [];
        }
        
        // Simple extraction - can be improved with AI
        return [$field => $message];
    }

    /**
     * Check if all required data is collected
     *
     * @param array $workflow
     * @param array $collectedData
     * @return bool
     */
    protected function checkAllDataCollected(array $workflow, array $collectedData): bool
    {
        foreach ($workflow['steps'] ?? [] as $step) {
            if ($step['type'] === 'collect_info' && ($step['required'] ?? true)) {
                // Support both 'field' (from WorkflowPlanner) and 'field_key' (from TemplateAnalyzer)
                $field = $step['field'] ?? $step['field_key'] ?? null;
                if ($field && !isset($collectedData[$field])) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Get next missing field
     *
     * @param array $workflow
     * @param array $collectedData
     * @return array|null
     */
    protected function getNextMissingField(array $workflow, array $collectedData): ?array
    {
        return $this->getNextStep($workflow, $collectedData);
    }

    /**
     * Generate question for field
     *
     * @param array|null $field
     * @param AiAssistant $assistant
     * @return string
     */
    protected function generateQuestion(?array $field, AiAssistant $assistant): string
    {
        if (!$field) {
            return 'Bạn có thể cung cấp thêm thông tin không?';
        }
        
        $label = $field['label'] ?? $field['field'] ?? 'thông tin';
        $question = $field['question'] ?? null;
        
        if ($question) {
            return $question;
        }
        
        return "Để tiếp tục, tôi cần biết về: {$label}. Bạn có thể cung cấp thông tin này không?";
    }

    /**
     * ✅ MỚI: Phân tích request có đủ thông tin để tạo báo cáo không
     *
     * @param string $userMessage
     * @param array $workflow
     * @param array $collectedData
     * @return array
     */
    protected function analyzeRequestCompleteness(
        string $userMessage,
        array $workflow,
        array $collectedData
    ): array {
        // Check collectedData
        $hasData = !empty($collectedData);
        
        // Check user message có chứa thông tin cụ thể không
        $hasSpecificInfo = $this->extractSpecificInfo($userMessage, $workflow);
        
        // Check workflow có required fields không
        $requiredFields = $this->getRequiredFields($workflow);
        $hasRequiredFields = !empty($requiredFields);
        
        // Get missing fields
        $missingFields = $this->getMissingFields($workflow, $collectedData);
        
        return [
            'is_vague' => !$hasData && !$hasSpecificInfo,
            'has_sufficient_info' => $hasData || $hasSpecificInfo,
            'has_required_fields' => $hasRequiredFields,
            'missing_fields' => $missingFields,
        ];
    }

    /**
     * ✅ MỚI: Extract thông tin cụ thể từ user message
     * 
     * CHÚ Ý: Không còn detect "mẫu/sample" nữa - luôn cần data thật
     *
     * @param string $userMessage
     * @param array $workflow
     * @return bool
     */
    protected function extractSpecificInfo(string $userMessage, array $workflow): bool
    {
        $userMessage = strtolower($userMessage);
        
        // ❌ REMOVED: No longer treat "mẫu/sample/template" as sufficient info
        // User must provide actual data or we'll ask for it
        
        // Check các pattern cho thông tin cụ thể
        $specificPatterns = [
            // Tên công ty/tổ chức (phải có tên cụ thể, không chỉ là "công ty")
            '/\b(?:công ty|company|tổ chức|organization|đơn vị|unit)\s+([a-záàảãạăắằẳẵặâấầẩẫậéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵđ]{3,}[a-záàảãạăắằẳẵặâấầẩẫậéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵđ\s]*?)(?:\s|$|,|\.)/i',
            // Năm cụ thể
            '/\b(?:năm|year)\s+(\d{4})\b/i',
            '/\b(20\d{2})\b/', // Years like 2024, 2023
            // Loại báo cáo cụ thể
            '/\b(?:báo cáo|report)\s+(?:thường niên|annual|hàng năm|hàng tháng|monthly|hàng quý|quarterly|định kỳ|periodic)\b/i',
            // Địa điểm cụ thể (phải có tên địa điểm, không chỉ là "tại")
            '/\b(?:tại|ở|in|at)\s+([a-záàảãạăắằẳẵặâấầẩẫậéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵđ]{3,}[a-záàảãạăắằẳẵặâấầẩẫậéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵđ\s]*?)(?:\s|$|,|\.)/i',
            // Thời gian cụ thể
            '/\b(?:tháng|month)\s+(\d{1,2})\b/i',
            '/\b(?:quý|quarter)\s+(\d)\b/i',
            // Số điện thoại
            '/\b(?:số điện thoại|phone|điện thoại|sdt)\s*:?\s*([0-9\s\-\(\)]+)/i',
            // Địa chỉ
            '/\b(?:địa chỉ|address)\s*:?\s*([^,\n]{5,})/i',
        ];
        
        $foundSpecificInfo = false;
        $extractedCount = 0;
        
        foreach ($specificPatterns as $pattern) {
            if (preg_match($pattern, $userMessage, $matches)) {
                // Check if extracted value is meaningful (not just keywords)
                if (isset($matches[1]) && strlen(trim($matches[1])) >= 2) {
                    $foundSpecificInfo = true;
                    $extractedCount++;
                }
            }
        }
        
        // ✅ NEW: Require at least 2 pieces of specific info
        // Just saying "công ty" or "năm" is not enough
        if ($extractedCount >= 2) {
            Log::info('Found sufficient specific info in message', [
                'extracted_count' => $extractedCount,
                'message' => substr($userMessage, 0, 100),
            ]);
            return true;
        }
        
        Log::info('Insufficient specific info in message', [
            'extracted_count' => $extractedCount,
            'message' => substr($userMessage, 0, 100),
        ]);
        
        return false;
    }
    
    /**
     * ✅ NEW: Generate sample data for template preview
     * Used when user explicitly requests "tự điền data mẫu"
     *
     * @param AiAssistant $assistant
     * @param array $workflow
     * @return array
     */
    protected function generateSampleDataForTemplate(AiAssistant $assistant, array $workflow): array
    {
        Log::info('Generating sample data for template', [
            'assistant_id' => $assistant->id,
            'assistant_name' => $assistant->name,
        ]);
        
        // Sample data based on common Vietnamese document fields
        $sampleData = [
            // Organization
            'ten_co_quan' => 'CÔNG TY TNHH ABC',
            'ten_cong_ty' => 'CÔNG TY TNHH ABC',
            'ten_to_chuc' => 'CÔNG TY TNHH ABC',
            
            // Address & Contact
            'dia_chi' => '123 Đường X, Phường Y, Quận Z, Thành Phố H',
            'so_dien_thoai' => '0123456789',
            'email' => 'contact@abc.com',
            'website' => 'www.abc.com',
            
            // Tax & Legal
            'ma_so_thue' => '0123456789',
            'ma_so_doanh_nghiep' => '0123456789',
            
            // Representative
            'nguoi_dai_dien' => 'Nguyễn Văn A',
            'chuc_vu' => 'Giám Đốc',
            
            // Document info
            'loai_van_ban' => 'BÁO CÁO HOẠT ĐỘNG',
            'ten_loai_van_ban' => 'BÁO CÁO HOẠT ĐỘNG',
            'so_van_ban' => '01/BC-ABC',
            'so' => '01/BC-ABC',
            
            // Date & Time
            'ngay_thang_nam' => date('d/m/Y'),
            'ngay' => date('d'),
            'thang' => date('m'),
            'nam' => date('Y'),
            'ngay_lap_bao_cao' => date('d/m/Y'),
            
            // Period
            'thoi_gian' => 'Tháng ' . date('m') . ' Năm ' . date('Y'),
            'ky_bao_cao' => 'Tháng ' . date('m') . ' Năm ' . date('Y'),
        ];
        
        // Add workflow-specific fields if available
        if (!empty($workflow['steps'])) {
            foreach ($workflow['steps'] as $step) {
                if ($step['type'] === 'collect_info') {
                    $fieldKey = $step['field'] ?? $step['field_key'] ?? null;
                    if ($fieldKey && !isset($sampleData[$fieldKey])) {
                        // Generate sample value based on field name
                        $sampleData[$fieldKey] = $this->generateSampleValueForField($fieldKey, $step);
                    }
                }
            }
        }
        
        Log::info('Sample data generated for template', [
            'assistant_id' => $assistant->id,
            'sample_data_count' => count($sampleData),
            'sample_data_keys' => array_keys($sampleData),
        ]);
        
        return $sampleData;
    }
    
    /**
     * Generate sample value for a specific field
     *
     * @param string $fieldKey
     * @param array $step
     * @return string
     */
    protected function generateSampleValueForField(string $fieldKey, array $step): string
    {
        // Check field label/description for hints
        $label = strtolower($step['label'] ?? $step['question'] ?? $fieldKey);
        
        if (str_contains($label, 'tên') || str_contains($label, 'name')) {
            return 'Ví dụ: ' . ucfirst($fieldKey);
        }
        
        if (str_contains($label, 'số') || str_contains($label, 'number')) {
            return '0123456789';
        }
        
        if (str_contains($label, 'ngày') || str_contains($label, 'date')) {
            return date('d/m/Y');
        }
        
        if (str_contains($label, 'địa chỉ') || str_contains($label, 'address')) {
            return '123 Đường X, Phường Y, Quận Z, Thành Phố H';
        }
        
        if (str_contains($label, 'email')) {
            return 'example@company.com';
        }
        
        if (str_contains($label, 'phone') || str_contains($label, 'điện thoại')) {
            return '0123456789';
        }
        
        // Default
        return '[Dữ liệu mẫu]';
    }
    
    /**
     * ❌ DEPRECATED: No longer generate sample data automatically
     * User must provide real data OR explicitly request "tự điền"
     * 
     * This method is kept for backward compatibility but should not be used
     *
     * @param AiAssistant $assistant
     * @return array
     */
    protected function generateSampleData(AiAssistant $assistant): array
    {
        Log::warning('generateSampleData called - this should not happen anymore', [
            'assistant_id' => $assistant->id,
        ]);
        
        // Return empty array to force asking for info
        return [];
    }

    /**
     * ✅ MỚI: Lấy required fields từ workflow
     *
     * @param array $workflow
     * @return array
     */
    protected function getRequiredFields(array $workflow): array
    {
        $requiredFields = [];
        
        foreach ($workflow['steps'] ?? [] as $step) {
            if ($step['type'] === 'collect_info' && ($step['required'] ?? true)) {
                $requiredFields[] = $step;
            }
        }
        
        return $requiredFields;
    }

    /**
     * ✅ MỚI: Lấy missing fields từ workflow
     *
     * @param array $workflow
     * @param array $collectedData
     * @return array
     */
    protected function getMissingFields(array $workflow, array $collectedData): array
    {
        $missingFields = [];
        
        foreach ($workflow['steps'] ?? [] as $step) {
            if ($step['type'] === 'collect_info' && ($step['required'] ?? true)) {
                $field = $step['field'] ?? $step['field_key'] ?? null;
                if ($field && !isset($collectedData[$field])) {
                    $missingFields[] = $step;
                }
            }
        }
        
        return $missingFields;
    }

    /**
     * ✅ MỚI: Hỏi user về thông tin cần thiết
     *
     * @param array $workflow
     * @param AiAssistant $assistant
     * @param array $collectedData
     * @return array
     */
    protected function askForRequiredInfo(array $workflow, AiAssistant $assistant, array $collectedData = []): array
    {
        // Get required fields từ workflow
        $requiredFields = $this->getRequiredFields($workflow);
        
        // Get missing fields
        $missingFields = $this->getMissingFields($workflow, $collectedData);
        
        if (empty($requiredFields) && empty($missingFields)) {
            // ✅ FIX: Không có required fields → Tạo báo cáo ngay với template (giữ nguyên format)
            // KHÔNG hỏi user, tạo báo cáo trực tiếp từ template
            try {
                $reportGenerator = app(ReportGenerator::class);
                $reportResult = $reportGenerator->generateReport(
                    $assistant,
                    $session,
                    $collectedData, // Có thể rỗng, nhưng vẫn tạo báo cáo với template
                    null
                );
                
                Log::info('Report generated (no required fields)', [
                    'session_id' => $session->id,
                    'assistant_id' => $assistant->id,
                    'report_id' => $reportResult['report_id'] ?? null,
                    'collected_data_count' => count($collectedData),
                ]);
                
                return [
                    'response' => "Báo cáo đã được tạo thành công!\n\n" . 
                                 "Bạn có thể xem nội dung báo cáo dưới đây:\n\n" . 
                                 "---\n" . 
                                 substr($reportResult['report_content'] ?? '', 0, 2000) . 
                                 (strlen($reportResult['report_content'] ?? '') > 2000 ? "\n\n... (báo cáo đã được lưu)" : ""),
                    'workflow_state' => [
                        'current_step' => 'completed',
                        'workflow' => $workflow,
                    ],
                    'report' => $reportResult, // ✅ Ensure report data is included
                ];
            } catch (\Exception $e) {
                Log::error('Failed to generate report (no required fields)', [
                    'error' => $e->getMessage(),
                    'assistant_id' => $assistant->id,
                    'session_id' => $session->id,
                ]);
                
                // Fallback: Ask user
                return [
                    'response' => "Tôi sẽ tạo báo cáo cho bạn. Bạn có muốn tôi tạo báo cáo mẫu với nội dung mặc định không?",
                    'workflow_state' => [
                        'current_step' => 'waiting_confirmation',
                        'workflow' => $workflow,
                    ],
                ];
            }
        }
        
        // Sử dụng missing fields nếu có, nếu không dùng required fields
        $fieldsToAsk = !empty($missingFields) ? $missingFields : $requiredFields;
        
        // Tạo câu hỏi thông minh
        $questions = [];
        foreach ($fieldsToAsk as $field) {
            $label = $field['field_label'] ?? $field['label'] ?? ($field['field'] ?? $field['field_key'] ?? 'thông tin');
            $question = $this->generateQuestion($field, $assistant);
            $questions[] = "- {$label}: {$question}";
        }
        
        $response = "Tôi sẽ giúp bạn tạo báo cáo. Để tạo báo cáo phù hợp, tôi cần một số thông tin:\n\n";
        $response .= "📋 Thông tin cần thiết:\n";
        $response .= implode("\n", $questions);
        $response .= "\n\nBạn có thể cung cấp thông tin này không?";
        
        return [
            'response' => $response,
            'workflow_state' => [
                'current_step' => 'collecting_info',
                'workflow' => $workflow,
                'required_fields' => $fieldsToAsk,
            ],
        ];
    }

    /**
     * Build chat messages for AI
     * ✅ CẢI TIẾN: Truyền context đầy đủ bao gồm workflow state, collected data
     *
     * @param ChatSession $session
     * @param string $newMessage
     * @param AiAssistant $assistant
     * @param array $additionalContext Additional context (workflow_state, collected_data, etc.)
     * @return array
     */
    protected function buildChatMessages(ChatSession $session, string $newMessage, AiAssistant $assistant, array $additionalContext = []): array
    {
        // ✅ FIX: Build system prompt chuyên nghiệp, lịch sự cho hành chính công
        $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
        
        // ✅ CẢI TIẾN: Thêm context về workflow và collected data nếu có
        $workflowState = $additionalContext['workflow_state'] ?? $session->workflow_state ?? null;
        $collectedData = $additionalContext['collected_data'] ?? $session->collected_data ?? [];
        
        if ($workflowState && !empty($workflowState)) {
            $currentStepIndex = $workflowState['current_step_index'] ?? null;
            $currentStep = null;
            
            if ($currentStepIndex !== null && isset($workflowState['workflow']['steps'][$currentStepIndex])) {
                $currentStep = $workflowState['workflow']['steps'][$currentStepIndex];
            }
            
            if ($currentStep) {
                $systemPrompt .= "\n\n**TRẠNG THÁI HIỆN TẠI:**\n";
                $systemPrompt .= "- Đang thực hiện bước: " . ($currentStep['name'] ?? 'Bước ' . ($currentStepIndex + 1)) . "\n";
                $systemPrompt .= "- Mô tả: " . ($currentStep['description'] ?? '') . "\n";
                
                if (!empty($collectedData)) {
                    $systemPrompt .= "- Đã thu thập thông tin: " . count($collectedData) . " mục\n";
                }
            }
        }
        
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];
        
        // Add previous messages (giới hạn 10 messages gần nhất để tránh quá dài)
        $previousMessages = $session->messages()->orderBy('created_at', 'desc')->limit(10)->get()->reverse();
        foreach ($previousMessages as $msg) {
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
     * Execute predefined steps sequentially
     *
     * @param array $steps
     * @param string $userMessage
     * @param ChatSession $session
     * @param AiAssistant $assistant
     * @param array $intent
     * @param array $workflow
     * @return array
     */
    protected function executePredefinedSteps(
        array $steps,
        string $userMessage,
        ChatSession $session,
        AiAssistant $assistant,
        array $intent,
        array $workflow,
        ?callable $streamCallback = null
    ): array {
        $collectedData = $session->collected_data ?? [];
        $workflowState = $session->workflow_state ?? [];
        $currentStepIndex = $workflowState['current_step_index'] ?? 0;

        // ✅ FIX: Kiểm tra nếu là greeting message và chưa bắt đầu workflow
        // Không trigger step ngay khi user chỉ chào hỏi
        // Chỉ check greeting nếu chưa có collected_data (chưa bắt đầu workflow)
        if ($currentStepIndex === 0 && empty($collectedData) && $this->isGreetingMessage($userMessage)) {
            Log::info('🔵 [executePredefinedSteps] Greeting message detected, not starting workflow yet', [
                'session_id' => $session->id,
                'user_message' => substr($userMessage, 0, 100),
            ]);
            
            // ✅ FIX: Trả lời chuyên nghiệp, lịch sự
            $greetingResponse = $assistant->greeting_message 
                ?? "Xin chào quý anh/chị! Tôi là {$assistant->name}. Tôi rất vui được hỗ trợ quý anh/chị. "
                . "Quý anh/chị có thể cho tôi biết quý anh/chị cần hỗ trợ về vấn đề gì không?";
            
            // Nếu greeting message không có câu hỏi, thêm câu hỏi để khuyến khích user bắt đầu
            if (!str_contains($greetingResponse, '?') && !str_contains($greetingResponse, '？')) {
                $greetingResponse .= " Quý anh/chị có muốn bắt đầu không?";
            }
            
            return [
                'response' => $greetingResponse,
                'workflow_state' => $workflowState, // Giữ nguyên, không bắt đầu workflow
            ];
        }

        // Sắp xếp steps theo order
        usort($steps, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        // Lấy step hiện tại
        if ($currentStepIndex >= count($steps)) {
            // Đã hoàn thành tất cả steps
        // ✅ FIX: Trả lời chuyên nghiệp
        return [
            'response' => 'Tôi đã hoàn thành tất cả các bước theo yêu cầu. Quý anh/chị có cần tôi hỗ trợ thêm điều gì nữa không?',
            'workflow_state' => null,
        ];
        }

        $currentStep = $steps[$currentStepIndex];
        $stepType = $currentStep['type'] ?? 'process';
        $stepAction = $currentStep['action'] ?? '';

        Log::info('Executing predefined step', [
            'session_id' => $session->id,
            'step_index' => $currentStepIndex,
            'step_id' => $currentStep['id'] ?? null,
            'step_type' => $stepType,
            'step_name' => $currentStep['name'] ?? '',
        ]);

        // Thực thi step dựa trên type
        $result = match ($stepType) {
            'collect_info' => $this->executeCollectInfoStep($currentStep, $userMessage, $collectedData, $assistant, $session), // ✅ CẢI TIẾN: Truyền session
            'generate' => $this->executeGenerateStep($currentStep, $userMessage, $collectedData, $assistant, $streamCallback), // ✅ PHASE 2: Pass streamCallback
            'search' => $this->executeSearchStep($currentStep, $userMessage, $collectedData, $assistant),
            'process' => $this->executeProcessStep($currentStep, $userMessage, $collectedData),
            'validate' => $this->executeValidateStep($currentStep, $collectedData),
            'conditional' => $this->executeConditionalStep($currentStep, $collectedData),
            default => [
                'response' => 'Xin lỗi quý anh/chị, tôi gặp khó khăn trong việc xử lý bước này. Quý anh/chị vui lòng thử lại hoặc liên hệ bộ phận hỗ trợ.',
                'completed' => false
            ],
        };
        
        // ✅ CẢI TIẾN: Nếu collect_info step phát hiện câu hỏi thông thường, fallback về handleGenericRequest
        if (isset($result['should_fallback']) && $result['should_fallback'] === true) {
            Log::info('🔵 [executePredefinedSteps] Falling back to handleGenericRequest for general question', [
                'session_id' => $session->id,
                'user_message' => substr($userMessage, 0, 100),
            ]);
            
            $intent = $result['intent'] ?? $this->intentRecognizer->recognize($userMessage, [
                'session' => $session,
                'assistant' => $assistant,
            ]);
            
            return $this->handleGenericRequest($userMessage, $session, $assistant, $intent);
        }

        // Cập nhật collected_data và workflow_state
        if (isset($result['data'])) {
            $collectedData = array_merge($collectedData, $result['data']);
        }

        $nextStepIndex = $result['completed'] ? $currentStepIndex + 1 : $currentStepIndex;
        $workflowState['current_step_index'] = $nextStepIndex;
        $workflowState['completed_steps'] = $workflowState['completed_steps'] ?? [];
        if ($result['completed']) {
            $workflowState['completed_steps'][] = $currentStep['id'] ?? "step_{$currentStepIndex}";
        }

        // Lưu vào session
        $session->collected_data = $collectedData;
        $session->workflow_state = $workflowState;
        $session->save();

        return [
            'response' => $result['response'],
            'workflow_state' => $workflowState,
        ];
    }

    /**
     * Execute collect_info step
     */
    protected function executeCollectInfoStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant, ?ChatSession $session = null): array
    {
        $config = $step['config'] ?? [];
        $questions = $config['questions'] ?? [];
        $fields = $config['fields'] ?? [];

        // ✅ LOG: Debug collect_info step
        Log::info('🔵 [executeCollectInfoStep] Executing collect_info step', [
            'step_id' => $step['id'] ?? null,
            'step_name' => $step['name'] ?? null,
            'has_questions' => !empty($questions),
            'questions_count' => is_array($questions) ? count($questions) : 0,
            'has_fields' => !empty($fields),
            'fields_count' => is_array($fields) ? count($fields) : 0,
            'user_message' => substr($userMessage, 0, 100),
            'collected_data_keys' => array_keys($collectedData),
        ]);

        // Nếu có questions, hỏi từng câu một
        if (!empty($questions) && is_array($questions)) {
            $askedQuestions = $collectedData['_asked_questions'] ?? [];
            $nextQuestionIndex = count($askedQuestions);

            Log::info('🔵 [executeCollectInfoStep] Processing questions', [
                'asked_count' => count($askedQuestions),
                'total_questions' => count($questions),
                'next_index' => $nextQuestionIndex,
            ]);

            if ($nextQuestionIndex < count($questions)) {
                $nextQuestion = $questions[$nextQuestionIndex];
                $askedQuestions[] = $nextQuestion;
                $collectedData['_asked_questions'] = $askedQuestions;

                Log::info('🔵 [executeCollectInfoStep] Asking question', [
                    'question_index' => $nextQuestionIndex,
                    'question' => $nextQuestion,
                ]);

                // ✅ CẢI TIẾN: Sử dụng ResponseEnhancementService để tạo câu hỏi tự nhiên, có ngữ cảnh
                $formattedQuestion = $this->responseEnhancer->generateContextualQuestion(
                    $nextQuestion,
                    $userMessage,
                    $session,
                    $assistant,
                    $collectedData
                );
                
                return [
                    'response' => $formattedQuestion,
                    'completed' => false,
                    'data' => $collectedData,
                ];
            } else {
                // Đã hỏi hết, cần extract answers từ userMessage
                Log::info('🔵 [executeCollectInfoStep] All questions asked, extracting answers');
                return $this->extractAnswersFromMessage($userMessage, $questions, $collectedData, $assistant);
            }
        }

        // Nếu có fields, sử dụng AI để extract
        if (!empty($fields) && is_array($fields)) {
            Log::info('🔵 [executeCollectInfoStep] Extracting fields');
            return $this->extractFieldsFromMessage($userMessage, $fields, $collectedData, $assistant);
        }

        // ✅ CẢI TIẾN: Nếu không có questions và fields, kiểm tra xem có phải câu hỏi thông thường không
        if (empty($questions) && empty($fields)) {
            Log::warning('🔵 [executeCollectInfoStep] No questions or fields configured', [
                'step' => $step,
                'user_message' => substr($userMessage, 0, 100),
            ]);
            
            // ✅ CẢI TIẾN: Kiểm tra xem có phải câu hỏi thông thường không
            // Nếu là câu hỏi thông thường, fallback về handleGenericRequest
            // Note: Cần session để fallback, nhưng nếu không có thì trả về response thông thường
            $intent = $this->intentRecognizer->recognize($userMessage, [
                'assistant' => $assistant,
                'collected_data' => $collectedData,
            ]);
            
            $isGeneralQuestion = $this->isGeneralQuestion($userMessage, $intent);
            
            if ($isGeneralQuestion) {
                Log::info('🔵 [executeCollectInfoStep] Detected general question, should fallback to handleGenericRequest', [
                    'user_message' => substr($userMessage, 0, 100),
                ]);
                
                // Trả về flag để executePredefinedSteps biết cần fallback
                return [
                    'response' => null, // Signal để fallback
                    'completed' => false,
                    'should_fallback' => true,
                    'intent' => $intent,
                ];
            }
        }

        // ✅ FIX: Trả lời chuyên nghiệp, lịch sự
        $professionalResponse = "Để tôi có thể hỗ trợ quý anh/chị tốt nhất, tôi cần một số thông tin. "
            . "Quý anh/chị vui lòng cung cấp các thông tin cần thiết để tôi có thể tiếp tục hỗ trợ quý anh/chị.";

        return [
            'response' => $professionalResponse,
            'completed' => false,
        ];
    }

    /**
     * Execute generate step
     */
    protected function executeGenerateStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant, ?callable $streamCallback = null): array
    {
        $config = $step['config'] ?? [];
        $promptTemplate = $config['prompt_template'] ?? $step['description'] ?? '';

        // ✅ CẢI TIẾN: Nếu không có prompt_template và có collected data, tự động build prompt
        if (empty($promptTemplate) && !empty($collectedData)) {
            Log::warning('Generate step missing prompt_template but has collected data', [
                'step_id' => $step['id'] ?? null,
                'collected_data_keys' => array_keys($collectedData),
            ]);
            
            // Tự động build prompt từ collected data
            $promptTemplate = $this->buildDefaultPromptFromCollectedData($step, $collectedData);
        }

        // Build prompt từ template và collected data
        $prompt = $this->buildPromptFromTemplate($promptTemplate, $collectedData, $userMessage);

        try {
            // ✅ FIX: Build professional system prompt
            $systemPrompt = $this->buildProfessionalSystemPrompt($assistant);
            if (!empty($step['description'])) {
                $systemPrompt .= "\n\n**NHIỆM VỤ CỤ THỂ:**\n{$step['description']}";
            }
            
            // ✅ PHASE 2: Stream từ OpenAI nếu có callback
            if ($streamCallback) {
                $fullContent = '';
                $response = OpenAI::chat()->createStreamed([
                    'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);
                
                foreach ($response as $chunk) {
                    $delta = $chunk->choices[0]->delta->content ?? '';
                    if ($delta) {
                        $fullContent .= $delta;
                        $streamCallback($delta); // ✅ Stream ngay lập tức
                    }
                }
                
                $generatedContent = $fullContent;
            } else {
                // Fallback: Non-streaming mode
                $response = OpenAI::chat()->create([
                    'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

                $generatedContent = $response->choices[0]->message->content;
            }

            return [
                'response' => $generatedContent,
                'completed' => true,
                'data' => [
                    ($step['id'] ?? 'step') . '_result' => $generatedContent,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Generate step error', [
                'error' => $e->getMessage(),
                'step' => $step,
            ]);

            // ✅ FIX: Trả lời lỗi chuyên nghiệp
            return [
                'response' => 'Xin lỗi quý anh/chị, tôi gặp một số khó khăn trong việc tạo nội dung. Quý anh/chị vui lòng thử lại sau hoặc liên hệ bộ phận hỗ trợ nếu vấn đề vẫn tiếp tục.',
                'completed' => false,
            ];
        }
    }

    /**
     * Execute search step
     * ✅ CẢI TIẾN: Cải thiện error handling và response
     */
    protected function executeSearchStep(array $step, string $userMessage, array $collectedData, AiAssistant $assistant): array
    {
        $config = $step['config'] ?? [];
        $searchQuery = $config['search_query'] ?? $userMessage;

        try {
            // ✅ CẢI TIẾN: Kiểm tra search query
            if (empty($searchQuery)) {
                Log::warning('Search step: Empty search query', [
                    'step' => $step['id'] ?? null,
                ]);
                return [
                    'response' => 'Xin lỗi quý anh/chị, tôi cần một từ khóa tìm kiếm. Quý anh/chị vui lòng cung cấp thông tin cần tìm.',
                    'completed' => false,
                ];
            }

            // Sử dụng VectorSearchService
            $results = $this->vectorSearchService->search($searchQuery, $assistant, 5);

            $responseText = 'Đã tìm thấy ' . count($results) . ' kết quả liên quan.';
            if (!empty($results)) {
                $responseText .= "\n\n" . implode("\n", array_slice($results, 0, 3));
            }

            return [
                'response' => $responseText,
                'completed' => true,
                'data' => [
                    ($step['id'] ?? 'step') . '_results' => $results,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Search step error', [
                'error' => $e->getMessage(),
                'step' => $step,
            ]);

            // ✅ FIX: Trả lời lỗi chuyên nghiệp
            return [
                'response' => 'Xin lỗi quý anh/chị, tôi gặp một số khó khăn trong việc tìm kiếm thông tin. Quý anh/chị vui lòng thử lại sau.',
                'completed' => false,
            ];
        }
    }

    /**
     * Execute process step
     */
    protected function executeProcessStep(array $step, string $userMessage, array $collectedData): array
    {
        // Xử lý dữ liệu dựa trên config
        // Có thể mở rộng với các processor khác nhau
        // ✅ FIX: Trả lời chuyên nghiệp
        return [
            'response' => 'Tôi đã xử lý dữ liệu thành công. Quý anh/chị có cần tôi làm gì thêm không?',
            'completed' => true,
        ];
    }

    /**
     * Execute validate step
     */
    protected function executeValidateStep(array $step, array $collectedData): array
    {
        $config = $step['config'] ?? [];
        $validationRules = $config['validation_rules'] ?? [];

        $errors = [];
        foreach ($validationRules as $field => $rule) {
            if (!isset($collectedData[$field]) || empty($collectedData[$field])) {
                $errors[] = $field . ' là bắt buộc.';
            }
        }

        if (!empty($errors)) {
            // ✅ FIX: Trả lời lỗi chuyên nghiệp
            $errorMessage = 'Tôi nhận thấy một số thông tin chưa đầy đủ: ' . implode(', ', $errors);
            $errorMessage .= ' Quý anh/chị vui lòng bổ sung các thông tin này để tôi có thể tiếp tục hỗ trợ.';
            
            return [
                'response' => $errorMessage,
                'completed' => false,
            ];
        }

        // ✅ FIX: Trả lời chuyên nghiệp
        return [
            'response' => 'Cảm ơn quý anh/chị. Tất cả thông tin đã được kiểm tra và hợp lệ. Tôi có thể tiếp tục xử lý.',
            'completed' => true,
        ];
    }

    /**
     * Execute conditional step
     */
    protected function executeConditionalStep(array $step, array $collectedData): array
    {
        $config = $step['config'] ?? [];
        $condition = $config['condition'] ?? '';
        $ifTrue = $config['if_true'] ?? null;
        $ifFalse = $config['if_false'] ?? null;

        $conditionMet = $this->evaluateCondition($condition, $collectedData);

        if ($conditionMet && $ifTrue) {
            return [
                'response' => $ifTrue['message'] ?? 'Điều kiện đúng.',
                'completed' => true,
                'data' => $ifTrue['data'] ?? [],
            ];
        } elseif (!$conditionMet && $ifFalse) {
            return [
                'response' => $ifFalse['message'] ?? 'Điều kiện sai.',
                'completed' => true,
                'data' => $ifFalse['data'] ?? [],
            ];
        }

        return [
            'response' => 'Đã kiểm tra điều kiện.',
            'completed' => true,
        ];
    }

    /**
     * Build prompt from template
     * ✅ CẢI TIẾN: Tự động include collected data nếu template không có placeholders
     */
    protected function buildPromptFromTemplate(string $template, array $data, string $userMessage = ''): string
    {
        // Thay thế placeholders trong template
        $prompt = $template;
        $hasPlaceholders = false;
        
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $placeholder = '{' . $key . '}';
                if (strpos($prompt, $placeholder) !== false) {
                    $prompt = str_replace($placeholder, (string)$value, $prompt);
                    $hasPlaceholders = true;
                }
            }
        }
        
        // ✅ CẢI TIẾN: Nếu template không có placeholders nhưng có collected data, tự động append
        if (!$hasPlaceholders && !empty($data) && !empty($template)) {
            // Filter out internal keys
            $relevantData = array_filter($data, function($key) {
                return !str_starts_with($key, '_');
            }, ARRAY_FILTER_USE_KEY);
            
            if (!empty($relevantData)) {
                $dataSummary = "\n\n**Thông tin đã thu thập:**\n";
                foreach ($relevantData as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $dataSummary .= "- {$key}: {$value}\n";
                    }
                }
                $prompt .= $dataSummary;
            }
        }
        
        // Thêm user message nếu có
        if (!empty($userMessage) && strpos($prompt, '{user_message}') !== false) {
            $prompt = str_replace('{user_message}', $userMessage, $prompt);
        }
        
        return $prompt;
    }

    /**
     * Build default prompt from collected data when prompt_template is missing
     * ✅ MỚI: Tự động tạo prompt từ collected data
     */
    protected function buildDefaultPromptFromCollectedData(array $step, array $collectedData): string
    {
        $stepDescription = $step['description'] ?? $step['name'] ?? 'Thực hiện nhiệm vụ';
        
        // Filter out internal keys
        $relevantData = array_filter($collectedData, function($key) {
            return !str_starts_with($key, '_');
        }, ARRAY_FILTER_USE_KEY);
        
        $prompt = $stepDescription . "\n\n";
        
        if (!empty($relevantData)) {
            $prompt .= "**Thông tin đã thu thập:**\n";
            foreach ($relevantData as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $prompt .= "- {$key}: {$value}\n";
                }
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Hãy thực hiện nhiệm vụ dựa trên thông tin đã thu thập ở trên.";
        
        return $prompt;
    }

    /**
     * Extract answers from message
     */
    protected function extractAnswersFromMessage(string $message, array $questions, array $collectedData, AiAssistant $assistant): array
    {
        // Sử dụng AI để extract answers từ user message
        try {
            $response = OpenAI::chat()->create([
                'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một AI chuyên extract thông tin từ câu trả lời của user. Trả về JSON với các câu trả lời tương ứng với từng câu hỏi.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Các câu hỏi:\n" . implode("\n", $questions) . "\n\nCâu trả lời của user: " . $message . "\n\nTrả về JSON với format: {\"answer_1\": \"...\", \"answer_2\": \"...\"}",
                    ],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $answers = json_decode($content, true);

            if ($answers && is_array($answers)) {
                $collectedData = array_merge($collectedData, $answers);
            }
        } catch (\Exception $e) {
            Log::error('Extract answers error', [
                'error' => $e->getMessage(),
            ]);
        }

        // ✅ FIX: Trả lời chuyên nghiệp (duplicate của extractAnswersFromMessage)
        return [
            'response' => 'Cảm ơn quý anh/chị đã cung cấp thông tin. Tôi đã thu thập đủ thông tin cần thiết.',
            'completed' => true,
            'data' => $collectedData,
        ];
    }

    /**
     * Extract fields from message
     */
    protected function extractFieldsFromMessage(string $message, array $fields, array $collectedData, AiAssistant $assistant): array
    {
        // Tương tự extractAnswersFromMessage nhưng với fields
        try {
            $response = OpenAI::chat()->create([
                'model' => $assistant->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một AI chuyên extract thông tin từ câu trả lời của user. Trả về JSON với các field tương ứng.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Các fields cần extract:\n" . implode("\n", $fields) . "\n\nCâu trả lời của user: " . $message . "\n\nTrả về JSON với các field này.",
                    ],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $extracted = json_decode($content, true);

            if ($extracted && is_array($extracted)) {
                $collectedData = array_merge($collectedData, $extracted);
            }
        } catch (\Exception $e) {
            Log::error('Extract fields error', [
                'error' => $e->getMessage(),
            ]);
        }

        // ✅ FIX: Trả lời chuyên nghiệp (duplicate của extractAnswersFromMessage)
        return [
            'response' => 'Cảm ơn quý anh/chị đã cung cấp thông tin. Tôi đã thu thập đủ thông tin cần thiết.',
            'completed' => true,
            'data' => $collectedData,
        ];
    }

    /**
     * Evaluate condition expression
     */
    protected function evaluateCondition(string $condition, array $data): bool
    {
        // Đơn giản hóa: kiểm tra xem field có tồn tại và có giá trị không
        if (preg_match('/has\((.+)\)/', $condition, $matches)) {
            $field = $matches[1];
            return isset($data[$field]) && !empty($data[$field]);
        }
        return true;
    }

    /**
     * Check if message is a greeting using AI
     *
     * @param string $message
     * @return bool
     */
    protected function isGreetingMessage(string $message): bool
    {
        // ✅ FIX: Dùng ChatGPT để detect greeting một cách tổng quát
        try {
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Bạn là một AI chuyên phân tích xem một tin nhắn có phải là lời chào hỏi hay không.\n\n"
                            . "**NHIỆM VỤ:**\n"
                            . "Phân tích tin nhắn của người dùng và xác định xem đó có phải là lời chào hỏi (greeting) hay không.\n\n"
                            . "**LỜI CHÀO HỎI BAO GỒM:**\n"
                            . "- Các câu chào hỏi: xin chào, chào, hello, hi, chào bạn, chào anh/chị, v.v.\n"
                            . "- Các câu hỏi thăm sức khỏe: khỏe không, thế nào, v.v.\n"
                            . "- Các câu mở đầu cuộc trò chuyện: bắt đầu, bắt đầu thôi, v.v.\n"
                            . "- Các câu chỉ mang tính xã giao, không có nội dung cụ thể\n\n"
                            . "**KHÔNG PHẢI LỜI CHÀO HỎI:**\n"
                            . "- Câu hỏi về chức năng: \"bạn làm được gì?\", \"mày làm được gì?\"\n"
                            . "- Yêu cầu cụ thể: \"tôi muốn tìm hiểu về...\", \"giúp tôi...\"\n"
                            . "- Câu hỏi có nội dung cụ thể: \"xã A có bao nhiêu dân?\"\n\n"
                            . "**YÊU CẦU:**\n"
                            . "Trả về JSON với format: {\"is_greeting\": true/false, \"confidence\": 0.0-1.0, \"reason\": \"lý do\"}\n"
                            . "Nếu tin nhắn chỉ là lời chào hỏi đơn thuần, không có yêu cầu cụ thể → is_greeting = true\n"
                            . "Nếu tin nhắn có nội dung cụ thể, yêu cầu, câu hỏi về chức năng → is_greeting = false",
                    ],
                    [
                        'role' => 'user',
                        'content' => "Tin nhắn cần phân tích: \"{$message}\"\n\nHãy phân tích và trả về JSON.",
                    ],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            if ($result && isset($result['is_greeting'])) {
                $isGreeting = (bool) $result['is_greeting'];
                $confidence = $result['confidence'] ?? 0.5;
                
                Log::debug('Greeting detection with AI', [
                    'message' => substr($message, 0, 100),
                    'is_greeting' => $isGreeting,
                    'confidence' => $confidence,
                    'reason' => $result['reason'] ?? null,
                ]);
                
                // Chỉ tin tưởng nếu confidence >= 0.7
                return $isGreeting && $confidence >= 0.7;
            }
        } catch (\Exception $e) {
            Log::warning('Greeting detection with AI failed, using fallback', [
                'error' => $e->getMessage(),
                'message' => substr($message, 0, 100),
            ]);
        }
        
        // ✅ Fallback: Nếu AI fail, dùng pattern matching đơn giản cho các trường hợp rõ ràng
        $normalizedMessage = mb_strtolower(trim($message));
        
        // Chỉ check các greeting rất rõ ràng và ngắn
        $clearGreetings = ['xin chào', 'chào', 'hello', 'hi', 'hey'];
        foreach ($clearGreetings as $greeting) {
            // Chỉ match nếu message rất ngắn và chỉ là greeting
            if ($normalizedMessage === $greeting || 
                $normalizedMessage === $greeting . '!' ||
                $normalizedMessage === $greeting . '.' ||
                $normalizedMessage === $greeting . ' ạ') {
                return true;
            }
        }
        
        // Nếu message quá ngắn (<= 10 ký tự) và chỉ chứa greeting words → có thể là greeting
        if (mb_strlen($normalizedMessage) <= 10) {
            foreach ($clearGreetings as $greeting) {
                if (str_contains($normalizedMessage, $greeting)) {
                    $withoutGreeting = str_replace($greeting, '', $normalizedMessage);
                    $withoutGreeting = trim($withoutGreeting);
                    // Chỉ còn dấu câu hoặc từ xưng hô ngắn
                    if (mb_strlen($withoutGreeting) <= 3 || in_array($withoutGreeting, ['bạn', 'anh', 'chị', 'ạ', '!', '.'])) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Check if message is a general question (not requiring workflow)
     * ✅ CẢI TIẾN: Sử dụng AI để nhận diện chính xác hơn
     *
     * @param string $message
     * @param array $intent
     * @return bool
     */
    protected function isGeneralQuestion(string $message, array $intent): bool
    {
        // ✅ MỚI: Sử dụng AI để detect general question
        try {
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Bạn là một AI chuyên phân tích xem một tin nhắn có phải là câu hỏi thông thường (general question) hay không.\n\n"
                            . "**CÂU HỎI THÔNG THƯỜNG BAO GỒM:**\n"
                            . "- Câu hỏi về thông tin thực tế: \"Hà Nội có bao nhiêu tỉnh?\", \"Việt Nam có bao nhiêu tỉnh thành?\"\n"
                            . "- Câu hỏi về kiến thức: \"GDP là gì?\", \"Công văn là gì?\"\n"
                            . "- Câu hỏi về chức năng: \"Bạn làm được gì?\", \"Tính năng của bạn là gì?\"\n"
                            . "- Câu hỏi về cách sử dụng: \"Làm thế nào để...?\", \"Cách sử dụng...?\"\n"
                            . "- Câu hỏi về thông tin địa lý, hành chính: \"Xã A có bao nhiêu dân?\", \"Tỉnh B có bao nhiêu huyện?\"\n"
                            . "- Câu hỏi về số lượng: \"có bao nhiêu\", \"bao nhiêu\"\n"
                            . "- Câu hỏi về định nghĩa: \"là gì\", \"là ai\", \"là như thế nào\"\n\n"
                            . "**KHÔNG PHẢI CÂU HỎI THÔNG THƯỜNG:**\n"
                            . "- Yêu cầu tạo văn bản: \"Tôi muốn soạn thảo công văn\", \"Giúp tôi tạo quyết định\"\n"
                            . "- Yêu cầu thu thập thông tin có workflow: \"Tôi muốn tạo báo cáo\", \"Làm báo cáo thường niên\"\n"
                            . "- Yêu cầu cụ thể cần nhiều bước: \"Tôi muốn viết sách\", \"Tạo kế hoạch dự án\"\n\n"
                            . "**YÊU CẦU:**\n"
                            . "Trả về JSON với format: {\"is_general_question\": true/false, \"confidence\": 0.0-1.0, \"reason\": \"lý do\"}\n"
                            . "Nếu là câu hỏi thông thường chỉ cần trả lời trực tiếp → is_general_question = true\n"
                            . "Nếu là yêu cầu cần workflow/steps → is_general_question = false",
                    ],
                    [
                        'role' => 'user',
                        'content' => "Tin nhắn cần phân tích: \"{$message}\"\n\nHãy phân tích và trả về JSON.",
                    ],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            if ($result && isset($result['is_general_question'])) {
                $isGeneralQuestion = (bool) $result['is_general_question'];
                $confidence = $result['confidence'] ?? 0.5;
                
                Log::debug('General question detection with AI', [
                    'message' => substr($message, 0, 100),
                    'is_general_question' => $isGeneralQuestion,
                    'confidence' => $confidence,
                    'reason' => $result['reason'] ?? null,
                ]);
                
                // Chỉ tin tưởng nếu confidence >= 0.7
                if ($isGeneralQuestion && $confidence >= 0.7) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('General question detection with AI failed, using fallback', [
                'error' => $e->getMessage(),
                'message' => substr($message, 0, 100),
            ]);
        }
        
        // ✅ Fallback: Pattern matching cho các trường hợp rõ ràng
        $normalizedMessage = mb_strtolower(trim($message));
        
        // Các câu hỏi thông thường không cần workflow
        $generalQuestionPatterns = [
            // Câu hỏi về chức năng
            'mày làm được gì',
            'bạn làm được gì',
            'bạn có thể làm gì',
            'chức năng',
            'tính năng',
            'giúp gì',
            'làm gì',
            'là gì',
            'như thế nào',
            'cách sử dụng',
            'hướng dẫn',
            // ✅ CẢI TIẾN: Thêm pattern cho câu hỏi về số lượng, thông tin thực tế
            'có bao nhiêu',
            'bao nhiêu',
            'là ai',
            'ở đâu',
            'khi nào',
            'tại sao',
            // Câu hỏi về địa lý, hành chính
            'có bao nhiêu tỉnh',
            'có bao nhiêu huyện',
            'có bao nhiêu xã',
            'có bao nhiêu dân',
            'có bao nhiêu quận',
            'có bao nhiêu phường',
        ];
        
        // Check patterns
        foreach ($generalQuestionPatterns as $pattern) {
            if (str_contains($normalizedMessage, $pattern)) {
                return true;
            }
        }
        
        // Check intent: nếu là ask_question và không có entity cụ thể → general question
        if (($intent['type'] ?? null) === 'ask_question' && empty($intent['entity'] ?? [])) {
            return true;
        }
        
        return false;
    }

    /**
     * Get context specific to assistant type
     * ✅ CẢI TIẾN: Cung cấp context chi tiết theo từng loại assistant
     *
     * @param AiAssistant $assistant
     * @return string
     */
    protected function getAssistantTypeContext(AiAssistant $assistant): string
    {
        $type = $assistant->getAssistantTypeValue() ?? '';
        
        return match($type) {
            'qa_based_document' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Trả lời câu hỏi dựa trên tài liệu đã được upload\n"
                . "- Nếu không có tài liệu hoặc không tìm thấy thông tin trong tài liệu, tìm kiếm trên mạng và trả lời\n"
                . "- Luôn trích dẫn nguồn thông tin khi có thể\n"
                . "- Ưu tiên thông tin từ tài liệu đã upload, sau đó mới tìm kiếm trên mạng\n\n",
            
            'document_drafting' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Soạn thảo các loại văn bản hành chính: Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết\n"
                . "- Sử dụng đúng format, ngôn ngữ hành chính, tuân thủ quy định pháp luật\n"
                . "- Có thể cần thu thập thông tin từ người dùng để soạn thảo chính xác\n"
                . "- Luôn kiểm tra tính hợp pháp và đúng quy trình\n\n",
            
            'document_management' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Quản lý văn bản đến, văn bản đi\n"
                . "- Phân loại văn bản tự động\n"
                . "- Tính toán và nhắc nhở thời hạn xử lý\n"
                . "- Lưu trữ và tìm kiếm văn bản\n"
                . "- Trả lời câu hỏi về văn bản một cách trực tiếp\n\n",
            
            'hr_management' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Quản lý nhân sự: tính lương, chấm công, nghỉ phép\n"
                . "- Tạo báo cáo nhân sự\n"
                . "- Trả lời câu hỏi về quy định nhân sự, chế độ chính sách\n"
                . "- Hỗ trợ tính toán lương, thưởng, phụ cấp\n\n",
            
            'finance_management' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Quản lý tài chính: lập dự toán, theo dõi thu chi\n"
                . "- Cảnh báo vượt ngân sách\n"
                . "- Tạo báo cáo tài chính\n"
                . "- Trả lời câu hỏi về quy định tài chính, ngân sách\n\n",
            
            'project_management' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Quản lý dự án đầu tư công\n"
                . "- Theo dõi tiến độ, ngân sách\n"
                . "- Phân tích rủi ro\n"
                . "- Tạo báo cáo dự án\n\n",
            
            'complaint_management' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Quản lý khiếu nại và tố cáo\n"
                . "- Tiếp nhận, phân loại\n"
                . "- Theo dõi tiến độ giải quyết\n"
                . "- Trả lời câu hỏi về quy trình giải quyết khiếu nại\n\n",
            
            'event_management' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Tổ chức sự kiện và hội nghị\n"
                . "- Lập kế hoạch sự kiện\n"
                . "- Quản lý khách mời\n"
                . "- Gửi thư mời tự động\n\n",
            
            'asset_management' => "**CHỨC NĂNG ĐẶC BIỆT:**\n"
                . "- Quản lý tài sản công\n"
                . "- Theo dõi bảo trì\n"
                . "- Kiểm kê định kỳ\n"
                . "- Tạo báo cáo tài sản\n\n",
            
            default => "**CHỨC NĂNG:**\n"
                . "- Hỗ trợ các tác vụ hành chính công\n"
                . "- Trả lời câu hỏi và thực hiện yêu cầu của người dùng\n\n",
        };
    }

    /**
     * Build professional system prompt for administrative AI
     * ✅ CẢI TIẾN: Sử dụng getAssistantTypeContext() để cung cấp context chi tiết
     *
     * @param AiAssistant $assistant
     * @return string
     */
    protected function buildProfessionalSystemPrompt(AiAssistant $assistant): string
    {
        // ✅ MỚI: Sử dụng SystemPromptBuilder để build prompt theo priority
        $builder = app(\App\Services\SystemPromptBuilder::class);
        return $builder->build($assistant);
    }

    /**
     * Format question professionally
     *
     * @param string $question
     * @param AiAssistant $assistant
     * @return string
     */
    protected function formatQuestionProfessionally(string $question, AiAssistant $assistant): string
    {
        // Nếu câu hỏi đã có format chuyên nghiệp, giữ nguyên
        if (str_contains($question, 'quý anh/chị') || str_contains($question, 'vui lòng')) {
            return $question;
        }
        
        // Format lại câu hỏi cho chuyên nghiệp
        $formatted = trim($question);
        
        // Thêm prefix lịch sự nếu chưa có
        if (!str_starts_with(mb_strtolower($formatted), 'quý anh/chị') && 
            !str_starts_with(mb_strtolower($formatted), 'bạn') &&
            !str_starts_with(mb_strtolower($formatted), 'anh/chị')) {
            $formatted = "Quý anh/chị vui lòng cho tôi biết: " . $formatted;
        }
        
        // Đảm bảo có dấu chấm hỏi
        if (!str_ends_with($formatted, '?') && !str_ends_with($formatted, '？')) {
            $formatted .= '?';
        }
        
        return $formatted;
    }
}

