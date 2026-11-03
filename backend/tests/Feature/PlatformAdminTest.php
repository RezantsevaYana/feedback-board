<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
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

    private function makeSuperadmin(): User
    {
        return User::factory()->create([
            'permissions' => Dashboard::getAllowAllPermission()->all(),
        ]);
    }
}
