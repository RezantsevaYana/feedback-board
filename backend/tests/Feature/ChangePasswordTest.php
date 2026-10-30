<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** «Сменить пароль» in the profile: PATCH /api/admin/me/password. */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'secret-pass']);
    }

    private function change(string $current, string $new): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)
            ->patchJson('/api/admin/me/password', ['current_password' => $current, 'password' => $new]);
    }

    public function test_guest_cannot_change_a_password(): void
    {
        $this->patchJson('/api/admin/me/password', ['current_password' => 'secret-pass', 'password' => 'new-secret-pass'])
            ->assertUnauthorized();
    }

    public function test_the_password_changes_and_a_letter_goes_out(): void
    {
        $this->change('secret-pass', 'new-secret-pass')->assertNoContent();

        $this->assertTrue(Hash::check('new-secret-pass', $this->user->fresh()->password));
        Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail) => $mail->hasTo('anna@example.com'));
    }

    public function test_the_letter_never_carries_the_password(): void
    {
        $this->change('secret-pass', 'new-secret-pass')->assertNoContent();

        Mail::assertSent(PasswordChangedMail::class, function (PasswordChangedMail $mail) {
            $html = $mail->render();

            return ! str_contains($html, 'new-secret-pass') && str_contains($html, '/app/forgot-password?email=anna%40example.com');
        });
    }

    public function test_a_wrong_current_password_is_refused(): void
    {
        $this->change('not-mine', 'new-secret-pass')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password' => 'Текущий пароль не подходит.']);

        $this->assertTrue(Hash::check('secret-pass', $this->user->fresh()->password));
        Mail::assertNothingSent();
    }

    public function test_the_new_password_is_validated(): void
    {
        $this->change('secret-pass', 'short')
            ->assertJsonValidationErrors(['password' => 'Пароль — хотя бы 8 символов.']);
        $this->change('secret-pass', 'secret-pass')
            ->assertJsonValidationErrors(['password' => 'Новый пароль совпадает с текущим.']);
    }

    public function test_guessing_the_current_password_is_rate_limited(): void
    {
        foreach (range(1, 5) as $_) {
            $this->change('not-mine', 'new-secret-pass')->assertUnprocessable();
        }

        $this->change('secret-pass', 'new-secret-pass')->assertTooManyRequests();
    }
}