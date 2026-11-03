<?php

declare(strict_types=1);

namespace App\Orchid\Modules\Board\Screens;

use App\Models\Project;
use Carbon\Carbon;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class BoardListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'boards' => Project::withTrashed()
                ->with('owner')
                ->withCount(['posts', 'members'])
                ->withMax('posts as last_post_at', 'created_at')
                ->filters()
                ->defaultSort('created_at', 'desc')
                ->paginate(20),
        ];
    }

    public function name(): ?string
    {
        return 'Доски';
    }

    public function description(): ?string
    {
        return 'Все доски сервиса, включая удалённые (soft-delete, корзина 30 дней — см. `boards:purge-trashed`).';
    }

    public function permission(): ?iterable
    {
        return ['platform.boards'];
    }

    public function layout(): iterable
    {
        return [
            Layout::table('boards', [
                TD::make('name', 'Название')
                    ->sort()
                    ->filter(Input::make())
                    ->render(fn (Project $board) => '<a href="'.e(config('app.frontend_url').'/'.$board->slug).'" target="_blank" rel="noopener">'.e($board->name).'</a>'),

                TD::make('slug', 'Адрес')
                    ->filter(Input::make())
                    ->render(fn (Project $board) => '<code>'.e($board->slug).'</code>'),

                TD::make('owner', 'Владелец')
                    ->render(function (Project $board) {
                        if ($board->owner === null) {
                            return '<span class="text-muted">из консоли</span>';
                        }

                        return e($board->owner->name).'<br><span class="text-muted small">'.e($board->owner->email).'</span>';
                    }),

                TD::make('visibility', 'Доступ')
                    ->filter(Select::make()->options([
                        'public' => 'Открытая',
                        'private' => 'Закрытая',
                    ])->empty('Любой'))
                    ->render(fn (Project $board) => $board->isPrivate() ? 'Закрытая' : 'Открытая'),

                TD::make('published', 'Опубликована')
                    ->render(fn (Project $board) => $board->isPublished() ? 'Да' : '<span class="text-warning">нет</span>'),

                TD::make('posts_count', 'Постов')->sort()->align(TD::ALIGN_RIGHT),

                TD::make('members_count', 'Участников')->align(TD::ALIGN_RIGHT),

                TD::make('last_post_at', 'Последний пост')
                    ->render(fn (Project $board) => $board->last_post_at
                        ? Carbon::parse($board->last_post_at)->diffForHumans()
                        : '—'),

                TD::make('created_at', 'Создана')
                    ->sort()
                    ->render(fn (Project $board) => $board->created_at->format('d.m.Y')),

                TD::make('deleted_at', 'В корзине')
                    ->render(fn (Project $board) => $board->deleted_at
                        ? '<span class="text-danger">'.$board->deleted_at->format('d.m.Y').'</span>'
                        : '—'),

                TD::make('actions', 'Действия')
                    ->align(TD::ALIGN_CENTER)
                    ->width('100px')
                    ->render(fn (Project $board) => Link::make('Открыть')
                        ->icon('bs.pencil')
                        ->route('platform.board.edit', $board)),
            ]),
        ];
    }
}
