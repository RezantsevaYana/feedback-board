<?php

namespace App\Enums;

enum PostStatus: string
{
    case New = 'new';
    case Done = 'done';
    case Rejected = 'rejected';
}
