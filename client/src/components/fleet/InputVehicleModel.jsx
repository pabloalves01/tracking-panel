import { useEffect, useState } from 'react'

import { InputCreatableSelect } from '@/components/common/inputs/InputCreatableSelect'
import { createVehicleModel, getVehicleModels } from '@/lib/fleet/catalog'
import { notifyError } from '@/lib/notify'

const toItem = (model) => ({ value: model.id, label: model.name })

/**
 * Modelo da marca escolhida. Sem marca, fica desabilitado. O que faltar é
 * acrescentado ali mesmo e fica para a empresa.
 */
export function InputVehicleModel({ brandId, disabled = false, ...props }) {
    const [state, setState] = useState({ brandId: null, items: [] })

    useEffect(() => {
        if (!brandId) return

        let active = true

        getVehicleModels(brandId)
            .then((models) => active && setState({ brandId, items: models.map(toItem) }))
            .catch(notifyError)

        return () => {
            active = false
        }
    }, [brandId])

    async function create(name) {
        const item = toItem(await createVehicleModel(brandId, name))
        setState((current) => ({
            ...current,
            items: current.items.some((other) => other.value === item.value) ? current.items : [...current.items, item],
        }))

        return item
    }

    return (
        <InputCreatableSelect
            items={state.brandId === brandId ? state.items : []}
            loading={Boolean(brandId) && state.brandId !== brandId}
            onCreate={brandId ? create : undefined}
            placeholder={brandId ? 'Procurar ou adicionar' : 'Escolha a marca primeiro'}
            emptyText="Nenhum modelo encontrado."
            disabled={disabled || !brandId}
            {...props}
        />
    )
}
