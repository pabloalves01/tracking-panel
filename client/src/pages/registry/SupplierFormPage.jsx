import { useState, useEffect } from 'react'
import { useNavigate, useParams } from 'react-router'
import { InputCity } from '@/components/common/inputs/InputCity'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
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
import { createSupplier, getSupplier, updateSupplier } from '@/lib/registry/suppliers'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/cadastros/entidades/fornecedores'

const personTypes = [
    { label: 'Pessoa jurídica', value: 'pj' },
    { label: 'Pessoa física', value: 'pf' },
]

export default function SupplierFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = !!uuid
    const [loading, setLoading] = useState(false)
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
    })

    const getEntityByDocument = async (document) => {
        
    }

    useEffect(() => {
        
    }, [])

    useEffect(() => {
        if (!isEditing) {
            return
        }

        setLoading(true)

        getSupplier(uuid)
            .then((response) => {
                const supplier = response.data ?? {}
                setFormData({
                    person_type: supplier.person_type || 'pj',
                    name: supplier.name ?? '',
                    trade_name: supplier.trade_name ?? '',
                    cpf_cnpj: supplier.cpf_cnpj ?? '',
                    email: supplier.email ?? '',
                    phone: supplier.phone ?? '',
                    postal_code: supplier.postal_code ?? '',
                    street: supplier.street ?? '',
                    street_number: supplier.street_number ?? '',
                    complement: supplier.complement ?? '',
                    neighborhood: supplier.neighborhood ?? '',
                    state_id: supplier.state_id ?? null,
                    city_id: supplier.city_id ?? null,
                    pix_key_type: supplier.pix_key_type || 'cpf',
                    pix_key: supplier.pix_key ?? '',
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

        const payload = {
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
                city_id: formData.city_id || null   ,
            },
            pix_key_type: formData.pix_key ? formData.pix_key_type : null,
            pix_key: formData.pix_key || null,
        }

        setLoading(true)

        try {
            const response = isEditing
                ? await updateSupplier(uuid, payload)
                : await createSupplier(payload)
            notifySuccess(response)
            navigate(listPath)
        } catch (error) {
            notifyError(error)
        } finally {
            setLoading(false)
        }
    }

    const isCompany = formData.person_type === 'pj'

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Cadastros', to: '/app/cadastros' },
                    { text: 'Entidades', to: '/app/cadastros/entidades' },
                    { text: 'Fornecedores', to: listPath },
                    { text: isEditing ? 'Editar fornecedor' : 'Novo fornecedor' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: listPath },
                    {
                        title: 'Salvar',
                        onClick: () => document.getElementById('supplier-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="supplier-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Dados do fornecedor</FieldLegend>
                        <FieldDescription>
                            Se o documento já existir, a pessoa é reaproveitada e passa a ser fornecedora.
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="person-type">Tipo de pessoa</FieldLabel>
                                <Select
                                    items={personTypes}
                                    value={formData.person_type}
                                    onValueChange={(value) => setField('person_type', value)}
                                >
                                    <SelectTrigger id="person-type" className="w-full">
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
                                <FieldLabel htmlFor="name">{isCompany ? 'Razão social' : 'Nome'}</FieldLabel>
                                <Input
                                    id="name"
                                    name="name"
                                    value={formData.name}
                                    onChange={(event) => setField('name', event.target.value)}
                                    placeholder={isCompany ? 'Ex: Melo Transporte LTDA' : 'Ex: João da Silva'}
                                    required
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="cpf-cnpj">{isCompany ? 'CNPJ' : 'CPF'}</FieldLabel>
                                <InputCpfCnpj
                                    type={isCompany ? 'cnpj' : 'cpf'}
                                    id="cpf-cnpj"
                                    name="cpf_cnpj"
                                    value={formData.cpf_cnpj}
                                    onChange={(cpf_cnpj) => setField('cpf_cnpj', cpf_cnpj)}
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
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="phone">Telefone</FieldLabel>
                                <InputPhone
                                    id="phone"
                                    name="phone"
                                    value={formData.phone}
                                    onChange={(phone) => setField('phone', phone)}
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
                        </FieldGroup>
                    </FieldSet>

                    <Separator />

                    <FieldSet>
                        <FieldLegend variant="label">Endereço</FieldLegend>
                        <FieldDescription>
                            Preencha os campos abaixo para informar o endereço do fornecedor.
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="supplier-postal-code">CEP</FieldLabel>
                                <InputPostalCode
                                    id="supplier-postal-code"
                                    value={formData.postal_code}
                                    onChange={(postalCode) => setField('postal_code', postalCode)}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="supplier-street">Rua</FieldLabel>
                                <Input
                                    id="supplier-street"
                                    name="street"
                                    value={formData.street}
                                    onChange={(event) => setField('street', event.target.value)}
                                    placeholder="Ex: Rua das Flores"
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-3">
                                <FieldLabel htmlFor="supplier-street-number">Número</FieldLabel>
                                <Input
                                    id="supplier-street-number"
                                    name="street_number"
                                    value={formData.street_number}
                                    onChange={(event) => setField('street_number', event.target.value)}
                                    placeholder="Ex: 123"
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="supplier-complement">Complemento</FieldLabel>
                                <Input
                                    id="supplier-complement"
                                    name="complement"
                                    value={formData.complement}
                                    onChange={(event) => setField('complement', event.target.value)}
                                    placeholder="Ex: Apt. 101"
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="supplier-neighborhood">Bairro</FieldLabel>
                                <Input
                                    id="supplier-neighborhood"
                                    name="neighborhood"
                                    value={formData.neighborhood}
                                    onChange={(event) => setField('neighborhood', event.target.value)}
                                    placeholder="Ex: Centro"
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="supplier-state">Estado</FieldLabel>
                                <SelectState
                                    id="supplier-state"
                                    value={formData.state_id}
                                    onChange={(stateId) => {
                                        setFormData((current) => ({
                                            ...current,
                                            state_id: stateId,
                                            city_id: null,
                                        }))
                                    }}
                                />
                            </Field>
                            <Field className="col-span-12 md:col-span-6" data-disabled={formData.state_id ? undefined : true}>
                                <FieldLabel htmlFor="supplier-city">Cidade</FieldLabel>
                                <InputCity
                                    id="supplier-city"
                                    stateId={formData.state_id}
                                    value={formData.city_id}
                                    onChange={(cityId) => setField('city_id', cityId)}
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>
                </form>
            </LoadingWrapper>
        </>
    )
}
