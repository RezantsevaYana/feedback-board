<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «Настройки аккаунта» → «Профиль»: PATCH /api/admin/me changes the name. */
class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_change_a_name(): void
    {
        $this->patchJson('/api/admin/me', ['name' => 'Анна'])->assertUnauthorized();
    }

    public function test_the_name_changes_and_me_comes_back(): void
    {
        $user = User::factory()->create(['name' => 'Anna', 'email' => 'anna@example.com']);

        $this->actingAs($user)->patchJson('/api/admin/me', ['name' => '  Анна Смирнова  '])
            ->assertOk()
            ->assertJsonPath('data.name', 'Анна Смирнова')
            ->assertJsonPath('data.email', 'anna@example.com');

        $this->assertSame('Анна Смирнова', $user->fresh()->name);
    }

    public function test_the_email_is_not_changed_here(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->actingAs($user)->patchJson('/api/admin/me', ['name' => 'Анна', 'email' => 'other@example.com'])->assertOk();

        $this->assertSame('anna@example.com', $user->fresh()->email);
    }

    public function test_the_name_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/admin/me', ['name' => '   '])
            ->assertJsonValidationErrors(['name' => 'Представьтесь — хотя бы имя.']);
        $this->actingAs($user)->patchJson('/api/admin/me', ['name' => str_repeat('я', 81)])
            ->assertJsonValidationErrors(['name' => 'Имя — не длиннее 80 символов.']);
    }
}