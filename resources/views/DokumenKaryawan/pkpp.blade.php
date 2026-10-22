<style>
    body { font-family: calibri, sans-serif; font-size: 11pt; line-height: 1.28; color: #111; }
    p { margin: 0 0 2.2mm; text-align: justify; }
    .perihal { text-align: left; margin: 0 0 3.5mm; }
    .identity { width: 100%; border-collapse: collapse; margin: 1mm 0 3mm; }
    .identity td { vertical-align: top; padding: 0.35mm 0; text-align: left; border: none; }
    .identity .label { width: 46mm; }
    .identity .sep { width: 4mm; }
    .rules { width: 100%; border-collapse: collapse; margin: 1.5mm 0 3mm; }
    .rules td { border: 0.6px solid #111; vertical-align: middle; padding: 1.3mm 1.6mm; }
    .rules .head { text-align: center; font-weight: bold; }
    .rules .num { width: 12mm; text-align: center; font-weight: bold; }
    .rules .ttd { width: 36mm; }
    .rules .body { padding: 0; }
    .rules .title { padding: 1.3mm 1.6mm; font-weight: bold; }
    .parts { width: 100%; border-collapse: collapse; }
    .parts td { border: none; border-top: 0.6px solid #111; padding: 1.15mm 1.6mm; vertical-align: middle; text-align: left; }
    .parts .letter { width: 8mm; padding-right: 0; }
    .sign { width: 70mm; border-collapse: collapse; margin-top: 5mm; }
    .sign td { text-align: left; border: none; padding: 0; }
    .sign .space { height: 16mm; }
    .name-line { border-collapse: collapse; }
    .name-line td { font-weight: bold; border: none; border-bottom: 0.6px solid #111; padding: 0 0 0.3mm; text-align: left; }
    .sign .role { font-style: italic; font-size: 10.5pt; padding-top: 0.6mm; }
</style>

<p class="perihal">Perihal : <strong>Kepatuhan terhadap Peraturan Perusahaan (<u>Non Sampling</u>)</strong></p>

<p>Saya yang bertanda tangan di bawah ini :</p>

<table class="identity">
    <tr><td class="label">Nama Lengkap / NIK</td><td class="sep">:</td><td>{{ $namaNik }}</td></tr>
    <tr><td class="label">Posisi</td><td class="sep">:</td><td>{{ $posisi }}</td></tr>
    <tr><td class="label">Divisi / Sub-Divisi</td><td class="sep">:</td><td>{{ $divisi }}</td></tr>
</table>

<p>Dengan ini saya telah membaca, mengetahui, menyetujui, serta akan melaksanakan dengan sebaik-baiknya seluruh ketentuan perusahaan yang terdapat dalam :</p>

<table class="rules">
    <tr>
        <td class="num head">No.</td>
        <td class="head">Nama Peraturan</td>
        <td class="ttd head">Tanda Tangan</td>
    </tr>
    <tr>
        <td class="num">1.</td>
        <td class="body">
            <div class="title">Peraturan Umum No. : Per-Um/09/22-VII</div>
            <table class="parts">
                <tr><td class="letter">a.</td><td>Bagian I – Kewajiban dan Larangan</td></tr>
                <tr><td class="letter">b.</td><td>Bagian II – Kehadiran</td></tr>
                <tr><td class="letter">c.</td><td>Bagian III – Upah Kerja</td></tr>
                <tr><td class="letter">d.</td><td>Bagian IV – Fasilitas Kerja dan Aset Perusahaan</td></tr>
                <tr><td class="letter">e.</td><td>Bagian V – Surat Peringatan</td></tr>
                <tr><td class="letter">f.</td><td>Bagian VI – Berakhirnya Hubungan Kerja</td></tr>
            </table>
        </td>
        <td class="ttd"></td>
    </tr>
    <tr>
        <td class="num">2.</td>
        <td class="body">
            <div class="title">Peraturan Penampilan No. Per-PK/09/22-VIII</div>
            <div class="title" style="font-weight:normal; padding-top:0;">Bagian A - Karyawan yang berhubungan / berinteraksi dengan pihak eksternal selain personel yang melakukan kegiatan pengujian (<em>Sampling</em>).</div>
        </td>
        <td class="ttd"></td>
    </tr>
    <tr>
        <td class="num">3.</td>
        <td class="body">
            <div class="title">Peraturan Perusahaan terkait Karyawan Pelatihan (SK Direktur No. : ISL/SK-Dir/23-XII/003)</div>
            <table class="parts">
                <tr><td class="letter">a.</td><td>Bagian A – Jenis Status Karyawan</td></tr>
                <tr><td class="letter">b.</td><td>Bagian B – Cuti Khusus</td></tr>
                <tr><td class="letter">c.</td><td>Bagian C – Cuti Tahunan</td></tr>
                <tr><td class="letter">d.</td><td>Bagian D – Pengakhiran Hubungan Kerja</td></tr>
            </table>
        </td>
        <td class="ttd"></td>
    </tr>
</table>

<p>Dengan ini saya juga menyatakan dengan sebenar-benarnya bahwa saya telah membaca, mengetahui, menyetujui, serta akan melaksanakan dengan sebaik-baiknya <strong>keseluruhan peraturan</strong> tersebut di atas tanpa terkecuali. Apabila terjadi pelanggaran peraturan perusahaan, maka saya bersedia dikenakan sanksi (-sanksi) berdasarkan peraturan, penilaian dan/atau pertimbangan perusahaan.</p>

<table class="sign">
    <tr><td>Tangerang, {{ $tanggalPengesahan }}</td></tr>
    <tr><td class="space"></td></tr>
    <tr><td><table class="name-line"><tr><td>{{ $namaKaryawan }}</td></tr></table></td></tr>
    <tr><td class="role">{{ $jabatanKaryawan }}</td></tr>
</table>
