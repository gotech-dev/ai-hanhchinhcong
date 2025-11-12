<?php

namespace App\Services;

use App\Models\AiAssistant;
use App\Models\AssistantDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

class AutoConfigurationService
{
    public function __construct(
        protected TemplateAnalyzer $templateAnalyzer,
        protected DocumentProcessor $documentProcessor,
        protected VectorSearchService $vectorSearchService
    ) {}

    /**
     * Analyze and configure assistant automatically
     *
     * @param AiAssistant $assistant
     * @param UploadedFile|null $templateFile
     * @param array|null $documentFiles
     * @return array
     */
    public function analyzeAndConfigure(
        AiAssistant $assistant,
        ?UploadedFile $templateFile = null,
        ?array $documentFiles = null
    ): array {
        try {
            $config = $assistant->config ?? [];
            
            // Note: report_generator has been merged into document_drafting
            // Template files are now handled via document_templates table in AdminController
            if ($assistant->assistant_type === 'qa_based_document' && $documentFiles) {
                // Index documents and create metadata
                $documentConfig = $this->indexDocuments($documentFiles, $assistant);
                $config = array_merge($config, $documentConfig);
            }
            
            // Generate greeting message using AI
            $greetingMessage = $this->generateGreetingMessage($assistant);
            
            // Update assistant config and greeting message
            $assistant->update([
                'config' => $config,
                'greeting_message' => $greetingMessage,
            ]);
            
            return [
                'status' => 'success',
                'config' => $config,
                'greeting_message' => $greetingMessage,
                'message' => 'Assistant configured successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Auto-configuration error', [
                'error' => $e->getMessage(),
                'assistant_id' => $assistant->id,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'message' => 'Failed to configure assistant',
            ];
        }
    }

    /**
     * Analyze template file and generate workflow config
     *
     * @param UploadedFile $templateFile
     * @param AiAssistant $assistant
     * @return array
     */
    protected function analyzeTemplate(UploadedFile $templateFile, AiAssistant $assistant): array
    {
        // Store template file
        $path = $templateFile->store('templates', 'public');
        $url = Storage::disk('public')->url($path);
        
        // Update assistant with template path
        $assistant->update([
            'template_file_path' => $url,
        ]);
        
        // Analyze template using TemplateAnalyzer
        $analysis = $this->templateAnalyzer->analyzeTemplate($templateFile);
        
        return [
            'template_file_path' => $url,
            'template_analysis' => $analysis,
            'template_fields' => $analysis['fields'] ?? [], // Save fields for WorkflowPlanner
            'workflow_config' => $analysis['workflow_config'] ?? null,
            'configured_at' => now()->toISOString(),
        ];
    }

    /**
     * Index documents and create metadata
     *
     * @param array $documentFiles
     * @param AiAssistant $assistant
     * @return array
     */
    protected function indexDocuments(array $documentFiles, AiAssistant $assistant): array
    {
        $indexedDocuments = [];
        $totalChunks = 0;
        
        foreach ($documentFiles as $documentFile) {
            try {
                // Save document
                $document = AssistantDocument::create([
                    'ai_assistant_id' => $assistant->id,
                    'file_name' => $documentFile->getClientOriginalName(),
                    'file_path' => $documentFile->store('documents', 'public'),
                    'file_type' => $documentFile->getClientOriginalExtension(),
                    'file_size' => $documentFile->getSize(),
                    'status' => 'pending',
                ]);
                
                // Extract text
                $text = $this->documentProcessor->extractText($documentFile);
                
                // Split into chunks
                $chunks = $this->documentProcessor->splitIntoChunks($text);
                
                // Index document immediately (sync) instead of queue
                // This ensures documents are indexed right away when creating assistant
                try {
                    // Create embeddings for all chunks
                    $embeddings = $this->vectorSearchService->createEmbeddings($chunks);
                    
                    // Save chunks with embeddings
                    foreach ($chunks as $index => $chunk) {
                        $this->vectorSearchService->saveChunk(
                            $document->id,
                            $index,
                            $chunk,
                            $embeddings[$index] ?? [],
                            [
                                'page' => (int) floor($index / 2) + 1,
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
                    
                    Log::info('Document indexed successfully via AutoConfigurationService', [
                        'document_id' => $document->id,
                        'chunks_count' => count($chunks),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Index document error in AutoConfigurationService', [
                        'error' => $e->getMessage(),
                        'document_id' => $document->id,
                    ]);
                    
                    // Mark as error but don't throw to continue with other documents
                    $document->update([
                        'status' => 'error',
                    ]);
                }
                
                $indexedDocuments[] = [
                    'id' => $document->id,
                    'file_name' => $document->file_name,
                    'chunks_count' => count($chunks),
                    'status' => 'indexed',
                ];
                
                $totalChunks += count($chunks);
            } catch (\Exception $e) {
                Log::error('Document indexing error', [
                    'error' => $e->getMessage(),
                    'file_name' => $documentFile->getClientOriginalName(),
                    'assistant_id' => $assistant->id,
                ]);
                
                $indexedDocuments[] = [
                    'file_name' => $documentFile->getClientOriginalName(),
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'documents' => $indexedDocuments,
            'total_documents' => count($indexedDocuments),
            'total_chunks' => $totalChunks,
            'configured_at' => now()->toISOString(),
        ];
    }

    /**
     * Generate greeting message using AI based on assistant name and description
     *
     * @param AiAssistant $assistant
     * @return string
     */
    protected function generateGreetingMessage(AiAssistant $assistant): string
    {
        try {
            $prompt = "Bạn hãy tạo một câu chào chuyên nghiệp, thân thiện và ấn tượng cho một trợ lý AI có tên là \"{$assistant->name}\"";
            
            if ($assistant->description) {
                $prompt .= " với mô tả: \"{$assistant->description}\"";
            }
            
            $prompt .= ". Câu chào phải ngắn gọn (không quá 2 câu), tự nhiên, và thể hiện sự sẵn sàng giúp đỡ. Chỉ trả về câu chào, không thêm giải thích hay ký tự đặc biệt.";
            
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một chuyên gia tạo nội dung chào hỏi chuyên nghiệp. Hãy tạo câu chào ngắn gọn, thân thiện và tự nhiên.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 150,
            ]);
            
            $greetingMessage = trim($response->choices[0]->message->content);
            
            // Remove quotes if wrapped in quotes
            $greetingMessage = preg_replace('/^["\']|["\']$/', '', $greetingMessage);
            
            Log::info('Greeting message generated', [
                'assistant_id' => $assistant->id,
                'greeting_message' => $greetingMessage,
            ]);
            
            return $greetingMessage;
        } catch (\Exception $e) {
            Log::error('Failed to generate greeting message', [
                'assistant_id' => $assistant->id,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to default greeting
            return "Xin chào bạn. Mình là {$assistant->name}. Mình rất vui được giúp đỡ bạn.";
        }
    }
}

