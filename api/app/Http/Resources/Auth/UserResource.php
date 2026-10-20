<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\System\Company\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Usuário autenticado. A senha fica de fora.
     * `current_company` é a empresa gravada no token desta sessão.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $companyId = $this->currentAccessToken()?->company_id;
        $company = $companyId ? Company::find($companyId) : null;

        return [
            'name' => $this->name,
            'email' => $this->email,
            'is_system_admin' => (bool) $this->is_system_admin,
            'has_company' => (bool) $company,
            'current_company' => $company ? CompanyResource::make($company) : null,
        ];
    }
}
