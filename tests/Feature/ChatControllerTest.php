<?php

namespace Tests\Feature;

use App\Models\AiAssistant;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected AiAssistant $assistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->assistant = AiAssistant::factory()->create([
            'admin_id' => $this->user->id,
            'is_active' => true,
        ]);
    }

    public function test_can_get_or_create_session(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/chat/sessions/assistant/{$this->assistant->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'session' => [
                    'id',
                    'user_id',
                    'ai_assistant_id',
                ],
            ]);
    }

    public function test_can_get_chat_history(): void
    {
        $session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'ai_assistant_id' => $this->assistant->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/chat/sessions/{$session->id}/history");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'session',
                'messages',
            ]);
    }

    public function test_can_get_user_sessions(): void
    {
        ChatSession::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'ai_assistant_id' => $this->assistant->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/sessions');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'sessions' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_delete_session(): void
    {
        $session = ChatSession::factory()->create([
            'user_id' => $this->user->id,
            'ai_assistant_id' => $this->assistant->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/chat/sessions/{$session->id}");
        
        $response->assertStatus(200);
        $this->assertDatabaseMissing('chat_sessions', ['id' => $session->id]);
    }

    public function test_cannot_access_other_user_session(): void
    {
        $otherUser = User::factory()->create();
        $session = ChatSession::factory()->create([
            'user_id' => $otherUser->id,
            'ai_assistant_id' => $this->assistant->id,
        ]);
        
        $response = $this->actingAs($this->user)
            ->getJson("/api/chat/sessions/{$session->id}/history");
        
        $response->assertStatus(404);
    }
}








