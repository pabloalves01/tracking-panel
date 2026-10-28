@if ($invitedBy){{ $invitedBy }} convidou você para acessar {{ $organization }} no LogIV@else Você foi convidado para acessar {{ $organization }} no LogIV@endif, o sistema de gestão logística e rastreamento.

SEU ACESSO
{{ $roleLabel }} - {{ $roleSummary }}

@if ($accountExists)Você já tem conta no LogIV. Entre com ela e confirme o convite:@else Para aceitar, crie sua conta - nome, senha e um código que enviamos na hora:@endif

{{ $url }}

Este convite vale até {{ $expiresAt }}. Depois disso será preciso pedir um novo.

Não esperava este convite? Pode ignorar este e-mail. Sem clicar no link, nada acontece e nenhuma conta é criada.

--
LogIV - mensagem automática, não responda.
