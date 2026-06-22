@php
    $userName = data_get($params, 'user_name');
    $userEmail = data_get($params, 'user_email');
    $subject = data_get($params, 'subject');
    $message = data_get($params, 'message');
    $submittedAt = data_get($params, 'submitted_at');
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Message de contact Stimergie Image Hub</title>
</head>
<body style="margin:0;background:#F2F0F0;color:#111111;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F2F0F0;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #D8D4D2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 32px 22px;border-bottom:4px solid #274854;">
                            <img src="{{ asset('logo_stimergie_header.png') }}" alt="Stimergie" width="188" style="display:block;width:188px;max-width:70%;height:auto;margin:0 0 22px;">
                            <p style="margin:0 0 8px;color:#6B6765;font-size:13px;font-weight:bold;text-transform:uppercase;">Image Hub</p>
                            <h1 style="margin:0;color:#111111;font-size:24px;line-height:1.25;">Nouveau message de contact</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 22px;">
                                <tr>
                                    <td style="padding:7px 0;color:#6B6765;width:140px;">Utilisateur</td>
                                    <td style="padding:7px 0;font-weight:bold;">{{ $userName ?: 'Non renseigne' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:7px 0;color:#6B6765;">Email</td>
                                    <td style="padding:7px 0;">{{ $userEmail ?: 'Non renseigne' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:7px 0;color:#6B6765;">Objet</td>
                                    <td style="padding:7px 0;">{{ $subject }}</td>
                                </tr>
                                @if ($submittedAt)
                                    <tr>
                                        <td style="padding:7px 0;color:#6B6765;">Envoye le</td>
                                        <td style="padding:7px 0;">{{ $submittedAt }}</td>
                                    </tr>
                                @endif
                            </table>

                            <div style="padding:18px 20px;background:#F8F7F6;border:1px solid #E7E2DF;border-radius:8px;white-space:pre-line;">{{ $message }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;background:#111111;color:#F2F0F0;font-size:12px;">
                            © {{ now()->year }} Stimergie
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
