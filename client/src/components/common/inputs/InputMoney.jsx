import { Input } from '@/components/ui/input'
import { formatMoney } from '@/helpers/formatters'

/**
 * Dinheiro em real, no mesmo campo visual dos outros inputs.
 *
 * A digitação entra pela menor casa: com 2 casas, 1, 2, 3, 4 mostra
 * R$ 0,01, R$ 0,12, R$ 1,23 e R$ 12,34. Com 3 casas, o mesmo
 * percurso termina em R$ 1,234.
 * `decimals` é 2 por padrão. O preço do combustível usa 3.
 * `value` e `onChange` usam o número cru que a API grava, por exemplo 12.34.
 * A máscara existe só na tela e reaproveita `formatMoney`.
 */
const maxDigits = 12

function digitsFromValue(value, decimals) {
    if (value === null || value === undefined || value === '') {
        return ''
    }

    const amount = Number(value)

    if (!Number.isFinite(amount)) {
        return ''
    }

    return Math.abs(amount).toFixed(decimals).replace('.', '')
}

function amountFromDigits(digits, decimals) {
    if (!digits) {
        return ''
    }

    const scale = 10 ** decimals

    return Number((Number(digits) / scale).toFixed(decimals))
}

export function InputMoney({
    id,
    value = '',
    onChange,
    disabled = false,
    decimals = 2,
    placeholder,
    name,
}) {
    const places = Number.isInteger(decimals) && decimals >= 0 ? decimals : 2
    const digits = digitsFromValue(value, places).replace(/^0+(?=\d)/, '')
    const display = digits ? formatMoney(amountFromDigits(digits, places), places) : ''
    const resolvedPlaceholder = placeholder ?? `R$ 0,${'0'.repeat(places) || '00'}`

    function handleChange(event) {
        const typed = event.target.value

        if (typed === '') {
            onChange?.('')
            return
        }

        const nextDigits = typed.replace(/\D/g, '').replace(/^0+/, '').slice(0, maxDigits)
        onChange?.(amountFromDigits(nextDigits, places))
    }

    return (
        <Input
            id={id}
            name={name}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder={resolvedPlaceholder}
            value={display}
            onChange={handleChange}
            disabled={disabled}
        />
    )
}
