<?php

namespace App\Http\Resources\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * Convite pendente. O token nunca sai daqui; só na criação e no "novo link".
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'role' => $this->role,
            'invited_by' => $this->inviter?->name,
            'expires_at' => $this->expires_at,
            'expired' => $this->isExpired(),
        ];
    }
}
