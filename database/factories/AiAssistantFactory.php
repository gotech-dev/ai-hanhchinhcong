<?php

namespace Database\Factories;

use App\Models\AiAssistant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiAssistant>
 */
class AiAssistantFactory extends Factory
{
    protected $model = AiAssistant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'assistant_type' => fake()->randomElement(['report_generator', 'qa_based_document']),
            'template_file_path' => null,
            'documents' => null,
            'config' => [],
            'avatar_url' => null,
            'is_active' => true,
        ];
    }
}








