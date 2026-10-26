<?php

namespace App\Http\Resources\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'invite_url' => $this->when($this->plainToken !== null, fn () => $this->inviteUrl()),
        ];
    }
}
