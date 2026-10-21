<?php

namespace App\Http\Requests\Registry\Driver;

use App\Rules\CpfCnpjForPersonType;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $entity = $this->input('entity');

        if (! is_array($entity)) {
            return;
        }

        if (array_key_exists('cpf_cnpj', $entity)) {
            $entity['cpf_cnpj'] = preg_replace('/\D/', '', (string) $entity['cpf_cnpj']) ?? '';
        }

        foreach (['rg_ie', 'emergency_phone', 'emergency_phone_type', 'document_file_path'] as $field) {
            if (! array_key_exists($field, $entity)) {
                continue;
            }

            $entity[$field] = filled($entity[$field]) ? $entity[$field] : null;
        }

        if (filled($entity['emergency_phone'] ?? null)) {
            $entity['emergency_phone'] = preg_replace('/\D/', '', (string) $entity['emergency_phone']) ?? '';
        }

        $this->merge([
            'entity' => $entity,
            'license_expires_on' => filled($this->input('license_expires_on')) ? $this->input('license_expires_on') : null,
            'license_issued_state' => filled($this->input('license_issued_state'))
                ? strtoupper((string) $this->input('license_issued_state'))
                : null,
            'residence_proof_path' => filled($this->input('residence_proof_path')) ? $this->input('residence_proof_path') : null,
            ...$this->blankPassword(),
        ]);
    }

    public function rules(): array
    {
        return [
            'entity.name' => ['required', 'string', 'max:255'],
            'entity.trade_name' => ['nullable', 'string', 'max:255'],
            'entity.person_type' => ['required', 'in:pf,pj'],
            'entity.cpf_cnpj' => ['required', 'string', 'max:18', 'bail', new CpfCnpjForPersonType],
            'entity.email' => ['nullable', 'email', 'max:255'],
            'entity.phone' => ['nullable', 'string', 'max:20'],
            'entity.rg_ie' => ['nullable', 'string', 'max:30'],
            'entity.emergency_phone' => ['nullable', 'string', 'max:20'],
            'entity.emergency_phone_type' => ['nullable', 'in:mobile,home,work,other'],
            'entity.document_file_path' => ['nullable', 'string', 'max:255'],
            'entity.state_id' => ['nullable', 'integer', 'exists:states,id'],
            'entity.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'entity.postal_code' => ['nullable', 'string', 'max:8'],
            'entity.street' => ['nullable', 'string', 'max:255'],
            'entity.street_number' => ['nullable', 'string', 'max:255'],
            'entity.complement' => ['nullable', 'string', 'max:255'],
            'entity.neighborhood' => ['nullable', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:20'],
            'license_category' => ['required', 'in:A,B,C,D,E,AB,AC,AD,AE,ACC'],
            'license_expires_on' => ['nullable', 'date_format:Y-m-d'],
            'license_issued_state' => ['nullable', 'string', 'size:2'],
            'residence_proof_path' => ['nullable', 'string', 'max:255'],
            'app_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'entity.name.required' => 'O nome é obrigatório',
            'entity.name.max' => 'O nome deve ter no máximo 255 caracteres',
            'entity.trade_name.max' => 'O nome fantasia deve ter no máximo 255 caracteres',
            'entity.person_type.required' => 'O tipo de pessoa é obrigatório',
            'entity.person_type.in' => 'O tipo de pessoa deve ser física ou jurídica',
            'entity.cpf_cnpj.required' => 'O CPF ou CNPJ é obrigatório',
            'entity.cpf_cnpj.max' => 'O CPF ou CNPJ deve ter no máximo 18 caracteres',
            'entity.email.email' => 'Informe um e-mail válido',
            'entity.phone.max' => 'O telefone deve ter no máximo 20 caracteres',
            'entity.rg_ie.max' => 'O RG ou a inscrição estadual deve ter no máximo 30 caracteres',
            'entity.emergency_phone.max' => 'O telefone de emergência deve ter no máximo 20 caracteres',
            'entity.emergency_phone_type.in' => 'O tipo do telefone de emergência é inválido',
            'entity.document_file_path.max' => 'O caminho do documento deve ter no máximo 255 caracteres',
            'entity.state_id.integer' => 'O estado informado é inválido',
            'entity.state_id.exists' => 'Selecione um estado válido',
            'entity.city_id.integer' => 'A cidade informada é inválida',
            'entity.city_id.exists' => 'Selecione uma cidade válida',
            'entity.postal_code.max' => 'O CEP deve ter no máximo 8 dígitos',
            'entity.street.max' => 'A rua deve ter no máximo 255 caracteres',
            'entity.street_number.max' => 'O número deve ter no máximo 255 caracteres',
            'entity.complement.max' => 'O complemento deve ter no máximo 255 caracteres',
            'entity.neighborhood.max' => 'O bairro deve ter no máximo 255 caracteres',
            'license_number.required' => 'O número da CNH é obrigatório',
            'license_number.max' => 'O número da CNH deve ter no máximo 20 caracteres',
            'license_category.required' => 'A categoria da CNH é obrigatória',
            'license_category.in' => 'A categoria da CNH é inválida',
            'license_expires_on.date_format' => 'Informe uma validade da CNH válida',
            'license_issued_state.size' => 'A UF da CNH deve ter 2 letras',
            'residence_proof_path.max' => 'O caminho do comprovante deve ter no máximo 255 caracteres',
            'app_password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'app_password.confirmed' => 'A confirmação da senha não confere',
        ];
    }

    /**
     * @return array<string, null>
     */
    private function blankPassword(): array
    {
        if (filled($this->input('app_password'))) {
            return [];
        }

        return [
            'app_password' => null,
            'app_password_confirmation' => null,
        ];
    }
}
