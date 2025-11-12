<?php

namespace App\Console\Commands;

use App\Models\AiAssistant;
use App\Models\AssistantDocument;
use App\Models\DocumentChunk;
use App\Services\VectorSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckPolyPiAssistant extends Command
{
    protected $signature = 'check:polypi {--test-search : Test search with sample query}';
    protected $description = 'Kiểm tra assistant PolyPi và documents đã upload';

    public function __construct(
        protected VectorSearchService $vectorSearchService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🔍 Đang tìm assistant "Trợ lý học tiếng Anh PolyPi"...');
        
        // Tìm assistant
        $assistant = AiAssistant::where('name', 'like', '%PolyPi%')
            ->orWhere('name', 'like', '%Trợ lý học tiếng Anh%')
            ->orWhere('name', 'like', '%tiếng Anh%')
            ->first();
        
        if (!$assistant) {
            $this->error('❌ Không tìm thấy assistant PolyPi');
            $this->info('Danh sách tất cả assistants:');
            $allAssistants = AiAssistant::select('id', 'name', 'assistant_type', 'is_active')
                ->get();
            foreach ($allAssistants as $a) {
                $this->line("  - ID: {$a->id}, Name: {$a->name}, Type: {$a->assistant_type}, Active: " . ($a->is_active ? 'Yes' : 'No'));
            }
            return 1;
        }
        
        $this->info("✅ Tìm thấy assistant: {$assistant->name} (ID: {$assistant->id})");
        $this->info("   Type: {$assistant->assistant_type}");
        $this->info("   Active: " . ($assistant->is_active ? 'Yes' : 'No'));
        $this->newLine();
        
        // Kiểm tra documents
        $this->info('📄 Đang kiểm tra documents...');
        $documents = $assistant->documents()->get();
        
        if ($documents->isEmpty()) {
            $this->warn('⚠️  Không có documents nào được upload');
            return 0;
        }
        
        $this->info("✅ Tìm thấy {$documents->count()} documents:");
        $this->newLine();
        
        $indexedCount = 0;
        $totalChunks = 0;
        
        foreach ($documents as $doc) {
            $this->line("📄 Document: {$doc->file_name}");
            $this->line("   - ID: {$doc->id}");
            $this->line("   - Type: {$doc->file_type}");
            $this->line("   - Size: " . ($doc->file_size ? number_format($doc->file_size / 1024, 2) . ' KB' : 'N/A'));
            $this->line("   - Status: {$doc->status}");
            $this->line("   - Is Indexed: " . ($doc->is_indexed ? 'Yes' : 'No'));
            $this->line("   - Chunks Count: " . ($doc->chunks_count ?? 0));
            
            // Đếm chunks thực tế
            $chunks = $doc->documentChunks()->get();
            $chunksWithEmbedding = $chunks->filter(fn($c) => !empty($c->embedding))->count();
            
            $this->line("   - Chunks (DB): {$chunks->count()}");
            $this->line("   - Chunks with Embedding: {$chunksWithEmbedding}");
            
            if ($doc->status === 'indexed' && $doc->is_indexed) {
                $indexedCount++;
            }
            
            $totalChunks += $chunks->count();
            
            // Hiển thị một vài chunks đầu tiên
            if ($chunks->isNotEmpty()) {
                $this->line("   - Sample chunks:");
                foreach ($chunks->take(3) as $chunk) {
                    $preview = substr($chunk->content, 0, 100);
                    $hasEmbedding = !empty($chunk->embedding) ? '✅' : '❌';
                    $this->line("     {$hasEmbedding} Chunk {$chunk->chunk_index}: {$preview}...");
                }
            }
            
            $this->newLine();
        }
        
        $this->info("📊 Tổng kết:");
        $this->line("   - Tổng documents: {$documents->count()}");
        $this->line("   - Documents đã index: {$indexedCount}");
        $this->line("   - Tổng chunks: {$totalChunks}");
        
        // Kiểm tra documents có status = 'indexed'
        $indexedDocuments = $assistant->documents()
            ->where('status', 'indexed')
            ->where('file_type', '!=', 'url')
            ->count();
        
        $this->newLine();
        $this->info("🔍 Kiểm tra documents cho search:");
        $this->line("   - Documents với status='indexed' và file_type!='url': {$indexedDocuments}");
        
        // Kiểm tra chunks có embedding
        $chunksWithEmbedding = DocumentChunk::query()
            ->whereHas('assistantDocument', function ($q) use ($assistant) {
                $q->where('ai_assistant_id', $assistant->id)
                  ->where('status', 'indexed')
                  ->where('file_type', '!=', 'url');
            })
            ->whereNotNull('embedding')
            ->count();
        
        $this->line("   - Chunks có embedding: {$chunksWithEmbedding}");
        
        // Test search nếu có option
        if ($this->option('test-search')) {
            $this->newLine();
            $this->info('🧪 Đang test search với câu hỏi: "PolyPi có chức năng gì?"');
            
            $testQuery = "PolyPi có chức năng gì";
            $searchResults = $this->vectorSearchService->searchSimilar(
                $testQuery,
                $assistant->id,
                5,
                0.7,
                []
            );
            
            // Filter out reference URL chunks
            $searchResults = array_filter($searchResults, function($result) {
                $metadata = $result['metadata'] ?? [];
                return ($metadata['source_type'] ?? null) !== 'reference_url';
            });
            
            if (empty($searchResults)) {
                $this->warn('⚠️  Không tìm thấy kết quả với threshold 0.7');
                $this->info('   Đang thử với threshold 0.5...');
                $searchResults = $this->vectorSearchService->searchSimilar(
                    $testQuery,
                    $assistant->id,
                    5,
                    0.5,
                    []
                );
                $searchResults = array_filter($searchResults, function($result) {
                    $metadata = $result['metadata'] ?? [];
                    return ($metadata['source_type'] ?? null) !== 'reference_url';
                });
            }
            
            if (empty($searchResults)) {
                $this->warn('⚠️  Không tìm thấy kết quả với threshold 0.5');
                $this->info('   Đang thử với threshold 0.3...');
                $searchResults = $this->vectorSearchService->searchSimilar(
                    $testQuery,
                    $assistant->id,
                    5,
                    0.3,
                    []
                );
                $searchResults = array_filter($searchResults, function($result) {
                    $metadata = $result['metadata'] ?? [];
                    return ($metadata['source_type'] ?? null) !== 'reference_url';
                });
            }
            
            if (empty($searchResults)) {
                $this->error('❌ Không tìm thấy kết quả nào với tất cả thresholds!');
                $this->info('   Có thể do:');
                $this->line('     1. Documents chưa được index đúng');
                $this->line('     2. Embeddings không được tạo');
                $this->line('     3. Nội dung câu hỏi không khớp với tài liệu');
            } else {
                $this->info("✅ Tìm thấy " . count($searchResults) . " kết quả:");
                foreach ($searchResults as $i => $result) {
                    $this->newLine();
                    $this->line("   Kết quả " . ($i + 1) . ":");
                    $this->line("   - Similarity: " . number_format($result['similarity'], 3));
                    $this->line("   - Content preview: " . substr($result['content'], 0, 200) . "...");
                }
            }
        }
        
        return 0;
    }
}


