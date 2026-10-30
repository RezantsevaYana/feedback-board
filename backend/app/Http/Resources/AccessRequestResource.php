<?php

namespace App\Http\Resources;

use App\Models\AccessRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pending «Запросить доступ» card on the participants page.
 *
 * @property-read AccessRequest $resource
 */
class AccessRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'message' => $this->resource->message,
            // asked while logged in: the address belongs to an account
            'has_account' => $this->resource->user_id !== null,
            'created_at' => $this->resource->created_at,
        ];
    }
}