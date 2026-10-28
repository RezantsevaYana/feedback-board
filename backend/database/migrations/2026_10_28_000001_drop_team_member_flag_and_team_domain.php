<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The «команда / внешний» badge is gone: access and rights come from the role alone,
     * the badge only decorated lists. Drops the flag on members and invitations and the
     * board's team domain that pre-filled it.
     */
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('is_team_member');
        });
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->dropColumn('is_team_member');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('team_domain');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('team_domain')->nullable()->after('owner_id');
        });
        Schema::table('project_invitations', function (Blueprint $table) {
            $table->boolean('is_team_member')->default(false);
        });
        Schema::table('project_user', function (Blueprint $table) {
            $table->boolean('is_team_member')->after('role')->default(false);
        });
    }
};