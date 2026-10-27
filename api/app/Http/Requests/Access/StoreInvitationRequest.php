<?php

namespace App\Http\Requests\Access;

use App\Support\Membership;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('email')) {
            $payload['email'] = Text::email($this->input('email'));
        }

        if (is_array($this->input('emails'))) {
            $payload['emails'] = array_map(
                fn (mixed $email) => Text::email($email),
                $this->input('emails'),
            );
        }

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without:emails', 'nullable', 'email', 'max:255'],
            'emails' => ['required_without:email', 'array', 'min:1', 'max:20'],
            'emails.*' => ['string', 'max:255'],
            'role' => ['required', 'string', Rule::in([Membership::ADMIN, Membership::MEMBER])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório',
            'email.email' => 'Informe um e-mail válido',
            'role.required' => 'O papel é obrigatório',
            'role.in' => 'Papel inválido para este convite',
        ];
    }
}
