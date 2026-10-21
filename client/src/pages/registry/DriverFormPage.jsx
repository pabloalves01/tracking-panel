import { useState, useEffect, useRef } from 'react'
import { TriangleAlert } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { InputCity } from '@/components/common/inputs/InputCity'
import { InputEmergencyNumber } from '@/components/common/inputs/InputEmergencyNumber'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { InputPhone } from '@/components/common/inputs/InputPhone'
import { InputPostalCode } from '@/components/common/inputs/InputPostalCode'
import { SelectState } from '@/components/common/inputs/SelectState'
import { DatePicker } from '@/components/datepicker/DatePicker'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { getEntityByDocument } from '@/lib/registry/entities'
import { createDriver, getDriver, updateDriver } from '@/lib/registry/drivers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/motoristas'

const personTypes = [
    { label: 'Pessoa jurídica', value: 'pj' },
    { label: 'Pessoa física', value: 'pf' },
]

const licenseCategories = ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE', 'ACC']

/**
 * Campos que pertencem à entidade. Usado para limpar o formulário
 * quando os dados preenchidos vieram de uma entidade que não vale mais.
 */
const emptyEntityFields = {
    name: '',
    trade_name: '',
    email: '',
    phone: '',
    rg: '',
    state_registration: '',
    emergency_phone: '',
    emergency_phone_type: 'mobile',
    postal_code: '',
    street: '',
    street_number: '',
    complement: '',
    neighborhood: '',
    state_id: null,
    city_id: null,
}

function entityFieldsFrom(source) {
    return {
        person_type: source.person_type || 'pj',
        name: source.name ?? '',
        trade_name: source.trade_name ?? '',
        cpf_cnpj: source.cpf_cnpj ?? '',
        email: source.email ?? '',
        phone: source.phone ?? '',
        rg: source.rg ?? '',
        state_registration: source.state_registration ?? '',
        emergency_phone: source.emergency_phone ?? '',
        emergency_phone_type: source.emergency_phone_type || 'mobile',
        postal_code: source.postal_code ?? '',
        street: source.street ?? '',
        street_number: source.street_number ?? '',
        complement: source.complement ?? '',
        neighborhood: source.neighborhood ?? '',
        state_id: source.state_id ?? null,
        city_id: source.city_id ?? null,
    }
}

