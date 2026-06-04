@php
    $userName = data_get($params, 'user_name', data_get($recipient, 'name', ''));
    $userEmail = data_get($params, 'user_email', data_get($recipient, 'email'));
    $loginUrl = data_get($params, 'login_url');
    $resetPasswordUrl = data_get($params, 'reset_password_url');
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre accès Stimergie Image Hub</title>
</head>
<body style="margin:0;background:#F2F0F0;color:#111111;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F2F0F0;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #D8D4D2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 32px 22px;border-bottom:4px solid #274854;">
                            <img src="{{ asset('logo_stimergie_header.png') }}" alt="Stimergie" width="188" style="display:block;width:188px;max-width:70%;height:auto;margin:0 0 22px;">
                            <p style="margin:0 0 8px;color:#6B6765;font-size:13px;font-weight:bold;letter-spacing:0;text-transform:uppercase;">Image Hub</p>
                            <h1 style="margin:0;color:#111111;font-size:26px;line-height:1.2;">Votre accès est prêt</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;">Bonjour {{ $userName ?: $userEmail }},</p>
                            <p style="margin:0 0 20px;">
                                Un compte a été créé pour vous sur Stimergie Image Hub avec l'adresse
                                <strong>{{ $userEmail }}</strong>.
                            </p>
                            <p style="margin:0 0 28px;">
                                Définissez votre mot de passe pour accéder à la galerie et aux images associées à vos projets.
                            </p>

                            @if ($resetPasswordUrl)
                                <p style="margin:0 0 28px;">
                                    <a href="{{ $resetPasswordUrl }}" style="display:inline-block;background:#274854;color:#ffffff;text-decoration:none;border-radius:6px;padding:12px 18px;font-weight:bold;">
                                        Définir mon mot de passe
                                    </a>
                                </p>
                            @endif

                            @if ($loginUrl)
                                <p style="margin:0;color:#6B6765;font-size:14px;">
                                    Connexion directe : <a href="{{ $loginUrl }}" style="color:#274854;">{{ $loginUrl }}</a>
                                </p>
                            @endif
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
