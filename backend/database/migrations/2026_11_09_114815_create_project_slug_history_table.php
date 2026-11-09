<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Освобождённые адреса доски (test.md п. 24). Когда владелец меняет slug, старое значение
 * остаётся здесь — публичные ссылки и уже встроенные виджеты по нему продолжают работать:
 * SPA получает новый slug и делает router.replace, виджет — внутреннюю подмену.
 *
 * Уникальный old_slug: один адрес не может указывать на две доски, и занять его обратно
 * (под другую доску) тоже нельзя — ссылки, уже разошедшиеся по сети, важнее.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_slug_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug', 64)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_slug_history');
    }
};
