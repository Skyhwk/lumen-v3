<?php

/**
 * 100 Soal Standar Industri untuk Kategori: FRONTEND_DEVELOPMENT
 * Manager Scope - Dedi Wibowo (IT Programming Manager)
 * 
 * Topik:
 * 1. HTML5 Semantic, DOM, CSS Architecture, Flexbox/Grid, Responsive & Rendering (20 soal)
 * 2. Core JavaScript, ES6+, Asynchronous, Event Loop, Closures & Scope (20 soal)
 * 3. TypeScript Type System, Generics, Narrowing & Best Practices (20 soal)
 * 4. React.js Ecosystem, Virtual DOM, Hooks, Reconciliation & State Management (20 soal)
 * 5. Web Performance (Core Web Vitals), Frontend Security (XSS/CSRF/CSP) & Testing (20 soal)
 *
 * Referensi: W3C Specs, MDN Web Docs, IEEE Software, ACM Computing Surveys,
 * Google Web Vitals, OWASP Frontend Security, React Documentation.
 */

$helpers = require __DIR__ . '/QuestionSeederHelpers.php';
$make = $helpers['makeQuestion'];

$questions = [];

// =========================================================================
// MODUL 1: HTML5, DOM, CSS ARCHITECTURE, FLEXBOX/GRID, RENDERING (20 Soal)
// =========================================================================
$htmlCssTopics = [
    [
        'Sebuah aplikasi e-commerce ingin meningkatkan aksesibilitas untuk screen reader pada struktur artikel berita promo. Tag HTML5 semantic manakah yang paling tepat digunakan untuk membungkus konten yang independen dan dapat didistribusikan secara terpisah',
        '<article>',
        ['<section>', '<div>', '<aside>'],
        'Berdasarkan spesifikasi W3C HTML5, elemen <article> merepresentasikan komposisi mandiri dalam dokumen yang dimaksudkan untuk dapat didistribusikan secara independen, berbeda dengan <section> yang merupakan pengelompokan tematik umum.',
    ],
    [
        'Dalam rendering pipeline browser (Critical Rendering Path), browser melakukan parsing HTML menjadi DOM dan CSS menjadi CSSOM. Tahap berikutnya yang menggabungkan kedua tree tersebut untuk menghitung elemen yang benar-benar tampak di layar adalah',
        'Render Tree Construction',
        ['Layout / Reflow', 'Painting', 'Rasterization'],
        'Menurut dokumentasi Google Developers & MDN, Critical Rendering Path memproses DOM dan CSSOM menjadi Render Tree sebelum menghitung geometri posisi (Layout/Reflow) dan menggambar pixel ke layar (Paint).',
    ],
    [
        'Ketika terjadi perubahan dimensi elemen DOM seperti width atau height melalui manipulasi script, proses kalkulasi ulang posisi dan geometri seluruh elemen yang terkena dampak di viewport disebut',
        'Reflow (Layout)',
        ['Repaint', 'Compositing', 'Pre-rendering'],
        'Reflow terjadi ketika perubahan geometri elemen memaksa browser menghitung ulang posisi dan ukuran node. Reflow jauh lebih mahal secara komputasi dibanding repaint (MDN Web Performance Guidelines).',
    ],
    [
        'Seorang developer menulis selector CSS: "#nav .menu-item a:hover". Berapakah bobot spesifisitas (specificity) selector tersebut menurut aturan standar CSS W3C (ID, Class/Attribute/Pseudo-class, Element/Pseudo-element)',
        '1, 2, 1 (1 ID, 2 Classes/Pseudo-classes, 1 Element)',
        ['0, 2, 2', '1, 1, 2', '0, 3, 1'],
        'Spesifisitas dihitung dari: #nav (1 ID), .menu-item dan :hover (2 class/pseudo-class), dan <a> (1 element), sehingga menghasilkan bobot 1, 2, 1 (W3C CSS Selectors Level 4).',
    ],
    [
        'Pada implementasi layout CSS Flexbox, properti manakah yang digunakan untuk mengatur perataan item di sepanjang cross-axis (sumbu silang)',
        'align-items',
        ['justify-content', 'align-content', 'flex-direction'],
        'Spesifikasi W3C CSS Flexible Box Layout mendefinisikan justify-content untuk mengatur perataan pada main-axis, sedangkan align-items mengatur perataan item individu di sepanjang cross-axis.',
    ],
    [
        'Dalam CSS Grid Layout, fungsi CSS manakah yang memungkinkan pembuatan kolom yang secara fleksibel mengisi ruang kontainer dengan batas minimum dan maksimum tanpa memerlukan media query berulang',
        'repeat(auto-fit, minmax(200px, 1fr))',
        ['flex(1, 200px, auto)', 'calc(100% / 3)', 'grid-template-columns: 1fr 1fr 1fr'],
        'Kombinasi repeat(auto-fit, minmax(...)) merupakan standar pola CSS Grid modern untuk responsivitas fluid tanpa breakpoint media query manual (W3C CSS Grid Level 2).',
    ],
    [
        'Dalam model CSS Stacking Context, properti manakah di bawah ini yang TIDAK membentuk stacking context baru bagi elemen',
        'position: absolute dengan z-index: auto',
        ['opacity kurang dari 1', 'transform selain none', 'position: relative dengan z-index bernilai integer selain auto'],
        'Elemen berposisi absolute atau relative hanya membentuk stacking context baru jika z-index bernilai integer selain auto. Nilai z-index: auto tidak menciptakan stacking context baru (W3C CSS2 Spec).',
    ],
    [
        'Sebuah form transaksi keuangan membutuhkan input angka yang aman dari format localized grouping dan hanya menerima digit nomor telepon. Atribut input manakah yang memicu keypad numerik pada perangkat mobile tanpa memvalidasi floating point',
        'type="tel" atau inputmode="numeric"',
        ['type="number"', 'type="text"', 'type="range"'],
        'Dokumentasi MDN dan studi UX Gov.UK menyarankan inputmode="numeric" atau type="tel" untuk kode/nomor tanpa panah stepper (spinbutton) dan tanpa masalah parsing scientific notation seperti yang terjadi pada type="number".',
    ],
    [
        'Properti CSS "box-sizing: border-box" mengubah kalkulasi lebar total sebuah elemen. Apa efek utama dari deklarasi ini terhadap perhitungan elemen',
        'Padding dan border dihitung ke dalam total nilai width dan height elemen',
        ['Margin dihitung ke dalam width elemen', 'Padding dan border ditambahkan di luar width yang ditentukan', 'Elemen otomatis berubah menjadi inline-block'],
        'Berdasarkan W3C Box Sizing Module, nilai border-box memastikan bahwa padding dan border dimasukkan ke dalam deklarasi width dan height, mencegah overflow yang tidak diinginkan.',
    ],
    [
        'Ketika memuat font kustom via CSS @font-face, nilai font-display manakah yang menampilkan teks cadangan (fallback) sesaat dan langsung menukar (swap) font segera setelah font kustom selesai diunduh',
        'font-display: swap',
        ['font-display: block', 'font-display: fallback', 'font-display: optional'],
        'font-display: swap meminimalkan FOIT (Flash of Invisible Text) dengan menggunakan fallback font segera dan menggantinya begitu custom font siap (Google Web Vitals & W3C Fonts Module).',
    ],
    [
        'Metode optimasi CSS "BEM" (Block Element Modifier) membagi penamaan class menjadi tiga entitas. Manakah contoh penamaan yang mematuhi konvensi BEM secara tepat',
        'btn btn--primary btn__icon',
        ['btn_primary-icon', 'button.primary.icon', 'btn-primary_icon'],
        'Konvensi BEM standar industri menggunakan double-underscore (__) untuk menandakan element turunan dan double-dash (--) untuk modifier status/varian.',
    ],
    [
        'Pada pemuatan resource eksternal berupa stylesheet, atribut rel="preload" pada tag <link> bertujuan untuk',
        'Memberitahu browser agar mengunduh resource dengan prioritas tinggi lebih awal sebelum parser memerlukannya',
        ['Menjalankan eksekusi script sebelum DOMContentLoaded', 'Menyimpan resource selamanya di LocalStorage', 'Menghalangi render halaman sampai resource selesai diuraikan'],
        'W3C Preload specification menyatakan bahwa <link rel="preload"> menginstruksikan browser mengambil resource krusial lebih awal dalam siklus hidup halaman untuk mempercepat waktu render.',
    ],
    [
        'Dalam Responsive Web Design, unit viewport "dvh" (Dynamic Viewport Height) diperkenalkan pada CSS Values Level 4 untuk memecahkan masalah',
        'Perubahan ukuran viewport mobile akibat kemunculan dan hilangnya address bar browser saat scrolling',
        ['Perbedaan resolusi layar retina dengan layar standar', 'Kalkulasi tinggi elemen SVG pada layar monitor desktop ultra-wide', 'Pengukuran tinggi font berbasis rasio modular'],
        'Unit dvh secara dinamis menyesuaikan nilainya saat dynamic UI browser mobile (seperti URL bar) mengembang atau menyusut, menyelesaikan bug tinggi 100vh klasik di iOS/Android.',
    ],
    [
        'Teknik CSS modern CSS Containment ("contain: content" atau "contain: strict") memberikan manfaat performa utama berupa',
        'Mengisolasi subtree DOM dari sisa halaman sehingga browser dapat membatasi kalkulasi reflow dan repaint',
        ['Mengompresi file CSS secara otomatis pada runtime browser', 'Mencegah terjadinya memory leak pada event listener', 'Menghapus selector CSS yang tidak terpakai dari memori'],
        'W3C CSS Containment Module Level 2 memungkinkan developer mengisolasi elemen individual sehingga kalkulasi layout, style, dan paint tidak merambat ke seluruh halaman.',
    ],
    [
        'Elemen HTML5 <picture> bersama dengan tag <source> dan atribut "srcset" serta "media" dirancang untuk menyelesaikan kebutuhan',
        'Art direction dan responsive images yang menyajikan format gambar berbeda sesuai ukuran layar atau dukungan browser',
        ['Pemutaran video streaming adaptif seperti HLS', 'Pengambilan gambar langsung dari kamera perangkat pengguna via WebRTC', 'Penyusunan galeri CSS 3D tanpa JavaScript'],
        'Spesifikasi W3C Responsive Images menetapkan <picture> sebagai standar art direction, memungkinkan browser memilih gambar yang paling optimal berdasarkan media query dan format (WebP/AVIF).',
    ],
    [
        'Dalam CSS transform dan animasi, properti manakah yang paling direkomendasikan untuk dianimasikan karena dapat di-offload langsung ke GPU (Compositor Thread) tanpa memicu reflow dan repaint',
        'transform dan opacity',
        ['top, left, dan margin', 'width, height, dan padding', 'background-color dan border-radius'],
        'Studi Google Web Performance menunjukkan bahwa animasi yang hanya melibatkan transform dan opacity berjalan langsung di GPU Compositor Thread, menghasilkan 60 FPS yang mulus tanpa beban reflow.',
    ],
    [
        'Atribut "loading=\'lazy\'" native pada tag <img> memiliki mekanisme kerja standar yaitu',
        'Menunda pemuatan gambar sampai gambar tersebut mendekati batas jarak tertentu dari viewport pengguna',
        ['Mengunduh gambar dengan resolusi terendah lalu mengaburkannya', 'Mengompresi gambar menjadi format base64 di memori browser', 'Menunggu interaksi klik pengguna sebelum gambar diunduh'],
        'Atribut loading="lazy" yang distandardisasi oleh WHATWG memungkinkan browser menunda fetching resource gambar yang berada di luar layar (below-the-fold) hingga pengguna menggulir mendekatinya.',
    ],
    [
        'Pada implementasi layout multi-kolom teks dengan CSS Columns ("columns: 3 200px"), properti manakah yang digunakan untuk mencegah pemotongan kartu elemen di tengah antar-kolom',
        'break-inside: avoid',
        ['column-gap: 0', 'page-break-after: always', 'white-space: nowrap'],
        'W3C CSS Fragmentation Module mendefinisikan break-inside: avoid untuk mencegah elemen kontainer terbelah saat terjadi pemecahan kolom atau halaman cetak.',
    ],
    [
        'Kapan sebuah selector CSS pseudo-class ":focus-visible" aktif pada sebuah elemen interaktif',
        'Hanya ketika elemen menerima fokus dan browser menentukan berdasarkan heuristik bahwa indikator fokus visual diperlukan (misal navigasi keyboard)',
        ['Setiap kali elemen diklik oleh mouse pengguna', 'Secara konstan selama elemen berada di dalam viewport', 'Hanya jika elemen memiliki atribut tabIndex bernilai negatif'],
        'W3C Selectors Level 4 memperkenalkan :focus-visible untuk meningkatkan estetika dan aksesibilitas: memunculkan focus ring untuk pengguna keyboard tanpa mengganggu pengguna mouse/touch.',
    ],
    [
        'Dalam arsitektur CSS modern, fitur CSS Cascade Layers ("@layer") dirancang secara spesifik untuk',
        'Mengelola urutan prioritas cascade CSS secara eksplisit terlepas dari tingkat spesifisitas selector',
        ['Memisahkan file CSS menjadi chunks terkompresi secara otomatis', 'Mengubah sintaks CSS biasa menjadi WebAssembly binary', 'Menjalankan logika percabangan if-else di dalam CSS native'],
        'W3C Cascading and Inheritance Level 5 mendefinisikan @layer untuk menyelesaikan specificity wars dengan memberikan kontrol eksplisit atas layer hierarki CSS (misal base, components, utilities).',
    ],
];

