import { useState } from 'react'
import { PlusIcon } from 'lucide-react'

import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox'
import { notifyError } from '@/lib/notify'

const CREATE = '__create__'

/**
 * Escolha numa lista, com a opção de acrescentar o que faltar — marca,
 * modelo. Digitar um nome que não está na lista mostra "Adicionar …".
 *
 * `items`: `{ value, label }`. `value` e `onChange` usam só o `value`.
 * `onCreate(nome)` devolve uma Promise do item criado (ou do que já
 * existia com esse nome).
 */
export function InputCreatableSelect({
    id,
    items = [],
    value = null,
    onChange,
    onCreate,
    placeholder = 'Selecione',
    emptyText = 'Nada encontrado.',
    loading = false,
    disabled = false,
    'aria-invalid': ariaInvalid,
}) {
    const [query, setQuery] = useState('')
    const [creating, setCreating] = useState(false)

    const selected = items.find((item) => item.value === value) ?? null
    const term = query.trim()
    const searching = term !== '' && term !== selected?.label
    const matches = searching ? items.filter((item) => fold(item.label).includes(fold(term))) : items
    const exact = items.some((item) => fold(item.label) === fold(term))
    const options = searching && !exact && onCreate ? [...matches, { value: CREATE, label: term, create: true }] : matches

    async function handleChange(item) {
        if (!item?.create) {
            onChange?.(item?.value ?? null)
            return
        }

        setCreating(true)

        try {
            const created = await onCreate(item.label)
            onChange?.(created.value)
        } catch (error) {
            notifyError(error)
        } finally {
            setCreating(false)
        }
    }

    return (
        <Combobox
            items={options}
            value={selected}
            onValueChange={handleChange}
            onInputValueChange={setQuery}
            filter={null}
            itemToStringLabel={(item) => item?.label ?? ''}
            isItemEqualToValue={(item, other) => item.value === other.value}
            autoHighlight
        >
            <ComboboxInput
                id={id}
                className="w-full"
                placeholder={creating ? 'Adicionando…' : loading ? 'Carregando…' : placeholder}
                disabled={disabled || creating}
                showClear={selected != null}
                autoComplete="off"
                aria-invalid={ariaInvalid}
            />
            <ComboboxContent>
                <ComboboxEmpty>{loading ? 'Carregando…' : emptyText}</ComboboxEmpty>
                <ComboboxList>
                    {(item) => (
                        <ComboboxItem key={item.value} value={item}>
                            {item.create ? (
                                <span className="flex items-center gap-2">
                                    <PlusIcon className="size-4" />
                                    Adicionar “{item.label}”
                                </span>
                            ) : (
                                item.label
                            )}
                        </ComboboxItem>
                    )}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    )
}

function fold(text) {
    return String(text).normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
}
