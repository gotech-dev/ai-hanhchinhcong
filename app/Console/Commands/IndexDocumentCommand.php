<?php

namespace App\Console\Commands;

use App\Models\AssistantDocument;
use App\Services\DocumentProcessor;
use App\Services\VectorSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IndexDocumentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'document:index {document_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index a document with vector embeddings';

    /**
     * Execute the console command.
     */
    public function handle(DocumentProcessor $documentProcessor, VectorSearchService $vectorSearchService): int
    {
        $documentId = $this->argument('document_id');
        
        $document = AssistantDocument::find($documentId);
        
        if (!$document) {
            $this->error("Document with ID {$documentId} not found.");
            return 1;
        }
        
        $this->info("Indexing document: {$document->file_name}");
        
        try {
            // Update status to processing
            $document->update([
                'status' => 'processing',
            ]);
            
            // Get file path
            $filePath = $document->file_path;
            
            // Convert URL to storage path if needed
            if (str_starts_with($filePath, 'http')) {
                $path = parse_url($filePath, PHP_URL_PATH);
                $storagePath = str_replace('/storage/', '', $path);
            } else {
                $storagePath = $filePath;
            }
            
            // Get full file path
            $fullPath = Storage::disk('public')->path($storagePath);
            
            if (!file_exists($fullPath)) {
                $this->error("File not found: {$fullPath}");
                return 1;
            }
            
            // Extract text from document
            $this->info("Extracting text from document...");
            $text = $documentProcessor->extractText($fullPath);
            
            if (empty($text)) {
                $this->error("Failed to extract text from document.");
                return 1;
            }
            
            // Split into chunks
            $this->info("Splitting into chunks...");
            $chunks = $documentProcessor->splitIntoChunks($text);
            
            $this->info("Found " . count($chunks) . " chunks");
            
            // Delete existing chunks
            $document->documentChunks()->delete();
            
            // Create embeddings for all chunks
            $this->info("Creating embeddings...");
            $embeddings = $vectorSearchService->createEmbeddings($chunks);
            
            // Save chunks with embeddings
            $this->info("Saving chunks...");
            foreach ($chunks as $index => $chunk) {
                $vectorSearchService->saveChunk(
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
            
            $this->info("Document indexed successfully!");
            $this->info("Chunks count: " . count($chunks));
            
            Log::info('Document indexed via command', [
                'document_id' => $document->id,
                'chunks_count' => count($chunks),
            ]);
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error indexing document: " . $e->getMessage());
            
            $document->update([
                'status' => 'error',
            ]);
            
            Log::error('Document indexing error via command', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
    }
}