// =========================================================================
// MODUL 2: CORE JAVASCRIPT, ES6+, ASYNC, EVENT LOOP, CLOSURES (20 Soal)
// =========================================================================
$jsCoreTopics = [
    [
        'Perhatikan kode: "console.log(typeof null);". Apakah output yang dihasilkan menurut spesifikasi standar ECMAScript dan mengapa hal tersebut terjadi',
        '"object", karena peninggalan bug desain awal JavaScript di mana type tag 0 menandakan object',
        ['"null", karena null adalah tipe data primitif tersendiri', '"undefined", karena nilai null belum didefinisikan', '"boolean", karena null dievaluasi sebagai false'],
        'Berdasarkan dokumentasi MDN dan riwayat ECMAScript oleh Brendan Eich, typeof null menghasilkan "object" karena pada implementasi pertama JavaScript, nilai null direpresentasikan sebagai pointer 0x00 yang cocok dengan type tag object.',
    ],
    [
        'Dalam mekanisme Event Loop JavaScript runtime (V8/Node/Browser), manakah urutan eksekusi yang benar dari kode berikut: console.log(1); setTimeout(() => console.log(2), 0); Promise.resolve().then(() => console.log(3)); console.log(4);',
        '1, 4, 3, 2',
        ['1, 2, 3, 4', '1, 4, 2, 3', '1, 3, 4, 2'],
        'Synchronous code dijalankan terlebih dahulu (1, 4). Selanjutnya, Microtask Queue (Promise callback: 3) diproses tuntas sebelum Event Loop beralih ke Macrotask/Task Queue (setTimeout callback: 2) (Spesifikasi HTML Event Loop WHATWG).',
    ],
    [
        'Konsep closure dalam JavaScript terjadi ketika',
        'Sebuah fungsi mempertahankan akses ke lexical scope-nya bahkan ketika fungsi tersebut dieksekusi di luar lexical scope asalnya',
        ['Sebuah fungsi dipanggil berulang kali secara rekursif hingga call stack habis', 'Dua fungsi saling memanggil satu sama lain dalam loop tertutup', 'Sebuah variabel global diubah nilainya di dalam block scope'],
        'Menurut Kyle Simpson (You Don\'t Know JS) dan ECMAScript Spec, closure adalah kemampuan fungsi mengakses variabel dari lexical scope penutupnya (enclosing scope) saat fungsi dieksekusi di tempat lain.',
    ],
    [
        'Manakah perbedaan mendasar antara "arrow function" dengan "regular function" dalam JavaScript terkait binding keyword "this"',
        'Arrow function tidak memiliki "this" sendiri; nilai "this" diwarisi secara lexical dari scope di mana ia didefinisikan',
        ['Arrow function mengikat "this" ke elemen DOM global secara default', 'Arrow function dapat di-bind ulang menggunakan method .bind(), .call(), atau .apply()', 'Regular function selalu memiliki "this" bernilai undefined dalam mode non-strict'],
        'Spesifikasi ECMAScript 2015 (ES6) menetapkan bahwa arrow functions tidak memiliki binding this, arguments, super, atau new.target sendiri, melainkan mengambil nilai this dari enclosing lexical context.',
    ],
    [
        'Sebuah array besar berisi objek transaksi perlu diubah nilainya tanpa memutasi array asli (prinsip immutability). Method manakah yang mengembalikan array baru dengan elemen yang diubah',
        'Array.prototype.map()',
        ['Array.prototype.forEach()', 'Array.prototype.splice()', 'Array.prototype.push()'],
        'Array.prototype.map() memproduksi array baru hasil transformasi setiap elemen tanpa memodifikasi array asal, sedangkan forEach tidak mengembalikan array baru dan splice/push bersifat mutatif (MDN).',
    ],
    [
        'Fungsi bawaan JavaScript "Object.freeze()" memberikan tingkat kekebalan (immutability) pada objek. Sejauh mana perlindungan yang diberikan oleh Object.freeze() standar',
        'Shallow freeze: hanya membekukan properti tingkat pertama; objek bersarang (nested object) tetap dapat dimutasi',
        ['Deep freeze: membekukan seluruh properti dan nested object secara rekursif otomatis', 'Hanya mencegah penambahan properti baru tetapi tetap mengizinkan perubahan nilai properti lama', 'Mencegah pembacaan properti objek oleh script lain'],
        'Object.freeze() bekerja secara shallow. Untuk mencapai deep immutability pada objek bersarang, diperlukan fungsi rekursif kustom atau library seperti Immer (MDN ECMAScript Reference).',
    ],
    [
        'Dalam asynchronous JavaScript, method "Promise.allSettled()" memiliki keunggulan dibanding "Promise.all()" dalam skenario di mana',
        'Developer ingin menunggu semua promise selesai (baik fulfilled maupun rejected) tanpa gagal langsung saat ada salah satu promise yang reject',
        ['Developer hanya menginginkan promise yang selesai paling cepat untuk diambil nilainya', 'Developer membutuhkan eksekusi promise secara serial satu per satu', 'Semua promise dipastikan tidak akan pernah mengalami error jaringan'],
        'ES2020 memperkenalkan Promise.allSettled() yang menghasilkan array objek status ({status, value/reason}) untuk semua promise yang telah selesai, berbeda dengan Promise.all yang langsung reject pada kegagalan pertama (fail-fast).',
    ],
    [
        'Teknik "Debounce" dan "Throttle" sering diterapkan pada event handler browser yang frekuensinya tinggi seperti window scroll atau input search. Apa perbedaan prinsip utama keduanya',
        'Debounce menunda eksekusi hingga jeda waktu tertentu setelah aktivitas terakhir berhenti, sedangkan Throttle membatasi eksekusi maksimal sekali setiap interval waktu tertentu',
        ['Debounce membatasi eksekusi secara periodik tetap, sedangkan Throttle menghapus event listener otomatis', 'Debounce hanya bekerja pada form input, sedangkan Throttle hanya bekerja pada event klik mouse', 'Keduanya identik dan hanya berbeda penamaan dalam library Lodash'],
        'Menurut studi interaksi HCI dan dokumentasi performa web, Debounce ideal untuk autosuggest/search input (tunggu user berhenti mengetik), sedangkan Throttle ideal untuk scroll/resize monitoring (batasi sampling rate).',
    ],
    [
        'Fitur ES2020 "Nullish Coalescing Operator" (??) berbeda dari "Logical OR Operator" (||) dalam penanganan nilai fallback karena',
        'Operator ?? hanya mengembalikan operand kanan jika operand kiri bernilai null atau undefined, sedangkan || mengembalikan operand kanan untuk semua nilai falsy (termasuk 0, false, dan "")',
        ['Operator ?? mengonversi operand kiri menjadi integer sebelum perbandingan', 'Operator ?? hanya bekerja pada tipe data string', 'Operator || lebih cepat dieksekusi oleh compiler V8 dibanding ??'],
        'ECMAScript 2020 mendefinisikan operator ?? untuk mengatasi masalah operator || yang secara keliru menimpa nilai sah seperti angka 0 atau string kosong "" karena dianggap falsy (MDN Specs).',
    ],
    [
        'Dalam JavaScript, apa perbedaan antara data struktur "Map" dengan objek literal biasa ({})',
        'Map mengizinkan tipe data apa pun (termasuk objek dan fungsi) sebagai key, serta menjamin urutan iterasi sesuai waktu penambahan entri',
        ['Objek biasa memiliki performa lookup yang selalu lebih cepat untuk jutaan entri dibanding Map', 'Map tidak memiliki properti size sehingga jumlah entri harus dihitung manual', 'Objek biasa secara default tidak memiliki prototype chain bawaan'],
        'Spesifikasi ES6 menetapkan bahwa Map menyimpan key dari tipe data arbitrer dan mempertahankan urutan penyisipan (insertion order), serta memiliki kompleksitas waktu lookup O(1) yang teroptimasi untuk seringnya penambahan/penghapusan.',
    ],
    [
        'Apa yang dimaksud dengan "Temporal Dead Zone" (TDZ) pada deklarasi variabel menggunakan let dan const di JavaScript',
        'Area kode antara awal scope blok hingga baris di mana deklarasi variabel tersebut dieksekusi, di mana variabel tidak dapat diakses dan akan melempar ReferenceError',
        ['Rentang waktu di mana garbage collector membersihkan memori yang tidak terpakai', 'Jeda waktu eksekusi asynchronous saat menunggu microtask queue kosong', 'Masa aktif session storage sebelum data kedaluwarsa secara otomatis'],
        'Berdasarkan standar ECMAScript 2015, deklarasi let dan const di-hoist ke puncak blok scope mereka, tetapi tidak diinisialisasi. Mengakses variabel tersebut sebelum deklarasi baris aslinya akan melempar ReferenceError karena berada di TDZ.',
    ],
    [
        'Sebuah fungsi generator dalam JavaScript dideklarasikan dengan sintaks "function*". Keyword manakah yang digunakan untuk menjeda (pause) dan mengembalikan nilai dari fungsi generator',
        'yield',
        ['return', 'await', 'pause'],
        'Keyword yield menghentikan sementara eksekusi fungsi generator dan mengembalikan nilai saat ini kepada pemanggil, memungkinkan pembuatan lazy evaluation streams dan iterator kustom (MDN Generators).',
    ],
    [
        'Mekanisme Event Delegation pada manipulasi DOM browser bekerja dengan memanfaatkan prinsip',
        'Event Bubbling: memasang satu event listener pada elemen parent untuk menangani event yang terpicu dari elemen-elemen childnya',
        ['Event Capturing murni tanpa melibatkan fase bubbling pada document tree', 'Kloning elemen DOM secara virtual ke dalam web worker thread', 'Penggunaan WebSocket untuk mem-broadcast event ke seluruh node'],
        'Event Delegation memanfaatkan fase bubbling standar DOM (W3C DOM Level 3 Events) di mana event merambat naik dari target asli ke parent, mengurangi konsumsi memori akibat ribuan listener individu.',
    ],
    [
        'Method native manakah pada JavaScript modern (ES2022) yang direkomendasikan untuk melakukan deep copy objek yang mengandung tanggal, array, dan nested structure secara andal tanpa batasan fungsi JSON.stringify()',
        'structuredClone()',
        ['Object.assign()', '{ ...originalObject }', 'JSON.parse(JSON.stringify(originalObject))'],
        'structuredClone() adalah API standar HTML/ECMAScript modern yang mampu menyalin struktur data kompleks secara rekursif termasuk circular references, Date, RegExp, Map, dan Set tanpa kehilangan tipe data seperti pada trik JSON.',
    ],
    [
        'Pada implementasi Web Workers di browser, batasan utama yang dimiliki oleh thread worker saat menjalankan komputasi berat di latar belakang adalah',
        'Worker thread tidak memiliki akses langsung ke DOM, window object, atau document tree utama',
        ['Worker thread tidak dapat melakukan request jaringan via fetch atau XMLHttpRequest', 'Worker thread hanya dapat mengeksekusi kode bahasa WebAssembly', 'Worker thread tidak mendukung timer seperti setTimeout dan setInterval'],
        'Spesifikasi WHATWG Web Workers menetapkan bahwa worker berjalan di thread terpisah dari UI thread. Komunikasi dengan thread utama dilakukan secara eksklusif melalui message passing (postMessage/onmessage).',
    ],
    [
        'Dalam arsitektur module JavaScript, apa perbedaan esensial antara ES Modules (import/export) dengan CommonJS (require/module.exports)',
        'ES Modules bersifat statis sehingga dependencies dapat dianalisis dan di-tree-shake pada build time sebelum eksekusi, sedangkan CommonJS bersifat dinamis dan dievaluasi pada runtime secara synchronous',
        ['CommonJS dapat dijalankan secara native di semua browser modern tanpa bundler', 'ES Modules tidak mendukung export fungsi asinkron', 'CommonJS selalu menghasilkan promise saat melakukan require'],
        'IEEE Software & Webpack Guides mencatat bahwa struktur statis ES Modules memungkinkan bundler melakukan optimasi agresif seperti Tree Shaking (penghapusan dead code) yang mustahil dilakukan sempurna pada CommonJS.',
    ],
    [
        'Kapan sebuah "WeakMap" lebih tepat digunakan dibanding "Map" biasa dalam aplikasi JavaScript berskala besar',
        'Ketika key berupa objek dan developer menginginkan objek tersebut dapat dibersihkan oleh Garbage Collector saat tidak ada lagi referensi lain ke objek tersebut',
        ['Ketika key berupa string dan jumlah entri diketahui lebih dari 10.000 data', 'Ketika developer membutuhkan fitur iterasi for..of pada seluruh entri data', 'Ketika ukuran data map harus dapat disimpan ke dalam format JSON'],
        'WeakMap memegang referensi "lemah" (weakly held) terhadap key-nya yang harus berupa objek. Ini sangat krusial untuk mencegah memory leak saat menyimpan metadata elemen DOM atau private state (MDN Reference).',
    ],
    [
        'Perhatikan kode: "const arr = [1, 2, [3, [4]]];". Method array native manakah yang meratakan array bersarang tersebut menjadi satu dimensi penuh [1, 2, 3, 4]',
        'arr.flat(Infinity)',
        ['arr.flatten()', 'arr.reduce()', 'arr.flat(1)'],
        'Method Array.prototype.flat(depth) yang diperkenalkan pada ES2019 menerima kedalaman rekursi. Memasukkan Infinity akan meratakan seluruh level array bersarang menjadi satu dimensi tunggal (MDN).',
    ],
    [
        'Dalam penanganan error asynchronous, apa yang terjadi jika sebuah Promise mengalami rejection tetapi tidak memiliki handler .catch() atau try-catch blok pada async/await',
        'Browser/Node runtime memicu event "unhandledrejection" dan pada lingkungan modern dapat menghentikan proses atau mencatat error kritis di konsol',
        ['Eksekusi aplikasi otomatis di-retry sebanyak 3 kali oleh browser', 'Promise otomatis mengembalikan nilai null tanpa memunculkan peringatan', 'Call stack browser dialihkan ke halaman fallback 404'],
        'Standar HTML & Node.js menetapkan bahwa unhandled promise rejection memicu event UnhandledPromiseRejection. Di Node.js modern, hal ini dapat menyebabkan proses terminated dengan exit code non-zero.',
    ],
    [
        'Method "Proxy" dan "Reflect" yang diperkenalkan pada ES6 digunakan untuk keperluan',
        'Metaprogramming: mencegat (intercept) dan mengkustomisasi operasi dasar pada objek (seperti get, set, deleteProperty)',
        ['Mengompresi memori objek pada compiler level', 'Mengamankan komunikasi data melalui protokol HTTPS murni', 'Mengonversi sintaks JavaScript ke format XML'],
        'Spesifikasi ECMAScript 2015 mendefinisikan Proxy dan Reflect sebagai pondasi metaprogramming, yang banyak digunakan oleh framework modern (seperti Vue 3 Reactivity System) untuk mendeteksi mutasi data secara presisi.',
    ],
];

