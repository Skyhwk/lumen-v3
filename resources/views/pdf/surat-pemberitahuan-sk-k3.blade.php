<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.45;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-line {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .header-line td {
            border-bottom: 1px solid #111;
            font-size: 1px;
            line-height: 1px;
            padding: 0;
        }
        .title-wrap { text-align: right; padding-top: 2mm; }
        .title-box { margin-left: auto; border-collapse: collapse; }
        .title-box td { text-align: center; }
        .title {
            font-size: 14pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .docno {
            font-size: 10pt;
            font-weight: bold;
            margin: 2px 0 0;
        }
        .perihal { margin: 14px 0 10px; }
        .body { text-align: justify; margin: 0 0 8px; }
        .info { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        .info td { vertical-align: top; padding: 1.5px 0; }
        .info .lbl { width: 52mm; white-space: nowrap; }
        .info .sep { width: 4mm; text-align: left; }
        .info .val { }
        .sign { margin-top: 14px; }
        .sign-space { height: 16mm; }
        .sign-name { font-weight: bold; margin: 0; }
        .sign-job { margin: 0; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width:48%; vertical-align:middle;">
                <img src="{{ $logo }}" alt="ISL" style="width: {{ $logoW }}mm; height: {{ $logoH }}mm;">
            </td>
            <td class="title-wrap" style="width:52%; vertical-align:middle;">
                <table class="title-box">
                    <tr>
                        <td>
                            <div class="title">SURAT PEMBERITAHUAN</div>
                            <div class="docno">NO : {{ $noDocument }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="header-line"><tr><td>&nbsp;</td></tr></table>

    <p class="perihal">Perihal : <b><u>Pemberitahuan Pelaksanaan Kegiatan Pengujian Lingkungan Kerja</u></b></p>

    <p>Kepada yang terhormat,<br>&nbsp;</p>

    <p>Dengan hormat,</p>
    <p class="body">
        Sehubungan dengan pemenuhan regulasi yaitu Peraturan Menteri Ketenagakerjaan No. 5 Tahun 2018
        <b>Pasal 65 Ayat (1)</b>;
        <i>”Pemeriksaan dan/atau Pengujian yang dilakukan oleh lembaga eksternal sebagaimana
        dimaksud dalam pasal 59 ayat (4) dilaksanakan dengan berkoordinasi dengan Unit Pengawasan
        Ketenagakerjaan sesuai dengan ketentuan peraturan perundang-undangan”</i>.
        Melalui surat ini saya
        memberitahukan bahwa terdapat pelaksanaan pengujian lingkungan kerja. Adapun detail informasi
        mengenai pengujian dan pemeriksaan yang telah dilakukan serta jumlah SK yang dimohonkan adalah sebagai
        berikut :
    </p>

    <table class="info" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="lbl" width="185" style="white-space:nowrap;">Nama Perusahaan</td>
            <td class="sep" width="12">:</td>
            <td class="val">{{ $namaPerusahaan }}</td>
        </tr>
        <tr>
            <td class="lbl" width="185" style="white-space:nowrap;">Alamat Lengkap Perusahaan</td>
            <td class="sep" width="12">:</td>
            <td class="val">{{ $alamatPerusahaan }}</td>
        </tr>
        <tr>
            <td class="lbl" width="185" style="white-space:nowrap;">Rencana Tanggal Sampling</td>
            <td class="sep" width="12">:</td>
            <td class="val">{{ $tanggalSampling }}</td>
        </tr>
        <tr>
            <td class="lbl" width="185" style="white-space:nowrap;">Pengujian</td>
            <td class="sep" width="12">:</td>
            <td class="val">
                @forelse ($pengujian as $item)
                    - {{ $item }}@if (!$loop->last)<br>@endif
                @empty
                    -
                @endforelse
            </td>
        </tr>
        <tr>
            <td class="lbl" width="185" style="white-space:nowrap;">Jumlah Objek Pengujian/Titik</td>
            <td class="sep" width="12">:</td>
            <td class="val">{{ $jumlahTitik }} Titik</td>
        </tr>
    </table>

    <p class="body">
        Demikian surat pemberitahuan ini dibuat, atas perhatian Bapak/Ibu saya ucapkan terima kasih.
    </p>

    <div class="sign">
        Tangerang, {{ $tanggalSurat }}<br>
        Hormat Saya,
        <div class="sign-space"></div>
        <p class="sign-name">Sucita Rahmi</p>
        <p class="sign-job">Penanggungjawab K3</p>
    </div>
</body>
</html>
