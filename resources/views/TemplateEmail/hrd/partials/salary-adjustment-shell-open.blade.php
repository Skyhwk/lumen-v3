<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title ?? 'Penyesuaian Gaji Internal' }}</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .email-padding { padding: 20px 16px !important; }
            .stack-column { display: block !important; width: 100% !important; max-width: 100% !important; }
            .btn-stack { display: block !important; width: 100% !important; margin: 0 0 8px 0 !important; text-align: center !important; box-sizing: border-box !important; }
            .hero-title { font-size: 20px !important; }
            .info-label { width: 42% !important; }
            .photo-cell { display: block !important; width: 100% !important; text-align: center !important; padding-bottom: 16px !important; }
            .employee-photo { width: 140px !important; height: 140px !important; margin: 0 auto !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:'Segoe UI',Arial,Helvetica,sans-serif;color:#18181b;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" class="email-container" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;width:100%;background-color:#ffffff;border:1px solid #d4d4d8;">
                <tr>
                    <td style="background-color:#18181b;padding:20px 28px;border-bottom:3px solid #2563eb;" class="email-padding">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td>
                                    <p style="margin:0;font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#a1a1aa;font-weight:600;">
                                        PT Inti Surya Laboratorium · HRIS Internal
                                    </p>
                                    <h1 class="hero-title" style="margin:8px 0 0 0;font-size:22px;line-height:1.35;color:#ffffff;font-weight:700;">
                                        {{ $heading ?? 'Penyesuaian Gaji Karyawan' }}
                                    </h1>
                                    @if(!empty($subheading))
                                        <p style="margin:8px 0 0 0;font-size:13px;line-height:1.6;color:#d4d4d8;">
                                            {{ $subheading }}
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="email-padding" style="padding:28px;">
