<?php

namespace App\Http\Requests\Registry\Supplier;

use App\Rules\PixKeyForType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedPix());
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
        ];
    }

    public function messages(): array
    {
        return [
            'pix_key_type.in' => 'O tipo da chave PIX é inválido',
            'pix_key.max' => 'A chave PIX deve ter no máximo 255 caracteres',
        ];
    }
}
