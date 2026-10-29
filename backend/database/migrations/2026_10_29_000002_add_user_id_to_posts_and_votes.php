<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slice 2 of stage 2 (docs/ROLES_PLAN.md §3.2): bind post authorship and votes to a user account.
     *
     * Both columns are nullable: the widget on someone else's site stays anonymous (no session there),
     * and existing rows have no account to attach to. The partial unique index on votes enforces
     * «one account — one vote per post» without touching anonymous votes (user_id IS NULL rows are skipped).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('author_user_id')->nullable()->after('author_token')->constrained('users')->nullOnDelete();
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('voter_token')->constrained('users')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX votes_post_id_user_id_unique ON votes (post_id, user_id) WHERE user_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS votes_post_id_user_id_unique');
        }

        Schema::table('votes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_user_id');
        });
    }
};
