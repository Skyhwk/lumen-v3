<?php

/**
 * 100 Soal Standar Industri untuk Kategori: UI_UX_DESIGN
 * Manager Scope - Dedi Wibowo (IT Programming Manager)
 * 
 * Topik:
 * 1. 10 Nielsen Usability Heuristics & Fundamental HCI Principles (20 soal)
 * 2. Gestalt Principles, Visual Hierarchy, Typography, Color Theory & WCAG Accessibility (20 soal)
 * 3. Design System, Atomic Design, Components, Variants & Auto-layout (20 soal)
 * 4. Information Architecture, Navigation, Interaction Design & Cognitive Laws (20 soal)
 * 5. UX Research Methods, Usability Testing, Metrics (SUS, SEQ) & Prototyping (20 soal)
 *
 * Referensi: Jakob Nielsen & Don Norman (Nielsen Norman Group), ISO 9241-210 (Human-centred design),
 * Don Norman (The Design of Everyday Things), Brad Frost (Atomic Design), WCAG 2.1 Guidelines,
 * John Brooke (SUS Framework), IEEE / ACM SIGCHI.
 */

$helpers = require __DIR__ . '/QuestionSeederHelpers.php';
$make = $helpers['makeQuestion'];

$questions = [];

// =========================================================================
// MODUL 1: 10 NIELSEN USABILITY HEURISTICS & FUNDAMENTAL HCI (20 Soal)
// =========================================================================
$heuristicsTopics = [
    [
        'Sebuah aplikasi mobile banking menampilkan progress bar persentase dan spinner saat pengguna mengunggah dokumen KTP. Prinsip 10 Usability Heuristics Jakob Nielsen manakah yang diterapkan',
        'Visibility of system status (Visibilitas status sistem)',
        ['Match between system and the real world', 'Error prevention', 'Flexibility and efficiency of use'],
        'Nielsen Norman Group (NN/g) mendefinisikan Heuristic #1 (Visibility of system status): sistem harus selalu memberi tahu pengguna tentang apa yang sedang terjadi melalui umpan balik yang tepat dan tepat waktu.',
    ],
    [
        'Ikon keranjang belanja (shopping cart) dan folder arsip di aplikasi e-commerce meniru benda fisik yang akrab di kehidupan sehari-hari pengguna. Heuristik Nielsen manakah yang mendasari prinsip desain ini',
        'Match between system and the real world (Kesesuaian antara sistem dan dunia nyata)',
        ['Consistency and standards', 'Recognition rather than recall', 'Aesthetic and minimalist design'],
        'Heuristic #2 (Match between system and the real world) menekankan bahwa sistem harus berbicara bahasa pengguna, menggunakan kata-kata, frasa, dan konsep yang akrab dengan dunia nyata pengguna, bukan istilah berorientasi sistem.',
    ],
    [
        'Fitur tombol "Undo", "Batal", dan link "Tutup (X)" pada dialog modal aplikasi memberikan keleluasaan bagi pengguna yang tidak sengaja melakukan kesalahan aksi. Prinsip heuristik apakah ini',
        'User control and freedom (Kontrol dan kebebasan pengguna)',
        ['Help users recognize, diagnose, and recover from errors', 'Error prevention', 'Flexibility and efficiency of use'],
        'Heuristic #3 (User control and freedom) menyatakan bahwa pengguna sering memilih fungsi sistem secara keliru dan membutuhkan "emergency exit" yang jelas untuk meninggalkan status yang tidak diinginkan tanpa proses berbelit-belit.',
    ],
    [
        'Sebuah platform web menggunakan warna biru bergaris bawah untuk link dan menempatkan logo di pojok kiri atas yang selalu mengarah ke halaman beranda. Heuristik manakah yang dipatuhi oleh konsistensi konvensi ini',
        'Consistency and standards (Konsistensi dan standar)',
        ['Recognition rather than recall', 'Match between system and real world', 'Aesthetic and minimalist design'],
        'Heuristic #4 (Consistency and standards) mengacu pada Jakob\'s Law: pengguna menghabiskan sebagian besar waktu mereka di situs lain, sehingga antarmuka harus mengikuti konvensi industri yang sudah baku agar tidak membingungkan.',
    ],
    [
        'Form registrasi menonaktifkan tombol submit dan memvalidasi format email secara real-time saat pengguna mengetik sebelum form dikirimkan, mencegah pengiriman data yang salah. Prinsip heuristik apakah ini',
        'Error prevention (Pencegahan kesalahan)',
        ['Help users recognize, diagnose, and recover from errors', 'Visibility of system status', 'Flexibility and efficiency of use'],
        'Heuristic #5 (Error prevention) menyatakan bahwa desain yang baik mencegah masalah terjadi sejak awal jauh lebih baik daripada pesan error yang informatif sekalipun, baik melalui konfirmasi atau batasan input yang cerdas.',
    ],
    [
        'Aplikasi pencarian hotel menampilkan riwayat pencarian terakhir dan daftar pilihan filter yang tampak langsung di layar daripada memaksa pengguna mengingat nama kota yang diketik kemarin. Prinsip manakah ini',
        'Recognition rather than recall (Pengenalan daripada mengingat kembali)',
        ['Consistency and standards', 'Aesthetic and minimalist design', 'User control and freedom'],
        'Heuristic #6 (Recognition rather than recall) menyatakan bahwa beban memori kerja pengguna (cognitive load) harus diminimalkan dengan membuat objek, aksi, dan opsi terlihat jelas, karena mengenali jauh lebih mudah daripada mengingat dari nol.',
    ],
    [
        'Aplikasi desain grafis menyediakan tombol klik menu untuk pemula dan keyboard shortcuts (hotkeys) tingkat lanjut bagi profesional untuk mempercepat pekerjaan. Prinsip heuristik apakah ini',
        'Flexibility and efficiency of use (Fleksibilitas dan efisiensi penggunaan)',
        ['Aesthetic and minimalist design', 'Visibility of system status', 'Match between system and the real world'],
        'Heuristic #7 (Flexibility and efficiency of use) menekankan akselerator (accelerators) yang tidak terlihat oleh pengguna pemula tetapi memungkinkan pengguna ahli mempercepat interaksi yang sering mereka lakukan.',
    ],
    [
        'Sebuah landing page menghapus banner promosi yang berkedip-kedip, teks paragraf yang tidak relevan, dan hanya menyajikan satu pesan utama dengan CTA yang jelas. Heuristik manakah yang diterapkan',
        'Aesthetic and minimalist design (Desain estetis dan minimalis)',
        ['Consistency and standards', 'Error prevention', 'Recognition rather than recall'],
        'Heuristic #8 (Aesthetic and minimalist design) menyatakan bahwa antarmuka tidak boleh memuat informasi yang tidak relevan atau jarang dibutuhkan, karena setiap unit informasi tambahan bersaing dan mengurangi visibilitas informasi penting.',
    ],
    [
        'Ketika pembayaran gagal, aplikasi menampilkan pesan: "Kartu kredit Anda kedaluwarsa. Silakan periksa masa berlaku kartu atau gunakan metode pembayaran lain", lengkap dengan tombol ganti metode. Prinsip manakah ini',
        'Help users recognize, diagnose, and recover from errors (Bantu pengguna mengenali, mendiagnosis, dan memulihkan error)',
        ['Error prevention', 'Visibility of system status', 'User control and freedom'],
        'Heuristic #9 menyatakan bahwa pesan error harus dinyatakan dalam bahasa manusia yang lugas (bukan kode error teknis seperti ERR_402), menunjukkan masalah secara presisi, dan secara konstruktif menawarkan solusi jalan keluar.',
    ],
    [
        'Meskipun sistem dirancang intuitif, aplikasi tetap menyediakan menu FAQ yang dapat dicari, panduan onboarding interaktif, dan tooltip penjelasan konteks. Heuristik manakah ini',
        'Help and documentation (Bantuan dan dokumentasi)',
        ['Recognition rather than recall', 'Flexibility and efficiency of use', 'Visibility of system status'],
        'Heuristic #10 (Help and documentation) menyatakan bahwa meskipun idealnya sistem dapat digunakan tanpa dokumentasi, penyediaan bantuan yang mudah dicari, berfokus pada tugas pengguna, dan ringkas tetap penting.',
    ],
    [
        'Dalam buku legendaris "The Design of Everyday Things", Don Norman memperkenalkan konsep "Affordance". Apakah definisi Affordance dalam desain antarmuka',
        'Hubungan antara sifat fisik/visual suatu objek dengan kemampuan manusia yang mengindikasikan bagaimana objek tersebut dapat digunakan (misal tombol 3D mengindikasikan dapat ditekan)',
        ['Jumlah klik minimum yang diperlukan untuk menyelesaikan transaksi', 'Warna latar belakang aplikasi yang disukai oleh mayoritas audiens', 'Kecepatan loading gambar pada perangkat berlayar kecil'],
        'Don Norman (1988/2013) mendefinisikan affordance sebagai petunjuk fundamental tentang pengoperasian sesuatu: keberadaan tombol visual yang tampak timbul memberikan affordance "bisa ditekan" kepada persepsi manusia.',
    ],
    [
        'Apa yang dimaksud dengan "Signifier" menurut Don Norman dalam Human-Computer Interaction (HCI)',
        'Tanda visual, suara, atau teks eksplisit yang memberitahu pengguna di mana dan aksi apa yang seharusnya dilakukan (misal label teks "Tarik" pada pintu atau ikon kaca pembesar pada kolom pencarian)',
        ['Tanda tangan digital pengembang aplikasi di footer website', 'Simbol hak cipta merek dagang perusahaan', 'Kode bahasa pemrograman yang digunakan untuk merender tombol'],
        'Don Norman menekankan bahwa signifier adalah sinyal komunikatif yang sengaja ditempatkan untuk memperjelas affordance: signifier menunjukkan lokasi dan sifat aksi yang diharapkan saat affordance fisik tidak cukup jelas.',
    ],
    [
        'Konsep "Feedback" dalam prinsip dasar interaksi Don Norman menyatakan bahwa',
        'Sistem harus segera mengirimkan informasi kembali kepada pengguna tentang hasil dari aksi yang baru saja dilakukan pengguna (misal bunyi klik, perubahan warna tombol, atau animasi sukses)',
        ['Pengguna wajib mengisi kuesioner ulasan bintang 5 setelah menggunakan aplikasi', 'Desainer harus meminta saran dari manajer proyek setiap minggu', 'Aplikasi harus selalu meminta konfirmasi password ulang setiap klik'],
        'Feedback memastikan pengguna mengetahui bahwa aksi mereka telah terdaftar oleh sistem. Ketiadaan feedback seketika memicu kebingungan dan pengulangan klik yang tidak perlu (Norman, The Design of Everyday Things).',
    ],
    [
        'Apa yang dimaksud dengan "Gulf of Execution" dalam model siklus aksi kognitif pengguna menurut Don Norman',
        'Kesenjangan antara niat tujuan pengguna dengan seberapa mudah sistem memfasilitasi dan mengizinkan tindakan untuk mencapai tujuan tersebut',
        ['Perbedaan antara estimasi waktu proyek dengan realisasi pengerjaan developer', 'Jarak geografis antara pengguna aplikasi dengan server data center', 'Kesenjangan antara jumlah pengguna pria dan wanita dalam aplikasi'],
        'Gulf of Execution mengukur seberapa intuitif antarmuka menerjemahkan niat mental pengguna ("Saya ingin membatalkan pesanan") ke dalam aksi fisik konkret yang disediakan antarmuka ("Di mana tombol batalnya?").',
    ],
    [
        'Sebaliknya, apa yang dimaksud dengan "Gulf of Evaluation" dalam interaksi manusia-komputer',
        'Tingkat kesulitan pengguna dalam mengamati dan menginterpretasikan status sistem saat ini untuk mengetahui apakah tujuannya telah tercapai setelah aksi dilakukan',
        ['Proses negosiasi gaji antara desainer UI/UX dengan divisi HRD', 'Perhitungan rasio profitabilitas aplikasi di akhir kuartal bisnis', 'Evaluasi performa baterai smartphone saat menjalankan aplikasi'],
        'Gulf of Evaluation mencerminkan upaya mental pengguna untuk memahami status sistem: antarmuka yang baik memberikan representasi status yang jelas sehingga pengguna seketika tahu apakah aksinya berhasil atau gagal.',
    ],
    [
        'Dalam standar internasional ISO 9241-210 (Human-centred design for interactive systems), prinsip utama perancangan berbasis manusia adalah',
        'Desain didasarkan pada pemahaman eksplisit tentang pengguna, tugas-tugas mereka, dan lingkungan penggunaannya, serta melibatkan evaluasi berulang (iterative design)',
        ['Desain ditentukan sepenuhnya oleh tren visual media sosial terkini tanpa riset pengguna', 'Pengembangan kode software harus selesai 100% sebelum antarmuka mulai digambar', 'Semua keputusan desain harus diputuskan oleh pimpinan tertinggi perusahaan'],
        'ISO 9241-210 menetapkan 6 prinsip HCD: memahami konteks penggunaan, keterlibatan aktif pengguna, evaluasi berbasis pengguna yang berulang (iterasi), mencakup seluruh pengalaman pengguna, dan tim multidisiplin.',
    ],
    [
        'Konsep "Mental Model" dalam psikologi kognitif UX merujuk pada',
        'Representasi mental dan pemahaman internal yang dimiliki pengguna mengenai bagaimana suatu sistem atau produk bekerja berdasarkan pengalaman masa lalu mereka',
        ['Diagram alur database yang dibuat oleh arsitek software backend', 'Tes kepribadian psikotes calon karyawan desain', 'Kode algoritma kecerdasan buatan di dalam server cloud'],
        'NN/g menjelaskan bahwa Mental Model adalah apa yang dipercayai pengguna tentang sistem. Kegagalan UX terjadi ketika sistem bekerja secara bertentangan dengan mental model yang telah terbangun di benak pengguna.',
    ],
    [
        'Apa perbedaan esensial antara konsep "User Interface" (UI) dan "User Experience" (UX)',
        'UI berfokus pada elemen visual dan sarana interaksi langsung (tipografi, warna, tombol, layout), sedangkan UX mencakup keseluruhan persepsi, emosi, kepuasan, dan efisiensi pengguna sebelum, selama, dan setelah berinteraksi dengan produk',
        ['UI hanya untuk aplikasi mobile, sedangkan UX hanya untuk website desktop', 'UI dikerjakan oleh developer, sedangkan UX dikerjakan oleh manajer marketing', 'Keduanya identik dan hanya berbeda istilah terjemahan bahasa'],
        'ISO 9241-210 mendefinisikan UX sebagai persepsi dan respons menyeluruh pengguna terhadap penggunaan produk. UI adalah subsistem visual yang menjadi jembatan bagi tercapainya pengalaman pengguna (UX) yang utuh.',
    ],
    [
        'Sebuah formulir checkout membagi proses pengisian data menjadi 3 langkah bertahap (Data Diri -> Alamat Pengiriman -> Pembayaran) alih-alih menampilkan 30 field sekaligus. Teknik ini menerapkan prinsip psikologi',
        'Chunking (membagi informasi kompleks menjadi unit-unit kecil yang mudah dicerna otak)',
        ['Gamification (memberikan poin reward pada setiap input)', 'Dark Pattern (menyembunyikan biaya tambahan di langkah akhir)', 'Skeuomorphism (meniru tekstur kertas formulir asli)'],
        'Prinsip kognitif Chunking (George A. Miller, 1956) membuktikan bahwa kapasitas memori kerja manusia terbatas. Memecah alur panjang menjadi tahapan modular (step-by-step wizard) menurunkan cognitive load dan drop-off rate.',
    ],
    [
        'Dalam UX Writing, panduan penulisan tombol Call to Action (CTA) yang paling efektif menurut riset Nielsen Norman Group adalah',
        'Menggunakan kata kerja aksi yang spesifik dan berorientasi hasil (misal: "Buka Tabungan Sekarang" daripada sekadar kata generik "Kirim" atau "Submit")',
        ['Menggunakan kalimat puitis panjang yang memancing rasa penasaran', 'Menuliskan seluruh huruf dalam format lowercase tanpa huruf kapital', 'Menghindari kata kerja dan hanya mencantumkan simbol emoji'],
        'Riset konversi NN/g membuktikan bahwa microcopy tombol CTA yang deskriptif dan berbasis aksi (action-oriented) memperjelas ekspektasi pengguna tentang apa yang terjadi berikutnya, melipatgandakan tingkat klik (CTR).',
    ],
];

