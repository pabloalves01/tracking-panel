import { Spinner } from '@/components/ui/spinner'

/**
 * Enquanto `loading` é verdadeiro, cobre o sistema inteiro (sidebar, cabeçalho e página)
 * com o spinner no centro e bloqueia cliques.
 * O conteúdo continua montado por baixo, então o formulário não perde o que foi digitado.
 */
export function LoadingWrapper({ loading = false, children }) {
    return (
        <>
            {children}
            {loading ? (
                <div
                    aria-busy="true"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-background/70 backdrop-blur-[1px]"
                >
                    <Spinner className="size-8" />
                </div>
            ) : null}
        </>
    )
}
