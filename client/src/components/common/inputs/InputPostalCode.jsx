import { formatWithMask, unformatWithMask } from 'use-mask-input'

import { Input } from '@/components/ui/input'

/**
 * CEP com máscara na tela.
 *
 * `value` e `onChange` trabalham só com dígitos, que é o que vai para o banco.
 * O hífen existe apenas na exibição: 00000-000.
 */
const pattern = '99999-999'
const length = 8

const maskOptions = {
    placeholder: '',
    jitMasking: true,
}

export function InputPostalCode({ value = '', onChange, name, placeholder = '00000-000', ...props }) {
    const digits = String(value).replace(/\D/g, '').slice(0, length)
    const masked = digits ? formatWithMask(digits, pattern, maskOptions) : ''

    function handleChange(event) {
        const typed = event.target.value
        let nextDigits = unformatWithMask(typed, pattern, maskOptions).slice(0, length)

        // Apagar o hífen não remove dígito. Nesse caso, tira o último número.
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
                autoComplete="postal-code"
                placeholder={placeholder}
                value={masked}
                onChange={handleChange}
            />
        </>
    )
}
