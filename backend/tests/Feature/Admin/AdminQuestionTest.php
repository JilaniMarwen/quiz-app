<?php

namespace Tests\Feature\Admin;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminQuestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'text' => 'Que signifie MVP ?',
            'choices' => [
                ['text' => 'Minimum Viable Product', 'is_correct' => true],
                ['text' => 'Most Valuable Player', 'is_correct' => false],
                ['text' => 'Maximum Value Plan', 'is_correct' => false],
                ['text' => 'Market Validation', 'is_correct' => false],
            ],
        ], $overrides);
    }

    public function test_admin_can_add_a_question_with_four_choices(): void
    {
        $quiz = Quiz::factory()->withQuestions(1)->create();

        $this->postJson("/api/admin/quizzes/{$quiz->id}/questions", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.text', 'Que signifie MVP ?')
            ->assertJsonPath('data.position', 2)
            ->assertJsonCount(4, 'data.choices')
            ->assertJsonPath('data.choices.0.is_correct', true);

        $this->assertTrue($quiz->fresh()->isValid());
    }

    public function test_question_must_have_exactly_four_choices(): void
    {
        $quiz = Quiz::factory()->create();
        $payload = $this->payload();
        array_pop($payload['choices']);

        $this->postJson("/api/admin/quizzes/{$quiz->id}/questions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('choices');
    }

    public function test_question_must_have_exactly_one_correct_choice(): void
    {
        $quiz = Quiz::factory()->create();

        $none = $this->payload();
        $none['choices'][0]['is_correct'] = false;
        $this->postJson("/api/admin/quizzes/{$quiz->id}/questions", $none)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['choices' => 'Il faut exactement une bonne réponse.']);

        $two = $this->payload();
        $two['choices'][1]['is_correct'] = true;
        $this->postJson("/api/admin/quizzes/{$quiz->id}/questions", $two)
            ->assertStatus(422)
            ->assertJsonValidationErrors('choices');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_texts_must_not_be_empty(): void
    {
        $quiz = Quiz::factory()->create();
        $payload = $this->payload(['text' => '']);
        $payload['choices'][2]['text'] = '';

        $this->postJson("/api/admin/quizzes/{$quiz->id}/questions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text', 'choices.2.text']);
    }

    public function test_update_question_keeps_choice_ids(): void
    {
        $quiz = Quiz::factory()->withQuestions(1)->create();
        $question = $quiz->questions->first();
        $ids = $question->choices->pluck('id')->all();

        $payload = $this->payload(['text' => 'Modifiée ?']);
        $payload['choices'][0]['is_correct'] = false;
        $payload['choices'][3]['is_correct'] = true;

        $this->putJson("/api/admin/questions/{$question->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.text', 'Modifiée ?')
            ->assertJsonPath('data.choices.3.is_correct', true)
            ->assertJsonPath('data.choices.0.is_correct', false);

        $this->assertSame($ids, $question->choices()->pluck('id')->all());
        $this->assertDatabaseCount('choices', 4);
    }

    public function test_update_question_is_validated_too(): void
    {
        $question = Quiz::factory()->withQuestions(1)->create()->questions->first();
        $payload = $this->payload();
        $payload['choices'][1]['is_correct'] = true;

        $this->putJson("/api/admin/questions/{$question->id}", $payload)->assertStatus(422);
    }

    public function test_admin_can_delete_a_question(): void
    {
        $quiz = Quiz::factory()->withQuestions(2)->create();

        $this->deleteJson("/api/admin/questions/{$quiz->questions->first()->id}")->assertNoContent();

        $this->assertDatabaseCount('questions', 1);
        $this->assertDatabaseCount('choices', 4);
    }

    public function test_last_question_of_an_open_quiz_cannot_be_deleted(): void
    {
        $quiz = Quiz::factory()->open()->withQuestions(1)->create();

        $this->deleteJson("/api/admin/questions/{$quiz->questions->first()->id}")->assertStatus(422);
        $this->assertDatabaseCount('questions', 1);
    }
}
