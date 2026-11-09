<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Initial backlog import is superseded by import_feedback_board_snapshot,
        // after the kanban_position column is available.
    }

    public function down(): void
    {
        // Preserve user-managed backlog data.
    }
};
