<?php

namespace Database\Factories;

use App\Models\AiAssistant;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatSession>
 */
class ChatSessionFactory extends Factory
{
    protected $model = ChatSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ai_assistant_id' => AiAssistant::factory(),
            'title' => fake()->sentence(3),
            'workflow_state' => null,
            'collected_data' => [],
        ];
    }
}








