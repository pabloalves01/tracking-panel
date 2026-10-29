<?php

namespace App\Http\Requests\Registry;

class DriverRequest extends EntityRequest
{
    protected function roleRules(): array
    {
        return [
            'license_number' => ['required', 'string', 'max:30'],
            'license_category' => ['required', 'string', 'max:10'],
            'license_expires_on' => ['nullable', 'date'],
            'license_issued_state' => ['nullable', 'string', 'size:2'],
            'residence_proof_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function roleMessages(): array
    {
        return [
            'license_number.required' => 'O número da CNH é obrigatório',
            'license_category.required' => 'A categoria da CNH é obrigatória',
            'license_expires_on.date' => 'A validade da CNH é inválida',
            'license_issued_state.size' => 'A UF de emissão deve ter 2 letras',
        ];
    }
}
