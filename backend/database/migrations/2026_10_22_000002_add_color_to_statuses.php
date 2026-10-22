<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-selected pill colour per status. Before, pills were coloured by the slug
 * (new=grey, done=green, rejected=red); now the colour follows the same palette as
 * categories (slugs in App\Models\Status::PALETTE). System statuses get sensible
 * defaults so the board doesn't lose its semantic colours after the migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->string('color', 16)->nullable()->after('name');
        });

        // Keep the familiar look for the three system columns.
        DB::table('statuses')->where('slug', 'new')->update(['color' => 'blue']);
        DB::table('statuses')->where('slug', 'done')->update(['color' => 'green']);
        DB::table('statuses')->where('slug', 'rejected')->update(['color' => 'red']);
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
