import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { memberApi } from '@/lib/access/members'
import { notifyError, notifySuccess } from '@/lib/notify'

function looksLikeEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

export default function InviteFormPage({ breadcrumbs, listPath, apiBase, roleOptions }) {
    const navigate = useNavigate()
    const access = useMemo(() => memberApi(apiBase), [apiBase])
    const [loading, setLoading] = useState(false)
    const [email, setEmail] = useState('')
    const [role, setRole] = useState(roleOptions[0]?.value ?? 'member')
    const [accountExists, setAccountExists] = useState(false)

    useEffect(() => {
        if (!looksLikeEmail(email)) {
            setAccountExists(false)
            return undefined
        }

        let cancelled = false
        const timer = setTimeout(() => {
            access.lookup(email.trim())
                .then((result) => {
                    if (!cancelled) setAccountExists(Boolean(result?.account_exists))
                })
                .catch(() => {
                    if (!cancelled) setAccountExists(false)
                })
        }, 400)

        return () => {
            cancelled = true
            clearTimeout(timer)
        }
    }, [access, email])

    async function handleSubmit(event) {
        event.preventDefault()
        setLoading(true)

        try {
            const response = await access.invite({ email: email.trim(), role })
            const created = response.data ?? response
            const url = created.invite_url
            const exists = Boolean(created.account_exists)
            notifySuccess(
                { message: exists ? 'Convite enviado. Esta pessoa já tem conta e só precisa aceitar.' : 'Convite enviado' },
                url ? { action: { label: 'Copiar link', onClick: () => navigator.clipboard.writeText(url) } } : undefined,
            )
            navigate(`${listPath}?aba=convites`)
        } catch (error) {
            notifyError(error)
        } finally {
            setLoading(false)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={breadcrumbs}
                buttons={[
                    { title: 'Cancelar', variant: 'outline', to: listPath },
                    {
                        title: 'Salvar',
                        permission: 'write',
                        onClick: () => document.getElementById('invite-form')?.requestSubmit(),
                    },
                ]}
            />

            <LoadingWrapper loading={loading}>
                <form id="invite-form" className="flex w-full flex-col gap-10" onSubmit={handleSubmit}>
                    <FieldSet>
                        <FieldLegend variant="label">Novo usuário</FieldLegend>
                        <FieldDescription>
                            Um e-mail por convite. Quem já tem conta na Logiv só precisa aceitar. O link vale 7 dias.
                        </FieldDescription>
                        <FieldGroup className="md:grid grid-cols-1 md:grid-cols-12">
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="invite-email">E-mail</FieldLabel>
                                <Input
                                    id="invite-email"
                                    name="email"
                                    type="email"
                                    value={email}
                                    placeholder="Ex: ana@empresa.com.br"
                                    autoComplete="off"
                                    required
                                    onChange={(event) => setEmail(event.target.value)}
                                />
                                {accountExists ? (
                                    <FieldDescription>Já tem conta, só vai aceitar.</FieldDescription>
                                ) : null}
                            </Field>
                            <Field className="col-span-12 md:col-span-6">
                                <FieldLabel htmlFor="invite-role">Papel</FieldLabel>
                                <Select items={roleOptions} value={role} onValueChange={setRole}>
                                    <SelectTrigger id="invite-role" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {roleOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        </FieldGroup>
                    </FieldSet>
                </form>
            </LoadingWrapper>
        </>
    )
}
