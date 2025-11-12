<?php

namespace App\Jobs;

use App\Models\AiAssistant;
use App\Models\AssistantDocument;
use App\Services\DocumentProcessor;
use App\Services\VectorSearchService;
use App\Services\WebCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlReferenceUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $assistantId;

    public function __construct(int $assistantId)
    {
        $this->assistantId = $assistantId;
    }

    public function handle(
        WebCrawlerService $crawlerService,
        DocumentProcessor $documentProcessor,
        VectorSearchService $vectorSearchService
    ): void {
        $assistant = AiAssistant::find($this->assistantId);
        
        if (!$assistant) {
            Log::warning('Assistant not found for crawling', [
                'assistant_id' => $this->assistantId,
            ]);
            return;
        }
        
        $referenceUrls = $assistant->referenceUrls()
            ->where(function($q) {
                $q->where('status', 'pending')
                  ->orWhere('status', 'failed');
            })
            ->get();
        
        foreach ($referenceUrls as $referenceUrl) {
            try {
                // Update status to crawling
                $referenceUrl->update([
                    'status' => 'crawling',
                    'error_message' => null,
                ]);
                
                // Crawl URL
                $result = $crawlerService->crawlUrl($referenceUrl->url);
                
                if (!$result['success']) {
                    throw new \Exception($result['error'] ?? 'Unknown error');
                }
                
                // Save crawled content
                $referenceUrl->update([
                    'title' => $result['title'] ?? null,
                    'description' => $result['description'] ?? null,
                    'crawled_content' => $result['content'],
                    'content_length' => $result['content_length'],
                    'last_crawled_at' => now(),
                ]);
                
                // ✅ Index vào vector database
                // Tạo AssistantDocument ảo cho reference URL để có thể index
                $document = AssistantDocument::create([
                    'ai_assistant_id' => $assistant->id,
                    'file_name' => $result['title'] ?? parse_url($referenceUrl->url, PHP_URL_HOST) ?? 'Reference URL',
                    'file_path' => $referenceUrl->url,
                    'file_type' => 'url',
                    'file_size' => $result['content_length'],
                    'page_count' => 1,
                    'is_indexed' => false,
                    'status' => 'processing',
                ]);
                
                // Chia content thành chunks
                $chunks = $documentProcessor->splitIntoChunks($result['content'], 800, 100);
                
                if (empty($chunks)) {
                    throw new \Exception('No chunks created from crawled content');
                }
                
                // Create embeddings for all chunks
                $embeddings = $vectorSearchService->createEmbeddings($chunks);
                
                // Save chunks with embeddings
                foreach ($chunks as $index => $chunk) {
                    $vectorSearchService->saveChunk(
                        $document->id,
                        $index,
                        $chunk,
                        $embeddings[$index] ?? [],
                        [
                            'source_type' => 'reference_url',
                            'source_url' => $referenceUrl->url,
                            'title' => $result['title'] ?? null,
                            'chunk_index' => $index,
                        ]
                    );
                }
                
                // Mark document as indexed
                $document->update([
                    'is_indexed' => true,
                    'status' => 'indexed',
                    'chunks_count' => count($chunks),
                    'indexed_at' => now(),
                ]);
                
                // Mark reference URL as indexed
                $referenceUrl->update([
                    'status' => 'indexed',
                ]);
                
                Log::info('Successfully crawled and indexed reference URL', [
                    'assistant_id' => $assistant->id,
                    'url' => $referenceUrl->url,
                    'chunks_count' => count($chunks),
                    'document_id' => $document->id,
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to crawl reference URL', [
                    'assistant_id' => $assistant->id,
                    'url' => $referenceUrl->url,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $referenceUrl->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
            
            // Rate limiting: delay 2-5 seconds between URLs
            sleep(rand(2, 5));
        }
    }
}
