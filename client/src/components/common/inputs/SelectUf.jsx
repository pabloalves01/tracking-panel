import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { UFS } from '@/lib/location/states'

const items = UFS.map((value) => ({ value, label: value }))

/**
 * UF como texto de duas letras — a da placa, a da CNH. Para estado com
 * cidade, use `SelectState`, que trabalha com a tabela de estados.
 */
export function SelectUf({ id, value = '', onChange, placeholder = 'UF', ...props }) {
    return (
        <Select items={items} value={value || null} onValueChange={(next) => onChange?.(next ?? '')}>
            <SelectTrigger id={id} className="w-full" {...props}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {UFS.map((uf) => (
                        <SelectItem key={uf} value={uf}>
                            {uf}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    )
}
