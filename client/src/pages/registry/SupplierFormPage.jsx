import { useState, useEffect } from 'react'
import { useNavigate, useParams } from 'react-router'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { InputPhone } from '@/components/common/inputs/InputPhone'
import { InputPix } from '@/components/common/inputs/InputPix'
import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
        pix_key_type: 'cpf',
        pix_key: '',
    })

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
                        <FieldGroup className="md:grid md:grid-cols-3">
                            <Field>
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
                            <Field>
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
                            <Field>
                                <FieldLabel htmlFor="trade-name">Nome fantasia</FieldLabel>
                                <Input
                                    id="trade-name"
                                    name="trade_name"
                                    value={formData.trade_name}
                                    onChange={(event) => setField('trade_name', event.target.value)}
                                    placeholder="Ex: Melo Transporte"
                                />
                            </Field>
                            <Field>
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
                            <Field>
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
                            <Field>
                                <FieldLabel htmlFor="phone">Telefone</FieldLabel>
                                <InputPhone
                                    id="phone"
                                    name="phone"
                                    value={formData.phone}
                                    onChange={(phone) => setField('phone', phone)}
                                />
                            </Field>
                            <Field>
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
                </form>
            </LoadingWrapper>
        </>
    )
}
