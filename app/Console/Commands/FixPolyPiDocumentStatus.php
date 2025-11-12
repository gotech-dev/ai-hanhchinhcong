<?php

namespace App\Console\Commands;

use App\Models\AiAssistant;
use App\Models\AssistantDocument;
use Illuminate\Console\Command;

class FixPolyPiDocumentStatus extends Command
{
    protected $signature = 'fix:polypi-status';
    protected $description = 'Fix status của documents PolyPi từ error thành indexed nếu có embeddings';

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
        
        $documents = $assistant->documents()->get();
        
        foreach ($documents as $doc) {
            $chunksWithEmbedding = $doc->documentChunks()
                ->whereNotNull('embedding')
                ->count();
            
            if ($chunksWithEmbedding > 0 && ($doc->status !== 'indexed' || !$doc->is_indexed)) {
                $this->info("📄 Fixing document: {$doc->file_name}");
                $this->line("   - Status hiện tại: {$doc->status}");
                $this->line("   - Is Indexed: " . ($doc->is_indexed ? 'Yes' : 'No'));
                $this->line("   - Chunks with embedding: {$chunksWithEmbedding}");
                
                $doc->update([
                    'status' => 'indexed',
                    'is_indexed' => true,
                ]);
                
                $this->info("   ✅ Đã cập nhật status thành 'indexed'");
            } else {
                $this->line("📄 Document: {$doc->file_name} - OK (status: {$doc->status}, chunks: {$chunksWithEmbedding})");
            }
        }
        
        $this->newLine();
        $this->info('✅ Hoàn thành!');
        
        return 0;
    }
}


