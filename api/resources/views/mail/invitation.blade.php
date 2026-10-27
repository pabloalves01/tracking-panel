<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Convite Logiv</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial, Helvetica, sans-serif;color:#1c2430;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#0f2744;padding:28px 32px;">
                            <p style="margin:0;font-size:22px;letter-spacing:0.08em;color:#f4f7fb;">LOGIV</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.5;">{{ $organization }} convidou você para a Logiv.</p>
                            <p style="margin:0 0 20px;font-size:14px;line-height:1.5;color:#3d4a5c;">O link vale até {{ $expiresAt }}. Se não foi você, ignore este e-mail.</p>
                            <p style="margin:0;">
                                <a href="{{ $url }}" style="display:inline-block;background:#e07a2f;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700;">Abrir convite</a>
                            </p>
                            <p style="margin:20px 0 0;font-size:12px;line-height:1.5;color:#7b8794;word-break:break-all;">{{ $url }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px;">
                            <p style="margin:0;font-size:12px;color:#7b8794;">Logiv · mensagem automática, não responda.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
