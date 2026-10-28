<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person on the board (project_user). Expects the User model to carry a pivot
 * with the role — i.e. loaded through Project::members() / User::projects().
 *
 * @property-read User $resource
 */
class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot */
        $pivot = $this->resource->pivot;

        return [
            'user_id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $pivot?->role,
            // set by ParticipantController::index: created | invitation | manual
            'source' => $this->resource->getAttribute('source'),
        ];
    }
}
