<style>
    body { font-family: calibri, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #111; text-align: justify; }
    p { margin: 0 0 2.7mm; text-align: justify; }
    .pasal { text-align: center; font-weight: bold; margin: 3.5mm 0 1.5mm; line-height: 1.25; }
    .party { width: 100%; border-collapse: collapse; margin: 0 0 2mm; }
    .party td { vertical-align: top; border: none; padding: 0; }
    .party .mark { width: 12mm; text-align: left; padding-right: 2mm; white-space: nowrap; }
    .party .body { text-align: justify; }
    .identity { width: 100%; border-collapse: collapse; margin: 0 0 1.6mm; }
    .identity td { vertical-align: top; padding: 0.25mm 0; text-align: left; border: none; }
    .identity .label { width: 46mm; }
    .identity .sep { width: 4mm; }
    .clause { width: 100%; border-collapse: collapse; margin-bottom: 1.2mm; }
    .clause td { vertical-align: top; text-align: justify; padding: 0.45mm 0; border: none; }
    .clause .mark { text-align: left; white-space: nowrap; padding-right: 2.4mm; }
    .clause .number { width: 9mm; }
    .clause .letter { width: 8mm; }
    .clause .roman { width: 11mm; }
    .sign { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    .sign td { width: 50%; text-align: center; vertical-align: top; border: none; padding: 0 2mm; }
    .sign .space { height: 24mm; }
    .sign .name { font-weight: bold; }
</style>

<p>Pada hari ini, {{ $hariPerjanjian }}, tanggal {{ $tanggalPerjanjian }}, yang bertanda tangan di bawah ini :</p>

<table class="party">
    <tr>
        <td class="mark">I.</td>
        <td class="body"><strong>{{ $namaPenanggungJawab }}</strong>, <strong>{{ $jabatanPenanggungJawab }}</strong>, dalam hal ini bertindak untuk dan atas nama PT INTI SURYA LABORATORIUM, berkedudukan di Ruko Icon Business Park Blok O No. 5 – 6, Jl. Raya Cisauk, Sampora, Cisauk, Kabupaten Tangerang.<br>(Selanjutnya dalam Perjanjian Kerahasiaan &amp; Non-Kompetisi ini disebut sebagai “<strong>Perusahaan</strong>”).</td>
    </tr>
    <tr><td class="mark" style="height:2.4mm;"></td><td></td></tr>
    <tr>
        <td class="mark">II.</td>
        <td class="body">
            <table class="identity">
                <tr><td class="label">Nama / NIK</td><td class="sep">:</td><td>{{ $namaNik }}</td></tr>
                <tr><td class="label">No. KTP</td><td class="sep">:</td><td>{{ $noKtp }}</td></tr>
                <tr><td class="label">Tempat, Tanggal Lahir</td><td class="sep">:</td><td>{{ $tempatTanggalLahir }}</td></tr>
                <tr><td class="label">Jenis Kelamin</td><td class="sep">:</td><td>{{ $jenisKelamin }}</td></tr>
                <tr><td class="label">Alamat Saat Ini</td><td class="sep">:</td><td>{{ $alamat }}</td></tr>
            </table>
            (Selanjutnya dalam Perjanjian Kerahasiaan &amp; Non-Kompetisi ini disebut sebagai “<strong>Karyawan</strong>”).
        </td>
    </tr>
</table>

<p><strong>Perusahaan</strong> dan <strong>Karyawan</strong> secara bersama-sama disebut sebagai “<strong>Para Pihak</strong>”.</p>

<p>Bahwa untuk menghindari segala sesuatu yang tidak dikehendaki oleh <strong>Para Pihak</strong> di kemudian hari, maka <strong>Para Pihak</strong> dengan ini telah saling setuju dan sepakat untuk membuat, melaksanakan, dan mematuhi Perjanjian Kerahasiaan &amp; Non-Kompetisi (selanjutnya disebut sebagai “<strong>Perjanjian</strong>”) dengan syarat-syarat dan ketentuan sebagai berikut :</p>

<div class="pasal">Pasal 1<br>Definisi</div>
<table class="clause">
    <colgroup><col style="width:9mm;"><col style="width:8mm;"><col style="width:11mm;"><col></colgroup>
    <tr>
        <td class="mark number">1.</td>
        <td colspan="3"><strong>Informasi Rahasia</strong> adalah setiap data dan/atau informasi, yang termasuk tetapi tidak terbatas pada identitas, jumlah pelanggan dan/atau pihak-pihak yang terkait dengan <strong>Perusahaan</strong>, ide, rencana, rancangan, rumusan, sistem / metode kerja, program, kebijakan, kontrak, budaya <strong>Perusahaan</strong>, hal-hal terkait kegiatan operasional maupun pengembangan <strong>Perusahaan</strong>, dan/atau hal-hal yang menurut <strong>Perusahaan</strong> tergolong sebagai informasi rahasia <strong>Perusahaan</strong> dalam bentuk apapun, yang didengar, diperoleh dan/atau diketahui oleh <strong>Karyawan</strong> dengan cara apapun.</td>
    </tr>
    <tr>
        <td class="mark number">2.</td>
        <td colspan="3"><strong>Periode Kerahasiaan</strong> adalah masa perlindungan terhadap <strong>Informasi Rahasia Perusahaan</strong> yang berlaku secara terus menerus sejak masa kerja <strong>Karyawan</strong> dimulai dan tetap berlaku bahkan setelah berakhirnya masa kerja <strong>Karyawan</strong>, tanpa batas waktu tertentu, untuk menjaga kerahasiaan informasi <strong>Perusahaan</strong> secara permanen.</td>
    </tr>
    <tr>
        <td class="mark number">3.</td>
        <td colspan="3"><strong>Periode Larangan Non Kompetisi</strong> adalah masa perlindungan terhadap hal-hal yang berpotensi dan/atau mengakibatkan kegiatan kompetitif / persaingan usaha, baik secara langsung maupun tidak langsung dengan <strong>Perusahaan</strong>, yaitu selama:</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">a.</td>
        <td colspan="2">Masa kerja; dan</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">b.</td>
        <td colspan="2">Jangka waktu selama {{ $jangkaWaktuNonKompetisi }} tahun sejak tanggal berakhirnya hubungan kerja antara Karyawan dan Perusahaan dengan dasar apapun.</td>
    </tr>
</table>

<div class="pasal">Pasal 2<br>Kewajiban Kerahasiaan</div>
<table class="clause">
    <colgroup><col style="width:9mm;"><col style="width:8mm;"><col style="width:11mm;"><col></colgroup>
    <tr>
        <td class="mark number">1.</td>
        <td colspan="3"><strong>Karyawan</strong> menyatakan dan sepakat untuk :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">a.</td>
        <td colspan="2">Tetap menjaga dan tidak akan memberikan, menduplikasi, mendiskusikan, membahas, membocorkan, menyebarluaskan, memperjualbelikan, mengalihkan, mengimitasi, baik secara sengaja maupun tidak sengaja, dan/atau melakukan hal apapun terhadap <strong>Informasi Rahasia Perusahaan</strong> yang mengakibatkan <strong>Informasi Rahasia</strong> menjadi diketahui, diperoleh, dimiliki, dan/atau beralih dengan cara apapun kepada pihak manapun, serta dengan maksud / tujuan apapun yang dapat dinilai sebagai tindakan yang merugikan <strong>Perusahaan</strong>;</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">b.</td>
        <td colspan="2">Tidak mengungkapkan <strong>Informasi Rahasia</strong> tersebut kepada pihak ketiga manapun tanpa izin tertulis dari <strong>Perusahaan</strong>; dan</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">c.</td>
        <td colspan="2">Tidak menggunakan <strong>Informasi Rahasia</strong> untuk kepentingan pribadi atau pihak manapun selain <strong>Perusahaan</strong>.</td>
    </tr>
    <tr>
        <td class="mark number">2.</td>
        <td colspan="3">Kewajiban ini tetap berlaku selama <strong>Periode Kerahasiaan</strong>, terlepas dari alasan berakhirnya hubungan kerja antara <strong>Karyawan</strong> dan <strong>Perusahaan</strong>.</td>
    </tr>
    <tr>
        <td class="mark number">3.</td>
        <td colspan="3">Kewajiban Kerahasiaan tidak berlaku terhadap informasi yang :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">a.</td>
        <td colspan="2">Telah menjadi domain publik tanpa pelanggaran Perjanjian ini; dan/atau</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">b.</td>
        <td colspan="2">Wajib diungkap berdasarkan perintah hukum dan/atau otoritas yang sah.</td>
    </tr>
</table>

<div class="pasal">Pasal 3<br>Kewajiban Non-Kompetisi</div>
<table class="clause">
    <colgroup><col style="width:9mm;"><col style="width:8mm;"><col style="width:11mm;"><col></colgroup>
    <tr>
        <td class="mark number">1.</td>
        <td colspan="3"><strong>Karyawan</strong> secara tegas menyatakan dan menyetujui bahwa selama <strong>Periode Larangan Non Kompetisi</strong>, <strong>Karyawan</strong> tidak akan, secara langsung maupun tidak langsung, di seluruh wilayah Republik Indonesia dan/atau yurisdiksi lain tempat <strong>Perusahaan</strong> menjalankan atau merencanakan kegiatan usaha, melakukan salah satu dan/atau seluruh tindakan berikut :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">a.</td>
        <td colspan="2">Bekerja pada, memberikan jasa dalam bentuk apapun, dan/atau terlibat dalam kapasitas apapun, baik sebagai karyawan, tenaga ahli, konsultan, kontraktor independen, penasihat, pengelola, maupun pihak terkait lainnya pada entitas bisnis manapun dan dengan bentuk usaha apapun, yang secara langsung maupun tidak langsung dalam menjalankan kegiatan usaha yang sejenis, serupa, dan/atau bersaing dengan <strong>Perusahaan</strong>;</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">b.</td>
        <td colspan="2">Mendirikan, mengelola, membiayai, dan/atau menjadi pemegang saham, anggota direksi, komisaris, mitra, agen, konsultan, atau afiliasi dari entitas bisnis yang bersaing, secara aktual maupun potensial, dengan kegiatan usaha <strong>Perusahaan</strong>, baik secara langsung maupun melalui perantara;</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">c.</td>
        <td colspan="2">Mengajak, membujuk, memengaruhi, menyarankan, dan/atau mendorong karyawan lain, pelanggan, pengguna jasa, vendor, atau mitra bisnis <strong>Perusahaan</strong> dengan cara apapun, untuk :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">&nbsp;</td>
        <td class="mark roman">i.</td>
        <td>Berhenti bekerja sama dan/atau mengakhiri hubungan kerja dengan <strong>Perusahaan</strong>;</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">&nbsp;</td>
        <td class="mark roman">ii.</td>
        <td>Berpindah ke entitas lain yang bersaing dengan <strong>Perusahaan</strong>; dan/atau</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">&nbsp;</td>
        <td class="mark roman">iii.</td>
        <td>Menjalin hubungan kerja sama atau hubungan usaha dengan pihak lain yang berpotensi menimbulkan persaingan dengan <strong>Perusahaan</strong>;</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">d.</td>
        <td colspan="2">Melakukan, memfasilitasi, atau berkontribusi terhadap tindakan apapun, baik secara langsung maupun tidak langsung, yang dapat berdampak pada kepentingan komersial, operasional, finansial, atau reputasional <strong>Perusahaan</strong>, termasuk namun tidak terbatas pada :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">&nbsp;</td>
        <td class="mark roman">i.</td>
        <td>Menyalahgunakan atau mengeksploitasi sumber daya, informasi, atau kerja sama, atau rencana bisnis <strong>Perusahaan</strong> yang diperoleh selama bekerja di <strong>Perusahaan</strong> untuk kepentingan pihak lain; dan/atau</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">&nbsp;</td>
        <td class="mark roman">ii.</td>
        <td>Mendukung secara pasif atau aktif kegiatan usaha yang diketahui bersaing atau berpotensi bersaing dengan <strong>Perusahaan</strong>.</td>
    </tr>
    <tr>
        <td class="mark number">2.</td>
        <td colspan="3"><strong>Karyawan</strong> dengan ini mengakui bahwa kewajiban Non-Kompetisi ini adalah adil, wajar, dan proporsional, serta diperlukan untuk melindungi kepentingan sah <strong>Perusahaan</strong> yang mencakup rahasia dagang, relasi bisnis, strategi komersial, stabilitas, dan kontinuitas usaha <strong>Perusahaan</strong> secara keseluruhan.</td>
    </tr>
    <tr>
        <td class="mark number">3.</td>
        <td colspan="3">Ketentuan dalam Pasal ini hanya dapat dikesampingkan dan dinyatakan tidak berlaku, apabila :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">a.</td>
        <td colspan="2"><strong>Perusahaan</strong> secara eksplisit memberikan persetujuan tertulis kepada <strong>Karyawan</strong> atas pengecualian <strong>Perjanjian</strong> ini, serta ditandatangani oleh pihak <strong>Perusahaan</strong> yang berwenang;</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">b.</td>
        <td colspan="2">Kegiatan atau aktivitas yang dilakukan oleh <strong>Karyawan</strong> terbukti secara objektif dan faktual bahwa :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">&nbsp;</td>
        <td class="mark roman">i.</td>
        <td>Tidak memiliki hubungan langsung dengan sektor, model, sistem, pendekatan bisnis, jenis layanan, atau produk yang dijalankan oleh <strong>Perusahaan</strong>; dan</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">&nbsp;</td>
        <td class="mark roman">ii.</td>
        <td>Tidak berpotensi menimbulkan persaingan, konflik kepentingan, atau dampak negatif terhadap <strong>Perusahaan</strong>;</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">c.</td>
        <td colspan="2"><strong>Karyawan</strong> terbukti secara objektif dan faktual bahwa tidak lagi membawa akses, pengaruh dan/atau informasi strategis yang berpotensi dan/atau berisiko bahwa tindakan <strong>Karyawan</strong> dapat menimbulkan konflik kepentingan, kompetisi usaha, dampak merugikan terhadap kepentingan strategis, operasional dan/atau komersial <strong>Perusahaan</strong>.</td>
    </tr>
</table>

<div class="pasal">Pasal 4<br>Sanksi</div>
<p>Dalam hal <strong>Karyawan</strong> terbukti melanggar salah satu ketentuan dalam <strong>Perjanjian</strong> ini, maka <strong>Karyawan</strong> setuju untuk :</p>
<table class="clause">
    <colgroup><col style="width:9mm;"><col style="width:8mm;"><col style="width:11mm;"><col></colgroup>
    <tr>
        <td class="mark number">1.</td>
        <td colspan="3">Bertanggung jawab secara penuh dan bersedia dikenakan sanksi apapun sesuai dengan keputusan pihak <strong>Perusahaan</strong> dengan tidak akan menuntut apapun kepada <strong>Perusahaan</strong> / pihak <strong>Perusahaan</strong> manapun, baik secara perdata / pidana dan/atau ketenagakerjaan;</td>
    </tr>
    <tr>
        <td class="mark number">2.</td>
        <td colspan="3">Mengganti seluruh kerugian materil dan imateril kepada <strong>Perusahaan</strong> (termasuk seluruh biaya berkenaan dengan biaya sehubungan dengan peradilan) serta potensi yang timbul bagi <strong>Perusahaan</strong>, termasuk kehilangan pelanggan, reputasi, dan keuntungan; dan/atau</td>
    </tr>
    <tr>
        <td class="mark number">3.</td>
        <td colspan="3">Digugat pada jalur hukum oleh <strong>Perusahaan</strong> sesuai dengan hukum yang berlaku.</td>
    </tr>
</table>

<div class="pasal">Pasal 5<br>Penyelesaian Perselisihan</div>
<table class="clause">
    <colgroup><col style="width:9mm;"><col style="width:8mm;"><col style="width:11mm;"><col></colgroup>
    <tr>
        <td class="mark number">1.</td>
        <td colspan="3"><strong>Para Pihak</strong> sepakat untuk menyelesaikan setiap perselisihan yang timbul dari dan/atau sehubungan dengan pelaksanaan <strong>Perjanjian</strong> ini secara musyawarah untuk mufakat terlebih dahulu dalam jangka waktu paling lama 30 (tiga puluh) hari kalender.</td>
    </tr>
    <tr>
        <td class="mark number">2.</td>
        <td colspan="3">Dalam hal perselisihan tersebut tidak dapat diselesaikan dengan cara musyawarah untuk mufakat selama 30 (tiga puluh) hari kalender, maka <strong>Para Pihak</strong> sepakat untuk menyelesaikannya melalui Pengadilan Negeri Tangerang.</td>
    </tr>
</table>

<div class="pasal">Pasal 6<br>Ketentuan Penutup</div>
<table class="clause">
    <colgroup><col style="width:9mm;"><col style="width:8mm;"><col style="width:11mm;"><col></colgroup>
    <tr>
        <td class="mark number">1.</td>
        <td colspan="3"><strong>Perjanjian</strong> ini mulai berlaku sejak tanggal ditandatangani dan tetap mengikat <strong>Para Pihak</strong> sepanjang hubungan kerja berlangsung, serta tetap berlaku setelahnya dengan ketentuan sebagai berikut :</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">a.</td>
        <td colspan="2"><strong>Karyawan</strong> masih menguasai atau menyimpan Informasi Rahasia sebagaimana dimaksud dalam Pasal 2; dan/atau</td>
    </tr>
    <tr>
        <td class="mark number">&nbsp;</td>
        <td class="mark letter">b.</td>
        <td colspan="2">Terdapat potensi persaingan, konflik kepentingan, dan/atau risiko kerugian terhadap <strong>Perusahaan</strong> yang dapat timbul akibat pengaruh dan/atau tindakan <strong>Karyawan</strong>, sebagaimana diatur dalam Pasal 3.</td>
    </tr>
    <tr>
        <td class="mark number">2.</td>
        <td colspan="3"><strong>Perjanjian</strong> ini merupakan satu kesatuan yang tidak terpisahkan dari peraturan dan kebijakan <strong>Perusahaan</strong>, ketentuan kerahasiaan, serta kode etik yang berlaku di <strong>Perusahaan</strong>.</td>
    </tr>
    <tr>
        <td class="mark number">3.</td>
        <td colspan="3"><strong>Perjanjian</strong> ini merupakan kesepakatan penuh yang mengatur hubungan antara <strong>Para Pihak</strong> dan tidak dapat diganggu gugat, kecuali terdapat kesepakatan tertulis yang sah untuk perubahan atau pembatalan <strong>Perjanjian</strong> ini oleh kedua belah pihak.</td>
    </tr>
    <tr>
        <td class="mark number">4.</td>
        <td colspan="3">Apabila terdapat ketentuan dalam Perjanjian ini yang dinyatakan tidak sah atau tidak berlaku oleh putusan pengadilan yang berwenang, maka ketentuan lainnya tetap berlaku dan mengikat Para Pihak secara penuh.</td>
    </tr>
</table>

<p>Demikian <strong>Perjanjian</strong> ini dibuat dalam 2 (dua) rangkap, yang ditandatangani oleh <strong>Para Pihak</strong> dengan bermeterai cukup, serta memiliki kekuatan hukum yang sama.</p>

<table class="sign">
    <tr>
        <td>Perusahaan,</td>
        <td>Karyawan,</td>
    </tr>
    <tr>
        <td class="space"></td>
        <td class="space"></td>
    </tr>
    <tr>
        <td>
            <div class="name" style="font-weight:bold; text-decoration:underline;">{{ $namaPenanggungJawab }}</div>
            <div>{{ $jabatanPenanggungJawab }}</div>
        </td>
        <td>
            <div class="name" style="font-weight:bold; text-decoration:underline;">{{ $namaKaryawan }}</div>
            <div>{{ $jabatanKaryawan }}</div>
        </td>
    </tr>
</table>
