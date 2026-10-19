import { MoonIcon, SunIcon } from 'lucide-react'
import { useTheme } from 'next-themes'

import { Button } from '@/components/ui/button'

/**
 * Alterna entre claro e escuro.
 * O tema inicial segue o sistema; a escolha fica salva no navegador.
 */
export function ThemeToggle() {
    const { resolvedTheme, setTheme } = useTheme()
    const isDark = resolvedTheme === 'dark'

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={isDark ? 'Usar tema claro' : 'Usar tema escuro'}
            onClick={() => setTheme(isDark ? 'light' : 'dark')}
        >
            {isDark ? <SunIcon /> : <MoonIcon />}
        </Button>
    )
}
