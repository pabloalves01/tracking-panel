import { useState, useEffect, useRef } from 'react'
import { TriangleAlert } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { InputCity } from '@/components/common/inputs/InputCity'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { InputMoney } from '@/components/common/inputs/InputMoney'
import { InputPassword } from '@/components/common/inputs/InputPassword'
import { InputPhone } from '@/components/common/inputs/InputPhone'
import { InputPix } from '@/components/common/inputs/InputPix'
import { InputPostalCode } from '@/components/common/inputs/InputPostalCode'
import { SelectState } from '@/components/common/inputs/SelectState'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { getEntityByDocument } from '@/lib/registry/entities'
import { createFuelStation, getFuelStation, updateFuelStation } from '@/lib/registry/fuelStations'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/postos'

function emptyToNull(value) {
    return value === '' || value === null || value === undefined ? null : value
}

const personTypes = [
    { label: 'Pessoa jurídica', value: 'pj' },
    { label: 'Pessoa física', value: 'pf' },
]

/**
 * Campos que pertencem à entidade. Usado para limpar o formulário
 * quando os dados preenchidos vieram de uma entidade que não vale mais.
 */
const emptyEntityFields = {
    name: '',
    trade_name: '',
    email: '',
    phone: '',
    postal_code: '',
    street: '',
    street_number: '',
    complement: '',
    neighborhood: '',
    state_id: null,
    city_id: null,
}

