<style>
    body { font-family: calibri, sans-serif; font-size: 10.5pt; line-height: 1.3; color: #111; text-align: justify; }
    p { margin: 0 0 2.2mm; text-align: justify; }
    .pasal { text-align: center; font-weight: bold; margin: 3.5mm 0 1.5mm; line-height: 1.25; }
    .party { width: 100%; border-collapse: collapse; margin: 0 0 2mm; }
    .party td { vertical-align: top; border: none; padding: 0; }
    .party .mark { width: 9mm; text-align: left; }
    .party .body { text-align: justify; }
    .identity { width: 100%; border-collapse: collapse; margin: 0 0 1.6mm; }
    .identity td { vertical-align: top; padding: 0.25mm 0; text-align: left; border: none; }
    .identity .label { width: 46mm; }
    .identity .sep { width: 4mm; }
    .clause { width: 100%; border-collapse: collapse; margin-bottom: 1.2mm; }
    .clause td { vertical-align: top; text-align: justify; padding: 0.2mm 0; border: none; }
    .clause .number { width: 7mm; text-align: left; }
    .clause .letter { width: 8mm; padding-left: 6mm; text-align: left; }
    .clause .roman { width: 10mm; padding-left: 12mm; text-align: left; }
    .sign { width: 100%; border-collapse: collapse; margin-top: 6mm; }
    .sign td { width: 50%; text-align: center; vertical-align: top; border: none; padding: 0 2mm; }
    .sign .space { height: 18mm; }
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

<p>Perusahaan dan Karyawan secara bersama-sama disebut sebagai “<strong>Para Pihak</strong>”.</p>

<p>Bahwa untuk menghindari segala sesuatu yang tidak dikehendaki oleh <strong>Para Pihak</strong> di kemudian hari, maka <strong>Para Pihak</strong> dengan ini telah saling setuju dan sepakat untuk membuat, melaksanakan, dan mematuhi Perjanjian Kerahasiaan &amp; Non-Kompetisi (selanjutnya disebut sebagai “<strong>Perjanjian</strong>”) dengan syarat-syarat dan ketentuan sebagai berikut :</p>

<div class="pasal">Pasal 1<br>Definisi</div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>Informasi Rahasia adalah setiap data dan/atau informasi, yang termasuk tetapi tidak terbatas pada identitas, jumlah pelanggan dan/atau pihak-pihak yang terkait dengan Perusahaan, ide, rencana, rancangan, rumusan, sistem / metode kerja, program, kebijakan, kontrak, budaya Perusahaan, hal-hal terkait kegiatan operasional maupun pengembangan Perusahaan, dan/atau hal-hal yang menurut Perusahaan tergolong sebagai informasi rahasia Perusahaan dalam bentuk apapun, yang didengar, diperoleh dan/atau diketahui oleh Karyawan dengan cara apapun.</td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>Periode Kerahasiaan adalah masa perlindungan terhadap Informasi Rahasia Perusahaan yang berlaku secara terus menerus sejak masa kerja Karyawan dimulai dan tetap berlaku bahkan setelah berakhirnya masa kerja Karyawan, tanpa batas waktu tertentu, untuk menjaga kerahasiaan informasi Perusahaan secara permanen.</td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td>Periode Larangan Non Kompetisi adalah masa perlindungan terhadap hal-hal yang berpotensi dan/atau mengakibatkan kegiatan kompetitif / persaingan usaha, baik secara langsung maupun tidak langsung dengan Perusahaan, yaitu selama :</td>
    </tr>
    <tr>
        <td class="letter">a.</td>
        <td>Masa kerja; dan</td>
    </tr>
    <tr>
        <td class="letter">b.</td>
        <td>Jangka waktu selama {{ $jangkaWaktu }} tahun sejak tanggal berakhirnya hubungan kerja antara Karyawan dan Perusahaan dengan dasar apapun.</td>
    </tr>
</table>

<div class="pasal">Pasal 2<br>Kewajiban Kerahasiaan</div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>Karyawan menyatakan dan sepakat untuk :</td>
    </tr>
    <tr>
        <td class="letter">a.</td>
        <td>Tetap menjaga dan tidak akan memberikan, menduplikasi, mendiskusikan, membahas, membocorkan, menyebarluaskan, memperjualbelikan, mengalihkan, mengimitasi, baik secara sengaja maupun tidak sengaja, dan/atau melakukan hal apapun terhadap Informasi Rahasia Perusahaan yang mengakibatkan Informasi Rahasia menjadi diketahui, diperoleh, dimiliki, dan/atau beralih dengan cara apapun kepada pihak manapun, serta dengan maksud / tujuan apapun yang dapat dinilai sebagai tindakan yang merugikan Perusahaan;</td>
    </tr>
    <tr>
        <td class="letter">b.</td>
        <td>Tidak mengungkapkan Informasi Rahasia tersebut kepada pihak ketiga manapun tanpa izin tertulis dari Perusahaan; dan</td>
    </tr>
    <tr>
        <td class="letter">c.</td>
        <td>Tidak menggunakan Informasi Rahasia untuk kepentingan pribadi atau pihak manapun selain Perusahaan.</td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>Kewajiban ini tetap berlaku selama Periode Kerahasiaan, terlepas dari alasan berakhirnya hubungan kerja antara Karyawan dan Perusahaan.</td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td>Kewajiban Kerahasiaan tidak berlaku terhadap informasi yang :</td>
    </tr>
    <tr>
        <td class="letter">a.</td>
        <td>Telah menjadi domain publik tanpa pelanggaran Perjanjian ini; dan/atau</td>
    </tr>
    <tr>
        <td class="letter">b.</td>
        <td>Wajib diungkap berdasarkan perintah hukum dan/atau otoritas yang sah.</td>
    </tr>
</table>

<div class="pasal">Pasal 3<br>Kewajiban Non-Kompetisi</div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>Karyawan secara tegas menyatakan dan menyetujui bahwa selama Periode Larangan Non Kompetisi, Karyawan tidak akan, secara langsung maupun tidak langsung, di seluruh wilayah Republik Indonesia dan/atau yurisdiksi lain tempat Perusahaan menjalankan atau merencanakan kegiatan usaha, melakukan salah satu dan/atau seluruh tindakan berikut :</td>
    </tr>
    <tr>
        <td class="letter">a.</td>
        <td>Bekerja pada, memberikan jasa dalam bentuk apapun, dan/atau terlibat dalam kapasitas apapun, baik sebagai karyawan, tenaga ahli, konsultan, kontraktor independen, penasihat, pengelola, maupun pihak terkait lainnya pada entitas bisnis manapun dan dengan bentuk usaha apapun, yang secara langsung maupun tidak langsung dalam menjalankan kegiatan usaha yang sejenis, serupa, dan/atau bersaing dengan Perusahaan;</td>
    </tr>
    <tr>
        <td class="letter">b.</td>
        <td>Mendirikan, mengelola, membiayai, dan/atau menjadi pemegang saham, anggota direksi, komisaris, mitra, agen, konsultan, atau afiliasi dari entitas bisnis yang bersaing, secara aktual maupun potensial, dengan kegiatan usaha Perusahaan, baik secara langsung maupun melalui perantara;</td>
    </tr>
    <tr>
        <td class="letter">c.</td>
        <td>Mengajak, membujuk, memengaruhi, menyarankan, dan/atau mendorong karyawan lain, pelanggan, pengguna jasa, vendor, atau mitra bisnis Perusahaan dengan cara apapun, untuk :</td>
    </tr>
    <tr>
        <td class="roman">i.</td>
        <td>Berhenti bekerja sama dan/atau mengakhiri hubungan kerja dengan Perusahaan;</td>
    </tr>
    <tr>
        <td class="roman">ii.</td>
        <td>Berpindah ke entitas lain yang bersaing dengan Perusahaan; dan/atau</td>
    </tr>
    <tr>
        <td class="roman">iii.</td>
        <td>Menjalin hubungan kerja sama atau hubungan usaha dengan pihak lain yang berpotensi menimbulkan persaingan dengan Perusahaan;</td>
    </tr>
    <tr>
        <td class="letter">d.</td>
        <td>Melakukan, memfasilitasi, atau berkontribusi terhadap tindakan apapun, baik secara langsung maupun tidak langsung, yang dapat berdampak pada kepentingan komersial, operasional, finansial, atau reputasional Perusahaan, termasuk namun tidak terbatas pada :</td>
    </tr>
    <tr>
        <td class="roman">i.</td>
        <td>Menyalahgunakan atau mengeksploitasi sumber daya, informasi, atau kerja sama, atau rencana bisnis Perusahaan yang diperoleh selama bekerja di Perusahaan untuk kepentingan pihak lain; dan/atau</td>
    </tr>
    <tr>
        <td class="roman">ii.</td>
        <td>Mendukung secara pasif atau aktif kegiatan usaha yang diketahui bersaing atau berpotensi bersaing dengan Perusahaan.</td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>Karyawan dengan ini mengakui bahwa kewajiban Non-Kompetisi ini adalah adil, wajar, dan proporsional, serta diperlukan untuk melindungi kepentingan sah Perusahaan yang mencakup rahasia dagang, relasi bisnis, strategi komersial, stabilitas, dan kontinuitas usaha Perusahaan secara keseluruhan.</td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td>Ketentuan dalam Pasal ini hanya dapat dikesampingkan dan dinyatakan tidak berlaku, apabila :</td>
    </tr>
    <tr>
        <td class="letter">a.</td>
        <td>Perusahaan secara eksplisit memberikan persetujuan tertulis kepada Karyawan atas pengecualian Perjanjian ini, serta ditandatangani oleh pihak Perusahaan yang berwenang;</td>
    </tr>
    <tr>
        <td class="letter">b.</td>
        <td>Kegiatan atau aktivitas yang dilakukan oleh Karyawan terbukti secara objektif :</td>
    </tr>
    <tr>
        <td class="roman">i.</td>
        <td>Tidak memiliki hubungan langsung dengan sektor, model, sistem, pendekatan bisnis, jenis layanan, atau produk yang dijalankan oleh Perusahaan; dan</td>
    </tr>
    <tr>
        <td class="roman">ii.</td>
        <td>Tidak berpotensi menimbulkan persaingan, konflik kepentingan, atau dampak negatif terhadap Perusahaan;</td>
    </tr>
    <tr>
        <td class="letter">c.</td>
        <td>Periode Larangan Non Kompetisi sebagaimana diatur dalam Pasal 1 angka (3) telah berakhir secara penuh dan tidak diperpanjang berdasarkan ketentuan apa pun.</td>
    </tr>
</table>

<div class="pasal">Pasal 4<br>Sanksi</div>
<p>Dalam hal <strong>Karyawan</strong> terbukti melanggar salah satu ketentuan dalam <strong>Perjanjian</strong> ini, maka <strong>Karyawan</strong> setuju untuk :</p>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td>Bertanggung jawab secara penuh dan bersedia dikenakan sanksi apapun sesuai dengan keputusan pihak <strong>Perusahaan</strong> dengan tidak akan menuntut apapun kepada <strong>Perusahaan</strong> / pihak <strong>Perusahaan</strong> manapun, baik secara perdata / pidana dan/atau ketenagakerjaan;</td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>Mengganti seluruh kerugian materil dan imateril kepada <strong>Perusahaan</strong> (termasuk seluruh biaya berkenaan dengan biaya sehubungan dengan peradilan) serta potensi yang timbul bagi <strong>Perusahaan</strong>, termasuk kehilangan pelanggan, reputasi, dan keuntungan; dan/atau</td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td>Digugat pada jalur hukum oleh <strong>Perusahaan</strong> sesuai dengan hukum yang berlaku.</td>
    </tr>
</table>

<div class="pasal">Pasal 5<br>Penyelesaian Perselisihan</div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td><strong>Para Pihak</strong> sepakat untuk menyelesaikan setiap perselisihan yang timbul dari dan/atau sehubungan dengan pelaksanaan <strong>Perjanjian</strong> ini secara musyawarah untuk mufakat terlebih dahulu dalam jangka waktu paling lama 30 (tiga puluh) hari kalender.</td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td>Dalam hal perselisihan tersebut tidak dapat diselesaikan dengan cara musyawarah untuk mufakat selama 30 (tiga puluh) hari kalender, maka <strong>Para Pihak</strong> sepakat untuk menyelesaikannya melalui Pengadilan Negeri Tangerang.</td>
    </tr>
</table>

<div class="pasal">Pasal 6<br>Ketentuan Penutup</div>
<table class="clause">
    <tr>
        <td class="number">1.</td>
        <td><strong>Perjanjian</strong> ini mulai berlaku sejak tanggal ditandatangani dan tetap mengikat <strong>Para Pihak</strong> sesuai dengan jangka waktu masing-masing kewajiban yang disebutkan di atas.</td>
    </tr>
    <tr>
        <td class="number">2.</td>
        <td><strong>Perjanjian</strong> ini merupakan satu kesatuan yang tidak terpisahkan dari peraturan dan kebijakan <strong>Perusahaan</strong>, ketentuan kerahasiaan, serta kode etik yang berlaku di <strong>Perusahaan</strong>.</td>
    </tr>
    <tr>
        <td class="number">3.</td>
        <td><strong>Perjanjian</strong> ini merupakan kesepakatan penuh yang mengatur hubungan antara <strong>Para Pihak</strong> dan tidak dapat diganggu gugat, kecuali terdapat kesepakatan tertulis yang sah untuk perubahan atau pembatalan <strong>Perjanjian</strong> ini oleh kedua belah pihak.</td>
    </tr>
    <tr>
        <td class="number">4.</td>
        <td>Apabila terdapat ketentuan dalam Perjanjian ini yang dinyatakan tidak sah atau tidak berlaku oleh putusan pengadilan yang berwenang, maka ketentuan lainnya tetap berlaku dan mengikat Para Pihak secara penuh.</td>
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
