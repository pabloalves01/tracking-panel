<?php

namespace App\Http\Requests\Registry\CommonEntity;

use App\Models\Registry\CommonEntity;
use App\Rules\CpfCnpjForPersonType;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommonEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $document = preg_replace('/\D/', '', (string) $this->input('cpf_cnpj', '')) ?? '';

        $this->merge([
            'cpf_cnpj' => $document,
            'entity' => [
                'person_type' => $this->input('person_type'),
            ],
        ]);
    }

    public function rules(): array
    {
        $entity = CommonEntity::query()->where('uuid', $this->route('uuid'))->first();

        $documentRules = ['required', 'string', 'max:18', 'bail', new CpfCnpjForPersonType];

        if ($entity) {
            $documentRules[] = Rule::unique('common_entities', 'cpf_cnpj')
                ->where(fn ($query) => $query->where('company_id', app(CurrentCompany::class)->id))
                ->ignore($entity->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'person_type' => ['required', 'in:pf,pj'],
            'cpf_cnpj' => $documentRules,
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'postal_code' => ['nullable', 'string', 'max:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:255'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório',
            'name.max' => 'O nome deve ter no máximo 255 caracteres',
            'trade_name.max' => 'O nome fantasia deve ter no máximo 255 caracteres',
            'person_type.required' => 'O tipo de pessoa é obrigatório',
            'person_type.in' => 'O tipo de pessoa deve ser física ou jurídica',
            'cpf_cnpj.required' => 'O CPF ou CNPJ é obrigatório',
            'cpf_cnpj.max' => 'O CPF ou CNPJ deve ter no máximo 18 caracteres',
            'cpf_cnpj.unique' => 'Este CPF ou CNPJ já está cadastrado',
            'email.email' => 'Informe um e-mail válido',
            'phone.max' => 'O telefone deve ter no máximo 20 caracteres',
            'state_id.integer' => 'O estado informado é inválido',
            'state_id.exists' => 'Selecione um estado válido',
            'city_id.integer' => 'A cidade informada é inválida',
            'city_id.exists' => 'Selecione uma cidade válida',
            'postal_code.max' => 'O CEP deve ter no máximo 8 dígitos',
            'street.max' => 'A rua deve ter no máximo 255 caracteres',
            'street_number.max' => 'O número deve ter no máximo 255 caracteres',
            'complement.max' => 'O complemento deve ter no máximo 255 caracteres',
            'neighborhood.max' => 'O bairro deve ter no máximo 255 caracteres',
        ];
    }
}
