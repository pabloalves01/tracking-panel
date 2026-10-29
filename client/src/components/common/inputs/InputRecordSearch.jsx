import { useEffect, useRef, useState } from 'react'

import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox'
import { notifyError } from '@/lib/notify'

/**
 * Busca de um registro na API enquanto se digita — proprietário,
 * implemento, rastreador.
 *
 * `search(term)` devolve uma Promise de opções `{ value, label,
 * description?, disabled?, hint? }`. `value` é a opção escolhida (o mesmo
 * formato) ou null; `onChange` recebe a opção, ou null ao limpar.
 *
 * A lista abre com os primeiros resultados, sem precisar digitar, e a
 * resposta de uma busca superada por outra é descartada.
 */
export function InputRecordSearch({
    id,
    value = null,
    onChange,
    search,
    placeholder = 'Procurar',
    emptyText = 'Nada encontrado.',
    disabled = false,
    clearable = true,
    'aria-invalid': ariaInvalid,
}) {
    const [items, setItems] = useState([])
    const [loading, setLoading] = useState(false)
    const run = useRef(0)
    const timer = useRef(null)

    useEffect(() => () => clearTimeout(timer.current), [])

    function load(term) {
        clearTimeout(timer.current)
        const current = ++run.current
        setLoading(true)

        timer.current = setTimeout(() => {
            search(term)
                .then((options) => {
                    if (current === run.current) setItems(options)
                })
                .catch((error) => {
                    if (current === run.current) notifyError(error)
                })
                .finally(() => {
                    if (current === run.current) setLoading(false)
                })
        }, term ? 250 : 0)
    }

    function handleInput(term, details) {
        // Escolher uma opção também muda o texto do campo: aí não é busca.
        if (details?.reason === 'item-press') return
        load(term)
    }

    // A opção escolhida continua na lista, para o combobox achar o que
    // marcar mesmo depois de uma busca que não a traz.
    const options = value && !items.some((item) => item.value === value.value) ? [value, ...items] : items

    return (
        <Combobox
            items={options}
            value={value}
            onValueChange={(next) => onChange?.(next ?? null)}
            onInputValueChange={handleInput}
            onOpenChange={(open) => open && items.length === 0 && load('')}
            filter={null}
            itemToStringLabel={(item) => item?.label ?? ''}
            isItemEqualToValue={(item, selected) => item.value === selected.value}
            autoHighlight
        >
            <ComboboxInput
                id={id}
                className="w-full"
                placeholder={placeholder}
                disabled={disabled}
                showClear={clearable && value != null}
                autoComplete="off"
                aria-invalid={ariaInvalid}
            />
            <ComboboxContent>
                <ComboboxEmpty>{loading ? 'Procurando…' : emptyText}</ComboboxEmpty>
                <ComboboxList>
                    {(item) => (
                        <ComboboxItem key={item.value} value={item} disabled={item.disabled}>
                            <span className="flex min-w-0 flex-col">
                                <span className="truncate">{item.label}</span>
                                {item.description ? (
                                    <span className="text-muted-foreground truncate text-xs">{item.description}</span>
                                ) : null}
                            </span>
                            {item.hint ? (
                                <span className="text-muted-foreground ml-auto shrink-0 text-xs">{item.hint}</span>
                            ) : null}
                        </ComboboxItem>
                    )}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    )
}
