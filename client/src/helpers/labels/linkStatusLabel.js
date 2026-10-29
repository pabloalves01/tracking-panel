export const linkStatusLabel = {
    request_sent: 'Solicitação enviada',
    active: 'Ativo',
    rejected: 'Recusado',
    disconnected: 'Conexão encerrada',
}

export function statusBadgeClass(status) {
    if (status === 'active' || status === 'accepted') {
        return 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
    }

    if (status === 'request_sent' || status === 'pending') {
        return 'border-transparent bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
    }

    if (status === 'rejected' || status === 'declined' || status === 'revoked') {
        return 'border-transparent bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200'
    }

    return 'border-transparent bg-muted text-muted-foreground'
}

export const invitationStatusLabel = {
    pending: 'Pendente',
    accepted: 'Aceito',
    declined: 'Recusado',
    revoked: 'Cancelado',
    expired: 'Expirado',
}
