<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** 5 wrong passwords in 10 minutes → password login for the address is locked for 15 minutes (docs/ROLES_PLAN.md, stage 2). */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['email' => 'anna@example.com', 'password' => 'secret-pass']);
    }

    /** Requests from the cabinet's origin get a Sanctum session, like in the browser. */
    private function fromCabinet(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/app/login']);
    }

    private function login(string $email, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->fromCabinet()->postJson('/api/admin/login', ['email' => $email, 'password' => $password]);
    }

    /** Wrong passwords a minute apart, so the per-IP limit (5 a minute) stays out of the way. */
    private function failTimes(int $times, string $email = 'anna@example.com'): void
    {
        foreach (range(1, $times) as $_) {
            $this->login($email, 'wrong-pass')->assertJsonValidationErrors(['email' => 'Неверный email или пароль.']);
            $this->travel(61)->seconds();
        }
    }

    public function test_five_wrong_passwords_lock_even_the_right_one(): void
    {
        $this->failTimes(5);

        $this->login('anna@example.com', 'secret-pass')
            ->assertTooManyRequests()
            ->assertJsonValidationErrors(['password']);
        $this->assertGuest('web');
    }

    public function test_four_wrong_passwords_still_let_the_right_one_in(): void
    {
        $this->failTimes(4);

        $this->login('anna@example.com', 'secret-pass')->assertOk();
    }

    public function test_the_lock_is_per_address_and_case_insensitive(): void
    {
        User::factory()->create(['email' => 'boris@example.com', 'password' => 'secret-pass']);
        $this->failTimes(5);

        $this->login(' Anna@Example.com ', 'secret-pass')->assertTooManyRequests();
        $this->login('boris@example.com', 'secret-pass')->assertOk();
    }

    public function test_the_lock_lifts_after_fifteen_minutes(): void
    {
        $this->failTimes(5); // the lock started a minute ago: failTimes waits after each try

        $this->travel(13)->minutes();
        $this->login('anna@example.com', 'secret-pass')->assertTooManyRequests();

        $this->travel(1)->minutes();
        $this->login('anna@example.com', 'secret-pass')->assertOk();
    }

    public function test_failures_older_than_ten_minutes_dont_count(): void
    {
        $this->failTimes(4);
        $this->travel(11)->minutes();
        $this->failTimes(1);

        $this->login('anna@example.com', 'secret-pass')->assertOk();
    }

    public function test_a_successful_login_starts_the_count_over(): void
    {
        $this->failTimes(4);
        $this->login('anna@example.com', 'secret-pass')->assertOk();
        $this->fromCabinet()->postJson('/api/admin/logout')->assertNoContent();
        $this->travel(61)->seconds();
        $this->failTimes(1);

        $this->login('anna@example.com', 'secret-pass')->assertOk();
    }

    public function test_an_unknown_address_locks_the_same_way(): void
    {
        $this->failTimes(5, 'nobody@example.com');

        $this->login('nobody@example.com', 'whatever')->assertTooManyRequests();
    }

    public function test_a_password_reset_lifts_the_lock(): void
    {
        Notification::fake();
        $this->failTimes(5);

        $this->postJson('/api/admin/forgot-password', ['email' => 'anna@example.com'])->assertNoContent();
        $token = null;
        Notification::assertSentTo(User::firstWhere('email', 'anna@example.com'), ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->fromCabinet()->postJson('/api/admin/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'new-secret-pass'])
            ->assertOk();
        $this->fromCabinet()->postJson('/api/admin/logout')->assertNoContent();

        $this->login('anna@example.com', 'new-secret-pass')->assertOk();
    }
}