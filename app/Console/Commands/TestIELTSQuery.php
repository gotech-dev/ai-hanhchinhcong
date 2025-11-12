<?php

namespace App\Console\Commands;

use App\Models\AiAssistant;
use App\Services\VectorSearchService;
use Illuminate\Console\Command;

class TestIELTSQuery extends Command
{
    protected $signature = 'test:ielts-query';
    protected $description = 'Test query về IELTS để debug';

    public function __construct(
        protected VectorSearchService $vectorSearchService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🔍 Đang tìm assistant PolyPi...');
        
        $assistant = AiAssistant::where('name', 'like', '%PolyPi%')
            ->orWhere('name', 'like', '%Trợ lý học tiếng Anh%')
            ->first();
        
        if (!$assistant) {
            $this->error('❌ Không tìm thấy assistant PolyPi');
            return 1;
        }
        
        $this->info("✅ Tìm thấy assistant: {$assistant->name} (ID: {$assistant->id})");
        $this->newLine();
        
        // Test với câu hỏi về IELTS
        $testQueries = [
            "IELTS có những chức năng gì",
            "chức năng của IELTS",
            "IELTS",
            "PolyPi có chức năng gì",
        ];
        
        foreach ($testQueries as $query) {
            $this->info("🧪 Testing query: \"{$query}\"");
            $this->newLine();
            
            // Test với các threshold
            $thresholds = [0.7, 0.5, 0.3];
            
            foreach ($thresholds as $threshold) {
                $this->line("   Threshold: {$threshold}");
                
                $searchResults = $this->vectorSearchService->searchSimilar(
                    $query,
                    $assistant->id,
                    5,
                    $threshold,
                    []
                );
                
                // Filter out reference URL chunks
                $searchResults = array_filter($searchResults, function($result) {
                    $metadata = $result['metadata'] ?? [];
                    return ($metadata['source_type'] ?? null) !== 'reference_url';
                });
                
                if (!empty($searchResults)) {
                    $this->info("   ✅ Tìm thấy " . count($searchResults) . " kết quả:");
                    foreach ($searchResults as $i => $result) {
                        $this->line("      " . ($i + 1) . ". Similarity: " . number_format($result['similarity'], 3));
                        $this->line("         Content: " . substr($result['content'], 0, 150) . "...");
                        $this->newLine();
                    }
                    break; // Dừng khi tìm thấy
                } else {
                    $this->warn("   ⚠️  Không tìm thấy kết quả với threshold {$threshold}");
                }
            }
            
            $this->newLine();
            $this->line("---");
            $this->newLine();
        }
        
        // Hiển thị toàn bộ nội dung chunks để xem có thông tin về IELTS không
        $this->info('📄 Đang hiển thị toàn bộ nội dung chunks...');
        $documents = $assistant->documents()->get();
        
        foreach ($documents as $doc) {
            $chunks = $doc->documentChunks()->get();
            $this->newLine();
            $this->info("Document: {$doc->file_name} ({$chunks->count()} chunks)");
            
            foreach ($chunks as $i => $chunk) {
                $this->newLine();
                $this->line("=== Chunk {$i} (Index: {$chunk->chunk_index}) ===");
                $this->line($chunk->content);
                $this->line("=== End Chunk {$i} ===");
            }
        }
        
        return 0;
    }
}