export default function DriverFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = !!uuid

    const [loading, setLoading] = useState(false)
    const [existingEntity, setExistingEntity] = useState(false)
    const [existingDriver, setExistingDriver] = useState(false)
    const [entityUuid, setEntityUuid] = useState(null)

    // Marca quando os campos foram preenchidos pela busca, para limpá-los
    // se o documento mudar. O que o usuário digitou à mão não é apagado.
    const filledFromEntity = useRef(false)

    const [formData, setFormData] = useState({
        person_type: 'pf',
        license_number: '',
        license_category: 'B',
        license_expires_on: '',
        license_issued_state: '',
        residence_proof_path: '',
        name: '',
        trade_name: '',
        cpf_cnpj: '',
        email: '',
        phone: '',
        rg: '',
        state_registration: '',
        emergency_phone: '',
        emergency_phone_type: 'mobile',
        postal_code: '',
        street: '',
        street_number: '',
        complement: '',
        neighborhood: '',
        state_id: null,
        city_id: null,
    })

    /**
     * Desfaz o resultado da busca. Os campos só são limpos quando o
     * preenchimento veio de uma entidade; o que foi digitado permanece.
     */
    function clearLookup() {
        setExistingEntity(false)
        setExistingDriver(false)
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
                    ...entityFieldsFrom(entity),
                    cpf_cnpj: current.cpf_cnpj,
                }))

                filledFromEntity.current = true
                setExistingEntity(true)
                setExistingDriver(Boolean(entity.driver_exists))
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

        getDriver(uuid)
            .then((response) => {
                const driver = response.data ?? {}
                setEntityUuid(driver.entity_uuid ?? null)
                setFormData({
                    ...entityFieldsFrom(driver),
                    license_number: driver.license_number ?? '',
                    license_category: driver.license_category || 'B',
                    license_expires_on: driver.license_expires_on ?? '',
                    license_issued_state: driver.license_issued_state ?? '',
                    residence_proof_path: driver.residence_proof_path ?? '',
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

        if (!isEditing && existingDriver) {
            return
        }

        const role = {
            license_number: formData.license_number,
            license_category: formData.license_category,
            license_expires_on: formData.license_expires_on || null,
            license_issued_state: formData.license_issued_state || null,
            residence_proof_path: formData.residence_proof_path || null,
        }

        const payload = isEditing
            ? role
            : {
                ...role,
                entity: {
                    person_type: formData.person_type,
                    name: formData.name,
                    trade_name: formData.trade_name || null,
                    cpf_cnpj: formData.cpf_cnpj,
                    email: formData.email || null,
                    phone: formData.phone || null,
                    rg: formData.person_type === 'pf' ? (formData.rg || null) : null,
                    state_registration: formData.person_type === 'pj' ? (formData.state_registration || null) : null,
                    emergency_phone: formData.emergency_phone || null,
                    emergency_phone_type: formData.emergency_phone ? formData.emergency_phone_type : null,
                    postal_code: formData.postal_code || null,
                    street: formData.street || null,
                    street_number: formData.street_number || null,
                    complement: formData.complement || null,
                    neighborhood: formData.neighborhood || null,
                    state_id: formData.state_id || null,
                    city_id: formData.city_id || null,
                },
            }

        setLoading(true)

        try {
            const response = isEditing
                ? await updateDriver(uuid, payload)
                : await createDriver(payload)
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
                    { text: 'Motoristas', to: listPath },
                    { text: isEditing ? 'Editar motorista' : 'Novo motorista' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: listPath },
                    {
                        title: 'Salvar',
                        disabled: !isEditing && existingDriver,
                        onClick: () => document.getElementById('driver-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="driver-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Dados do motorista</FieldLegend>
                        <FieldDescription>
                            A CNH fica no motorista. Nome, documento e contato pertencem à entidade.
                        </FieldDescription>

                        {entityLocked && !existingDriver && (
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

                        {existingDriver && !isEditing && (
                            <div
                                role="status"
                                className="col-span-12 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-50"
                            >
                                <TriangleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <p className="leading-5">
                                    <span className="font-semibold">Atenção.</span>{' '}
                                    Já existe um motorista com este documento.
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
                            {isCompany ? (
                                <Field className="col-span-12 md:col-span-6">
                                    <FieldLabel htmlFor="state-registration">Inscrição estadual</FieldLabel>
                                    <Input
                                        id="state-registration"
                                        name="state_registration"
                                        value={formData.state_registration}
                                        onChange={(event) => setField('state_registration', event.target.value)}
                                        placeholder="Ex: 123.456.789.012"
                                        disabled={entityLocked}
                                    />
                                </Field>
                            ) : (
                                <Field className="col-span-12 md:col-span-6">
                                    <FieldLabel htmlFor="rg">RG</FieldLabel>
                                    <Input
                                        id="rg"
                                        name="rg"
                                        value={formData.rg}
                                        onChange={(event) => setField('rg', event.target.value)}
                                        placeholder="Ex: 12.345.678-9"
                                        disabled={entityLocked}
                                    />
                                </Field>
                            )}
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="emergency-phone">Telefone de emergência</FieldLabel>
                                <InputEmergencyNumber
                                    id="emergency-phone"
                                    type={formData.emergency_phone_type}
                                    value={formData.emergency_phone}
                                    onTypeChange={(phoneType) => setField('emergency_phone_type', phoneType)}
                                    onValueChange={(phone) => setField('emergency_phone', phone)}
                                    disabled={entityLocked}
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    <Separator />

                    <FieldSet>
                        <FieldLegend variant="label">CNH</FieldLegend>
                        <FieldDescription>Dados da habilitação e o caminho do comprovante de residência.</FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-4">
                                <FieldLabel htmlFor="license-number">Número da CNH</FieldLabel>
                                <Input
                                    id="license-number"
                                    name="license_number"
                                    value={formData.license_number}
                                    onChange={(event) => setField('license_number', event.target.value)}
                                    placeholder="Ex: 12345678900"
                                    required
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-4">
                                <FieldLabel htmlFor="license-category">Categoria</FieldLabel>
                                <Select
                                    items={licenseCategories.map((value) => ({ label: value, value }))}
                                    value={formData.license_category}
                                    onValueChange={(value) => setField('license_category', value)}
                                >
                                    <SelectTrigger id="license-category" className="w-full">
                                        <SelectValue placeholder="Ex: AB" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {licenseCategories.map((value) => (
                                                <SelectItem key={value} value={value}>
                                                    {value}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field className="col-span-12 md:col-span-4">
                                <FieldLabel htmlFor="license-expires-on">Validade</FieldLabel>
                                <DatePicker
                                    id="license-expires-on"
                                    value={formData.license_expires_on}
                                    onChange={(date) => setField('license_expires_on', date)}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-4">
                                <FieldLabel htmlFor="license-issued-state">UF de emissão</FieldLabel>
                                <Input
                                    id="license-issued-state"
                                    name="license_issued_state"
                                    value={formData.license_issued_state}
                                    maxLength={2}
                                    onChange={(event) => setField('license_issued_state', event.target.value.toUpperCase())}
                                    placeholder="Ex: SP"
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-8">
                                <FieldLabel htmlFor="residence-proof-path">Comprovante de residência</FieldLabel>
                                <Input
                                    id="residence-proof-path"
                                    name="residence_proof_path"
                                    value={formData.residence_proof_path}
                                    onChange={(event) => setField('residence_proof_path', event.target.value)}
                                    placeholder="Ex: documentos/comprovante.pdf"
                                />
                                <FieldDescription>Guarda só o caminho. O arquivo não é enviado por aqui.</FieldDescription>
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    <Separator />

                    <FieldSet>
                        <FieldLegend variant="label">Endereço</FieldLegend>
                        <FieldDescription>
                            {entityLocked
                                ? 'Endereço cadastrado na entidade.'
                                : 'Preencha os campos abaixo para informar o endereço do motorista.'}
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="driver-postal-code">CEP</FieldLabel>
                                <InputPostalCode
                                    id="driver-postal-code"
                                    value={formData.postal_code}
                                    onChange={(postalCode) => setField('postal_code', postalCode)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="driver-street">Rua</FieldLabel>
                                <Input
                                    id="driver-street"
                                    name="street"
                                    value={formData.street}
                                    onChange={(event) => setField('street', event.target.value)}
                                    placeholder="Ex: Rua das Flores"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="driver-street-number">Número</FieldLabel>
                                <Input
                                    id="driver-street-number"
                                    name="street_number"
                                    value={formData.street_number}
                                    onChange={(event) => setField('street_number', event.target.value)}
                                    placeholder="Ex: 123"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="driver-complement">Complemento</FieldLabel>
                                <Input
                                    id="driver-complement"
                                    name="complement"
                                    value={formData.complement}
                                    onChange={(event) => setField('complement', event.target.value)}
                                    placeholder="Ex: Apt. 101"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="driver-neighborhood">Bairro</FieldLabel>
                                <Input
                                    id="driver-neighborhood"
                                    name="neighborhood"
                                    value={formData.neighborhood}
                                    onChange={(event) => setField('neighborhood', event.target.value)}
                                    placeholder="Ex: Centro"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="driver-state">Estado</FieldLabel>
                                <SelectState
                                    id="driver-state"
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
                                <FieldLabel htmlFor="driver-city">Cidade</FieldLabel>
                                <InputCity
                                    id="driver-city"
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
