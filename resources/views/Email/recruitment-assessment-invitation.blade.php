<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Career Assessment</title>
</head>
<body style="margin:0;padding:0;background-color:#eef3f8;font-family:Arial,Helvetica,sans-serif;color:#18324d;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#eef3f8;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:640px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 36px 24px;background-color:#ffffff;border-bottom:4px solid #1672d6;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size:22px;line-height:26px;font-weight:700;color:#0c3f88;">INTI SURYA</td>
                                    <td align="right" style="font-size:12px;line-height:18px;color:#60758c;">CAREER ASSESSMENT</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px;">
                            <p style="margin:0 0 12px;font-size:15px;line-height:24px;color:#425b75;">Halo {{ $nama_lengkap }},</p>
                            <h1 style="margin:0 0 18px;font-size:28px;line-height:36px;font-weight:700;color:#112f50;">Terima kasih telah mendaftar</h1>
                            <p style="margin:0 0 24px;font-size:16px;line-height:26px;color:#425b75;">Lamaran Anda untuk posisi <strong style="color:#173d68;">{{ $posisi_dilamar }}</strong> telah kami terima. Silakan lanjutkan ke tahap career assessment melalui tombol di bawah ini.</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;background-color:#f2f8ff;border-left:4px solid #1672d6;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 6px;font-size:14px;line-height:20px;font-weight:700;color:#183b61;">Sebelum memulai</p>
                                        <p style="margin:0;font-size:14px;line-height:22px;color:#4a637d;">Pastikan koneksi internet stabil dan kamera perangkat dapat digunakan. Link assessment berlaku selama 2 x 24 jam sejak pendaftaran Anda dibuat.</p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 26px;">
                                <tr>
                                    <td align="center" bgcolor="#126ee8" style="border-radius:6px;">
                                        <a href="{{ $assessment_url }}" target="_blank" style="display:inline-block;padding:14px 24px;font-size:15px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:6px;">Mulai Career Assessment</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px;font-size:13px;line-height:20px;color:#61758b;">Jika tombol tidak dapat dibuka, gunakan tautan berikut:</p>
                            <p style="margin:0;font-size:12px;line-height:19px;word-break:break-all;"><a href="{{ $assessment_url }}" target="_blank" style="color:#126ee8;text-decoration:underline;">{{ $assessment_url }}</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 36px;background-color:#f7f9fc;border-top:1px solid #dde6ef;">
                            <p style="margin:0 0 5px;font-size:14px;line-height:20px;color:#3f5971;">Regards,</p>
                            <p style="margin:0;font-size:14px;line-height:20px;font-weight:700;color:#173d68;">PT Inti Surya Laboratorium</p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:11px;line-height:17px;color:#7c8da0;">Email ini dikirim secara otomatis. Mohon tidak membalas email ini.</p>
            </td>
        </tr>
    </table>
</body>
</html>
