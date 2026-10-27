import { useEffect, useState } from "react"
import { useNavigate, useParams } from "react-router"
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { InputCity } from "@/components/common/inputs/InputCity"
import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { SelectState } from "@/components/common/inputs/SelectState"
import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { Button } from "@/components/ui/button"
import { createCompany, getCompany, updateCompany } from "@/lib/system/companies"
import { notifyError, notifySuccess } from "@/lib/notify"
import { Separator } from "@/components/ui/separator";

export default function CompanyFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = !!uuid

    const [loading, setLoading] = useState(isEditing)
    const [invite, setInvite] = useState(null)
    const [formData, setFormData] = useState({
        legal_name: '',
        trade_name: '',
        cnpj: '',
        state_id: null,
        city_id: null,
        admin_email: '',
    })

    useEffect(() => {
        if (!uuid) return

        getCompany(uuid)
            .then((company) => {
                setFormData({
                    legal_name: company.legal_name,
                    trade_name: company.trade_name,
                    cnpj: company.cnpj,
                    state_id: company.state_id ?? null,
                    city_id: company.city_id ?? null,
                })
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [uuid])

    async function handleSubmit(event) {
        event.preventDefault()
        setLoading(true)

        try {
            const company = {
                legal_name: formData.legal_name,
                trade_name: formData.trade_name,
                cnpj: formData.cnpj,
                state_id: formData.state_id,
                city_id: formData.city_id,
            }
            if (isEditing) {
                notifySuccess(await updateCompany(uuid, company))
                navigate('/app/sistema/empresas')
                return
            }

            const response = await createCompany({ ...company, admin_email: formData.admin_email })
            notifySuccess(response)
            setInvite(response.invitation)
        } catch (error) {
            notifyError(error)
        } finally {
            setLoading(false)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Sistema', to: '/app/sistema' },
                    { text: 'Empresas', to: '/app/sistema/empresas' },
                    { text: isEditing ? 'Editar empresa' : 'Nova empresa' },
                ]}
                buttons={invite ? [
                    { title: 'Voltar para empresas', variant: 'outline', to: '/app/sistema/empresas' },
                ] : [
                    { title: 'Cancelar', variant: 'outline', to: '/app/sistema/empresas' },
                    {
                        title: 'Salvar',
                        onClick: () => document.getElementById('company-form')?.requestSubmit(),
                    },
                ]}
            />

            {invite ? (
                <div className="flex max-w-xl flex-col gap-4">
                    <FieldSet>
                        <FieldLegend variant="label">Convite do proprietário</FieldLegend>
                        <FieldDescription>
                            A empresa foi criada. O convite também segue por e-mail para {invite.email}. O link vale 7 dias.
                        </FieldDescription>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="company-invite-url">Link</FieldLabel>
                                <Input id="company-invite-url" readOnly value={invite.invite_url} />
                            </Field>
                        </FieldGroup>
                    </FieldSet>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            onClick={async () => {
                                try {
                                    await navigator.clipboard.writeText(invite.invite_url)
                                    notifySuccess({ message: 'Link copiado' })
                                } catch (error) {
                                    notifyError(error)
                                }
                            }}
                        >
                            Copiar link
                        </Button>
                        <Button type="button" variant="outline" onClick={() => navigate('/app/sistema/empresas')}>
                            Voltar para empresas
                        </Button>
                    </div>
                </div>
            ) : null}

            <LoadingWrapper loading={loading}>
                <form id="company-form" className={`flex w-full flex-col gap-10 ${invite ? 'hidden' : ''}`} onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Dados da empresa</FieldLegend>
                        <FieldDescription>Informações cadastrais usadas na operação.</FieldDescription>
                        <FieldGroup className="md:grid md:grid-cols-3">
                            <Field>
                                <FieldLabel htmlFor="legal-name">Razão social</FieldLabel>
                                <Input
                                    id="legal-name"
                                    name="legal_name"
                                    value={formData.legal_name}
                                    onChange={(e) => setFormData({ ...formData, legal_name: e.target.value })}
                                    placeholder="Ex: Melo Transporte e Logísitca LTDA"
                                    autoComplete="organization"
                                    required
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="trade-name">Nome fantasia</FieldLabel>
                                <Input
                                    id="trade-name"
                                    name="trade_name"
                                    value={formData.trade_name}
                                    onChange={(e) => setFormData({ ...formData, trade_name: e.target.value })}
                                    placeholder="Ex: Melo Transporte"
                                    autoComplete="organization"
                                    required
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="cnpj">CNPJ</FieldLabel>
                                <InputCpfCnpj
                                    type="cnpj"
                                    id="cnpj"
                                    name="cnpj"
                                    value={formData.cnpj}
                                    onChange={(cnpj) => setFormData({ ...formData, cnpj })}
                                    required
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    <Separator />

                    <FieldSet>
                        <FieldLegend variant="label">Localização</FieldLegend>
                        <FieldDescription>
                            Estado e cidade oficiais do IBGE. A cidade abre depois do estado.
                        </FieldDescription>
                        <FieldGroup className="md:grid md:grid-cols-3">
                            <Field>
                                <FieldLabel htmlFor="company-state">Estado</FieldLabel>
                                <SelectState
                                    id="company-state"
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
                            <Field data-disabled={formData.state_id ? undefined : true}>
                                <FieldLabel htmlFor="company-city">Cidade</FieldLabel>
                                <InputCity
                                    id="company-city"
                                    stateId={formData.state_id}
                                    value={formData.city_id}
                                    onChange={(cityId) => {
                                        setFormData((current) => ({ ...current, city_id: cityId }))
                                    }}
                                />
                            </Field>
                        </FieldGroup>
                    </FieldSet>

                    {!isEditing ? (
                        <>
                            <Separator />
                            <FieldSet>
                                <FieldLegend variant="label">Proprietário</FieldLegend>
                                <FieldDescription>
                                    A Logiv não cria a senha. Esta pessoa recebe um convite de proprietário e cria a conta ao aceitar.
                                </FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="admin-email">E-mail</FieldLabel>
                                        <Input
                                            id="admin-email"
                                            type="email"
                                            value={formData.admin_email}
                                            placeholder="Ex: proprietario@empresa.com.br"
                                            onChange={(e) => setFormData({ ...formData, admin_email: e.target.value })}
                                            autoComplete="off"
                                            required
                                        />
                                    </Field>
                                </FieldGroup>
                            </FieldSet>
                        </>
                    ) : null}
                </form>
            </LoadingWrapper>
        </>
    )
}
