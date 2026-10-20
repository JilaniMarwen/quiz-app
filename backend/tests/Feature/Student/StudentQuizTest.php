<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;

class StudentQuizTest extends StudentTestCase
{
    public function test_list_shows_open_quizzes_with_status(): void
    {
        $open = Quiz::factory()->open()->withQuestions(2)->create(['title' => 'Ouvert']);
        Quiz::factory()->withQuestions(2)->create(['title' => 'Fermé']);
        $token = $this->register();

        $this->as($token)->getJson('/api/student/quizzes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Ouvert')
            ->assertJsonPath('data.0.status', 'not_started')
            ->assertJsonPath('data.0.questions_count', 2);

        $this->startQuiz($token, $open);

        $this->as($token)->getJson('/api/student/quizzes')->assertJsonPath('data.0.status', 'in_progress');
    }

    public function test_done_quiz_shows_score_even_after_closing(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);

        $q1 = $this->current($token, $attemptId)->json('question.id');
        $q2 = $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->json('question.id');
        $this->answer($token, $attemptId, $q2, $this->wrongChoiceId($q2));

        $quiz->update(['is_open' => false]);

        $this->as($token)->getJson('/api/student/quizzes')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'done')
            ->assertJsonPath('data.0.is_open', false)
            ->assertJsonPath('data.0.score', 1)
            ->assertJsonPath('data.0.total', 2);
    }

    public function test_closed_quiz_cannot_be_started(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();
        $token = $this->register();

        $this->as($token)->postJson("/api/student/quizzes/{$quiz->id}/start")
            ->assertForbidden()
            ->assertJsonPath('message', 'Ce quiz est fermé.');

        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_second_attempt_is_refused_and_returns_the_existing_one(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);

        $this->as($token)->postJson("/api/student/quizzes/{$quiz->id}/start")
            ->assertStatus(409)
            ->assertJsonPath('attempt.id', $attemptId)
            ->assertJsonPath('attempt.status', 'in_progress');

        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_finished_attempt_cannot_be_replayed(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(1)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');
        $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->assertJsonPath('status', 'finished');

        $this->as($token)->postJson("/api/student/quizzes/{$quiz->id}/start")
            ->assertStatus(409)
            ->assertJsonPath('attempt.status', 'done');

        $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->assertStatus(409);
    }

    public function test_attempt_in_progress_can_finish_after_the_quiz_is_closed(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');

        $quiz->update(['is_open' => false]);

        $q2 = $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->assertOk()->json('question.id');
        $this->answer($token, $attemptId, $q2, $this->correctChoiceId($q2))
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('score', 2);
    }
}
