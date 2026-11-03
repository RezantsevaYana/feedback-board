<?php

namespace Database\Seeders;

use App\Enums\PostPriority;
use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Orchid\Support\Facades\Dashboard;

/**
 * Local demo: project "Demo" (/demo) with a few posts and an admin admin@example.com / password (also a superadmin).
 * Does nothing if the demo project already exists, so it is safe to run on every start.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Project::where('slug', 'demo')->exists()) {
            return;
        }

        $admin = User::create([
            'name' => 'Админ',
            'email' => 'admin@example.com',
            'password' => 'password',
            // local admin also opens «Админка сервиса» (/admin) — Dashboard::getAllowAllPermission()
            // returns the full set registered in PlatformProvider::registerPermissions().
            'permissions' => Dashboard::getAllowAllPermission()->all(),
        ]);
        $admin->markEmailAsVerified();

        // owner_id so ParticipantController marks this row as «создал доску» (source = created),
        // not «добавлен вручную». Pivot role must match: ProjectRole::Owner.
        $project = Project::create([
            'name' => 'Demo',
            'slug' => 'demo',
            'owner_id' => $admin->id,
            'allowed_origins' => ['http://localhost:*', 'http://127.0.0.1:*'],
        ]);
        $admin->projects()->attach($project, [
            'role' => ProjectRole::Owner->value,
        ]);

        $project->createDefaultCategories();
        $project->createDefaultStatuses();
        [$bug, $idea] = $project->categories()->get()->all();
        $statuses = $project->statuses()->get()->keyBy('slug');

        $posts = [
            [$idea, 'Тёмная тема в веб-версии', 'Вечером глаза устают от белого фона. Хочется переключатель темы в настройках профиля, и чтобы по умолчанию тема бралась из системы.', 'Марина К.', 'new', PostPriority::Medium, 23, 2, 21, null],
            [$bug, 'Уведомления приходят дважды на телефоне', 'После обновления в понедельник каждое сообщение в рабочем чате приходит двумя пушами подряд. Android 14, приложение последней версии.', 'Алексей', 'new', PostPriority::High, 17, 0, 6, null],
            [$idea, 'Отложенная отправка сообщений', 'Иногда пишу коллегам вечером и не хочу их будить. Было бы удобно выбрать «отправить завтра в 9:00».', null, 'new', null, 14, 3, 9, null],
            [$bug, 'Не прикрепляется файл больше 20 МБ', 'Пытаюсь отправить презентацию 24 МБ: крутится загрузка и через минуту пропадает без ошибки. Хотя бы сообщение о лимите показать.', 'Денис П.', 'new', null, 6, 0, 2, null],
            [$idea, 'Поиск по закреплённым сообщениям', 'В канале #разработка закреплено 40+ сообщений, найти нужное невозможно. Нужен поиск или хотя бы фильтр.', 'Ольга', 'new', null, 9, 1, 4, null],
            [$bug, 'Сбивается часовой пояс в календаре', 'Встречи, созданные из Новосибирска, у коллег в Москве показываются на час раньше. Видимо, не учитывается пояс автора.', 'Игорь Н.', 'done', PostPriority::Critical, 11, 0, 30, 'Исправили в версии 3.12: время встречи теперь хранится с поясом автора. Спасибо за подробное описание!'],
            [$idea, 'Реакции эмодзи на сообщения', 'Чтобы не писать «ок» и «спасибо» отдельными сообщениями.', 'Аноним из маркетинга', 'rejected', PostPriority::Low, 3, 5, 40, 'Реакции уже есть: наведите на сообщение и нажмите на смайлик справа. Сделаем подсказку заметнее.'],
            [$idea, 'Статус «в отпуске» с датой возвращения', 'Чтобы при упоминании человека сразу было видно, что он в отпуске до 28 октября, и не ждать ответа.', 'Марина К.', 'new', null, 4, 0, 1, null],
        ];

        foreach ($posts as [$category, $title, $description, $author, $statusSlug, $priority, $likes, $dislikes, $daysAgo, $comment]) {
            $post = new Post([
                'category_id' => $category->id, 'title' => $title, 'description' => $description, 'author_name' => $author,
                'status_id' => $statuses[$statusSlug]->id, 'priority' => $priority, 'team_comment' => $comment,
            ]);
            $post->project()->associate($project);
            $post->likes_count = $likes;
            $post->dislikes_count = $dislikes;
            $post->created_at = now()->subDays($daysAgo);
            $post->status_changed_at = now()->subDays(max(0, $daysAgo - 2));
            $post->save();
        }
    }
}
