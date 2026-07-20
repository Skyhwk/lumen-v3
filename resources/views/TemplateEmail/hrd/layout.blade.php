<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title ?? 'HRD Recruitment' }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .email-padding { padding: 20px 16px !important; }
            .stack-column { display: block !important; width: 100% !important; max-width: 100% !important; }
            .btn-stack { display: block !important; width: 100% !important; margin: 0 0 10px 0 !important; text-align: center !important; }
            .hero-title { font-size: 22px !important; }
            .info-label { width: 42% !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eef2ff;font-family:'Segoe UI',Arial,Helvetica,sans-serif;color:#1e293b;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#eef2ff;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" class="email-container" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(30,64,175,0.12);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1e40af 0%,#3b82f6 55%,#60a5fa 100%);padding:28px 32px;" class="email-padding">
                            <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.82);font-weight:600;">
                                PT Inti Surya Laboratorium
                            </p>
                            <h1 class="hero-title" style="margin:10px 0 0 0;font-size:26px;line-height:1.3;color:#ffffff;font-weight:700;">
                                {{ $heading ?? 'HRD Recruitment' }}
                            </h1>
                            @if(!empty($subheading))
                                <p style="margin:8px 0 0 0;font-size:14px;line-height:1.6;color:rgba(255,255,255,0.92);">
                                    {{ $subheading }}
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="email-padding" style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 28px 32px;background-color:#f8fafc;border-top:1px solid #e2e8f0;" class="email-padding">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;text-align:center;">
                                Email ini dikirim otomatis oleh sistem HRD Recruitment.<br>
                                &copy; {{ date('Y') }} PT Inti Surya Laboratorium
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
