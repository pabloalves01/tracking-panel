import { toast } from 'sonner'

const titles = {
    success: 'Sucesso',
    error: 'Não foi possível realizar a ação',
    server: 'Erro do servidor',
}

/**
 * Toast padronizado.
 *
 * O título é fixo: sucesso, erro de validação ou erro do servidor.
 * A descrição vem da API (`message` ou o primeiro erro de campo).
 */
export function notifySuccess(payload, options = {}) {
    toast.success(titles.success, {
        description: options.description ?? getApiMessage(payload, 'Operação concluída.'),
        action: options.action,
        duration: options.duration,
    })
}

export function apiMessage(error, fallback = 'Não foi possível completar a operação.') {
    return getApiMessage(error?.response?.data ?? error, fallback)
}

export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text)
        notifySuccess({ message: 'Link copiado' })
    } catch {
        notifyError({ response: { status: 400, data: { message: 'Não foi possível copiar o link.' } } })
    }
}

export function notifyError(error) {
    const status = error?.response?.status
    const title = !status || status >= 500 ? titles.server : titles.error

    toast.error(title, {
        description: getApiMessage(error?.response?.data, 'Não foi possível completar a operação.'),
    })
}

function getApiMessage(payload, fallback) {
    if (typeof payload === 'string' && payload.trim()) {
        return payload
    }

    const firstFieldError = payload?.errors
        ? Object.values(payload.errors).flat().find(Boolean)
        : null

    if (typeof firstFieldError === 'string' && firstFieldError.trim()) {
        return firstFieldError
    }

    if (typeof payload?.message === 'string' && payload.message.trim()) {
        return payload.message
    }

    return fallback
}
