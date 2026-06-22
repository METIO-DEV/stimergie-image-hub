<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Demande d extension de cession</title>
</head>
<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;border-bottom:1px solid #e2e8f0;">
                            <h1 style="margin:0;font-size:22px;line-height:1.3;">Demande d extension de cession de droits</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="margin:0 0 16px;">Une demande d extension de cession de droits a ete creee.</p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 0 20px;">
                                <tr>
                                    <td style="padding:8px 0;color:#64748b;width:180px;">Reference demande</td>
                                    <td style="padding:8px 0;font-weight:bold;">#{{ data_get($params, 'request_id') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#64748b;">Image</td>
                                    <td style="padding:8px 0;">#{{ data_get($params, 'image_id') }} - {{ data_get($params, 'image_title', 'Image') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#64748b;">Projet</td>
                                    <td style="padding:8px 0;">{{ data_get($params, 'project_name', '-') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#64748b;">Client</td>
                                    <td style="padding:8px 0;">{{ data_get($params, 'client_name', '-') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#64748b;">Fin de cession actuelle</td>
                                    <td style="padding:8px 0;">{{ data_get($params, 'rights_ends_at', 'Non renseignee') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#64748b;">Statut</td>
                                    <td style="padding:8px 0;">{{ data_get($params, 'status_label', 'Demandee') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#64748b;">Demandeur</td>
                                    <td style="padding:8px 0;">
                                        {{ data_get($params, 'requested_by_name', '-') }}
                                        @if (data_get($params, 'requested_by_email'))
                                            ({{ data_get($params, 'requested_by_email') }})
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px;">
                                <a href="{{ data_get($params, 'admin_url') }}" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:6px;padding:10px 14px;font-weight:bold;">
                                    Ouvrir Stimergie Image Hub
                                </a>
                            </p>

                            <p style="margin:0;color:#64748b;font-size:13px;">Ce message a ete genere automatiquement.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
