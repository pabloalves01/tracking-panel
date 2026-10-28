<?php

namespace App\Http\Resources\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Membership
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->user->uuid,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'status' => $this->status,
            'joined_at' => $this->created_at,
            'is_me' => $request->user()?->getKey() === $this->user_id,
        ];
    }
}