// =========================================================================
// MODUL 3: TYPESCRIPT TYPE SYSTEM, GENERICS, ADVANCED TYPES (20 Soal)
// =========================================================================
$typeScriptTopics = [
    [
        'Dalam sistem tipe TypeScript, manakah perbedaan mendasar antara tipe "any" dan tipe "unknown"',
        'Tipe "unknown" bersifat type-safe: nilai bertipe unknown tidak dapat digunakan atau dipanggil sebelum dilakukan type narrowing/pemeriksaan tipe, sedangkan "any" menonaktifkan seluruh type-checking',
        ['Tipe "any" hanya menerima tipe primitif, sedangkan "unknown" hanya menerima objek', 'Tipe "unknown" otomatis diubah menjadi string pada saat kompilasi ke JavaScript', 'Keduanya identik dan hanya berbeda versi rilis TypeScript'],
        'Dokumentasi resmi TypeScript menetapkan bahwa "unknown" adalah top type yang aman: memaksa developer melakukan narrowing sebelum operasi dijalankan, mencegah runtime error yang sering timbul dari penyalahgunaan "any".',
    ],
    [
        'Teknik "Discriminated Union" (Tagged Union) dalam TypeScript mengandalkan fitur manakah untuk membedakan varian tipe dalam blok switch/if',
        'Sebuah properti literal umum (common literal property) yang bernilai unik pada setiap interface anggota union',
        ['Nama parameter fungsi pembungkus objek', 'Urutan deklarasi interface di dalam file source code', 'Pemeriksaan menggunakan keyword typeof pada runtime'],
        'Desain pattern Discriminated Unions (TypeScript Handbook) menggunakan properti singleton bernilai konstan (seperti "type" atau "kind") untuk memungkinkan compiler menyimpulkan tipe spesifik secara aman dalam percabangan.',
    ],
    [
        'Utility Type TypeScript manakah yang digunakan untuk membuat tipe baru dengan mengubah seluruh properti pada tipe asal menjadi opsional (optional / boleh undefined)',
        'Partial<T>',
        ['Required<T>', 'Readonly<T>', 'Pick<T, K>'],
        'TypeScript menyediakan Partial<T> yang memetakan setiap properti T dan menambahkan modifier tanda tanya (?) sehingga semua field menjadi opsional (TypeScript Utility Types Reference).',
    ],
    [
        'Utility Type manakah yang digunakan untuk mengkonstruksi tipe baru dengan memilih sebagian subset properti K yang ditentukan dari tipe induk T',
        'Pick<T, K>',
        ['Omit<T, K>', 'Extract<T, U>', 'Exclude<T, U>'],
        'Pick<T, K> mengambil properti kunci K dari T, sedangkan kebalikannya adalah Omit<T, K> yang menghapus kunci K dari T (TypeScript Standard Handbook).',
    ],
    [
        'Dalam penulisan generic function "function getFirst<T>(items: T[]): T", fungsi dari identifier <T> adalah',
        'Sebagai type parameter (type variable) yang menangkap tipe data aktual yang diberikan saat pemanggilan fungsi sehingga integritas tipe tetap terjaga',
        ['Mengonversi tipe data runtime menjadi tipe generic string', 'Memaksa array items untuk hanya menerima data bertipe tuple', 'Mendeklarasikan bahwa fungsi tersebut berjalan secara asynchronous'],
        'Generics pada TypeScript memungkinkan komponen atau fungsi bekerja pada berbagai tipe data tanpa mengorbankan type safety, bertindak sebagai placeholder tipe yang ditentukan saat pemanggilan (ACM Computing Surveys on Type Systems).',
    ],
    [
        'Apa kegunaan keyword "as const" (const assertion) yang disematkan pada akhir deklarasi objek literal di TypeScript',
        'Mengunci seluruh properti objek menjadi readonly dan mempersempit (narrow) tipe data primitif menjadi tipe literal spesifiknya',
        ['Mencegah objek tersebut diubah nilainya oleh compiler JavaScript runtime via Object.seal()', 'Mengubah objek menjadi format string konstan pada output JavaScript', 'Menghilangkan semua tipe method yang ada di dalam objek'],
        'Const assertions (TypeScript 3.4+) mencegah pelebaran tipe (type widening) sehingga literal string tidak melebar menjadi tipe "string" umum dan setiap properti secara rekursif menjadi readonly.',
    ],
    [
        'Kapan developer sebaiknya menggunakan custom "Type Guard" fungsi dengan predikat pengembalian "pet is Dog" (type predicate) di TypeScript',
        'Ketika pemeriksaan tipe runtime bawaan (typeof atau instanceof) tidak cukup fleksibel untuk memvalidasi struktur objek kompleks',
        ['Ketika developer ingin menghentikan kompilasi TypeScript secara sengaja', 'Ketika fungsi harus mengembalikan nilai boolean tanpa pengecekan tipe', 'Ketika fungsi menerima input yang tidak diketahui tipenya sama sekali'],
        'User-Defined Type Guards dengan format "parameterName is Type" memberitahu compiler bahwa jika fungsi mengembalikan nilai true, tipe variabel di blok tersebut telah terverifikasi sebagai Type tersebut (TypeScript Handbook).',
    ],
    [
        'Manakah perbedaan utama antara "interface" dan "type alias" di TypeScript terkait kemampuan deklarasi ulang (re-declaration)',
        'Interface mendukung declaration merging (penggabungan deklarasi dengan nama yang sama secara otomatis), sedangkan type alias akan melempar error duplicate identifier',
        ['Type alias mendukung inheritance menggunakan keyword extends, sedangkan interface tidak bisa', 'Interface dapat digunakan untuk mendeklarasikan union types, sedangkan type alias tidak bisa', 'Interface otomatis terhapus saat kompilasi, sedangkan type alias tetap ada di runtime JavaScript'],
        'Declaration merging adalah fitur unik interface di mana deklarasi ganda dengan nama sama digabungkan menjadi satu. Type alias tidak dapat dideklarasikan ulang dalam scope yang sama (TypeScript Handbook on Interfaces vs Types).',
    ],
    [
        'Apa tujuan penerapan flag "noImplicitAny: true" pada file konfigurasi tsconfig.json dalam proyek enterprise',
        'Memaksa compiler melempar error ketika tipe variabel tidak dideklarasikan secara eksplisit dan compiler gagal menyimpulkan tipenya secara otomatis',
        ['Menghapus seluruh kata kunci any dari file deklarasi pustaka eksternal', 'Mencegah eksekusi kompilasi JavaScript saat ada peringatan minor', 'Mengizinkan casting tipe data tanpa batasan apa pun'],
        'Mengaktifkan noImplicitAny mencegah degradasi type safety di mana variabel yang tidak didefinisikan secara tidak sengaja mendapatkan tipe fallback any, yang merupakan pemicu utama runtime bug (Microsoft TS Best Practices).',
    ],
    [
        'Operator "keyof" dalam TypeScript menghasilkan',
        'Union dari tipe literal string atau numeric yang merepresentasikan semua kunci (keys) yang ada pada sebuah tipe objek',
        ['Array dari nilai properti objek pada saat runtime', 'Fungsi generator yang mengiterasi entri objek', 'Boolean yang menyatakan apakah properti tertentu ada di dalam interface'],
        'Operator keyof mengambil tipe objek dan memproduksi union representasi string/number dari nama-nama propertinya (misal: keyof { a: number; b: string } menghasilkan "a" | "b").',
    ],
    [
        'Tipe "never" dalam sistem tipe TypeScript merepresentasikan',
        'Nilai yang tidak akan pernah terjadi (misal fungsi yang selalu melempar error atau infinite loop, serta state yang mustahil tercapai pada exhaustive type checking)',
        ['Nilai kosong yang mirip dengan null atau undefined', 'Sebuah fungsi yang mengembalikan nilai kosong tanpa statement return', 'Variabel yang tipenya belum dianalisis oleh compiler'],
        'Tipe never adalah bottom type dalam type lattice TypeScript. Sering dimanfaatkan untuk Exhaustiveness Checking pada blok switch untuk memastikan setiap kasus union tertangani dengan sempurna.',
    ],
    [
        'Teknik "Mapped Types" dalam TypeScript memungkinkan pembuatan tipe baru dengan cara',
        'Melakukan iterasi terhadap union kunci (menggunakan operator in) untuk mendefinisikan tipe properti dari tipe baru tersebut',
        ['Menggabungkan dua file TypeScript menjadi satu modul', 'Mengonversi file antarmuka JSON menjadi tabel SQL otomatis', 'Menyimpan tipe data ke dalam struktur database Redis'],
        'Mapped types berbasis sintaks [K in keyof T] memungkinkan transformasi dinamis atas properti tipe yang ada, seperti membuat seluruh properti menjadi readonly, optional, atau nullable.',
    ],
    [
        'Utility Type "Record<K, T>" di TypeScript digunakan untuk',
        'Mengkonstruksi tipe objek yang memiliki kumpulan properti bertipe K dengan nilai masing-masing bertipe T',
        ['Mencatat log perubahan state ke dalam console browser', 'Menyimpan riwayat rekaman audio dan video pada WebRTC', 'Membuat tuple array dengan panjang elemen tetap'],
        'Record<K, T> memetakan tipe kunci K (biasanya string atau union literal) ke tipe data nilai T. Sangat umum digunakan untuk mendefinisikan kamus data (dictionary) atau lookup table bertipe aman.',
    ],
    [
        'Dalam TypeScript 4.1+, fitur "Template Literal Types" memungkinkan developer untuk',
        'Memanipulasi dan mengkombinasikan tipe string menggunakan sintaks template literal untuk menghasilkan union string yang kompleks dan presisi',
        ['Mencetak teks string langsung ke DOM tanpa parsing template', 'Menjalankan interpolasi ekspresi JavaScript pada file tsconfig.json', 'Menghubungkan aplikasi web dengan template engine Handlebars'],
        'Template Literal Types memungkinkan pembuatan tipe berbasis pola string (misal: `on${Capitalize<EventName>}` atau `${ThemeColor}-${Shade}`), membuka kapabilitas type-safety tingkat tinggi pada library design system.',
    ],
    [
        'Apa fungsi dari keyword "satisfies" yang diperkenalkan pada TypeScript 4.9',
        'Memvalidasi bahwa sebuah ekspresi memenuhi suatu tipe kontrak tertentu tanpa mengubah atau memperlebar (widen) tipe inferensi asli dari ekspresi tersebut',
        ['Melakukan type-casting paksa tanpa validasi compiler sama sekali', 'Memaksa sebuah fungsi untuk selalu mengembalikan nilai promise', 'Mengecek apakah suatu class mengimplementasikan semua abstract method'],
        'Operator satisfies memungkinkan developer mengecek kesesuaian struktur dengan tipe kontrak tanpa mengorbankan tipe inferensi yang lebih spesifik, menghindari kelemahan type assertion ("as Type") dan type annotation biasa.',
    ],
    [
        'Pada implementasi generic constraints "function merge<T extends object, U extends object>(a: T, b: U)", arti dari keyword "extends" adalah',
        'Membatasi agar type parameter T dan U harus berupa tipe yang kompatibel dengan tipe object',
        ['Mewarisi seluruh method prototype dari class Object ke dalam a dan b', 'Membuat salinan class baru dari class induk bernama object', 'Mencegah parameter a dan b menerima data array'],
        'Generic constraints menggunakan keyword extends untuk membatasi rentang tipe yang dapat diterima oleh generic type variable, memastikan operasi yang bergantung pada properti tertentu dapat dipanggil dengan aman.',
    ],
    [
        'Utility Type "ReturnType<T>" bekerja dengan cara',
        'Mengekstrak tipe data yang dikembalikan oleh fungsi bertipe T',
        ['Mengembalikan nilai fungsi pada saat runtime', 'Mengubah tipe pengembalian fungsi menjadi void', 'Membuat fungsi pembungkus yang otomatis melakukan logging'],
        'ReturnType<T> menggunakan conditional types dan keyword infer untuk menginspeksi signature tipe fungsi T dan mengisolasi tipe nilai balikan (return value) yang dihasilkan.',
    ],
    [
        'Apa implikasi dari flag "strictNullChecks: true" dalam tsconfig.json terhadap tipe data bawaan variabel',
        'Nilai null dan undefined tidak lagi dianggap valid untuk semua tipe data lain kecuali jika dideklarasikan secara eksplisit menggunakan union (misal: string | null)',
        ['Variabel bertipe number otomatis dikonversi menjadi 0 jika bernilai null', 'Semua variabel wajib memiliki nilai inisialisasi pada saat deklarasi', 'Nilai null diubah menjadi string "null" saat proses kompilasi'],
        'strictNullChecks menghilangkan apa yang disebut oleh Sir Tony Hoare sebagai "The Billion Dollar Mistake" dengan melarang penugasan implisit null/undefined ke tipe data konkret tanpa deklarasi union eksplisit.',
    ],
    [
        'Kapan developer sebaiknya menggunakan tipe "Tuple" daripada tipe "Array biasa" di TypeScript',
        'Ketika kumpulan data memiliki panjang elemen yang tetap dan setiap indeks posisi memiliki tipe data yang telah ditentukan secara spesifik (misal: [number, string])',
        ['Ketika jumlah data tidak diketahui dan dapat bertambah secara tak terbatas', 'Ketika seluruh elemen data selalu memiliki tipe data yang homogen (sama)', 'Ketika data hanya boleh diakses menggunakan method pop dan push'],
        'Tuple merepresentasikan array dengan jumlah elemen tetap di mana tipe setiap indeks diketahui secara pasti, seperti struktur yang dikembalikan oleh React hook useState ([value, setter]).',
    ],
    [
        'Fitur "Conditional Types" dalam TypeScript diekspresikan dengan sintaks yang mirip dengan ternary operator: "T extends U ? X : Y". Fitur ini memungkinkan',
        'Pemilihan tipe data secara dinamis berdasarkan hubungan keterkaitan tipe input T terhadap tipe acuan U pada tahap kompilasi',
        ['Pengecekan logika bisnis if-else pada runtime eksekusi program', 'Pemberian nilai default pada parameter fungsi jika argumen kosong', 'Penghentian program jika variabel bernilai false'],
        'Conditional types (TypeScript 2.8+) membawa kapabilitas komputasi tipe pada type-level programming, menjadi pondasi banyak utility types tingkat lanjut seperti NonNullable, Extract, dan Exclude.',
    ],
];

