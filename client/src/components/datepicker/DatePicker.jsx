import { useState } from 'react'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { CalendarIcon } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'

/**
 * Data em um popover.
 *
 * `value` e `onChange` usam `aaaa-mm-dd`, o formato que a API grava.
 * A tela mostra `dd/mm/aaaa`.
 */
export function DatePicker({
    id,
    value,
    onChange,
    disabled = false,
    placeholder = 'dd/mm/aaaa',
    fromYear = 1920,
    toYear = new Date().getFullYear() + 20,
}) {
    const [open, setOpen] = useState(false)
    const selected = parseIsoDate(value)

    function handleSelect(date) {
        onChange?.(date ? toIsoDate(date) : '')
        setOpen(false)
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                id={id}
                disabled={disabled}
                render={
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        className={cn(
                            'w-full justify-start rounded-2xl px-3 font-normal',
                            !selected && 'text-muted-foreground',
                        )}
                    />
                }
            >
                <CalendarIcon />
                {selected ? format(selected, 'dd/MM/yyyy', { locale: ptBR }) : placeholder}
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    locale={ptBR}
                    selected={selected}
                    onSelect={handleSelect}
                    captionLayout="dropdown"
                    startMonth={new Date(fromYear, 0)}
                    endMonth={new Date(toYear, 11)}
                    defaultMonth={selected ?? new Date()}
                />
            </PopoverContent>
        </Popover>
    )
}

function parseIsoDate(value) {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return undefined
    }

    const [year, month, day] = value.split('-').map(Number)
    const date = new Date(year, month - 1, day)

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return undefined
    }

    return date
}

function toIsoDate(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}
