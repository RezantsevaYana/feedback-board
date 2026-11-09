<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\LegalAcceptance;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LegalAcceptancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    private function acceptedDocuments(): array
    {
        $catalog = $this->getJson('/api/public/legal-documents')->assertOk()->json('data');
        $result = [];
        foreach (['terms', 'personal_data'] as $key) {
            $result[$key] = ['accepted' => true, 'version' => $catalog[$key]['version'], 'content_hash' => $catalog[$key]['content_hash']];
        }

        return $result;
    }

    private function registration(array $extra = []): array
    {
        return $extra + ['name' => 'Анна', 'email' => 'anna@example.com', 'password' => 'secret-pass'];
    }

    private function invite(): ProjectInvitation
    {
        $owner = User::factory()->create();
        $board = Project::factory()->create(['owner_id' => $owner->id]);

        return $board->invitations()->create([
            'email' => 'new@example.com', 'role' => ProjectRole::Voter,
            'token' => ProjectInvitation::newToken(), 'expires_at' => ProjectInvitation::defaultExpiry(), 'invited_by' => $owner->id,
        ]);
    }

    public function test_registration_without_confirmations_does_not_create_an_account(): void
    {
        $this->postJson('/api/admin/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors(['legal.terms.accepted', 'legal.personal_data.accepted']);

        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
        $this->assertDatabaseCount('legal_acceptances', 0);
        Notification::assertNothingSent();
    }

    public function test_terms_acceptance_does_not_substitute_personal_data_consent(): void
    {
        $legal = $this->acceptedDocuments();
        $legal['personal_data']['accepted'] = false;
        $this->postJson('/api/admin/register', $this->registration(['legal' => $legal]))
            ->assertUnprocessable()->assertJsonValidationErrors('legal.personal_data.accepted');
        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
    }

    public function test_personal_data_consent_does_not_substitute_terms_acceptance(): void
    {
        $legal = $this->acceptedDocuments();
        $legal['terms']['accepted'] = false;
        $this->postJson('/api/admin/register', $this->registration(['legal' => $legal]))
            ->assertUnprocessable()->assertJsonValidationErrors('legal.terms.accepted');
        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
    }

    public function test_registration_records_separate_snapshots_and_does_not_verify_email(): void
    {
        $legal = $this->acceptedDocuments();
        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/admin/register', $this->registration(['legal' => $legal]))
            ->assertOk()->assertJsonPath('data.email_verified', false);

        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $this->assertCount(2, $user->legalAcceptances);
        foreach ($user->legalAcceptances as $record) {
            $this->assertSame('registration', $record->source);
            $this->assertSame($legal[$record->document_key]['content_hash'], $record->content_hash);
            $this->assertTrue($record->snapshot['draft']);
            $this->assertNotEmpty($record->snapshot['sections']);
            $this->assertNotNull($record->accepted_at);
        }
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_stale_document_version_is_refused(): void
    {
        $legal = $this->acceptedDocuments();
        config(['legal.version' => 'new-version']);
        $this->postJson('/api/admin/register', $this->registration(['legal' => $legal]))
            ->assertUnprocessable()->assertJsonValidationErrors(['legal.terms.version', 'legal.personal_data.version']);
        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
    }

    public function test_changed_text_is_refused_even_if_someone_forgot_to_change_the_version(): void
    {
        $legal = $this->acceptedDocuments();
        config(['legal.documents.terms.sections.0.text' => 'Новый текст']);
        $this->postJson('/api/admin/register', $this->registration(['legal' => $legal]))
            ->assertUnprocessable()->assertJsonValidationErrors('legal.terms.content_hash');
        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
    }

    public function test_new_account_cannot_bypass_confirmations_through_an_invitation(): void
    {
        $invitation = $this->invite();
        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", ['name' => 'Новичок', 'password' => 'secret-pass'])
            ->assertUnprocessable()->assertJsonValidationErrors(['legal.terms.accepted', 'legal.personal_data.accepted']);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_invitation_records_confirmations_and_verifies_the_new_account_email(): void
    {
        $invitation = $this->invite();
        $this->postJson("/api/admin/invitations/{$invitation->token}/accept", [
            'name' => 'Новичок', 'password' => 'secret-pass', 'legal' => $this->acceptedDocuments(),
        ])->assertOk();
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(ProjectRole::Voter, $user->roleOn($invitation->project));
        $this->assertSame(['invitation', 'invitation'], $user->legalAcceptances()->pluck('source')->all());
    }

    public function test_snapshot_failure_does_not_consume_an_invitation_or_create_a_user(): void
    {
        $invitation = $this->invite();
        $legal = $this->acceptedDocuments();
        LegalAcceptance::creating(fn () => throw new \RuntimeException('Storage unavailable'));
        try {
            $this->postJson("/api/admin/invitations/{$invitation->token}/accept", [
                'name' => 'Новичок', 'password' => 'secret-pass', 'legal' => $legal,
            ])->assertServerError();
            $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
            $this->assertNull($invitation->fresh()->accepted_at);
            $this->assertSame(1, $invitation->project->members()->count());
        } finally {
            LegalAcceptance::flushEventListeners();
        }
    }

    public function test_existing_account_joins_without_fabricating_registration_consents(): void
    {
        $invitation = $this->invite();
        $user = User::factory()->create(['email' => $invitation->email]);
        $this->actingAs($user)->postJson("/api/admin/invitations/{$invitation->token}/accept")->assertOk();
        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_saved_snapshot_keeps_the_original_text_when_documents_change(): void
    {
        $legal = $this->acceptedDocuments();
        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/admin/register', $this->registration(['legal' => $legal]))->assertOk();
        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $record = $user->legalAcceptances()->where('document_key', 'terms')->firstOrFail();
        $snapshot = $record->snapshot;
        config(['legal.documents.terms.sections.0.text' => 'Новый текст']);

        $this->assertSame($snapshot, $record->fresh()->snapshot);
        $this->assertSame($legal['terms']['content_hash'], $record->fresh()->content_hash);
        $this->getJson('/api/public/legal-documents')->assertOk()
            ->assertJsonPath('data.terms.sections.0.text', 'Новый текст');
    }

    public function test_snapshot_failure_rolls_back_account_creation(): void
    {
        $legal = $this->acceptedDocuments();
        LegalAcceptance::creating(fn () => throw new \RuntimeException('Storage unavailable'));
        try {
            $this->withHeader('Origin', 'http://localhost:5173')
                ->postJson('/api/admin/register', $this->registration(['legal' => $legal]))->assertServerError();
            $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
            $this->assertDatabaseCount('legal_acceptances', 0);
            Notification::assertNothingSent();
        } finally {
            LegalAcceptance::flushEventListeners();
        }
    }
}
