<?php

namespace Tests\Feature;

use App\Mail\NoAccountMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /** Requests from the cabinet's origin get a Sanctum session, like in the browser. */
    private function fromCabinet(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/app/reset-password']);
    }

    private function letter(User $user): ResetPassword
    {
        $letter = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$letter) {
            $letter = $n;

            return true;
        });

        return $letter;
    }

    public function test_the_letter_links_to_the_spa_page(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->postJson('/api/admin/forgot-password', ['email' => ' Anna@Example.com '])->assertNoContent();

        $letter = $this->letter($user);
        $this->assertSame(
            'http://localhost:5173/app/reset-password/'.$letter->token.'?email=anna%40example.com',
            $letter->toMail($user)->viewData['url'],
        );
    }

    public function test_an_unknown_address_gets_the_same_answer_and_a_no_account_letter(): void
    {
        Notification::fake();
        Mail::fake();

        $this->postJson('/api/admin/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();

        Notification::assertNothingSent();
        Mail::assertSent(NoAccountMail::class, fn (NoAccountMail $mail) => $mail->hasTo('nobody@example.com'));
    }

    public function test_the_no_account_letter_goes_once_an_hour(): void
    {
        Mail::fake();

        $this->postJson('/api/admin/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();
        $this->postJson('/api/admin/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();
        Mail::assertSentCount(1);

        $this->travel(61)->minutes();
        $this->postJson('/api/admin/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();
        Mail::assertSentCount(2);
    }

    public function test_a_known_address_gets_no_no_account_letter(): void
    {
        Notification::fake();
        Mail::fake();
        User::factory()->create(['email' => 'anna@example.com']);

        $this->postJson('/api/admin/forgot-password', ['email' => 'anna@example.com'])->assertNoContent();

        Mail::assertNothingSent();
    }

    public function test_the_email_is_validated(): void
    {
        $this->postJson('/api/admin/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Введите email в формате name@example.com.']);
    }

    public function test_a_new_password_logs_in_and_confirms_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'anna@example.com']);
        $this->postJson('/api/admin/forgot-password', ['email' => 'anna@example.com']);
        $token = $this->letter($user)->token;

        $this->fromCabinet()->postJson('/api/admin/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'new-secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email_verified', true);

        $this->assertTrue(Hash::check('new-secret-pass', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_link_works_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $this->postJson('/api/admin/forgot-password', ['email' => 'anna@example.com']);
        $token = $this->letter($user)->token;

        $this->fromCabinet()->postJson('/api/admin/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'new-secret-pass'])->assertOk();

        $this->postJson('/api/admin/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'another-pass'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
        $this->assertTrue(Hash::check('new-secret-pass', $user->fresh()->password));
    }

    public function test_the_page_learns_on_open_that_the_link_is_used(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $this->postJson('/api/admin/forgot-password', ['email' => 'anna@example.com']);
        $token = $this->letter($user)->token;

        $this->postJson('/api/admin/reset-password/check', ['token' => $token, 'email' => 'anna@example.com'])->assertNoContent();

        $this->fromCabinet()->postJson('/api/admin/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'new-secret-pass'])->assertOk();

        $this->postJson('/api/admin/reset-password/check', ['token' => $token, 'email' => 'anna@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
        $this->postJson('/api/admin/reset-password/check', ['token' => $token, 'email' => 'nobody@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_a_stale_token_is_refused(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $this->postJson('/api/admin/forgot-password', ['email' => 'anna@example.com']);
        $token = $this->letter($user)->token;

        $this->travel(61)->minutes();

        $this->postJson('/api/admin/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'new-secret-pass'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->postJson('/api/admin/reset-password', ['token' => 'x', 'email' => 'anna@example.com', 'password' => 'short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Пароль — хотя бы 8 символов.']);
    }
}