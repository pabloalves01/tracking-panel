import { formatWithMask, unformatWithMask } from 'use-mask-input'

import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/**
 * Telefone de emergência num único campo: o tipo fica à esquerda e o número à direita.
 *
 * `type` é o `emergency_phone_type` e `value` é o `emergency_phone`.
 * Os dois vão para a API com os mesmos nomes de sempre.
 * O número guarda só dígitos. A máscara (00) 00000-0000 existe só na tela.
 */
const phoneTypes = [
    { value: 'mobile', label: 'Celular' },
    { value: 'home', label: 'Fixo' },
    { value: 'work', label: 'Trabalho' },
    { value: 'other', label: 'Outro' },
]

const phonePattern = '(99) 99999-9999'
const phoneLength = 11

const maskOptions = {
    placeholder: '',
    jitMasking: true,
}

export function InputEmergencyNumber({
    id,
    type = 'mobile',
    value = '',
    onTypeChange,
    onValueChange,
    disabled = false,
}) {
    const current = phoneTypes.find((item) => item.value === type) ?? phoneTypes[0]
    const digits = String(value).replace(/\D/g, '').slice(0, phoneLength)
    const masked = digits ? formatWithMask(digits, phonePattern, maskOptions) : ''

    function handleTypeChange(nextType) {
        if (!nextType) {
            return
        }

        onTypeChange?.(nextType)
    }

    function handleValueChange(event) {
        const typed = event.target.value
        let nextDigits = unformatWithMask(typed, phonePattern, maskOptions).slice(0, phoneLength)

        if (nextDigits === digits && typed.length < masked.length) {
            nextDigits = nextDigits.slice(0, -1)
        }

        onValueChange?.(nextDigits)
    }

    return (
        <InputGroup data-disabled={disabled || undefined}>
            <InputGroupAddon align="inline-start" className="h-full border-r border-input px-0 py-0 text-foreground">
                <Select
                    items={phoneTypes}
                    value={current.value}
                    onValueChange={handleTypeChange}
                    disabled={disabled}
                >
                    <SelectTrigger
                        aria-label="Tipo do telefone de emergência"
                        disabled={disabled}
                        className="h-full w-36 rounded-none border-0 bg-transparent shadow-none focus-visible:ring-0"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent align="start" alignItemWithTrigger={false}>
                        <SelectGroup>
                            {phoneTypes.map((item) => (
                                <SelectItem key={item.value} value={item.value}>
                                    {item.label}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </InputGroupAddon>
            <InputGroupInput
                id={id}
                type="tel"
                inputMode="numeric"
                autoComplete="tel"
                placeholder="(00) 00000-0000"
                value={masked}
                onChange={handleValueChange}
                disabled={disabled}
            />
        </InputGroup>
    )
}
