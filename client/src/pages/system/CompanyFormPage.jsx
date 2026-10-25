import { useEffect, useState } from "react"
import { useNavigate, useParams } from "react-router"
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { InputCity } from "@/components/common/inputs/InputCity"
import { InputCpfCnpj } from "@/components/common/inputs/InputCpfCnpj"
import { SelectState } from "@/components/common/inputs/SelectState"
import { LayoutToolbar } from "@/components/common/LayoutToolbar"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { createCompany, getCompany, updateCompany } from "@/lib/system/companies"
import { lookupUser } from "@/lib/system/users"
import { notifyError, notifySuccess } from "@/lib/notify"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
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
        state_id: null,
        city_id: null,
    })
    // Administrador da empresa: uma conta que já existe, buscada pelo e-mail completo.
    const [userEmail, setUserEmail] = useState('')
    const [selectedUser, setSelectedUser] = useState(null)
    const [userNotFound, setUserNotFound] = useState(false)
    const [searchingUser, setSearchingUser] = useState(false)

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

    async function handleUserSearch() {
        setSearchingUser(true)
        setSelectedUser(null)
        setUserNotFound(false)

        try {
            setSelectedUser(await lookupUser(userEmail))
        } catch (error) {
            if (error.response?.status === 404) {
                setUserNotFound(true)
            } else {
                notifyError(error)
            }
        } finally {
            setSearchingUser(false)
        }
    }

    async function handleSubmit(event) {
        event.preventDefault()

        if (!isEditing && !selectedUser) {
            notifyError({
                response: {
                    status: 422,
                    data: { message: 'Busque e selecione o administrador da empresa.' },
                },
            })
            return
        }

        setLoading(true)

        try {
            const company = {
                legal_name: formData.legal_name,
                trade_name: formData.trade_name,
                cnpj: formData.cnpj,
                state_id: formData.state_id,
                city_id: formData.city_id,
            }
            const response = isEditing
                ? await updateCompany(uuid, company)
                : await createCompany({ ...company, user_email: selectedUser.email })
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
                                <FieldLegend variant="label">Administrador da empresa</FieldLegend>
                                <FieldDescription>
                                    Busque pelo e-mail completo de uma conta que já existe na Logiv. Ela entra como administradora da empresa.
                                </FieldDescription>
                                <FieldGroup className="md:grid md:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="user-email">E-mail</FieldLabel>
                                        <div className="flex gap-2">
                                            <Input
                                                id="user-email"
                                                type="email"
                                                value={userEmail}
                                                placeholder="Ex: usuario@empresa.com.br"
                                                onChange={(e) => {
                                                    setUserEmail(e.target.value)
                                                    setSelectedUser(null)
                                                    setUserNotFound(false)
                                                }}
                                                onKeyDown={(e) => {
                                                    if (e.key !== 'Enter') return
                                                    e.preventDefault()
                                                    if (userEmail) handleUserSearch()
                                                }}
                                                autoComplete="off"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="cursor-pointer"
                                                disabled={!userEmail || searchingUser}
                                                onClick={handleUserSearch}
                                            >
                                                {searchingUser ? 'Buscando...' : 'Buscar'}
                                            </Button>
                                        </div>
                                        {selectedUser ? (
                                            <Alert>
                                                <AlertTitle>{selectedUser.name}</AlertTitle>
                                                <AlertDescription>
                                                    {selectedUser.email} terá acesso de administrador nesta empresa.
                                                </AlertDescription>
                                            </Alert>
                                        ) : null}
                                        {userNotFound ? (
                                            <Alert variant="destructive">
                                                <AlertTitle>Nenhuma conta com este e-mail</AlertTitle>
                                                <AlertDescription>
                                                    Peça para o cliente criar uma conta na Logiv, na tela de login em "Criar conta", e busque de novo.
                                                </AlertDescription>
                                            </Alert>
                                        ) : null}
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
