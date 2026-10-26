<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** Requests from the cabinet's origin get a Sanctum session, like in the browser. */
    private function fromCabinet(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/app/register']);
    }

    /** The SPA page from the letter posts its own path and query to the API — the same here. */
    private function apiPath(string $letterUrl): string
    {
        return '/api/admin/email/verify'.Str::after($letterUrl, '/app/verify-email');
    }

    private function letterUrl(User $user): string
    {
        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user, &$url) {
            $url = $n->toMail($user)->viewData['url'];

            return true;
        });

        return $url;
    }

    public function test_registration_sends_the_letter_and_leaves_the_account_unconfirmed(): void
    {
        Notification::fake();

        $this->fromCabinet()->postJson('/api/admin/register', ['name' => 'Анна', 'email' => 'anna@example.com', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);

        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $this->assertStringStartsWith('http://localhost:5173/app/verify-email/'.$user->id.'/', $this->letterUrl($user));
    }

    public function test_the_link_confirms_the_email_without_a_session(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        $this->postJson($this->apiPath($this->letterUrl($user)))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->getJson('/api/admin/me')->assertJsonPath('data.email_verified', true);
    }

    public function test_a_tampered_or_expired_link_is_refused(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $path = $this->apiPath($this->letterUrl($user));

        // someone else's id under the same signature
        $this->postJson(str_replace("/verify/{$user->id}/", "/verify/{$other->id}/", $path))->assertForbidden();

        $this->travel(25)->hours();
        $this->postJson($path)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_goes_only_to_an_unconfirmed_address(): void
    {
        Notification::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $this->actingAs($unverified)->postJson('/api/admin/email/verification-notification')->assertNoContent();
        $this->actingAs($verified)->postJson('/api/admin/email/verification-notification')->assertNoContent();

        Notification::assertSentTo($unverified, VerifyEmail::class);
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_guest_cannot_ask_for_a_letter(): void
    {
        $this->postJson('/api/admin/email/verification-notification')->assertUnauthorized();
    }
}