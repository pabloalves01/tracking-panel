import { formatWithMask, unformatWithMask } from 'use-mask-input'

import { Input } from '@/components/ui/input'

/**
 * CPF e CNPJ com máscara na tela.
 *
 * `value` e `onChange` trabalham só com dígitos, que é o que vai para o banco.
 * A pontuação existe apenas na exibição.
 *
 * `type`: `cpf` ou `cnpj`.
 */
const documents = {
    cpf: {
        pattern: '999.999.999-99',
        placeholder: '000.000.000-00',
        length: 11,
    },
    cnpj: {
        pattern: '99.999.999/9999-99',
        placeholder: '00.000.000/0000-00',
        length: 14,
    },
}

const maskOptions = {
    placeholder: '',
    jitMasking: true,
}

export function InputCpfCnpj({ type = 'cnpj', value = '', onChange, name, placeholder, ...props }) {
    const document = documents[type] ?? documents.cnpj
    const digits = String(value).replace(/\D/g, '').slice(0, document.length)
    const masked = digits ? formatWithMask(digits, document.pattern, maskOptions) : ''

    function handleChange(event) {
        const typed = event.target.value
        let nextDigits = unformatWithMask(typed, document.pattern, maskOptions).slice(0, document.length)

        // Apagar um ponto ou barra não remove dígito. Nesse caso, tira o último número.
        if (nextDigits === digits && typed.length < masked.length) {
            nextDigits = nextDigits.slice(0, -1)
        }

        onChange?.(nextDigits)
    }

    return (
        <>
            {name ? <input type="hidden" name={name} value={digits} /> : null}
            <Input
                {...props}
                inputMode="numeric"
                autoComplete="off"
                placeholder={placeholder ?? document.placeholder}
                value={masked}
                onChange={handleChange}
            />
        </>
    )
}
