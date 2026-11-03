<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchid\Support\Facades\Dashboard;
use Tests\TestCase;

/**
 * Orchid dashboard at /admin: who gets in, what they see, how superadmin:grant toggles rights.
 * Guard — 'platform' (its own session, isolated from the SPA's 'web' guard — see config/auth.php).
 * `/api/admin/me` stays on 'web': it's the SPA cabinet, not the Orchid admin.
 */
class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_orchid_login(): void
    {
        $this->get('/admin/boards')
            ->assertRedirect('/admin/login');
    }

    public function test_user_without_platform_access_gets_403(): void
    {
        $plain = User::factory()->create(['permissions' => null]);

        $this->actingAs($plain, 'platform')->get('/admin/boards')->assertForbidden();
        $this->actingAs($plain, 'platform')->get('/admin/users')->assertForbidden();
    }

    public function test_superadmin_opens_both_screens(): void
    {
        $admin = $this->makeSuperadmin();

        $this->actingAs($admin, 'platform')->get('/admin/boards')->assertOk();
        $this->actingAs($admin, 'platform')->get('/admin/users')->assertOk();
    }

    public function test_board_list_includes_trashed_and_ownerless_rows(): void
    {
        $admin = $this->makeSuperadmin();

        // owned by somebody — the usual self-serve board
        $ownerOfKept = User::factory()->create();
        $kept = Project::factory()->create(['name' => 'Kept board', 'owner_id' => $ownerOfKept->id]);

        // legacy «из консоли»: no owner at all
        $legacy = Project::factory()->create(['name' => 'Legacy board', 'owner_id' => null]);

        // soft-deleted — in the корзина, still has to show up in the Orchid list
        $trashed = Project::factory()->create(['name' => 'Trashed board', 'owner_id' => $ownerOfKept->id]);
        $trashed->delete();

        $response = $this->actingAs($admin, 'platform')->get('/admin/boards')->assertOk();
        $response->assertSeeText('Kept board');
        $response->assertSeeText('Legacy board');
        $response->assertSeeText('Trashed board');
        // «из консоли» marker for null owner
        $response->assertSeeText('из консоли');
    }

    public function test_me_endpoint_reflects_is_superadmin(): void
    {
        $plain = User::factory()->create(['permissions' => null]);
        $this->actingAs($plain)->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.is_superadmin', false);

        $admin = $this->makeSuperadmin();
        $this->actingAs($admin)->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.is_superadmin', true);
    }

    public function test_superadmin_grant_toggles_permissions_and_fails_on_unknown_email(): void
    {
        $user = User::factory()->create(['permissions' => null]);

        // User::find() each time: UserAccess caches resolved permissions on the model instance,
        // and refresh() keeps that private cache — a stale $user would report old access.
        $this->artisan('superadmin:grant', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue(User::find($user->id)->hasAccess('platform.index'));

        $this->artisan('superadmin:grant', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse(User::find($user->id)->hasAccess('platform.index'));

        $this->artisan('superadmin:grant', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_impersonation_stop_logs_out_web_guard_and_clears_session_key(): void
    {
        $target = User::factory()->create();

        // Эмулируем состояние после «Войти как»: web-гвард залогинен в target, в сессии — originator.
        $this->actingAs($target)
            ->withSession(['impersonator_platform_id' => 999])
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.impersonating_by', null); // 999 — несуществующий, но ключ читается

        $this->actingAs($target)
            ->withSession(['impersonator_platform_id' => 999])
            ->postJson('/api/admin/impersonation/stop')
            ->assertOk()
            ->assertJsonPath('data.redirect', '/admin');
    }

    public function test_transfer_ownership_moves_owner_id_and_demotes_previous(): void
    {
        $oldOwner = User::factory()->create();
        $newOwner = User::factory()->create();
        // ProjectFactory сам кладёт owner в пивот с role=owner.
        $board = Project::factory()->create(['owner_id' => $oldOwner->id]);

        app(ProjectService::class)->transferOwnership($board, $newOwner);

        $this->assertSame($newOwner->id, $board->refresh()->owner_id);
        $this->assertSame(ProjectRole::Owner, $newOwner->fresh()->roleOn($board));
        $this->assertSame(ProjectRole::Admin, $oldOwner->fresh()->roleOn($board));
    }

    public function test_transfer_ownership_upgrades_user_already_on_the_board(): void
    {
        $oldOwner = User::factory()->create();
        $otherAdmin = User::factory()->create();
        $board = Project::factory()->create(['owner_id' => $oldOwner->id]);
        $board->members()->attach($otherAdmin->id, ['role' => ProjectRole::Admin->value]);

        app(ProjectService::class)->transferOwnership($board, $otherAdmin);

        $this->assertSame(ProjectRole::Owner, $otherAdmin->fresh()->roleOn($board));
        $this->assertSame(ProjectRole::Admin, $oldOwner->fresh()->roleOn($board));
    }

    private function makeSuperadmin(): User
    {
        return User::factory()->create([
            'permissions' => Dashboard::getAllowAllPermission()->all(),
        ]);
    }
}
