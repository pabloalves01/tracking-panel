import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { memberApi } from '@/lib/access/members'
import { notifyError, notifySuccess } from '@/lib/notify'

export default function InviteFormPage({ breadcrumbs, listPath, apiBase, roleOptions }) {
    const navigate = useNavigate()
    const access = useMemo(() => memberApi(apiBase), [apiBase])
    const [loading, setLoading] = useState(false)
    const [email, setEmail] = useState('')
    const [role, setRole] = useState(roleOptions[0]?.value ?? 'member')
    const [inviteUrl, setInviteUrl] = useState('')

    async function handleSubmit(event) {
        event.preventDefault()
        setLoading(true)

        try {
            const invitation = await access.invite({ email, role })
            notifySuccess({ message: 'Convite criado' })
            setInviteUrl(invitation?.invite_url ?? '')
        } catch (error) {
            notifyError(error)
        } finally {
            setLoading(false)
        }
    }

    async function copyLink() {
        try {
            await navigator.clipboard.writeText(inviteUrl)
            notifySuccess({ message: 'Link copiado' })
        } catch (error) {
            notifyError(error)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={breadcrumbs}
                buttons={[
                    { title: 'Voltar', variant: 'outline', to: listPath },
                    inviteUrl
                        ? null
                        : {
                            title: 'Enviar convite',
                            onClick: () => document.getElementById('invite-form')?.requestSubmit(),
                        },
                ].filter(Boolean)}
            />

            <LoadingWrapper loading={loading}>
                {inviteUrl ? (
                    <div className="flex max-w-xl flex-col gap-4">
                        <FieldSet>
                            <FieldLegend variant="label">Link do convite</FieldLegend>
                            <FieldDescription>
                                Copie e envie para {email}. O link vale 7 dias e não é enviado por e-mail.
                            </FieldDescription>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="invite-url">Link</FieldLabel>
                                    <Input id="invite-url" readOnly value={inviteUrl} />
                                </Field>
                            </FieldGroup>
                        </FieldSet>
                        <div className="flex gap-2">
                            <Button type="button" onClick={copyLink}>Copiar link</Button>
                            <Button type="button" variant="outline" onClick={() => navigate(listPath)}>
                                Voltar para usuários
                            </Button>
                        </div>
                    </div>
                ) : (
                    <form id="invite-form" className="flex max-w-xl flex-col gap-6" onSubmit={handleSubmit}>
                        <FieldSet>
                            <FieldLegend variant="label">Convidar pessoa</FieldLegend>
                            <FieldDescription>
                                A pessoa entra com o papel escolhido depois de aceitar o link.
                            </FieldDescription>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="invite-email">E-mail</FieldLabel>
                                    <Input
                                        id="invite-email"
                                        type="email"
                                        required
                                        autoComplete="off"
                                        placeholder="pessoa@empresa.com.br"
                                        value={email}
                                        onChange={(event) => setEmail(event.target.value)}
                                    />
                                </Field>
                                <Field>
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
                )}
            </LoadingWrapper>
        </>
    )
}
