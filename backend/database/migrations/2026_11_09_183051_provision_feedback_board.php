<?php

use Database\Seeders\FeedbackBoardSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        (new FeedbackBoardSeeder)->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep the board and its backlog when rolling back application code.
    }
};
