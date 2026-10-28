import { useState } from 'react'
import { useNavigate } from 'react-router'

import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { createInvitation } from '@/lib/access/members'
import { useSession } from '@/lib/auth/session'
import { copyText, notifyError, notifySuccess } from '@/lib/notify'

/**
 * Convidar uma pessoa para a organização ativa.
 *
 * Um e-mail por vez: o convite em lote chegou a existir e foi rejeitado —
 * o padrão aqui é um registro por formulário.
 *
 * Sem escolha de papel: todo membro tem o mesmo acesso por enquanto.
 */
export default function InviteFormPage() {
    const navigate = useNavigate()
    const { current_organization: organization } = useSession()
    const [email, setEmail] = useState('')
    const [saving, setSaving] = useState(false)

    const basePath = organization?.home_path ?? '/app'
    const listPath = `${basePath}/usuarios`

    async function handleSubmit(event) {
        event.preventDefault()
        setSaving(true)

        try {
            const response = await createInvitation(email.trim())
            const url = response.data?.invite_url

            notifySuccess(response)

            // O link fica à mão para quem quiser mandar por outro canal —
            // o e-mail às vezes demora ou cai no spam.
            if (url) {
                copyText(url, 'Link do convite copiado')
            }

            navigate(`${listPath}?aba=convites`)
        } catch (error) {
            notifyError(error)
            setSaving(false)
        }
    }

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: basePath },
                    { text: 'Usuários', to: listPath },
                    { text: 'Convidar' },
                ]}
                buttons={[
                    { title: 'Cancelar', variant: 'ghost', to: listPath },
                    {
                        title: saving ? 'Enviando…' : 'Enviar convite',
                        onClick: handleSubmit,
                        disabled: saving || !email.trim(),
                    },
                ]}
            />

            <form onSubmit={handleSubmit} className="max-w-xl">
                <FieldSet>
                    <FieldLegend variant="label">Quem você quer convidar</FieldLegend>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="email">E-mail</FieldLabel>
                            <Input
                                id="email"
                                type="email"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                placeholder="nome@empresa.com.br"
                                autoFocus
                                required
                            />
                            <FieldDescription>
                                A pessoa recebe um link para entrar em{' '}
                                {organization?.display_name ?? 'sua organização'}. Se ela já tiver
                                conta no LogIV, é só aceitar; se não, cria a conta na hora.
                                O convite vale por 7 dias.
                            </FieldDescription>
                        </Field>
                    </FieldGroup>
                </FieldSet>
            </form>
        </>
    )
}
