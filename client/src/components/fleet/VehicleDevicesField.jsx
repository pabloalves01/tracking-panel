import { XIcon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { InputRecordSearch } from '@/components/common/inputs/InputRecordSearch'
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { getDevices } from '@/lib/fleet/devices'
import { INSTALLATION_ROLES } from '@/lib/fleet/options'

const MAX_DEVICES = 5

/**
 * Os rastreadores instalados no veículo, cada um principal ou reserva.
 *
 * `value` é uma lista de `{ value: uuid, label: IMEI, description: modelo,
 * role }`. Há no máximo um principal: escolher outro rebaixa o anterior a
 * reserva. Rastreador instalado em outro veículo, ou inativo, aparece na
 * busca desabilitado — a transferência é feita pela tela do rastreador.
 */
export function VehicleDevicesField({ id, value = [], onChange, vehicleUuid = null }) {
    function search(term) {
        return getDevices({ search: term || undefined, per_page: 10 }).then((response) =>
            (response.data ?? [])
                .filter((device) => !value.some((item) => item.value === device.uuid))
                .map((device) => {
                    const elsewhere = device.vehicle && device.vehicle.uuid !== vehicleUuid
                    const inactive = device.status === 'inactive'

                    return {
                        value: device.uuid,
                        label: device.imei,
                        description: device.model?.label,
                        disabled: elsewhere || inactive,
                        hint: elsewhere ? `Em ${device.vehicle.plate}` : inactive ? 'Inativo' : undefined,
                    }
                })
        )
    }

    function add(item) {
        const role = value.some((other) => other.role === 'primary') ? 'backup' : 'primary'
        onChange([...value, { ...item, role }])
    }

    function setRole(uuid, role) {
        onChange(
            value.map((item) => {
                if (item.value === uuid) return { ...item, role }
                if (role === 'primary' && item.role === 'primary') return { ...item, role: 'backup' }

                return item
            })
        )
    }

    return (
        <div className="flex flex-col gap-2">
            {value.length > 0 ? (
                <ul className="divide-y rounded-lg border">
                    {value.map((item) => (
                        <li key={item.value} className="flex items-center gap-3 px-3 py-2">
                            <span className="min-w-0 flex-1">
                                <span className="font-mono text-sm tabular-nums">{item.label}</span>
                                {item.description ? (
                                    <span className="text-muted-foreground block truncate text-xs">{item.description}</span>
                                ) : null}
                            </span>
                            <Select
                                items={INSTALLATION_ROLES}
                                value={item.role}
                                onValueChange={(role) => role && setRole(item.value, role)}
                            >
                                <SelectTrigger className="w-32" aria-label={`Função do rastreador ${item.label}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {INSTALLATION_ROLES.map((role) => (
                                            <SelectItem key={role.value} value={role.value}>
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <ActionButton
                                icon={XIcon}
                                tooltip={`Retirar ${item.label}`}
                                variant="ghost"
                                onClick={() => onChange(value.filter((other) => other.value !== item.value))}
                            />
                        </li>
                    ))}
                </ul>
            ) : null}

            {value.length < MAX_DEVICES ? (
                <InputRecordSearch
                    key={value.length}
                    id={id}
                    value={null}
                    onChange={(item) => item && add(item)}
                    search={search}
                    placeholder={value.length === 0 ? 'IMEI ou número do chip' : 'Instalar outro rastreador'}
                    emptyText="Nenhum rastreador encontrado."
                    clearable={false}
                />
            ) : null}
        </div>
    )
}
