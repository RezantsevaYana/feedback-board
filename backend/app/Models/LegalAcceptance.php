<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'document_key', 'version', 'content_hash', 'snapshot', 'source', 'accepted_at'])]
class LegalAcceptance extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'accepted_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
