<?php

namespace App\Http\Requests\Registry\Driver;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedRole());
    }

    public function rules(): array
    {
        return [
            'license_number' => ['required', 'string', 'max:20'],
            'license_category' => ['required', 'in:A,B,C,D,E,AB,AC,AD,AE,ACC'],
            'license_expires_on' => ['nullable', 'date_format:Y-m-d'],
            'license_issued_state' => ['nullable', 'string', 'size:2'],
            'residence_proof_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'license_number.required' => 'O número da CNH é obrigatório',
            'license_number.max' => 'O número da CNH deve ter no máximo 20 caracteres',
            'license_category.required' => 'A categoria da CNH é obrigatória',
            'license_category.in' => 'A categoria da CNH é inválida',
            'license_expires_on.date_format' => 'Informe uma validade da CNH válida',
            'license_issued_state.size' => 'A UF da CNH deve ter 2 letras',
            'residence_proof_path.max' => 'O caminho do comprovante deve ter no máximo 255 caracteres',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedRole(): array
    {
        return [
            'license_expires_on' => filled($this->input('license_expires_on')) ? $this->input('license_expires_on') : null,
            'license_issued_state' => filled($this->input('license_issued_state'))
                ? strtoupper((string) $this->input('license_issued_state'))
                : null,
            'residence_proof_path' => filled($this->input('residence_proof_path')) ? $this->input('residence_proof_path') : null,
        ];
    }
}
