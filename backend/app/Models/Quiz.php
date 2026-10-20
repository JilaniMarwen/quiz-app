<?php

namespace App\Models;

use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'time_per_question', 'is_open'])]
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    protected $attributes = [
        'time_per_question' => 20,
        'is_open' => false,
    ];

    protected function casts(): array
    {
        return [
            'time_per_question' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    /**
     * Rule 8: openable only with ≥ 1 question, each with exactly 4 choices of which 1 is correct.
     */
    public function isValid(): bool
    {
        $questions = $this->questions()
            ->withCount(['choices', 'choices as correct_choices_count' => fn ($q) => $q->where('is_correct', true)])
            ->get();

        return $questions->isNotEmpty()
            && $questions->every(fn ($q) => $q->choices_count === 4 && $q->correct_choices_count === 1);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
