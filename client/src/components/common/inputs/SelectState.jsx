import { useEffect, useState } from 'react'

import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { getStates } from '@/lib/location/locations'
import { notifyError } from '@/lib/notify'

/**
 * Lista dos estados do IBGE.
 *
 * `value` é o id da tabela `states`, ou null.
 * `onChange` recebe esse id, ou null quando o campo é limpo.
 */
export function SelectState({ id, value = null, onChange }) {
    const [states, setStates] = useState([])
    const [loading, setLoading] = useState(true)
    const selectedId = toId(value)

    useEffect(() => {
        let active = true

        getStates()
            .then((rows) => {
                if (active) setStates(rows ?? [])
            })
            .catch((error) => {
                if (active) notifyError(error)
            })
            .finally(() => {
                if (active) setLoading(false)
            })

        return () => {
            active = false
        }
    }, [])

    const items = states.map((state) => ({
        value: String(state.id),
        label: `${state.name} (${state.uf})`,
    }))

    function handleChange(nextValue) {
        if (!nextValue || nextValue === 'clear') {
            onChange?.(null)
            return
        }

        onChange?.(toId(nextValue))
    }

    return (
        <Select
            items={items}
            value={selectedId ? String(selectedId) : null}
            onValueChange={handleChange}
            disabled={loading}
        >
            <SelectTrigger id={id} className="w-full">
                <SelectValue placeholder={loading ? 'Carregando estados...' : 'Selecione o estado'} />
            </SelectTrigger>
            <SelectContent
                side="bottom"
                align="start"
                alignItemWithTrigger={false}
                collisionAvoidance={{ side: 'none', align: 'shift', fallbackAxisSide: 'none' }}
            >
                <SelectGroup>
                    {selectedId ? <SelectItem value="clear">Limpar</SelectItem> : null}
                    {items.map((state) => (
                        <SelectItem key={state.value} value={state.value}>
                            {state.label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    )
}

function toId(value) {
    if (value === null || value === undefined || value === '') {
        return null
    }

    const id = Number(value)

    return Number.isInteger(id) ? id : null
}
