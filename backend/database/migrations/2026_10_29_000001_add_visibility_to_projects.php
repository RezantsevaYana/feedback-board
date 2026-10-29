<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slice 1 of stage 2 (docs/ROLES_PLAN.md §3.2): per-board visibility.
     *
     * Public is the default and matches every existing row — the service was public-only
     * before this migration, so no backfill is needed beyond letting the DB default fill in.
     * Private is one-way in V1: EnsureProjectPublished + UpdateBoardRequest both enforce it;
     * the enum lives in App\Enums\BoardVisibility to keep writes typed on the PHP side.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('visibility', 16)->after('slug')->default(\App\Enums\BoardVisibility::Public->value);
        });

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            $allowed = array_map(fn (\App\Enums\BoardVisibility $v) => $v->value, \App\Enums\BoardVisibility::cases());
            $list = "'".implode("','", $allowed)."'";
            DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_visibility_check CHECK (visibility IN ({$list}))");
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'mysql'], true)) {
            DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_visibility_check');
        }
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
