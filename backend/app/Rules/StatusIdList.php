<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The board's status filter: one id or several, comma-separated (status_id=3,5), all of this project.
 */
class StatusIdList implements ValidationRule
{
    public function __construct(private Project $project) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! preg_match('/^\d+(,\d+)*$/', (string) $value)) {
            $fail('Неверный фильтр по статусу.');

            return;
        }

        $ids = array_unique(array_map('intval', explode(',', (string) $value)));
        if ($this->project->statuses()->whereIn('id', $ids)->count() !== count($ids)) {
            $fail('Такого статуса нет в этом проекте.');
        }
    }
}
