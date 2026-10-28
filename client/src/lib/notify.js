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

/**
 * Copia para a área de transferência.
 *
 * Só funciona dentro do gesto do usuário: depois de um `await` de requisição
 * o navegador já fechou a janela de ativação e recusa a escrita. Então chame
 * daqui de um onClick, nunca no fim de um submit.
 *
 * O fallback cobre contexto não seguro (http em rede, que é como o sistema
 * costuma ser acessado fora do localhost), onde `navigator.clipboard` nem
 * existe.
 */
export async function copyText(text, message = 'Link copiado') {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text)
        } else {
            legacyCopy(text)
        }

        notifySuccess({ message })
        return true
    } catch {
        notifyError({
            response: {
                status: 400,
                data: { message: 'Não foi possível copiar. Selecione o texto e copie à mão.' },
            },
        })
        return false
    }
}

function legacyCopy(text) {
    const field = document.createElement('textarea')
    field.value = text
    field.setAttribute('readonly', '')
    field.style.position = 'fixed'
    field.style.opacity = '0'
    document.body.appendChild(field)
    field.select()

    try {
        if (!document.execCommand('copy')) throw new Error('execCommand recusou')
    } finally {
        document.body.removeChild(field)
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
