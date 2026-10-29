<?php

namespace App\Http\Requests\Fleet;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        foreach (['imei', 'sim_number', 'sim_iccid'] as $field) {
            if ($this->has($field)) {
                $input[$field] = filled($this->input($field)) ? Text::digits($this->input($field)) : null;
            }
        }

        if ($this->has('tracking_id')) {
            $input['tracking_id'] = filled($this->input('tracking_id')) ? Text::plain($this->input('tracking_id')) : null;
        }

        $this->merge($input);
    }

    public function rules(): array
    {
        return [
            'device_model_id' => ['required', 'integer', 'exists:device_models,id'],
            'imei' => ['required', 'digits_between:8,20'],
            'tracking_id' => ['nullable', 'alpha_num:ascii', 'max:30'],
            'sim_number' => ['nullable', 'digits_between:10,13'],
            'sim_iccid' => ['nullable', 'digits_between:18,22'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_model_id.required' => 'Selecione o modelo',
            'device_model_id.exists' => 'Selecione um modelo da lista',
            'imei.required' => 'O IMEI é obrigatório',
            'imei.digits_between' => 'O IMEI deve ter de 8 a 20 dígitos',
            'tracking_id.alpha_num' => 'O identificador tem só letras e números',
            'tracking_id.max' => 'O identificador deve ter no máximo 30 caracteres',
            'sim_number.digits_between' => 'Informe o número do chip com DDD',
            'sim_iccid.digits_between' => 'O ICCID deve ter de 18 a 22 dígitos',
        ];
    }
}
