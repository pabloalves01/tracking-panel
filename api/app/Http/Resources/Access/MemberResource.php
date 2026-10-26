<?php

namespace App\Http\Resources\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->user?->uuid,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'role' => $this->role,
            'status' => $this->status,
        ];
    }
}
