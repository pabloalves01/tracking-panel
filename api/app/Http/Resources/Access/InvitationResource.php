<?php

namespace App\Http\Resources\Access;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'invited_by' => $this->whenLoaded('invitedBy', fn () => $this->invitedBy?->name),
            'created_at' => $this->created_at,
            // Só na resposta de criar ou reenviar: o link para copiar.
            'invite_url' => $this->when($this->plainToken !== null, fn () => $this->inviteUrl()),
            'account_exists' => $this->when($this->accountExists !== null, fn () => $this->accountExists),
        ];
    }
}
