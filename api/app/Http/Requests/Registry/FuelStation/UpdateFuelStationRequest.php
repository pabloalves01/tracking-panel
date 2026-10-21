<?php

namespace App\Http\Requests\Registry\FuelStation;

use App\Rules\PixKeyForType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFuelStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            ...$this->normalizedPix(),
            ...$this->optionalAttributes(),
            ...$this->blankPassword(),
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
            'pix_key_type' => ['nullable', 'in:cpf,cnpj,email,phone,random'],
            'pix_key' => ['nullable', 'string', 'max:255', 'bail', new PixKeyForType],
            'login' => ['required', 'email', 'max:255', Rule::unique('fuel_stations', 'login')->ignore($this->fuelStationId())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            ...$this->stationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'pix_key_type.in' => 'O tipo da chave PIX é inválido',
            'pix_key.max' => 'A chave PIX deve ter no máximo 255 caracteres',
            'login.required' => 'O e-mail de acesso é obrigatório',
            'login.email' => 'Informe um e-mail de acesso válido',
            'login.max' => 'O e-mail de acesso deve ter no máximo 255 caracteres',
            'login.unique' => 'Este e-mail de acesso já está em uso',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'A confirmação da senha não confere',
            ...$this->stationMessages(),
        ];
    }

    private function fuelStationId(): ?int
    {
        return \App\Models\Registry\FuelStation::query()->where('uuid', $this->route('uuid'))->value('id');
    }

    /**
     * @return array<string, null>
     */
    private function blankPassword(): array
    {
        if (filled($this->input('password'))) {
            return [];
        }

        return [
            'password' => null,
            'password_confirmation' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionalAttributes(): array
    {
        $values = [];

        foreach ([
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
            'diesel_s10_price.numeric' => 'O preço do diesel S10 deve ser um número',
            'diesel_s500_price.numeric' => 'O preço do diesel S500 deve ser um número',
            'gasoline_price.numeric' => 'O preço da gasolina deve ser um número',
            'additive_gasoline_price.numeric' => 'O preço da gasolina aditivada deve ser um número',
            'status.required' => 'A situação é obrigatória',
            'status.in' => 'A situação é inválida',
        ];
    }
}
