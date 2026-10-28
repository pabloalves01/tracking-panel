<?php

namespace App\Http\Requests\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Entity;
use App\Rules\CpfCnpjForKind;
use App\Rules\PixKeyForType;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A validação do cadastro de entidades, uma vez só. Antes o mesmo
 * `prepareForValidation` estava copiado em catorze FormRequests.
 */
abstract class EntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O papel vem da rota, não do corpo: a tela de motoristas cadastra
     * motorista.
     */
    protected function role(): EntityRole
    {
        return $this->route('role');
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->has('document')) {
            $input['document'] = Text::digits($this->input('document'));
        }

        if ($this->has('postal_code')) {
            $input['postal_code'] = Text::digits($this->input('postal_code'));
        }

        foreach (['phone', 'emergency_phone'] as $field) {
            if ($this->has($field)) {
                $input[$field] = filled($this->input($field)) ? Text::digits($this->input($field)) : null;
            }
        }

        if ($this->has('email')) {
            $input['email'] = filled($this->input('email')) ? Text::email($this->input('email')) : null;
        }

        foreach (['trade_name', 'national_id', 'state_registration', 'pix_key', 'pix_key_type'] as $field) {
            if ($this->has($field)) {
                $input[$field] = filled($this->input($field)) ? $this->input($field) : null;
            }
        }

        $this->merge($input);
    }

    public function rules(): array
    {
        return [
            ...$this->entityRules(),
            ...$this->roleRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function entityRules(): array
    {
        return [
            'kind' => ['required', Rule::in([Entity::INDIVIDUAL, Entity::ORGANIZATION])],
            'document' => ['required', 'string', 'bail', new CpfCnpjForKind],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],

            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
            'emergency_phone_type' => ['nullable', Rule::in(['mobile', 'home', 'work', 'other'])],

            'national_id' => ['nullable', 'string', 'max:30'],
            'state_registration' => ['nullable', 'string', 'max:30'],

            'pix_key_type' => ['nullable', Rule::in(['cpf', 'cnpj', 'email', 'phone', 'random'])],
            'pix_key' => ['nullable', 'string', 'max:255', new PixKeyForType],

            'postal_code' => ['nullable', 'string', 'size:8'],
            'street' => ['nullable', 'string', 'max:255'],
            'street_number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ];
    }

    /**
     * Os campos que só um papel tem. Hoje, só o motorista.
     *
     * @return array<string, mixed>
     */
    protected function roleRules(): array
    {
        if ($this->role() !== EntityRole::Driver) {
            return [];
        }

        return [
            'license_number' => ['required', 'string', 'max:30'],
            'license_category' => ['required', 'string', 'max:10'],
            'license_expires_on' => ['nullable', 'date'],
            'license_issued_state' => ['nullable', 'string', 'size:2'],
            'residence_proof_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'Informe se é pessoa física ou jurídica',
            'kind.in' => 'O tipo de pessoa é inválido',
            'document.required' => 'O CPF ou CNPJ é obrigatório',
            'name.required' => 'O nome é obrigatório',
            'name.max' => 'O nome deve ter no máximo 255 caracteres',
            'trade_name.max' => 'O nome fantasia deve ter no máximo 255 caracteres',
            'email.email' => 'Informe um e-mail válido',
            'phone.max' => 'O telefone deve ter no máximo 20 caracteres',
            'emergency_phone.max' => 'O telefone de emergência deve ter no máximo 20 caracteres',
            'emergency_phone_type.in' => 'O tipo do telefone de emergência é inválido',
            'national_id.max' => 'O RG deve ter no máximo 30 caracteres',
            'state_registration.max' => 'A inscrição estadual deve ter no máximo 30 caracteres',
            'pix_key_type.in' => 'O tipo de chave PIX é inválido',
            'postal_code.size' => 'O CEP deve ter 8 dígitos',
            'state_id.exists' => 'Selecione um estado válido',
            'city_id.exists' => 'Selecione uma cidade válida',
            'license_number.required' => 'O número da CNH é obrigatório',
            'license_category.required' => 'A categoria da CNH é obrigatória',
            'license_expires_on.date' => 'A validade da CNH é inválida',
            'license_issued_state.size' => 'A UF de emissão deve ter 2 letras',
        ];
    }
}
