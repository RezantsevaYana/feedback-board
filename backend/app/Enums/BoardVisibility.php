<?php

namespace App\Enums;

/**
 * Who can see the board. Flipping public → private closes it to everyone outside the
 * project_user pivot; private → public opens it back, with everything written there
 * (docs/ROLES_PLAN.md §5, 02.11.2026). Stored on projects.visibility as a string with a CHECK.
 */
enum BoardVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
