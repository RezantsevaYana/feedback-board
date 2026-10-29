<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `voter_token` was the only way to identify a voter when the system was anonymous-first.
     * Now `votes.user_id` carries the signed-in identity (see 2026_10_29_000002), and the cookie
     * is a fallback for the cross-origin widget. Signed-in votes without a cookie should still
     * work — so the column is nullable. The legacy unique index (post_id, voter_token) stays:
     * NULL values are treated as distinct in Postgres/MySQL/SQLite, so it keeps protecting
     * anonymous votes without blocking signed-in rows.
     */
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->uuid('voter_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->uuid('voter_token')->nullable(false)->change();
        });
    }
};
