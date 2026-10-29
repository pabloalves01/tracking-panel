import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { format, parseISO } from 'date-fns'
import { InfoIcon, TriangleAlertIcon, UserCheckIcon } from 'lucide-react'

import { InputPlate } from '@/components/common/inputs/InputPlate'
import { SelectUf } from '@/components/common/inputs/SelectUf'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { TabButton } from '@/components/common/TabButton'
import { DatePicker } from '@/components/datepicker/DatePicker'
import { InputVehicleBrand } from '@/components/fleet/InputVehicleBrand'
import { InputVehicleModel } from '@/components/fleet/InputVehicleModel'
import { SelectVehicleColor } from '@/components/fleet/SelectVehicleColor'
import { VehicleDevicesField } from '@/components/fleet/VehicleDevicesField'
import { VehicleTrailersField } from '@/components/fleet/VehicleTrailersField'
import { InputEntityRole } from '@/components/registry/InputEntityRole'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
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
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { formatCpfCnpj, formatPhone } from '@/helpers/formatters'
import { getBodyTypes, getVehicleTypes } from '@/lib/fleet/catalog'
import { FLEET_KINDS, FLEET_TYPES, vehiclePath } from '@/lib/fleet/options'
import { createVehicle, getVehicle, getVehicleCouplings, updateVehicle } from '@/lib/fleet/vehicles'
import { notifyError, notifySuccess } from '@/lib/notify'
import { createEntity, getEntityByDocument } from '@/lib/registry/entities'
import { roleOption } from '@/lib/registry/roleOption'

const tabKeys = ['geral', 'vinculos', 'capacidades', 'equipamentos']

/** Em que aba mora cada campo — é para lá que a tela vai quando ele dá erro. */
function tabOf(field) {
    if (['fleet_type', 'owner', 'responsible', 'carrier'].includes(field)) return 'vinculos'
    if (/^(axle_count|tare_weight_kg|load_capacity_kg|gross_weight_kg|max_traction_weight_kg|volume_capacity_m3)$/.test(field)) {
        return 'capacidades'
    }
    if (field.startsWith('trailers') || field.startsWith('devices')) return 'equipamentos'

    return 'geral'
}

const numberFields = [
    'manufacture_year',
    'model_year',
    'axle_count',
    'tare_weight_kg',
    'load_capacity_kg',
    'gross_weight_kg',
    'max_traction_weight_kg',
    'volume_capacity_m3',
]

const emptyForm = {
    vehicle_type_id: null,
    body_type_id: null,
    fleet_type: 'own',
    plate: '',
    plate_state: '',
    renavam: '',
    chassis: '',
    registration_expires_on: null,
    vehicle_brand_id: null,
    vehicle_model_id: null,
    color: '',
    manufacture_year: '',
    model_year: '',
    axle_count: '',
    tare_weight_kg: '',
    load_capacity_kg: '',
    gross_weight_kg: '',
    max_traction_weight_kg: '',
    volume_capacity_m3: '',
    owner: null,
    responsible: null,
    carrier: null,
    trailers: [],
    devices: [],
}

function formFrom(vehicle) {
    const form = { ...emptyForm }

    for (const key of Object.keys(emptyForm)) {
        if (vehicle[key] !== undefined && vehicle[key] !== null && typeof vehicle[key] !== 'object') {
            form[key] = numberFields.includes(key) ? String(vehicle[key]) : vehicle[key]
        }
    }

    return {
        ...form,
        vehicle_type_id: vehicle.type.id,
        body_type_id: vehicle.body_type?.id ?? null,
        vehicle_brand_id: vehicle.brand?.id ?? null,
        vehicle_model_id: vehicle.model?.id ?? null,
        owner: roleOption(vehicle.owner),
        responsible: roleOption(vehicle.responsible),
        carrier: roleOption(vehicle.carrier),
        trailers: vehicle.trailers.map((trailer) => ({
            value: trailer.uuid,
            label: trailer.plate,
            description: trailer.type,
        })),
        devices: vehicle.devices.map((device) => ({
            value: device.uuid,
            label: device.imei,
            description: device.model,
            role: device.role,
        })),
    }
}

/**
 * O que o tipo não tem não vai: carroceria, lotação e volume sem
 * carroceria; CMT e implementos sem tração; transportador na frota
 * própria. A API ignora do mesmo jeito.
 */
