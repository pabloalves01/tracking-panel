import { LayoutToolbar } from '@/components/common/LayoutToolbar'
import { useSession } from '@/lib/auth/session'

export default function ProfilePage() {
    const { user } = useSession()

    return (
        <>
            <LayoutToolbar
                breadcrumbs={[
                    { text: 'Início', to: '/app' },
                    { text: 'Meu perfil' },
                ]}
            />

            <dl className="grid max-w-md gap-4">
                <div className="flex flex-col gap-1">
                    <dt className="text-sm text-muted-foreground">Nome</dt>
                    <dd className="font-medium">{user.name}</dd>
                </div>
                <div className="flex flex-col gap-1">
                    <dt className="text-sm text-muted-foreground">E-mail</dt>
                    <dd className="font-medium">{user.email}</dd>
                </div>
            </dl>
        </>
    )
}
