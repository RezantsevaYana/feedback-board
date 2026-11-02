<?php

namespace App\Enums;

/**
 * A person's level on a single board — the only thing access and rights depend on.
 * Stored in project_user.role as a string with a CHECK constraint; the enum guards
 * the PHP side.
 */
enum ProjectRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Voter = 'voter';
    case Reader = 'reader';

    public function canConfigureBoard(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            default => false,
        };
    }

    public function canTriage(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::Manager => true,
            default => false,
        };
    }

    public function canVote(): bool
    {
        return $this !== self::Reader;
    }

    /**
     * Roles that exist on an open board: the team that triages and configures it. Everyone else there is a guest
     * by the link, so Участник / Читатель aren't invited to an open board and get no rights on it (02.11.2026).
     *
     * @return array<int, string>
     */
    public static function openBoardAssignable(): array
    {
        return [self::Admin->value, self::Manager->value];
    }

    public function canSubmit(): bool
    {
        return $this !== self::Reader;
    }

    public function canDeleteBoard(): bool
    {
        return $this === self::Owner;
    }
}