// =========================================================================
// MODUL 4: REACT.JS ECOSYSTEM, VIRTUAL DOM, HOOKS, STATE MANAGEMENT (20 Soal)
// =========================================================================
$reactTopics = [
    [
        'Bagaimana algoritma Reconciliation (Diffing Algorithm) pada React mengoptimasi perbandingan Virtual DOM dari kompleksitas O(n³) menjadi O(n)',
        'Dengan mengasumsikan bahwa dua elemen dari tipe yang berbeda akan menghasilkan pohon yang berbeda, dan memanfaatkan properti "key" yang stabil untuk membedakan elemen anak dalam daftar',
        ['Dengan memindai seluruh atribut DOM secara rekursif hingga tingkat daun terdalam', 'Dengan mengonversi seluruh komponen menjadi string HTML dan melakukan hashing MD5', 'Dengan menjalankan diffing secara terpisah di Web Worker pada background thread'],
        'Berdasarkan dokumentasi resmi React dan paper seminal Virtual DOM, React menggunakan dua asumsi heuristik: komponen dengan tipe berbeda menghasilkan tree berbeda, dan developer menyediakan key unik yang stabil untuk identifikasi node list.',
    ],
    [
        'Mengapa anti-pattern menggunakan "index" array sebagai "key" pada render list (arr.map((item, index) => <Item key={index} />)) dapat menimbulkan bug serius',
        'Ketika elemen list diurutkan ulang, disisipkan di awal, atau dihapus, index akan bergeser sehingga React salah mencocokkan local state komponen dan input uncontrolled dengan item data yang keliru',
        ['Index array menyebabkan browser melempar Uncaught TypeError saat rendering', 'Penggunaan index array mematikan fitur garbage collection pada memori komponen', 'Index array memaksa React merender ulang seluruh komponen induk setiap detik'],
        'Dokumentasi resmi React menekankan bahwa key harus stabil, dapat diprediksi, dan unik per item data. Pergeseran index merusak identitas komponen, memicu bug pada state internal (seperti input checkbox atau animasi).',
    ],
    [
        'Hook bawaan React "useCallback(fn, deps)" memiliki tujuan performa utama yaitu',
        'Mengembalikan memoized version dari fungsi callback yang hanya dibuat ulang jika dependensi dalam deps array mengalami perubahan nilai (referential equality)',
        ['Menjalankan fungsi callback secara asynchronous di background thread', 'Mencegah komponen induk merender ulang saat props child berubah', 'Mengunci nilai variabel global agar tidak termutasi oleh fungsi lain'],
        'useCallback mempertahankan referential equality dari instance fungsi antar siklus render. Sangat krusial saat fungsi tersebut dioper sebagai props ke komponen anak yang dibungkus React.memo.',
    ],
    [
        'Apa perbedaan tujuan mendasar antara hook "useMemo" dengan "useEffect" dalam siklus hidup komponen React',
        'useMemo dijalankan selama proses rendering untuk memoisasi hasil komputasi berat, sedangkan useEffect dijalankan setelah render selesai dan layar diperbarui untuk menangani efek samping (side effects)',
        ['useMemo hanya berjalan pada saat unmount, sedangkan useEffect berjalan saat mount', 'useMemo digunakan untuk manipulasi DOM langsung, sedangkan useEffect untuk kalkulasi matematis', 'useMemo selalu mengembalikan Promise, sedangkan useEffect mengembalikan nilai sinkron'],
        'Aturan arsitektur React menetapkan bahwa rendering harus murni (pure function tanpa side effect). Karena itu, kalkulasi mahal dimemoisasi saat render via useMemo, sementara mutasi DOM/fetching/subskripsi diisolasi dalam useEffect.',
    ],
    [
        'Dalam aturan "Rules of Hooks" React, mengapa Hooks tidak boleh dipanggil di dalam loop, percabangan if-else, atau nested functions',
        'React mengandalkan urutan pemanggilan Hooks yang identik pada setiap siklus render untuk mengasosiasikan state internal dengan hook yang bersangkutan',
        ['Interpreter JavaScript V8 tidak mengizinkan fungsi dipanggil di dalam blok kondisi', 'Hooks akan memicu deadlock pada browser jika diletakkan di dalam perulangan', 'Pemanggilan di dalam loop akan otomatis mengubah komponen menjadi class component'],
        'React menyimpan state hooks dalam linked list internal yang dipetakan berdasarkan urutan eksekusi. Perubahan urutan akibat kondisi if atau loop akan mengacaukan pemetaan pointer state React.',
    ],
    [
        'Kapan hook "useRef" lebih tepat digunakan daripada "useState" pada komponen fungsional React',
        'Ketika developer perlu menyimpan nilai yang dapat dimutasi (mutable value) tanpa memicu render ulang komponen saat nilai tersebut diperbarui, atau untuk mengakses elemen DOM langsung',
        ['Ketika nilai variabel perlu langsung ditampilkan ke antarmuka pengguna pada setiap perubahan', 'Ketika nilai data harus disinkronkan dengan URL query parameter otomatis', 'Ketika data hanya berupa tipe data boolean atau string sederhana'],
        'useRef mengembalikan plain JavaScript object { current: ... } yang bertahan sepanjang masa hidup komponen. Mengubah .current tidak memicu re-render, berbeda dengan setter pada useState (React Docs).',
    ],
    [
        'Fitur "React.memo" adalah Higher-Order Component yang mengoptimasi performa komponen dengan cara',
        'Mencegah re-render komponen anak jika props yang diterimanya tidak mengalami perubahan berdasarkan shallow comparison',
        ['Menyimpan salinan DOM komponen ke dalam cache browser IndexedDB', 'Mengubah seluruh state komponen menjadi global state otomatis', 'Menghapus event listener komponen saat pengguna menggulir layar'],
        'React.memo membandingkan prevProps dan nextProps secara dangkal (shallow). Jika identik, React melewati proses render komponen tersebut dan menggunakan kembali hasil render sebelumnya.',
    ],
    [
        'Konsep "Batching" pada pembaruan state di React 18 (Automatic Batching) memberikan efisiensi berupa',
        'Menggabungkan beberapa pembaruan state dari promises, setTimeout, dan native event handlers menjadi satu render tunggal untuk performa optimal',
        ['Membagi rendering aplikasi menjadi beberapa file bundle kecil secara otomatis', 'Mengirimkan request API secara paralel ke server dalam satu payload batch', 'Mengumpulkan semua pesan log error console dan mengirimkannya ke database'],
        'React 18 memperkenalkan Automatic Batching di semua konteks asynchronous (bukan hanya event handler React), secara drastis mengurangi jumlah render yang tidak perlu (React 18 Architecture WG).',
    ],
    [
        'Dalam arsitektur React, konsep "Lifting State Up" direkomendasikan ketika',
        'Dua atau lebih komponen sibling membutuhkan akses dan sinkronisasi terhadap data state yang sama',
        ['Komponen anak ingin memodifikasi data pada local storage secara independen', 'Sebuah komponen memiliki terlalu banyak props sehingga perlu dihapus', 'Komponen harus dipindahkan ke server-side rendering'],
        'Lifting state up memindahkan kepemilikan state ke komponen ancestor terdekat yang menaungi komponen-komponen yang membutuhkan data tersebut, mempertahankan single source of truth.',
    ],
    [
        'Fitur "React Portal" (ReactDOM.createPortal) dirancang secara spesifik untuk memecahkan kasus penggunaan',
        'Merender elemen anak (seperti modal, tooltip, atau popover) ke node DOM yang berada di luar hierarki DOM induknya tanpa kehilangan konteks event React',
        ['Menghubungkan frontend React dengan portal database pihak ketiga secara real-time', 'Membuat iframe aman yang tidak dapat diblokir oleh ekstensi browser', 'Melakukan transmisi state antar tab browser yang berbeda'],
        'Portals menyediakan cara elegan untuk menyisipkan elemen ke titik lain di document.body, membebaskan modal/tooltip dari jeratan CSS parent seperti overflow: hidden atau z-index stacking context.',
    ],
    [
        'Pada library state management "Redux Toolkit" (RTK), fungsi "createSlice" mempermudah developer dalam mengelola state karena',
        'Secara internal mengintegrasikan library Immer sehingga developer dapat menulis logika mutasi state yang tampak langsung ("mutating syntax") tetapi tetap dieksekusi secara immutable',
        ['Mengeliminasi kebutuhan akan dispatcher dan actions pada aplikasi', 'Menghubungkan aplikasi React langsung ke database PostgreSQL tanpa backend API', 'Mengonversi seluruh state menjadi format cookie terenkripsi'],
        'Redux Toolkit menggunakan Immer di dalam reducers createSlice. Ini menghilangkan boilerplate penulisan spread operator bertingkat ({ ...state, user: { ...state.user } }) yang rawan bug mutasi tidak sengaja.',
    ],
    [
        'Apa keunggulan arsitektur pustaka state management atomik seperti "Zustand" atau "Jotai" dibandingkan dengan React Context API standar untuk data yang sering berubah',
        'Komponen hanya akan merender ulang jika atom/state slice spesifik yang dilanggannya mengalami perubahan, menghindari re-render massal yang terjadi pada Context Provider',
        ['Zustand menyimpan data di server secara otomatis tanpa koneksi internet', 'Context API membutuhkan instalasi binary native di server Node.js', 'Jotai tidak mengizinkan data diubah setelah komponen di-mount'],
        'Studi re-render performa React menunjukkan bahwa Context API memicu render pada seluruh konsumen saat value provider berubah, sedangkan atomic/selector-based state managers mengisolasi re-render hanya pada subscriber terkait.',
    ],
    [
        'Kapan developer sebaiknya menggunakan hook "useLayoutEffect" sebagai pengganti "useEffect"',
        'Ketika developer perlu membaca layout DOM dan melakukan mutasi visual sinkron sebelum browser menggambar (painting) layar untuk mencegah kedipan visual (flickering)',
        ['Ketika developer ingin melakukan HTTP GET request data awal halaman', 'Ketika operasi komputasi membutuhkan waktu lebih dari 5 detik', 'Ketika aplikasi dijalankan di lingkungan Node.js server-side rendering'],
        'useLayoutEffect berjalan secara synchronous segera setelah semua mutasi DOM selesai tetapi sebelum browser melakukan paint. Ideal untuk mengukur dimensi tooltip atau animasi sebelum user melihat layout awal yang salah.',
    ],
    [
        'Komponen "Error Boundary" dalam React adalah komponen khusus yang digunakan untuk menangkap JavaScript error di pohon komponen bawahnya. Bagaimana implementasi Error Boundary dibuat pada React standar',
        'Sebagai Class Component yang mengimplementasikan lifecycle method static getDerivedStateFromError() atau componentDidCatch()',
        ['Menggunakan hook bawaan useErrorBoundary() pada functional component', 'Membungkus seluruh fungsi komponen di dalam blok try-catch biasa', 'Menambahkan atribut HTML onerror pada tag <div> pembungkus'],
        'Sampai saat ini, React secara resmi mensyaratkan Error Boundary berupa Class Component dengan lifecycle getDerivedStateFromError (untuk render UI fallback) atau componentDidCatch (untuk logging error) (React Docs).',
    ],
    [
        'Dalam paradigma React Server Components (RSC) modern, apa keunggulan mendasar dari Server Component dibanding Client Component ("use client")',
        'Server Component dieksekusi secara eksklusif di server dan tidak menyertakan dependensi kodenya ke dalam client bundle JavaScript, menghasilkan bundle size yang jauh lebih kecil',
        ['Server Component memiliki akses penuh ke browser local storage dan window events', 'Server Component mengizinkan penggunaan useState dan useEffect tanpa batasan', 'Server Component hanya dapat merender teks polos tanpa styling CSS'],
        'Paper arsitektur React Server Components (Meta) menjelaskan bahwa RSC memindahkan abstraksi berat dan data fetching ke server sehingga client hanya mengunduh respons minimal tanpa overhead bundle library parsing/formatting.',
    ],
    [
        'Dalam penulisan Custom Hook di React, konvensi penamaan fungsi dengan awalan "use" (misal: useWindowSize) bersifat wajib karena',
        'Memungkinkan plugin linter React (eslint-plugin-react-hooks) memverifikasi secara otomatis bahwa Rules of Hooks dipatuhi di dalam fungsi tersebut',
        ['Browser akan melempar SyntaxError jika fungsi yang memanggil hook tidak diawali "use"', 'Kata "use" mengalokasikan ruang memori khusus pada JavaScript engine V8', 'React menolak kompilasi JSX jika ada fungsi utilitas tanpa awalan "use"'],
        'Awalan "use" adalah konvensi resmi yang dikenali oleh tooling React Linter untuk menegakkan aturan hooks (seperti larangan conditional calling) di seluruh basis kode komponen dan custom hooks.',
    ],
    [
        'Fitur "React.Suspense" bersama dengan React.lazy() digunakan pada arsitektur frontend untuk',
        'Menerapkan Code Splitting dengan memuat komponen secara dinamis saat dibutuhkan dan menampilkan UI fallback (seperti spinner/skeleton) selama komponen diunduh',
        ['Menghentikan seluruh eksekusi kode JavaScript saat terjadi error fatal di aplikasi', 'Memperlambat render komponen agar sinkron dengan kecepatan refresh rate monitor pengguna', 'Menyimpan komponen yang tidak aktif ke dalam IndexedDB browser'],
        'Suspense mengoordinasikan pemuatan resource asynchronous (termasuk lazy-loaded components dan streaming SSR) dengan menyediakan deklaratif fallback UI saat promise sedang resolving (React Core Spec).',
    ],
    [
        'Pada library data fetching modern seperti "TanStack Query" (React Query), konsep "stale-while-revalidate" bekerja dengan cara',
        'Menyajikan data dari cache terlebih dahulu secara instan (meskipun stale), lalu melakukan background refetch untuk menyinkronkan data terbaru dengan server',
        ['Menolak request baru jika data di cache sudah berumur lebih dari 1 menit', 'Menghapus seluruh cache browser setiap kali pengguna berpindah halaman', 'Memaksa server mengirimkan seluruh tabel database setiap detik'],
        'Pola HTTP RFC 5861 stale-while-revalidate yang diadaptasi TanStack Query mengoptimalkan perceived performance: pengguna langsung melihat data cache tanpa loading spinner sementara data background diperbarui.',
    ],
    [
        'Apa yang dimaksud dengan "Prop Drilling" dalam arsitektur React dan pendekatan arsitektural apa yang paling tepat untuk mengatasinya',
        'Kondisi di mana props harus dioper melalui banyak layer komponen perantara yang sebenarnya tidak memerlukan data tersebut; diatasi dengan Context API, Composition (children), atau State Management',
        ['Kegagalan pengiriman props akibat salah penulisan nama variabel di komponen induk; diatasi dengan console.log', 'Kondisi di mana tipe data props berubah di tengah runtime; diatasi dengan TypeScript murni', 'Pengiriman props yang terlalu banyak hingga melebihi batas memori browser; diatasi dengan reload halaman'],
        'Prop drilling adalah code smell klasik dalam React di mana komponen perantara menjadi perantara pasif. Teknik component composition (inversion of control via children) atau state management global adalah solusi standar industrinya.',
    ],
    [
        'Apa fungsi dari hook "useId" yang diperkenalkan pada React 18',
        'Menghasilkan unique ID yang stabil dan konsisten antara server-side rendering (SSR) dan client hydration untuk keperluan atribut aksesibilitas form (seperti htmlFor dan aria-describedby)',
        ['Menghasilkan nomor ID primary key database otomatis untuk entitas baru', 'Menghitung identifier unik perangkat keras pengguna untuk keamanan sesi', 'Membuat UUID v4 acak pada setiap klik tombol di aplikasi'],
        'React 18 useId menyelesaikan masalah hydration mismatch klasik saat SSR di mana math.random() menghasilkan ID berbeda antara server dan client, merusak binding label form aksesibel.',
    ],
];

