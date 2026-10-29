<?php

namespace App\Http\Resources\Registry;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um papel como sai da API: o uuid e a situação do papel, a pessoa em
 * `entity` e as colunas próprias do papel no mesmo nível.
 *
 * O papel que ganhar colunas estende esta classe e as devolve em
 * `details()` — ver `DriverResource`.
 */
class EntityRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'role' => $this->role()->value,
            'status' => $this->status,
            'entity' => EntityResource::make($this->whenLoaded('entity')),
            ...$this->details(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(): array
    {
        return [];
    }
}
