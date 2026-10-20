<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'text' => rtrim(fake()->sentence(), '.').' ?',
            'position' => 1,
        ];
    }

    /**
     * Every question gets 4 choices; the first one is correct.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Question $question) {
            foreach (range(0, 3) as $i) {
                $question->choices()->create([
                    'text' => fake()->words(2, true),
                    'is_correct' => $i === 0,
                    'position' => $i + 1,
                ]);
            }
        });
    }
}
