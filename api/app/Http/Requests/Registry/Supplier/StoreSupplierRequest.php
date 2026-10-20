<?php

namespace App\Http\Requests\Registry\Supplier;

use App\Rules\CpfCnpjForPersonType;
use App\Rules\PixKeyForType;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
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

        $this->merge([
            'entity' => $entity,
            ...$this->normalizedPix(),
        ]);
    }

    /**
     * @return array{pix_key_type: ?string, pix_key: ?string}
     */
    private function normalizedPix(): array
    {
        $pixKey = trim((string) $this->input('pix_key', ''));
        $pixKeyType = $this->input('pix_key_type');

        if ($pixKey === '') {
            return [
                'pix_key_type' => null,
                'pix_key' => null,
            ];
        }

        if (in_array($pixKeyType, ['cpf', 'cnpj', 'phone'], true)) {
            $pixKey = preg_replace('/\D/', '', $pixKey) ?? '';
        }

        return [
            'pix_key_type' => $pixKeyType,
            'pix_key' => $pixKey,
        ];
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
            'entity.state_id' => ['nullable', 'integer', 'exists:states,id'],
            'entity.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'entity.postal_code' => ['nullable', 'string', 'max:8'],
            'entity.street' => ['nullable', 'string', 'max:255'],
            'entity.street_number' => ['nullable', 'string', 'max:255'],
            'entity.complement' => ['nullable', 'string', 'max:255'],
            'entity.neighborhood' => ['nullable', 'string', 'max:255'],
            'pix_key_type' => ['nullable', 'in:cpf,cnpj,email,phone,random'],
            'pix_key' => ['nullable', 'string', 'max:255', 'bail', new PixKeyForType],
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
            'entity.state_id.integer' => 'O estado informado é inválido',
            'entity.state_id.exists' => 'Selecione um estado válido',
            'entity.city_id.integer' => 'A cidade informada é inválida',
            'entity.city_id.exists' => 'Selecione uma cidade válida',
            'entity.postal_code.max' => 'O CEP deve ter no máximo 8 dígitos',
            'entity.street.max' => 'A rua deve ter no máximo 255 caracteres',
            'entity.street_number.max' => 'O número deve ter no máximo 255 caracteres',
            'entity.complement.max' => 'O complemento deve ter no máximo 255 caracteres',
            'entity.neighborhood.max' => 'O bairro deve ter no máximo 255 caracteres',
            'pix_key_type.in' => 'O tipo da chave PIX é inválido',
            'pix_key.max' => 'A chave PIX deve ter no máximo 255 caracteres',
        ];
    }
}