// =========================================================================
// MODUL 2: GESTALT, VISUAL HIERARCHY, TYPOGRAPHY, COLOR, WCAG (20 Soal)
// =========================================================================
$gestaltA11yTopics = [
    [
        'Prinsip Gestalt "Law of Proximity" (Hukum Kedekatan) dalam desain antarmuka menyatakan bahwa',
        'Objek-objek yang posisinya saling berdekatan satu sama lain dipersepsikan oleh otak manusia sebagai satu kelompok atau memiliki hubungan keterkaitan fungsi',
        ['Objek yang memiliki warna sama selalu dianggap memiliki ukuran yang sama', 'Objek yang bergerak cepat akan menarik perhatian lebih lama', 'Pengguna selalu membaca antarmuka dari sudut kanan bawah ke kiri atas'],
        'Prinsip Psikologi Gestalt (Wertheimer, 1923) menetapkan Law of Proximity sebagai pilar visual grouping: jarak spasi (white space) yang lebih rapat antar-elemen menyatukan mereka secara mental (misal label form dengan field inputnya).',
    ],
    [
        'Prinsip Gestalt "Law of Similarity" (Hukum Kemiripan) paling sering diterapkan dalam UI untuk',
        'Menandakan fungsi atau status yang sejenis pada elemen visual (misal semua tombol aksi utama menggunakan warna dan bentuk tombol yang identik di seluruh halaman)',
        ['Membuat seluruh gambar di aplikasi memiliki resolusi yang berbeda-beda', 'Memaksa pengguna mengulang kata sandi yang sama', 'Menyamakan semua teks judul dan paragraf menjadi satu ukuran font'],
        'Law of Similarity menyatakan bahwa elemen yang berbagi karakteristik visual serupa (warna, bentuk, ukuran, orientasi) secara otomatis dipersepsikan memiliki makna, kategori, atau fungsi yang setara oleh persepsi manusia.',
    ],
    [
        'Ketika sebuah kartu antarmuka memiliki border luar dan latar belakang kontras yang mengelilingi teks dan tombol di dalamnya, prinsip Gestalt manakah yang bekerja menciptakan batas entitas mandiri tersebut',
        'Law of Common Region (Hukum Daerah Bersama)',
        ['Law of Continuity', 'Law of Closure', 'Law of Symmetry'],
        'Palmer (1992) merumuskan Law of Common Region: elemen-elemen yang dilingkupi di dalam batas wilayah visual tertutup yang sama (seperti kartu dengan background card atau garis border) akan dipersepsikan sebagai satu kelompok solid.',
    ],
    [
        'Prinsip Gestalt "Closure" (Penutupan) dimanfaatkan secara efektif dalam desain antarmuka mobile pada komponen',
        'Horizontal Carousel di mana kartu terakhir sengaja dipotong separuh (cut-off) di tepi layar untuk mengindikasikan bahwa deretan kartu dapat di-scroll horizontal',
        ['Tombol penutup aplikasi (close window)', 'Formulir login yang meminta konfirmasi captcha', 'Footer website yang selalu menempel di bagian paling bawah'],
        'Law of Closure menyatakan bahwa otak manusia secara alami melengkapi informasi yang terpotong menjadi bentuk utuh. Kartu visual yang terpotong di tepi layar memicu persepsi kontinuitas gulir tanpa memerlukan instruksi teks.',
    ],
    [
        'Pola pemindaian visual mata pengguna saat membaca halaman web yang padat teks (seperti artikel berita atau hasil pencarian Google) menurut riset eye-tracking Nielsen Norman Group umumnya membentuk pola',
        'F-Shaped Pattern (dua garis horizontal di bagian atas diikuti gerakan vertikal di sisi kiri)',
        ['Z-Shaped Pattern murni dari pojok ke pojok', 'Lingkaran konsentris spiral', 'Gerakan zig-zag diagonal acak'],
        'Riset eye-tracking NN/g (Jakob Nielsen, 2006) menemukan F-Pattern pada pemindaian teks web: pengguna membaca baris teratas secara penuh, lalu membaca sebagian baris kedua lebih pendek, dan hanya memindai tepi kiri ke bawah.',
    ],
    [
        'Sebaliknya, pada landing page visual dengan sedikit teks dan fokus pada banner hero serta tombol CTA utama, pola pemindaian mata pengguna cenderung mengikuti',
        'Z-Shaped Pattern (membaca header dari kiri ke kanan, diagonal ke kiri bawah, lalu horizontal ke kanan bawah menuju CTA)',
        ['F-Shaped Pattern vertikal', 'Pola piramida terbalik murni', 'Pola acak tanpa arah dominan'],
        'Z-Pattern berlaku untuk antarmuka berorientasi visual yang minim blok teks padat: mata pengguna secara alami menyapu dari logo kiri atas ke menu kanan, menyeberang diagonal ke konten kiri, dan berakhir di tombol aksi kanan bawah.',
    ],
    [
        'Standar Web Content Accessibility Guidelines (WCAG 2.1) Level AA menetapkan batas rasio kontras warna minimum (minimum contrast ratio) untuk teks normal (di bawah 18pt / 14pt bold) terhadap latar belakangnya adalah',
        '4.5 : 1',
        ['3.0 : 1', '7.0 : 1', '1.5 : 1'],
        'W3C WCAG 2.1 Success Criterion 1.4.3 mensyaratkan rasio kontras minimal 4.5:1 untuk teks standar (body copy) terhadap background agar dapat dibaca dengan jelas oleh pengguna dengan gangguan penglihatan rendah (low vision).',
    ],
    [
        'Sedangkan untuk teks berukuran besar (large text: minimal 18pt reguler atau 14pt bold) dan elemen antarmuka grafis (seperti icon dan border input), standar kontras WCAG Level AA mensyaratkan rasio minimal sebesar',
        '3.0 : 1',
        ['4.5 : 1', '2.0 : 1', '10.0 : 1'],
        'WCAG 2.1 SC 1.4.3 & 1.4.11 menetapkan kelonggaran rasio 3:1 untuk teks besar karena ukuran glyph yang lebih tebal lebih mudah dibedakan, serta untuk komponen grafis antarmuka penting (seperti checkbox border).',
    ],
    [
        'Aturan komposisi warna interior dan desain grafis "60-30-10 Rule" diterapkan dalam pembuatan palet warna UI aplikasi dengan proporsi pembagian',
        '60% warna dominan (background/netral), 30% warna sekunder/struktur (card/teks), dan 10% warna aksen (CTA/fokus interaktif)',
        ['60% warna merah cerah, 30% warna kuning, dan 10% warna biru neon', '60% warna tombol aksi, 30% warna background, dan 10% warna border', 'Semua warna dibagi rata secara merata 33.3%'],
        'Pola proporsi 60-30-10 menciptakan keseimbangan visual harmonis: mencegah antarmuka terlihat berantakan atau melelahkan mata dengan membatasi warna aksen berdaya tarik tinggi hanya pada elemen interaksi krusial (10%).',
    ],
    [
        'Mengapa mengandalkan warna semata (misal hanya warna merah tanpa teks atau ikon) untuk menandakan status error pada form input melanggar pedoman aksesibilitas WCAG (SC 1.4.1: Use of Color)',
        'Pengguna dengan kondisi buta warna (color blindness) seperti deuteranopia/protanopia tidak dapat membedakan perubahan warna merah dari warna abu-abu atau hijau',
        ['Warna merah menyebabkan monitor komputer lebih cepat panas', 'Warna merah dilarang oleh aturan periklanan digital internasional', 'Browser web versi lama tidak mendukung kode warna hex heksadesimal'],
        'WCAG 2.1 SC 1.4.1 melarang penggunaan warna sebagai satu-satunya indikator visual informasi, aksi, atau status. Desain wajib menyertakan indikator ganda seperti teks pesan error atau ikon peringatan (exclamation icon).',
    ],
    [
        'Dalam sistem tipografi antarmuka digital, konsep "Modular Scale" (seperti rasio Major Third 1.250 atau Perfect Fourth 1.333) digunakan untuk',
        'Menghitung hierarki ukuran font secara matematis dan harmonis dari ukuran body text terkecil hingga heading terbesar',
        ['Mengubah format file font dari TTF menjadi WOFF2 otomatis', 'Menentukan jumlah kata maksimum dalam satu paragraf artikel', 'Menghitung kecepatan membaca pengguna per menit'],
        'Typographic Modular Scale (Robert Bringhurst, The Elements of Typographic Style) menerapkan proporsi geometris yang konsisten untuk menghasilkan kontras ukuran antar level heading (H1, H2, H3, Body, Caption) yang tertata rapi.',
    ],
    [
        'Rekomendasi panjang baris teks (line length / measure) yang paling optimal untuk kenyamanan membaca artikel di layar monitor desktop adalah',
        '45 hingga 75 karakter per baris (termasuk spasi)',
        ['120 hingga 200 karakter per baris', '10 hingga 20 karakter per baris', 'Tidak terbatas mengikuti lebar penuh monitor ultra-wide'],
        'Penelitian ergonomi tipografi dan pedoman WCAG merekomendasikan batas 45-75 karakter (sekitar 8-12 kata) per baris: baris yang terlalu panjang membuat mata kesulitan berpindah ke baris berikutnya, sedangkan baris terlalu pendek memutus ritme membaca.',
    ],
    [
        'Properti tipografi "Line Height" (Leading) yang ideal untuk body text antarmuka digital agar nyaman dibaca tanpa terasa sempit umumnya diatur pada rentang',
        '140% hingga 160% (1.4 - 1.6 kali) dari ukuran font teks bersangkutan',
        ['90% hingga 100% dari ukuran font', '250% hingga 300% dari ukuran font', 'Tepat bernilai 10 pixel tanpa peduli ukuran font'],
        'WCAG 2.1 Success Criterion 1.4.12 dan standar desain UI modern menyarankan line-height setidaknya 1.5 kali ukuran font untuk teks paragraf guna memberikan ruang bernapas yang cukup antar baris.',
    ],
    [
        'Dalam tipografi web, apa perbedaan mendasar antara font bertipe "Serif" dan font bertipe "Sans-Serif"',
        'Font Serif memiliki kait/garis kecil pemanis (serifs) di ujung guratan huruf (seperti Times New Roman), sedangkan Sans-Serif tidak memiliki kait dan berpenampilan bersih modern (seperti Inter atau Roboto)',
        ['Font Serif hanya dapat digunakan pada sistem operasi Linux', 'Font Sans-Serif tidak mendukung huruf kapital', 'Font Serif selalu memiliki ukuran file yang lebih besar'],
        'Tipografi digital modern umumnya memfavoritkan font Sans-Serif untuk body text antarmuka aplikasi karena keterbacaan (legibility) yang superior pada layar beresolusi sedang dan berbagai ukuran perangkat mobile.',
    ],
    [
        'Teknik visual hierarchy "Scale and Proportion" (Skala dan Proporsi) mengarahkan fokus perhatian pengguna pertama kali ke elemen yang',
        'Memiliki ukuran relatif paling besar dan kontras visual paling dominan dibandingkan elemen-elemen di sekitarnya',
        ['Terletak paling dekat dengan tombol navigasi back di browser', 'Ditulis menggunakan huruf miring (italics) berukuran kecil', 'Memiliki warna latar belakang transparan'],
        'Prinsip Visual Hierarchy menetapkan bahwa mata manusia secara naluriah terpikat pada elemen yang menonjol dalam skala ukuran, berat (weight), dan kontras, menetapkan urutan membaca informasi yang direncanakan desainer.',
    ],
    [
        'Penggunaan ruang kosong atau "White Space" (Negative Space) yang proporsional di sekitar komponen antarmuka memberikan manfaat krusial berupa',
        'Mengurangi beban kognitif (cognitive load), meningkatkan keterbacaan, dan memberikan penekanan visual tanpa perlu menambah elemen grafis baru',
        ['Mengurangi penggunaan kuota data internet pengguna secara drastis', 'Mempercepat kecepatan download file JavaScript aplikasi', 'Menghilangkan kebutuhan akan media query responsif'],
        'Riset interaksi HCI membuktikan bahwa white space yang tepat meningkatkan pemahaman bacaan hingga 20% dan mencegah antarmuka terasa sesak (cluttered), meningkatkan persepsi kualitas dan kemewahan produk.',
    ],
    [
        'Metode "Dark Mode" yang ramah bagi mata pengguna tidak boleh menggunakan warna latar belakang hitam murni (#000000) pekat dengan teks putih murni (#FFFFFF) karena',
        'Kontras 100% yang ekstrem menyebabkan efek halasi (visual halation) dan ketegangan otot mata; disarankan menggunakan dark gray (#121212) dan off-white',
        ['Layar monitor OLED akan meledak jika merender warna hitam murni', 'Warna hitam murni tidak dapat dibaca oleh algoritma Google SEO', 'Warna putih murni tidak didukung oleh browser Safari di iOS'],
        'Google Material Design Dark Theme Guidelines secara tegas menganjurkan penggunaan dark gray (misal #121212) daripada hitam murni untuk mengurangi kontras tajam yang melelahkan mata dan memungkinkan elevasi kedalaman bayangan (shadow/surface overlay) tetap terlihat.',
    ],
    [
        'Dalam desain ikonografi antarmuka, prinsip "Visual Weight" (Bobot Visual) mengharuskan desainer untuk',
        'Menyesuaikan ukuran fisik ikon dengan bentuk berbeda (misal lingkaran vs bujur sangkar) agar tampak memiliki bobot optik yang seimbang secara visual di mata manusia (optical balancing)',
        ['Menimbang berat fisik file ikon dalam satuan kilobyte', 'Memastikan semua ikon selalu digambar di dalam kanvas persegi 100x100 cm', 'Memberikan efek bayangan tebal pada setiap ikon tanpa terkecuali'],
        'Optical balancing adalah keahlian fundamental UI design: bentuk geometris berbeda (seperti lingkaran dan kotak) dengan bounding box matematis yang sama akan tampak berbeda ukuran di mata manusia, sehingga memerlukan koreksi optik.',
    ],
    [
        'Apa yang dimaksud dengan "Color Blindness Simulation" (Protanopia, Deuteranopia, Tritanopia) yang wajib diuji dalam tahap perancangan UI',
        'Pengujian untuk memastikan antarmuka tetap informatif dan dapat dibedakan bagi pengguna dengan berbagai defisiensi penglihatan warna merah-hijau atau biru-kuning',
        ['Simulasi tampilan website saat monitor komputer dalam mode hemat daya', 'Pewarnaan otomatis website menjadi hitam putih saat malam hari', 'Pengujian kecerahan layar di bawah terik sinar matahari langsung'],
        'Sekitar 8% pria dan 0.5% wanita di dunia memiliki kondisi defisiensi penglihatan warna (Color Vision Deficiency). Desainer profesional menggunakan plugin simulasi color blindness untuk memastikan palet warna lulus uji keterbacaan universal.',
    ],
    [
        'Dalam tipografi web, apa fungsi dari properti OpenType "Kerning" dan "Tracking" (Letter-spacing)',
        'Kerning mengatur jarak antar pasangan huruf spesifik yang berdampingan (seperti A dan V), sedangkan Tracking mengatur jarak spasi seragam di seluruh rentang kata atau kalimat',
        ['Kerning mengatur tinggi huruf kapital, sedangkan Tracking mengatur kemiringan huruf', 'Keduanya mengatur warna gradasi pada font antarmuka', 'Tracking digunakan untuk melacak lokasi GPS pengguna aplikasi'],
        'Bringhurst (The Elements of Typographic Style) menjelaskan bahwa kerning mengoreksi celah canggung antara bentuk huruf tertentu, sedangkan letter-spacing (tracking) diterapkan secara makro (misal tracking renggang pada teks all-caps).',
    ],
];

