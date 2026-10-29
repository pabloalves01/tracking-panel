import { Input } from '@/components/ui/input'

/**
 * Placa no padrão antigo (ABC-1234) ou Mercosul (ABC1D23).
 *
 * `value` e `onChange` trabalham com as 7 letras e números, em
 * maiúsculas, que é o que vai para o banco. O traço do padrão antigo
 * aparece só na tela.
 */
export function InputPlate({ value = '', onChange, placeholder = 'ABC1D23', ...props }) {
    const plate = normalize(value)
    const shown = /^[A-Z]{3}\d{4}$/.test(plate) ? `${plate.slice(0, 3)}-${plate.slice(3)}` : plate

    return (
        <Input
            {...props}
            value={shown}
            placeholder={placeholder}
            onChange={(event) => onChange?.(normalize(event.target.value))}
            className="font-mono tracking-wider uppercase"
            maxLength={8}
            autoComplete="off"
            spellCheck={false}
        />
    )
}

function normalize(value) {
    return String(value ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7)
}