function payloadFrom(form, type) {
    const payload = { ...form }

    for (const key of numberFields) {
        payload[key] = form[key] === '' ? null : Number(form[key])
    }

    if (!type?.has_body) {
        delete payload.body_type_id
        delete payload.load_capacity_kg
        delete payload.volume_capacity_m3
    }

    return {
        ...payload,
        color: form.color || null,
        owner: form.owner?.value ?? null,
        responsible: form.responsible?.value ?? null,
        carrier: form.fleet_type === 'own' ? null : (form.carrier?.value ?? null),
        max_traction_weight_kg: type?.can_tow ? payload.max_traction_weight_kg : null,
        trailers: type?.can_tow ? form.trailers.map((item) => item.value) : [],
        devices: form.devices.map((item) => ({ device: item.value, role: item.role })),
    }
}

/**
 * O formulário de veículo ou de implemento — o mesmo cadastro, em telas
 * separadas. O grupo é o da tela; dentro dele, os campos seguem o tipo
 * escolhido: carroceria, lotação e volume só para quem leva carga; CMT e
 * implementos só para quem puxa.
 */
export default function VehicleFormPage({ kind }) {
    const config = FLEET_KINDS[kind]
    const listPath = config.path
    const { uuid } = useParams()
    const navigate = useNavigate()
    const [searchParams, setSearchParams] = useSearchParams()
    const isEditing = Boolean(uuid)

    const [form, setForm] = useState(emptyForm)
    const [vehicleTypes, setVehicleTypes] = useState([])
    const [bodyTypes, setBodyTypes] = useState([])
    const [traction, setTraction] = useState(null)
    const [history, setHistory] = useState([])
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [linking, setLinking] = useState(false)
    const [errors, setErrors] = useState({})

    const requested = searchParams.get('aba')
    const tab = tabKeys.includes(requested) ? requested : 'geral'

    const types = vehicleTypes.filter((item) => item.kind === kind)
    const type = types.find((item) => item.id === form.vehicle_type_id) ?? null
    const hasBody = Boolean(type?.has_body)
    const canTow = Boolean(type?.can_tow)

    const tabs = [
        { key: 'geral', label: 'Dados gerais' },
        { key: 'vinculos', label: 'Vínculos' },
        { key: 'capacidades', label: 'Pesos e capacidades' },
        { key: 'equipamentos', label: canTow ? 'Implementos e rastreadores' : 'Rastreadores' },
    ]

    function selectTab(next) {
        const params = new URLSearchParams(searchParams)

        if (next === 'geral') params.delete('aba')
        else params.set('aba', next)

        setSearchParams(params, { replace: true })
    }

    useEffect(() => {
        Promise.all([
            getVehicleTypes(),
            getBodyTypes(),
            isEditing ? getVehicle(uuid) : null,
            isEditing ? getVehicleCouplings(uuid) : [],
        ])
            .then(([types, bodies, vehicle, couplings]) => {
                setVehicleTypes(types)
                setBodyTypes(bodies)
                setHistory(couplings)

                if (vehicle) {
                    setForm(formFrom(vehicle))
                    setTraction(vehicle.traction)
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

    /**
     * O proprietário vira o responsável. Se ele ainda não for responsável,
     * o papel é criado com os dados que já estão no cadastro dele.
     */
    async function assignOwnerAsResponsible() {
        if (!form.owner) return
        setLinking(true)

        try {
            const entity = await getEntityByDocument(form.owner.document)
            const existing = entity?.roles?.find((item) => item.role === 'vehicle_responsible')
            const responsibleUuid = existing
                ? existing.uuid
                : (await createEntity('vehicle_responsible', entity)).data.uuid

            set('responsible', { ...form.owner, value: responsibleUuid })
        } catch (error) {
            notifyError(error)
        } finally {
            setLinking(false)
        }
    }

    async function handleSubmit(event) {
        event?.preventDefault()
        setSaving(true)
        setErrors({})

        try {
            const payload = payloadFrom(form, type)
            const response = isEditing ? await updateVehicle(uuid, payload) : await createVehicle(payload)

            notifySuccess(response)
            navigate(listPath)
        } catch (error) {
            notifyError(error)

            const fieldErrors = error?.response?.data?.errors ?? {}
            const fields = Object.keys(fieldErrors)

            if (fields.length > 0) {
                setErrors(Object.fromEntries(fields.map((field) => [field, fieldErrors[field][0]])))
                selectTab(tabOf(fields[0]))
            }
        } finally {
            setSaving(false)
        }
    }

    const errorTabs = [...new Set(Object.keys(errors).map(tabOf))]
    const error = (field) => errors[field] ?? ''
    // `trailers.1`, `devices.0.device`: o primeiro erro da lista inteira.
    const listError = (prefix) =>
        Object.entries(errors).find(([field]) => field === prefix || field.startsWith(`${prefix}.`))?.[1] ?? ''
    const invalid = (field) => (errors[field] ? true : undefined)
    const title = isEditing ? `Editar ${form.plate || config.singular}` : config.create
    const sameAsOwner = form.owner && form.responsible && form.owner.document === form.responsible.document

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Frota', to: '/app/cadastros/frota' },
                    { text: config.plural, to: listPath },
                    { text: title },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'ghost', to: listPath },
                    { title: saving ? 'Salvando…' : 'Salvar', onClick: handleSubmit, disabled: saving || loading },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form onSubmit={handleSubmit}>
                    <div role="tablist" className="mb-6 flex gap-1 overflow-x-auto border-b">
                        {tabs.map((item) => (
                            <TabButton
                                key={item.key}
                                active={tab === item.key}
                                alert={tab !== item.key && errorTabs.includes(item.key)}
                                onClick={() => selectTab(item.key)}
                            >
                                {item.label}
                            </TabButton>
                        ))}
                    </div>

                    {tab === 'geral' ? (
                        <>
                            <FieldSet>
                                <FieldLegend variant="label">Classificação</FieldLegend>
                                <FieldDescription>
                                    {kind === 'trailer'
                                        ? 'O tipo do implemento e a carroceria, onde vai a carga.'
                                        : 'O tipo diz o que o veículo leva e se ele puxa implemento. A carroceria é onde vai a carga — o cavalo mecânico não tem.'}
                                </FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                    <Field className="col-span-12 md:col-span-4" data-invalid={invalid('vehicle_type_id')}>
                                        <FieldLabel htmlFor="vehicle-type">Tipo</FieldLabel>
                                        <Select
                                            items={types.map((item) => ({ value: item.id, label: item.name }))}
                                            value={form.vehicle_type_id}
                                            onValueChange={(value) => set('vehicle_type_id', value)}
                                        >
                                            <SelectTrigger id="vehicle-type" className="w-full" aria-invalid={invalid('vehicle_type_id')}>
                                                <SelectValue placeholder="Selecione" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {types.map((item) => (
                                                        <SelectItem key={item.id} value={item.id}>
                                                            {item.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{error('vehicle_type_id')}</FieldError>
                                    </Field>

                                    {hasBody ? (
                                        <Field className="col-span-12 md:col-span-4" data-invalid={invalid('body_type_id')}>
                                            <FieldLabel htmlFor="body-type">Carroceria</FieldLabel>
                                            <Select
                                                items={bodyTypes.map((item) => ({ value: item.id, label: item.name }))}
                                                value={form.body_type_id}
                                                onValueChange={(value) => set('body_type_id', value)}
                                            >
                                                <SelectTrigger id="body-type" className="w-full" aria-invalid={invalid('body_type_id')}>
                                                    <SelectValue placeholder="Selecione" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {bodyTypes.map((item) => (
                                                            <SelectItem key={item.id} value={item.id}>
                                                                {item.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldError>{error('body_type_id')}</FieldError>
                                        </Field>
                                    ) : null}
                                </FieldGroup>
                            </FieldSet>

                            <Separator className="my-6" />

                            <FieldSet>
                                <FieldLegend variant="label">Documento</FieldLegend>
                                <FieldDescription>Como está no CRLV.</FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                    <Field className="col-span-12 md:col-span-3 xl:col-span-2" data-invalid={invalid('plate')}>
                                        <FieldLabel htmlFor="plate">Placa</FieldLabel>
                                        <InputPlate
                                            id="plate"
                                            value={form.plate}
                                            onChange={(value) => set('plate', value)}
                                            aria-invalid={invalid('plate')}
                                        />
                                        <FieldError>{error('plate')}</FieldError>
                                    </Field>

                                    <Field className="col-span-12 md:col-span-2 xl:col-span-1" data-invalid={invalid('plate_state')}>
                                        <FieldLabel htmlFor="plate-state">UF</FieldLabel>
                                        <SelectUf
                                            id="plate-state"
                                            value={form.plate_state}
                                            onChange={(value) => set('plate_state', value)}
                                            aria-invalid={invalid('plate_state')}
                                        />
                                        <FieldError>{error('plate_state')}</FieldError>
                                    </Field>

                                    <Field className="col-span-12 md:col-span-3 xl:col-span-3" data-invalid={invalid('renavam')}>
                                        <FieldLabel htmlFor="renavam">RENAVAM</FieldLabel>
                                        <Input
                                            id="renavam"
                                            inputMode="numeric"
                                            placeholder="11 dígitos"
                                            maxLength={11}
                                            value={form.renavam}
                                            onChange={(event) => set('renavam', event.target.value.replace(/\D/g, ''))}
                                            aria-invalid={invalid('renavam')}
                                            className="tabular-nums"
                                        />
                                        <FieldError>{error('renavam')}</FieldError>
                                    </Field>

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-4" data-invalid={invalid('chassis')}>
                                        <FieldLabel htmlFor="chassis">Chassi</FieldLabel>
                                        <Input
                                            id="chassis"
                                            placeholder="17 caracteres"
                                            maxLength={17}
                                            value={form.chassis}
                                            onChange={(event) =>
                                                set('chassis', event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))
                                            }
                                            aria-invalid={invalid('chassis')}
                                            className="font-mono tracking-wider"
                                        />
                                        <FieldError>{error('chassis')}</FieldError>
                                    </Field>

                                    <Field className="col-span-12 md:col-span-4 xl:col-span-2" data-invalid={invalid('registration_expires_on')}>
                                        <FieldLabel htmlFor="registration-expires">Validade do CRLV</FieldLabel>
                                        <DatePicker
                                            id="registration-expires"
                                            value={form.registration_expires_on}
                                            onChange={(value) => set('registration_expires_on', value || null)}
                                            aria-invalid={invalid('registration_expires_on')}
                                        />
                                        <FieldError>{error('registration_expires_on')}</FieldError>
                                    </Field>
                                </FieldGroup>
                            </FieldSet>

                            <Separator className="my-6" />

                            <FieldSet>
                                <FieldLegend variant="label">Fabricação</FieldLegend>
                                <FieldDescription>Também do CRLV. A marca ou o modelo que não estiver na lista pode ser adicionado ali mesmo.</FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                    <Field className="col-span-12 md:col-span-3" data-invalid={invalid('vehicle_brand_id')}>
                                        <FieldLabel htmlFor="brand">Marca</FieldLabel>
                                        <InputVehicleBrand
                                            id="brand"
                                            kind={kind}
                                            value={form.vehicle_brand_id}
                                            onChange={(value) => {
                                                if (value !== form.vehicle_brand_id) set('vehicle_model_id', null)
                                                set('vehicle_brand_id', value)
                                            }}
                                            aria-invalid={invalid('vehicle_brand_id')}
                                        />
                                        <FieldError>{error('vehicle_brand_id')}</FieldError>
                                    </Field>

                                    <Field className="col-span-12 md:col-span-3" data-invalid={invalid('vehicle_model_id')}>
                                        <FieldLabel htmlFor="model">Modelo</FieldLabel>
                                        <InputVehicleModel
                                            id="model"
                                            brandId={form.vehicle_brand_id}
                                            value={form.vehicle_model_id}
                                            onChange={(value) => set('vehicle_model_id', value)}
                                            aria-invalid={invalid('vehicle_model_id')}
                                        />
                                        <FieldError>{error('vehicle_model_id')}</FieldError>
                                    </Field>

                                    <Field className="col-span-6 md:col-span-2" data-invalid={invalid('manufacture_year')}>
                                        <FieldLabel htmlFor="manufacture-year">Ano de fabricação</FieldLabel>
                                        <YearInput
                                            id="manufacture-year"
                                            value={form.manufacture_year}
                                            onChange={(value) => set('manufacture_year', value)}
                                            invalid={invalid('manufacture_year')}
                                        />
                                        <FieldError>{error('manufacture_year')}</FieldError>
                                    </Field>

                                    <Field className="col-span-6 md:col-span-2" data-invalid={invalid('model_year')}>
                                        <FieldLabel htmlFor="model-year">Ano do modelo</FieldLabel>
                                        <YearInput
                                            id="model-year"
                                            value={form.model_year}
                                            onChange={(value) => set('model_year', value)}
                                            invalid={invalid('model_year')}
                                        />
                                        <FieldError>{error('model_year')}</FieldError>
                                    </Field>

                                    <Field className="col-span-12 md:col-span-2">
                                        <FieldLabel htmlFor="color">Cor</FieldLabel>
                                        <SelectVehicleColor id="color" value={form.color} onChange={(value) => set('color', value)} />
                                    </Field>
                                </FieldGroup>
                            </FieldSet>
                        </>
                    ) : null}

                    {tab === 'vinculos' ? (
                        <>
                        <FieldSet>
                            <FieldLegend variant="label">Proprietário e responsável</FieldLegend>
                            <FieldDescription>
                                As receitas e as despesas do {config.singular} — fretes, adiantamentos, descontos — vão para o
                                responsável. A chave PIX é a do cadastro dele.
                            </FieldDescription>
                            <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                <Field className="col-span-12 md:col-span-4" data-invalid={invalid('fleet_type')}>
                                    <FieldLabel htmlFor="fleet-type">Frota</FieldLabel>
                                    <Select items={FLEET_TYPES} value={form.fleet_type} onValueChange={(value) => set('fleet_type', value)}>
                                        <SelectTrigger id="fleet-type" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {FLEET_TYPES.map((item) => (
                                                    <SelectItem key={item.value} value={item.value}>
                                                        {item.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldDescription>
                                        Própria: da empresa. Agregado: de um motorista que trabalha só para ela. Terceiro: contratado
                                        por frete.
                                    </FieldDescription>
                                    <FieldError>{error('fleet_type')}</FieldError>
                                </Field>
                                <div className="hidden md:col-span-8 md:block" />

                                <Field className="col-span-12 md:col-span-6" data-invalid={invalid('owner')}>
                                    <FieldLabel htmlFor="owner">Proprietário</FieldLabel>
                                    <InputEntityRole
                                        id="owner"
                                        role="vehicle_owner"
                                        value={form.owner}
                                        onChange={(value) => set('owner', value)}
                                        aria-invalid={invalid('owner')}
                                    />
                                    <FieldDescription>
                                        Quem consta no CRLV. Não está na lista?{' '}
                                        <Link className="underline" to="/app/cadastros/entidades/proprietarios/novo" target="_blank">
                                            Cadastre o proprietário
                                        </Link>
                                        .
                                    </FieldDescription>
                                    <FieldError>{error('owner')}</FieldError>
                                </Field>

                                <Field className="col-span-12 md:col-span-6" data-invalid={invalid('responsible')}>
                                    <FieldLabel htmlFor="responsible">Responsável</FieldLabel>
                                    <InputEntityRole
                                        id="responsible"
                                        role="vehicle_responsible"
                                        value={form.responsible}
                                        onChange={(value) => set('responsible', value)}
                                        aria-invalid={invalid('responsible')}
                                    />
                                    {form.owner && !sameAsOwner ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="w-fit"
                                            disabled={linking}
                                            onClick={assignOwnerAsResponsible}
                                        >
                                            <UserCheckIcon data-icon="inline-start" />
                                            {linking ? 'Vinculando…' : 'O responsável é o proprietário'}
                                        </Button>
                                    ) : null}
                                    <FieldError>{error('responsible')}</FieldError>
                                </Field>

                                {form.responsible ? (
                                    <div className="col-span-12">
                                        <PixSummary person={form.responsible} />
                                    </div>
                                ) : null}
                            </FieldGroup>
                        </FieldSet>

                        {form.fleet_type !== 'own' ? (
                            <>
                                <Separator className="my-6" />

                                <FieldSet>
                                    <FieldLegend variant="label">Transportador na ANTT</FieldLegend>
                                    <FieldDescription>
                                        Quem tem o RNTRC em que este {config.singular} está registrado — em geral o próprio dono,
                                        quando é autônomo (TAC), ou quem arrendou o {config.singular}. É o RNTRC que vai no CIOT e no
                                        pagamento do frete.
                                    </FieldDescription>
                                    <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                        <Field className="col-span-12 md:col-span-6" data-invalid={invalid('carrier')}>
                                            <FieldLabel htmlFor="carrier">Transportador</FieldLabel>
                                            <InputEntityRole
                                                id="carrier"
                                                role="carrier"
                                                value={form.carrier}
                                                onChange={(value) => set('carrier', value)}
                                                aria-invalid={invalid('carrier')}
                                            />
                                            <FieldDescription>
                                                O RNTRC fica no cadastro do transportador, na aba ANTT.{' '}
                                                <Link className="underline" to="/app/cadastros/entidades/transportadoras/novo" target="_blank">
                                                    Cadastrar transportador
                                                </Link>
                                            </FieldDescription>
                                            <FieldError>{error('carrier')}</FieldError>
                                        </Field>
                                    </FieldGroup>
                                </FieldSet>
                            </>
                        ) : null}
                        </>
                    ) : null}

                    {tab === 'capacidades' ? (
                        <FieldSet>
                            <FieldLegend variant="label">Pesos e capacidades</FieldLegend>
                            <FieldDescription>
                                Os valores do CRLV deste {config.singular}. O conjunto é a soma: a tara do cavalo, mais a das
                                carretas, mais a carga, não pode passar do CMT do cavalo. O peso de cada carga fica na viagem.
                            </FieldDescription>
                            <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                <NumberField
                                    id="axle-count"
                                    label="Eixos"
                                    field="axle_count"
                                    form={form}
                                    set={set}
                                    error={error}
                                    className="md:col-span-2"
                                />
                                <NumberField
                                    id="tare"
                                    label="Tara"
                                    unit="kg"
                                    field="tare_weight_kg"
                                    form={form}
                                    set={set}
                                    error={error}
                                    description="Peso do veículo vazio."
                                />
                                {hasBody ? (
                                    <NumberField
                                        id="load-capacity"
                                        label="Lotação"
                                        unit="kg"
                                        field="load_capacity_kg"
                                        form={form}
                                        set={set}
                                        error={error}
                                        description="Carga máxima que ele leva."
                                    />
                                ) : null}
                                <NumberField
                                    id="gross-weight"
                                    label="PBT"
                                    unit="kg"
                                    field="gross_weight_kg"
                                    form={form}
                                    set={set}
                                    error={error}
                                    description="Peso bruto total homologado."
                                />
                                {canTow ? (
                                    <NumberField
                                        id="max-traction"
                                        label="CMT"
                                        unit="kg"
                                        field="max_traction_weight_kg"
                                        form={form}
                                        set={set}
                                        error={error}
                                        description="O máximo que ele puxa, com os implementos carregados."
                                    />
                                ) : null}
                                {hasBody ? (
                                    <NumberField
                                        id="volume"
                                        label="Volume"
                                        unit="m³"
                                        field="volume_capacity_m3"
                                        form={form}
                                        set={set}
                                        error={error}
                                        decimal
                                        description="Capacidade da carroceria."
                                    />
                                ) : null}
                            </FieldGroup>
                        </FieldSet>
                    ) : null}

                    {tab === 'equipamentos' ? (
                        <>
                            {canTow ? (
                                <>
                                    <FieldSet>
                                        <FieldLegend variant="label">Implementos</FieldLegend>
                                        <FieldDescription>Na ordem em que estão engatados. Desengatar guarda o histórico.</FieldDescription>
                                        <Field data-invalid={listError('trailers') ? true : undefined}>
                                            <VehicleTrailersField
                                                id="trailers"
                                                vehicleUuid={uuid ?? null}
                                                value={form.trailers}
                                                onChange={(value) => set('trailers', value)}
                                            />
                                            <FieldError>{listError('trailers')}</FieldError>
                                        </Field>
                                    </FieldSet>
                                    <Separator className="my-6" />
                                </>
                            ) : null}

                            {kind === 'trailer' ? (
                                <>
                                    <Alert>
                                        <InfoIcon />
                                        <AlertTitle>
                                            {traction
                                                ? `Engatado em ${traction.plate}, na ${traction.position}ª posição`
                                                : 'Desengatado'}
                                        </AlertTitle>
                                        <AlertDescription>
                                            O engate é feito no cadastro do veículo que puxa.{' '}
                                            {traction ? (
                                                <Link className="underline" to={vehiclePath('motor_vehicle', traction.uuid, 'equipamentos')}>
                                                    Abrir {traction.plate}
                                                </Link>
                                            ) : null}
                                        </AlertDescription>
                                    </Alert>
                                    <Separator className="my-6" />
                                </>
                            ) : null}

                            <FieldSet>
                                <FieldLegend variant="label">Rastreadores</FieldLegend>
                                <FieldDescription>
                                    Um principal, que é a posição oficial do veículo, e quantos reservas precisar.
                                </FieldDescription>
                                <Field data-invalid={listError('devices') ? true : undefined}>
                                    <VehicleDevicesField
                                        id="devices"
                                        vehicleUuid={uuid ?? null}
                                        value={form.devices}
                                        onChange={(value) => set('devices', value)}
                                    />
                                    <FieldError>{listError('devices')}</FieldError>
                                </Field>
                            </FieldSet>

                            {history.length > 0 ? (
                                <>
                                    <Separator className="my-6" />
                                    <FieldSet>
                                        <FieldLegend variant="label">Histórico de engates</FieldLegend>
                                        <ul className="divide-y rounded-lg border text-sm">
                                            {history.map((item, index) => (
                                                <li key={index} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                                                    <span className="font-mono tracking-wider">
                                                        {item.traction.plate} → {item.trailer.plate}
                                                    </span>
                                                    <span className="text-muted-foreground text-xs">{item.position}ª posição</span>
                                                    <span className="text-muted-foreground ml-auto text-xs tabular-nums">
                                                        {format(parseISO(item.coupled_at), 'dd/MM/yyyy HH:mm')} —{' '}
                                                        {item.uncoupled_at
                                                            ? format(parseISO(item.uncoupled_at), 'dd/MM/yyyy HH:mm')
                                                            : 'engatado'}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </FieldSet>
                                </>
                            ) : null}
                        </>
                    ) : null}
                </form>
            </LoadingWrapper>
        </>
    )
}

function YearInput({ id, value, onChange, invalid }) {
    return (
        <Input
            id={id}
            inputMode="numeric"
            placeholder="aaaa"
            maxLength={4}
            value={value}
            onChange={(event) => onChange(event.target.value.replace(/\D/g, ''))}
            aria-invalid={invalid}
            className="tabular-nums"
        />
    )
}

function NumberField({ id, label, unit, field, form, set, error, description, decimal = false, className = 'md:col-span-3' }) {
    const message = error(field)

    return (
        <Field className={`col-span-6 ${className}`} data-invalid={message ? true : undefined}>
            <FieldLabel htmlFor={id}>
                {label}
                {unit ? <span className="text-muted-foreground font-normal">({unit})</span> : null}
            </FieldLabel>
            <Input
                id={id}
                inputMode={decimal ? 'decimal' : 'numeric'}
                value={form[field]}
                onChange={(event) =>
                    set(field, decimal ? event.target.value.replace(',', '.').replace(/[^\d.]/g, '') : event.target.value.replace(/\D/g, ''))
                }
                aria-invalid={message ? true : undefined}
                className="tabular-nums"
            />
            {description ? <FieldDescription>{description}</FieldDescription> : null}
            <FieldError>{message}</FieldError>
        </Field>
    )
}

/**
 * A chave PIX do responsável, para conferir: é para ela que vão os
 * pagamentos do veículo. Sem chave, o aviso leva ao cadastro da pessoa.
 */
function PixSummary({ person }) {
    if (!person.pix_key) {
        return (
            <Alert>
                <TriangleAlertIcon />
                <AlertTitle>{person.label} não tem chave PIX</AlertTitle>
                <AlertDescription>
                    Sem ela, os pagamentos do veículo não têm para onde ir. A chave fica no cadastro de responsáveis, na
                    aba Pagamento.
                </AlertDescription>
            </Alert>
        )
    }

    const key = ['cpf', 'cnpj'].includes(person.pix_key_type)
        ? formatCpfCnpj(person.pix_key)
        : person.pix_key_type === 'phone'
            ? formatPhone(person.pix_key)
            : person.pix_key

    return (
        <p className="inline-flex items-center gap-x-1 text-muted-foreground text-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                className="size-3.5 shrink-0"
                fill="none" stroke="currentColor" strokeWidth="1.5"
                strokeLinecap="round" strokeLinejoin="round">
                <path d="M7 6.5 10.6 2.9a2 2 0 0 1 2.8 0L17 6.5h-2l-3 3-3-3Z" />
                <path d="m17.5 7 3.6 3.6a2 2 0 0 1 0 2.8L17.5 17v-2l-3-3 3-3Z" />
                <path d="m17 17.5-3.6 3.6a2 2 0 0 1-2.8 0L7 17.5h2l3-3 3 3Z" />
                <path d="m6.5 17-3.6-3.6a2 2 0 0 1 0-2.8L6.5 7v2l3 3-3 3Z" />
            </svg>
            Pagamentos via PIX para <span className="text-foreground font-medium tabular-nums">{key}</span>
        </p>
    )
}
