import axios from 'axios';

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    // timeout: 5000,
    headers: {
        Accept: 'application/json',
    },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const isLogin = error.config?.url?.includes('/auth/login')
        // Telas públicas tratam a sessão vencida sozinhas, sem sair da página.
        const publicPage = ['/auth/login', '/auth/convite/', '/auth/register/posto']
            .some((path) => window.location.pathname.startsWith(path))
        // Perdeu o acesso ao contexto do token (ex.: foi removido): volta pra escolha.
        const lostContext = error.response?.status === 403
            && ['Você não tem acesso a esta empresa', 'Você não tem acesso a este posto'].includes(error.response.data?.message)
        if (lostContext && !publicPage) {
            window.location.assign('/auth/contextos')
        }
        if (error.response?.status === 401 && !isLogin) {
            localStorage.removeItem('token')
            if (!publicPage) {
                window.location.assign('/auth/login')
            }
        }
        return Promise.reject(error)
    },
)