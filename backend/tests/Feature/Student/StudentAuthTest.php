<?php

namespace Tests\Feature\Student;

use App\Models\Student;

class StudentAuthTest extends StudentTestCase
{
    public function test_student_registers_with_a_name_and_gets_a_token(): void
    {
        $response = $this->postJson('/api/students', ['name' => '  Ahmed   Ben  '])
            ->assertCreated()
            ->assertJsonPath('student.name', 'Ahmed Ben')
            ->assertJsonStructure(['token']);

        // Only the hash is stored.
        $this->assertNotSame($response->json('token'), Student::first()->token);
        $this->assertSame(Student::hashToken($response->json('token')), Student::first()->token);
    }

    public function test_name_already_taken_is_refused(): void
    {
        $this->register('Ahmed');

        $this->postJson('/api/students', ['name' => 'Ahmed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Ce nom est déjà utilisé.']);

        $this->postJson('/api/students', ['name' => 'ahmed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('students', 1);
    }

    public function test_name_is_required(): void
    {
        $this->postJson('/api/students', ['name' => '   '])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_token_identifies_the_student_after_reload(): void
    {
        $token = $this->register('Ahmed');

        $this->as($token)->getJson('/api/student/me')
            ->assertOk()
            ->assertJsonPath('student.name', 'Ahmed')
            ->assertJsonMissingPath('student.token');
    }

    public function test_missing_or_invalid_token_is_rejected(): void
    {
        $this->getJson('/api/student/me')->assertUnauthorized();
        $this->as('not-a-real-token')->getJson('/api/student/quizzes')->assertUnauthorized();
    }
}
