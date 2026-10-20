<?php

namespace Tests\Feature\Admin;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_admin_can_create_a_quiz_closed_by_default(): void
    {
        $this->postJson('/api/admin/quizzes', ['title' => 'Quiz 1', 'time_per_question' => 30])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Quiz 1')
            ->assertJsonPath('data.time_per_question', 30)
            ->assertJsonPath('data.is_open', false);

        $this->postJson('/api/admin/quizzes', ['title' => 'Quiz 2'])
            ->assertCreated()
            ->assertJsonPath('data.time_per_question', 20);
    }

    public function test_quiz_validation(): void
    {
        $this->postJson('/api/admin/quizzes', ['title' => '', 'time_per_question' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'time_per_question']);
    }

    public function test_list_includes_question_and_participant_counts(): void
    {
        $quiz = Quiz::factory()->withQuestions(3)->create();
        Attempt::create(['student_id' => Student::factory()->create()->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'total' => 3]);

        $this->getJson('/api/admin/quizzes')
            ->assertOk()
            ->assertJsonPath('data.0.questions_count', 3)
            ->assertJsonPath('data.0.participants_count', 1);
    }

    public function test_show_returns_questions_with_correct_answer_for_admin(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();

        $this->getJson("/api/admin/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonCount(4, 'data.questions.0.choices')
            ->assertJsonPath('data.questions.0.choices.0.is_correct', true);
    }

    public function test_admin_can_update_and_delete_a_quiz(): void
    {
        $quiz = Quiz::factory()->withQuestions(1)->create();

        $this->putJson("/api/admin/quizzes/{$quiz->id}", ['title' => 'Nouveau titre', 'time_per_question' => 15])
            ->assertOk()
            ->assertJsonPath('data.title', 'Nouveau titre')
            ->assertJsonPath('data.time_per_question', 15);

        $this->deleteJson("/api/admin/quizzes/{$quiz->id}")->assertNoContent();
        $this->assertDatabaseCount('quizzes', 0);
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_quiz_without_questions_cannot_be_opened(): void
    {
        $quiz = Quiz::factory()->create();

        $this->patchJson("/api/admin/quizzes/{$quiz->id}/toggle")->assertStatus(422);
        $this->assertFalse($quiz->fresh()->is_open);
    }

    public function test_quiz_with_an_invalid_question_cannot_be_opened(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();
        // Corrupt one question: make a second choice correct.
        $quiz->questions->first()->choices()->where('position', 2)->update(['is_correct' => true]);

        $this->patchJson("/api/admin/quizzes/{$quiz->id}/toggle")->assertStatus(422);
        $this->assertFalse($quiz->fresh()->is_open);
    }

    public function test_valid_quiz_can_be_opened_and_closed(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();

        $this->patchJson("/api/admin/quizzes/{$quiz->id}/toggle")->assertOk()->assertJsonPath('data.is_open', true);
        $this->patchJson("/api/admin/quizzes/{$quiz->id}/toggle")->assertOk()->assertJsonPath('data.is_open', false);
    }

    public function test_reset_attempts_deletes_results_only(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();
        $attempt = Attempt::create(['student_id' => Student::factory()->create()->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'total' => 2]);
        $attempt->answers()->create(['question_id' => Question::first()->id, 'served_at' => now()]);

        $this->deleteJson("/api/admin/quizzes/{$quiz->id}/attempts")
            ->assertOk()
            ->assertJsonPath('data.participants_count', 0);

        $this->assertDatabaseCount('attempts', 0);
        $this->assertDatabaseCount('attempt_answers', 0);
        $this->assertDatabaseCount('questions', 2);
        $this->assertDatabaseCount('students', 1);
    }
}
