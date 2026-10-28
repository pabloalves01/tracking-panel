/**
 * Aba sublinhada, do padrão da tela de usuários.
 *
 * Fica na mão em vez de um componente de abas porque o estado vai na URL
 * (`?aba=`), e quem usa decide o que fazer com ele. `alert` põe uma bolinha
 * vermelha: num formulário, é o que diz que há erro numa aba que não está
 * aberta.
 */
export function TabButton({ active, onClick, alert = false, children }) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onClick}
            className={`flex items-center gap-2 border-b-2 px-3 pb-2 text-sm transition-colors ${
                active
                    ? 'border-foreground text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground border-transparent'
            }`}
        >
            {children}
            {alert ? (
                <span className="bg-destructive size-1.5 rounded-full" aria-label="Há campos com erro nesta aba" />
            ) : null}
        </button>
    )
}
