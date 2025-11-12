<?php

namespace App\Jobs;

use App\Models\AssistantDocument;
use App\Services\DocumentProcessor;
use App\Services\VectorSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AssistantDocument $document
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentProcessor $documentProcessor, VectorSearchService $vectorSearchService): void
    {
        try {
            // Update status to processing
            $this->document->update([
                'status' => 'processing',
            ]);
            
            // Extract text from document
            $text = $documentProcessor->extractText($this->document->file_path);
            
            // Split into chunks
            $chunks = $documentProcessor->splitIntoChunks($text);
            
            // Index document with chunks
            $vectorSearchService->indexDocument($this->document->id, $chunks);
            
            // Update document status
            $this->document->update([
                'status' => 'indexed',
                'chunks_count' => count($chunks),
                'indexed_at' => now(),
                'is_indexed' => true,
            ]);
            
            Log::info('Document indexed successfully', [
                'document_id' => $this->document->id,
                'chunks_count' => count($chunks),
            ]);
        } catch (\Exception $e) {
            Log::error('Document indexing failed', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Update status to error
            $this->document->update([
                'status' => 'error',
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('IndexDocumentJob failed', [
            'document_id' => $this->document->id,
            'error' => $exception->getMessage(),
        ]);
        
        $this->document->update([
            'status' => 'error',
        ]);
    }
}








