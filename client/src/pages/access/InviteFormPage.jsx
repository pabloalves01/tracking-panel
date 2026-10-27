import { useMemo, useState } from 'react'
import { XIcon } from 'lucide-react'
import { useNavigate } from 'react-router'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { memberApi } from '@/lib/access/members'
import { notifyError, notifySuccess } from '@/lib/notify'

function splitEmails(value) {
    return String(value)
        .split(/[\s,;]+/)
        .map((email) => email.trim())
        .filter(Boolean)
}

function uniqueEmails(emails) {
    const seen = new Set()

    return emails.filter((email) => {
        const key = email.toLowerCase()

        if (seen.has(key)) {
            return false
        }

        seen.add(key)
        return true
    })
}

function looksLikeEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

export default function InviteFormPage({ breadcrumbs, listPath, apiBase, roleOptions }) {
    const navigate = useNavigate()
    const access = useMemo(() => memberApi(apiBase), [apiBase])
    const [loading, setLoading] = useState(false)
    const [draft, setDraft] = useState('')
    const [emails, setEmails] = useState([])
    const [role, setRole] = useState(roleOptions[0]?.value ?? 'member')
    const [results, setResults] = useState([])
    const [formError, setFormError] = useState('')

    function addDraft() {
        const next = uniqueEmails([...emails, ...splitEmails(draft)])
        setEmails(next)
        setDraft('')
        setFormError(next.length > 20 ? 'Envie no máximo 20 e-mails por vez.' : '')
    }

    function removeEmail(email) {
        setEmails((current) => current.filter((item) => item !== email))
        setFormError('')
    }

    async function handleSubmit(event) {
        event.preventDefault()
        const pending = uniqueEmails([...emails, ...splitEmails(draft)])
        setEmails(pending)
        setDraft('')

        if (pending.length === 0) {
            setFormError('Informe ao menos um e-mail.')
            return
        }

        if (pending.length > 20) {
            setFormError('Envie no máximo 20 e-mails por vez.')
            return
        }

        setLoading(true)
        setFormError('')

        try {
            const response = await access.invite({ emails: pending, role })
            setResults(response.results ?? [])
            notifySuccess({ message: 'Convites processados' })
        } catch (error) {
            notifyError(error)
        } finally {
            setLoading(false)
        }
    }

    async function copyLink(url) {
        try {
            await navigator.clipboard.writeText(url)
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
                    results.length
                        ? null
                        : {
                            title: 'Enviar convites',
                            onClick: () => document.getElementById('invite-form')?.requestSubmit(),
                        },
                ].filter(Boolean)}
            />

            <LoadingWrapper loading={loading}>
                {results.length ? (
                    <div className="flex max-w-xl flex-col gap-4">
                        <FieldSet>
                            <FieldLegend variant="label">Resultado</FieldLegend>
                            <FieldDescription>
                                Cada e-mail foi tratado à parte. Quem já tem conta só precisa aceitar. O convite também segue por e-mail e o link vale 7 dias.
                            </FieldDescription>
                        </FieldSet>
                        <ul className="flex flex-col gap-3">
                            {results.map((result) => (
                                <li key={result.email || result.message} className="rounded-xl border p-4">
                                    <p className="font-medium">{result.email || 'E-mail inválido'}</p>
                                    <p className={result.ok ? 'text-sm text-muted-foreground' : 'text-sm text-destructive'}>
                                        {result.message}
                                    </p>
                                    {result.invite_url ? (
                                        <Button className="mt-3" type="button" variant="outline" onClick={() => copyLink(result.invite_url)}>
                                            Copiar link
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                        <Button type="button" variant="outline" onClick={() => navigate(listPath)}>
                            Voltar para usuários
                        </Button>
                    </div>
                ) : (
                    <form id="invite-form" className="flex max-w-xl flex-col gap-6" onSubmit={handleSubmit}>
                        <FieldSet>
                            <FieldLegend variant="label">Convidar pessoas</FieldLegend>
                            <FieldDescription>
                                Vários e-mails, um papel só. Quem já tem conta na Logiv só vai aceitar o convite.
                            </FieldDescription>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="invite-emails">E-mails</FieldLabel>
                                    <textarea
                                        id="invite-emails"
                                        className="min-h-24 w-full rounded-xl border border-input bg-input/30 px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        placeholder="um@empresa.com.br, outro@empresa.com.br"
                                        value={draft}
                                        onChange={(event) => {
                                            setDraft(event.target.value)
                                            setFormError('')
                                        }}
                                        onBlur={addDraft}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ',') {
                                                event.preventDefault()
                                                addDraft()
                                            }
                                        }}
                                    />
                                    {emails.length ? (
                                        <ul className="flex flex-wrap gap-2">
                                            {emails.map((email) => (
                                                <li key={email}>
                                                    <button
                                                        type="button"
                                                        className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs ${looksLikeEmail(email) ? '' : 'border-destructive text-destructive'}`}
                                                        onClick={() => removeEmail(email)}
                                                    >
                                                        {email}
                                                        <XIcon className="size-3" />
                                                        <span className="sr-only">Remover {email}</span>
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : null}
                                    {formError ? <p className="text-sm text-destructive" role="alert">{formError}</p> : null}
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
