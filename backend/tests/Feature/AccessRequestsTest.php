<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\BoardVisibility;
use App\Enums\ProjectRole;
use App\Mail\AccessApprovedMail;
use App\Mail\AccessRejectedMail;
use App\Mail\AccessRequestedMail;
use App\Models\AccessRequest;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Stage 3 of docs/ROLES_PLAN.md: «Запросить доступ» to a private board and the admins' decision. */
class AccessRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = User::factory()->create(['email' => 'owner@acme.com']);
        $this->admin = User::factory()->create(['email' => 'admin@acme.com']);
        $this->board = Project::factory()->create([
            'name' => 'Клиентский проект',
            'slug' => 'closed',
            'owner_id' => $this->owner->id,
            'visibility' => BoardVisibility::Private,
        ]);
        $this->board->members()->attach($this->admin, ['role' => ProjectRole::Admin->value]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function ask(array $overrides = [], string $slug = 'closed'): TestResponse
    {
        return $this->postJson("/api/public/projects/{$slug}/access-requests", $overrides + [
            'name' => 'Клиент',
            'email' => 'client@external.com',
            'message' => 'Веду интеграцию с вашей стороны',
        ]);
    }

    private function request(): AccessRequest
    {
        return $this->board->accessRequests()->firstOrFail();
    }

    // --- the request ---

    public function test_a_guest_asks_and_every_owner_and_admin_gets_a_letter(): void
    {
        $this->ask(['email' => ' Client@External.com '])
            ->assertStatus(202)
            ->assertJsonPath('data.email', 'client@external.com');

        $request = $this->request();
        $this->assertSame(AccessRequestStatus::Pending, $request->status);
        $this->assertSame('Веду интеграцию с вашей стороны', $request->message);
        Mail::assertSent(AccessRequestedMail::class, 2);
        Mail::assertSent(AccessRequestedMail::class, fn (AccessRequestedMail $m) => $m->hasTo('owner@acme.com'));
        Mail::assertSent(AccessRequestedMail::class, fn (AccessRequestedMail $m) => $m->hasTo('admin@acme.com'));
    }

    public function test_a_repeat_while_pending_updates_the_same_request_quietly(): void
    {
        $this->ask()->assertStatus(202);
        $this->ask(['message' => 'Напоминаю'])->assertStatus(202);

        $this->assertSame(1, $this->board->accessRequests()->count());
        $this->assertSame('Напоминаю', $this->request()->message);
        Mail::assertSent(AccessRequestedMail::class, 2); // the first request only, one letter per admin
    }

    public function test_a_logged_in_visitor_asks_as_their_account(): void
    {
        $user = User::factory()->create(['name' => 'Борис', 'email' => 'boris@external.com']);

        $this->actingAs($user)->ask(['name' => 'Кто угодно', 'email' => 'other@example.com'])
            ->assertStatus(202)
            ->assertJsonPath('data.email', 'boris@external.com');

        $request = $this->request();
        $this->assertSame('boris@external.com', $request->email);
        $this->assertSame('Борис', $request->name);
        $this->assertSame($user->id, $request->user_id);
    }

    public function test_the_answer_is_the_same_when_nothing_is_recorded(): void
    {
        Project::factory()->create(['slug' => 'open', 'owner_id' => $this->owner->id]);

        $this->ask(['email' => 'admin@acme.com'])->assertStatus(202);          // already a member
        $this->ask([], 'open')->assertStatus(202);                             // a public board has nothing to ask for

        $this->assertSame(0, AccessRequest::count());
        Mail::assertNothingSent();
    }

    public function test_the_form_is_validated(): void
    {
        $this->ask(['name' => '', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_requests_are_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->ask(['email' => "client{$i}@external.com"])->assertStatus(202);
        }
        $this->ask(['email' => 'client6@external.com'])->assertTooManyRequests();
    }

    public function test_the_gate_says_which_board_it_is(): void
    {
        $this->getJson('/api/public/projects/closed')
            ->assertStatus(403)
            ->assertJsonPath('board.name', 'Клиентский проект');
    }

    // --- the decision ---

    public function test_admins_see_pending_requests_with_the_participants(): void
    {
        $this->ask();

        $this->actingAs($this->admin)->getJson('/api/admin/boards/closed/participants')
            ->assertOk()
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath('data.requests.0.email', 'client@external.com')
            ->assertJsonPath('data.requests.0.message', 'Веду интеграцию с вашей стороны');
    }

    public function test_approval_issues_an_invitation_with_the_role_and_a_letter(): void
    {
        $this->ask();
        $request = $this->request();

        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$request->id}/approve", ['role' => 'voter'])
            ->assertOk()
            ->assertJsonPath('data.email', 'client@external.com')
            ->assertJsonPath('data.role', 'voter');

        $request->refresh();
        $this->assertSame(AccessRequestStatus::Approved, $request->status);
        $this->assertSame(ProjectRole::Voter, $request->role_on_approve);
        $this->assertSame($this->admin->id, $request->decided_by);

        $invitation = $this->board->invitations()->pending()->where('email', 'client@external.com')->firstOrFail();
        Mail::assertSent(AccessApprovedMail::class, fn (AccessApprovedMail $m) => $m->hasTo('client@external.com')
            && str_contains($m->render(), '/app/invite/'.$invitation->token));
    }

    public function test_after_accepting_the_participant_reads_as_came_by_request(): void
    {
        $this->ask();
        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/approve", ['role' => 'reader']);
        $token = $this->board->invitations()->pending()->firstOrFail()->token;
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/admin/invitations/{$token}/accept", ['name' => 'Клиент', 'password' => 'secret-pass'])->assertOk();

        $this->actingAs($this->admin)->getJson('/api/admin/boards/closed/participants')
            ->assertJsonFragment(['email' => 'client@external.com', 'role' => 'reader', 'source' => 'request'])
            ->assertJsonCount(0, 'data.requests');
    }

    public function test_verified_authenticated_applicant_gets_access_immediately_after_approval(): void
    {
        $user = User::factory()->create(['email' => 'client@external.com']);
        $this->actingAs($user)->ask()->assertStatus(202);
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/approve", ['role' => 'reader'])
            ->assertOk()->assertJsonPath('data.is_pending', false);

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->getJson('/api/public/projects/closed')->assertOk();
        $this->assertSame(ProjectRole::Reader, $user->roleOn($this->board));
        Mail::assertSent(AccessApprovedMail::class, fn (AccessApprovedMail $mail) => str_contains($mail->render(), '/closed') && ! str_contains($mail->render(), '/app/invite/'));
    }

    public function test_guest_cannot_get_existing_account_added_without_email_proof(): void
    {
        $user = User::factory()->create(['email' => 'client@external.com']);
        $this->ask()->assertStatus(202);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/approve", ['role' => 'voter'])
            ->assertOk()->assertJsonPath('data.is_pending', true);

        $this->assertNull($user->roleOn($this->board));
    }

    public function test_unverified_authenticated_applicant_still_needs_invitation_link(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'client@external.com']);
        $this->actingAs($user)->ask()->assertStatus(202);
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/approve", ['role' => 'voter'])
            ->assertOk()->assertJsonPath('data.is_pending', true);

        $this->assertNull($user->roleOn($this->board));
    }

    public function test_inviting_applicant_resolves_the_pending_request(): void
    {
        $this->ask()->assertStatus(202);
        $this->actingAs($this->admin)->postJson('/api/admin/boards/closed/invitations', [
            'role' => 'reader', 'invitees' => [['email' => 'client@external.com']],
        ])->assertCreated();

        $this->getJson('/api/admin/boards/closed/participants')->assertOk()
            ->assertJsonCount(0, 'data.requests')->assertJsonCount(1, 'data.invitations');
        $this->assertSame(AccessRequestStatus::Approved, $this->request()->status);
    }

    public function test_invited_person_does_not_create_a_second_pending_request(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/boards/closed/invitations', [
            'role' => 'voter', 'invitees' => [['email' => 'client@external.com']],
        ])->assertCreated();
        $this->app['auth']->forgetGuards();

        $this->ask()->assertStatus(202);
        $this->assertSame(0, $this->board->accessRequests()->pending()->count());
        Mail::assertNotSent(AccessRequestedMail::class);
    }

    public function test_legacy_invitation_and_request_overlap_is_hidden_from_list_and_badge(): void
    {
        $this->ask()->assertStatus(202);
        $invitation = $this->board->invitations()->create([
            'email' => 'client@external.com',
            'role' => ProjectRole::Reader,
            'token' => ProjectInvitation::newToken(),
            'expires_at' => ProjectInvitation::defaultExpiry(),
            'invited_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->getJson('/api/admin/boards/closed/participants')
            ->assertOk()->assertJsonCount(0, 'data.requests')->assertJsonCount(1, 'data.invitations');
        $this->getJson('/api/admin/me')->assertOk()->assertJsonPath('data.projects.0.pending_requests', 0);
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", [
            'name' => 'Клиент', 'password' => 'secret-pass',
        ])->assertOk();
        $this->assertSame(AccessRequestStatus::Approved, $this->request()->status);
    }

    public function test_rejection_sends_the_reason_without_who_decided(): void
    {
        $this->ask();

        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/reject", ['reason' => 'Доска только для команды проекта'])
            ->assertNoContent();

        $this->assertSame(AccessRequestStatus::Rejected, $this->request()->status);
        Mail::assertSent(AccessRejectedMail::class, function (AccessRejectedMail $m) {
            $html = $m->render();

            return $m->hasTo('client@external.com')
                && str_contains($html, 'Доска только для команды проекта')
                && ! str_contains($html, $this->admin->name);
        });
        $this->assertSame(0, $this->board->invitations()->count());
    }

    public function test_after_a_rejection_the_address_waits_a_day_silently(): void
    {
        $this->ask();
        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/reject")->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->travel(23)->hours();
        $this->ask(['email' => 'CLIENT@external.com', 'message' => 'Ещё раз'])->assertStatus(202);

        $this->assertSame(0, $this->board->accessRequests()->pending()->count());
        Mail::assertSent(AccessRequestedMail::class, 2); // still only the first request's letters
    }

    public function test_a_day_after_a_rejection_the_person_may_ask_again(): void
    {
        $this->ask();
        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/reject");
        $this->app['auth']->forgetGuards();

        $this->travel(25)->hours();
        $this->ask(['message' => 'Уточнил задачу'])->assertStatus(202);

        $this->assertSame(1, $this->board->accessRequests()->pending()->count());
        Mail::assertSent(AccessRequestedMail::class, 4);
    }

    public function test_the_pause_is_per_board(): void
    {
        $this->ask();
        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/reject");
        $this->app['auth']->forgetGuards();
        $other = Project::factory()->create([
            'slug' => 'closed-too',
            'owner_id' => $this->owner->id,
            'visibility' => BoardVisibility::Private,
        ]);

        $this->ask([], 'closed-too')->assertStatus(202);

        $this->assertSame(1, $other->accessRequests()->pending()->count());
    }

    public function test_a_decided_request_cant_be_decided_again(): void
    {
        $this->ask();
        $id = $this->request()->id;
        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$id}/reject")->assertNoContent();

        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$id}/approve", ['role' => 'voter'])
            ->assertUnprocessable();
    }

    public function test_owner_is_not_an_approvable_role(): void
    {
        $this->ask();

        $this->actingAs($this->admin)->postJson("/api/admin/boards/closed/access-requests/{$this->request()->id}/approve", ['role' => 'owner'])
            ->assertJsonValidationErrors(['role']);
    }

    public function test_managers_and_strangers_cannot_decide(): void
    {
        $this->ask();
        $id = $this->request()->id;
        $manager = User::factory()->create();
        $this->board->members()->attach($manager, ['role' => ProjectRole::Manager->value]);

        $this->actingAs($manager)->postJson("/api/admin/boards/closed/access-requests/{$id}/approve", ['role' => 'voter'])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson("/api/admin/boards/closed/access-requests/{$id}/reject")->assertForbidden();
    }

    public function test_a_request_of_another_board_is_404(): void
    {
        $this->ask();
        $other = Project::factory()->create(['slug' => 'other', 'owner_id' => $this->admin->id]);

        $this->actingAs($this->admin)->postJson("/api/admin/boards/{$other->slug}/access-requests/{$this->request()->id}/approve", ['role' => 'voter'])
            ->assertNotFound();
    }

    public function test_opening_a_board_hides_pending_requests_and_counts_until_closed_again(): void
    {
        $this->ask()->assertStatus(202);
        $this->actingAs($this->owner)->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.projects.0.pending_requests', 1)
            ->assertJsonPath('data.boards.0.pending_requests', 1);

        $this->patchJson('/api/admin/boards/closed', ['visibility' => 'public'])->assertOk();
        $this->getJson('/api/admin/me')->assertOk()
            ->assertJsonPath('data.projects.0.pending_requests', 0)
            ->assertJsonPath('data.boards.0.pending_requests', 0);
        $this->getJson('/api/admin/boards/closed/participants')->assertOk()
            ->assertJsonCount(0, 'data.requests');

        $this->patchJson('/api/admin/boards/closed', ['visibility' => 'private'])->assertOk();
        $this->getJson('/api/admin/me')->assertOk()
            ->assertJsonPath('data.projects.0.pending_requests', 1)
            ->assertJsonPath('data.boards.0.pending_requests', 1);
        $this->getJson('/api/admin/boards/closed/participants')->assertOk()
            ->assertJsonCount(1, 'data.requests');
    }

    public function test_me_counts_waiting_requests_only_where_i_decide(): void
    {
        $this->ask();
        $this->ask(['email' => 'second@external.com']);
        $manager = User::factory()->create();
        $this->board->members()->attach($manager, ['role' => ProjectRole::Manager->value]);

        $this->actingAs($this->admin)->getJson('/api/admin/me')
            ->assertJsonPath('data.projects.0.pending_requests', 2);
        $this->actingAs($manager)->getJson('/api/admin/me')
            ->assertJsonPath('data.projects.0.pending_requests', 0);
    }
}
