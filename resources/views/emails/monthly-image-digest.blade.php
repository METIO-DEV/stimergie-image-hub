@php
    $userName = data_get($params, 'user_name', data_get($recipient, 'name', ''));
    $periodStart = data_get($params, 'period_start');
    $periodEnd = data_get($params, 'period_end');
    $imageCount = data_get($params, 'image_count', 0);
    $galleryUrl = data_get($params, 'gallery_url');
    $projects = collect(data_get($params, 'projects', []));
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Récapitulatif mensuel</title>
</head>
<body style="margin:0;background:#F2F0F0;color:#111111;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F2F0F0;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:720px;background:#ffffff;border:1px solid #D8D4D2;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 32px 22px;border-bottom:4px solid #274854;">
                            <img src="{{ asset('logo_stimergie_header.png') }}" alt="Stimergie" width="188" style="display:block;width:188px;max-width:70%;height:auto;margin:0 0 22px;">
                            <p style="margin:0 0 8px;color:#6B6765;font-size:13px;font-weight:bold;letter-spacing:0;text-transform:uppercase;">Récapitulatif mensuel</p>
                            <h1 style="margin:0;color:#111111;font-size:26px;line-height:1.2;">
                                {{ $imageCount }} nouvelle(s) image(s) disponibles
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;">Bonjour {{ $userName ?: 'à tous' }},</p>
                            <p style="margin:0 0 24px;">
                                Voici les nouvelles images accessibles sur votre espace
                                @if ($periodStart || $periodEnd)
                                    pour la période du {{ $periodStart ?: 'début' }} au {{ $periodEnd ?: 'jour' }}.
                                @else
                                    ce mois-ci.
                                @endif
                            </p>

                            @if ($projects->isNotEmpty())
                                <h2 style="margin:0 0 16px;color:#111111;font-size:18px;">Aperçu par projet</h2>

                                @foreach ($projects as $project)
                                    @php
                                        $previewImages = collect(data_get($project, 'preview_images', []))->take(3);
                                    @endphp

                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid #D8D4D2;border-radius:8px;border-collapse:separate;overflow:hidden;">
                                        <tr>
                                            <td style="padding:18px 20px;background:#F2F0F0;">
                                                <strong style="display:block;color:#111111;font-size:17px;">{{ data_get($project, 'project_name') ?: 'Projet sans nom' }}</strong>
                                                <span style="display:block;margin-top:4px;color:#6B6765;font-size:14px;">
                                                    {{ data_get($project, 'client_name') ?: 'Client non renseigné' }}
                                                    · {{ data_get($project, 'image_count', 0) }} image(s)
                                                </span>
                                            </td>
                                        </tr>

                                        @if ($previewImages->isNotEmpty())
                                            <tr>
                                                <td style="padding:18px 20px 20px;">
                                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            @foreach ($previewImages as $image)
                                                                <td width="33.33%" valign="top" style="padding-right:{{ $loop->last ? '0' : '10px' }};">
                                                                    @if (data_get($image, 'url'))
                                                                        <a href="{{ data_get($image, 'url') }}" style="color:#274854;text-decoration:none;">
                                                                    @endif
                                                                    @if (data_get($image, 'thumbnail_url'))
                                                                        <img src="{{ data_get($image, 'thumbnail_url') }}" alt="{{ data_get($image, 'title') ?: 'Image du projet' }}" width="190" style="display:block;width:100%;max-width:190px;height:118px;object-fit:cover;border-radius:6px;border:1px solid #D8D4D2;background:#F2F0F0;">
                                                                    @else
                                                                        <span style="display:block;height:118px;border-radius:6px;border:1px solid #D8D4D2;background:#F2F0F0;color:#6B6765;text-align:center;line-height:118px;font-size:13px;">
                                                                            Image
                                                                        </span>
                                                                    @endif
                                                                    <span style="display:block;margin-top:8px;color:#111111;font-size:13px;line-height:1.35;">
                                                                        {{ data_get($image, 'title') ?: 'Image sans titre' }}
                                                                    </span>
                                                                    @if (data_get($image, 'url'))
                                                                        </a>
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        @endif
                                    </table>
                                @endforeach
                            @endif

                            @if ($galleryUrl)
                                <p style="margin:28px 0 0;">
                                    <a href="{{ $galleryUrl }}" style="display:inline-block;background:#274854;color:#ffffff;text-decoration:none;border-radius:6px;padding:12px 18px;font-weight:bold;">
                                        Ouvrir la galerie
                                    </a>
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
