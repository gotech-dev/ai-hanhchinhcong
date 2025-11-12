<?php

namespace Tests\Feature;

use App\Models\AiAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_assistants(): void
    {
        AiAssistant::factory()->count(3)->create([
            'is_active' => true,
        ]);
        
        $response = $this->getJson('/api/assistants');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'assistants' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'assistant_type',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_get_assistant_details(): void
    {
        $assistant = AiAssistant::factory()->create([
            'is_active' => true,
        ]);
        
        $response = $this->getJson("/api/assistants/{$assistant->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'assistant' => [
                    'id',
                    'name',
                    'description',
                    'assistant_type',
                ],
            ]);
    }

    public function test_can_create_assistant(): void
    {
        $data = [
            'name' => 'Test Assistant',
            'description' => 'Test Description',
            'assistant_type' => 'report_generator',
        ];
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/assistants', $data);
        
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
            'admin_id' => $this->user->id,
        ]);
    }

    public function test_can_update_assistant(): void
    {
        $assistant = AiAssistant::factory()->create([
            'admin_id' => $this->user->id,
        ]);
        
        $data = [
            'name' => 'Updated Name',
        ];
        
        $response = $this->actingAs($this->user)
            ->putJson("/api/assistants/{$assistant->id}", $data);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('ai_assistants', [
            'id' => $assistant->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_assistant(): void
    {
        $assistant = AiAssistant::factory()->create([
            'admin_id' => $this->user->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/assistants/{$assistant->id}");
        
        $response->assertStatus(200);
        $this->assertDatabaseMissing('ai_assistants', ['id' => $assistant->id]);
    }

    public function test_cannot_delete_other_user_assistant(): void
    {
        $otherUser = User::factory()->create();
        $assistant = AiAssistant::factory()->create([
            'admin_id' => $otherUser->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/assistants/{$assistant->id}");
        
        $response->assertStatus(404);
    }

    public function test_validation_works_for_assistant_creation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/assistants', []);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'assistant_type']);
    }
}








