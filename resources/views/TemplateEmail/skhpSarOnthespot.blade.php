<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Keterangan Hasil Pengujian</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6f8; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0"
                    style="max-width:600px; width:100%; background-color:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px 16px 32px; border-bottom:3px solid #1a5276;">
                            <p style="margin:0; font-size:18px; font-weight:bold; color:#1a5276;">
                                PT Inti Surya Laboratorium
                            </p>
                            <p style="margin:6px 0 0 0; font-size:12px; color:#6b7280;">
                                Surat Keterangan Hasil Pengujian SAR On-The-Spot
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.7;">
                                Kepada Yth.<br>
                                <strong>{{ $data->nama_pelanggan ?? 'Pelanggan' }}</strong>
                            </p>

                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.7; text-align:justify;">
                                Semoga email ini sampai kepada Bapak/Ibu dalam keadaan sehat dan baik.
                                Terima kasih atas kepercayaan yang telah diberikan kepada
                                <strong>PT Inti Surya Laboratorium</strong> dalam layanan pengujian SAR On-The-Spot.
                            </p>

                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.7; text-align:justify;">
                                Bersama email ini, kami sampaikan
                                <strong>Surat Keterangan Hasil Pengujian</strong> untuk pesanan Bapak/Ibu
                                dengan rincian sebagai berikut:
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                style="margin:0 0 20px 0; font-size:13px; line-height:1.6; background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 16px; width:38%; color:#6b7280;">No. Order</td>
                                    <td style="padding:12px 8px; width:4%;">:</td>
                                    <td style="padding:12px 16px;"><strong>{{ $data->no_order ?? '-' }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; color:#6b7280; border-top:1px solid #e5e7eb;">No. Dokumen</td>
                                    <td style="padding:12px 8px; border-top:1px solid #e5e7eb;">:</td>
                                    <td style="padding:12px 16px; border-top:1px solid #e5e7eb;">
                                        {{ str_replace('/', '-', $data->no_document ?? '-') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; color:#6b7280; border-top:1px solid #e5e7eb;">Lokasi Pengambilan</td>
                                    <td style="padding:12px 8px; border-top:1px solid #e5e7eb;">:</td>
                                    <td style="padding:12px 16px; border-top:1px solid #e5e7eb;">
                                        {{ $data->lokasi_pengambilan ?? '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; color:#6b7280; border-top:1px solid #e5e7eb;">Tanggal Selesai</td>
                                    <td style="padding:12px 8px; border-top:1px solid #e5e7eb;">:</td>
                                    <td style="padding:12px 16px; border-top:1px solid #e5e7eb;">
                                        @if (!empty($data->tanggal_selesai))
                                            {{ \Carbon\Carbon::parse($data->tanggal_selesai)->locale('id')->translatedFormat('d F Y') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px 0; font-size:14px; line-height:1.7; text-align:justify;">
                                Dokumen hasil pengujian terlampir dalam format PDF pada email ini.
                                Mohon dapat diperiksa kembali. Apabila terdapat hal yang perlu diklarifikasi,
                                Bapak/Ibu dapat menghubungi kami — kami dengan senang hati akan membantu.
                            </p>

                            <p style="margin:0 0 8px 0; font-size:14px; line-height:1.7; text-align:justify;">
                                Sekali lagi, terima kasih atas kepercayaan dan kerja sama Bapak/Ibu.
                                Semoga hasil pengujian ini bermanfaat.
                            </p>

                            <p style="margin:24px 0 0 0; font-size:14px; line-height:1.7;">
                                Hormat kami,
                            </p>
                            <p style="margin:8px 0 0 0; font-size:14px; line-height:1.7;">
                                <strong>PT Inti Surya Laboratorium</strong><br>
                                <span style="font-size:12px; color:#6b7280;">
                                    Ruko Icon Business Park Blok O No. 5–6, BSD City<br>
                                    Jl. BSD Raya Utama, Cisauk, Tangerang 15341<br>
                                    Telp: 021-5089-8988/89 &nbsp;|&nbsp; Email: contact@intilab.com
                                </span>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:11px; line-height:1.6; color:#9ca3af; text-align:center;">
                                Email ini dikirim secara otomatis oleh sistem.
                                Mohon tidak membalas email ini.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
