import { Link } from 'react-router'

import { Button } from '@/components/ui/button'
import { ThemeToggle } from '@/components/layout/ThemeToggle'

/**
 * Rota que não existe.
 *
 * Era um `<h1>404</h1>` sem estilo nem saída, em inglês, no meio de um
 * sistema em português: quem errava a URL ficava sem pista do que fazer.
 * O destino depende de onde a pessoa estava — daí os dois caminhos.
 */
export default function NotFound() {
    return (
        <div className="flex min-h-svh flex-col gap-4 p-6 md:p-10">
            <div className="flex items-center justify-between">
                <Link to="/" className="flex items-center gap-2 font-medium">
                    <img src="/brand/logo-dark.svg" alt="Logiv" className="h-8 w-auto dark:hidden" />
                    <img src="/brand/logo-mono.svg" alt="" className="hidden h-8 w-auto dark:block" />
                </Link>
                <ThemeToggle />
            </div>

            <div className="flex flex-1 items-center justify-center">
                <div className="flex w-full max-w-md flex-col items-center gap-6 text-center">
                    <p className="text-6xl font-semibold tracking-tight text-muted-foreground/40">404</p>

                    <div className="flex flex-col gap-2">
                        <h1 className="text-xl font-semibold">Página não encontrada</h1>
                        <p className="text-sm text-muted-foreground">
                            O endereço não existe ou a página foi movida. Se você chegou por um link
                            do sistema, avise quem administra a organização.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button nativeButton={false} render={<Link to="/app" />}>Ir para o início</Button>
                        <Button variant="outline" nativeButton={false} render={<Link to="/auth/login" />}>
                            Ir para o login
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    )
}
