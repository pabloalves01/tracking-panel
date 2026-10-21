import { useState } from 'react'
import { EyeIcon, EyeOffIcon } from 'lucide-react'

import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

/**
 * Senha com botão para mostrar ou ocultar.
 *
 * O valor vai em texto puro para a API. O backend grava o hash e não devolve a senha.
 */
export function InputPassword({ id, value, onChange, placeholder, disabled, className, autoComplete = 'new-password' }) {
    const [visible, setVisible] = useState(false)

    return (
        <div className="relative">
            <Input
                id={id}
                type={visible ? 'text' : 'password'}
                value={value}
                disabled={disabled}
                autoComplete={autoComplete}
                placeholder={placeholder}
                className={cn('pr-10', className)}
                onChange={(event) => onChange?.(event.target.value)}
            />
            <button
                type="button"
                disabled={disabled}
                className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground disabled:pointer-events-none"
                aria-label={visible ? 'Ocultar senha' : 'Mostrar senha'}
                onClick={() => setVisible((current) => !current)}
            >
                {visible ? <EyeOffIcon className="size-4" /> : <EyeIcon className="size-4" />}
            </button>
        </div>
    )
}
