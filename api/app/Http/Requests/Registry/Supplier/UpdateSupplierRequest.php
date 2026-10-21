<?php

namespace App\Http\Requests\Registry\Supplier;

use App\Models\Registry\Supplier;
use App\Rules\PixKeyForType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            ...$this->normalizedPix(),
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
            'login' => ['nullable', 'string', 'max:255', Rule::unique('suppliers', 'login')->ignore($this->supplierId())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'pix_key_type.in' => 'O tipo da chave PIX é inválido',
            'pix_key.max' => 'A chave PIX deve ter no máximo 255 caracteres',
            'login.max' => 'O login deve ter no máximo 255 caracteres',
            'login.unique' => 'Este login já está em uso',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'A confirmação da senha não confere',
        ];
    }

    private function supplierId(): ?int
    {
        return Supplier::query()->where('uuid', $this->route('uuid'))->value('id');
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
}
