import { useCanWrite } from '@/lib/auth/permissions'

export function ReadOnlyHint() {
    const canWrite = useCanWrite()

    if (canWrite) {
        return null
    }

    return (
        <p className="mb-4 text-sm text-muted-foreground">
            Seu papel neste contexto só permite consulta.
        </p>
    )
}
