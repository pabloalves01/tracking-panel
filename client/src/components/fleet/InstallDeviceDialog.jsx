import { useState } from 'react'

import { InputRecordSearch } from '@/components/common/inputs/InputRecordSearch'
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field'
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { installDevice } from '@/lib/fleet/devices'
import { INSTALLATION_ROLES } from '@/lib/fleet/options'
import { getVehicles } from '@/lib/fleet/vehicles'
import { notifyError, notifySuccess } from '@/lib/notify'

const roles = [{ value: 'auto', label: 'Automático' }, ...INSTALLATION_ROLES]

/**
 * Instalar o rastreador num veículo — ou transferi-lo, quando ele já está
 * em outro. Sem função escolhida, entra como principal se o veículo não
 * tiver um, e como reserva se tiver.
 */
export function InstallDeviceDialog({ device, onClose, onDone }) {
    const [vehicle, setVehicle] = useState(null)
    const [role, setRole] = useState('auto')
    const [saving, setSaving] = useState(false)

    const current = device?.vehicle ?? null

    function search(term) {
        return getVehicles({ search: term || undefined, per_page: 10 }).then((response) =>
            (response.data ?? [])
                .filter((item) => item.uuid !== current?.uuid)
                .map((item) => ({
                    value: item.uuid,
                    label: item.plate,
                    description: [item.type.name, `${item.brand} ${item.model}`].join(' · '),
                    hint: item.devices.length > 0 ? `${item.devices.length} rastreador${item.devices.length > 1 ? 'es' : ''}` : undefined,
                }))
        )
    }

    async function handleConfirm() {
        if (!vehicle) return
        setSaving(true)

        try {
            notifySuccess(
                await installDevice(device.uuid, {
                    vehicle: vehicle.value,
                    role: role === 'auto' ? null : role,
                    transfer: Boolean(current),
                })
            )
            onDone()
        } catch (error) {
            notifyError(error)
        } finally {
            setSaving(false)
        }
    }

    return (
        <AlertDialog open={!!device} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        {current ? 'Transferir' : 'Instalar'} rastreador {device?.imei}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {current
                            ? `Ele sai de ${current.plate} e passa para o veículo escolhido. As duas instalações ficam no histórico.`
                            : 'Escolha o veículo em que ele foi instalado.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="flex flex-col gap-4">
                    <Field>
                        <FieldLabel htmlFor="install-vehicle">Veículo</FieldLabel>
                        <InputRecordSearch
                            id="install-vehicle"
                            value={vehicle}
                            onChange={setVehicle}
                            search={search}
                            placeholder="Placa do veículo"
                            emptyText="Nenhum veículo encontrado."
                        />
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="install-role">Função</FieldLabel>
                        <Select items={roles} value={role} onValueChange={(value) => value && setRole(value)}>
                            <SelectTrigger id="install-role" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {roles.map((item) => (
                                        <SelectItem key={item.value} value={item.value}>
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <FieldDescription>
                            Automático: principal se o veículo ainda não tiver um; reserva se tiver.
                        </FieldDescription>
                    </Field>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <Button type="button" disabled={!vehicle || saving} onClick={handleConfirm}>
                        {saving ? 'Salvando…' : current ? 'Transferir' : 'Instalar'}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    )
}
