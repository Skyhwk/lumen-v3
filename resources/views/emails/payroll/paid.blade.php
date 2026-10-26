<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll {{ $periodeLabel }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#344256;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f9;">
    <tr>
        <td align="center" style="padding:24px 16px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border:1px solid #dfe5ec;border-radius:6px;overflow:hidden;">
                <tr>
                    <td style="padding:18px 20px;background:#1f2b3d;color:#fff;">
                        <div style="font-size:15px;font-weight:700;letter-spacing:.1px;">PT INTI SURYA LABORATORIUM</div>
                        <div style="margin-top:3px;font-size:11px;color:#b9c7d8;">Payroll · Finance</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 22px 26px;">
                        <p style="margin:0 0 16px;font-size:13px;line-height:20px;">Yth. <strong>{{ $nama }}</strong>,</p>

                        <p style="margin:0 0 16px;font-size:13px;line-height:21px;">
                            <strong>Good news!</strong> Gaji Anda untuk periode <strong>{{ $periodeLabel }}</strong> telah diproses dan ditransfer.
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;background:#f8fafc;border:1px solid #e2e8ef;border-radius:4px;">
                            <tr>
                                <td style="padding:14px 16px;font-size:13px;line-height:20px;">
                                    <span style="color:#667588;">Tanggal transfer</span><br>
                                    <strong style="color:#1f2b3d;">{{ $tglTransferLabel ?? '-' }}</strong>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px;font-size:13px;line-height:21px;">
                            Slip gaji terlampir pada email ini (PDF terproteksi). Anda juga dapat melihat slip gaji melalui aplikasi <strong>Attendance</strong>.
                        </p>

                        <p style="margin:0;font-size:13px;line-height:21px;">Terima kasih atas kontribusi dan dedikasi Anda.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:17px 22px;background:#f8fafc;border-top:1px solid #e2e8ef;">
                        <p style="margin:0 0 4px;font-size:12px;line-height:18px;">Salam,</p>
                        <p style="margin:0;font-size:12px;font-weight:700;line-height:18px;">
                            Tim Payroll<br>
                            <span style="font-weight:400;">PT Inti Surya Laboratorium</span>
                        </p>
                        <p style="margin:12px 0 0;font-size:11px;line-height:16px;color:#8896a8;">
                            Email ini dikirim otomatis. Mohon tidak membalas email ini.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
