<?php

namespace Tests\Feature\Admin;

use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(Quiz $quiz, string $name, int $score, ?int $seconds): void
    {
        $start = now()->subMinutes(10);

        Attempt::create([
            'student_id' => Student::factory()->create(['name' => $name])->id,
            'quiz_id' => $quiz->id,
            'started_at' => $start,
            'finished_at' => $seconds === null ? null : $start->copy()->addSeconds($seconds),
            'score' => $score,
            'total' => 5,
        ]);
    }

    public function test_sorted_by_score_then_time_with_in_progress_last_among_equals(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $quiz = Quiz::factory()->withQuestions(5)->create();

        $this->attempt($quiz, 'Lent', 4, 90);
        $this->attempt($quiz, 'Rapide', 4, 40);
        $this->attempt($quiz, 'Champion', 5, 100);
        $this->attempt($quiz, 'EnCours', 4, null);
        $this->attempt($quiz, 'Dernier', 1, 20);
        $this->attempt($quiz, 'ExAequo', 4, 40);

        $response = $this->getJson("/api/admin/quizzes/{$quiz->id}/leaderboard")->assertOk();

        $names = collect($response->json('entries'))->pluck('name')->all();
        $this->assertSame('Champion', $names[0]);
        $this->assertEqualsCanonicalizing(['Rapide', 'ExAequo'], array_slice($names, 1, 2));
        $this->assertSame(['Lent', 'EnCours', 'Dernier'], array_slice($names, 3));

        $this->assertSame([1, 2, 2, 4, 5, 6], collect($response->json('entries'))->pluck('rank')->all());
        $response->assertJsonPath('entries.0.duration_seconds', 100)
            ->assertJsonPath('entries.0.total', 5)
            ->assertJsonPath('quiz.questions_count', 5);
    }

    public function test_leaderboard_requires_admin(): void
    {
        $quiz = Quiz::factory()->create();

        $this->getJson("/api/admin/quizzes/{$quiz->id}/leaderboard")->assertUnauthorized();
    }
}
