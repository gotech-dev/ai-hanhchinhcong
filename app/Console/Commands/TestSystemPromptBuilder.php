<?php

namespace App\Console\Commands;

use App\Models\AiAssistant;
use App\Services\SystemPromptBuilder;
use Illuminate\Console\Command;

class TestSystemPromptBuilder extends Command
{
    protected $signature = 'test:system-prompt {assistant_id?}';
    protected $description = 'Test SystemPromptBuilder với assistant cụ thể';

    public function handle()
    {
        $assistantId = $this->argument('assistant_id');
        
        if ($assistantId) {
            $assistant = AiAssistant::find($assistantId);
            if (!$assistant) {
                $this->error("❌ Không tìm thấy assistant với ID: {$assistantId}");
                return 1;
            }
            $assistants = [$assistant];
        } else {
            // Test với PolyPi
            $assistants = AiAssistant::where('name', 'like', '%PolyPi%')
                ->orWhere('name', 'like', '%Trợ lý học tiếng Anh%')
                ->get();
            
            if ($assistants->isEmpty()) {
                $this->warn('⚠️  Không tìm thấy assistant PolyPi, test với assistant đầu tiên');
                $assistants = AiAssistant::limit(1)->get();
            }
        }
        
        $builder = app(SystemPromptBuilder::class);
        
        foreach ($assistants as $assistant) {
            $this->info("🔍 Testing assistant: {$assistant->name} (ID: {$assistant->id})");
            $this->info("   Type: {$assistant->getAssistantTypeValue()}");
            $this->newLine();
            
            $prompt = $builder->build($assistant);
            
            $this->info("📄 System Prompt:");
            $this->line("---");
            $this->line($prompt);
            $this->line("---");
            $this->newLine();
            
            // Check source
            if (!empty($assistant->system_prompt_override)) {
                $this->info("✅ Source: assistant.system_prompt_override");
            } elseif ($assistant->type && !empty($assistant->type->system_prompt)) {
                $this->info("✅ Source: assistant_type.system_prompt ({$assistant->type->code})");
            } else {
                $this->info("✅ Source: default prompt by type");
            }
            
            $this->newLine();
            $this->line("---");
            $this->newLine();
        }
        
        return 0;
    }
}


