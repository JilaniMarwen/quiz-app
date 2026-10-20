<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_name_is_unique(): void
    {
        Student::factory()->create(['name' => 'Ahmed']);

        $this->expectException(QueryException::class);
        Student::factory()->create(['name' => 'Ahmed']);
    }

    public function test_student_can_only_have_one_attempt_per_quiz(): void
    {
        $student = Student::factory()->create();
        $quiz = Quiz::factory()->withQuestions(2)->create();
        $data = ['student_id' => $student->id, 'quiz_id' => $quiz->id, 'started_at' => now(), 'total' => 2];

        Attempt::create($data);

        $this->expectException(QueryException::class);
        Attempt::create($data);
    }

    public function test_deleting_a_quiz_cascades_to_questions_choices_and_attempts(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();
        $attempt = Attempt::create([
            'student_id' => Student::factory()->create()->id,
            'quiz_id' => $quiz->id,
            'started_at' => now(),
            'total' => 2,
        ]);
        $attempt->answers()->create(['question_id' => $quiz->questions->first()->id, 'served_at' => now()]);

        $quiz->delete();

        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('choices', 0);
        $this->assertDatabaseCount('attempts', 0);
        $this->assertDatabaseCount('attempt_answers', 0);
    }

    public function test_student_token_is_hidden_from_serialization(): void
    {
        $student = Student::factory()->create();

        $this->assertArrayNotHasKey('token', $student->toArray());
    }

    public function test_seeder_creates_admin_from_env_and_closed_valid_quizzes(): void
    {
        config(['admin.username' => 'prof', 'admin.password' => 'secret123']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempotent

        $admin = User::sole();
        $this->assertSame('prof', $admin->username);
        $this->assertTrue(Hash::check('secret123', $admin->password));

        $quizzes = Quiz::with('questions.choices')->get()->keyBy('title');
        $this->assertSame(
            ["Les bases de l'entrepreneuriat" => 5, 'La classification des entreprises' => 14],
            $quizzes->map(fn ($quiz) => $quiz->questions->count())->all(),
        );

        foreach ($quizzes as $quiz) {
            $this->assertFalse($quiz->is_open);
            $this->assertTrue($quiz->isValid()); // 4 choices, exactly 1 correct (rule 8)
        }
    }
}
