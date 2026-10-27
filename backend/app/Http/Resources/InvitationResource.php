<?php

namespace App\Http\Resources;

use App\Models\ProjectInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pending or accepted invitation. The token NEVER leaves this resource —
 * it only lives in the letter and in the acceptance URL.
 *
 * @property-read ProjectInvitation $resource
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'is_team_member' => $this->resource->is_team_member,
            'invited_by' => [
                'id' => $this->resource->invited_by,
                'name' => $this->resource->inviter?->name,
            ],
            'expires_at' => $this->resource->expires_at,
            'accepted_at' => $this->resource->accepted_at,
            'is_pending' => $this->resource->isPending(),
        ];
    }
}
