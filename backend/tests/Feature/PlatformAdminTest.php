<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Админка сервиса», first iteration: superadmins see every board and every account, nobody else does.
 */
class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->create();
        $this->superadmin->forceFill(['is_superadmin' => true])->save();
    }

    public function test_lists_every_board_with_owner_counts_and_trash(): void
    {
        $owner = User::factory()->create(['email' => 'owner@acme.com']);
        $board = Project::factory()->create(['owner_id' => $owner->id]);
        $legacy = Project::factory()->create();
        $trashed = Project::factory()->create();
        $trashed->delete();

        $boards = collect($this->actingAs($this->superadmin)->getJson('/api/admin/platform/boards')->assertOk()->json('data'))->keyBy('slug');

        $this->assertCount(3, $boards);
        $this->assertSame('owner@acme.com', $boards[$board->slug]['owner']['email']);
        $this->assertSame(1, $boards[$board->slug]['members_count']);
        $this->assertNull($boards[$legacy->slug]['owner']);
        $this->assertNotNull($boards[$trashed->slug]['deleted_at']);
    }

    public function test_lists_every_account_with_its_boards_and_roles(): void
    {
        $board = Project::factory()->create();
        $member = User::factory()->unverified()->create(['email' => 'member@acme.com']);
        $board->members()->attach($member, ['role' => ProjectRole::Manager->value]);

        $users = collect($this->actingAs($this->superadmin)->getJson('/api/admin/platform/users')->assertOk()->json('data'))->keyBy('email');

        $this->assertCount(2, $users);
        $this->assertFalse($users['member@acme.com']['email_verified']);
        $this->assertSame([['name' => $board->name, 'slug' => $board->slug, 'role' => 'manager']], $users['member@acme.com']['boards']);
        $this->assertTrue($users[$this->superadmin->email]['is_superadmin']);
        $this->assertArrayNotHasKey('password', $users['member@acme.com']);
    }

    public function test_closed_to_everyone_else(): void
    {
        $owner = User::factory()->create();
        Project::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->getJson('/api/admin/platform/boards')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/admin/platform/users')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/admin/me')->assertJsonPath('data.is_superadmin', false);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/admin/platform/boards')->assertUnauthorized();
    }

    public function test_console_grants_and_revokes_the_rights(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->artisan('superadmin:grant', ['email' => 'Anna@Example.com'])->assertSuccessful();
        $this->assertTrue($user->refresh()->is_superadmin);
        $this->actingAs($user)->getJson('/api/admin/me')->assertJsonPath('data.is_superadmin', true);

        $this->artisan('superadmin:grant', ['email' => 'anna@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->refresh()->is_superadmin);

        $this->artisan('superadmin:grant', ['email' => 'nobody@example.com'])->assertFailed();
    }
}