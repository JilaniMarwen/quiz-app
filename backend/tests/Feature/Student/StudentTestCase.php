<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class StudentTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic server timer: time only moves with travel().
        $this->freezeTime();
    }

    /**
     * Register a student through the API and return the plain token.
     */
    protected function register(string $name = 'Ahmed'): string
    {
        return $this->postJson('/api/students', ['name' => $name])->assertCreated()->json('token');
    }

    protected function as(string $token): static
    {
        return $this->withHeader('X-Student-Token', $token);
    }

    protected function startQuiz(string $token, Quiz $quiz): int
    {
        return $this->as($token)->postJson("/api/student/quizzes/{$quiz->id}/start")->assertCreated()->json('attempt.id');
    }

    protected function current(string $token, int $attemptId): TestResponse
    {
        return $this->as($token)->getJson("/api/student/attempts/{$attemptId}/current")->assertOk();
    }

    protected function answer(string $token, int $attemptId, int $questionId, ?int $choiceId): TestResponse
    {
        return $this->as($token)->postJson("/api/student/attempts/{$attemptId}/answer", [
            'question_id' => $questionId,
            'choice_id' => $choiceId,
        ]);
    }

    /**
     * Factory questions: the choice at position 1 is the correct one.
     */
    protected function correctChoiceId(int $questionId): int
    {
        return \App\Models\Choice::where('question_id', $questionId)->where('is_correct', true)->value('id');
    }

    protected function wrongChoiceId(int $questionId): int
    {
        return \App\Models\Choice::where('question_id', $questionId)->where('is_correct', false)->value('id');
    }
}
