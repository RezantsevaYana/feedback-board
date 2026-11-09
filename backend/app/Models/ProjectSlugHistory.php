<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Освобождённый адрес доски (test.md п. 24): сохраняется каждый раз, когда владелец
 * меняет slug. Публичный резолвер проверяет эту таблицу и возвращает актуальный проект
 * под его новым адресом, а валидация занимает запрещает кому-либо (включая самого
 * владельца) снова занять такой slug.
 */
#[Fillable(['project_id', 'old_slug'])]
class ProjectSlugHistory extends Model
{
    protected $table = 'project_slug_history';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
