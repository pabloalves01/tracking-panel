import { Input } from '@/components/ui/input'
import { formatMoney } from '@/helpers/formatters'

/**
 * Dinheiro em real, no mesmo campo visual dos outros inputs.
 *
 * A digitação entra pelos centavos: 1, 2, 3, 4 mostra
 * R$ 0,01, R$ 0,12, R$ 1,23 e R$ 12,34.
 * `value` e `onChange` usam o número cru que a API grava, por exemplo 12.34.
 * A máscara existe só na tela e reaproveita `formatMoney`.
 */
const maxDigits = 12

function centsFromValue(value) {
    if (value === null || value === undefined || value === '') {
        return ''
    }

    const amount = Number(value)

    if (!Number.isFinite(amount)) {
        return ''
    }

    return String(Math.round(Math.abs(amount) * 100))
}

function amountFromCents(cents) {
    if (!cents) {
        return ''
    }

    return Number((Number(cents) / 100).toFixed(2))
}

export function InputMoney({
    id,
    value = '',
    onChange,
    disabled = false,
    placeholder = 'R$ 0,00',
    name,
}) {
    const cents = centsFromValue(value).replace(/^0+(?=\d)/, '')
    const display = cents ? formatMoney(amountFromCents(cents)) : ''

    function handleChange(event) {
        const typed = event.target.value

        if (typed === '') {
            onChange?.('')
            return
        }

        const nextCents = typed.replace(/\D/g, '').replace(/^0+/, '').slice(0, maxDigits)
        onChange?.(amountFromCents(nextCents))
    }

    return (
        <Input
            id={id}
            name={name}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder={placeholder}
            value={display}
            onChange={handleChange}
            disabled={disabled}
        />
    )
}
