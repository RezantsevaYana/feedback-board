<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Статусы доски становятся настраиваемыми. Три системных (new, done, rejected)
 * создаются для каждого проекта и никогда не удаляются; команда может добавлять
 * свои между ними. У постов `status` строка превращается в `status_id` → statuses.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 40);
            $table->string('name', 40);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'position']);
        });

        // 3 системных статуса на проект; оставляем «воздух» между позициями,
        // чтобы кастомные статусы могли вставляться без перенумерации.
        $systemStatuses = [
            ['slug' => 'new', 'name' => 'Новое', 'position' => 100],
            ['slug' => 'done', 'name' => 'Готово', 'position' => 9000],
            ['slug' => 'rejected', 'name' => 'Отклонено', 'position' => 9100],
        ];
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            foreach ($systemStatuses as $s) {
                DB::table('statuses')->insert([
                    'project_id' => $projectId,
                    'slug' => $s['slug'],
                    'name' => $s['name'],
                    'position' => $s['position'],
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('author_name')->constrained('statuses');
        });

        // Переносим posts.status → status_id, матчим по (project_id, slug)
        DB::statement('
            update posts
            set status_id = s.id
            from statuses s
            where s.project_id = posts.project_id and s.slug = posts.status
        ');

        // Остатки (вдруг какой-то пост в неизвестном статусе) — в «Новое» того же проекта
        DB::statement("
            update posts
            set status_id = (select id from statuses s where s.project_id = posts.project_id and s.slug = 'new')
            where status_id is null
        ");

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropColumn('status');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable(false)->change();
            $table->index(['project_id', 'status_id']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('status', 20)->default('new')->after('author_name');
            $table->dropIndex(['project_id', 'status_id']);
        });

        // Пишем обратно slug в posts.status из statuses
        DB::statement('
            update posts
            set status = s.slug
            from statuses s
            where s.id = posts.status_id
        ');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_id');
            $table->index(['project_id', 'status']);
        });

        Schema::dropIfExists('statuses');
    }
};
