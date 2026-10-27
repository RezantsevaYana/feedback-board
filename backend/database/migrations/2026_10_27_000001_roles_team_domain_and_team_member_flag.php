<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Slice 1 of docs/ROLES_PLAN.md — just the schema and a one-shot backfill.
     * No API or UI changes land with this migration: everyone keeps the exact rights they had.
     *
     * Three changes to the schema:
     *   - project_user.role: 5 levels (owner, admin, manager, voter, reader) as a string + CHECK.
     *   - project_user.is_team_member: boolean axis independent of role, for the "team / external" badge.
     *   - projects.team_domain: nullable string, auto-filled from the owner's email domain
     *     (null when the owner uses a public mail provider — see config/feedback.php).
     *
     * Backfill (existing data stays functionally identical):
     *   - Every existing project_user row → role=admin, is_team_member=true. Nobody loses access.
     *   - Every projects.owner_id → upserted into project_user with role=owner, is_team_member=true.
     *     projects.owner_id stays as a "who created this" cache (plan §1).
     *   - projects.team_domain: owner's email domain unless it's a public provider.
     */
    public function up(): void
    {
        $allowed = array_map(fn (\App\Enums\ProjectRole $r) => $r->value, \App\Enums\ProjectRole::cases());

        Schema::table('project_user', function (Blueprint $table) {
            $table->string('role', 16)->after('user_id')->default(\App\Enums\ProjectRole::Admin->value);
            $table->boolean('is_team_member')->after('role')->default(false);
        });

        // CHECK is a second line of defence for anything that writes directly without the enum.
        // Only Postgres and MySQL understand ALTER TABLE ADD CONSTRAINT; SQLite (test DB) silently
        // skips it, PHP-side ProjectRole still guards every write.
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            $list = "'".implode("','", $allowed)."'";
            DB::statement("ALTER TABLE project_user ADD CONSTRAINT project_user_role_check CHECK (role IN ({$list}))");
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->string('team_domain')->nullable()->after('owner_id');
        });

        // Everything that was in project_user before this migration is a legacy teammate — pre-plan
        // we only had "admins with full access". Keep them admins; mark them as team members.
        DB::table('project_user')->update([
            'role' => \App\Enums\ProjectRole::Admin->value,
            'is_team_member' => true,
        ]);

        // Self-serve owners live in projects.owner_id. Move them into project_user as role=owner.
        $publicDomains = array_map('strtolower', (array) config('feedback.public_email_domains', []));
        $owners = DB::table('projects')
            ->join('users', 'users.id', '=', 'projects.owner_id')
            ->whereNotNull('projects.owner_id')
            ->get(['projects.id as project_id', 'users.id as user_id', 'users.email']);

        foreach ($owners as $row) {
            DB::table('project_user')->updateOrInsert(
                ['project_id' => $row->project_id, 'user_id' => $row->user_id],
                ['role' => \App\Enums\ProjectRole::Owner->value, 'is_team_member' => true],
            );

            $domain = Str::lower((string) Str::after($row->email, '@'));
            $teamDomain = ($domain !== '' && ! in_array($domain, $publicDomains, true)) ? $domain : null;
            DB::table('projects')->where('id', $row->project_id)->update(['team_domain' => $teamDomain]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('team_domain');
        });

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            DB::statement('ALTER TABLE project_user DROP CONSTRAINT IF EXISTS project_user_role_check');
        }

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_team_member']);
        });
    }
};
