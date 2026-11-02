<?php

namespace Tests\Feature;

use App\Enums\BoardVisibility;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An open board has only a team (02.11.2026, docs/ROLES_FEEDBACK_PLAN.md): anyone by the link reads, votes and
 * posts, so Участник / Читатель aren't invited there, aren't listed, and pending invitations with those roles
 * go when a closed board opens. The pivot rows stay, for when it's closed again.
 */
class OpenBoardTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create();
        $this->board = Project::factory()->create(['owner_id' => $this->owner->id, 'slug' => 'open']);
    }

    private function invite(string $role, string $email = 'anna@acme.com')
    {
        return $this->actingAs($this->owner)->postJson('/api/admin/boards/open/invitations', [
            'role' => $role,
            'invitees' => [['email' => $email]],
        ]);
    }

    public function test_an_open_board_invites_only_to_the_team(): void
    {
        $this->invite('manager')->assertCreated();
        $this->invite('admin', 'boris@acme.com')->assertCreated();

        $this->invite('voter', 'client@othercorp.com')->assertUnprocessable()->assertJsonValidationErrors(['role']);
        $this->invite('reader', 'client@othercorp.com')->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_a_closed_board_invites_every_role(): void
    {
        $this->board->update(['visibility' => BoardVisibility::Private]);

        $this->invite('voter')->assertCreated();
        $this->invite('reader', 'boris@acme.com')->assertCreated();
    }

    public function test_an_open_board_lists_only_the_team(): void
    {
        $manager = User::factory()->create(['name' => 'Менеджер']);
        $voter = User::factory()->create(['name' => 'Участник']);
        $this->board->members()->attach($manager->id, ['role' => ProjectRole::Manager->value]);
        $this->board->members()->attach($voter->id, ['role' => ProjectRole::Voter->value]);

        $ids = collect($this->actingAs($this->owner)->getJson('/api/admin/boards/open/participants')->assertOk()->json('data.members'))
            ->pluck('user_id');

        $this->assertTrue($ids->contains($manager->id));
        $this->assertFalse($ids->contains($voter->id));
        // still on the board, so closing it again brings them back
        $this->assertSame(ProjectRole::Voter, $voter->roleOn($this->board));
    }

    public function test_a_team_member_cant_be_moved_to_a_guest_role_on_an_open_board(): void
    {
        $manager = User::factory()->create();
        $this->board->members()->attach($manager->id, ['role' => ProjectRole::Manager->value]);

        $this->actingAs($this->owner)
            ->patchJson("/api/admin/boards/open/participants/{$manager->id}", ['role' => 'voter'])
            ->assertUnprocessable();
    }

    public function test_opening_a_board_cancels_guest_role_invitations(): void
    {
        $this->board->update(['visibility' => BoardVisibility::Private]);
        $this->invite('voter')->assertCreated();
        $this->invite('manager', 'boris@acme.com')->assertCreated();

        $this->actingAs($this->owner)->patchJson('/api/admin/boards/open', ['visibility' => 'public'])->assertOk();

        $this->assertSame(['boris@acme.com'], ProjectInvitation::pluck('email')->all());
    }

    public function test_me_says_whether_each_board_is_open(): void
    {
        $this->actingAs($this->owner)->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.projects.0.visibility', 'public');
    }
}