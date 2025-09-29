<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Pengalihan Tanggung Jawab Quotation</title>
</head>

<body style="margin:0;padding:0;background-color:#f7f7f7;font-family:Arial, Helvetica, sans-serif;color:#222;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f7;padding:20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="margin:0 0 12px 0;font-size:14px;">Yth. {{ trim($sales->new) }},</p>

                            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;">
                                Sehubungan dengan status non-aktifnya sales {{ trim($sales->old) }}, berikut disampaikan
                                penugasan untuk melanjutkan penanganan quotation berikut:
                            </p>

                            <!-- Daftar nomor penawaran: isi sesuai kebutuhan -->
                            <ol style="margin:12px 0 18px 20px;font-size:14px;line-height:1.6;color:#111;">
                                @foreach ($quotation as $qt)
                                    <li>{{ $qt }}</li>
                                @endforeach
                            </ol>

                            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;">
                                Mulai tanggal surat ini, tanggung jawab atas tindak lanjut, komunikasi, serta
                                penyelesaian terkait quotation tersebut berada pada sales pengganti yang ditunjuk. Harap
                                memastikan seluruh proses dapat berjalan lancar sehingga kebutuhan pelanggan tetap
                                terpenuhi.
                            </p>

                            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;">
                                Apabila terdapat kendala atau memerlukan informasi tambahan, silakan berkoordinasi
                                dengan atasan langsung maupun tim terkait.
                            </p>

                            <p style="margin:16px 0 4px 0;font-size:14px;">Demikian disampaikan. Atas perhatian dan
                                kerja sama yang baik diucapkan terima kasih.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f0f0f0;padding:12px 28px;font-size:12px;color:#666;">
                            <em>Catatan:</em> Email ini digenerate secara otomatis oleh sistem. Mohon tidak membalas
                            email ini.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
