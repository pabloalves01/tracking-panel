<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Convite para o LogIV</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        /* Cliente de e-mail ignora boa parte do CSS, então tudo que
           precisa funcionar está inline. Aqui ficam só os ajustes de
           tela pequena e o modo escuro, que inline não alcança. */
        @media only screen and (max-width: 600px) {
            .sm-full { width: 100% !important; }
            .sm-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .sm-stack { display: block !important; width: 100% !important; }
            .sm-center { text-align: center !important; }
            .sm-mt { margin-top: 16px !important; }
        }
        @media (prefers-color-scheme: dark) {
            .dm-bg { background-color: #14161a !important; }
            .dm-card { background-color: #1d2026 !important; }
            .dm-text { color: #e8eaed !important; }
            .dm-muted { color: #9aa1ab !important; }
            .dm-border { border-color: #2d3139 !important; }
            .dm-panel { background-color: #232730 !important; }
        }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
    </style>
</head>
<body class="dm-bg" style="margin:0;padding:0;width:100%;background-color:#eef1f5;-webkit-font-smoothing:antialiased;">

    {{-- Preheader: o trecho que o cliente mostra ao lado do assunto. --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Você foi convidado para {{ $organization }}. O convite vale até {{ $expiresAt }}.
        &#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;&#8203;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="dm-bg" style="background-color:#eef1f5;">
        <tr>
            <td align="center" style="padding:40px 12px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="sm-full" style="max-width:560px;">

                    {{-- Marca --}}
                    <tr>
                        <td align="left" style="padding:0 0 20px 4px;">
                            <span style="font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;letter-spacing:0.14em;color:#00205b;" class="dm-text">LOG<span style="color:#fe5000;">IV</span></span>
                        </td>
                    </tr>

                    <tr>
                        {{-- Sem faixa colorida no topo: `overflow:hidden` não
                             recorta de forma confiável em cliente de e-mail, e
                             a faixa escapava do canto arredondado. A marca já
                             está acima do card e o laranja, no botão. --}}
                        <td class="dm-card dm-border" style="background-color:#ffffff;border:1px solid #e4e9f0;border-radius:14px;">

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="sm-pad" style="padding:36px 40px 8px;">
                                        <h1 class="dm-text" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.3;font-weight:700;color:#101828;">
                                            @if ($invitedBy)
                                                {{ $invitedBy }} convidou você
                                            @else
                                                Você foi convidado
                                            @endif
                                        </h1>
                                        <p class="dm-muted" style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.55;color:#475467;">
                                            para acessar <strong class="dm-text" style="color:#101828;">{{ $organization }}</strong> no LogIV,
                                            o sistema de gestão logística e rastreamento.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Que tipo de organização é. Quem recebe o
                                     convite nem sempre sabe se está entrando
                                     numa transportadora ou num posto. --}}
                                <tr>
                                    <td class="sm-pad" style="padding:24px 40px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="dm-panel dm-border" style="background-color:#f7f9fc;border:1px solid #e4e9f0;border-radius:10px;">
                                            <tr>
                                                <td style="padding:16px 18px;">
                                                    <p class="dm-muted" style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.08em;color:#667085;text-transform:uppercase;">
                                                        {{ $organizationKind }}
                                                    </p>
                                                    <p class="dm-text" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#101828;">
                                                        {{ $organization }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Botão. Com VML para o Outlook, que ignora padding em <a>. --}}
                                <tr>
                                    <td class="sm-pad" style="padding:28px 40px 0;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:100%;" arcsize="20%" stroke="f" fillcolor="#fe5000">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">
                                                {{ $accountExists ? 'Entrar e aceitar' : 'Criar minha conta' }}
                                            </center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-- -->
                                        <a href="{{ $url }}" style="display:block;background-color:#fe5000;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;line-height:48px;height:48px;text-align:center;text-decoration:none;border-radius:10px;">
                                            {{ $accountExists ? 'Entrar e aceitar' : 'Criar minha conta' }}
                                        </a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>

                                <tr>
                                    <td class="sm-pad" style="padding:14px 40px 0;">
                                        <p class="dm-muted" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:#667085;">
                                            @if ($accountExists)
                                                Você já tem conta no LogIV — é só entrar com ela e confirmar.
                                            @else
                                                Leva menos de um minuto: nome, senha e um código que enviamos na hora.
                                            @endif
                                        </p>
                                    </td>
                                </tr>

                                {{-- Validade --}}
                                <tr>
                                    <td class="sm-pad" style="padding:24px 40px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td class="dm-border" style="border-top:1px solid #e4e9f0;padding-top:18px;">
                                                    <p class="dm-muted" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#667085;">
                                                        Este convite vale até <strong class="dm-text" style="color:#101828;white-space:nowrap;">{{ $expiresAt }}</strong>.
                                                        Depois disso será preciso pedir um novo.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Link em texto, para quem o botão não funciona. --}}
                                <tr>
                                    <td class="sm-pad" style="padding:18px 40px 36px;">
                                        <p class="dm-muted" style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#98a2b3;">
                                            Se o botão não abrir, copie este endereço:
                                        </p>
                                        <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;word-break:break-all;">
                                            <a href="{{ $url }}" style="color:#1e4db7;text-decoration:underline;">{{ $url }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Rodapé --}}
                    <tr>
                        <td class="sm-pad" style="padding:22px 16px 0;">
                            <p class="dm-muted" style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#98a2b3;">
                                Não esperava este convite? Pode ignorar este e-mail — sem clicar no link, nada acontece e nenhuma conta é criada.
                            </p>
                            <p class="dm-muted" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#98a2b3;">
                                LogIV · mensagem automática, não responda.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
