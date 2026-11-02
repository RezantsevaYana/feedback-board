<?php

namespace Tests\Feature;

use App\Enums\BoardVisibility;
use App\Enums\ProjectRole;
use App\Mail\InvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Slice 2 of docs/ROLES_PLAN.md — bulk invite + accept flow.
 */
class InvitationsTest extends TestCase
{
    use RefreshDatabase;

    private Project $board;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['email' => 'owner@acme.com']);
        $this->board = Project::factory()->create([
            'owner_id' => $this->owner->id,
            'slug' => 'acme-feedback',
            // every role is invited only to a closed board; the open one has just a team (OpenBoardTeamTest)
            'visibility' => BoardVisibility::Private,
        ]);
    }

    public function test_bulk_invite_gives_everyone_the_batch_role(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/admin/boards/{$this->board->slug}/invitations", [
                'role' => 'manager',
                'invitees' => [
                    ['email' => 'colleague@acme.com'],
                    ['email' => 'client@othercorp.com'],
                ],
            ])
            ->assertStatus(201);

        $this->assertEqualsCanonicalizing(['manager', 'manager'], collect($response->json('data'))->pluck('role')->all());
    }

    public function test_bulk_invite_sends_one_letter_per_invitation(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/admin/boards/{$this->board->slug}/invitations", [
                'role' => 'voter',
                'invitees' => [['email' => 'a@acme.com'], ['email' => 'b@acme.com']],
            ])
            ->assertStatus(201);

        Mail::assertSent(InvitationMail::class, 2);
    }

    public function test_bulk_invite_skips_existing_members(): void
    {
        $member = User::factory()->create(['email' => 'already@acme.com']);
        $this->board->members()->attach($member, ['role' => ProjectRole::Voter->value]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/admin/boards/{$this->board->slug}/invitations", [
                'role' => 'manager',
                'invitees' => [['email' => 'already@acme.com'], ['email' => 'new@acme.com']],
            ])
            ->assertStatus(201);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('already@acme.com', $response->json('skipped.0.email'));
        $this->assertSame('already_member', $response->json('skipped.0.reason'));
    }

    public function test_second_invite_for_same_email_updates_the_pending_slot(): void
    {
        $this->actingAs($this->owner)->postJson("/api/admin/boards/{$this->board->slug}/invitations", [
            'role' => 'voter',
            'invitees' => [['email' => 'twice@acme.com']],
        ])->assertStatus(201);

        $this->actingAs($this->owner)->postJson("/api/admin/boards/{$this->board->slug}/invitations", [
            'role' => 'manager',
            'invitees' => [['email' => 'twice@acme.com']],
        ])->assertStatus(201);

        $this->assertSame(1, ProjectInvitation::where('email', 'twice@acme.com')->count());
        $this->assertSame('manager', \Illuminate\Support\Facades\DB::table('project_invitations')
            ->where('email', 'twice@acme.com')->value('role'));
    }

    public function test_dedupe_same_email_in_one_batch(): void
    {
        $response = $this->actingAs($this->owner)->postJson("/api/admin/boards/{$this->board->slug}/invitations", [
            'role' => 'reader',
            'invitees' => [['email' => 'dup@acme.com'], ['email' => 'dup@acme.com']],
        ])->assertStatus(201);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_cannot_bulk_invite_as_manager(): void
    {
        $manager = User::factory()->create();
        $this->board->members()->attach($manager, ['role' => ProjectRole::Manager->value]);

        $this->actingAs($manager)->postJson("/api/admin/boards/{$this->board->slug}/invitations", [
            'role' => 'reader',
            'invitees' => [['email' => 'who@acme.com']],
        ])->assertForbidden();
    }

    public function test_resend_rotates_token_and_mails_again(): void
    {
        $invitation = $this->makeInvite(['email' => 'r@acme.com']);
        $oldToken = $invitation->token;

        $this->actingAs($this->owner)
            ->postJson("/api/admin/boards/{$this->board->slug}/invitations/{$invitation->id}/resend")
            ->assertNoContent();

        $this->assertNotSame($oldToken, $invitation->fresh()->token);
        Mail::assertSent(InvitationMail::class, 1);
    }

    public function test_cancel_removes_invitation(): void
    {
        $invitation = $this->makeInvite(['email' => 'c@acme.com']);

        $this->actingAs($this->owner)
            ->deleteJson("/api/admin/boards/{$this->board->slug}/invitations/{$invitation->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_invitations', ['id' => $invitation->id]);
    }

    public function test_show_returns_metadata_without_token(): void
    {
        $invitation = $this->makeInvite(['email' => 's@acme.com', 'role' => ProjectRole::Manager->value]);

        $response = $this->getJson("/api/admin/invitations/{$invitation->token}")->assertOk();
        $this->assertSame('s@acme.com', $response->json('data.email'));
        $this->assertSame('manager', $response->json('data.role'));
        $this->assertSame($this->board->slug, $response->json('data.board.slug'));
        $this->assertSame($this->owner->name, $response->json('data.invited_by.name'));
    }

    public function test_show_410_for_expired_invitation(): void
    {
        $invitation = $this->makeInvite(['email' => 'old@acme.com']);
        $invitation->update(['expires_at' => now()->subDay()]);

        $this->getJson("/api/admin/invitations/{$invitation->token}")->assertStatus(410);
    }

    public function test_accept_as_new_user_creates_account_and_pivot(): void
    {
        $invitation = $this->makeInvite(['email' => 'new@acme.com', 'role' => ProjectRole::Voter->value]);

        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", [
            'name' => 'Новичок',
            'password' => 'strong-pass-123',
        ])->assertOk();

        $user = User::where('email', 'new@acme.com')->firstOrFail();
        $this->assertSame('Новичок', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $this->board->id,
            'user_id' => $user->id,
            'role' => 'voter',
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_accept_without_name_for_new_user_is_422(): void
    {
        $invitation = $this->makeInvite(['email' => 'anon@acme.com']);

        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", [])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Введите имя.');
    }

    public function test_accept_without_password_for_new_user_is_422(): void
    {
        $invitation = $this->makeInvite(['email' => 'nopass@acme.com']);

        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", ['name' => 'Без пароля'])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Придумайте пароль.');

        $this->assertDatabaseMissing('users', ['email' => 'nopass@acme.com']);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_accept_as_logged_in_matching_user_just_adds_pivot(): void
    {
        $user = User::factory()->create(['email' => 'already@acme.com', 'name' => 'Уже тут']);
        $invitation = $this->makeInvite(['email' => 'already@acme.com', 'role' => ProjectRole::Reader->value]);

        $this->actingAs($user)
            ->postJson("/api/admin/invitations/{$invitation->token}/accept")
            ->assertOk();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $this->board->id,
            'user_id' => $user->id,
            'role' => 'reader',
        ]);
    }

    public function test_accept_409_when_account_exists_but_not_logged_in(): void
    {
        User::factory()->create(['email' => 'existing@acme.com']);
        $invitation = $this->makeInvite(['email' => 'existing@acme.com']);

        $this->postJson("/api/admin/invitations/{$invitation->token}/accept")
            ->assertStatus(409)
            ->assertJsonPath('action', 'login_required');
    }

    public function test_accept_410_after_first_accept(): void
    {
        $invitation = $this->makeInvite(['email' => 'once@acme.com']);
        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", ['name' => 'X', 'password' => 'strong-pass-123'])->assertOk();
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", ['name' => 'Y'])
            ->assertStatus(410);
    }

    private function makeInvite(array $attrs): ProjectInvitation
    {
        return ProjectInvitation::create(array_merge([
            'project_id' => $this->board->id,
            'email' => 'default@acme.com',
            'role' => ProjectRole::Voter->value,
            'token' => ProjectInvitation::newToken(),
            'expires_at' => ProjectInvitation::defaultExpiry(),
            'invited_by' => $this->owner->id,
        ], $attrs));
    }
}
