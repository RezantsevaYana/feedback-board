<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Привязка идей к анонимному токену автора и справочник имён для лидерборда.
 * Старые посты остаются с author_token = NULL — в топе не участвуют.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->uuid('author_token')->nullable()->after('author_name');
            $table->index('author_token');
        });

        Schema::create('voters', function (Blueprint $table) {
            $table->uuid('token')->primary();
            $table->string('display_name', 80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voters');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['author_token']);
            $table->dropColumn('author_token');
        });
    }
};
