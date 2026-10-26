<?php

namespace App\Http\Resources\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Vínculo de uma pessoa com a empresa ou o posto.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->user->uuid,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->role,
            'joined_at' => $this->created_at,
            'is_you' => $this->user_id === $request->user()->id,
        ];
    }
}
