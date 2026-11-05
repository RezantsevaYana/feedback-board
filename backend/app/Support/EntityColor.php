<?php

namespace App\Support;

use App\Models\Category;

class EntityColor
{
    public const RULE = 'regex:/\A(?:red|orange|yellow|green|blue|pink|#[0-9a-f]{6})\z/';

    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }
        if (in_array($value, Category::PALETTE, true)) {
            return $value;
        }

        $hex = preg_replace('/^#/', '', $value);
        if (preg_match('/\A[0-9a-f]{3}\z/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }
}