// =========================================================================
// MODUL 5: WEB PERF (CORE WEB VITALS), FRONTEND SECURITY & TESTING (20 Soal)
// =========================================================================
$perfSecTestTopics = [
    [
        'Metrik Core Web Vitals "LCP" (Largest Contentful Paint) mengukur kecepatan rendering halaman. Elemen apakah yang menjadi fokus pengukuran LCP dan berapa ambang batas waktu (threshold) kondisi "Good"',
        'Waktu render elemen konten visual terbesar (seperti gambar hero atau blok teks utama) di viewport; ambang batas Good adalah ≤ 2.5 detik',
        ['Waktu tunggu hingga request HTTP pertama merespons; ambang batas Good adalah ≤ 1.0 detik', 'Total ukuran file JavaScript yang diunduh browser; ambang batas Good adalah ≤ 500 KB', 'Jumlah pergeseran layout halaman saat dimuat; ambang batas Good adalah skor 0'],
        'Google Core Web Vitals menetapkan LCP sebagai representasi perceived load speed: mengukur waktu render elemen visual terbesar di atas fold. Skor di bawah 2.5 detik dikategorikan Good.',
    ],
    [
        'Metrik Core Web Vitals baru "INP" (Interaction to Next Paint) yang resmi menggantikan FID (First Input Delay) pada tahun 2024 berfokus pada pengukuran',
        'Responsivitas keseluruhan halaman dengan mencatat latensi terpanjang dari seluruh interaksi pengguna (klik, tap, keydown) sepanjang sesi halaman',
        ['Hanya interaksi klik pertama pengguna saat halaman baru terbuka', 'Kecepatan animasi scroll CSS pada halaman berdimensi panjang', 'Waktu yang dibutuhkan browser untuk mengeksekusi service worker'],
        'Spesifikasi Web Vitals 2024 memperkenalkan INP untuk mengukur responsivitas interaktif menyeluruh sepanjang siklus hidup halaman, bukan hanya latensi input pertama seperti pada metrik lama FID.',
    ],
    [
        'Metrik Core Web Vitals "CLS" (Cumulative Layout Shift) mengukur stabilitas visual halaman web. Skor CLS yang dikategorikan "Good" menurut standar Google adalah',
        'Kurang dari atau sama dengan 0.1',
        ['Kurang dari atau sama dengan 1.0 detik', 'Tepat bernilai 100%', 'Antara 0.5 hingga 1.0'],
        'CLS mengukur total skor pergeseran layout tak terduga yang terjadi sepanjang masa aktif halaman. Nilai CLS ≤ 0.1 menjamin pengalaman membaca yang stabil tanpa lonjakan konten yang mengganggu.',
    ],
    [
        'Strategi manakah yang paling efektif untuk mencegah terjadinya Cumulative Layout Shift (CLS) pada elemen gambar (<img>) responsive',
        'Selalu mencantumkan atribut "width" dan "height" eksplisit pada tag <img> atau menggunakan properti CSS "aspect-ratio"',
        ['Menggunakan gambar berformat GIF transparan', 'Membuat posisi gambar selalu position: fixed di layar', 'Menghapus seluruh padding dan margin pada kontainer gambar'],
        'Menyediakan atribut width/height atau CSS aspect-ratio memungkinkan browser menghitung aspect ratio dan memesan ruang layout (box reserve) sebelum gambar selesai diunduh, mengeliminasi layout shift (Web.dev).',
    ],
    [
        'Dalam serangan "Cross-Site Scripting" (XSS), seorang peretas menyuntikkan script berbahaya ke dalam halaman web. Manakah praktik terbaik di sisi frontend untuk mencegah celah Reflected dan Stored XSS',
        'Melakukan context-aware output encoding / HTML escaping dan memanfaatkan sanitization library terpercaya (seperti DOMPurify) sebelum merender konten HTML dinamis',
        ['Mengenkripsi seluruh kode JavaScript menggunakan obfuscator', 'Menyimpan seluruh token otentikasi di dalam cookie tanpa flag HttpOnly', 'Mengandalkan fungsi eval() untuk membersihkan karakter khusus'],
        'OWASP Top 10 menetapkan bahwa pencegahan utama XSS adalah contextual output escaping (yang otomatis dilakukan oleh JSX React pada kurung kurawal {}) dan penggunaan DOMPurify jika dangerouslySetInnerHTML wajib digunakan.',
    ],
    [
        'Header keamanan HTTP "Content Security Policy" (CSP) dikonfigurasi di web server atau via meta tag untuk',
        'Membatasi domain dan sumber daya (scripts, images, styles, connections) yang diizinkan untuk dimuat dan dieksekusi oleh browser pada halaman tersebut',
        ['Mencegah browser menyimpan cache file statis di memori', 'Memblokir pengguna yang mengakses web menggunakan koneksi VPN', 'Mengenkripsi database backend dari serangan SQL injection'],
        'CSP adalah lapisan pertahanan mendalam (defense-in-depth) standar W3C yang secara drastis memitigasi serangan XSS dan data injection dengan membatasi eksekusi inline script dan whitelist domain sumber daya.',
    ],
    [
        'Serangan "Cross-Site Request Forgery" (CSRF) memanfaatkan kredensial sesi pengguna yang tersimpan di browser untuk mengirimkan aksi tidak sah. Proteksi cookie native manakah yang paling efektif memitigasi CSRF pada browser modern',
        'Menyetel atribut cookie "SameSite=Lax" atau "SameSite=Strict"',
        ['Menyetel atribut Secure tanpa atribut HttpOnly', 'Menghapus masa kedaluwarsa cookie (Session Cookie murni)', 'Menyimpan token rahasia di dalam URL query parameter'],
        'Atribut SameSite pada cookie (IETF RFC 6265bis) membatasi pengiriman cookie pada request lintas situs (cross-site requests), memberikan mitigasi native yang sangat tangguh terhadap vektor serangan CSRF.',
    ],
    [
        'Dalam pengujian komponen React menggunakan "React Testing Library" (RTL), filosofi utama penulisan pengujian yang ditekankan oleh pustaka tersebut adalah',
        '"The more your tests resemble the way your software is used, the more confidence they can give you" (Uji perilaku dari sudut pandang pengguna, bukan menguji implementasi internal komponen)',
        ['Uji setiap state dan method private secara terisolasi menggunakan shallow rendering', 'Wajib mencapai code coverage 100% pada fungsi-fungsi utility tanpa menguji komponen visual', 'Selalu lakukan snapshot testing pada setiap perubahan tag HTML div'],
        'Kent C. Dodds (pencipta RTL) menekankan agar developer menguji antarmuka sebagaimana pengguna berinteraksi (mencari tombol berdasarkan teks/role aksesibel, bukan selector class atau state internal).',
    ],
    [
        'Query method manakah pada React Testing Library yang direkomendasikan sebagai prioritas utama (highest priority) untuk mencari elemen interaktif seperti tombol input form',
        'getByRole (misal: getByRole("button", { name: /kirim/i }))',
        ['getByTestId', 'querySelector dengan class name', 'getByText'],
        'Dokumentasi resmi RTL menetapkan getByRole sebagai prioritas pertama karena menguji aksesibilitas DOM secara langsung sebagaimana screen reader dan pengguna bernavigasi.',
    ],
    [
        'Teknik optimasi bundle JavaScript "Tree Shaking" bekerja efektif jika kode memenuhi persyaratan',
        'Menggunakan sintaks ES Modules statis (import/export) dan package.json mencantumkan properti "sideEffects: false"',
        ['Semua file JavaScript digabungkan menjadi satu file raksasa secara monolitik', 'Menggunakan method eval() untuk mengimpor pustaka pihak ketiga', 'Mengompilasi kode ke target ECMAScript 3'],
        'Tree Shaking mengandalkan analisis dependensi statis ES Modules untuk mendeteksi dan membuang kode yang tidak pernah diimpor/dieksekusi (dead code), dengan flag sideEffects memastikan modul aman dibuang (Webpack Documentation).',
    ],
    [
        'Apa tujuan penerapan teknik "Code Splitting" berbasis rute (Route-based Code Splitting) pada aplikasi Single Page Application (SPA)',
        'Memecah bundle JavaScript menjadi potongan-potongan terpisah sehingga browser hanya mengunduh kode yang diperlukan untuk halaman yang sedang aktif saat ini',
        ['Mengurangi jumlah baris kode di database server backend', 'Mengonversi komponen React menjadi kode mesin biner', 'Mencegah script CSS menimpa aturan styling global'],
        'Google Web Performance & React Docs menyarankan Route-based splitting agar pengguna tidak terbebani mengunduh seluruh kode aplikasi (misal halaman dashboard admin saat hanya membuka landing page).',
    ],
    [
        'Dalam pengujian frontend, apa perbedaan peran mendasar antara "Mocking" dengan "Spreading/Stubbing"',
        'Mocking membuat objek/fungsi tiruan yang mencatat pemanggilan dan dapat memverifikasi ekspektasi interaksi, sedangkan Stubbing hanya menyediakan data balikan yang telah ditentukan sebelumnya tanpa verifikasi interaksi mendalam',
        ['Mocking hanya digunakan di unit test, sedangkan Stubbing hanya di E2E testing', 'Mocking dijalankan oleh browser, sedangkan Stubbing dijalankan oleh compiler TypeScript', 'Keduanya identik dan tidak memiliki perbedaan definisi dalam rekayasa perangkat lunak'],
        'Menurut Martin Fowler (Mocks Aren\'t Stubs), perbedaan utama terletak pada cara verifikasi: stub menggunakan state verification, sedangkan mock menggunakan behavior verification.',
    ],
    [
        'Pada implementasi Service Worker untuk Progressive Web App (PWA), strategi caching "Cache First (Cache Falling Back to Network)" paling ideal diterapkan untuk resource jenis',
        'Aset statis berversi (versioned/hashed static assets) seperti file bundle JS, CSS, dan web fonts yang tidak pernah berubah kontennya',
        ['Data saldo akun bank pengguna yang berubah setiap detik', 'Endpoint API login dan autentikasi token', 'Halaman berita breaking news real-time'],
        'Google Workbox Architecture Guide merekomendasikan strategi Cache First untuk aset immutable ber-hash: aset disajikan instan dari cache lokal, menghemat bandwidth dan mempercepat load secara dramatis.',
    ],
    [
        'Dalam tool audit performa "Google Lighthouse", apa yang dimaksud dengan metrik "TBT" (Total Blocking Time)',
        'Total durasi waktu antara FCP dan TTI di mana main thread browser terblokir oleh Long Tasks (tugas yang memakan waktu > 50 ms)',
        ['Total waktu yang dibutuhkan koneksi internet untuk mengunduh seluruh gambar', 'Jumlah detik di mana pengguna tidak menyentuh layar aplikasi', 'Waktu server backend memproses query database sebelum mengirimkan respons'],
        'TBT mengukur akumulasi waktu di atas 50ms dari seluruh long task di main thread antara First Contentful Paint dan Time to Interactive, merefleksikan tingkat keparahan blocking sebelum halaman responsif (Web.dev).',
    ],
    [
        'Format gambar modern "WebP" dan "AVIF" memiliki keunggulan komparatif dibanding format warisan "JPEG" dan "PNG" dalam hal',
        'Memberikan rasio kompresi yang jauh lebih tinggi (ukuran file 30%-50% lebih kecil) dengan kualitas visual yang setara atau lebih baik, serta mendukung transparansi dan animasi',
        ['Dapat diedit langsung menggunakan kode CSS tanpa software desain', 'Secara otomatis mengubah background gambar menjadi transparan di browser lama', 'Menghilangkan kebutuhan akan tag img pada file HTML'],
        'Riset Google & Alliance for Open Media (AOMedia) membuktikan AVIF dan WebP menghasilkan efisiensi kompresi data superior, berkontribusi signifikan terhadap penurunan metrik LCP pada web performance.',
    ],
    [
        'Bagaimana atribut "rel=\'noopener noreferrer\'" pada link eksternal (<a target="_blank" href="...">) melindungi keamanan aplikasi frontend',
        'Mencegah halaman eksternal yang baru dibuka mengakses objek "window.opener" halaman asal, sehingga mencegah serangan tabnabbing (pengalihan halaman asal ke situs phishing)',
        ['Mencegah peretas mengunduh file gambar yang ada di halaman web', 'Mengenkripsi teks URL link agar tidak terbaca oleh search engine', 'Menghalangi browser membuka tab baru'],
        'MDN Security Docs dan OWASP mencatat bahwa tanpa noopener, halaman target dapat memanipulasi window.opener.location halaman asal untuk menipu pengguna (Reverse Tabnabbing). Browser modern kini mengaktifkannya secara default pada target="_blank".',
    ],
    [
        'Penggunaan fungsi "crypto.getRandomValues()" dari Web Crypto API lebih disarankan dibanding "Math.random()" untuk keperluan keamanan karena',
        'crypto.getRandomValues() adalah Cryptographically Secure Pseudorandom Number Generator (CSPRNG) yang tidak dapat diprediksi secara matematis, sedangkan Math.random() bersifat pseudo-random deterministik',
        ['crypto.getRandomValues() menghasilkan angka desimal yang selalu genap', 'Math.random() membutuhkan izin akses kamera dari pengguna browser', 'crypto.getRandomValues() hanya dapat dipanggil di dalam Service Worker'],
        'W3C Web Cryptography API mensyaratkan generator berbasis entropy sistem operasi yang aman dari prediksi serangan kriptografi, berbeda dengan algoritma PRNG (seperti xorshift128+) pada Math.random().',
    ],
    [
        'Dalam arsitektur micro-frontend atau modul terisolasi, bagaimana mekanisme "CSS Modules" mencegah benturan styling (style collision) global',
        'Dengan menghasilkan nama class unik secara otomatis (misal: Button_button__3xY1z) pada saat proses build (compile time)',
        ['Menjalankan script CSS di dalam iframe terpisah untuk setiap komponen', 'Mengubah seluruh selector CSS menjadi inline style pada tag HTML', 'Menghapus deklarasi CSS yang memiliki nama sama secara otomatis'],
        'CSS Modules memetakan class lokal ke nama class yang di-mangle secara unik saat build time, memberikan scoped styling tanpa overhead performa runtime Shadow DOM.',
    ],
    [
        'Pada pengujian End-to-End (E2E) menggunakan framework modern seperti "Playwright" atau "Cypress", apa keunggulan mekanisme "Auto-waiting" dibanding hardcoded delay (sleep/waitForTimeout)',
        'Framework secara otomatis menunggu elemen DOM siap, terlihat (visible), dan stabil sebelum melakukan aksi (seperti klik), mengeliminasi flaky tests akibat latensi jaringan dinamis',
        ['Auto-waiting mempercepat kecepatan refresh layar monitor komputer developer', 'Auto-waiting menghapus kebutuhan akan assertions pada file test', 'Auto-waiting menjalankan pengujian tanpa membuka instance browser'],
        'Dokumentasi resmi Playwright & Cypress menjelaskan bahwa auto-waiting mengeliminasi race conditions dan flaky tests dengan memvalidasi serangkaian actionability checks sebelum aksi dieksekusi.',
    ],
    [
        'Fitur "Subresource Integrity" (SRI) pada tag script (<script src="..." integrity="sha384-..." crossorigin="anonymous">) memberikan perlindungan keamanan berupa',
        'Memvalidasi bahwa hash kriptografi dari file yang diunduh dari CDN cocok dengan atribut integrity, mencegah eksekusi kode jika file CDN telah dimanipulasi atau diretas pihak ketiga',
        ['Mempercepat proses parsing JavaScript oleh V8 engine sebesar 50%', 'Menyimpan script ke dalam LocalStorage secara otomatis', 'Mengizinkan script berjalan tanpa batasan Content Security Policy'],
        'W3C SRI Specification melindungi aplikasi web dari kompromi Content Delivery Network (CDN compromise) dengan memverifikasi digest sha256/sha384/sha512 file script sebelum dieksekusi di browser.',
    ],
];

// Gabungkan semua modul menjadi 100 soal
$index = 1;
foreach ([$htmlCssTopics, $jsCoreTopics, $typeScriptTopics, $reactTopics, $perfSecTestTopics] as $module) {
    foreach ($module as $item) {
        $questions[] = $make($item[0], $item[1], $item[2], $item[3], $index++);
    }
}

return $questions;
