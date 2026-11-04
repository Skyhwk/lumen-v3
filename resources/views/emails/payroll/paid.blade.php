<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemberitahuan Pembayaran Gaji Periode {{ $periodeLabel }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;">
    <tr>
        <td align="center" style="padding:28px 16px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #d1d5db;">
                <tr>
                    <td style="padding:20px 28px;border-bottom:3px solid #1e3a5f;">
                        <div style="font-size:16px;font-weight:700;color:#1e3a5f;letter-spacing:.3px;">PT INTI SURYA LABORATORIUM</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 28px 8px;">
                        <p style="margin:0 0 18px;font-size:13px;line-height:22px;">Dengan hormat,</p>
                        <p style="margin:0 0 20px;font-size:13px;line-height:22px;">Yth. <strong>{{ $nama }}</strong></p>

                        <p style="margin:0 0 16px;font-size:13px;line-height:22px;text-align:justify;">
                            Bersama ini kami sampaikan bahwa pembayaran gaji Anda untuk periode
                            <strong>{{ $periodeLabel }}</strong> telah kami proses dan ditransfer ke rekening yang terdaftar.
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;border:1px solid #d1d5db;">
                            <tr>
                                <td width="40%" style="padding:10px 14px;font-size:12px;color:#4b5563;background:#f9fafb;border-bottom:1px solid #e5e7eb;">Periode</td>
                                <td style="padding:10px 14px;font-size:13px;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $periodeLabel }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 14px;font-size:12px;color:#4b5563;background:#f9fafb;">Tanggal transfer</td>
                                <td style="padding:10px 14px;font-size:13px;font-weight:600;">{{ $tglTransferLabel ?? '-' }}</td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px;font-size:13px;line-height:22px;text-align:justify;">
                            Terlampir slip gaji dalam bentuk berkas PDF yang dilindungi kata sandi.
                            Kata sandi untuk membuka berkas tersebut adalah tanggal lahir Anda dengan format
                            <strong>ddmmyyyy</strong>.
                        </p>

                        <p style="margin:0 0 16px;font-size:13px;line-height:22px;text-align:justify;">
                            Selain itu, slip gaji juga dapat diakses melalui aplikasi <strong>Attendance</strong>.
                        </p>

                        <p style="margin:0 0 8px;font-size:13px;line-height:22px;text-align:justify;">
                            Demikian pemberitahuan ini kami sampaikan. Atas perhatian dan kerja sama Anda, kami ucapkan terima kasih.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 28px;">
                        <p style="margin:16px 0 4px;font-size:13px;line-height:22px;">Salam hangat,</p>
                        <p style="margin:0;font-size:13px;line-height:20px;font-weight:700;">
                            Inti Surya Laboratorium
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                        <p style="margin:0;font-size:11px;line-height:17px;color:#6b7280;">
                            Email ini dikirim secara otomatis oleh sistem. Mohon untuk tidak membalas email ini.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
