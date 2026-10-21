import { useState, useEffect, useRef } from 'react'
import { TriangleAlert } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { InputCity } from '@/components/common/inputs/InputCity'
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
import { createCustomer, getCustomer, updateCustomer } from '@/lib/registry/customers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/clientes'

const personTypes = [
    { label: 'Pessoa jurídica', value: 'pj' },
    { label: 'Pessoa física', value: 'pf' },
]

const emergencyPhoneTypes = [
    { label: 'Celular', value: 'mobile' },
    { label: 'Residencial', value: 'home' },
    { label: 'Trabalho', value: 'work' },
    { label: 'Outro', value: 'other' },
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
    rg_ie: '',
    birth_date: '',
    emergency_phone: '',
    emergency_phone_type: 'mobile',
    document_file_path: '',
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
        rg_ie: source.rg_ie ?? '',
        birth_date: source.birth_date ?? '',
        emergency_phone: source.emergency_phone ?? '',
        emergency_phone_type: source.emergency_phone_type || 'mobile',
        document_file_path: source.document_file_path ?? '',
        postal_code: source.postal_code ?? '',
        street: source.street ?? '',
        street_number: source.street_number ?? '',
        complement: source.complement ?? '',
        neighborhood: source.neighborhood ?? '',
        state_id: source.state_id ?? null,
        city_id: source.city_id ?? null,
    }
}

export default function CustomerFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = !!uuid

    const [loading, setLoading] = useState(false)
    const [existingEntity, setExistingEntity] = useState(false)
    const [existingCustomer, setExistingCustomer] = useState(false)
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
        rg_ie: '',
        birth_date: '',
        emergency_phone: '',
        emergency_phone_type: 'mobile',
        document_file_path: '',
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
        setExistingCustomer(false)
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
                setExistingCustomer(Boolean(entity.customer_exists))
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

        getCustomer(uuid)
            .then((response) => {
                const customer = response.data ?? {}
                setEntityUuid(customer.entity_uuid ?? null)
                setFormData(entityFieldsFrom(customer))
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [isEditing, uuid])

    function setField(field, value) {
        setFormData((current) => ({ ...current, [field]: value }))
    }

    async function handleSubmit(event) {
        event.preventDefault()

        if (!isEditing && existingCustomer) {
            return
        }

        const payload = isEditing
            ? {}
            : {
                entity: {
                    person_type: formData.person_type,
                    name: formData.name,
                    trade_name: formData.trade_name || null,
                    cpf_cnpj: formData.cpf_cnpj,
                    email: formData.email || null,
                    phone: formData.phone || null,
                    rg_ie: formData.rg_ie || null,
                    birth_date: formData.birth_date || null,
                    emergency_phone: formData.emergency_phone || null,
                    emergency_phone_type: formData.emergency_phone ? formData.emergency_phone_type : null,
                    document_file_path: formData.document_file_path || null,
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
                ? await updateCustomer(uuid, payload)
                : await createCustomer(payload)
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
                    { text: 'Clientes', to: listPath },
                    { text: isEditing ? 'Editar cliente' : 'Novo cliente' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: listPath },
                    {
                        title: 'Salvar',
                        disabled: !isEditing && existingCustomer,
                        onClick: () => document.getElementById('customer-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="customer-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Dados do cliente</FieldLegend>
                        <FieldDescription>
                            O cliente só vincula a pessoa. Nome, documento e contato pertencem à entidade.
                        </FieldDescription>

                        {entityLocked && !existingCustomer && (
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

                        {existingCustomer && !isEditing && (
                            <div
                                role="status"
                                className="col-span-12 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-50"
                            >
                                <TriangleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <p className="leading-5">
                                    <span className="font-semibold">Atenção.</span>{' '}
                                    Já existe um cliente com este documento.
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
                                        <SelectValue />
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
                                <FieldLabel htmlFor="rg-ie">{isCompany ? 'Inscrição estadual' : 'RG'}</FieldLabel>
                                <Input
                                    id="rg-ie"
                                    name="rg_ie"
                                    value={formData.rg_ie}
                                    onChange={(event) => setField('rg_ie', event.target.value)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="birth-date">Data de nascimento</FieldLabel>
                                <DatePicker
                                    id="birth-date"
                                    value={formData.birth_date}
                                    onChange={(birthDate) => setField('birth_date', birthDate)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-4">
                                <FieldLabel htmlFor="emergency-phone-type">Tipo do telefone de emergência</FieldLabel>
                                <Select
                                    items={emergencyPhoneTypes}
                                    value={formData.emergency_phone_type}
                                    onValueChange={(value) => setField('emergency_phone_type', value)}
                                    disabled={entityLocked}
                                >
                                    <SelectTrigger id="emergency-phone-type" className="w-full" disabled={entityLocked}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {emergencyPhoneTypes.map((item) => (
                                                <SelectItem key={item.value} value={item.value}>
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field className="col-span-12 md:col-span-8">
                                <FieldLabel htmlFor="emergency-phone">Telefone de emergência</FieldLabel>
                                <InputPhone
                                    id="emergency-phone"
                                    name="emergency_phone"
                                    value={formData.emergency_phone}
                                    onChange={(phone) => setField('emergency_phone', phone)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12">
                                <FieldLabel htmlFor="document-file-path">Caminho do documento</FieldLabel>
                                <Input
                                    id="document-file-path"
                                    name="document_file_path"
                                    value={formData.document_file_path}
                                    onChange={(event) => setField('document_file_path', event.target.value)}
                                    placeholder="Ex: documentos/rg.pdf"
                                    disabled={entityLocked}
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
                                : 'Preencha os campos abaixo para informar o endereço do cliente.'}
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="customer-postal-code">CEP</FieldLabel>
                                <InputPostalCode
                                    id="customer-postal-code"
                                    value={formData.postal_code}
                                    onChange={(postalCode) => setField('postal_code', postalCode)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="customer-street">Rua</FieldLabel>
                                <Input
                                    id="customer-street"
                                    name="street"
                                    value={formData.street}
                                    onChange={(event) => setField('street', event.target.value)}
                                    placeholder="Ex: Rua das Flores"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="customer-street-number">Número</FieldLabel>
                                <Input
                                    id="customer-street-number"
                                    name="street_number"
                                    value={formData.street_number}
                                    onChange={(event) => setField('street_number', event.target.value)}
                                    placeholder="Ex: 123"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="customer-complement">Complemento</FieldLabel>
                                <Input
                                    id="customer-complement"
                                    name="complement"
                                    value={formData.complement}
                                    onChange={(event) => setField('complement', event.target.value)}
                                    placeholder="Ex: Apt. 101"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="customer-neighborhood">Bairro</FieldLabel>
                                <Input
                                    id="customer-neighborhood"
                                    name="neighborhood"
                                    value={formData.neighborhood}
                                    onChange={(event) => setField('neighborhood', event.target.value)}
                                    placeholder="Ex: Centro"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="customer-state">Estado</FieldLabel>
                                <SelectState
                                    id="customer-state"
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
                                <FieldLabel htmlFor="customer-city">Cidade</FieldLabel>
                                <InputCity
                                    id="customer-city"
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
