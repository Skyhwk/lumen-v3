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

    @php
        $tanggalArray = is_array($tanggal) ? $tanggal : [];

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
    @endphp


    <table cellpadding="0" cellspacing="0" style="font-size:14px;">
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
                {{ $jam_mulai }} s/d {{ $jam_selesai }} WIB
            </td>
        </tr>
    </table>

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