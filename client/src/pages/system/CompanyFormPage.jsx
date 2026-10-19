import { useEffect, useState } from "react"
import { useNavigate, useParams } from "react-router"
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { createCompany, getCompany, updateCompany } from "@/lib/system/companies"
import { notifyError, notifySuccess } from "@/lib/notify"
import { Separator } from "@/components/ui/separator";

export default function CompanyFormPage() {
    const { uuid } = useParams()
    const navigate = useNavigate()
    const isEditing = !!uuid
    
    const [loading, setLoading] = useState(isEditing)
    const [formData, setFormData] = useState({
        legal_name: '',
        trade_name: '',
        cnpj: '',
        user: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        },
    })

    useEffect(() => {
        if (!uuid) return

        getCompany(uuid)
            .then((company) => {
                setFormData({
                    legal_name: company.legal_name,
                    trade_name: company.trade_name,
                    cnpj: company.cnpj,
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
            }
            const response = isEditing
                ? await updateCompany(uuid, company)
                : await createCompany({ ...company, user: formData.user })
            notifySuccess(response)
            navigate('/app/sistema/empresas')
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
                    { text: 'Início', to: '/' },
                    { text: 'Sistema', to: '/app/sistema' },
                    { text: 'Empresas', to: '/app/sistema/empresas' },
                    { text: isEditing ? 'Editar empresa' : 'Nova empresa' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: '/app/sistema/empresas' },
                    {
                        title: 'Salvar',
                        onClick: () => document.getElementById('company-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="company-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
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

                    {!isEditing ? (
                        <FieldSet>
                            <FieldLegend variant="label">Primeiro usuário</FieldLegend>
                            <FieldDescription>
                                Esta pessoa entra no sistema como administradora da empresa e cria os demais acessos.
                            </FieldDescription>
                            <FieldGroup className="md:grid md:grid-cols-2">
                                <Field>
                                    <FieldLabel htmlFor="user-name">Nome</FieldLabel>
                                    <Input
                                        id="user-name"
                                        value={formData.user.name}
                                        placeholder="Ex: Pablo Alves"
                                        onChange={(e) => setFormData({ ...formData, user: { ...formData.user, name: e.target.value } })}
                                        autoComplete="name"
                                        required
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="user-email">E-mail</FieldLabel>
                                    <Input
                                        id="user-email"
                                        type="email"
                                        value={formData.user.email}
                                        placeholder="Ex: usuario@empresa.com.br"
                                        onChange={(e) => setFormData({ ...formData, user: { ...formData.user, email: e.target.value } })}
                                        autoComplete="off"
                                        required
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="user-password">Senha</FieldLabel>
                                    <Input
                                        id="user-password"
                                        type="password"
                                        value={formData.user.password}
                                        placeholder="**********"
                                        onChange={(e) => setFormData({ ...formData, user: { ...formData.user, password: e.target.value } })}
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="user-password-confirmation">Confirmar senha</FieldLabel>
                                    <Input
                                        id="user-password-confirmation"
                                        type="password"
                                        value={formData.user.password_confirmation}
                                        placeholder="**********"
                                        onChange={(e) => setFormData({ ...formData, user: { ...formData.user, password_confirmation: e.target.value } })}
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                            </FieldGroup>
                        </FieldSet>
                    ) : null}
                </form>
            </LoadingWrapper>
        </>
    )
}
