import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'
import { Building2, Fuel, MailOpen } from 'lucide-react'

import { LoadingWrapper } from '@/components/common/LoadingWrapper'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty'
import { formatCpfCnpj } from '@/helpers/formatters'
import {
    acceptPendingInvitation,
    declinePendingInvitation,
    logout,
    me,
    selectOrganization,
} from '@/lib/auth/auth'
import { notifyError, notifySuccess } from '@/lib/notify'

const typeIcon = {
    company: Building2,
    fuel_station: Fuel,
}

/**
 * Onde a pessoa escolhe em qual organização entrar.
 *
 * Antes se chamava "contexto", que não dizia nada a quem usa. Empresa e
 * posto também apareciam em listas separadas, embora a escolha seja a
 * mesma; agora é uma lista só, com o tipo no selo.
 *
 * Quem tem uma organização só e nenhum convite pendente nem chega aqui: o
 * login já entra direto.
 */
export default function SelectOrganizationPage() {
    const navigate = useNavigate()
    const [user, setUser] = useState(null)
    const [organizations, setOrganizations] = useState([])
    const [invitations, setInvitations] = useState([])
    const [loading, setLoading] = useState(true)
    const [toDecline, setToDecline] = useState(null)

    useEffect(() => {
        me()
            .then((data) => {
                setUser(data.user)
                setOrganizations(data.organizations ?? [])
                setInvitations(data.invitations ?? [])
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [])

    async function enter(uuid) {
        setLoading(true)

        try {
            const response = await selectOrganization(uuid)
            navigate(response.redirect_to)
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function acceptInvite(uuid) {
        setLoading(true)

        try {
            const response = await acceptPendingInvitation(uuid)
            notifySuccess(response)
            navigate(response.redirect_to)
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function declineInvite() {
        if (!toDecline) return

        try {
            notifySuccess(await declinePendingInvitation(toDecline.uuid))
            setInvitations((current) => current.filter((item) => item.uuid !== toDecline.uuid))
        } catch (error) {
            notifyError(error)
        } finally {
            setToDecline(null)
        }
    }

    async function handleLogout() {
        await logout()
        navigate('/auth/login')
    }

    const isEmpty = organizations.length === 0 && invitations.length === 0

    return (
        <div className="mx-auto flex min-h-svh w-full max-w-lg flex-col justify-center gap-6 px-4 py-10">
            <header>
                <h1 className="text-xl font-semibold tracking-tight">
                    {user?.name ? `Olá, ${user.name.split(' ')[0]}` : 'Escolha onde entrar'}
                </h1>
                <p className="text-muted-foreground mt-0.5 text-sm">
                    {isEmpty
                        ? 'Sua conta ainda não está vinculada a nenhuma organização.'
                        : 'Escolha a organização em que você quer trabalhar agora.'}
                </p>
            </header>

            <LoadingWrapper loading={loading}>
                {invitations.length > 0 ? (
                    <section className="grid gap-2">
                        <h2 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            Convites pendentes
                        </h2>
                        {invitations.map((invitation) => (
                            <Card key={invitation.uuid}>
                                <CardContent className="flex flex-wrap items-center gap-3">
                                    <MailOpen className="text-muted-foreground size-4 shrink-0" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {invitation.organization}
                                        </p>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {invitation.invited_by
                                                ? `Convite de ${invitation.invited_by}`
                                                : 'Você foi convidado'}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setToDecline(invitation)}
                                        >
                                            Recusar
                                        </Button>
                                        <Button size="sm" onClick={() => acceptInvite(invitation.uuid)}>
                                            Aceitar
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                ) : null}

                {organizations.length > 0 ? (
                    <section className="mt-2 grid gap-2">
                        {invitations.length > 0 ? (
                            <h2 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                Suas organizações
                            </h2>
                        ) : null}

                        {organizations.map((organization) => {
                            const Icon = typeIcon[organization.type] ?? Building2

                            return (
                                <Card
                                    key={organization.uuid}
                                    render={
                                        <button
                                            type="button"
                                            onClick={() => enter(organization.uuid)}
                                            className="hover:bg-muted/50 focus-visible:ring-ring w-full cursor-pointer text-left transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                        />
                                    }
                                >
                                    <CardContent className="flex items-center gap-3">
                                        <Icon className="text-muted-foreground size-4 shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {organization.display_name}
                                            </p>
                                            <p className="text-muted-foreground truncate text-xs tabular-nums">
                                                {formatCpfCnpj(organization.document)}
                                            </p>
                                        </div>
                                        <Badge variant="outline">{organization.type_label}</Badge>
                                    </CardContent>
                                </Card>
                            )
                        })}
                    </section>
                ) : null}

                {isEmpty ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Building2 />
                            </EmptyMedia>
                            <EmptyTitle>Nenhuma organização</EmptyTitle>
                            <EmptyDescription>
                                Peça a quem administra a empresa para te convidar, ou cadastre um
                                posto para começar.
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button variant="outline" onClick={() => navigate('/auth/register/posto')}>
                                Cadastrar um posto
                            </Button>
                        </EmptyContent>
                    </Empty>
                ) : null}
            </LoadingWrapper>

            <footer className="flex justify-center">
                <Button variant="ghost" size="sm" onClick={handleLogout}>
                    Sair desta conta
                </Button>
            </footer>

            <AlertDialog open={!!toDecline} onOpenChange={(open) => !open && setToDecline(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Recusar convite</AlertDialogTitle>
                        <AlertDialogDescription>
                            Você não vai entrar em {toDecline?.organization}. Para voltar atrás,
                            será preciso pedir um convite novo.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={declineInvite}>
                            Recusar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    )
}
