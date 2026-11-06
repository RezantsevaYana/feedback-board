<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the owner left the new-board onboarding (test.md п. 20): the step to open on «Продолжить» from the hub.
 * Null — nothing to continue: finished, hidden with «Скрыть», or a board that never went through it
 * (every board created before, by seeds or from the console).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('onboarding_step')->nullable()->after('widget_launcher_text');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('onboarding_step');
        });
    }
};
