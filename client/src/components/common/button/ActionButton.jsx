import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

/**
 * Botão de ação só com ícone, no tamanho `sm`, com tooltip.
 * Pensado para linhas de tabela: editar, excluir, visualizar.
 *
 * `icon`: componente do ícone, por exemplo `PencilIcon`.
 * `tooltip`: texto do tooltip. Também vira o nome acessível do botão.
 * `disabled`: desabilita o clique. Desabilitado, o tooltip não aparece.
 * `variant`: mesmo do `Button`. Padrão `outline`.
 */
export function ActionButton({ icon: Icon, tooltip, disabled = false, variant = 'outline', onClick, permission: _permission = 'write', ...props }) {
    return (
        <Tooltip>
            <TooltipTrigger
                render={
                    <Button
                        type="button"
                        variant={variant}
                        size="icon-sm"
                        className="cursor-pointer"
                        disabled={disabled}
                        aria-label={tooltip}
                        onClick={onClick}
                        {...props}
                    />
                }
            >
                {Icon ? <Icon /> : null}
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    )
}
