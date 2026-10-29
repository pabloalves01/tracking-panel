import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { format, parseISO } from 'date-fns'

import { InputPhone } from '@/components/common/inputs/InputPhone'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Badge } from '@/components/ui/badge'
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { getDeviceModels } from '@/lib/fleet/catalog'
import { createDevice, getDevice, getDeviceInstallations, updateDevice } from '@/lib/fleet/devices'
import { INSTALLATION_ROLE_LABELS } from '@/lib/fleet/options'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/frota/rastreadores'

const emptyForm = {
    device_model_id: null,
    imei: '',
    tracking_id: '',
    sim_number: '',
    sim_iccid: '',
}

/**
 * O rastreador: o aparelho e o chip. Onde ele está instalado se resolve
 * na lista, em "Instalar" e "Transferir".
 */
export default function DeviceFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = Boolean(uuid)

    const [form, setForm] = useState(emptyForm)
    const [models, setModels] = useState([])
    const [vehicle, setVehicle] = useState(null)
    const [history, setHistory] = useState([])
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [errors, setErrors] = useState({})

    useEffect(() => {
        Promise.all([
            getDeviceModels(),
            isEditing ? getDevice(uuid) : null,
            isEditing ? getDeviceInstallations(uuid) : [],
        ])
            .then(([catalog, device, installations]) => {
                setModels(catalog)
                setHistory(installations)

                if (device) {
                    setForm({
                        device_model_id: device.model?.id ?? null,
                        imei: device.imei,
                        tracking_id: device.tracking_id ?? '',
                        sim_number: device.sim_number ?? '',
                        sim_iccid: device.sim_iccid ?? '',
                    })
                    setVehicle(device.vehicle)
                }
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [uuid, isEditing])

    function set(field, value) {
        setForm((current) => ({ ...current, [field]: value }))
        setErrors((current) => {
            if (!current[field]) return current
            const rest = { ...current }
            delete rest[field]
            return rest
        })
    }

    async function handleSubmit(event) {
        event?.preventDefault()
        setSaving(true)
        setErrors({})

        const payload = {
            ...form,
            tracking_id: form.tracking_id || null,
            sim_number: form.sim_number || null,
            sim_iccid: form.sim_iccid || null,
        }

        try {
            const response = isEditing ? await updateDevice(uuid, payload) : await createDevice(payload)
            notifySuccess(response)
            navigate(listPath)
        } catch (error) {
            notifyError(error)
            const fieldErrors = error?.response?.data?.errors ?? {}
            setErrors(Object.fromEntries(Object.entries(fieldErrors).map(([field, messages]) => [field, messages[0]])))
        } finally {
            setSaving(false)
        }
    }

    const error = (field) => errors[field] ?? ''
    const invalid = (field) => (errors[field] ? true : undefined)
    const manufacturers = [...new Set(models.map((model) => model.manufacturer))]
    const title = isEditing ? `Editar ${form.imei || 'rastreador'}` : 'Novo rastreador'

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Frota', to: '/app/cadastros/frota' },
                    { text: 'Rastreadores', to: listPath },
                    { text: title },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'ghost', to: listPath },
                    { title: saving ? 'Salvando…' : 'Salvar', onClick: handleSubmit, disabled: saving || loading },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form onSubmit={handleSubmit}>
                    {vehicle ? (
                        <p className="text-muted-foreground mb-5 flex items-center gap-2 text-sm">
                            Instalado em
                            <Link
                                className="text-foreground font-mono font-medium tracking-wider underline-offset-4 hover:underline"
                                to={`/app/cadastros/frota/veiculos/${vehicle.uuid}/editar?aba=equipamentos`}
                            >
                                {vehicle.plate}
                            </Link>
                            <Badge variant="secondary">{INSTALLATION_ROLE_LABELS[vehicle.role]}</Badge>
                        </p>
                    ) : null}

                    <FieldSet>
                        <FieldLegend variant="label">Aparelho</FieldLegend>
                        <FieldDescription>O IMEI é único no LogIV: o mesmo rastreador não é cadastrado duas vezes.</FieldDescription>
                        <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                            <Field className="col-span-12 md:col-span-4" data-invalid={invalid('device_model_id')}>
                                <FieldLabel htmlFor="device-model">Modelo</FieldLabel>
                                <Select
                                    items={models.map((model) => ({ value: model.id, label: model.label }))}
                                    value={form.device_model_id}
                                    onValueChange={(value) => set('device_model_id', value)}
                                >
                                    <SelectTrigger id="device-model" className="w-full" aria-invalid={invalid('device_model_id')}>
                                        <SelectValue placeholder="Selecione" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {manufacturers.map((manufacturer) => (
                                            <SelectGroup key={manufacturer}>
                                                <SelectLabel>{manufacturer}</SelectLabel>
                                                {models
                                                    .filter((model) => model.manufacturer === manufacturer)
                                                    .map((model) => (
                                                        <SelectItem key={model.id} value={model.id}>
                                                            {model.label}
                                                        </SelectItem>
                                                    ))}
                                            </SelectGroup>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError>{error('device_model_id')}</FieldError>
                            </Field>

                            <Field className="col-span-12 md:col-span-4" data-invalid={invalid('imei')}>
                                <FieldLabel htmlFor="imei">IMEI</FieldLabel>
                                <Input
                                    id="imei"
                                    inputMode="numeric"
                                    placeholder="15 dígitos"
                                    maxLength={20}
                                    value={form.imei}
                                    onChange={(event) => set('imei', event.target.value.replace(/\D/g, ''))}
                                    aria-invalid={invalid('imei')}
                                    className="font-mono tabular-nums"
                                />
                                <FieldError>{error('imei')}</FieldError>
                            </Field>

                            <Field className="col-span-12 md:col-span-4" data-invalid={invalid('tracking_id')}>
                                <FieldLabel htmlFor="tracking-id">
                                    ID na plataforma <span className="text-muted-foreground font-normal">(opcional)</span>
                                </FieldLabel>
                                <Input
                                    id="tracking-id"
                                    placeholder="Igual ao IMEI"
                                    maxLength={30}
                                    value={form.tracking_id}
                                    onChange={(event) => set('tracking_id', event.target.value.replace(/[^A-Za-z0-9]/g, ''))}
                                    aria-invalid={invalid('tracking_id')}
                                    className="font-mono"
                                />
                                <FieldDescription>Só quando o rastreador se identifica por número de série, e não pelo IMEI.</FieldDescription>
                                <FieldError>{error('tracking_id')}</FieldError>
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    <Separator className="my-6" />

                    <FieldSet>
                        <FieldLegend variant="label">Chip</FieldLegend>
                        <FieldDescription>O chip de dados que está no rastreador.</FieldDescription>
                        <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                            <Field className="col-span-12 md:col-span-4" data-invalid={invalid('sim_number')}>
                                <FieldLabel htmlFor="sim-number">Número</FieldLabel>
                                <InputPhone
                                    id="sim-number"
                                    value={form.sim_number}
                                    onChange={(digits) => set('sim_number', digits)}
                                    aria-invalid={invalid('sim_number')}
                                />
                                <FieldError>{error('sim_number')}</FieldError>
                            </Field>

                            <Field className="col-span-12 md:col-span-5" data-invalid={invalid('sim_iccid')}>
                                <FieldLabel htmlFor="sim-iccid">ICCID</FieldLabel>
                                <Input
                                    id="sim-iccid"
                                    inputMode="numeric"
                                    placeholder="Impresso no chip, de 18 a 22 dígitos"
                                    maxLength={22}
                                    value={form.sim_iccid}
                                    onChange={(event) => set('sim_iccid', event.target.value.replace(/\D/g, ''))}
                                    aria-invalid={invalid('sim_iccid')}
                                    className="font-mono tabular-nums"
                                />
                                <FieldError>{error('sim_iccid')}</FieldError>
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    {history.length > 0 ? (
                        <>
                            <Separator className="my-6" />
                            <FieldSet>
                                <FieldLegend variant="label">Histórico de instalações</FieldLegend>
                                <ul className="divide-y rounded-lg border text-sm">
                                    {history.map((item, index) => (
                                        <li key={index} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                                            <span className="font-mono tracking-wider">{item.vehicle.plate}</span>
                                            <Badge variant="secondary">{INSTALLATION_ROLE_LABELS[item.role]}</Badge>
                                            <span className="text-muted-foreground ml-auto text-xs tabular-nums">
                                                {format(parseISO(item.installed_at), 'dd/MM/yyyy HH:mm')} —{' '}
                                                {item.removed_at ? format(parseISO(item.removed_at), 'dd/MM/yyyy HH:mm') : 'instalado'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </FieldSet>
                        </>
                    ) : null}
                </form>
            </LoadingWrapper>
        </>
    )
}
