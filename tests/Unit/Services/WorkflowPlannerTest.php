<?php

namespace Tests\Unit\Services;

use App\Models\AiAssistant;
use App\Models\User;
use App\Services\WorkflowPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new WorkflowPlanner();
    }

    public function test_can_plan_qa_workflow(): void
    {
        $user = User::factory()->create();
        
        $assistant = AiAssistant::create([
            'admin_id' => $user->id,
            'name' => 'Test Assistant',
            'assistant_type' => 'qa_based_document',
            'is_active' => true,
        ]);
        
        $intent = [
            'type' => 'ask_question',
            'entity' => [],
            'confidence' => 0.9,
        ];
        
        $workflow = $this->planner->plan($intent, $assistant);
        
        $this->assertIsArray($workflow);
        $this->assertArrayHasKey('steps', $workflow);
        $this->assertArrayHasKey('estimated_time', $workflow);
        $this->assertNotEmpty($workflow['steps']);
    }

    public function test_can_plan_report_workflow(): void
    {
        $user = User::factory()->create();
        
        $assistant = AiAssistant::create([
            'admin_id' => $user->id,
            'name' => 'Test Assistant',
            'assistant_type' => 'report_generator',
            'config' => [
                'template_fields' => [
                    ['key' => 'activities', 'label' => 'Hoạt động', 'required' => true],
                    ['key' => 'results', 'label' => 'Kết quả', 'required' => true],
                ],
            ],
            'is_active' => true,
        ]);
        
        $intent = [
            'type' => 'create_report',
            'entity' => [],
            'confidence' => 0.9,
        ];
        
        $workflow = $this->planner->plan($intent, $assistant, []);
        
        $this->assertIsArray($workflow);
        $this->assertArrayHasKey('steps', $workflow);
        $this->assertArrayHasKey('estimated_time', $workflow);
        
        // Should have steps for collecting info
        $this->assertNotEmpty($workflow['steps']);
    }

    public function test_workflow_includes_generation_step_when_all_data_collected(): void
    {
        $user = User::factory()->create();
        
        $assistant = AiAssistant::create([
            'admin_id' => $user->id,
            'name' => 'Test Assistant',
            'assistant_type' => 'report_generator',
            'config' => [
                'template_fields' => [
                    ['key' => 'activities', 'label' => 'Hoạt động', 'required' => true],
                ],
            ],
            'is_active' => true,
        ]);
        
        $intent = [
            'type' => 'create_report',
            'entity' => [],
            'confidence' => 0.9,
        ];
        
        $collectedData = [
            'activities' => 'Test activities',
        ];
        
        $workflow = $this->planner->plan($intent, $assistant, $collectedData);
        
        $this->assertIsArray($workflow);
        
        // Should have generate_report step
        $hasGenerateStep = false;
        foreach ($workflow['steps'] as $step) {
            if (($step['type'] ?? null) === 'generate' || ($step['action'] ?? null) === 'create_report_from_template') {
                $hasGenerateStep = true;
                break;
            }
        }
        
        $this->assertTrue($hasGenerateStep);
    }
}








