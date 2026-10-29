<?php

namespace App\Enums;

/**
 * Who can see the board. Flipping public → private closes it to everyone outside the
 * project_user pivot; the reverse direction is forbidden in V1 (see docs/ROLES_PLAN.md §4
 * «Что НЕ входит в V1»). Stored on projects.visibility as a string with a CHECK.
 */
enum BoardVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
