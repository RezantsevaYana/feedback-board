<?php

declare(strict_types=1);

namespace App\Orchid\Modules\Board\Screens;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Relation;
use Orchid\Screen\Layouts\Rows;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

/**
 * Карточка доски в админке. В первой итерации — правка имени и передача владения
 * (включая legacy-доски, где owner_id=null — назначение первого владельца).
 */
class BoardEditScreen extends Screen
{
    // Nullable — Orchid вызывает name()/description() ДО fillPublicProperties($queryResult),
    // так что до query() свойство ещё не инициализировано. Фолбэк в name() решает.
    public ?Project $board = null;

    public function query(Project $board): iterable
    {
        return [
            'board' => $board,
            // Resolution-ready values для Rows: Relation ждёт id, Input — строку.
            'form' => [
                'name' => $board->name,
                'owner_id' => $board->owner_id,
            ],
        ];
    }

    public function name(): ?string
    {
        return $this->board ? 'Доска: '.$this->board->name : 'Доска';
    }

    public function description(): ?string
    {
        return 'Передача владения и переименование. Slug и видимость меняются только командой доски.';
    }

    public function permission(): ?iterable
    {
        return ['platform.boards'];
    }

    public function commandBar(): iterable
    {
        return [
            Button::make('Сохранить')
                ->icon('bs.save')
                ->method('save'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('form.name')
                    ->title('Название')
                    ->maxlength(80)
                    ->required(),

                Relation::make('form.owner_id')
                    ->title('Владелец')
                    ->help('Email нового владельца. Предыдущий останется на доске с ролью «Админ». Если новый владелец ещё не подтвердил почту, доска уйдёт в «не опубликована» до подтверждения.')
                    ->fromModel(User::class, 'email')
                    ->searchColumns('email', 'name')
                    ->empty('— из консоли (без владельца) —'),
            ]),
        ];
    }

    public function save(Project $board, Request $request, ProjectService $service): RedirectResponse
    {
        $data = $request->validate([
            'form.name' => ['required', 'string', 'max:80'],
            'form.owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $board->name = $data['form']['name'];
        $board->save();

        $newOwnerId = $data['form']['owner_id'] ?? null;
        if ($newOwnerId !== $board->owner_id) {
            if ($newOwnerId === null) {
                // Отзыв владельца (крайний кейс: вернуть доску в «из консоли»). Пивот не трогаем —
                // прежний владелец останется с ролью owner до ручной корректировки.
                $board->owner_id = null;
                $board->save();
                Toast::info('Владелец снят — доска переведена в «из консоли».');
            } else {
                $service->transferOwnership($board, User::findOrFail($newOwnerId));
                Toast::info('Владение передано. Старый владелец сохраняет доступ с ролью «Админ».');
            }
        } else {
            Toast::info('Сохранено.');
        }

        return redirect()->route('platform.board.list');
    }
}