export default function FuelStationFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = !!uuid

    const [loading, setLoading] = useState(false)
    const [existingEntity, setExistingEntity] = useState(false)
    const [existingFuelStation, setExistingFuelStation] = useState(false)
    const [entityUuid, setEntityUuid] = useState(null)

    // Marca quando os campos foram preenchidos pela busca, para limpá-los
    // se o documento mudar. O que o usuário digitou à mão não é apagado.
    const filledFromEntity = useRef(false)

    const [formData, setFormData] = useState({
        person_type: 'pj',
        name: '',
        trade_name: '',
        cpf_cnpj: '',
        email: '',
        phone: '',
        postal_code: '',
        street: '',
        street_number: '',
        complement: '',
        neighborhood: '',
        state_id: null,
        city_id: null,
        pix_key_type: 'cpf',
        pix_key: '',
        login: '',
        password: '',
        password_confirmation: '',
        diesel_s10_price: '',
        diesel_s500_price: '',
        gasoline_price: '',
        additive_gasoline_price: '',
    })

    /**
     * Desfaz o resultado da busca. Os campos só são limpos quando o
     * preenchimento veio de uma entidade; o que foi digitado permanece.
     */
    function clearLookup() {
        setExistingEntity(false)
        setExistingFuelStation(false)
        setEntityUuid(null)

        if (filledFromEntity.current) {
            filledFromEntity.current = false
            setFormData((current) => ({ ...current, ...emptyEntityFields }))
        }
    }

    useEffect(() => {
        if (isEditing) {
            return
        }

        const digits = formData.cpf_cnpj
        const expectedLength = formData.person_type === 'pf' ? 11 : 14

        if (digits.length !== expectedLength) {
            clearLookup()
            return
        }

        let cancelled = false
        setLoading(true)

        getEntityByDocument(formData.cpf_cnpj)
            .then((entity) => {
                if (cancelled) {
                    return
                }

                if (!entity) {
                    clearLookup()
                    return
                }

                setFormData((current) => ({
                    ...current,
                    person_type: entity.person_type || current.person_type,
                    name: entity.name ?? '',
                    trade_name: entity.trade_name ?? '',
                    email: entity.email ?? '',
                    phone: entity.phone ?? '',
                    postal_code: entity.postal_code ?? '',
                    street: entity.street ?? '',
                    street_number: entity.street_number ?? '',
                    complement: entity.complement ?? '',
                    neighborhood: entity.neighborhood ?? '',
                    state_id: entity.state_id ?? null,
                    city_id: entity.city_id ?? null,
                }))

                filledFromEntity.current = true
                setExistingEntity(true)
                setExistingFuelStation(Boolean(entity.fuel_station_exists))
                setEntityUuid(entity.uuid ?? null)
            })
            .catch((error) => {
                if (!cancelled) {
                    notifyError(error)
                }
            })
            .finally(() => setLoading(false))

        return () => {
            cancelled = true
        }
    }, [formData.cpf_cnpj, formData.person_type, isEditing])

    useEffect(() => {
        if (!isEditing) {
            return
        }

        setLoading(true)

        getFuelStation(uuid)
            .then((response) => {
                const fuelStation = response.data ?? {}
                setEntityUuid(fuelStation.entity_uuid ?? null)
                setFormData({
                    person_type: fuelStation.person_type || 'pj',
                    name: fuelStation.name ?? '',
                    trade_name: fuelStation.trade_name ?? '',
                    cpf_cnpj: fuelStation.cpf_cnpj ?? '',
                    email: fuelStation.email ?? '',
                    phone: fuelStation.phone ?? '',
                    postal_code: fuelStation.postal_code ?? '',
                    street: fuelStation.street ?? '',
                    street_number: fuelStation.street_number ?? '',
                    complement: fuelStation.complement ?? '',
                    neighborhood: fuelStation.neighborhood ?? '',
                    state_id: fuelStation.state_id ?? null,
                    city_id: fuelStation.city_id ?? null,
                    pix_key_type: fuelStation.pix_key_type || 'cpf',
                    pix_key: fuelStation.pix_key ?? '',
                    login: fuelStation.login ?? '',
                    password: '',
                    password_confirmation: '',
                    diesel_s10_price: fuelStation.diesel_s10_price ?? '',
                    diesel_s500_price: fuelStation.diesel_s500_price ?? '',
                    gasoline_price: fuelStation.gasoline_price ?? '',
                    additive_gasoline_price: fuelStation.additive_gasoline_price ?? '',
                })
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [isEditing, uuid])

    function setField(field, value) {
        setFormData((current) => ({ ...current, [field]: value }))
    }

    async function handleSubmit(event) {
        event.preventDefault()

        if (!isEditing && existingFuelStation) {
            return
        }

        const role = {
            pix_key_type: formData.pix_key ? formData.pix_key_type : null,
            pix_key: formData.pix_key || null,
            login: formData.login,
            password: formData.password || null,
            password_confirmation: formData.password ? formData.password_confirmation : null,
            diesel_s10_price: emptyToNull(formData.diesel_s10_price),
            diesel_s500_price: emptyToNull(formData.diesel_s500_price),
            gasoline_price: emptyToNull(formData.gasoline_price),
            additive_gasoline_price: emptyToNull(formData.additive_gasoline_price),
        }

        const payload = isEditing
            ? role
            : {
                entity: {
                    person_type: formData.person_type,
                    name: formData.name,
                    trade_name: formData.trade_name || null,
                    cpf_cnpj: formData.cpf_cnpj,
                    email: formData.email || null,
                    phone: formData.phone || null,
                    postal_code: formData.postal_code || null,
                    street: formData.street || null,
                    street_number: formData.street_number || null,
                    complement: formData.complement || null,
                    neighborhood: formData.neighborhood || null,
                    state_id: formData.state_id || null,
                    city_id: formData.city_id || null,
                },
                ...role,
            }

        setLoading(true)

        try {
            const response = isEditing
                ? await updateFuelStation(uuid, payload)
                : await createFuelStation(payload)
            notifySuccess(response)
            navigate(listPath)
        } catch (error) {
            notifyError(error)
        } finally {
            setLoading(false)
        }
    }

    const isCompany = formData.person_type === 'pj'
    const entityLocked = isEditing || existingEntity

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: 'Postos', to: listPath },
                    { text: isEditing ? 'Editar posto' : 'Novo posto' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: listPath },
                    {
                        title: 'Salvar',
                        disabled: !isEditing && existingFuelStation,
                        onClick: () => document.getElementById('fuelStation-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="fuelStation-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Dados do posto</FieldLegend>
                        <FieldDescription>
                            Login, senha, PIX e preços ficam no posto. Nome, documento e endereço pertencem à entidade.
                        </FieldDescription>

                        {entityLocked && !existingFuelStation && (
                            <div
                                role="status"
                                className="col-span-12 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-50"
                            >
                                <TriangleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <p className="leading-5">
                                    <span className="font-semibold">Atenção.</span>{' '}
                                    Entidade encontrada. Dados preenchidos automaticamente.
                                    {entityUuid ? (
                                        <>
                                            {' '}
                                            <Link to={`/app/cadastros/entidades/${entityUuid}/editar`} className="font-semibold underline">
                                                Editar entidade
                                            </Link>
                                        </>
                                    ) : null}
                                </p>
                            </div>
                        )}

                        {existingFuelStation && !isEditing && (
                            <div
                                role="status"
                                className="col-span-12 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-50"
                            >
                                <TriangleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <p className="leading-5">
                                    <span className="font-semibold">Atenção.</span>{' '}
                                    Já existe um posto com este documento.
                                </p>
                            </div>
                        )}
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="person-type">Tipo de pessoa</FieldLabel>
                                <Select
                                    items={personTypes}
                                    value={formData.person_type}
                                    onValueChange={(value) => setField('person_type', value)}
                                    disabled={entityLocked}
                                >
                                    <SelectTrigger id="person-type" className="w-full" disabled={entityLocked}>
                                        <SelectValue placeholder="Ex: Pessoa jurídica" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {personTypes.map((item) => (
                                                <SelectItem key={item.value} value={item.value}>
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="cpf-cnpj">{isCompany ? 'CNPJ' : 'CPF'}</FieldLabel>
                                <InputCpfCnpj
                                    type={isCompany ? 'cnpj' : 'cpf'}
                                    id="cpf-cnpj"
                                    name="cpf_cnpj"
                                    value={formData.cpf_cnpj}
                                    onChange={(cpf_cnpj) => setField('cpf_cnpj', cpf_cnpj)}
                                    disabled={entityLocked}
                                    required
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="name">{isCompany ? 'Razão social' : 'Nome'}</FieldLabel>
                                <Input
                                    id="name"
                                    name="name"
                                    value={formData.name}
                                    onChange={(event) => setField('name', event.target.value)}
                                    placeholder={isCompany ? 'Ex: Melo Transporte LTDA' : 'Ex: João da Silva'}
                                    disabled={entityLocked}
                                    required
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="trade-name">Nome fantasia</FieldLabel>
                                <Input
                                    id="trade-name"
                                    name="trade_name"
                                    value={formData.trade_name}
                                    onChange={(event) => setField('trade_name', event.target.value)}
                                    placeholder="Ex: Melo Transporte"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="email">E-mail</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={formData.email}
                                    onChange={(event) => setField('email', event.target.value)}
                                    placeholder="Ex: contato@empresa.com.br"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="phone">Telefone</FieldLabel>
                                <InputPhone
                                    id="phone"
                                    name="phone"
                                    value={formData.phone}
                                    onChange={(phone) => setField('phone', phone)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="pix-key">Chave PIX</FieldLabel>
                                <InputPix
                                    id="pix-key"
                                    type={formData.pix_key_type}
                                    value={formData.pix_key}
                                    onTypeChange={(pixKeyType) => setField('pix_key_type', pixKeyType)}
                                    onValueChange={(pixKey) => setField('pix_key', pixKey)}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="fuelStation-login">E-mail de acesso</FieldLabel>
                                <Input
                                    id="fuelStation-login"
                                    type="email"
                                    value={formData.login}
                                    onChange={(event) => setField('login', event.target.value)}
                                    placeholder="Ex: posto@empresa.com.br"
                                    autoComplete="off"
                                    required
                                />
                                <FieldDescription>Fica no posto, para um acesso futuro. Não entra na entidade.</FieldDescription>
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="fuelStation-password">Senha</FieldLabel>
                                <InputPassword
                                    id="fuelStation-password"
                                    value={formData.password}
                                    onChange={(password) => setField('password', password)}
                                    placeholder={isEditing ? 'Em branco mantém a atual' : 'Senha de acesso'}
                                    required={!isEditing}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="fuelStation-password-confirmation">Confirmação da senha</FieldLabel>
                                <InputPassword
                                    id="fuelStation-password-confirmation"
                                    value={formData.password_confirmation}
                                    onChange={(password) => setField('password_confirmation', password)}
                                    placeholder="Repita a senha"
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    <Separator />

                    <FieldSet>
                        <FieldLegend variant="label">Preços</FieldLegend>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            {[
                                ['diesel_s10_price', 'Diesel S10'],
                                ['diesel_s500_price', 'Diesel S500'],
                                ['gasoline_price', 'Gasolina'],
                                ['additive_gasoline_price', 'Gasolina aditivada'],
                            ].map(([field, label]) => (
                                <Field key={field} className="col-span-12 md:col-span-3">
                                    <FieldLabel htmlFor={field}>{label}</FieldLabel>
                                    <InputMoney
                                        id={field}
                                        value={formData[field]}
                                        onChange={(amount) => setField(field, amount)}
                                    />
                                </Field>
                            ))}
                        </FieldGroup>
                    </FieldSet>

                    <Separator />

                    <FieldSet>
                        <FieldLegend variant="label">Endereço</FieldLegend>
                        <FieldDescription>
                            {entityLocked
                                ? 'Endereço cadastrado na entidade.'
                                : 'Preencha os campos abaixo para informar o endereço do posto.'}
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="fuelStation-postal-code">CEP</FieldLabel>
                                <InputPostalCode
                                    id="fuelStation-postal-code"
                                    value={formData.postal_code}
                                    onChange={(postalCode) => setField('postal_code', postalCode)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="fuelStation-street">Rua</FieldLabel>
                                <Input
                                    id="fuelStation-street"
                                    name="street"
                                    value={formData.street}
                                    onChange={(event) => setField('street', event.target.value)}
                                    placeholder="Ex: Rua das Flores"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="fuelStation-street-number">Número</FieldLabel>
                                <Input
                                    id="fuelStation-street-number"
                                    name="street_number"
                                    value={formData.street_number}
                                    onChange={(event) => setField('street_number', event.target.value)}
                                    placeholder="Ex: 123"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="fuelStation-complement">Complemento</FieldLabel>
                                <Input
                                    id="fuelStation-complement"
                                    name="complement"
                                    value={formData.complement}
                                    onChange={(event) => setField('complement', event.target.value)}
                                    placeholder="Ex: Apt. 101"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="fuelStation-neighborhood">Bairro</FieldLabel>
                                <Input
                                    id="fuelStation-neighborhood"
                                    name="neighborhood"
                                    value={formData.neighborhood}
                                    onChange={(event) => setField('neighborhood', event.target.value)}
                                    placeholder="Ex: Centro"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="fuelStation-state">Estado</FieldLabel>
                                <SelectState
                                    id="fuelStation-state"
                                    value={formData.state_id}
                                    disabled={entityLocked}
                                    onChange={(stateId) => {
                                        setFormData((current) => ({
                                            ...current,
                                            state_id: stateId,
                                            city_id: null,
                                        }))
                                    }}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6" data-disabled={entityLocked || !formData.state_id ? true : undefined}>
                                <FieldLabel htmlFor="fuelStation-city">Cidade</FieldLabel>
                                <InputCity
                                    id="fuelStation-city"
                                    stateId={formData.state_id}
                                    value={formData.city_id}
                                    onChange={(cityId) => setField('city_id', cityId)}
                                    disabled={entityLocked}
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>
                </form>
            </LoadingWrapper>
        </>
    )
}
