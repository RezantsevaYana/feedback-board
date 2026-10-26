<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The widget's main colour, chosen by the owner on the «Подключение» page as a HEX string (#1d8547).
 * Null keeps the service's own green. Stored on the board, so a change reaches every site with the
 * widget without touching the embed code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('widget_color', 7)->nullable()->after('allowed_origins');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('widget_color');
        });
    }
};