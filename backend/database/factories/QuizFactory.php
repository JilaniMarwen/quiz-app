<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => null,
            'time_per_question' => 20,
            'is_open' => false,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['is_open' => true]);
    }

    /**
     * Add $count valid questions (4 choices, the first one correct).
     */
    public function withQuestions(int $count = 3): static
    {
        return $this->afterCreating(function (Quiz $quiz) use ($count) {
            Question::factory()->count($count)->for($quiz)
                ->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
                ->create();
        });
    }
}
