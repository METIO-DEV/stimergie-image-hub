@php
    $albumName = data_get($params, 'album_name', 'Album partagé');
    $albumDescription = data_get($params, 'album_description');
    $message = data_get($params, 'message');
    $shareUrl = data_get($params, 'share_url');
    $startsAt = data_get($params, 'starts_at');
    $expiresAt = data_get($params, 'expires_at');
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Album partagé</title>
</head>
<body style="margin:0;background:#F2F0F0;color:#111111;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F2F0F0;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #D8D4D2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 32px 22px;border-bottom:4px solid #274854;">
                            <img src="{{ asset('logo_stimergie_header.png') }}" alt="Stimergie" width="188" style="display:block;width:188px;max-width:70%;height:auto;margin:0 0 22px;">
                            <p style="margin:0 0 8px;color:#6B6765;font-size:13px;font-weight:bold;letter-spacing:0;text-transform:uppercase;">Album partagé</p>
                            <h1 style="margin:0;color:#111111;font-size:26px;line-height:1.2;">{{ $albumName }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 18px;">
                                Un album photo vient de vous être partagé.
                            </p>

                            @if ($albumDescription)
                                <p style="margin:0 0 18px;color:#2A2A2A;">{{ $albumDescription }}</p>
                            @endif

                            @if ($message)
                                <div style="margin:0 0 24px;padding:16px 18px;background:#F2F0F0;border-left:4px solid #274854;border-radius:6px;">
                                    {{ $message }}
                                </div>
                            @endif

                            @if ($shareUrl)
                                <p style="margin:0 0 28px;">
                                    <a href="{{ $shareUrl }}" style="display:inline-block;background:#274854;color:#ffffff;text-decoration:none;border-radius:6px;padding:12px 18px;font-weight:bold;">
                                        Ouvrir l'album
                                    </a>
                                </p>
                            @endif

                            @if ($startsAt || $expiresAt)
                                <p style="margin:0;color:#6B6765;font-size:14px;">
                                    @if ($startsAt)
                                        Accessible à partir du {{ $startsAt }}.
                                    @endif
                                    @if ($expiresAt)
                                        Expire le {{ $expiresAt }}.
                                    @endif
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
