import { useEffect, useState } from "react"
import { Building2 } from "lucide-react"
import { useNavigate } from "react-router"

import { Button } from "@/components/ui/button"
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty"
import { LoadingWrapper } from "@/components/common/LoadingWrapper"
import { formatCpfCnpj } from "@/helpers/formatters"
import { getAuthCompanies, logout, me, selectCompany } from "@/lib/auth/auth"
import { notifyError } from "@/lib/notify"

export default function SelectCompaniesPage() {
    const navigate = useNavigate()
    const [user, setUser] = useState(null)
    const [companies, setCompanies] = useState([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        Promise.all([me(), getAuthCompanies()])
            .then(([currentUser, companyList]) => {
                setUser(currentUser)
                setCompanies(companyList)
            })
            .catch(notifyError)
            .finally(() => setLoading(false))
    }, [])

    async function handleCompanyClick(uuid) {
        setLoading(true)

        try {
            await selectCompany(uuid)
            navigate("/app")
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    async function handleLogout() {
        setLoading(true)

        try {
            await logout()
            navigate("/auth/login", { replace: true })
        } catch (error) {
            notifyError(error)
            setLoading(false)
        }
    }

    return (
        <LoadingWrapper loading={loading}>
            <div className="relative flex min-h-svh flex-col overflow-hidden">
                <WaveBackground />

                <div className="relative z-10 flex flex-1 items-center justify-center px-6 py-16">
                    <div className="flex w-full max-w-[76rem] flex-col items-center gap-8">
                        <div className="flex flex-col gap-1 text-center">
                            <p className="text-sm text-muted-foreground">Bem-vindo, {user?.name}</p>
                            <h1 className="text-2xl font-bold">Selecionar a empresa</h1>
                            <p className="text-sm text-muted-foreground">
                                Escolha em qual empresa você quer entrar.
                            </p>
                        </div>

                        {!loading && companies.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Building2 />
                                    </EmptyMedia>
                                    <EmptyTitle>Nenhuma empresa</EmptyTitle>
                                    <EmptyDescription>
                                        Este usuário não está vinculado a nenhuma empresa.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="flex w-full flex-wrap justify-center gap-4">
                                {companies.map((company) => (
                                    <button
                                        key={company.uuid}
                                        type="button"
                                        className="flex w-full max-w-sm flex-col gap-4 rounded-xl border bg-card/95 p-5 text-left text-card-foreground shadow-sm backdrop-blur-sm transition-colors hover:bg-muted sm:w-72 cursor-pointer"
                                        onClick={() => handleCompanyClick(company.uuid)}
                                    >
                                        <span className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                            <Building2 className="size-5" />
                                        </span>
                                        <span className="flex flex-col gap-1">
                                            <span className="font-medium">{company.trade_name}</span>
                                            <span className="text-sm text-muted-foreground">{company.legal_name}</span>
                                            <span className="text-sm text-muted-foreground">
                                                {formatCpfCnpj(company.cnpj)}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}

                        <Button variant="outline" onClick={handleLogout} className="cursor-pointer">
                            Sair
                        </Button>
                    </div>
                </div>

                <footer className="relative z-10 flex flex-col items-center gap-1 px-6 pb-6 text-center text-xs text-muted-foreground">
                    <p>VF3M TECNOLOGIA LTDA · CNPJ 63.005.739/0001-06 © Todos os direitos reservados.</p>
                </footer>
            </div>
        </LoadingWrapper>
    )
}

function WaveBackground() {
    return (
        <div className="pointer-events-none absolute inset-x-0 top-[68%] bottom-0 overflow-hidden" aria-hidden="true">
            <style>
                {`
                    @keyframes auth-wave {
                        from { transform: translateX(0); }
                        to { transform: translateX(-50%); }
                    }
                    @media (prefers-reduced-motion: reduce) {
                        .auth-wave { animation: none !important; }
                    }
                `}
            </style>
            <div className="auth-wave absolute inset-0 flex w-[200%] text-primary/15" style={{ animation: "auth-wave 18s linear infinite" }}>
                <Wave />
                <Wave />
            </div>
            <div className="auth-wave absolute inset-0 flex w-[200%] text-primary/10" style={{ animation: "auth-wave 28s linear infinite reverse" }}>
                <Wave offset />
                <Wave offset />
            </div>
        </div>
    )
}

function Wave({ offset = false }) {
    const path = offset
        ? "M0,70 C240,10 480,130 720,70 C960,10 1200,130 1440,70 L1440,320 L0,320 Z"
        : "M0,40 C240,110 480,0 720,40 C960,110 1200,0 1440,40 L1440,320 L0,320 Z"

    return (
        <svg className="h-full w-1/2 shrink-0" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path d={path} fill="currentColor" />
        </svg>
    )
}
