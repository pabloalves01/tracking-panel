import { useEffect, useState } from 'react'

import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox'
import { getCities } from '@/lib/location/locations'
import { notifyError } from '@/lib/notify'

/**
 * Busca de cidade do IBGE, limitada ao estado informado.
 *
 * Sem `stateId`, o campo fica desabilitado e não carrega municípios.
 * `value` é o id da tabela `cities`, ou null.
 * `onChange` recebe esse id, ou null quando o campo é limpo.
 */
export function InputCity({ id, stateId = null, value = null, onChange }) {
    const [cities, setCities] = useState([])
    const [loading, setLoading] = useState(false)
    const selectedStateId = toId(stateId)
    const selectedCityId = toId(value)

    useEffect(() => {
        if (!selectedStateId) {
            setCities([])
            setLoading(false)
            return
        }

        let active = true
        setCities([])
        setLoading(true)

        getCities(selectedStateId)
            .then((rows) => {
                if (active) setCities(rows ?? [])
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
    }, [selectedStateId])

    const items = cities.map((city) => ({
        value: toId(city.id),
        label: city.name,
    }))

    const selectedCity = items.find((city) => city.value === selectedCityId) ?? null
    const placeholder = !selectedStateId
        ? 'Selecione o estado primeiro'
        : loading
            ? 'Carregando cidades...'
            : 'Buscar cidade'

    function handleChange(city) {
        onChange?.(city ? toId(city.value) : null)
    }

    return (
        <Combobox
            key={selectedStateId ?? 'none'}
            items={items}
            value={selectedCity}
            onValueChange={handleChange}
            filter={cityMatches}
            isItemEqualToValue={(item, selected) => item.value === selected.value}
            autoHighlight
        >
            <ComboboxInput
                id={id}
                className="w-full"
                placeholder={placeholder}
                disabled={!selectedStateId}
                showClear={selectedCityId != null}
                autoComplete="off"
            />
            <ComboboxContent>
                <ComboboxEmpty>
                    {loading ? 'Carregando cidades...' : 'Nenhuma cidade encontrada.'}
                </ComboboxEmpty>
                <ComboboxList>
                    {(city) => (
                        <ComboboxItem key={city.value} value={city}>
                            {city.label}
                        </ComboboxItem>
                    )}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    )
}

function toId(value) {
    if (value === null || value === undefined || value === '') {
        return null
    }

    const id = Number(value)

    return Number.isInteger(id) ? id : null
}

/**
 * "sao" encontra "São Paulo". A lista já está limitada ao estado selecionado.
 */
function cityMatches(item, query) {
    const label = item && typeof item === 'object' ? item.label : item

    if (!query) {
        return true
    }

    return fold(label).includes(fold(query))
}

function fold(value) {
    return String(value).normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
}
