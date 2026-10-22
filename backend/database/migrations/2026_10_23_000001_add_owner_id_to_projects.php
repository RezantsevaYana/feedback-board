<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner of the board — the admin who created it via self-serve registration.
     * Nullable: legacy projects created via `php artisan project:create` stay without an owner
     * and remain accessible only through the project_user pivot.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('slug')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
