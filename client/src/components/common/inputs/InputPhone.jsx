import { formatWithMask } from 'use-mask-input'

import { Input } from '@/components/ui/input'

/**
 * Telefone com máscara na tela.
 *
 * `value` e `onChange` trabalham só com dígitos, que é o que vai para o banco.
 * Parênteses, espaço e hífen existem apenas na exibição.
 *
 * Até 10 dígitos é fixo: (00) 0000-0000.
 * Com 11 dígitos é celular: (00) 00000-0000.
 */
const phones = {
    landline: {
        pattern: '(99) 9999-9999',
    },
    mobile: {
        pattern: '(99) 99999-9999',
    },
}

const maskOptions = {
    placeholder: '',
    jitMasking: true,
}

export function InputPhone({ value = '', onChange, name, placeholder = '(00) 00000-0000', ...props }) {
    const digits = String(value).replace(/\D/g, '').slice(0, 11)
    const phone = digits.length > 10 ? phones.mobile : phones.landline
    const masked = digits ? formatWithMask(digits, phone.pattern, maskOptions) : ''

    function handleChange(event) {
        const typed = event.target.value
        let nextDigits = typed.replace(/\D/g, '').slice(0, 11)

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
                type="tel"
                inputMode="numeric"
                autoComplete="tel"
                placeholder={placeholder}
                value={masked}
                onChange={handleChange}
            />
        </>
    )
}