// =========================================================================
// MODUL 3: DESIGN SYSTEM, ATOMIC DESIGN, COMPONENTS, FIGMA (20 Soal)
// =========================================================================
$designSystemTopics = [
    [
        'Metodologi "Atomic Design" yang dirumuskan oleh Brad Frost membagi hierarki komponen antarmuka menjadi lima tingkat berurutan, yaitu',
        'Atoms, Molecules, Organisms, Templates, dan Pages',
        ['Bits, Bytes, Megabytes, Gigabytes, dan Terabytes', 'Buttons, Inputs, Cards, Layouts, dan Themes', 'Lines, Shapes, Colors, Textures, dan Layouts'],
        'Brad Frost (Atomic Design, 2013) meminjam analogi kimia untuk menyusun komponen modular dari unit terkecil yang tak dapat dipecah lagi (Atoms: tombol/label) hingga implementasi konten spesifik nyata (Pages).',
    ],
    [
        'Menurut metodologi Atomic Design, contoh entitas antarmuka manakah yang tepat dikategorikan sebagai "Molecule"',
        'Sebuah Form Search yang terdiri dari gabungan Label (Atom), Input Field (Atom), dan Tombol Cari (Atom) yang bekerja bersama untuk satu tujuan fungsional sederhana',
        ['Satu buah token warna primer heksadesimal #0f3460 murni', 'Sebuah Header navigasi lengkap dengan logo, menu dropdown, search bar, dan user profile widget', 'Halaman checkout transaksi e-commerce lengkap dengan data dummy'],
        'Molekul adalah kombinasi sederhana dari dua atau lebih atom yang berfungsi bersama sebagai unit operasional terkecil (misal: input form search yang menggabungkan label, input teks, dan icon button).',
    ],
    [
        'Sedangkan entitas antarmuka kompleks seperti "Global Navigation Bar" yang menggabungkan logo brand, search molecule, menu navigasi, dan user notification widget dikategorikan sebagai',
        'Organism',
        ['Template', 'Atom', 'Page'],
        'Organisme adalah komponen antarmuka yang relatif kompleks dan mandiri, tersusun dari kelompok molekul dan/atau atom lain, membentuk bagian definitif dari antarmuka (misal Header, Footer, Product Card Grid).',
    ],
    [
        'Dalam arsitektur Design System modern, apa yang dimaksud dengan "Design Tokens"',
        'Nilai-nilai atomik platform-agnostic (seperti kode warna, ukuran spasi, nilai radius, font-family) yang disimpan dalam format netral (seperti JSON) dan disinkronkan otomatis ke berbagai platform (Web CSS, iOS Swift, Android Kotlin)',
        ['Koin kripto yang digunakan untuk membeli lisensi software desain Figma', 'Token autentikasi login yang dimasukkan desainer untuk membuka file proyek', 'Kupon diskon yang diberikan desainer kepada pengguna baru aplikasi'],
        'Jina Anne & Salesforce Design (pencipta konsep Design Tokens) menetapkan tokens sebagai single source of truth untuk atribut desain, memungkinkan sinkronisasi otomatis antara repository desain dan repository kode engineering.',
    ],
    [
        'Dalam struktur hierarki Design Tokens, apa perbedaan antara "Global Tokens" (Core/Primitive), "Alias Tokens" (Semantic), dan "Component-specific Tokens"',
        'Global Tokens menyimpan nilai mentah (color-blue-500: #2563eb), Semantic Tokens memberi konteks fungsi (color-action-primary: {color-blue-500}), dan Component Tokens mengikat nilai ke komponen spesifik (button-primary-bg: {color-action-primary})',
        ['Global Tokens hanya digunakan di Amerika, sedangkan Semantic Tokens di Asia', 'Component Tokens dapat dimodifikasi oleh pengguna akhir di browser klien', 'Ketiganya identik dan hanya berbeda bahasa pemrograman pendukung'],
        'Struktur 3-tier token (Nathan Curtis, EightShapes) adalah standar industri enterprise: layer semantik memungkinkan pengubahan tema (Theming / Dark Mode) secara instan tanpa menyentuh ratusan komponen individual.',
    ],
    [
        'Fitur "Auto Layout" pada software desain industri seperti Figma meniru konsep layout engineering web berupa',
        'CSS Flexbox (mengatur perataan horizontal/vertikal, padding, gap spasi antar elemen, dan perilaku responsive resizing)',
        ['CSS 3D Perspective Animation', 'HTML Canvas WebGL Engine', 'Database Relational Mapping'],
        'Figma Auto Layout dibangun di atas model mental CSS Flexbox (direction, align-items, justify-content, padding, gap), memungkinkan desainer membuat frame yang secara otomatis melar dan menyusut mengikuti panjang teks konten dinamis.',
    ],
    [
        'Dalam Figma Component Architecture, fitur "Component Variants" digunakan untuk',
        'Mengelompokkan variasi status dari satu komponen yang sama (seperti Size: Small/Medium/Large, State: Default/Hover/Active/Disabled) ke dalam satu wadah komponen tunggal',
        ['Menduplikasi file desain ke dalam 5 folder terpisah di Google Drive', 'Mengubah desain antarmuka menjadi gambar bitmap berukuran kecil', 'Menghubungkan file desain langsung ke printer cetak offset'],
        'Component Variants menyederhanakan pustaka desain (component library): desainer tidak perlu membuat 20 komponen tombol terpisah, melainkan satu komponen Button dengan kombinasi properti varian yang rapi.',
    ],
    [
        'Fitur Figma "Component Properties" (Boolean, Text, Instance Swap, Variant) memberikan keuntungan kolaborasi engineering berupa',
        'Mengurangi jumlah varian layer visual berlebih dan menyelaraskan penamaan props desain dengan penamaan props komponen pada kode frontend (React/Vue)',
        ['Menghapus seluruh file CSS dari server pengujian', 'Mengizinkan desainer mengedit kode database secara langsung dari Figma', 'Menjalankan testing unit JavaScript di dalam kanvas desain'],
        'Figma Component Properties menjembatani jurang pemisah antara desain dan kode: props boolean di Figma mencerminkan boolean props pada React (misal: hasIcon: true), mengurangi cognitive overhead saat handoff developer.',
    ],
    [
        'Apa yang dimaksud dengan "Design System Governance" dalam pengelolaan produk digital skala besar',
        'Proses terstruktur, aturan, dan tim pengelola yang bertugas meninjau, menyetujui, memperbarui, dan memelihara komponen baru agar sistem tetap konsisten dan tidak mengalami fragmentasi',
        ['Instalasi antivirus pada komputer seluruh tim desainer grafis', 'Pemeriksaan jam kerja kehadiran harian anggota divisi desain', 'Pemberian sanksi denda bagi desainer yang terlambat mengumpulkan tugas'],
        'Nathan Curtis (EightShapes) & Alla Kholmatova (Design Systems) menekankan Governance: tanpa alur kontribusi dan review yang jelas, design system akan cepat usang dan mengalami "component bloat" (duplikasi tak terkendali).',
    ],
    [
        'Dalam penerapan Design System, konsep "Single Source of Truth" (SSOT) paling ideal dicapai ketika',
        'Tokens dan aset desain dikelola di repositori terpusat (misal via GitHub Actions) yang secara otomatis mengekspor perubahan ke library Figma dan package kode frontend (NPM package)',
        ['Desainer menyimpan semua master desain di flashdisk pimpinan divisi', 'Developer menyalin kode warna secara manual menggunakan pipet eyedropper', 'Pimpinan divisi menuliskan seluruh aturan styling di papan tulis kantor'],
        'Enterprise Design Systems (seperti Shopify Polaris atau IBM Carbon) mengotomatisasi SSOT via CI/CD pipeline: desainer memperbarui token di Figma/Tokens Studio, memicu Pull Request ke repository kode frontend secara instan.',
    ],
    [
        'Sistem grid "8-Point Grid System" (atau 4-Point Subgrid) sangat populer diadopsi dalam UI Design System modern karena',
        'Sebagian besar resolusi layar digital dapat dibagi rata dengan angka 8, menyederhanakan perhitungan spasi, margin, dan padding secara konsisten di seluruh breakpoint perangkat',
        ['Angka 8 dianggap membawa keberuntungan menurut mitologi kuno', 'Software Figma hanya dapat mengenali angka kelipatan 8', 'CSS tidak dapat merender angka spasi ganjil seperti 5px atau 7px'],
        '8-point grid (Google Material Design & Apple HIG) menyederhanakan keputusan desainer dan developer: menghilangkan keraguan menentukan ukuran spasi (8, 16, 24, 32, 40, 48px) dan merender tajam pada kepadatan layar (1x, 2x, 3x).',
    ],
    [
        'Dalam pembuatan Button Component pada design system, status manakah yang wajib didefinisikan secara lengkap untuk menjamin kelengkapan interaksi',
        'Default, Hover, Focus, Active/Pressed, Disabled, dan Loading/Processing',
        ['Hanya status Default dan Deleted', 'Status Morning, Afternoon, dan Night', 'Status Online dan Offline murni'],
        'Kelengkapan status tombol (Interaction States) adalah prasyarat mutlak antarmuka profesional: memandu pengguna melalui berbagai moda interaksi (mouse hover, keyboard focus ring, touch active, disabled validation, async loading spinner).',
    ],
    [
        'Pola arsitektur form input "Floating Label" (dikenal pada Google Material Design) memiliki kelemahan aksesibilitas dan usability yang sering dikritik oleh Nielsen Norman Group berupa',
        'Teks label yang mengecil saat fokus dapat sulit dibaca oleh pengguna dengan daya lihat rendah, dan ketiadaan ruang kosong membuat pengguna bingung membedakan label dengan nilai yang sudah terisi',
        ['Label melayang menyebabkan aplikasi boros baterai smartphone', 'Label melayang tidak dapat diterjemahkan ke dalam bahasa Indonesia', 'Label melayang merusak memori RAM server backend'],
        'Riset usability NN/g (Katie Sherwin) menemukan bahwa Floating Labels sering membingungkan pengguna: saat field terisi, label mini sulit dibaca sekilas untuk verifikasi error, dan ketergantungan pada animasi memicu cognitive load.',
    ],
    [
        'Pada dokumentasi Design System, pedoman penggunaan "Do\'s and Don\'ts" berfungsi untuk',
        'Memberikan contoh visual konkret mengenai cara penggunaan komponen yang benar dan kesalahan umum yang harus dihindari oleh tim produk',
        ['Mencatat daftar absensi harian anggota tim desain', 'Melarang developer melakukan refactoring kode tanpa izin', 'Menyimpan riwayat error log dari server pengujian'],
        'Shopify Polaris & Atlassian Design System menyertakan Do and Don\'t guidelines sebagai panduan kontekstual esensial: mengajarkan filosofi di balik komponen dan mencegah penyalahgunaan visual dalam skenario riil.',
    ],
    [
        'Komponen "Modal Dialog" berbeda dengan "Non-modal Popover / Tooltip" dalam hal perilaku interaksi pengguna karena',
        'Modal Dialog menciptakan focus trap dan overlay yang memblokir interaksi dengan sisa halaman lain hingga pengguna menyelesaikan atau menutup dialog tersebut',
        ['Modal Dialog tidak dapat ditutup menggunakan tombol escape di keyboard', 'Popover selalu ditampilkan di tengah layar monitor secara permanen', 'Modal Dialog hanya boleh memuat teks maksimal 5 kata'],
        'W3C WAI-ARIA Modal Dialog Pattern menetapkan bahwa modal bersifat mengganggu (disruptive) dan menuntut perhatian penuh pengguna, menjebak fokus keyboard (focus trap) di dalam dialog hingga ditutup.',
    ],
    [
        'Komponen notifikasi "Toast" (Snackbar) dirancang khusus untuk menyampaikan informasi jenis',
        'Umpan balik pasif bersurat ringan yang bersifat sementara (auto-dismiss) tanpa memutus alur kerja utama pengguna (misal: "Pesan berhasil disimpan")',
        ['Peringatan kritis yang membutuhkan keputusan persetujuan legal segera dari pengguna', 'Daftar panjang riwayat transaksi perbankan selama 1 tahun terakhir', 'Pertanyaan konfirmasi penghapusan akun database permanen'],
        'Toast/Snackbar memberikan umpan balik asinkron berprioritas rendah-sedang: muncul di tepi layar dan menghilang otomatis setelah beberapa detik tanpa memaksa pengguna mengklik tombol penutup.',
    ],
    [
        'Dalam desain antarmuka berbasis kartu (Card-based UI), prinsip batasan informasi (Container Boundary) mengharuskan kartu untuk',
        'Mengemas informasi yang saling terkait tentang satu topik tunggal menjadi satu unit modular yang independen dan dapat diposisikan ulang secara fleksibel',
        ['Memuat setidaknya 10 tombol aksi yang berbeda di setiap kartu', 'Menggunakan gambar berukuran minimal 4K Ultra HD', 'Memiliki ukuran tinggi kartu yang selalu sama di semua resolusi'],
        'Material Design Card Guidelines mendefinisikan Card sebagai titik masuk (entry point) informasi modular: mengelompokkan elemen terkait (gambar, judul, deskripsi, aksi) tentang satu subjek tunggal secara terisolasi.',
    ],
    [
        'Fitur "Skeleton Screen" pada antarmuka pemuatan data (loading state) lebih unggul daripada animasi spinner lingkaran biasa karena',
        'Memberikan ilusi kecepatan pemuatan (perceived performance) yang lebih cepat dengan memproyeksikan tata letak struktur konten yang akan segera muncul',
        ['Secara fisik mempercepat koneksi internet pengguna sebesar dua kali lipat', 'Mencegah server backend mengalami kehabisan memori komputasi', 'Mengurangi konsumsi daya baterai perangkat hingga 90%'],
        'Studi psikologi UX Luke Wroblewski & Bill Chung menunjukkan bahwa Skeleton Screen mengurangi perceived waiting time: pengguna melihat kerangka konten yang terstruktur sehingga fokus pada progres, bukan pada rasa bosan menunggu spinner berputar.',
    ],
    [
        'Apa yang dimaksud dengan "Zero State" atau "Empty State" dalam perancangan antarmuka aplikasi',
        'Kondisi layar antarmuka ketika belum ada data yang tersedia untuk ditampilkan (misal keranjang belanja baru kosong atau kotak masuk email belum ada pesan)',
        ['Kondisi aplikasi saat mengalami crash total dan layar berwarna putih', 'Status baterai smartphone pengguna saat berada di level 0%', 'Tingkat error rate aplikasi backend yang mencapai angka nol'],
        'Empty State adalah momen onboarding krusial (NN/g): antarmuka tidak boleh hanya membiarkan layar kosong melompong, melainkan harus menyajikan ilustrasi ramah, penjelasan mengapa kosong, dan tombol CTA untuk memulai aksi pertama.',
    ],
    [
        'Dalam proses "Design Handoff" ke tim engineering, informasi apakah yang wajib disediakan oleh desainer selain tampilan visual statis',
        'Spesifikasi interaksi mikro, status komponen (states), perilaku responsive (breakpoint constraints), mapping token variabel, dan aturan penanganan error / edge cases',
        ['Password akun email pribadi seluruh anggota tim desain', 'Estimasi harga jual saham perusahaan di pasar modal', 'Surat rekomendasi kerja untuk developer frontend'],
        'Design-to-Code Handoff yang matang (Airbnb & Spotify Design Practices) mencakup aset desain, panduan edge-case (teks terpanjang, teks kosong, kegagalan koneksi), dan spesifikasi transisi animasi agar hasil implementasi pixel-perfect.',
    ],
];

