<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_and_receives_a_token(): void
    {
        User::factory()->create(['username' => 'prof', 'password' => 'secret123']);

        $response = $this->postJson('/api/admin/login', ['username' => 'prof', 'password' => 'secret123']);

        $response->assertOk()
            ->assertJsonPath('user.username', 'prof')
            ->assertJsonStructure(['token']);

        $this->withToken($response->json('token'))
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('user.username', 'prof');
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'prof', 'password' => 'secret123']);

        $this->postJson('/api/admin/login', ['username' => 'prof', 'password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username' => 'Identifiants incorrects.']);
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/quizzes')->assertUnauthorized();
        $this->postJson('/api/admin/quizzes', ['title' => 'X'])->assertUnauthorized();
        $this->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        User::factory()->create(['username' => 'prof', 'password' => 'secret123']);
        $token = $this->postJson('/api/admin/login', ['username' => 'prof', 'password' => 'secret123'])->json('token');

        $this->withToken($token)->postJson('/api/admin/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
