<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 10pt;
        line-height: 1.50;
        color: #111;
        text-align: justify;
    }
    p { margin: 0 0 5mm; }
    .center { text-align: center; }
    .article-title {
        text-align: center;
        font-weight: bold;
        margin: 6mm 0 3mm;
        line-height: 1.3;
    }
    .identity { width: 100%; border-collapse: collapse; margin: 2mm 0; }
    .identity td { padding: 0.8mm 0; vertical-align: top; }
    .identity .roman { width: 7mm; }
    .identity .label { width: 42mm; }
    .identity .separator { width: 3%; text-align: center; }
    .section-row { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    .section-row td { vertical-align: top; }
    .section-row .roman { width: 7mm; }
    .clause { width: 100%; border-collapse: collapse; margin-bottom: 1.5mm; }
    .clause td { vertical-align: top; }
    .clause .number { width: 6mm; }
    .clause .letter { width: 8mm; padding-left: 6mm; }
    ol { margin: 1mm 0 2mm 7mm; padding-left: 5mm; }
    li { margin-bottom: 1.2mm; padding-left: 1mm; }
    .salary { width: 155mm; border-collapse: collapse; margin: 6px 0 10px; }
    .salary td { padding: 3px 5px; vertical-align: top; }
    .salary .separator { width: 4mm; text-align: center; }
    .salary .amount { width: 38mm; text-align: left; white-space: nowrap; }
    .salary .basis { width: auto; white-space: nowrap; padding: 3px 5px; }
    .salary .basis-line { border-collapse: collapse; }
    .salary .basis-line td { padding: 0; border: none; }
    .salary .basis-gap { width: 25mm; }
    .salary .basis-source { white-space: nowrap; }
    .salary .section { font-weight: bold; padding-top: 7px; }
    .signature { width: 100%; border-collapse: collapse; margin-top: 28px; }
    .signature td { width: 50%; text-align: center; vertical-align: top; }
    .signature-space { height: 68px; }
    .approval-wrap { width: 100%; border-collapse: collapse; margin-top: 8mm; }
    .approval-wrap td { border: none; padding: 0; vertical-align: top; }
    .approval-gap { width: 78mm; }
    .approval-slot { width: 104mm; }
    .approval { width: 104mm; border-collapse: collapse; font-size: 10pt; color: #000; }
    .approval td { border: 1px solid #000; padding: 1.2mm 1.6mm; vertical-align: middle; color: #000; }
    .approval-title { text-align: center; font-weight: bold; text-decoration: underline; border-bottom: none; }
    .approval-body { border-top: none; border-bottom: none; padding: 1mm 1.6mm 1.4mm; }
    .approval-meta { width: 100%; border-collapse: collapse; }
    .approval-meta td { border: none; padding: 0.35mm 0; color: #000; }
    .approval-meta .label { width: 42mm; }
    .approval-meta .sep { width: 4mm; text-align: center; }
    .approval-paraf { text-align: center; }
    .approval-sign { width: 34.6mm; height: 18mm; padding: 0; }
    .page-break { page-break-after: always; }
</style>

<div>
    Pada hari ini, {{ $hariPerjanjian }}, tanggal
    {{ $tanggalPerjanjian }}, yang bertanda tangan di bawah ini:
</div>

<table class="section-row">
    <tr>
        <td class="roman">I.</td>
        <td>
            <strong>{{ $namaPenanggungJawab }}</strong>,
            <strong>{{ $jabatanPenanggungJawab }}</strong>, dalam hal ini bertindak
            untuk dan atas nama PT INTI SURYA LABORATORIUM, berkedudukan di Ruko Icon Business
            Park Blok O No. 5-6, Jl. Raya Cisauk, Sampora, Cisauk, Kabupaten Tangerang.
            <br>(Selanjutnya dalam <strong>Perjanjian</strong> Masa Pelatihan ini disebut sebagai <strong>“Perusahaan”</strong>).
        </td>
    </tr>
</table>

<table class="identity">
    <tr>
        <td class="roman" rowspan="6">II.</td>
        <td class="label">Nama / NIK</td><td class="separator">:</td>
        <td>{{ $namaNik }}</td>
    </tr>
    <tr>
        <td class="label">No. KTP</td><td class="separator">:</td>
        <td>{{ $noKtp }}</td>
    </tr>
    <tr>
        <td class="label">Tempat, Tanggal Lahir</td><td class="separator">:</td>
        <td>{{ $tempatTanggalLahir }}</td>
    </tr>
    <tr>
        <td class="label">Jenis Kelamin</td><td class="separator">:</td>
        <td>{{ $jenisKelamin }}</td>
    </tr>
    <tr>
        <td class="label">Alamat Saat Ini</td><td class="separator">:</td>
        <td>{{ $alamatKaryawan }}</td>
    </tr>
    <tr>
        <td class="label">E-mail</td><td class="separator">:</td>
        <td>{{ $emailKaryawan }}</td>
    </tr>
</table>

<p style="margin-left:7mm;">
    (Selanjutnya dalam <strong>Perjanjian</strong> Masa Pelatihan ini disebut sebagai <strong>“Karyawan”</strong>).
</p>
<p><strong>Perusahaan</strong> dan <strong>Karyawan</strong> secara bersama-sama disebut sebagai <strong>“Para Pihak”</strong>.</p>
<p>
    Bahwa untuk menghindari segala sesuatu yang tidak dikehendaki oleh <strong>Para Pihak</strong>
    di kemudian hari, maka <strong>Para Pihak</strong> dengan ini telah saling setuju dan sepakat
    untuk membuat, melaksanakan, dan mematuhi Perjanjian Masa Pelatihan (selanjutnya disebut
    sebagai <strong>“Perjanjian”</strong>) dengan
    syarat-syarat dan ketentuan sebagai berikut:
</p>

<div class="article-title"><div>Pasal 1</div><div>Posisi Kerja</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td><strong>Perusahaan</strong> dengan ini menerima <strong>Karyawan</strong> pada:</td>
    </tr>
</table>
<table class="identity" style="margin-left:6mm; width:94%;">
    <tr><td class="label">a. Posisi</td><td class="separator">:</td><td>{{ $jabatanKaryawan }}</td></tr>
    <tr><td class="label">b. Divisi</td><td class="separator">:</td><td>{{ ($karyawan->getAttributes()['department'] ?? null) ?: (optional($karyawan->getRelation('department'))->nama_divisi ?: '-') }}</td></tr>
    <tr><td class="label">c. Status</td><td class="separator">:</td><td>Karyawan Pelatihan</td></tr>
    <tr><td class="label">d. Lokasi Penempatan</td><td class="separator">:</td><td>{{ optional($karyawan->cabang)->nama_cabang ?: '-' }}</td></tr>
</table>
<table class="clause">
    <tr>
        <td class="number">2.</td>
        <td><strong>Karyawan</strong> bersedia ditempatkan pada posisi/divisi dan/atau lokasi penempatan lain sesuai dengan kebutuhan pihak <strong>Perusahaan</strong>.</td>
    </tr>
</table>

<div class="article-title" style="margin-top:11mm;"><div>Pasal 2</div><div>Waktu Kerja</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>Waktu kerja <strong>Karyawan</strong> mengikuti ketentuan hari dan jam kerja <strong>Perusahaan</strong> sebagaimana diatur dalam Peraturan <strong>Perusahaan</strong> yang berlaku.</td>
    </tr>
</table>

<table class="clause">
    <tr>
        <td class="number">2.</td>
        <td>Dalam hal tertentu, <strong>Karyawan</strong> dapat diberlakukan pengaturan waktu kerja khusus (termasuk sistem shift), yang pelaksanaannya diatur lebih lanjut dalam kebijakan <strong>Perusahaan</strong>, serta sesuai dengan kebutuhan <strong>Perusahaan</strong>.</td>
    </tr>
</table>

<div class="article-title"><div>Pasal 3</div><div>Jangka Waktu Perjanjian</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>
            Jangka waktu Perjanjian ini adalah selama
            <strong>{{ $jumlahJangkaWaktu ?: '-' }} ({{ $jumlahJangkaWaktuTerbilang }}) {{ $satuanJangkaWaktu }}</strong>,
            terhitung dari tanggal <strong>{{ $tanggalMulai }}</strong> sampai dengan
            tanggal <strong>{{ $tanggalSelesai }}</strong>.
        </td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>
            Jangka waktu Perjanjian ini dapat dipersingkat atau diperpanjang sesuai dengan kebutuhan
            dan evaluasi Perusahaan. Apabila diperpanjang, maka akan dilakukan dengan addendum Perjanjian.
        </td>
    </tr>
</table>

<div class="article-title"><div>Pasal 4</div><div>Hak dan Kewajiban Perusahaan</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>Perusahaan berhak mendapatkan hasil kerja yang maksimal dari Karyawan sehingga produktivitas Perusahaan dapat terpenuhi.</td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>Perusahaan berhak melakukan evaluasi, baik dalam hal kinerja, keterampilan kerja, maupun karakter Karyawan.</td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td>Perusahaan wajib membayar upah masa pelatihan Karyawan sesuai dengan yang telah disepakati Para Pihak sebagaimana diatur dalam Pasal 7 Perjanjian ini.</td>
    </tr>
</table>

<div class="article-title"><div>Pasal 5</div><div>Hak dan Kewajiban Karyawan</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>
            Karyawan berkewajiban menaati dan mematuhi seluruh ketentuan dalam Perjanjian ini serta
            peraturan dan Ketetapan Perusahaan, baik yang telah berlaku maupun yang akan diberlakukan
            di kemudian hari, termasuk namun tidak terbatas pada Ketetapan Perusahaan tentang
            Kewajiban dan Larangan.
        </td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>Karyawan berhak mendapatkan upah masa pelatihan yang telah disepakati Para Pihak sebagaimana diatur dalam Pasal 7 Perjanjian ini.</td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td>
            Karyawan berhak atas Tunjangan Hari Raya (THR) sesuai penentuan pilihan hari raya keagamaan dan
            pengaturan <strong>Perusahaan</strong>, yaitu pada <strong>{{ $hariRaya }}</strong>, serta sesuai dengan ketentuan dalam Ketetapan Perusahaan tentang Tunjangan Hari Raya (THR).
        </td>
    </tr>
    <tr>
        <td class="number">4.</td>
        <td>
            <strong>Karyawan</strong> yang akan <em>resign</em> wajib mengajukan surat <em>resign</em> selambat-lambatnya 30 (tiga puluh) hari kalender
            sebelum tanggal <em>resign</em> / tanggal terakhir bekerja, yang terhitung sejak surat <em>resign</em> diterima oleh pihak HRD.
            <strong>Karyawan</strong> yang mengajukan <em>resign</em> juga memiliki kewajiban-kewajiban sebagai berikut:
            <table class="clause">
                <tr>
                    <td class="letter">a.</td>
                    <td>Tidak dalam masa ikatan kerja dengan Perusahaan;</td>
                </tr>
                <tr>
                    <td class="letter">b.</td>
                    <td>Mematuhi seluruh peraturan Perusahaan yang berlaku;</td>
                </tr>
                <tr>
                    <td class="letter">c.</td>
                    <td>Tetap melaksanakan seluruh kewajiban, tugas, dan tanggung jawab sampai tanggal terakhir bekerja;</td>
                </tr>
                <tr>
                    <td class="letter">d.</td>
                    <td>Melakukan serah terima pekerjaan dan mengembalikan inventaris Perusahaan maksimal pada tanggal terakhir bekerja; dan</td>
                </tr>
                <tr>
                    <td class="letter">e.</td>
                    <td>Menyelesaikan seluruh pembayaran dan proses administrasi sebelum tanggal terakhir bekerja.</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="article-title"><div>Pasal 6</div><div>Sanksi</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>
            Apabila <strong>Karyawan</strong> secara lalai atau sengaja melakukan pelanggaran terhadap <strong>Perjanjian</strong> ini,
            Ketetapan, dan/atau Peraturan Perusahaan, maka Karyawan dapat dikenakan:
            <table class="clause">
                <tr>
                    <td class="letter">a.</td>
                    <td>Surat Teguran (ST);</td>
                </tr>
                <tr>
                    <td class="letter">b.</td>
                    <td>Surat Peringatan (SP);</td>
                </tr>
                <tr>
                    <td class="letter">c.</td>
                    <td>Ganti kerugian, khususnya terkait rusak dan/atau hilangnya aset <strong>Perusahaan</strong>;</td>
                </tr>
                <tr>
                    <td class="letter">d.</td>
                    <td>Pemutusan Hubungan Kerja (PHK);</td>
                </tr>
                <tr>
                    <td class="letter">e.</td>
                    <td>Gugatan melalui jalur hukum, baik perdata, pidana, dan/atau ketenagakerjaan; dan/atau</td>
                </tr>
                <tr>
                    <td class="letter">f.</td>
                    <td>Sanksi lain berdasarkan pertimbangan dan penilaian <strong>Perusahaan</strong>.</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>
            <strong>Karyawan</strong> yang terbukti melakukan pelanggaran terhadap <strong>Perjanjian</strong> ini dan/atau larangan lainnya berdasarkan 
            peraturan <strong>Perusahaan</strong>, bersedia melepaskan haknya untuk menuntut <strong>Perusahaan</strong> atas segala tuntutan, baik 
            secara pidana, perdata, maupun ketenagakerjaan. 
        </td>
    </tr>
</table>

<div class="page-break"></div>

<div class="article-title"><div>Pasal 7</div><div>Upah Masa Pelatihan dan Cara Pembayaran</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>Upah masa pelatihan yang disepakati <strong>Para Pihak</strong> adalah sebagai berikut:</td>
    </tr>
</table>
<table class="salary">
    <tr><td colspan="3" class="section">Penerimaan (Per Bulan)</td></tr>
    <tr><td>Gaji Pokok</td><td class="separator">:</td><td class="amount">Rp {{ number_format($gajiPokok, 0, ',', '.') }}</td></tr>
    <tr><td>Tunjangan Kerja (Tidak Tetap)</td><td class="separator">:</td><td class="amount">Rp {{ number_format($tunjangan, 0, ',', '.') }}</td></tr>
    <tr><td colspan="3" class="section">Pengurangan (Per Bulan)</td></tr>
    {{-- <tr><td>BPJS Kesehatan</td><td class="separator">:</td><td class="amount">Rp {{ number_format($bpjsKes, 0, ',', '.') }}</td></tr> --}}
    <tr><td>BPJS Ketenagakerjaan</td><td class="separator">:</td><td class="basis"><table class="basis-line"><tr><td class="basis-gap">Rp {{ number_format($bpjsTk, 0, ',', '.') }}</td></tr></table></td></tr>
    {{-- <tr><td>PPh 21</td><td class="separator">:</td><td class="amount">Rp {{ number_format($pph21, 0, ',', '.') }}</td></tr> --}}
    {{-- @if($pencadangan && (float) $pencadangan->nominal > 0)
        <tr><td>Pencadangan Upah</td><td class="separator">:</td><td class="amount">Rp {{ number_format((float) $pencadangan->nominal, 0, ',', '.') }}</td></tr>
    @endif --}}
    <tr><td>Lainnya</td><td class="separator">:</td><td class="basis"><table class="basis-line"><tr><td class="basis-gap">Rp {{ number_format($totalPotongan - $bpjsTk, 0, ',', '.') }}</td></tr></table></td></tr>

    <tr><td class="section">Total Upah Diterima</td><td class="separator">:</td><td class="amount section">Rp {{ number_format($totalUpah, 0, ',', '.') }}</td></tr>
</table>
@if($pencadangan && (float) $pencadangan->nominal > 0)
    <p>
        Pencadangan Upah (Per Bulan) terhitung sejak <strong>{{ $tanggalPencadanganMulai }}</strong>
        sampai dengan <strong>{{ $tanggalPencadanganSelesai }}</strong>.
    </p>
@endif

<table class="clause">
    <tr>
        <td class="number">2.</td>
        <td>
            Pemberian upah masa pelatihan dilaksanakan dengan ketentuan sebagai berikut:
            <table class="clause">
    <tr>
        <td class="letter">a.</td>
        <td>
            Pembayaran upah masa pelatihan (selain fee) diberikan oleh <strong>Perusahaan</strong> 1 (satu) kali setiap bulan dengan
            metode pembayaran yang disepakati <strong>Para Pihak</strong>, yaitu dengan <strong>{{ $metodePembayaran }}</strong>@if(!empty($rekeningGaji)) pada:
            <table class="identity">
                @foreach($rekeningGaji as $baris)
                    <tr>
                        <td class="label" style="width:52mm;">{{ $baris['label'] }}</td>
                        <td class="separator">:</td>
                        <td>{{ $baris['value'] }}</td>
                    </tr>
                @endforeach
            </table>
            @else.
            @endif
        </td>
    </tr>
    <tr>
        <td class="letter">b.</td>
        <td>
            Metode pembayaran upah masa pelatihan (selain fee) dapat berubah sesuai dengan pengaturan dan
            keputusan <strong>Perusahaan</strong>.
        </td>
    </tr>
    <tr>
        <td class="letter">c.</td>
        <td>
            Pembayaran upah masa pelatihan berupa fee diberikan oleh <strong>Perusahaan</strong> berdasarkan ketentuan yang diatur
            dalam Ketetapan Perusahaan.
        </td>
    </tr>
    <tr>
        <td class="letter">d.</td>
        <td>
            @if($jumlahHariKerja !== null && $jadwalPenggajian !== null)
                Pembayaran akan diberikan {{ $jumlahHariKerja }} hari kerja setelah hari kerja pertama bulan
                berikutnya (H+{{ $jadwalPenggajian }}), atau jika tanggal tersebut
                jatuh bertepatan dengan hari libur, maka pembayaran akan diberikan pada hari kerja
                berikutnya.
            @else
                Pembayaran akan diberikan sejumlah hari kerja setelah hari kerja pertama bulan berikutnya / pada tanggal
                yang ditentukan <strong>Perusahaan</strong> di bulan berikutnya. Apabila tanggal tersebut jatuh bertepatan dengan hari libur,
                pembayaran akan diberikan pada hari kerja sebelumnya / berikutnya sesuai ketentuan <strong>Perusahaan</strong>.
            @endif
        </td>
    </tr>
    @if($pencadangan && (float) $pencadangan->nominal > 0)
        <tr>
            <td class="letter">e.</td>
            <td>
                <strong>Perusahaan</strong> memberikan pencadangan upah pada Pasal 7 kepada <strong>Karyawan</strong> secara bertahap, maksimal
                pada penggajian bulan <strong>{{ $bulanPenggajianPencadangan }}</strong> yang diterima oleh karyawan pada bulan
                <strong>{{ $bulanDiterimaPencadangan }}</strong>, dan/atau sesuai pengaturan pihak perusahaan.
            </td>
        </tr>
    @endif
            </table>
        </td>
    </tr>
</table>

<div class="article-title"><div>Pasal 8</div><div>Berakhirnya Masa Pelatihan</div></div>
<p>Masa pelatihan dalam <strong>Perjanjian</strong> ini berakhir dengan sendirinya apabila:</p>
<table class="clause">
    <tr>
        <td class="letter">a.</td>
        <td><strong>Karyawan</strong> diberikan status gagal masa pelatihan oleh <strong>Perusahaan</strong>;</td>
    </tr>
    <tr>
        <td class="letter">b.</td>
        <td><strong>Karyawan</strong> dikenakan PHK oleh <strong>Perusahaan</strong>;</td>
    </tr>
    <tr>
        <td class="letter">c.</td>
        <td><strong>Perusahaan</strong> memutuskan tidak memperpanjang jangka waktu <strong>Perjanjian</strong> (dengan / tanpa alasan apapun); atau</td>
    </tr>
    <tr>
        <td class="letter">d.</td>
        <td><strong>Karyawan</strong> meninggal dunia.</td>
    </tr>
</table>

<div class="article-title"><div>Pasal 9</div><div>Penyelesaian Perselisihan</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>
            <strong>Para Pihak</strong> sepakat menyelesaikan segala perselisihan yang timbul sehubungan dengan pelaksanaan
            <strong>Perjanjian</strong> ini secara musyawarah untuk mufakat.
        </td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>
            Apabila tidak dapat diselesaikan selama 30 (tiga puluh) hari kalender, <strong>Para Pihak</strong> sepakat
            menyelesaikannya berdasarkan peraturan perundang-undangan di bidang Penyelesaian Perselisihan
            Hubungan Industrial yang berlaku.
        </td>
    </tr>
</table>

<div class="page-break"></div>

<div class="article-title"><div>Pasal 10</div><div>Lain-Lain</div></div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>
            <strong>Karyawan</strong> berjanji tidak akan mempermasalahkan baik secara pidana, perdata, maupun ketenagakerjaan
            terkait Pesangon, Uang Penghargaan Masa Kerja (UPMK), Uang Pengganti Hak (UPH), Uang Pisah (UP), hak-hak
            material dan/atau imaterial apapun ketika masa <strong>Perjanjian</strong> ini berakhir.
        </td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>
            <strong>Karyawan</strong> telah membaca, memahami, dan mengerti isi dari <strong>Perjanjian</strong> ini, serta menyetujuinya dengan
            membubuhkan tanda tangan dengan sadar dan tanpa paksaan dari pihak manapun.
        </td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td>
            Apabila di kemudian hari dipandang perlu untuk melakukan perubahan / penambahan ketentuan dalam
            <strong>Perjanjian</strong> ini, maka akan diatur dalam suatu addendum yang merupakan bagian tak terpisahkan dalam
            <strong>Perjanjian</strong> ini berdasarkan kesepakatan <strong>Para Pihak</strong>.
        </td>
    </tr>
</table>

<p>
    Demikian <strong>Perjanjian</strong> ini dibuat dalam 2 (dua) rangkap, ditandatangani oleh <strong>Para Pihak</strong> dengan
    meterai cukup, dan kedua rangkap memiliki kekuatan hukum yang sama.
</p>

<table class="signature">
    <tr>
        <td><strong>Perusahaan</strong>,</td>
        <td><strong>Karyawan</strong>,</td>
    </tr>
    <tr>
        <td class="signature-space"></td>
        <td class="signature-space"></td>
    </tr>
    <tr>
        <td><strong><u>{{ $namaPenanggungJawab }}</u></strong></td>
        <td><strong><u>{{ $namaKaryawan }}</u></strong></td>
    </tr>
    <tr>
        <td>{{ $jabatanPenanggungJawab }}</td>
        <td>Karyawan Pelatihan</td>
    </tr>
</table>

<table class="approval-wrap">
    <tr>
        <td class="approval-gap"></td>
        <td class="approval-slot">
            <table class="approval">
                <tr>
                    <td colspan="3" class="approval-title">DOKUMEN INI TELAH DISAHKAN</td>
                </tr>
                <tr>
                    <td colspan="3" class="approval-body">
                        <table class="approval-meta">
                            <tr>
                                <td class="label">Tanggal Pengesahan</td>
                                <td class="sep">:</td>
                                <td>{{ $tanggalPerjanjian }}</td>
                            </tr>
                            <tr>
                                <td class="label">Nomor Pengesahan</td>
                                <td class="sep">:</td>
                                <td>{{ $noPengesahan }}</td>
                            </tr>
                            <tr>
                                <td class="label">Lampiran</td>
                                <td class="sep">:</td>
                                <td>{PAGENO} dari {nbpg} Halaman</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="approval-paraf">Paraf</td>
                </tr>
                <tr>
                    <td class="approval-sign"></td>
                    <td class="approval-sign"></td>
                    <td class="approval-sign"></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
