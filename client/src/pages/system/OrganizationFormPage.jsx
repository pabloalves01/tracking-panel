import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { InputCity } from '@/components/common/inputs/InputCity'
import { InputCpfCnpj } from '@/components/common/inputs/InputCpfCnpj'
import { InputPostalCode } from '@/components/common/inputs/InputPostalCode'
import { SelectState } from '@/components/common/inputs/SelectState'
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import {
    createOrganization,
    getOrganization,
    updateOrganization,
} from '@/lib/system/organizations'
import { InviteLinkDialog } from '@/components/access/InviteLinkDialog'
import { notifyError, notifySuccess } from '@/lib/notify'

const listPath = '/app/sistema/organizacoes'

const emptyForm = {
    document: '',
    legal_name: '',
    trade_name: '',
    owner_email: '',
    postal_code: '',
    street: '',
    street_number: '',
    complement: '',
    neighborhood: '',
    state_id: null,
    city_id: null,
}

/**
 * Cadastro e edição de empresa pelo admin do sistema.
 *
 * A empresa nasce junto com o convite do primeiro usuário: o admin do
 * sistema não cria conta por ninguém nem entra na empresa.
 */
export default function OrganizationFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = Boolean(uuid)

    const [form, setForm] = useState(emptyForm)
    const [loading, setLoading] = useState(isEditing)
    const [saving, setSaving] = useState(false)
    const [created, setCreated] = useState(null)

    useEffect(() => {
        if (!isEditing) return

        setLoading(true)
        getOrganization(uuid)
            .then((organization) =>
                setForm({
                    ...emptyForm,
                    ...Object.fromEntries(
                        Object.keys(emptyForm)
                            .filter((key) => organization[key] != null)
                            .map((key) => [key, organization[key]])
                    ),
                })
            )
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [uuid, isEditing])

    function set(field, value) {
        setForm((current) => ({ ...current, [field]: value }))
    }

    async function handleSubmit(event) {
        event.preventDefault()
        setSaving(true)

        try {
            if (isEditing) {
                notifySuccess(await updateOrganization(uuid, form))
            } else {
                const response = await createOrganization(form)
                notifySuccess(response)

                // O convite do dono só mostra o token agora: o diálogo segura
                // a navegação até a pessoa ter copiado o link.
                if (response.invitation?.invite_url) {
                    setCreated(response.invitation)
                    return
                }
            }

            navigate(listPath)
        } catch (error) {
            notifyError(error)
            setSaving(false)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Sistema', to: '/app/sistema' },
                    { text: 'Organizações', to: listPath },
                    { text: isEditing ? 'Editar empresa' : 'Nova empresa' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'ghost', to: listPath },
                    {
                        title: saving ? 'Salvando…' : 'Salvar',
                        onClick: handleSubmit,
                        disabled: saving,
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Empresa</FieldLegend>
                    <FieldDescription>Os dados da empresa, como estão na Receita.</FieldDescription>
                        <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                            <Field className="col-span-12 md:col-span-4 xl:col-span-3">
                                <FieldLabel htmlFor="document">CNPJ</FieldLabel>
                                <InputCpfCnpj
                                    id="document"
                                    type="cnpj"
                                    value={form.document}
                                    onChange={(digits) => set('document', digits)}
                                    disabled={isEditing}
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-8 xl:col-span-5">
                                <FieldLabel htmlFor="legal-name">Razão social</FieldLabel>
                                <Input
                                    id="legal-name"
                                    placeholder="Razão social, como no CNPJ"
                                    value={form.legal_name}
                                    onChange={(event) => set('legal_name', event.target.value)}
                                    required
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-6 xl:col-span-4">
                                <FieldLabel htmlFor="trade-name">Nome fantasia</FieldLabel>
                                <Input
                                    id="trade-name"
                                    placeholder="Como a empresa é conhecida"
                                    value={form.trade_name}
                                    onChange={(event) => set('trade_name', event.target.value)}
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    {!isEditing ? (
                        <>
                            <Separator className="my-6" />
                            <FieldSet>
                                <FieldLegend variant="label">Primeiro acesso</FieldLegend>
                                <FieldDescription>Quem vai administrar recebe um convite por e-mail e cria a própria conta. Você não entra na empresa nem define a senha dela.</FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                                    <Field className="col-span-12 md:col-span-6 xl:col-span-4">
                                        <FieldLabel htmlFor="owner-email">
                                            E-mail de quem vai administrar
                                        </FieldLabel>
                                        <Input
                                            id="owner-email"
                                            placeholder="nome@empresa.com.br"
                                            type="email"
                                            value={form.owner_email}
                                            onChange={(event) => set('owner_email', event.target.value)}
                                            required
                                        />
                                    </Field>
                                </FieldGroup>
                            </FieldSet>
                        </>
                    ) : null}

                    <Separator className="my-6" />

                    <FieldSet>
                        <FieldLegend variant="label">Endereço</FieldLegend>
                    <FieldDescription>O endereço da sede.</FieldDescription>
                        <FieldGroup className="md:grid md:grid-cols-12 md:gap-4">
                            <Field className="col-span-12 md:col-span-3 xl:col-span-2">
                                <FieldLabel htmlFor="postal-code">CEP</FieldLabel>
                                <InputPostalCode
                                    id="postal-code"
                                    value={form.postal_code}
                                    onChange={(digits) => set('postal_code', digits)}
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-7 xl:col-span-5">
                                <FieldLabel htmlFor="street">Rua</FieldLabel>
                                <Input
                                    id="street"
                                    placeholder="Rua, avenida, rodovia…"
                                    value={form.street}
                                    onChange={(event) => set('street', event.target.value)}
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-2 xl:col-span-1">
                                <FieldLabel htmlFor="street-number">Número</FieldLabel>
                                <Input
                                    id="street-number"
                                    placeholder="Nº"
                                    value={form.street_number}
                                    onChange={(event) => set('street_number', event.target.value)}
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-4 xl:col-span-4">
                                <FieldLabel htmlFor="neighborhood">Bairro</FieldLabel>
                                <Input
                                    id="neighborhood"
                                    placeholder="Bairro"
                                    value={form.neighborhood}
                                    onChange={(event) => set('neighborhood', event.target.value)}
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-4 xl:col-span-4">
                                <FieldLabel htmlFor="complement">Complemento</FieldLabel>
                                <Input
                                    id="complement"
                                    placeholder="Sala, bloco, km…"
                                    value={form.complement}
                                    onChange={(event) => set('complement', event.target.value)}
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-2 xl:col-span-3">
                                <FieldLabel htmlFor="state">Estado</FieldLabel>
                                <SelectState
                                    id="state"
                                    value={form.state_id}
                                    onChange={(value) => {
                                        set('state_id', value)
                                        set('city_id', null)
                                    }}
                                />
                            </Field>

                            <Field className="col-span-12 md:col-span-2 xl:col-span-5">
                                <FieldLabel htmlFor="city">Cidade</FieldLabel>
                                <InputCity
                                    id="city"
                                    stateId={form.state_id}
                                    value={form.city_id}
                                    onChange={(value) => set('city_id', value)}
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>
                </form>
            </LoadingWrapper>

            <InviteLinkDialog invite={created} onClose={() => navigate(listPath)} />
        </>
    )
}
