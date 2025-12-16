<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            color: #000;
        }

        p {
            margin: 0 0 10px 0;
        }

        .title {
            font-weight: bold;
            margin-bottom: 15px;
        }

        pre {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <p>
        Kepada yang terhormat,<br>
        <b>
            {{ $client->nama_pic_order }}<br>
            {{ $client->nama_perusahaan }}
        </b>
    </p>

    <br>

    <p>
        Saya harap email ini menemui Anda dalam keadaan baik. Saya ingin mengatur jadwal pengambilan sampling yang telah direncanakan dengan Anda. Berikut adalah rincian jadwal dan informasi yang relevan:
    </p>

    @foreach ($values as $periode => $data)
        @php
            $tanggalArray = is_array($data['tanggal'] ?? null)
                ? $data['tanggal']
                : [];

            if (count($tanggalArray) > 0) {
                $tanggalList = collect($tanggalArray)
                    ->filter()
                    ->sort()
                    ->map(function ($tgl) {
                        return \Carbon\Carbon::parse($tgl)
                            ->locale('id')
                            ->translatedFormat('j F Y');
                    })
                    ->values()
                    ->implode(', ');
            } else {
                $tanggalList = '-';
            }

            $jamMulai   = $data['jam_mulai'] ?? '-';
            $jamSelesai = $data['jam_selesai'] ?? '-';

            try {
                $periodeLabel = \Carbon\Carbon::parse($periode . '-01')
                    ->locale('id')
                    ->translatedFormat('F Y');
            } catch (\Exception $e) {
                $periodeLabel = $periode;
            }
        @endphp

        <!-- WRAPPER -->
        <table cellpadding="0" cellspacing="0" width="100%" style="font-size:14px; margin-bottom:15px;">
            <tr>
                <td>

                    <!-- CONTENT TABLE -->
                    <table cellpadding="0" cellspacing="0" style="font-size:14px;">
                        <tr>
                            <td style="padding-right:10px; white-space:nowrap;">
                                <b>Periode</b>
                            </td>
                            <td style="padding-right:5px;">:</td>
                            <td>
                                {{ $periodeLabel }}
                            </td>
                        </tr>

                        <tr>
                            <td style="padding-right:10px; white-space:nowrap;">
                                <b>Tanggal Pengambilan Sampling</b>
                            </td>
                            <td style="padding-right:5px;">:</td>
                            <td>
                                {{ $tanggalList }}
                            </td>
                        </tr>

                        <tr>
                            <td style="padding-right:10px; white-space:nowrap;">
                                <b>Waktu</b>
                            </td>
                            <td style="padding-right:5px;">:</td>
                            <td>
                                {{ $jamMulai }} s/d {{ $jamSelesai }} WIB
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>

        <!-- SPACER (OUTLOOK SAFE) -->
        <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td height="10"></td>
            </tr>
        </table>

        <!-- <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td style="padding-top:10px; padding-bottom:10px;">
                    <table cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td style="border-top:1px dashed #999;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table> -->
    @endforeach

    <br>
    <p>
        Untuk melihat rincian jadwal, silakan mengakses tautan berikut:
        <a href="{{ env('PORTAL_API') . $file['token'] }}">Klik di sini</a>
    </p>

    <br>
    <p><strong>Perhatian (Konfirmasi dan Pembatalan)</strong></p>
    <p>
        Mohon konfirmasi kesediaan jadwal ini selambat-lambatnya <strong>Tujuh</strong>(7) hari sebelum tanggal pelaksanaan kegiatan sampling pertama.
    </p>
    <p>
        <strong>PENTING</strong>: Apabila konfirmasi belum kami terima, status booking jadwal ini akan secara otomatis dibatalkan dan dipindahkan ke jadwal sampling berikutnya yang tersedia.
    </p>
    <p>
        Jika terdapat perubahan yang perlu disesuaikan, atau jika Anda memiliki pertanyaan lebih lanjut, silakan segera hubungi Departemen Sales kami ({{ $sales->nama_lengkap }} - {{ $sales->no_telpon }})
        atau melalui telepon 021-5089-8988/89.
    </p>
    <br>

    <!-- <p>
        Mohon agar dapat diperiksa lebih lanjut dokumen melalui link berikut:
        <a href="{{ env('PORTAL_API') . $file['token'] }}">Click Here</a>
    </p> -->
    <p>
        Kami sangat menghargai kerjasama Anda dalam proses pengambilan sampling ini dan berharap semuanya berjalan lancar. Terima kasih atas perhatian Anda dan segera konfirmasi jadwal ini agar kami dapat mempersiapkan segala yang diperlukan.
    </p>
    <br>

    <p>
        Terima kasih dan salam,
    </p>

    <br>

    <p>
        {{ $user['nama_lengkap'] }}<br>
        {{ $user['department'] }}<br>
        INTI SURYA LABORATARIUM<br>
        {{ $user['email'] }}<br>
        {{ $user['no_telpon'] }}
    </p>

</body>

</html>