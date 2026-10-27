<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slice 2 of docs/ROLES_PLAN.md: bulk invitations.
     *
     *   - email + role picked at send time (role can't be "owner" — there is only one).
     *   - token is the one-shot handle in the letter; expires_at = send time + 7 days.
     *   - is_team_member pre-filled by matching the invitee's email domain against
     *     projects.team_domain, overridable per-row at send time.
     *   - accepted_at marks the terminal state. We keep accepted rows for the audit trail
     *     ("кем приглашён, когда принято") shown on the participants page.
     *
     * A board can hold at most one pending invitation per email (the partial unique index below).
     * A follow-up "invite again" updates that row instead of inserting — see InvitationController.
     */
    public function up(): void
    {
        Schema::create('project_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 16);
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->boolean('is_team_member')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'email']);
        });

        // Only Owner is excluded from assignable roles — the board has exactly one, set at creation.
        $assignable = array_filter(
            array_map(fn (\App\Enums\ProjectRole $r) => $r->value, \App\Enums\ProjectRole::cases()),
            fn (string $v) => $v !== \App\Enums\ProjectRole::Owner->value,
        );

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            $list = "'".implode("','", $assignable)."'";
            DB::statement("ALTER TABLE project_invitations ADD CONSTRAINT project_invitations_role_check CHECK (role IN ({$list}))");
        }

        // One pending invitation per (board, email). Postgres partial index: rows with accepted_at IS NULL only.
        // Historical accepted rows stay for the audit log and don't block re-inviting the same person later.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX project_invitations_pending_unique ON project_invitations (project_id, email) WHERE accepted_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS project_invitations_pending_unique');
        }
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            DB::statement('ALTER TABLE project_invitations DROP CONSTRAINT IF EXISTS project_invitations_role_check');
        }

        Schema::dropIfExists('project_invitations');
    }
};
