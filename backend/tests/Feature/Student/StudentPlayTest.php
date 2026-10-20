<?php

namespace Tests\Feature\Student;

use App\Models\Attempt;
use App\Models\Quiz;

class StudentPlayTest extends StudentTestCase
{
    public function test_full_play_through_computes_the_score(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(3)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);

        $state = $this->current($token, $attemptId)
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('index', 1)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('remaining_seconds', 20)
            ->assertJsonCount(4, 'question.choices');

        $q1 = $state->json('question.id');
        $q2 = $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))
            ->assertOk()->assertJsonPath('index', 2)->json('question.id');
        $q3 = $this->answer($token, $attemptId, $q2, $this->wrongChoiceId($q2))
            ->assertOk()->assertJsonPath('index', 3)->json('question.id');

        $this->answer($token, $attemptId, $q3, $this->correctChoiceId($q3))
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('score', 2)
            ->assertJsonPath('total', 3);

        $this->as($token)->getJson("/api/student/attempts/{$attemptId}/result")
            ->assertOk()
            ->assertJsonPath('score', 2)
            ->assertJsonPath('total', 3);

        $this->assertNotNull(Attempt::find($attemptId)->finished_at);
    }

    public function test_answer_after_the_deadline_counts_as_wrong(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create(['time_per_question' => 20]);
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');

        // 20 s + 2 s grace is still accepted…
        $this->travel(22)->seconds();
        $q2 = $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->assertOk()->json('question.id');

        // …but 23 s is too late, even with the right answer.
        $this->travel(23)->seconds();
        $this->answer($token, $attemptId, $q2, $this->correctChoiceId($q2))
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('score', 1);
    }

    public function test_timer_keeps_millisecond_precision(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create(['time_per_question' => 20]);
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);

        $this->travelTo(now()->startOfSecond()->addMilliseconds(900));
        $q1 = $this->current($token, $attemptId)->json('question.id');

        // 21.9 s after serving: inside 20 s + 2 s grace (would be "late" if served_at lost its milliseconds).
        $this->travelTo(now()->addMilliseconds(21900));
        $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->assertOk();

        $this->assertSame(1, Attempt::find($attemptId)->score);
    }

    public function test_timeout_with_null_choice_counts_as_wrong_and_moves_on(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');

        $this->travel(20)->seconds();
        $this->answer($token, $attemptId, $q1, null)
            ->assertOk()
            ->assertJsonPath('index', 2)
            ->assertJsonPath('remaining_seconds', 20);

        $this->assertSame(0, Attempt::find($attemptId)->score);
    }

    public function test_refresh_resumes_the_same_question_with_the_server_timer(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');

        $this->travel(8)->seconds();

        $this->current($token, $attemptId)
            ->assertJsonPath('question.id', $q1)
            ->assertJsonPath('index', 1)
            ->assertJsonPath('remaining_seconds', 12);
    }

    public function test_leaving_mid_quiz_expires_the_question_on_return(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');

        $this->travel(5)->minutes();

        $state = $this->current($token, $attemptId)->assertJsonPath('index', 2);
        $this->assertNotSame($q1, $state->json('question.id'));

        // The expired question can no longer be answered.
        $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->assertStatus(409);
    }

    public function test_choice_must_belong_to_the_current_question(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');
        $otherQuestion = $quiz->questions()->where('id', '!=', $q1)->value('id');

        $this->answer($token, $attemptId, $q1, $this->correctChoiceId($otherQuestion))->assertStatus(422);
        $this->answer($token, $attemptId, $otherQuestion, $this->correctChoiceId($otherQuestion))->assertStatus(409);
    }

    public function test_is_correct_is_never_sent_to_the_student(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();

        $responses = [$this->as($token)->getJson('/api/student/quizzes')];
        $responses[] = $start = $this->as($token)->postJson("/api/student/quizzes/{$quiz->id}/start");
        $attemptId = $start->json('attempt.id');
        $responses[] = $current = $this->current($token, $attemptId);
        $q1 = $current->json('question.id');
        $responses[] = $next = $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1));
        $responses[] = $this->answer($token, $attemptId, $next->json('question.id'), $this->wrongChoiceId($next->json('question.id')));
        $responses[] = $this->as($token)->getJson("/api/student/attempts/{$attemptId}/result");

        foreach ($responses as $response) {
            $this->assertStringNotContainsString('is_correct', $response->getContent());
            $this->assertStringNotContainsString('correct', strtolower(json_encode(array_keys($response->json() ?? []))));
        }
    }

    public function test_student_cannot_access_another_students_attempt(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $attemptId = $this->startQuiz($this->register('Alice'), $quiz);
        $intruder = $this->register('Bob');

        $this->as($intruder)->getJson("/api/student/attempts/{$attemptId}/current")->assertNotFound();
        $this->as($intruder)->getJson("/api/student/attempts/{$attemptId}/result")->assertNotFound();
        $this->answer($intruder, $attemptId, 1, null)->assertNotFound();
    }

    public function test_result_of_unfinished_attempt_is_409(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register();
        $attemptId = $this->startQuiz($token, $quiz);

        $this->as($token)->getJson("/api/student/attempts/{$attemptId}/result")
            ->assertStatus(409)
            ->assertJsonPath('status', 'in_progress');
    }

    public function test_leaderboard_reflects_live_score_and_duration(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(2)->create();
        $token = $this->register('Alice');
        $attemptId = $this->startQuiz($token, $quiz);
        $q1 = $this->current($token, $attemptId)->json('question.id');
        $this->travel(3)->seconds();
        $q2 = $this->answer($token, $attemptId, $q1, $this->correctChoiceId($q1))->json('question.id');

        $this->assertSame(1, Attempt::find($attemptId)->score); // live, while in progress

        $this->travel(4)->seconds();
        $this->answer($token, $attemptId, $q2, $this->correctChoiceId($q2));

        $attempt = Attempt::find($attemptId);
        $this->assertSame(2, $attempt->score);
        $this->assertSame(7000, $attempt->durationMs());
    }
}
