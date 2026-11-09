<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пускать ли поисковики на страницу открытой доски. Если выключить — на публичной странице доски
 * отдадим <meta name="robots" content="noindex, nofollow">, а в robots.txt добавится Disallow: /{slug}
 * (test.md п. 19). Для закрытой доски настройка смысла не имеет: гость её и так не увидит, но поле
 * храним у всех проектов, чтобы при повторном открытии прежний выбор сохранился.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('search_indexable')->default(true)->after('widget_launcher_text');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('search_indexable');
        });
    }
};
