<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Кастомный текст на кнопке-лаунчере виджета (по умолчанию «Обратная связь»). Null — стандартный текст.
 * Как и widget_color, хранится на проекте — изменение сразу долетает до каждого сайта с виджетом,
 * код вставки менять не нужно.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('widget_launcher_text', 40)->nullable()->after('widget_color');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('widget_launcher_text');
        });
    }
};