// =========================================================================
// MODUL 4: INFORMATION ARCHITECTURE, COGNITIVE LAWS, INTERACTION (20 Soal)
// =========================================================================
$iaCognitiveTopics = [
    [
        'Hukum UX terkenal "Fitts\'s Law" menyatakan bahwa waktu yang dibutuhkan pengguna untuk mengarahkan kursor/jari ke target sasaran dipengaruhi oleh',
        'Jarak tempuh ke target dan ukuran fisik target sasaran (semakin dekat dan besar target, semakin cepat dan mudah dicapai)',
        ['Warna kecerahan tombol dan font tipografi yang digunakan', 'Kecepatan koneksi internet pengguna pada saat mengklik tombol', 'Jumlah kata yang terdapat di dalam tombol navigasi'],
        'Paul Fitts (1954, J. Experimental Psychology) merumuskan Fitts\'s Law: waktu pergerakan merupakan fungsi logaritmik dari rasio jarak dibagi lebar target (MT = a + b log2(2D/W)). Di UI, tombol penting dibuat besar dan diletakkan di area mudah dijangkau jari (thumb zone).',
    ],
    [
        'Hukum UX "Hick\'s Law" (Hick-Hyman Law) menyatakan bahwa waktu yang dibutuhkan seseorang untuk mengambil keputusan',
        'Meningkat secara logaritmik seiring dengan bertambahnya jumlah dan kompleksitas pilihan yang disajikan',
        ['Selalu konstan tidak peduli berapa banyak pilihan yang tersedia', 'Menurun secara drastis saat jumlah pilihan melebihi 100 pilihan', 'Hanya dipengaruhi oleh tingkat kecerdasan akademik pengguna'],
        'William Edmund Hick & Ray Hyman (1952) menemukan bahwa waktu reaksi bertambah dengan bertambahnya alternatif pilihan (T = b · log2(n + 1)). Dalam UX, ini mendasari aturan penyederhanaan opsi navigasi dan formulir bertingkat.',
    ],
    [
        'Hukum kognitif "Miller\'s Law" (The Magical Number Seven, Plus or Minus Two) dalam psikologi kognitif menyatakan bahwa',
        'Rata-rata manusia hanya mampu menyimpan 7 (± 2) butir informasi (chunks) di dalam memori kerja jangka pendek (working memory) mereka pada satu waktu',
        ['Sebuah website harus selalu memiliki tepat 7 halaman menu navigasi utama', 'Pengguna akan menghapus aplikasi jika waktu loading melebihi 7 detik', 'Setiap form wajib memiliki minimal 7 input pertanyaan'],
        'George A. Miller (1956, Harvard University) merumuskan kapasitas memori kerja manusia sebesar 7±2 chunks. Desainer UI menerapkan ini untuk membatasi jumlah item pada navigasi bar dan memecah kelompok nomor telepon menjadi 3-4 digit.',
    ],
    [
        'Hukum UX "Jakob\'s Law of Internet User Experience" (Jakob Nielsen) menyatakan bahwa',
        'Pengguna menghabiskan sebagian besar waktu mereka di situs-situs lain, sehingga mereka mengharapkan situs Anda berfungsi dan bernavigasi dengan cara yang sama seperti situs yang sudah mereka kenal',
        ['Semua website harus dirancang menggunakan warna biru seperti Facebook', 'Pengguna internet modern tidak pernah membaca teks lebih dari 3 detik', 'Website yang paling sukses adalah website yang memiliki fitur paling banyak'],
        'Jakob Nielsen (NN/g) menegaskan bahwa orisinalitas radikal pada konvensi navigasi standar (misal memindahkan search bar ke pojok bawah) memicu friksi kognitif: ikuti standar industri agar pengguna tidak perlu belajar ulang cara pakai.',
    ],
    [
        'Prinsip kognitif "Serial Position Effect" (kombinasi Primacy Effect dan Recency Effect) menunjukkan kecenderungan pengguna untuk',
        'Paling baik mengingat item yang berada di urutan paling awal (primacy) dan paling akhir (recency) dari sebuah daftar atau deretan navigasi',
        ['Hanya mengingat item yang berada persis di tengah-tengah daftar menu', 'Melupakan seluruh informasi segera setelah menutup aplikasi', 'Mengingat semua urutan item secara sempurna tanpa bias posisi'],
        'Hermann Ebbinghaus merumuskan Serial Position Effect: memori kerja memprioritaskan elemen pembuka dan penutup. Dalam UX, item menu yang paling krusial selalu diletakkan di posisi paling kiri/kanan pada navigation bar.',
    ],
    [
        'Hukum UX "Tesler\'s Law" (The Law of Conservation of Complexity) menyatakan bahwa',
        'Untuk setiap sistem, terdapat tingkat kompleksitas minimum bawaan tertentu yang tidak dapat direduksi lagi, melainkan hanya dapat dipindahkan (antara developer/desainer atau pengguna)',
        ['Setiap perangkat lunak pasti akan mengalami penambahan bug seiring waktu', 'Kompleksitas database selalu meningkat dua kali lipat setiap dua tahun', 'Semua kode backend harus ditulis dalam paradigma pemrograman fungsional'],
        'Larry Tesler (Xerox PARC) menyatakan bahwa kompleksitas tidak bisa dihilangkan: jika desainer dan engineer bekerja keras menyerap kompleksitas di backend/UI, pengguna akan menikmati kemudahan interaksi yang mulus (simplexity).',
    ],
    [
        'Fenomena psikologis "Von Restorff Effect" (The Isolation Effect) dimanfaatkan dalam desain halaman harga (Pricing Table) ketika',
        'Satu paket langganan (misal "Pro Plan") dibuat paling menonjol secara visual (warna berbeda, ukuran lebih besar, badge "Most Popular") agar paling diingat dan dipilih pengguna',
        ['Semua paket harga disembunyikan di balik menu dropdown rahasia', 'Harga paket diubah-ubah secara acak setiap kali halaman direfresh', 'Deskripsi fitur paket ditulis menggunakan font berukuran sangat kecil'],
        'Hedwig von Restorff (1933) menemukan bahwa ketika beberapa objek homogen disajikan, objek yang berbeda secara visual dari kelompoknya akan paling mudah diingat dan menarik perhatian mata.',
    ],
    [
        'Efek psikologis "Doherty Threshold" dalam interaksi manusia-komputer menetapkan bahwa produktivitas dan kepuasan pengguna meningkat pesat ketika interaksi antara komputer dan manusia berlangsung dalam jeda waktu',
        'Kurang dari 400 milidetik (< 0.4 detik)',
        ['Antara 5 hingga 10 detik', 'Tepat 1 menit penuh', 'Lebih dari 3 detik'],
        'Walter J. Doherty & Ahrvind J. Thadani (IBM, 1982) membuktikan bahwa ketika respons sistem berada di bawah ambang 400 ms, ritme interaksi pengguna tidak terputus dan pengguna merasa memegang kendali penuh atas sistem.',
    ],
    [
        'Dalam metode riset Information Architecture (IA), teknik "Card Sorting" jenis "Open Card Sorting" dilakukan dengan cara',
        'Pengguna mengelompokkan kartu-kartu topik konten ke dalam kategori yang mereka buat dan beri nama sendiri secara bebas sesuai logika mental mereka',
        ['Pengguna memasukkan kartu topik ke dalam kategori yang sudah ditentukan sebelumnya oleh tim desainer', 'Pengguna bermain kartu remi untuk menentukan pemenang proyek desain', 'Desainer mengurutkan kartu berdasarkan abjad nama karyawan'],
        'Rosenfeld, Morville, & Arango (Information Architecture for the Web) menjelaskan Open Card Sorting untuk menemukan pola mental model pengguna: kategori dan label nama dirumuskan murni dari perspektif audiens target.',
    ],
    [
        'Sebaliknya, teknik "Closed Card Sorting" paling ideal digunakan ketika desainer ingin',
        'Mengevaluasi dan menguji seberapa tepat struktur hierarki kategori yang telah ada dalam menampung konten-konten baru dari sudut pandang pemahaman pengguna',
        ['Mencari ide nama kategori baru dari nol', 'Menghitung waktu loading database aplikasi', 'Memilih palet warna ilustrasi aplikasi'],
        'Closed Card Sorting memberikan kategori yang sudah mapan (pre-defined categories) kepada partisipan untuk menguji apakah pengguna dapat memetakan kartu topik ke kategori yang tepat secara konsisten.',
    ],
    [
        'Metode pengujian struktur navigasi "Tree Testing" (sering disebut Reverse Card Sorting) dilakukan dengan cara',
        'Meminta pengguna mencari lokasi item tertentu hanya dengan menelusuri bagan pohon teks kategori bertingkat tanpa elemen visual desain UI untuk menguji kemudahan navigasi hierarkis',
        ['Meminta pengguna menggambar sketsa pohon keluarga di kanvas kertas', 'Memeriksa kestabilan server backend saat menerima lonjakan trafik', 'Melihat apakah pengguna menyukai kombinasi warna hijau di website'],
        'Tree Testing mengisolasi Information Architecture dari gangguan estetika grafis: mengukur secara objektif apakah struktur menu dan pelabelan taksonomi berhasil memandu pengguna menemukan konten yang dicari.',
    ],
    [
        'Dalam navigasi antarmuka, komponen "Breadcrumbs" (remah roti) memiliki peran krusial bagi navigasi pengguna karena',
        'Menunjukkan hierarki posisi halaman saat ini dalam struktur situs secara keseluruhan dan memungkinkan navigasi satu-klik kembali ke tingkat induk sebelumnya',
        ['Menghapus riwayat pencarian pengguna di browser secara otomatis', 'Menampilkan resep pembuatan roti dan kue interaktif', 'Menyimpan token autentikasi pengguna ke dalam cookie'],
        'NN/g menyarankan Breadcrumbs untuk situs hierarkis bertingkat (seperti e-commerce): bertindak sebagai secondary navigation yang memperjelas konteks lokasi pengguna tanpa menggantikan menu navigasi utama.',
    ],
    [
        'Pada desain aplikasi smartphone, konsep "Thumb Zone" yang dipopulerkan oleh Steven Hoober mengelompokkan area layar perangkat mobile berdasarkan',
        'Tingkat kemudahan dan kenyamanan jangkauan ibu jari tangan pengguna saat menggenggam perangkat dengan satu tangan (Natural, Stretch, Hard to Reach)',
        ['Kekuatan daya tekan sensor sidik jari perangkat', 'Kepadatan pixel layar per inci persegi (PPI)', 'Kecepatan reaksi sensor gyroscope saat perangkat diputar'],
        'Steven Hoober (Designing for Touch, 2013) memetakan 75% interaksi smartphone dilakukan dengan satu ibu jari: area bawah layar adalah "Natural Zone" yang paling mudah dijangkau, ideal untuk navigasi bottom bar dan tombol aksi utama.',
    ],
    [
        'Pola navigasi mobile "Bottom Navigation Bar" umumnya dibatasi untuk memuat maksimal',
        '3 hingga 5 menu destinasi utama',
        ['10 hingga 15 menu destinasi', 'Hanya 1 tombol saja', 'Tidak terbatas dengan scrolling horizontal'],
        'Google Material Design & Apple Human Interface Guidelines secara konsisten membatasi Bottom Nav Bar pada 3-5 item: melebihi 5 item membuat target sentuh terlalu sempit (melanggar Fitts\'s Law) dan membebani memori kerja visual.',
    ],
    [
        'Apa yang dimaksud dengan "Progressive Disclosure" dalam desain interaksi manusia-komputer',
        'Teknik memisahkan informasi menjadi beberapa tahapan logis di mana hanya informasi dasar yang ditampilkan terlebih dahulu, sedangkan detail lanjutan disajikan hanya saat diminta oleh pengguna',
        ['Pemberitahuan kepada pengguna tentang persentase update software sistem', 'Pengungkapan data privasi pengguna ke pihak ketiga secara hukum', 'Pemuatan gambar secara bertahap menggunakan blur effect'],
        'Progressive Disclosure (Jakob Nielsen) adalah teknik ampuh mengelola kompleksitas: mencegah pengguna awam kewalahan menghadapi antarmuka rumit dengan menyembunyikan opsi advanced di balik menu "More Options" atau accordion.',
    ],
    [
        'Dalam Information Architecture, struktur organisasi situs bertipe "Hierarchical (Strict Tree)" berbeda dengan struktur bertipe "Faceted (Polyhierarchical)" dalam hal',
        'Hierarchical menempatkan satu entitas konten di tepat satu kategori tunggal, sedangkan Faceted memungkinkan satu entitas diakses melalui berbagai dimensi filter atribut yang berbeda (seperti ukuran, warna, harga)',
        ['Hierarchical hanya digunakan di website pemerintah, sedangkan Faceted di toko online', 'Faceted tidak menggunakan basis data relasional sama sekali', 'Hierarchical selalu memiliki kedalaman kategori maksimal 2 tingkat'],
        'Faceted Classification (S.R. Ranganathan & Peter Morville) mendasari navigasi e-commerce modern: produk sepatu dapat ditemukan melalui cabang kategori Olahraga, filter Ukuran 42, atau filter Merek Nike secara paralel.',
    ],
    [
        'Tantangan psikologis dalam interaksi "Paradox of Choice" (Barry Schwartz) menjelaskan bahwa ketika pengguna dihadapkan pada terlalu banyak pilihan produk yang serupa, dampaknya adalah',
        'Kecemasan keputusan (decision paralysis), penundaan pembelian (drop-off), dan penyesalan yang lebih tinggi setelah memilih dibanding jika diberi sedikit pilihan',
        ['Pengguna akan memborong seluruh produk yang ada di toko seketika', 'Pengguna akan merekomendasikan aplikasi ke seluruh teman-temannya', 'Tingkat konversi penjualan website akan melonjak sebesar 300%'],
        'Barry Schwartz (The Paradox of Choice, 2004) membuktikan bahwa opsi berlebih melumpuhkan keputusan (paralysis rather than liberation): dalam UX, fungsi kurasi, default cerdas, dan filter terfokus mutlak diperlukan.',
    ],
    [
        'Dalam UX Design, praktik manipulatif "Dark Pattern" (Deceptive Patterns) didefinisikan oleh Harry Brignull sebagai',
        'Trik antarmuka yang sengaja dirancang untuk memanipulasi atau menipu pengguna agar melakukan tindakan yang sebenarnya tidak ingin mereka lakukan (misal biaya tersembunyi atau langganan otomatis)',
        ['Penggunaan tema warna hitam pekat (Dark Mode) pada malam hari', 'Kesalahan coding yang menyebabkan layar komputer menjadi gelap', 'Desain antarmuka yang tidak menggunakan ikon sama sekali'],
        'Harry Brignull (2010, Deceptive Design) mengkatalogkan dark patterns (seperti Roach Motel, Sneak into Basket, Confirmshaming): praktik tidak etis yang mengeksploitasi bias kognitif manusia demi metrik bisnis jangka pendek.',
    ],
    [
        'Praktik deceptive design "Confirmshaming" terjadi ketika antarmuka formulir opt-out promosi menggunakan bahasa pilihan berupa',
        'Teks tombol penolakan yang sengaja mempermalukan atau menimbulkan rasa bersalah pada pengguna (misal: "Tidak, saya tidak ingin menghemat uang")',
        ['Teks tombol netral seperti "Batal" atau "Lewati"', 'Konfirmasi email yang dikirimkan secara otomatis setelah pendaftaran', 'Pemberitahuan bahwa kuota pendaftaran kelas telah penuh'],
        'Confirmshaming memanipulasi emosi pengguna menggunakan bahasa pasif-agresif agar pengguna merasa bersalah saat menolak tawaran, merusak kepercayaan dan reputasi jangka panjang brand produk.',
    ],
    [
        'Konsep "Micro-interactions" yang dirumuskan oleh Dan Saffer terdiri dari empat elemen pembangun siklus, yaitu',
        'Trigger (Pemicu), Rules (Aturan), Feedback (Umpan Balik), dan Loops & Modes (Perulangan & Moda)',
        ['Input, Output, Storage, dan Memory', 'Header, Footer, Sidebar, dan Content', 'HTML, CSS, JavaScript, dan Database'],
        'Dan Saffer (Microinteractions: Designing with Details, 2013) menetapkan 4 komponen siklus interaksi mikro: trigger memicu aksi, rules menentukan apa yang terjadi, feedback memberi tahu pengguna, dan loops mengatur durasi pengulangan.',
    ],
];

