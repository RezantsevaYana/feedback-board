<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Что может сделать гость на открытой доске (test.md п. 40): предложить и проголосовать. По умолчанию
 * оба true — открытая доска ведёт себя как сейчас, регистрации не требует. Если владелец запрещает
 * оставлять предложения без аккаунта — виджет на сайте не рисуется (войти в нём нельзя).
 * Поля храним у всех проектов, включая закрытые: гостевые настройки оживут, если доску снова открыть.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('guests_can_post')->default(true)->after('search_indexable');
            $table->boolean('guests_can_vote')->default(true)->after('guests_can_post');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['guests_can_post', 'guests_can_vote']);
        });
    }
};
