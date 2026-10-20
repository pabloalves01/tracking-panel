<?php

namespace App\Http\Resources\System\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Empresa como sai na API.
     * O `id` interno não é exposto; o cliente identifica a empresa pelo `uuid`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'cnpj' => $this->cnpj,
            'logo_path' => $this->logo_path,
            'users_count' => $this->whenCounted('users'),
            // 'created_at' => $this->created_at?->toISOString(),
            // 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