// =========================================================================
// MODUL 5: UX RESEARCH, USABILITY TESTING, METRICS (SUS, SEQ) & PROTO (20)
// =========================================================================
$researchTestingTopics = [
    [
        'Dalam metodologi UX Research, apa perbedaan tujuan utama antara "Generative Research" (Discovery) dan "Evaluative Research"',
        'Generative Research bertujuan memahami kebutuhan mendalam, perilaku, dan pain points pengguna untuk menemukan peluang solusi baru, sedangkan Evaluative Research menguji efektivitas dan kemudahan solusi desain yang sudah dibuat',
        ['Generative Research dilakukan oleh komputer AI, sedangkan Evaluative Research oleh manusia', 'Evaluative Research hanya dilakukan setelah produk bangkrut di pasaran', 'Keduanya identik dan hanya berbeda sebutan istilah di instansi akademik'],
        'Erika Hall (Just Enough Research) membedakan: Generative/Exploratory Research menjawab "Masalah apa yang perlu dipecahkan?", sedangkan Evaluative Research menjawab "Seberapa baik solusi yang kita rancang menyelesaikan masalah tersebut?".',
    ],
    [
        'Dalam pengujian kualitatif Usability Testing, rekomendasi terkenal Jakob Nielsen mengenai jumlah partisipan uji yang paling optimal dari segi biaya-manfaat (cost-benefit) adalah',
        'Cukup 5 orang partisipan untuk menemukan sekitar 85% dari total masalah usability yang ada pada antarmuka',
        ['Wajib minimal 1.000 orang pengguna agar data valid secara statistik', 'Hanya 1 orang desainer internal saja', 'Tepat 50 orang pengguna dari setiap kota di Indonesia'],
        'Jakob Nielsen & Thomas K. Landauer (1993, ACM INTERCHI) membuktikan formula matematis N(1 - (1 - L)^n): menguji 5 partisipan mengungkap ~85% masalah usability utama. Melibatkan lebih dari 5 orang menghasilkan temuan masalah yang sama berulang kali (diminishing returns).',
    ],
    [
        'Kuesioner standar industri "System Usability Scale" (SUS) yang diciptakan oleh John Brooke terdiri dari',
        '10 butir pertanyaan skala Likert 5-poin dengan pernyataan positif dan negatif yang berselang-seling',
        ['100 pertanyaan pilihan ganda tentang teknologi informasi', '3 pertanyaan essay deskriptif tentang biografi pengguna', 'Satu tombol rating jempol ke atas (like) dan jempol ke bawah (dislike)'],
        'John Brooke (1986, Redhatch Consulting) merancang SUS sebagai "quick and dirty" reliability tool berstandar tinggi: 10 pertanyaan Likert selang-seling (ganjil positif, genap negatif) untuk mencegah response bias.',
    ],
    [
        'Dalam interpretasi skor "System Usability Scale" (SUS) yang memiliki rentang skor 0 hingga 100, skor rata-rata industri (average benchmark) yang dianggap sebagai batas kelayakan standar (Grade C / Above Average) adalah',
        'Skor 68',
        ['Skor 50', 'Skor 90', 'Skor 100'],
        'Jeff Sauro (MeasuringU, 2011) menganalisis ribuan studi SUS: skor rata-rata normatif komposit adalah 68. Skor di atas 68 dianggap di atas rata-rata (Above Average / Grade C+), sedangkan skor di atas 80.3 masuk kategori Grade A (Excellent).',
    ],
    [
        'Metrik pengujian usability "Single Ease Question" (SEQ) ditanyakan kepada partisipan dengan cara',
        'Satu pertanyaan skala 7-poin ("Secara keseluruhan, seberapa mudah atau sulit tugas ini diselesaikan?") yang diajukan langsung sesaat setelah partisipan menyelesaikan setiap tugas uji (task-level metric)',
        ['Pertanyaan tertulis panjang yang dikirimkan via pos 1 bulan setelah pengujian', 'Wawancara psikologis mendalam selama 3 jam di akhir pekan', 'Pertanyaan pilihan ganda mengenai latar belakang keluarga pengguna'],
        'Jeff Sauro & Joseph S. Dumas (2009, ACM CHI) memvalidasi SEQ sebagai post-task metric yang sangat reliabel dan sensitif untuk mengukur perceived task difficulty segera setelah tugas selesai tanpa membebani partisipan.',
    ],
    [
        'Metrik kuantitatif usability "Task Success Rate" (Completion Rate) dihitung dengan rumus',
        '(Jumlah tugas yang berhasil diselesaikan dengan sukses ÷ Total seluruh tugas yang dicoba) × 100%',
        ['Jumlah tombol yang diklik pengguna dikalikan 100', 'Waktu tercepat penyelesaian tugas dibagi skor IQ partisipan', 'Total error yang dialami pengguna dikurangi nilai kepuasan'],
        'Tom Tullis & Bill Albert (Measuring the User Experience) menetapkan Completion Rate sebagai metrik fundamental efektivitas (ISO 9241-11): mengukur persentase keberhasilan partisipan menuntaskan tugas uji sesuai kriteria sukses objektif.',
    ],
    [
        'Protokol riset "Think-Aloud Protocol" dalam moderated usability testing mengharuskan partisipan untuk',
        'Menyuarakan secara verbal apa yang mereka pikirkan, rasakan, lihat, dan lakukan saat berinteraksi dengan antarmuka secara real-time',
        ['Berbicara keras menggunakan mikrofon panggung di depan audiens umum', 'Membaca buku panduan pengguna dengan suara keras sebelum menyentuh antarmuka', 'Menyanyikan instruksi tugas yang diberikan oleh fasilitator uji'],
        'Clayton Lewis (1982, IBM) & Jakob Nielsen menetapkan Think-Aloud sebagai metode emas kualitatif UX: mengungkap pemikiran bawah sadar, kesalahpahaman mental model, dan reaksi emosional pengguna secara langsung tanpa rekayasa.',
    ],
    [
        'Dalam wawancara pengguna (User Interview), jenis pertanyaan manakah yang paling dihindari karena berpotensi merusak validitas data (Leading Questions)',
        '"Bukankah fitur navigasi baru kami ini terasa jauh lebih mudah dan menyenangkan untuk Anda gunakan?" (pertanyaan mengarahkan opini)',
        ['"Bisa ceritakan bagaimana pengalaman terakhir Anda saat melakukan transfer dana?" (pertanyaan terbuka netral)', '"Apa tantangan terbesar yang Anda hadapi saat mencari riwayat transaksi?"', '"Bagaimana biasanya langkah-langkah yang Anda lakukan saat membeli barang online?"'],
        'Steve Portigal (Interviewing Users) menegaskan bahwa leading questions merusak objektivitas riset: pertanyaan yang mengarahkan memicu "courtesy bias" di mana partisipan cenderung mengiyakan perkataan interviewer demi menyenangkan peneliti.',
    ],
    [
        'Artefak riset UX "User Persona" yang representatif dan efektif disusun berdasarkan',
        'Sintesis data perilaku, kebutuhan, motivasi, dan tantangan riil dari sekelompok pengguna nyata hasil riset empiris, bukan asumsi stereotip fiktif belaka',
        ['Foto acak selebriti terkenal yang disukai oleh manajer produk', 'Koleksi seluruh data identitas KTP dan nomor telepon pengguna asli', 'Hasil tebakan desainer grafis saat memulai proyek tanpa riset'],
        'Alan Cooper (The Inmates Are Running the Asylum, 1999) merumuskan Persona sebagai arketipe pengguna berbasis riset etnografis/kualitatif, membantu tim produk berempati dan mengambil keputusan yang berpusat pada pengguna spesifik.',
    ],
    [
        'Artefak UX "Customer Journey Map" (CJM) memvisualisasikan alur interaksi pengguna dengan produk yang mencakup komponen utama berupa',
        'Tahapan alur proses (Stages), aksi pengguna (Actions), titik sentuh (Touchpoints), pikiran dan emosi (Thoughts & Feelings), serta peluang perbaikan (Opportunities)',
        ['Struktur kode database SQL lengkap dengan foreign key relasi', 'Daftar absensi kehadiran pengguna saat menggunakan aplikasi', 'Skema rangkaian sirkuit elektronik motherboard komputer'],
        'Nielsen Norman Group mendefinisikan Customer Journey Map sebagai visualisasi holistik dari perjalanan end-to-end pengguna untuk mencapai tujuan, memetakan pasang surut emosional (pain points) untuk menemukan peluang inovasi.',
    ],
    [
        'Metode riset UX "Contextual Inquiry" (bagian dari Contextual Design) mengharuskan peneliti untuk',
        'Mengamati dan mewawancarai pengguna langsung di lingkungan alami kerja/kehidupan asli mereka saat mereka sedang melakukan tugas nyata sehari-hari',
        ['Mengundang pengguna ke laboratorium buatan yang steril tanpa gangguan', 'Mengirimkan kuesioner otomatis melalui robot WhatsApp', 'Meminta pengguna membayangkan situasi kerja tanpa observasi langsung'],
        'Hugh Beyer & Karen Holtzblatt (Contextual Design, 1997) menetapkan 4 prinsip Contextual Inquiry: context (di lingkungan asli), partnership (relasi master-apprentice), interpretation (pemaknaan bersama), dan focus.',
    ],
    [
        'Dalam metode pengujian eksperimen kuantitatif "A/B Testing" (Split Testing), aturan validitas statistik yang paling krusial adalah',
        'Hanya mengubah satu variabel independen tunggal (misal hanya teks judul tombol) antara Varian A dan Varian B, serta membagi trafik secara acak hingga mencapai signifikansi statistik (p-value < 0.05)',
        ['Mengubah seluruh tampilan warna, layout, dan nama produk secara bersamaan pada Varian B', 'Menjalankan pengujian Varian A di hari Senin dan Varian B di hari Sabtu', 'Menghentikan pengujian segera setelah ada 5 pengguna pertama yang mengklik'],
        'Kohavi, Tang, & Xu (Trustworthy Online Controlled Experiments, Cambridge) menjelaskan metodologi A/B testing: menjaga variabel pengganggu (confounding variables) konstan dan memastikan ukuran sampel memadai untuk statistical power yang valid.',
    ],
    [
        'Tingkat kesetiaan visual dan fungsional prototipe "Low-Fidelity Prototype" (seperti Paper Prototype atau wireframe kotak abu-abu sederhana) paling tepat digunakan pada tahap',
        'Eksplorasi konsep awal dan arsitektur alur ide (ideation phase) untuk menguji navigasi secara cepat dan murah sebelum investasi waktu visual detail',
        ['Presentasi final ke dewan direksi perusahaan pada peluncuran resmi produk', 'Pengujian performa render GPU pada browser mobile', 'Pengujian akurasi kode warna hex sebelum diserahkan ke developer'],
        'Snyder (Paper Prototyping, 2003) & Houde & Hill (1997) menegaskan bahwa low-fi prototypes menurunkan bias keindahan visual: pengguna dan stakeholder berani mengkritik konsep struktural inti tanpa terdistraksi oleh warna atau tipografi.',
    ],
    [
        'Sebaliknya, kapan penggunaan "High-Fidelity Interactive Prototype" (di Figma dengan smart animate dan dynamic data) mutlak diperlukan dalam proses desain',
        'Ketika tim perlu menguji transisi animasi halus, interaksi mikro presisi, kelayakan teknis visual, dan melakukan presentasi usability realistis kepada pengguna akhir serta klien',
        ['Pada pertemuan brainstorming pertama kali saat ide baru dilontarkan', 'Ketika proyek hanya memiliki sisa waktu pengerjaan 1 jam', 'Ketika aplikasi hanya akan memuat teks statis tanpa interaksi'],
        'High-fidelity interactive prototype menjembatani simulasi realitas: pengguna memperlakukan prototipe seperti aplikasi sungguhan, esensial untuk menguji transisi status interaktif dan affordance visual yang mendekati produk akhir.',
    ],
    [
        'Dalam pengujian kuantitatif UX, apa perbedaan antara metrik "Attitudinal" (Sikap/Persepsi) dan metrik "Behavioral" (Perilaku/Tindakan Nyata)',
        'Attitudinal mengukur apa yang dikatakan atau dirasakan pengguna (misal kepuasan CSAT/NPS), sedangkan Behavioral mengukur apa yang benar-benar dilakukan pengguna (misal click-through rate, waktu tugas, drop-off rate)',
        ['Attitudinal hanya diuji pada hewan, sedangkan Behavioral pada manusia', 'Behavioral dikumpulkan melalui wawancara, sedangkan Attitudinal melalui log server', 'Keduanya identik dan tidak pernah dibedakan dalam riset sains'],
        'Christian Rohrer (NN/g Framework on Landscape of User Research Methods) memetakan sumbu Attitudinal vs Behavioral: terdapat perbedaan besar antara apa yang orang katakan (self-reported) dan apa yang sebenarnya mereka lakukan secara objektif.',
    ],
    [
        'Metode riset UX kuantitatif "First Click Testing" mengukur tingkat keberhasilan pengguna dalam memilih klik pertama pada antarmuka. Mengapa klik pertama sangat krusial dalam navigasi web',
        'Riset Bob Bailey menunjukkan bahwa pengguna yang klik pertamanya sudah tepat memiliki peluang keberhasilan menyelesaikan tugas hingga 87%, sedangkan jika klik pertama salah, peluang sukses merosot hingga 46%',
        ['Browser akan langsung memutus koneksi internet jika klik pertama salah', 'Klik pertama menentukan warna tema tampilan aplikasi secara otomatis', 'Klik pertama selalu memakan waktu loading paling lama di server'],
        'Bob Bailey & Cari Wolfson (Human Factors International, 2006) mempublikasikan First Click Testing: klik pertama mencerminkan keselarasan awal mental model pengguna dengan Information Architecture antarmuka.',
    ],
    [
        'Bias kognitif dalam riset "Hawthorne Effect" (Observer Effect) terjadi ketika',
        'Partisipan riset mengubah atau memperbaiki perilakunya menjadi tidak natural semata-mata karena mereka sadar bahwa mereka sedang diobservasi dan diawasi oleh peneliti',
        ['Peneliti tertidur saat sesi pengujian sedang berlangsung', 'Kamera perekam riset kehabisan baterai di tengah wawancara', 'Partisipan menolak menjawab pertanyaan karena lapar'],
        'Studi klasik Western Electric Hawthorne Works (Elton Mayo) menemukan bahwa kesadaran diobservasi mengubah perilaku subjek. Fasilitator usability testing profesional meminimalkan efek ini dengan menciptakan suasana informal tanpa penghakiman.',
    ],
    [
        'Dalam metode pengumpulan data pengalaman jangka panjang, kapan teknik "Diary Study" paling tepat diterapkan dalam UX Research',
        'Ketika peneliti perlu memahami perilaku, kebiasaan, dan perubahan emosi pengguna yang terjadi berulang kali dalam kurun waktu beberapa hari, minggu, atau bulan di kehidupan nyata',
        ['Ketika desainer membutuhkan jawaban cepat dalam waktu 5 menit', 'Ketika desainer ingin menguji kontras warna tombol form', 'Ketika aplikasi hanya akan digunakan satu kali seumur hidup'],
        'Kim Flaherty (Nielsen Norman Group) merekomendasikan Diary Studies untuk meneliti retensi, kebiasaan organik, dan interaksi temporal jangka panjang yang tidak dapat ditangkap dalam satu sesi lab singkat berdurasi 1 jam.',
    ],
    [
        'Matriks prioritas perbaikan masalah usability (Severity Rating) yang dirumuskan oleh Jakob Nielsen dinilai berdasarkan kombinasi tiga faktor utama, yaitu',
        'Frekuensi terjadinya masalah, dampak keparahan masalah terhadap penyelesaian tugas, dan persistensi masalah (apakah mudah diatasi atau terus menghalangi)',
        ['Tingkat kemarahan developer, durasi waktu coding, dan biaya server database', 'Jumlah baris kode CSS yang diperlukan untuk memperbaiki tampilan', 'Warna elemen antarmuka yang mengalami kerusakan grafis'],
        'Jakob Nielsen (1995, Usability Inspection Methods) menetapkan skala keparahan 0-4 (Cosmetic, Minor, Major, Catastrophic) berbasis kalkulasi kombinasi: seberapa sering terjadi, seberapa fatal dampaknya, dan seberapa sulit pengguna pulih dari masalah.',
    ],
    [
        'Dalam metodologi Design Thinking yang dipelopori oleh d.school Stanford dan IDEO, lima fase siklus inovasi yang berulang (non-linear) terdiri dari',
        'Empathize, Define, Ideate, Prototype, dan Test',
        ['Plan, Code, Build, Test, dan Deploy', 'Survey, Design, Sell, Profit, dan Exit', 'Analyze, Document, Negotiate, Sign, dan Close'],
        'Hasso Plattner Institute of Design di Stanford University merumuskan 5 tahap Design Thinking: berempati dengan pengguna (Empathize), mendefinisikan problem statement (Define), mengeksplorasi solusi (Ideate), membuat wujud nyata (Prototype), dan menguji ke pengguna (Test).',
    ],
];

// Gabungkan semua modul menjadi 100 soal
$index = 1;
foreach ([$heuristicsTopics, $gestaltA11yTopics, $designSystemTopics, $iaCognitiveTopics, $researchTestingTopics] as $module) {
    foreach ($module as $item) {
        $questions[] = $make($item[0], $item[1], $item[2], $item[3], $index++);
    }
}

return $questions;
