<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_model_returns_a_french_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/quizzes/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Élément introuvable (il a peut-être été supprimé).');
    }

    public function test_login_is_throttled_with_a_french_message(): void
    {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/admin/login', ['username' => 'x', 'password' => 'y'])->assertStatus(422);
        }

        $this->postJson('/api/admin/login', ['username' => 'x', 'password' => 'y'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Trop de tentatives. Patiente une minute.');
    }
}
