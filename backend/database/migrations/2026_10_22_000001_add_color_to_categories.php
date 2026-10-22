<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-selected tag colour per category: before, every category tag was the same sage grey.
 * Palette lives in App\Models\Category::PALETTE and is referenced by slug ('red', 'blue', …).
 * Null means «no colour set»; the tag falls back to --tag like it did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('color', 16)->nullable()->after('name');
        });

        // Known default category names get sensible colours out of the gate; others stay neutral.
        DB::table('categories')->where('name', 'Баг')->update(['color' => 'red']);
        DB::table('categories')->whereIn('name', ['Идея', 'Фича'])->update(['color' => 'blue']);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
