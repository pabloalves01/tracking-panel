import { useState, useEffect, useRef } from 'react'
import { TriangleAlert } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { InputCity } from '@/components/common/inputs/InputCity'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { InputPhone } from '@/components/common/inputs/InputPhone'
import { InputPostalCode } from '@/components/common/inputs/InputPostalCode'
import { SelectState } from '@/components/common/inputs/SelectState'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { getEntityByDocument } from '@/lib/registry/entities'
import { createCarrier, getCarrier, updateCarrier } from '@/lib/registry/carriers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/transportadoras'

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

function entityFieldsFrom(source) {
    return {
        person_type: source.person_type || 'pj',
        name: source.name ?? '',
        trade_name: source.trade_name ?? '',
        cpf_cnpj: source.cpf_cnpj ?? '',
        email: source.email ?? '',
        phone: source.phone ?? '',
        postal_code: source.postal_code ?? '',
        street: source.street ?? '',
        street_number: source.street_number ?? '',
        complement: source.complement ?? '',
        neighborhood: source.neighborhood ?? '',
        state_id: source.state_id ?? null,
        city_id: source.city_id ?? null,
    }
}

export default function CarrierFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = !!uuid

    const [loading, setLoading] = useState(false)
    const [existingEntity, setExistingEntity] = useState(false)
    const [existingCarrier, setExistingCarrier] = useState(false)
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
    })

    /**
     * Desfaz o resultado da busca. Os campos só são limpos quando o
     * preenchimento veio de uma entidade; o que foi digitado permanece.
     */
    function clearLookup() {
        setExistingEntity(false)
        setExistingCarrier(false)
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
                setExistingCarrier(Boolean(entity.carrier_exists))
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

        getCarrier(uuid)
            .then((response) => {
                const carrier = response.data ?? {}
                setEntityUuid(carrier.entity_uuid ?? null)
                setFormData(entityFieldsFrom(carrier))
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [isEditing, uuid])

    function setField(field, value) {
        setFormData((current) => ({ ...current, [field]: value }))
    }

    async function handleSubmit(event) {
        event.preventDefault()

        if (!isEditing && existingCarrier) {
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
                ? await updateCarrier(uuid, payload)
                : await createCarrier(payload)
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
                    { text: 'Transportadoras', to: listPath },
                    { text: isEditing ? 'Editar transportadora' : 'Nova transportadora' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: listPath },
                    {
                        title: 'Salvar',
                        disabled: !isEditing && existingCarrier,
                        onClick: () => document.getElementById('carrier-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="carrier-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Dados da transportadora</FieldLegend>
                        <FieldDescription>
                            A transportadora só vincula a pessoa. Nome, documento e contato pertencem à entidade.
                        </FieldDescription>

                        {entityLocked && !existingCarrier && (
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

                        {existingCarrier && !isEditing && (
                            <div
                                role="status"
                                className="col-span-12 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-50"
                            >
                                <TriangleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                <p className="leading-5">
                                    <span className="font-semibold">Atenção.</span>{' '}
                                    Já existe uma transportadora com este documento.
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
                        </FieldGroup>
                    </FieldSet>

                    <Separator />

                    <FieldSet>
                        <FieldLegend variant="label">Endereço</FieldLegend>
                        <FieldDescription>
                            {entityLocked
                                ? 'Endereço cadastrado na entidade.'
                                : 'Preencha os campos abaixo para informar o endereço da transportadora.'}
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="carrier-postal-code">CEP</FieldLabel>
                                <InputPostalCode
                                    id="carrier-postal-code"
                                    value={formData.postal_code}
                                    onChange={(postalCode) => setField('postal_code', postalCode)}
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="carrier-street">Rua</FieldLabel>
                                <Input
                                    id="carrier-street"
                                    name="street"
                                    value={formData.street}
                                    onChange={(event) => setField('street', event.target.value)}
                                    placeholder="Ex: Rua das Flores"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="carrier-street-number">Número</FieldLabel>
                                <Input
                                    id="carrier-street-number"
                                    name="street_number"
                                    value={formData.street_number}
                                    onChange={(event) => setField('street_number', event.target.value)}
                                    placeholder="Ex: 123"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="carrier-complement">Complemento</FieldLabel>
                                <Input
                                    id="carrier-complement"
                                    name="complement"
                                    value={formData.complement}
                                    onChange={(event) => setField('complement', event.target.value)}
                                    placeholder="Ex: Apt. 101"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="carrier-neighborhood">Bairro</FieldLabel>
                                <Input
                                    id="carrier-neighborhood"
                                    name="neighborhood"
                                    value={formData.neighborhood}
                                    onChange={(event) => setField('neighborhood', event.target.value)}
                                    placeholder="Ex: Centro"
                                    disabled={entityLocked}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="carrier-state">Estado</FieldLabel>
                                <SelectState
                                    id="carrier-state"
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
                                <FieldLabel htmlFor="carrier-city">Cidade</FieldLabel>
                                <InputCity
                                    id="carrier-city"
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
