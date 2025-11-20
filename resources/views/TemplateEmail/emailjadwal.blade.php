<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Penjadwalan Pengambilan Sampling</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color:#333;">

    <p><strong>Penjadwalan Pengambilan Sampling</strong></p>

    <p>Kepada Bapak/Ibu <strong>{{ $nama_penerima }}</strong>,</p>

    <p><strong>{{ $nama_perusahaan }}</strong></p>

    <p>
        Kami harap email ini menemui Anda dalam keadaan baik. 
        Kami ingin mengatur jadwal pengambilan sampling yang telah direncanakan dengan Anda. 
        Berikut adalah rincian jadwal dan informasi yang relevan:
    </p>

    <p>
        <strong>Tanggal Pengambilan Sampling:</strong><br>
        <strong>Tanggal Pengambilan Sampling:</strong>
        <ul style="margin-top:6px;">
            @foreach($tanggal_sampling as $tgl)
                <li>{{ \Carbon\Carbon::parse($tgl)->translatedFormat('d F Y') }}</li>
            @endforeach
        </ul>
        <strong>Waktu:</strong> {{ $waktu_mulai }} sd {{ $waktu_selesai }} WIB
    </p>

    <p><strong>Perhatian (Konfirmasi dan Pembatalan)</strong></p>

    <p>
        Mohon konfirmasi kesediaan jadwal ini selambat-lambatnya 
        <strong>Tujuh (7) hari</strong> sebelum tanggal pelaksanaan kegiatan sampling pertama.
    </p>

    <p>
        <strong>PENTING:</strong> Apabila konfirmasi belum kami terima, status booking jadwal ini akan 
        secara otomatis dibatalkan dan dipindahkan ke jadwal sampling berikutnya yang tersedia.
    </p>

    <p>
        Jika terdapat perubahan yang perlu disesuaikan, atau jika Anda memiliki pertanyaan lebih lanjut, 
        silakan segera hubungi Departemen <strong>Sales</strong> kami {{$sales}}, {{$no_tlp}}.
    </p>

    <p>
        Kami sangat menghargai kerjasama Anda dalam proses pengambilan sampling ini dan berharap semuanya berjalan lancar. 
        Terima kasih atas perhatian Anda dan segera konfirmasi jadwal ini agar kami dapat mempersiapkan segala yang diperlukan.
    </p>

    <br>

    <p>Terima kasih dan salam,</p>

    <p>
        <strong>{{ $pengirim_nama }}</strong><br>
        {{ $pengirim_divisi }}<br>
        {{ $pengirim_perusahaan }}<br>
        <a href="mailto:{{ $pengirim_email }}">{{ $pengirim_email }}</a><br>
        {{ $pengirim_nohp }}
    </p>

</body>
</html>
