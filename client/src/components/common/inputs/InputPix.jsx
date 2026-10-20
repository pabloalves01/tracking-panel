import { formatWithMask, unformatWithMask } from 'use-mask-input'

import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'

/**
 * Chave PIX num único campo: o tipo fica à esquerda e o valor à direita.
 *
 * `type` e `value` são o que vai para a API.
 * CPF, CNPJ e telefone guardam só dígitos. E-mail e chave aleatória guardam o texto.
 */
const pixKeyTypes = [
    {
        value: 'cpf',
        label: 'CPF',
        placeholder: '000.000.000-00',
        pattern: '999.999.999-99',
        length: 11,
        inputMode: 'numeric',
    },
    {
        value: 'cnpj',
        label: 'CNPJ',
        placeholder: '00.000.000/0000-00',
        pattern: '99.999.999/9999-99',
        length: 14,
        inputMode: 'numeric',
    },
    {
        value: 'email',
        label: 'E-mail',
        placeholder: 'nome@email.com',
        inputMode: 'email',
    },
    {
        value: 'phone',
        label: 'Telefone',
        placeholder: '(00) 00000-0000',
        pattern: '(99) 99999-9999',
        length: 11,
        inputMode: 'tel',
    },
    {
        value: 'random',
        label: 'Aleatória',
        placeholder: '00000000-0000-0000-0000-000000000000',
        inputMode: 'text',
    },
]

const maskOptions = {
    placeholder: '',
    jitMasking: true,
}

export function InputPix({ id, type = 'cpf', value = '', onTypeChange, onValueChange }) {
    const current = pixKeyTypes.find((item) => item.value === type) ?? pixKeyTypes[0]
    const usesMask = Boolean(current.pattern)
    const digits = usesMask ? String(value).replace(/\D/g, '').slice(0, current.length) : ''
    const masked = usesMask && digits ? formatWithMask(digits, current.pattern, maskOptions) : ''
    const displayValue = usesMask ? masked : value

    function handleTypeChange(event) {
        onTypeChange?.(event.target.value)
        onValueChange?.('')
    }

    function handleValueChange(event) {
        const typed = event.target.value

        if (!usesMask) {
            onValueChange?.(typed)
            return
        }

        let nextDigits = unformatWithMask(typed, current.pattern, maskOptions).slice(0, current.length)

        if (nextDigits === digits && typed.length < masked.length) {
            nextDigits = nextDigits.slice(0, -1)
        }

        onValueChange?.(nextDigits)
    }

    return (
        <InputGroup>
            <InputGroupAddon align="inline-start" className="h-full border-r border-input px-0 py-0 text-foreground">
                <select
                    aria-label="Tipo da chave PIX"
                    value={current.value}
                    onChange={handleTypeChange}
                    onClick={(event) => event.stopPropagation()}
                    className="h-full w-28 cursor-pointer appearance-none bg-transparent px-3 text-sm text-foreground outline-none"
                >
                    {pixKeyTypes.map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
            </InputGroupAddon>
            <InputGroupInput
                id={id}
                inputMode={current.inputMode}
                autoComplete="off"
                placeholder={current.placeholder}
                value={displayValue}
                onChange={handleValueChange}
            />
        </InputGroup>
    )
}
