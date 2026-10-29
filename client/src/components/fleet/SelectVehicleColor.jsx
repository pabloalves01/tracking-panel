import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { VEHICLE_COLORS } from '@/lib/fleet/options'

const items = VEHICLE_COLORS.map((color) => ({ value: color.value, label: color.value }))

/**
 * Cor do CRLV, com a amostra ao lado do nome — na lista e no campo.
 */
export function SelectVehicleColor({ id, value = '', onChange }) {
    return (
        <Select items={items} value={value || null} onValueChange={(next) => onChange?.(next ?? '')}>
            <SelectTrigger id={id} className="w-full">
                <SelectValue placeholder="Selecione">
                    {(selected) =>
                        selected ? <ColorOption value={selected} /> : <span className="text-muted-foreground">Selecione</span>
                    }
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {VEHICLE_COLORS.map((color) => (
                        <SelectItem key={color.value} value={color.value}>
                            <ColorOption value={color.value} />
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    )
}

export function ColorDot({ value, className = '' }) {
    const swatch = VEHICLE_COLORS.find((color) => color.value === value)?.swatch

    if (!swatch) return null

    return (
        <span
            aria-hidden="true"
            className={`ring-foreground/15 inline-block size-3 shrink-0 rounded-full ring-1 ${className}`}
            style={{ background: swatch }}
        />
    )
}

function ColorOption({ value }) {
    return (
        <span className="flex items-center gap-2">
            <ColorDot value={value} />
            {value}
        </span>
    )
}
