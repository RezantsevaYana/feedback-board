<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum of Orchid dashboard route names (see routes/platform.php). Mirrors the kerr pattern:
 * `<self>->list()` builds the full route name, `<self>->getTitle()` — the breadcrumb/header text.
 */
enum OrchidRoutes: string
{
    case BOARD = 'board';

    public function list(): string
    {
        return 'platform.'.$this->value.'.list';
    }

    public function getTitle(): string
    {
        return match ($this) {
            self::BOARD => 'Доски',
        };
    }
}
