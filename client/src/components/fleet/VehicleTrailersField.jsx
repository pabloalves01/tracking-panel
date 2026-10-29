import { ArrowDownIcon, ArrowUpIcon, XIcon } from 'lucide-react'

import { ActionButton } from '@/components/common/button/ActionButton'
import { InputRecordSearch } from '@/components/common/inputs/InputRecordSearch'
import { getVehicles } from '@/lib/fleet/vehicles'

const MAX_TRAILERS = 3

/**
 * Os implementos engatados, na ordem: o primeiro é a 1ª carreta.
 *
 * `value` é uma lista de `{ value: uuid, label: placa, description: tipo }`.
 * Implemento engatado em outro veículo aparece na busca, desabilitado, com
 * a placa de onde está: trocar de cavalo é desengatar lá antes.
 */
export function VehicleTrailersField({ id, value = [], onChange, vehicleUuid = null }) {
    function search(term) {
        return getVehicles({ kind: 'trailer', search: term || undefined, per_page: 10 }).then((response) =>
            (response.data ?? [])
                .filter((vehicle) => !value.some((item) => item.value === vehicle.uuid))
                .map((vehicle) => {
                    const elsewhere = vehicle.traction && vehicle.traction.uuid !== vehicleUuid

                    return {
                        value: vehicle.uuid,
                        label: vehicle.plate,
                        description: [vehicle.type.name, vehicle.body_type?.name].filter(Boolean).join(' · '),
                        disabled: elsewhere,
                        hint: elsewhere ? `Engatado em ${vehicle.traction.plate}` : undefined,
                    }
                })
        )
    }

    function move(index, offset) {
        const next = [...value]
        const [item] = next.splice(index, 1)
        next.splice(index + offset, 0, item)
        onChange(next)
    }

    return (
        <div className="flex flex-col gap-2">
            {value.length > 0 ? (
                <ol className="divide-y rounded-lg border">
                    {value.map((item, index) => (
                        <li key={item.value} className="flex items-center gap-3 px-3 py-2">
                            <span className="text-muted-foreground w-16 shrink-0 text-xs">{index + 1}ª posição</span>
                            <span className="min-w-0 flex-1">
                                <span className="font-mono text-sm font-medium tracking-wider">{item.label}</span>
                                {item.description ? (
                                    <span className="text-muted-foreground block truncate text-xs">{item.description}</span>
                                ) : null}
                            </span>
                            <div className="flex shrink-0 gap-1">
                                <ActionButton
                                    icon={ArrowUpIcon}
                                    tooltip="Mover para cima"
                                    variant="ghost"
                                    disabled={index === 0}
                                    onClick={() => move(index, -1)}
                                />
                                <ActionButton
                                    icon={ArrowDownIcon}
                                    tooltip="Mover para baixo"
                                    variant="ghost"
                                    disabled={index === value.length - 1}
                                    onClick={() => move(index, 1)}
                                />
                                <ActionButton
                                    icon={XIcon}
                                    tooltip={`Desengatar ${item.label}`}
                                    variant="ghost"
                                    onClick={() => onChange(value.filter((other) => other.value !== item.value))}
                                />
                            </div>
                        </li>
                    ))}
                </ol>
            ) : null}

            {value.length < MAX_TRAILERS ? (
                <InputRecordSearch
                    // Reinicia o campo depois de cada escolha.
                    key={value.length}
                    id={id}
                    value={null}
                    onChange={(item) => item && onChange([...value, item])}
                    search={search}
                    placeholder={value.length === 0 ? 'Placa do implemento' : 'Engatar outro implemento'}
                    emptyText="Nenhum implemento encontrado."
                    clearable={false}
                />
            ) : (
                <p className="text-muted-foreground text-xs">Um veículo leva no máximo {MAX_TRAILERS} implementos.</p>
            )}
        </div>
    )
}
