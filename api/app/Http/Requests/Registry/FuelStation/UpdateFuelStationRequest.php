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
            ...$this->stationRules(true),
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
            'contact_name',
            'credit_limit',
            'default_payment_method',
            'diesel_s10_price',
            'diesel_s500_price',
            'gasoline_price',
            'additive_gasoline_price',
            'billing_recurrence',
            'billing_cutoff_day',
            'billing_interval_days',
            'billing_cycle_starts_on',
            'billing_due_days',
        ] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $values[$field] = filled($this->input($field)) ? $this->input($field) : null;
        }

        if ($this->exists('billing_last_day_of_month')) {
            $values['billing_last_day_of_month'] = filter_var($this->input('billing_last_day_of_month'), FILTER_VALIDATE_BOOLEAN);
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
            'billing_recurrence' => ['nullable', 'in:weekly,biweekly,monthly,custom'],
            'billing_cutoff_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'billing_last_day_of_month' => ['nullable', 'boolean'],
            'billing_interval_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'billing_cycle_starts_on' => ['nullable', 'date_format:Y-m-d'],
            'billing_due_days' => ['nullable', 'integer', 'min:0', 'max:366'],
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
            'billing_recurrence.in' => 'A recorrência de faturamento é inválida',
            'billing_cutoff_day.integer' => 'O dia de corte deve ser um número',
            'billing_cutoff_day.min' => 'O dia de corte deve ser entre 1 e 31',
            'billing_cutoff_day.max' => 'O dia de corte deve ser entre 1 e 31',
            'billing_cycle_starts_on.date_format' => 'Informe uma data de início válida',
            'billing_due_days.integer' => 'Os dias para vencimento devem ser um número',
            'status.required' => 'A situação é obrigatória',
            'status.in' => 'A situação é inválida',
        ];
    }
}
