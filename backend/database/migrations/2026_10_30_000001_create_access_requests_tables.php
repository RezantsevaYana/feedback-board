<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stage 3 of docs/ROLES_PLAN.md: access requests to a private board by its URL.
     *
     *   - access_requests: who asks (name, email, why), and how it ended. A pending request is one per
     *     (board, email) — a second one from the same person updates the row instead (partial unique index).
     *     Decided rows stay for the audit trail and for «по запросу» in the participants' source column.
     *   - user_id is set when the person asked while logged in; the letters still go to `email`.
     *   - decided_at also drives the pause after a rejection (AccessRequest::REJECT_COOLDOWN_HOURS).
     */
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name', 80);
            $table->text('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default(\App\Enums\AccessRequestStatus::Pending->value);
            $table->string('role_on_approve', 16)->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            $allowed = array_map(fn (\App\Enums\AccessRequestStatus $s) => $s->value, \App\Enums\AccessRequestStatus::cases());
            $list = "'".implode("','", $allowed)."'";
            DB::statement("ALTER TABLE access_requests ADD CONSTRAINT access_requests_status_check CHECK (status IN ({$list}))");
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX access_requests_pending_unique ON access_requests (project_id, email) WHERE status = 'pending'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};