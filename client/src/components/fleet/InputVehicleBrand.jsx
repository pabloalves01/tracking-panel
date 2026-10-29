import { useEffect, useState } from 'react'

import { InputCreatableSelect } from '@/components/common/inputs/InputCreatableSelect'
import { createVehicleBrand, getVehicleBrands } from '@/lib/fleet/catalog'
import { notifyError } from '@/lib/notify'

const toItem = (brand) => ({ value: brand.id, label: brand.name })

/**
 * Marca do grupo (`motor_vehicle` ou `trailer`). A que faltar é
 * acrescentada ali mesmo e fica para a empresa.
 */
export function InputVehicleBrand({ kind, ...props }) {
    const [state, setState] = useState({ kind: null, items: [] })

    useEffect(() => {
        let active = true

        getVehicleBrands(kind)
            .then((brands) => active && setState({ kind, items: brands.map(toItem) }))
            .catch(notifyError)

        return () => {
            active = false
        }
    }, [kind])

    async function create(name) {
        const item = toItem(await createVehicleBrand(kind, name))
        setState((current) => ({
            ...current,
            items: current.items.some((other) => other.value === item.value) ? current.items : [...current.items, item],
        }))

        return item
    }

    return (
        <InputCreatableSelect
            items={state.items}
            loading={state.kind !== kind}
            onCreate={create}
            placeholder="Procurar ou adicionar"
            emptyText="Nenhuma marca encontrada."
            {...props}
        />
    )
}
