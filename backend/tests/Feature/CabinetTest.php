<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cabinet of slice 1 (docs/ROLES_PLAN.md): every board I'm on with my role, invitations
 * addressed to me, where each participant came from.
 */
class CabinetTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $me;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'owner@acme.com']);
        $this->board = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->me = User::factory()->create(['email' => 'Me@Client.com']);
    }

    public function test_hub_lists_boards_i_take_part_in_with_my_role(): void
    {
        $mine = Project::factory()->create(['owner_id' => $this->me->id]);
        $this->board->members()->attach($this->me, ['role' => ProjectRole::Voter->value]);

        $boards = collect($this->actingAs($this->me)->getJson('/api/admin/boards')->assertOk()->json('data'))->keyBy('slug');

        $this->assertSame('owner', $boards[$mine->slug]['role']);
        $this->assertSame('voter', $boards[$this->board->slug]['role']);
        $this->assertSame(2, $boards[$this->board->slug]['members_count']);
    }

    public function test_incoming_invitations_are_listed_and_accepted(): void
    {
        $invitation = $this->invite('me@client.com');
        $this->invite('someone@else.com');

        $list = $this->actingAs($this->me)->getJson('/api/admin/me/invitations')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertSame($this->board->slug, $list[0]['board']['slug']);
        $this->assertArrayNotHasKey('token', $list[0]);

        $this->actingAs($this->me)
            ->postJson("/api/admin/me/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.role', 'manager');

        $this->assertSame(ProjectRole::Manager, $this->me->roleOn($this->board));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->actingAs($this->me)->getJson('/api/admin/me/invitations')->assertJsonCount(0, 'data');
    }

    public function test_declining_removes_the_invitation(): void
    {
        $invitation = $this->invite('me@client.com');

        $this->actingAs($this->me)->deleteJson("/api/admin/me/invitations/{$invitation->id}")->assertNoContent();

        $this->assertModelMissing($invitation);
        $this->assertNull($this->me->roleOn($this->board));
    }

    public function test_someone_elses_invitation_is_404(): void
    {
        $invitation = $this->invite('someone@else.com');

        $this->actingAs($this->me)->postJson("/api/admin/me/invitations/{$invitation->id}/accept")->assertNotFound();
        $this->actingAs($this->me)->deleteJson("/api/admin/me/invitations/{$invitation->id}")->assertNotFound();
    }

    public function test_unconfirmed_email_sees_no_invitations(): void
    {
        $this->me->forceFill(['email_verified_at' => null])->save();
        $invitation = $this->invite('me@client.com');

        $this->actingAs($this->me)->getJson('/api/admin/me/invitations')->assertJsonCount(0, 'data');
        $this->actingAs($this->me)->postJson("/api/admin/me/invitations/{$invitation->id}/accept")->assertNotFound();
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $invitation = $this->invite('me@client.com', ['expires_at' => now()->subDay()]);

        $this->actingAs($this->me)->postJson("/api/admin/me/invitations/{$invitation->id}/accept")->assertStatus(410);
    }

    public function test_participants_show_where_each_person_came_from(): void
    {
        $this->invite('me@client.com')->acceptFor($this->me);
        $manual = User::factory()->create();
        $this->board->members()->attach($manual, ['role' => ProjectRole::Admin->value]);

        $response = $this->actingAs($this->owner)->getJson("/api/admin/boards/{$this->board->slug}/participants")->assertOk();
        $sources = collect($response->json('data.members'))->pluck('source', 'user_id');

        $this->assertSame('created', $sources[$this->owner->id]);
        $this->assertSame('invitation', $sources[$this->me->id]);
        $this->assertSame('manual', $sources[$manual->id]);
    }


    private function invite(string $email, array $attrs = []): ProjectInvitation
    {
        return ProjectInvitation::create(array_merge([
            'project_id' => $this->board->id,
            'email' => $email,
            'role' => ProjectRole::Manager->value,
            'token' => ProjectInvitation::newToken(),
            'expires_at' => ProjectInvitation::defaultExpiry(),
            'invited_by' => $this->owner->id,
        ], $attrs));
    }
}