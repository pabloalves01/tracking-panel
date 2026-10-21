<?php

namespace App\Http\Requests\Registry\FuelStation;

use App\Rules\CpfCnpjForPersonType;
use App\Rules\PixKeyForType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuelStationRequest extends FormRequest
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
            ...$this->optionalAttributes(),
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
            'login' => ['required', 'email', 'max:255', Rule::unique('fuel_stations', 'login')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...$this->stationRules(),
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
            'login.required' => 'O e-mail de acesso é obrigatório',
            'login.email' => 'Informe um e-mail de acesso válido',
            'login.max' => 'O e-mail de acesso deve ter no máximo 255 caracteres',
            'login.unique' => 'Este e-mail de acesso já está em uso',
            'password.required' => 'A senha é obrigatória',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'A confirmação da senha não confere',
            ...$this->stationMessages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionalAttributes(): array
    {
        $values = [];

        foreach ([
            'contact_name',
            'credit_limit',
            'default_payment_method',
            'diesel_s10_price',
            'diesel_s500_price',
            'gasoline_price',
            'additive_gasoline_price',
        ] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $values[$field] = filled($this->input($field)) ? $this->input($field) : null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function stationRules(bool $statusRequired = false): array
    {
        return [
            'contact_name' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'default_payment_method' => ['nullable', 'in:cash,pix,invoice,debit,credit'],
            'diesel_s10_price' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],
            'diesel_s500_price' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],
            'gasoline_price' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],
            'additive_gasoline_price' => ['nullable', 'numeric', 'min:0', 'max:999999.999'],
            'status' => [$statusRequired ? 'required' : 'nullable', 'in:active,inactive,suspended'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stationMessages(): array
    {
        return [
            'contact_name.max' => 'O contato deve ter no máximo 255 caracteres',
            'credit_limit.numeric' => 'O limite de crédito deve ser um número',
            'credit_limit.min' => 'O limite de crédito não pode ser negativo',
            'default_payment_method.in' => 'A forma de pagamento é inválida',
            'diesel_s10_price.numeric' => 'O preço do diesel S10 deve ser um número',
            'diesel_s500_price.numeric' => 'O preço do diesel S500 deve ser um número',
            'gasoline_price.numeric' => 'O preço da gasolina deve ser um número',
            'additive_gasoline_price.numeric' => 'O preço da gasolina aditivada deve ser um número',
            'status.required' => 'A situação é obrigatória',
            'status.in' => 'A situação é inválida',
        ];
    }
}
