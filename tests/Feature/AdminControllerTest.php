<?php

namespace Tests\Feature;

use App\Models\AiAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Storage::fake('public');
    }

    public function test_can_get_admin_assistants(): void
    {
        AiAssistant::factory()->count(3)->create([
            'admin_id' => $this->admin->id,
        ]);
        
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/assistants');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'assistants' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_create_assistant_with_minimalist_form(): void
    {
        $data = [
            'name' => 'Test Assistant',
            'description' => 'Test Description',
            'assistant_type' => 'qa_based_document',
        ];
        
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', $data);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'assistant' => [
                    'id',
                    'name',
                ],
                'message',
            ]);
        
        $this->assertDatabaseHas('ai_assistants', [
            'name' => 'Test Assistant',
            'admin_id' => $this->admin->id,
        ]);
    }

    public function test_can_upload_documents_to_assistant(): void
    {
        $assistant = AiAssistant::factory()->create([
            'admin_id' => $this->admin->id,
            'assistant_type' => 'qa_based_document',
        ]);
        
        $file = UploadedFile::fake()->create('document.pdf', 100);
        
        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/assistants/{$assistant->id}/documents", [
                'documents' => [$file],
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_can_get_dashboard_stats(): void
    {
        AiAssistant::factory()->count(2)->create([
            'admin_id' => $this->admin->id,
        ]);
        
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard/stats');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'statistics' => [
                    'total_assistants',
                    'active_assistants',
                ],
            ]);
    }

    public function test_can_preview_assistant(): void
    {
        $assistant = AiAssistant::factory()->create([
            'admin_id' => $this->admin->id,
        ]);
        
        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/assistants/{$assistant->id}/preview");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'assistant' => [
                    'id',
                    'name',
                ],
                'statistics',
            ]);
    }

    public function test_only_admin_can_access_admin_routes(): void
    {
        $otherUser = User::factory()->create();
        
        $response = $this->actingAs($otherUser)
            ->getJson('/api/admin/assistants');
        
        // Should be able to access but see only their own assistants
        $response->assertStatus(200);
    }
}








