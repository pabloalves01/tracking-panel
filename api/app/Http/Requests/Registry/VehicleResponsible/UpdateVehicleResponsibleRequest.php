<?php

namespace App\Http\Requests\Registry\VehicleResponsible;

use App\Rules\PixKeyForType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleResponsibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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
            'pix_key_type' => ['required', 'in:cpf,cnpj,email,phone,random'],
            'pix_key' => ['required', 'string', 'max:255', 'bail', new PixKeyForType],
        ];
    }

    public function messages(): array
    {
        return [
            'pix_key_type.required' => 'O tipo da chave PIX é obrigatório',
            'pix_key_type.in' => 'O tipo da chave PIX é inválido',
            'pix_key.required' => 'A chave PIX é obrigatória',
            'pix_key.max' => 'A chave PIX deve ter no máximo 255 caracteres',
        ];
    }

}
