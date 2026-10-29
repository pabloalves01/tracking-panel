{{-- As diretivas ficam em linhas próprias de propósito.

     O Blade ignora uma arroba precedida de caractere de palavra, para não
     quebrar endereços de e-mail. Colada depois de uma palavra — como no fim
     de "no LogIV" — a diretiva vira texto literal e a condição fica sem
     fechamento, derrubando a renderização do e-mail inteiro. --}}
@if ($invitedBy)
{{ $invitedBy }} convidou você para acessar {{ $organization }} no LogIV, o sistema de gestão logística e rastreamento.
@else
Você foi convidado para acessar {{ $organization }} no LogIV, o sistema de gestão logística e rastreamento.
@endif

{{ mb_strtoupper($organizationKind) }}
{{ $organization }}

@if ($accountExists)
Você já tem conta no LogIV. Entre com ela e confirme o convite:
@else
Para aceitar, crie sua conta - nome, senha e um código que enviamos na hora:
@endif

{{ $url }}

Este convite vale até {{ $expiresAt }}. Depois disso será preciso pedir um novo.

Não esperava este convite? Pode ignorar este e-mail. Sem clicar no link, nada acontece e nenhuma conta é criada.

--
LogIV - mensagem automática, não responda.
