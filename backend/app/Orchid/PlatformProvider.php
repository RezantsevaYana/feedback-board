<?php

declare(strict_types=1);

namespace App\Orchid;

use App\Enums\OrchidRoutes;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;

class PlatformProvider extends OrchidServiceProvider
{
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);
    }

    /**
     * The dashboard sidebar.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make('Доски')
                ->icon('bs.kanban')
                ->route(OrchidRoutes::BOARD->list())
                ->permission('platform.boards')
                ->title('Сервис'),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Access Controls')),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),
        ];
    }

    /**
     * Permissions shown in the role editor and used in menu()/screens via ->permission(...).
     * «Сервис» is OUR group; the system ones (users/roles/attachment/index) come from Orchid itself.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group('Сервис')
                ->addPermission('platform.boards', 'Доски')
                ->addPermission('platform.users', 'Пользователи'),

            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users')),
        ];
    }
}
