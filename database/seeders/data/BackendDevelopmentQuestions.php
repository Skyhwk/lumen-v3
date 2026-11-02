<?php

/**
 * 100 Soal Standar Industri untuk Kategori: BACKEND_DEVELOPMENT
 * Manager Scope - Dedi Wibowo (IT Programming Manager)
 * 
 * Topik:
 * 1. Software Architecture, Clean Architecture, SOLID Principles, Design Patterns & DDD (20 soal)
 * 2. API Design, RESTful Standard RFC 7231, GraphQL, gRPC, Idempotency & HTTP Protocol (20 soal)
 * 3. Relational Database Design, SQL Optimization, B-Tree Indexing, ACID & Transaction Isolation (20 soal)
 * 4. Caching Strategies (Redis), NoSQL, Message Queues (RabbitMQ/Kafka) & Asynchronous Processing (20 soal)
 * 5. Backend Security (OWASP Top 10, JWT/OAuth2, Hashing), Concurrency & DevOps (Docker/CI-CD/Nginx) (20 soal)
 *
 * Referensi: IEEE Software, ACM Computing Surveys, Martin Fowler, Robert C. Martin (Clean Architecture),
 * Fielding REST Dissertation, OWASP Top 10, C.J. Date (Database Systems), RFC Standards.
 */

$helpers = require __DIR__ . '/QuestionSeederHelpers.php';
$make = $helpers['makeQuestion'];

$questions = [];

// =========================================================================
// MODUL 1: ARCHITECTURE, SOLID, CLEAN CODE, DESIGN PATTERNS, DDD (20 Soal)
// =========================================================================
$archPatternsTopics = [
    [
        'Sebuah kelas "OrderService" menangani validasi pesanan, perhitungan diskon, penyimpanan database, pengiriman email notifikasi, dan pencetakan PDF faktur. Prinsip SOLID manakah yang dilanggar secara langsung oleh desain ini',
        'Single Responsibility Principle (SRP)',
        ['Open/Closed Principle (OCP)', 'Liskov Substitution Principle (LSP)', 'Interface Segregation Principle (ISP)'],
        'Robert C. Martin (Clean Architecture) mendefinisikan Single Responsibility Principle: sebuah modul atau kelas seharusnya hanya memiliki satu alasan untuk berubah (one reason to change). Menggabungkan persistence, email, dan rendering PDF melanggar SRP.',
    ],
    [
        'Prinsip "Open/Closed Principle" (OCP) dalam perancangan perangkat lunak berorientasi objek menganjurkan agar entitas perangkat lunak',
        'Terbuka untuk perluasan (extension) fitur baru, tetapi tertutup untuk modifikasi (modification) kode yang sudah teruji',
        ['Terbuka untuk modifikasi langsung kode inti, tetapi tertutup untuk penambahan class baru', 'Memiliki aksesibilitas public pada semua method dan private pada properti data', 'Dapat diakses oleh siapa saja tanpa autentikasi API'],
        'Menurut Bertrand Meyer & Robert C. Martin, OCP meminimalkan risiko regresi dengan menggunakan abstraksi/interface sehingga penambahan perilaku baru dilakukan dengan membuat kelas baru tanpa mengubah kode lama yang stabil.',
    ],
    [
        'Sebuah class "Square" (Persegi) mewarisi class "Rectangle" (Persegi Panjang). Ketika setter setWidth() pada Square secara otomatis mengubah height, fungsi kalkulasi area yang mengasumsikan Rectangle independen menghasilkan bug. Pelanggaran prinsip SOLID apakah kasus ini',
        'Liskov Substitution Principle (LSP)',
        ['Dependency Inversion Principle (DIP)', 'Single Responsibility Principle (SRP)', 'Interface Segregation Principle (ISP)'],
        'Barbara Liskov (1987) merumuskan LSP: objek dari tipe turunan harus dapat menggantikan objek tipe dasar tanpa merusak kebenaran program. Persegi yang memutasi height saat setWidth melanggar invarian Rectangle.',
    ],
    [
        'Prinsip "Dependency Inversion Principle" (DIP) menyatakan bahwa high-level modules tidak boleh bergantung langsung pada low-level modules. Keduanya harus bergantung pada',
        'Abstraksi (Interface / Abstract Class)',
        ['Database ORM secara langsung', 'Variabel global lingkungan server', 'Konfigurasi file .env lokal'],
        'DIP (bagian dari SOLID) membalik arah ketergantungan: domain logic inti (high-level) tidak boleh terikat langsung dengan detail implementasi seperti MySQL atau Sendgrid (low-level), melainkan bergantung pada interface abstraksi.',
    ],
    [
        'Dalam Clean Architecture (Hexagonal Architecture / Ports and Adapters), aturan ketergantungan (Dependency Rule) yang paling fundamental menetapkan bahwa',
        'Ketergantungan kode hanya boleh mengarah ke dalam (inward), di mana domain logic terdalam tidak mengetahui detail framework, database, atau UI di lapisan luar',
        ['Semua lapisan kode bebas saling memanggil satu sama lain secara sirkular', 'Database berada di pusat arsitektur dan mengontrol semua entitas domain bisnis', 'Lapisan domain harus mengimpor controller dan route handler'],
        'Alistair Cockburn (Hexagonal) dan Robert C. Martin (Clean Architecture) menegaskan bahwa domain bisnis berada di inti dan tidak boleh memiliki dependensi ke lapisan terluar (DB, HTTP Framework, Third-party SDKs).',
    ],
    [
        'Design Pattern manakah yang paling tepat digunakan ketika sebuah sistem pembayaran perlu mendukung berbagai gateway (Midtrans, Xendit, Stripe) dengan antarmuka seragam yang dapat ditukar secara dinamis pada saat runtime',
        'Strategy Pattern',
        ['Observer Pattern', 'Singleton Pattern', 'Decorator Pattern'],
        'Gang of Four (GoF) mendefinisikan Strategy Pattern untuk mengemas keluarga algoritma/penyedia ke dalam objek-objek terpisah yang mengimplementasikan interface bersama, memungkinkan pertukaran algoritma pembayaran secara runtime.',
    ],
    [
        'Pola arsitektur "Repository Pattern" dalam pengembangan aplikasi backend berfungsi untuk',
        'Menyediakan lapisan abstraksi antara lapisan domain logic dengan lapisan persistensi data (database), memperlakukan koleksi data seolah berada di memori',
        ['Menyimpan salinan file backup database secara otomatis ke cloud storage', 'Menghubungkan repository Git lokal dengan repository remote di GitHub', 'Mengatur antrian background job menggunakan Redis'],
        'Martin Fowler (Patterns of Enterprise Application Architecture) mendefinisikan Repository sebagai mediator antara lapisan domain dan pemetaan data, mengisolasi query database dari logika bisnis.',
    ],
    [
        'Dalam Domain-Driven Design (DDD), apa perbedaan mendasar antara entitas "Entity" dan "Value Object"',
        'Entity memiliki identitas unik (ID) yang bertahan sepanjang siklus hidupnya, sedangkan Value Object tidak memiliki identitas dan didefinisikan murni oleh nilai atribut-atributnya (bersifat immutable)',
        ['Entity disimpan di database NoSQL, sedangkan Value Object disimpan di database SQL', 'Value Object dapat diubah nilainya kapan saja, sedangkan Entity dilarang diubah', 'Entity selalu berupa tipe data primitif, sedangkan Value Object berupa tabel relasional'],
        'Eric Evans (Domain-Driven Design) membedakan Entity (memiliki identitas kontinu seperti User dengan ID unik) dan Value Object (deskriptif dan dapat ditukar jika nilainya sama, misal Alamat atau Money).',
    ],
    [
        'Design pattern GoF manakah yang digunakan untuk memisahkan konstruksi objek yang kompleks dari representasinya, sehingga proses konstruksi yang sama dapat menghasilkan representasi yang berbeda',
        'Builder Pattern',
        ['Adapter Pattern', 'Proxy Pattern', 'Composite Pattern'],
        'Builder Pattern (GoF) sangat berguna untuk membangun objek dengan banyak parameter konfigurasi atau langkah-langkah bertahap (step-by-step), menghindari "telescoping constructor" anti-pattern.',
    ],
    [
        'Dalam arsitektur microservices, pola "Saga Pattern" diterapkan untuk menyelesaikan tantangan',
        'Pengelolaan transaksi terdistribusi yang melibatkan banyak layanan mandiri tanpa menggunakan two-phase commit (2PC) yang lambat',
        ['Penyusunan dokumentasi API secara otomatis dari route controller', 'Pencegahan serangan DDoS pada API Gateway publik', 'Penyimpanan sesi login pengguna di dalam distributed cache'],
        'Chris Richardson (Microservices Patterns) menjelaskan bahwa Saga Pattern mengoordinasikan transaksi terdistribusi melalui serangkaian transaksi lokal yang saling terhubung via event/choreography atau orchestrator, lengkap dengan compensating transactions jika gagal.',
    ],
    [
        'Pola arsitektur "CQRS" (Command Query Responsibility Segregation) mengusulkan pemisahan antara',
        'Operasi yang memutasi/mengubah data (Commands) dan operasi yang hanya membaca data (Queries), sering kali menggunakan model data atau database yang terpisah',
        ['Kode yang ditulis menggunakan bahasa PHP dan kode yang ditulis menggunakan Node.js', 'Request yang berasal dari perangkat mobile dan request yang berasal dari browser desktop', 'File konfigurasi server staging dan file konfigurasi server production'],
        'Martin Fowler dan Greg Young memperkenalkan CQRS untuk mengoptimalkan sistem berskala besar: model penulisan (write-side) dioptimalkan untuk integritas dan validasi bisnis, sedangkan model pembacaan (read-side) dioptimalkan untuk kecepatan query dan denormalisasi.',
    ],
    [
        'Design pattern "Decorator Pattern" memiliki kegunaan utama dalam rekayasa perangkat lunak untuk',
        'Menambahkan tanggung jawab atau perilaku baru ke sebuah objek secara dinamis pada saat runtime tanpa memodifikasi kode kelas aslinya atau menggunakan pewarisan (subclassing) berlebih',
        ['Menghias tampilan antarmuka web dengan animasi CSS modern', 'Mencegah pembuatan lebih dari satu instance objek di dalam memori aplikasi', 'Menghubungkan dua antarmuka yang tidak kompatibel agar dapat berkomunikasi'],
        'Decorator Pattern (GoF) mengusung prinsip "Composition over Inheritance", membungkus (wrapping) objek target untuk menyuntikkan fungsionalitas tambahan (seperti logging, caching, atau kompresi) secara modular.',
    ],
    [
        'Dalam arsitektur event-driven, apa yang dimaksud dengan sifat "Idempotent Consumer" pada pemrosesan pesan (message processing)',
        'Konsumen pesan mampu memproses pesan yang sama berulang kali (akibat network retry atau duplikasi) tanpa menghasilkan efek samping ganda pada status sistem',
        ['Konsumen pesan hanya dapat memproses pesan satu kali sebelum mati secara otomatis', 'Pesan yang masuk otomatis dienkripsi menggunakan kunci privat per transaksi', 'Pesan dijamin selalu tiba di antrian dengan latensi di bawah 1 milidetik'],
        'Enterprise Integration Patterns (Gregor Hohpe) mencatat bahwa dalam jaringan terdistribusi dengan jaminan "at-least-once delivery", konsumen harus idempotent (misal menggunakan deduplication table / idempotency key) untuk mencegah double charging.',
    ],
    [
        'Apa tujuan penerapan pola "Circuit Breaker Pattern" pada komunikasi antar-layanan di backend',
        'Mencegah kegagalan kaskade (cascading failure) dengan menghentikan sementara pengiriman request ke layanan eksternal yang sedang down dan langsung mengembalikan fallback response',
        ['Mempercepat kecepatan koneksi kabel fiber optic antar-data center', 'Memutuskan koneksi pengguna yang melanggar batasan rate limit API', 'Membagi trafik pengguna ke server yang memiliki spesifikasi CPU tertinggi'],
        'Michael Nygard (Release It!) merumuskan Circuit Breaker: saat tingkat kegagalan dependensi melebihi ambang batas, sirkuit "terbuka" (Open) dan request langsung dialihkan ke fallback tanpa menunggu timeout yang menghabiskan resource thread pool.',
    ],
    [
        'Konsep "Anemic Domain Model" yang diidentifikasi oleh Martin Fowler sebagai sebuah anti-pattern terjadi ketika',
        'Kelas domain hanya berisi data properti (getter/setter murni) tanpa logika bisnis, sedangkan seluruh logika bisnis diletakkan secara terpisah di dalam service layer',
        ['Sebuah tabel database tidak memiliki foreign key penghubung ke tabel lain', 'Model domain memiliki terlalu banyak metode sehingga memakan memori RAM server', 'Sebuah controller memiliki baris kode lebih dari 1.000 baris'],
        'Martin Fowler mengkritik Anemic Domain Model karena bertentangan dengan esensi OOP: memisahkan data dari perilaku menciptakan kode prosedural yang tersebar di service layer dan sulit dirawat dibanding Rich Domain Model.',
    ],
    [
        'Dalam perancangan REST API dan transaksi finansial, apa fungsi utama dari pengiriman header HTTP "Idempotency-Key"',
        'Mengidentifikasi request pembuatan sumber daya secara unik sehingga pengiriman ulang request yang identik (akibat timeout jaringan) tidak akan membuat data ganda di server',
        ['Mengenkripsi password pengguna sebelum dikirimkan ke server backend', 'Menentukan durasi waktu sesi login pengguna di browser klien', 'Menghapus data transaksi lama yang sudah kedaluwarsa secara otomatis'],
        'Standar IETF draft-ietf-httpapi-idempotency-key dan arsitektur Stripe API menggunakan header Idempotency-Key untuk menjamin keselamatan transaksi jaringan yang rentan terputus (at-most-once execution).',
    ],
    [
        'Kapan developer sebaiknya menggunakan design pattern "Observer Pattern" (Pub/Sub)',
        'Ketika terjadi perubahan status pada satu objek dan objek-objek lain yang bergantung perlu diberitahu serta diperbarui secara otomatis tanpa ikatan kopling ketat (loose coupling)',
        ['Ketika sebuah objek harus disalin secara persis ke dalam database relasional', 'Ketika sebuah algoritma harus dijalankan secara paralel di多 thread CPU', 'Ketika sebuah class hanya boleh memiliki satu instance tunggal di seluruh aplikasi'],
        'Observer Pattern (GoF) mendefinisikan relasi one-to-many antar objek sehingga saat subject berubah status, semua observer yang terdaftar ternotifikasi secara terisolasi tanpa saling mengenal implementasi konkret masing-masing.',
    ],
    [
        'Pola arsitektur "Outbox Pattern" (Transactional Outbox) dirancang untuk memecahkan masalah',
        'Menjamin konsistensi antara pembaruan database lokal dengan penerbitan pesan ke Message Broker (seperti Kafka/RabbitMQ) dalam satu transaksi atomik',
        ['Mengirimkan email keluar dari server tanpa menggunakan protokol SMTP', 'Menyimpan file attachment email di luar server utama ke Amazon S3', 'Mencegah pengguna mengirimkan pesan spam ke sistem customer service'],
        'Transactional Outbox Pattern menyimpan status bisnis dan pesan event ke dalam tabel outbox di database yang sama dalam satu transaksi DB lokal, lalu background worker membacanya untuk dikirim ke broker (Microservices Patterns).',
    ],
    [
        'Apa yang dimaksud dengan prinsip "Fail-Fast" dalam penulisan logika backend',
        'Segera melempar exception atau menghentikan eksekusi pada langkah sedini mungkin saat ditemukan input tidak valid atau kondisi prasyarat tidak terpenuhi',
        ['Membiarkan sistem terus berjalan dengan data salah hingga menemukan solusi otomatis', 'Mematikan server produksi setiap kali ditemukan error minor di log aplikasi', 'Mengabaikan validasi input agar response API dapat dikembalikan lebih cepat'],
        'Prinsip Fail-Fast (IEEE Software) menyederhanakan debugging dan mencegah kerusakan integritas data lebih lanjut: error ditangkap di awal alur (guard clauses) sebelum operasi persistensi atau komputasi mahal dijalankan.',
    ],
    [
        'Pola "Adapter Pattern" dalam refaktorisasi sistem legacy berfungsi untuk',
        'Mengonversi antarmuka (interface) dari sebuah kelas yang sudah ada menjadi antarmuka lain yang diharapkan oleh klien, memungkinkan kelas-kelas yang tidak kompatibel bekerja bersama',
        ['Mengganti kabel koneksi perangkat keras server secara otomatis', 'Mengadaptasi database MySQL agar dapat membaca sintaks query MongoDB langsung', 'Mengubah resolusi tampilan antarmuka web pada monitor pengguna'],
        'Adapter Pattern (GoF) bertindak sebagai penerjemah (wrapper) antara dua sistem atau modul yang memiliki kontrak antarmuka berbeda tanpa perlu merombak kode sumber dari modul asli.',
    ],
];

// =========================================================================
// MODUL 2: RESTFUL API, RFC 7231, GRAPHQL, GRPC, PROTOCOLS (20 Soal)
// =========================================================================
$apiProtocolTopics = [
    [
        'Berdasarkan spesifikasi resmi HTTP RFC 7231, metode HTTP manakah yang didefinisikan sebagai "Safe" (tidak menghasilkan perubahan status/mutasi pada server)',
        'GET, HEAD, dan OPTIONS',
        ['POST, PUT, dan DELETE', 'GET, POST, dan PATCH', 'PUT, DELETE, dan TRACE'],
        'RFC 7231 Section 4.2.1 mendefinisikan Safe Methods sebagai metode yang semantiknya hanya bersifat read-only bagi klien (seperti GET, HEAD, OPTIONS). Menjalankannya tidak memicu perubahan state pada resource origin server.',
    ],
    [
        'Apa perbedaan semantik yang benar antara HTTP method "PUT" dan "PATCH" menurut standar RFC 5789 & RFC 7231',
        'PUT menggantikan seluruh representasi resource target secara utuh (full replacement), sedangkan PATCH menerapkan modifikasi parsial (partial modification) pada resource',
        ['PUT digunakan untuk membuat resource baru saja, sedangkan PATCH hanya untuk menghapus data', 'PUT bersifat non-idempotent, sedangkan PATCH selalu bersifat idempotent murni', 'PUT hanya menerima payload format XML, sedangkan PATCH menerima JSON'],
        'IETF RFC 5789 mendefinisikan PATCH untuk memodifikasi sebagian atribut resource, sedangkan RFC 7231 menetapkan PUT untuk menimpa representasi resource secara keseluruhan dengan payload baru.',
    ],
    [
        'Kapan sebuah HTTP status code "409 Conflict" paling tepat dikembalikan oleh API server kepada klien',
        'Ketika request tidak dapat diproses karena terjadi konflik dengan status sumber daya saat ini (misal duplikasi email unik atau konflik versi concurrency/optimistic lock)',
        ['Ketika token autentikasi pengguna salah atau telah kedaluwarsa', 'Ketika endpoint URL yang diminta tidak ditemukan di routing server', 'Ketika server internal mengalami kehabisan kapasitas memori RAM'],
        'RFC 7231 Section 6.5.8 mendefinisikan 409 Conflict: kondisi di mana request berbenturan dengan state server saat ini, paling umum terjadi pada pelanggaran unique constraint atau collision concurrency control.',
    ],
    [
        'Perbedaan mendasar antara HTTP status code "401 Unauthorized" dengan "403 Forbidden" adalah',
        '401 Unauthorized menandakan klien belum terotentikasi (kredensial hilang atau tidak valid), sedangkan 403 Forbidden menandakan identitas klien diketahui tetapi tidak memiliki hak izin akses (insufficient privileges)',
        ['401 digunakan untuk browser mobile, sedangkan 403 digunakan untuk browser desktop', '401 menandakan kesalahan server internal, sedangkan 403 menandakan kesalahan database', 'Keduanya identik dan boleh digunakan secara bergantian menurut RFC'],
        'Berdasarkan RFC 7235, 401 mengindikasikan otentikasi diperlukan (WWW-Authenticate challenge), sementara 403 menandakan server memahami siapa pemohon tetapi menolak otorisasi aksi tersebut.',
    ],
    [
        'Dalam perancangan REST API yang mematuhi Richardson Maturity Model Level 3, konsep "HATEOAS" (Hypermedia as the Engine of Application State) mengharuskan server untuk',
        'Menyertakan tautan navigasi hypermedia (hyperlinks) di dalam payload respons yang memandu klien mengenai aksi atau transisi status apa saja yang valid berikutnya',
        ['Mengirimkan seluruh file kode JavaScript frontend di dalam respons API JSON', 'Menyediakan dokumentasi Swagger di setiap header request HTTP', 'Mengharuskan klien menggunakan protokol WebSocket murni'],
        'Disertasi Roy Fielding (2000) dan Richardson Maturity Model menetapkan HATEOAS sebagai puncak arsitektur REST: respons data memuat relasi link (misal _links) yang mengarahkan klien ke aksi selanjutnya secara dinamis.',
    ],
    [
        'Manakah praktik terbaik penamaan endpoint URI untuk arsitektur RESTful yang profesional dan konsisten',
        'POST /api/v1/orders/{orderId}/items (menggunakan kata benda jamak / plural nouns dan hierarki relasional)',
        ['GET /api/v1/getAllOrdersAction', 'POST /api/v1/createNewOrderAndItems', 'DELETE /api/v1/deleteOrderById?id=10'],
        'Standar industri REST API (Google API Design Guide, Fielding) menganjurkan penggunaan plural nouns untuk koleksi resource dan memanfaatkan HTTP verbs untuk aksi, menghindari penyematan kata kerja (verbs) ke dalam URI.',
    ],
    [
        'Tantangan arsitektur klasik dalam REST API yang menjadi motivasi utama kemunculan "GraphQL" adalah',
        '"Over-fetching" (mengambil data berlebih yang tidak dibutuhkan) dan "Under-fetching" (memerlukan request berulang ke banyak endpoint untuk merakit data terkait)',
        ['Ketidakmampuan REST API untuk mengirimkan data berformat JSON', 'REST API tidak mendukung enkripsi HTTPS di tingkat protokol', 'REST API hanya dapat diakses melalui browser Chrome'],
        'GraphQL Specification (Meta, 2015) dirancang untuk memecahkan over-fetching dan under-fetching: klien memiliki kendali deklaratif untuk meminta bentuk dan field data yang spesifik dalam satu round-trip network tunggal.',
    ],
    [
        'Dalam ekosistem GraphQL, masalah performa terkenal "N+1 Query Problem" pada resolver database paling efektif diatasi dengan menggunakan library',
        'DataLoader (menerapkan batching dan per-request caching terhadap ID yang diminta)',
        ['GraphQL Playground bawaan server', 'Babel compiler pada backend', 'Redux Toolkit di server-side'],
        'DataLoader (Meta Open Source) mengumpulkan (batching) semua ID yang diminta selama satu tick eksekusi event loop dan mengeksekusinya dalam satu query SQL (misal WHERE id IN (...)), mengeliminasi loop N+1 query individual.',
    ],
    [
        'Protokol "gRPC" (Google Remote Procedure Call) menggunakan teknologi transportasi dan format serialisasi data modern berupa',
        'HTTP/2 sebagai protokol transport dan Protocol Buffers (Protobuf) sebagai format serialisasi biner yang kompak',
        ['HTTP/1.1 sebagai transport dan XML sebagai format serialisasi teks', 'WebSocket sebagai transport murni dan JSON sebagai payload serialisasi', 'Protokol UDP murni tanpa jaminan pengiriman paket'],
        'Dokumentasi resmi gRPC & CNCF menjelaskan bahwa kombinasi HTTP/2 (multiplexing, header compression, streaming) dan Protobuf (binary serialization) menghasilkan transmisi data yang jauh lebih cepat dan hemat bandwidth dibanding REST/JSON.',
    ],
    [
        'Apa keuntungan utama dari mekanisme "HTTP/2 Multiplexing" dibandingkan dengan protokol "HTTP/1.1"',
        'Dapat mengirimkan dan menerima banyak request serta respons secara paralel melalui satu koneksi TCP tunggal tanpa mengalami Head-of-Line (HoL) Blocking di tingkat aplikasi',
        ['Mengurangi ukuran gambar JPEG secara otomatis di tingkat server', 'Menghapus kebutuhan akan sertifikat SSL/TLS untuk koneksi aman', 'Mengganti alamat IP server secara dinamis setiap 5 menit'],
        'RFC 7540 mendefinisikan HTTP/2 multiplexing dengan memecah pesan menjadi frames independen yang disisipkan (interleaved) dalam satu TCP stream tunggal, mengeliminasi delay pembukaan koneksi TCP berulang pada HTTP/1.1.',
    ],
    [
        'Header HTTP "Content-Negotiation" manakah yang dikirimkan oleh klien untuk memberitahu server format MIME type apa yang dapat diterima sebagai respons',
        'Accept (misal: Accept: application/json)',
        ['Content-Type', 'User-Agent', 'Authorization'],
        'RFC 7231 Section 5.3 menetapkan header Accept untuk content negotiation: klien meminta representasi media type tertentu (JSON, XML), sedangkan Content-Type menjelaskan format data payload yang sedang dikirimkan saat itu.',
    ],
    [
        'Kapan sebuah endpoint API backend sebaiknya mengembalikan HTTP status code "202 Accepted"',
        'Ketika request telah diterima dan valid, namun pemrosesannya bersifat asinkron (misal antrian job latar belakang) dan belum selesai saat respons dikembalikan',
        ['Ketika request berhasil dan resource baru telah selesai dibuat di database', 'Ketika klien mengakses halaman login dengan kredensial yang tepat', 'Ketika server mengizinkan klien mengunduh file video'],
        'RFC 7231 Section 6.3.3 menyatakan bahwa 202 Accepted sengaja bersifat non-komitmen: request telah diterima untuk diproses lebih lanjut, tetapi hasil akhirnya belum tentu berhasil pada saat proses background tuntas.',
    ],
    [
        'Dalam arsitektur API Gateway modern, fungsi dari mekanisme "Rate Limiting" menggunakan algoritma "Token Bucket" atau "Leaky Bucket" adalah',
        'Membatasi jumlah request yang dapat dilakukan klien dalam periode waktu tertentu untuk melindungi server dari kelebihan beban (overload) dan serangan brute-force',
        ['Menghitung total tagihan biaya langganan cloud per bulan secara real-time', 'Mengubah struktur query SQL menjadi format NoSQL secara otomatis', 'Mengompresi respons JSON menggunakan algoritma Gzip sebelum dikirim'],
        'Studi rekayasa keandalan sistem (SRE) dan RFC 6585 merekomendasikan Token/Leaky Bucket untuk mengontrol laju trafik API, mengembalikan status 429 Too Many Requests saat kuota request terlampaui.',
    ],
    [
        'Fitur "Webhook" dalam arsitektur integrasi sistem modern bekerja dengan pola',
        'Reverse API (HTTP Push): server produsen mengirimkan HTTP POST request ke URL endpoint yang didaftarkan konsumen saat terjadi event tertentu di sistem produsen',
        ['Polling konstan: klien mengirimkan HTTP GET request setiap 1 detik untuk mengecek data baru', 'Membuka koneksi database langsung antar dua perusahaan yang berbeda', 'Mengirimkan pesan melalui jaringan satelit nirkabel'],
        'Webhook adalah mekanisme komunikasi event-driven standar industri yang jauh lebih efisien dibanding polling berkala: notifikasi status (misal status pembayaran lunas) dikirimkan secara instan (real-time push).',
    ],
    [
        'Header CORS (Cross-Origin Resource Sharing) "Access-Control-Allow-Origin" berfungsi untuk',
        'Memberitahu browser apakah origin (domain, protokol, port) dari halaman web pemohon diizinkan untuk membaca respons dari server backend',
        ['Mengatur hak akses pengguna berdasarkan level jabatan di database', 'Mengizinkan browser menyimpan password pengguna tanpa enkripsi', 'Menyambungkan dua server backend yang berbeda jaringan subnet'],
        'W3C Fetch & CORS Spec menetapkan bahwa pembatasan cross-origin ditegakkan oleh browser: header Access-Control-Allow-Origin memberi instruksi kepada browser untuk membuka akses data lintas domain yang aman.',
    ],
    [
        'Kapan browser secara otomatis mengirimkan request CORS bertipe "Preflight Request" (HTTP OPTIONS) sebelum request utama dijalankan',
        'Ketika request menggunakan method selain GET, HEAD, POST, atau menyertakan custom headers (seperti Authorization), atau Content-Type selain yang diizinkan standar form',
        ['Hanya ketika pengguna mengakses web melalui koneksi internet yang lambat', 'Setiap kali pengguna melakukan refresh halaman browser', 'Hanya jika ukuran file payload request lebih dari 10 MB'],
        'Browser mengirimkan preflight OPTIONS request untuk memverifikasi apakah server mengizinkan kombinasi method, headers, dan origin tersebut sebelum request mutasi aktual yang berpotensi berbahaya dieksekusi (W3C CORS).',
    ],
    [
        'Dalam implementasi API pagination untuk dataset raksasa (jutaan baris), mengapa "Cursor-based Pagination" (Keyset Pagination) lebih unggul daripada "Offset-based Pagination" (LIMIT offset, count)',
        'Cursor-based memiliki performa konstan O(1) karena memanfaatkan indeks langsung (WHERE id > last_seen_id), sedangkan Offset-based memiliki performa O(N) yang memburuk di halaman dalam karena database harus memindai dan membuang N baris sebelumnya',
        ['Offset-based pagination tidak dapat diurutkan berdasarkan tanggal', 'Cursor-based pagination tidak membutuhkan koneksi database aktif', 'Cursor-based pagination selalu mengembalikan seluruh isi tabel sekaligus'],
        'Paper evaluasi query database besar (ACM/SIGMOD) membuktikan bahwa OFFSET 1000000 memaksa engine membaca satu juta baris sebelum mengambil data, memicu latensi tinggi dan masalah duplikasi data saat baris baru disisipkan.',
    ],
    [
        'Apa fungsi dari header HTTP "ETag" (Entity Tag) dalam arsitektur caching REST API',
        'Menyediakan token validator unik (biasanya hash dari konten) yang memungkinkan klien melakukan conditional request (If-None-Match) untuk menghemat bandwidth jika resource belum berubah (304 Not Modified)',
        ['Menandai perangkat keras server mana yang memproses request', 'Menyimpan riwayat alamat email pengirim request', 'Mengatur format mata uang yang ditampilkan pada antarmuka'],
        'RFC 7232 mendefinisikan ETag sebagai cache validator yang sangat efektif: server dapat mengembalikan status 304 Not Modified tanpa body payload jika hash konten saat ini masih identik dengan yang dimiliki klien.',
    ],
    [
        'Dalam arsitektur WebSocket (RFC 6455), proses inisialisasi koneksi antara klien dan server diawali dengan mekanisme',
        'HTTP Upgrade Handshake (klien mengirim HTTP GET dengan header "Upgrade: websocket" dan "Connection: Upgrade")',
        ['Pengiriman paket UDP broadcast ke port 8080', 'Inisialisasi koneksi FTP secara tersembunyi di browser', 'Pembukaan tunnel SSH langsung dari JavaScript klien'],
        'RFC 6455 menetapkan bahwa koneksi WebSocket dua arah (full-duplex) dimulai dari HTTP handshake standar (HTTP 101 Switching Protocols), memungkinkan koneksi melewati infrastruktur firewall dan proxy web yang ada.',
    ],
    [
        'Dalam spesifikasi OpenAPI (Swagger), apa fungsi utama dari file dokumen skema API (swagger.json / openapi.yaml)',
        'Menyediakan kontrak mesin yang terstandarisasi untuk mendeskripsikan seluruh endpoint, parameter input, skema respons, dan keamanan API, yang dapat digunakan untuk otomatisasi dokumentasi dan code generator',
        ['Menjalankan kode backend secara otomatis di cloud server', 'Menggantikan seluruh controller dan database backend', 'Menyimpan rekaman log transaksi pengguna'],
        'OpenAPI Initiative (Linux Foundation) menetapkan OpenAPI Specification sebagai standar vendor-neutral untuk mendefinisikan kontrak interface API, memungkinkan pembuatan SDK klien, mock server, dan pengujian otomatis.',
    ],
];

// =========================================================================
// MODUL 3: DATABASE DESIGN, SQL OPTIMIZATION, INDEXING, ACID (20 Soal)
// =========================================================================
$databaseTopics = [
    [
        'Sebuah tabel relasional dikatakan memenuhi "Bentuk Normal Ketiga" (Third Normal Form / 3NF) jika memenuhi 2NF dan',
        'Tidak memiliki ketergantungan transitif (transitive dependency), di mana atribut non-primary key tidak boleh bergantung pada atribut non-primary key lainnya',
        ['Semua kolom bertipe data teks harus diubah menjadi tipe data integer', 'Tabel memiliki minimal 10 buah foreign key penghubung', 'Semua baris data harus diurutkan secara alfabetis berdasarkan nama'],
        'E.F. Codd (1971) merumuskan 3NF: setiap atribut non-key harus bergantung secara langsung pada primary key (the key, the whole key, and nothing but the key, so help me Codd), mengeliminasi transitive dependency.',
    ],
    [
        'Dalam properti transaksi ACID, prinsip "Atomicity" menjamin bahwa',
        'Semua operasi dalam satu transaksi harus berhasil diselesaikan secara utuh, atau jika ada satu yang gagal, seluruh operasi dibatalkan total (all-or-nothing rollback)',
        ['Data transaksi selalu tersimpan secara permanen meskipun listrik server padam', 'Dua transaksi konkuren tidak dapat saling melihat perubahan data masing-masing', 'Data yang disimpan selalu memenuhi aturan validasi skema tipe data'],
        'Prinsip Atomicity memastikan bahwa transaksi finansial (misal transfer bank debit & kredit) dieksekusi sebagai satu kesatuan tak terbagi. Kegagalan di tengah jalan memicu rollback penuh ke status awal (C.J. Date).',
    ],
    [
        'Tingkat isolasi transaksi SQL "Read Committed" memberikan perlindungan terhadap fenomena anomali',
        'Dirty Read (mencegah transaksi membaca data yang belum di-commit oleh transaksi lain)',
        ['Non-Repeatable Read', 'Phantom Read', 'Serialization Failure'],
        'Standar ANSI/ISO SQL-92 mendefinisikan isolasi: Read Committed mencegah Dirty Read dengan memastikan transaksi hanya melihat data yang telah selesai di-commit, meskipun fenomena Non-Repeatable Read masih dimungkinkan.',
    ],
    [
        'Anomali transaksi "Phantom Read" terjadi pada database relasional ketika',
        'Sebuah transaksi menjalankan query rentang data (range query) dua kali, dan pada pembacaan kedua menemukan baris-baris data baru yang disisipkan oleh transaksi lain yang baru saja commit',
        ['Database membaca data dari memori yang sudah terhapus oleh garbage collector', 'Sebuah transaksi membaca baris yang nilainya sedang diubah oleh transaksi yang akhirnya rollback', 'Koneksi database terputus di tengah eksekusi query SELECT'],
        'ANSI/ISO SQL-92 Isolation Levels menetapkan Phantom Read sebagai anomali di mana baris baru muncul di dalam hasil range query transaksi A akibat transaksi B yang menyisipkan data baru di rentang tersebut.',
    ],
    [
        'Tingkat isolasi transaksi tertinggi pada database relasional yang menjamin eksekusi konkuren menghasilkan status yang identik dengan eksekusi serial satu per satu adalah',
        'Serializable',
        ['Repeatable Read', 'Read Committed', 'Read Uncommitted'],
        'Tingkat isolasi Serializable adalah level tertinggi dalam standar ANSI SQL-92: mengeliminasi semua anomali konkurensi (Dirty Read, Non-repeatable Read, Phantom Read) dengan biaya performa throughput penguncian/MVCC tertinggi.',
    ],
    [
        'Struktur data indeks default pada database relasional modern (seperti MySQL InnoDB dan PostgreSQL) adalah "B-Tree" (atau B+Tree). Alasan fundamental penggunaan B+Tree dibanding Binary Search Tree (BST) biasa untuk storage engine disk adalah',
        'B+Tree memiliki fan-out (branching factor) yang sangat tinggi sehingga pohon sangat dangkal, secara dramatis meminimalkan jumlah operasi I/O pembacaan blok disk',
        ['B+Tree tidak memerlukan memori RAM untuk menyimpan pointer node', 'B+Tree hanya dapat menyimpan tipe data teks string pendek', 'Binary Search Tree tidak dapat diimplementasikan dalam bahasa C'],
        'B+Tree menyimpan banyak key dalam satu node sebesar ukuran page disk (misal 16KB di InnoDB). Ini menghasilkan tinggi pohon (height) yang sangat rendah (biasanya 3-4 tingkat untuk jutaan baris), menghemat disk I/O seek time (Silberschatz, Database System Concepts).',
    ],
    [
        'Sebuah tabel memiliki composite index pada tiga kolom: INDEX idx_abc (kolom_a, kolom_b, kolom_c). Berdasarkan aturan "Leftmost Prefix Rule", kondisi WHERE clause manakah yang TIDAK dapat memanfaatkan indeks tersebut',
        'WHERE kolom_b = 10 AND kolom_c = 20',
        ['WHERE kolom_a = 5 AND kolom_b = 10', 'WHERE kolom_a = 5', 'WHERE kolom_a = 5 AND kolom_b = 10 AND kolom_c = 20'],
        'Prinsip Leftmost Prefix pada B-Tree mensyaratkan query harus menyertakan kolom paling kiri (kolom_a) agar traversal pohon indeks dapat dimulai. Melewati kolom_a menyebabkan engine melakukan full table scan (MySQL Internals).',
    ],
    [
        'Ketika menjalankan perintah "EXPLAIN" pada query MySQL, nilai kolom "type" manakah yang menandakan performa pencarian terbaik (selain NULL/const)',
        'eq_ref',
        ['ALL', 'index', 'range'],
        'Dokumentasi resmi MySQL Optimization mengurutkan performa join type: system > const > eq_ref > ref > range > index > ALL. "eq_ref" menandakan pencarian satu baris menggunakan primary key atau unique non-nullable index.',
    ],
    [
        'Apa yang dimaksud dengan "Covering Index" dalam optimasi query SQL',
        'Indeks yang memuat semua kolom yang diminta dalam klausa SELECT, WHERE, dan ORDER BY sehingga query dapat dilayani tuntas murni dari pohon indeks tanpa mengakses tabel utama (table lookup)',
        ['Indeks yang menutupi seluruh tabel dari pembacaan oleh pengguna anonim', 'Indeks otomatis yang dibuat oleh database saat tabel pertama kali dibuat', 'Indeks yang mencakup semua database yang ada di server'],
        'Covering Index (ditandai dengan "Using index" pada Extra EXPLAIN MySQL) mengeliminasi langkah secondary index lookup ke clustered index (bookmark lookup), menghasilkan lonjakan kecepatan query yang sangat signifikan.',
    ],
    [
        'Klausul SQL "HAVING" berbeda penggunaannya dari klausul "WHERE" karena',
        'HAVING digunakan untuk memfilter baris data setelah agregasi (GROUP BY) dilakukan, sedangkan WHERE memfilter baris data sebelum agregasi diproses',
        ['HAVING hanya dapat digunakan pada tabel temporer', 'WHERE hanya bekerja pada tipe data string', 'HAVING tidak dapat dikombinasikan dengan klausa ORDER BY'],
        'Standar ISO/IEC 9075 SQL menetapkan urutan pemrosesan logis: FROM -> WHERE -> GROUP BY -> HAVING -> SELECT. Karena itu, fungsi agregat (seperti SUM, COUNT) hanya valid diletakkan pada HAVING, bukan WHERE.',
    ],
    [
        'Perhatikan query: "SELECT * FROM orders WHERE YEAR(created_at) = 2024;". Mengapa query ini menyebabkan indeks pada kolom created_at tidak digunakan (full scan) dan bagaimana perbaikan yang benar',
        'Membungkus kolom dengan fungsi membuat ekspresi non-sargable (indeks tidak dapat di-seek); perbaikan yang benar adalah: WHERE created_at >= \'2024-01-01\' AND created_at < \'2025-01-01\'',
        ['Indeks created_at rusak; perbaikan yang benar adalah menghapus tabel dan membuatnya ulang', 'Fungsi YEAR() hanya dapat digunakan di PostgreSQL, bukan di MySQL', 'Kueri harus menggunakan operator LIKE \'%2024%\''],
        'Search Argumentable (SARGable) query mensyaratkan kolom indeks tidak boleh dimanipulasi fungsi secara langsung pada predikat WHERE, karena engine tidak dapat memanfaatkan struktur B-Tree yang terurut dari nilai mentah (SQL Anti-patterns).',
    ],
    [
        'Dalam arsitektur engine database MySQL InnoDB, apa perbedaan antara "Clustered Index" dengan "Secondary Index"',
        'Clustered Index menyimpan data baris tabel aktual langsung di daun pohonnya (leaf nodes) berdasarkan Primary Key, sedangkan Secondary Index menyimpan leaf nodes berisi nilai Primary Key penunjuk',
        ['Clustered Index disimpan di file eksternal, sedangkan Secondary Index di RAM', 'Tabel dapat memiliki hingga 10 Clustered Index sekaligus', 'Secondary Index selalu dibuat secara manual oleh developer'],
        'InnoDB mengorganisasi tabel secara fisik sebagai Clustered Index (berbasis PK). Secondary index lookup memerlukan dua kali traversal (secondary tree -> clustered tree) kecuali jika query merupakan covering index.',
    ],
    [
        'Teknik "Database Sharding" (Horizontal Partitioning) diterapkan pada arsitektur data skala masif untuk',
        'Memecah baris data tabel tunggal ke beberapa server database fisik yang berbeda berdasarkan Sharding Key (misal: user_id hash)',
        ['Menyalin seluruh tabel database ke 100 server yang identik', 'Mengompresi file log transaksi database ke format zip', 'Memisahkan kolom tabel menjadi beberapa tabel kecil tanpa relasi'],
        'Sharding membagi beban data dan I/O secara horizontal ke multiple nodes database independen (shared-nothing architecture), memecahkan batas kapasitas vertikal hardware server tunggal (Martin Kleppmann, Designing Data-Intensive Applications).',
    ],
    [
        'Dalam strategi concurrency control database, perbedaan mendasar antara "Optimistic Locking" dan "Pessimistic Locking" adalah',
        'Pessimistic Locking mengunci baris data di database (misal: SELECT ... FOR UPDATE) sejak awal pembacaan, sedangkan Optimistic Locking tidak mengunci baris dan memverifikasi versi (version number / timestamp) saat update dilakukan',
        ['Optimistic Locking hanya bekerja pada transaksi read-only murni', 'Pessimistic Locking tidak pernah mengalami deadlock', 'Optimistic Locking membutuhkan hardware server khusus dengan chip kriptografi'],
        'Martin Fowler (PofEAA) membedakan: Optimistic Lock ideal untuk sistem high-concurrency dengan konflik tabrakan rendah (menggunakan WHERE version = current_version), sedangkan Pessimistic Lock ideal untuk transaksi finansial kritis berkonflik tinggi.',
    ],
    [
        'Kondisi "Deadlock" pada database relasional terjadi ketika',
        'Dua transaksi atau lebih saling menunggu kunci (locks) yang dipegang oleh transaksi lainnya dalam siklus tertutup sehingga tidak ada yang dapat melanjutkan proses',
        ['Server database kehabisan ruang penyimpanan hard disk secara tiba-tiba', 'Sebuah query SELECT memakan waktu lebih dari 60 detik tanpa henti', 'Pengguna memasukkan password yang salah sebanyak tiga kali berturut-turut'],
        'Deadlock adalah kondisi klasik konkurensi (Coffman conditions). Engine database modern seperti InnoDB mendeteksi siklus tunggu ini via Deadlock Detection algorithm dan secara otomatis me-rollback salah satu transaksi termurah (victim).',
    ],
    [
        'Apa manfaat utama dari penggunaan fitur "Foreign Key Constraint" dengan opsi "ON DELETE RESTRICT" di database relasional',
        'Mencegah penghapusan baris data pada tabel induk (parent) jika masih terdapat baris data anak (child) yang mereferensikannya, menjamin integritas referensial',
        ['Secara otomatis menghapus seluruh database jika ada tabel yang rusak', 'Mengizinkan penghapusan data anak tanpa memvalidasi data induk', 'Mempercepat kecepatan query INSERT hingga 10 kali lipat'],
        'Integritas referensial (C.J. Date) menjamin tidak adanya orphan records. Opsi RESTRICT/NO ACTION menolak penghapusan baris induk yang masih memiliki ketergantungan relasi aktif.',
    ],
    [
        'Pada PostgreSQL, fitur indeks "GIN" (Generalized Inverted Index) paling tepat digunakan untuk mengoptimasi pencarian pada kolom bertipe data',
        'Array, Dokumen JSONB, dan Full-Text Search',
        ['Integer primary key berurutan', 'Kolom VARCHAR pendek dengan perbandingan sama dengan (=)', 'Kolom boolean murni (true/false)'],
        'PostgreSQL Documentation mencatat GIN index sebagai inverted index yang memetakan elemen individual (komponen array, key/value jsonb, kata pada text search) ke baris tabel, sangat efisien untuk operator containment (@>).',
    ],
    [
        'Konsep "Write-Ahead Logging" (WAL) pada engine penyimpanan database modern berfungsi untuk',
        'Menjamin durabilitas (D dalam ACID) dengan mencatat seluruh perubahan data ke append-only log di disk sebelum data tersebut dituliskan ke file data utama di storage',
        ['Mencatat daftar query yang dieksekusi oleh developer untuk keperluan absensi', 'Menuliskan log error ke console terminal browser', 'Mengompresi data tabel sebelum dikirimkan melalui jaringan internet'],
        'ARIES Recovery Algorithm (Mohan et al., IBM) membuktikan bahwa WAL memungkinkan recovery yang aman dari crash: pemulihan dapat mengulang aksi yang telah dicatat (Redo) dan membatalkan transaksi yang belum selesai (Undo).',
    ],
    [
        'Apa yang dimaksud dengan "Database Connection Pooling" (seperti HikariCP atau PgBouncer) dan mengapa krusial untuk backend berkinerja tinggi',
        'Mengelola kumpulan koneksi database yang siap digunakan kembali, mengeliminasi overhead latensi pembuatan handshake TCP dan autentikasi koneksi baru pada setiap request',
        ['Menggabungkan beberapa database menjadi satu tabel tunggal secara otomatis', 'Menggandakan bandwidth internet server database sebesar dua kali lipat', 'Menghapus query SQL yang lambat secara otomatis dari memori'],
        'Membuka koneksi baru ke database membutuhkan overhead mahal (TCP handshake, SSL exchange, alokasi memori process backend). Connection pool mempertahankan koneksi hangat (warm connections) yang dapat dipinjam secara efisien.',
    ],
    [
        'Kapan sebuah query database relasional sebaiknya menggunakan "Common Table Expression" (CTE / WITH clause) rekursif',
        'Ketika perlu menelusuri struktur data hierarkis atau graph (seperti pohon organisasi atasan-bawahan atau kategori bersarang bertingkat)',
        ['Ketika ingin melakukan enkripsi password pengguna', 'Ketika query hanya membaca satu baris data berdasarkan ID', 'Ketika tabel hanya memiliki dua kolom'],
        'SQL:1999 standard recursive CTE (WITH RECURSIVE) dirancang khusus untuk memproses relasi hierarkis mandiri (self-referencing hierarchies) tanpa memerlukan perulangan imperative di sisi kode aplikasi.',
    ],
];

// =========================================================================
// MODUL 4: CACHING (REDIS), NOSQL, MESSAGE QUEUES, ASYNC JOBS (20 Soal)
// =========================================================================
$cacheQueueTopics = [
    [
        'Dalam strategi caching data backend, pola "Cache-Aside" (Lazy Loading) bekerja dengan mekanisme alur',
        'Aplikasi mencari data ke cache terlebih dahulu; jika cache miss, aplikasi mengambil data dari database, menyimpannya ke cache, lalu mengembalikannya ke klien',
        ['Database secara otomatis memperbarui cache setiap kali ada query insert', 'Aplikasi hanya menulis data ke cache dan tidak pernah menyimpannya ke database', 'Cache menghapus seluruh isinya setiap kali terjadi kegagalan jaringan'],
        'Martin Fowler & AWS Architecture Center mendefinisikan Cache-Aside: aplikasi bertanggung jawab langsung membaca cache, menangani cache miss dengan fallback ke database, dan mengisinya kembali dengan TTL.',
    ],
    [
        'Masalah performa caching "Cache Stampede" (Thundering Herd) terjadi ketika',
        'Kunci cache yang sangat populer (hot key) kedaluwarsa, memicu ribuan request konkuren secara bersamaan mengakses database untuk menghitung ulang data tersebut',
        ['Kapasitas memori RAM Redis penuh sehingga server mati mendadak', 'Data di cache dimutasi oleh dua admin secara bersamaan', 'Koneksi jaringan internet terputus antara server web dan Redis'],
        'Cache Stampede membebani database utama hingga down saat item berfrekuensi akses tinggi hilang dari cache. Solusi standarnya meliputi mutex locking, probabilistic early expiration (XFetch algorithm), atau background refresh.',
    ],
    [
        'Tipe data Redis manakah yang paling ideal digunakan untuk mengimplementasikan fitur "Real-time Leaderboard" skor tertinggi pengguna dengan urutan skor otomatis',
        'Sorted Set (ZSET)',
        ['Hash', 'String', 'List'],
        'Redis Sorted Set (ZSET) memetakan member unik ke floating point score, secara internal menggunakan kombinasi Hash Table dan Skip List untuk menghasilkan operasi penambahan dan range lookup skor berkecepatan O(log N).',
    ],
    [
        'Kebijakan pengusiran data Redis (Eviction Policy) "allkeys-lru" (Least Recently Used) memiliki mekanisme kerja',
        'Menghapus kunci-kunci yang paling lama tidak diakses di seluruh dataset ketika kapasitas memori Redis mencapai batas maxmemory',
        ['Menghapus kunci secara acak tanpa memperhatikan waktu akses', 'Menghapus kunci hanya jika kunci tersebut memiliki masa expired (TTL)', 'Menolak semua request penulisan baru dan melempar error out-of-memory'],
        'Dokumentasi resmi Redis menetapkan allkeys-lru sebagai kebijakan standar untuk skenario shared cache: mempertahankan kunci yang paling sering digunakan dan menyingkirkan kunci yang paling lama tidak disentuh.',
    ],
    [
        'Fitur Redis "Pub/Sub" berbeda dengan "Redis Streams" dalam hal ketahanan data (durability) karena',
        'Redis Pub/Sub bersifat "fire-and-forget" tanpa penyimpanan riwayat (pesan hilang jika subscriber offline), sedangkan Redis Streams menyimpan log pesan secara persisten dan mendukung consumer groups',
        ['Redis Pub/Sub hanya dapat mengirimkan angka desimal', 'Redis Streams tidak mendukung multi-subscriber', 'Redis Pub/Sub membutuhkan instalasi plugin pihak ketiga'],
        'Dokumentasi Redis 5.0+ menjelaskan Streams sebagai log terstruktur (mirip Apache Kafka) dengan jaminan persistensi pesan, ack handling, dan offset tracking, berbeda dari Pub/Sub klasik yang murni ephemeral.',
    ],
    [
        'Dalam arsitektur Message Broker "RabbitMQ", komponen manakah yang bertanggung jawab menerima pesan dari produsen dan merutekannya ke antrian (queue) berdasarkan routing key',
        'Exchange',
        ['Channel', 'Virtual Host', 'Dead Letter Pipe'],
        'AMQP 0-9-1 Standard mendefinisikan arsitektur RabbitMQ: producer tidak pernah mengirim pesan langsung ke queue, melainkan ke Exchange yang bertugas mendistribusikan pesan ke binding queues (Direct, Topic, Fanout, Headers).',
    ],
    [
        'Tipe Exchange RabbitMQ "Fanout Exchange" bekerja dengan cara',
        'Menduplikasi dan merutekan setiap pesan yang masuk ke seluruh antrian (queues) yang terikat padanya tanpa memperhatikan nilai routing key',
        ['Merutekan pesan hanya ke antrian yang memiliki kecocokan routing key persis', 'Merutekan pesan berdasarkan ekspresi reguler regex kompleks', 'Menghapus pesan jika antrian tujuan sedang memiliki lebih dari 100 antrian'],
        'Fanout Exchange menerapkan pola broadcast murni: setiap queue yang terikat pada exchange tersebut akan menerima salinan pesan yang identik, sangat berguna untuk notifikasi sistem multi-service massal.',
    ],
    [
        'Dalam sistem Apache Kafka, konsep "Partition" pada sebuah Topic berfungsi untuk',
        'Menyediakan skalabilitas horizontal dan throughput tinggi dengan memungkinkan pemrosesan pesan secara paralel oleh banyak konsumen di dalam Consumer Group',
        ['Menghapus pesan-pesan lama yang sudah dibaca oleh klien', 'Mengenkripsi isi payload pesan menggunakan sertifikat SSL', 'Menghubungkan Kafka dengan database MySQL secara native'],
        'The Log: What every software engineer should know about real-time data\'s unifying abstraction (Jay Kreps, LinkedIn) menjelaskan bahwa Partisi adalah unit skalabilitas Kafka: menjamin urutan pesan strictly-ordered per partisi.',
    ],
    [
        'Fitur "Dead Letter Queue" (DLQ) dalam arsitektur antrian pesan bertugas untuk',
        'Menampung pesan-pesan yang gagal diproses berulang kali (setelah melampaui batas percobaan retry maksimal) agar dapat diisolasi dan dianalisis tanpa memblokir antrian utama',
        ['Menghapus pesan spam yang dikirimkan oleh peretas secara otomatis', 'Mengirimkan pesan ucapan duka cita ke email karyawan yang resign', 'Mempercepat pengiriman pesan promosi pemasaran'],
        'Enterprise Integration Patterns (EIP) mendefinisikan Dead Letter Channel/Queue sebagai pengaman sistem antrian: pesan beracun (poison message yang memicu unhandled exception) disisihkan agar tidak menyebabkan infinite retry loop.',
    ],
    [
        'Teorema CAP (Brewer\'s CAP Theorem) pada sistem terdistribusi menyatakan bahwa ketika terjadi kegagalan partisi jaringan (Partition Tolerance / P), sistem hanya dapat memilih salah satu di antara',
        'Consistency (C) atau Availability (A)',
        ['Performance (P) atau Security (S)', 'Durability (D) atau Scalability (S)', 'Atomicity (A) atau Isolation (I)'],
        'Eric Brewer & Seth Gilbert (ACM 2002) membuktikan secara formal: jaringan terdistribusi pasti mengalami partisi (P). Oleh karena itu, arsitek sistem harus memilih apakah mengorbankan Availability (CP, misal HBase) atau Consistency (AP, misal Cassandra/DynamoDB).',
    ],
    [
        'Dalam database NoSQL berjenis Document Store (seperti MongoDB), kapan teknik "Embedding Documents" lebih direkomendasikan daripada "Referencing (Normalized Documents)"',
        'Ketika data relasi bertipe "1-to-Few", data anak selalu diakses bersamaan dengan data induknya, dan data anak tidak memiliki makna mandiri jika induknya dihapus',
        ['Ketika data anak bertambah secara tak terbatas hingga jutaan entitas per induk', 'Ketika data anak sering diperbarui secara independen oleh ratusan user bersamaan', 'Ketika ukuran total dokumen melebihi batas 16 MB'],
        'MongoDB Data Modeling Guide merekomendasikan Embedding untuk relasi berkorelasi tinggi dengan kardinalitas terbatas (misal baris item pesanan dalam sebuah pesanan), memanfaatkan atomic single-document write dan eliminasi join.',
    ],
    [
        'Mekanisme "Distributed Locking" menggunakan Redis (seperti algoritma "Redlock") dirancang untuk',
        'Mencegah dua proses backend di server yang berbeda mengeksekusi blok kode kritis yang sama secara konkuren pada resource bersama',
        ['Mengunci seluruh tabel database saat jam operasional kantor berakhir', 'Mencegah server cloud mematikan instance cadangan', 'Menghapus data sesi pengguna yang tidak aktif selama 30 menit'],
        'Algoritma Redlock (Salvatore Sanfilippo, pembuat Redis) menyediakan jaminan mutual exclusion di lingkungan distributed systems multi-node menggunakan kunci berbasis TTL dan random token pengenal.',
    ],
    [
        'Apa yang dimaksud dengan "Backpressure" dalam arsitektur pemrosesan data stream / reactive systems',
        'Mekanisme di mana konsumen yang kewalahan memberi sinyal ke produsen untuk memperlambat laju pengiriman data, mencegah kehabisan memori (buffer overflow) pada konsumen',
        ['Tekanan udara di dalam ruang server data center', 'Kegagalan database saat menerima query transaksi besar', 'Penolakan request dari pengguna yang belum membayar tagihan'],
        'Reactive Manifesto & Reactive Streams Specification mendefinisikan Backpressure sebagai mekanisme non-blocking di mana subscriber mengendalikan kecepatan emisi publisher agar sistem tetap tangguh dan elastis di bawah beban puncak.',
    ],
    [
        'Pada implementasi background jobs (seperti Laravel Queue, BullMQ, atau Celery), fungsi parameter "Exponential Backoff" pada mekanisme retry adalah',
        'Meningkatkan durasi jeda waktu tunggu secara eksponensial di setiap kegagalan percobaan (misal: 1 detik, 2 detik, 4 detik, 8 detik) sebelum mencoba kembali',
        ['Mengurangi ukuran payload pesan sebesar dua kali lipat pada setiap retry', 'Menghapus tugas dari antrian jika gagal dalam percobaan pertama', 'Menjalankan percobaan ulang secara simultan di 10 server sekaligus'],
        'Exponential Backoff (RFC 2988) adalah algoritma standar untuk pemulihan dari kegagalan jaringan sementara: memberi ruang bagi sistem dependensi (misal payment gateway) untuk pulih tanpa dibanjiri retry beruntun yang agresif.',
    ],
    [
        'Dalam database NoSQL kolom-lebar (seperti Apache Cassandra), konsep model konsistensi "Eventual Consistency" berarti',
        'Jika tidak ada mutasi baru, semua replika node pada akhirnya akan menyatu (converge) menghasilkan nilai data yang identik seiring berjalannya waktu',
        ['Data dijamin konsisten seketika itu juga pada detik yang sama di semua node global', 'Data yang sudah ditulis tidak akan pernah dapat diubah kembali selamanya', 'Database hanya konsisten pada saat server direstart'],
        'Werner Vogels (Amazon CTO, ACM Queue 2008) menjelaskan Eventual Consistency sebagai trade-off model data terdistribusi untuk mencapai high availability dan fault tolerance tinggi dengan menyinkronkan data antar-node secara asinkron.',
    ],
    [
        'Strategi pembaruan cache "Write-Through" berbeda dengan "Write-Behind" (Write-Back) dalam hal',
        'Write-Through menulis data ke cache dan database secara sinkron sebelum mengembalikan sukses, sedangkan Write-Behind menulis ke cache terlebih dahulu lalu memperbarui database secara asinkron di latar belakang',
        ['Write-Through hanya digunakan di memori hard disk, sedangkan Write-Behind di flash drive', 'Write-Through tidak menjamin persistensi data di database', 'Keduanya identik dan hanya berbeda penamaan vendor cloud'],
        'Studi pola arsitektur storage (ACM) membedakan: Write-Through menjamin konsistensi instan antar layer (latensi penulisan lebih tinggi), sedangkan Write-Behind mengoptimalkan write-latency dengan risiko kehilangan data jika node cache crash sebelum flush.',
    ],
    [
        'Fitur "Key Expiration" (TTL - Time to Live) pada Redis bekerja secara internal menggunakan kombinasi dua mekanisme, yaitu',
        'Passive (kunci dihapus saat ada akses baca terhadap kunci yang sudah kedaluwarsa) dan Active (kunci diuji dan dibersihkan secara acak periodik di latar belakang)',
        ['Pembersihan total seluruh database setiap jam tepat waktu', 'Mengandalkan cron job sistem operasi Linux secara langsung', 'Menghapus kunci hanya jika memori server telah mencapai 99%'],
        'Dokumentasi internal Redis menjelaskan bahwa Redis memadukan Lazy Expiration (saat key di-read) dan Active Probabilistic Expiration (10 kali per detik menyampling 20 key dengan TTL) untuk efisiensi CPU dan memori.',
    ],
    [
        'Dalam sistem Apache Kafka, apa yang terjadi jika jumlah konsumen (consumers) dalam satu Consumer Group melebihi jumlah partisi (partitions) pada topic yang dilanggan',
        'Konsumen yang berlebih akan berada dalam status menganggur (idle / tidak menerima pesan apa pun)',
        ['Konsumen yang berlebih akan membagi setiap partisi menjadi dua bagian baru', 'Pesan akan terduplikasi dan dikirimkan ke semua konsumen sekaligus', 'Kafka cluster akan melempar error fatal dan mematikan broker'],
        'Aturan fundamental Kafka Consumer Groups menetapkan bahwa setiap partisi hanya boleh diproses oleh tepat satu konsumen di dalam grup yang sama, sehingga jumlah partisi menjadi batas atas konkurensi pemrosesan.',
    ],
    [
        'Pada database MongoDB, kapan sebuah kueri indeks menghasilkan status "COLLSCAN" pada hasil profiling query explain()',
        'Ketika kueri tidak dapat memanfaatkan indeks yang ada sehingga engine terpaksa memindai seluruh koleksi dokumen dari awal hingga akhir (Collection Scan)',
        ['Ketika kueri berhasil menggunakan indeks B-Tree yang optimal', 'Ketika dokumen berhasil disimpan ke dalam disk storage', 'Ketika data yang dicari ditemukan dalam waktu di bawah 1 milidetik'],
        'COLLSCAN pada MongoDB adalah padanan dari Full Table Scan di SQL: menandakan kueri berjalan tidak efisien dan memerlukan pembuatan indeks baru untuk mencegah degradasi performa database (MongoDB Performance Best Practices).',
    ],
    [
        'Strategi pencegahan "Message Loss" pada antrian RabbitMQ mengharuskan kombinasi konfigurasi berupa',
        'Queue dideklarasikan sebagai durable, pesan dikirim dengan flag delivery_mode=persistent, dan konsumen menggunakan manual acknowledgment (manual ack)',
        ['Queue dibuat sebagai auto-delete dan auto-ack diaktifkan', 'Pesan disimpan di file teks log server sebelum dikirim ke antrian', 'Mengirimkan pesan hanya saat koneksi jaringan berkecepatan tinggi'],
        'RabbitMQ Reliability Guide menegaskan bahwa untuk mencegah kehilangan pesan saat broker restart atau worker crash, ketiga pilar (durable queue, persistent message on disk, manual consumer ack setelah sukses memproses) wajib diterapkan.',
    ],
];

// =========================================================================
// MODUL 5: SECURITY (OWASP), AUTH, CONCURRENCY, DEVOPS & DOCKER (20 Soal)
// =========================================================================
$secDevOpsTopics = [
    [
        'Serangan "SQL Injection" (SQLi) menempati peringkat teratas risiko keamanan perangkat lunak. Mekanisme pertahanan terkuat dan paling standar di sisi backend untuk mencegah SQLi adalah',
        'Menggunakan Prepared Statements dengan Parameterized Queries di seluruh akses database',
        ['Melakukan enkripsi database menggunakan algoritma RSA 2048-bit', 'Mengubah nama tabel database menjadi acak setiap hari', 'Mematikan fitur foreign key pada seluruh tabel relasional'],
        'OWASP Top 10 & SEI CERT Coding Standards menetapkan bahwa parameterized queries memisahkan kode SQL dari input data di level kompilasi query engine, menjadikan input murni diperlakukan sebagai literal tanpa risiko interpretasi kode eksekusi.',
    ],
    [
        'Algoritma hashing password manakah yang saat ini direkomendasikan secara resmi oleh OWASP dan pemenang Password Hashing Competition (PHC) karena ketahanannya terhadap serangan akselerasi GPU dan ASIC',
        'Argon2id',
        ['MD5', 'SHA-256 murni tanpa salt', 'Base64 encoding'],
        'OWASP Password Storage Cheat Sheet secara tegas merekomendasikan Argon2id (atau bcrypt/scrypt) karena memiliki parameter memory-hard dan time-cost yang dapat dituning untuk menangkal peretasan brute-force berbasis GPU/ASIC.',
    ],
    [
        'Struktur token "JWT" (JSON Web Token) terdiri dari tiga bagian yang dipisahkan oleh tanda titik (header.payload.signature). Bagian manakah yang menjamin integritas token agar tidak dapat dipalsukan oleh klien',
        'Signature (tanda tangan kriptografi hasil hashing header + payload menggunakan secret key atau private key)',
        ['Payload yang berisi klaim data pengguna', 'Header yang mendefinisikan algoritma', 'Masa aktif token yang tercantum di atribut exp'],
        'RFC 7519 mendefinisikan JWT: Payload sengaja hanya di-encode dalam Base64Url (dapat dibaca siapa saja). Keamanan dan validitas token sepenuhnya bergantung pada Signature kriptografi yang diverifikasi oleh backend menggunakan secret/public key.',
    ],
    [
        'Dalam arsitektur otentikasi berbasis stateless JWT, strategi paling aman untuk menangani kebutuhan "Logout" atau pembatalan token sebelum masa kedaluwarsa (revocation) adalah',
        'Menyimpan token ID (JTI) yang dicabut ke dalam distributed blacklist (seperti Redis) dengan TTL yang sama dengan sisa masa aktif token',
        ['Menghapus private key server sehingga semua pengguna harus login ulang', 'Mengubah algoritma JWT menjadi MD5 secara dinamis', 'Meminta browser pengguna untuk menghapus token secara sukarela tanpa verifikasi server'],
        'NIST Digital Identity Guidelines (SP 800-63B) menyarankan Token Revocation List (Blacklist) berbasis memori cepat dengan auto-eviction via TTL, mempertahankan efisiensi stateless JWT tanpa mengorbankan keamanan sesi.',
    ],
    [
        'Kelemahan keamanan "Broken Object Level Authorization" (BOLA / IDOR) yang menempati peringkat #1 pada OWASP API Security Top 10 terjadi ketika',
        'API endpoint menerima identifier objek (misal: GET /api/v1/invoices/105) tanpa memverifikasi apakah pengguna yang terautentikasi memiliki hak sah untuk mengakses objek tersebut',
        ['Koneksi API tidak menggunakan protokol HTTPS', 'Token JWT tidak memiliki tanggal kedaluwarsa', 'Server backend kehabisan memori saat mengurai JSON payload'],
        'OWASP API Security Top 10 mengidentifikasi BOLA sebagai celah paling masif: backend memvalidasi siapa pengguna (autentikasi), tetapi luput memvalidasi kepemilikan data atas ID yang diminta (otorisasi level objek).',
    ],
    [
        'Dalam protokol otentikasi "OAuth 2.0" untuk Single Page Application (SPA) atau Mobile App, alur grant type manakah yang resmi direkomendasikan untuk menggantikan Implicit Grant',
        'Authorization Code Grant dengan PKCE (Proof Key for Code Exchange)',
        ['Resource Owner Password Credentials Grant', 'Client Credentials Grant murni', 'SAML 2.0 Web Browser SSO'],
        'IETF RFC 7636 & OAuth 2.0 Security Best Current Practice melarang Implicit Grant dan mewajibkan Authorization Code dengan PKCE untuk public clients guna mencegah pencurian authorization code di lingkungan yang tidak dapat menyimpan client secret.',
    ],
    [
        'Mekanisme pengamanan password hashing "Salt" dan "Cost Factor / Work Factor" memiliki fungsi spesifik yaitu',
        'Salt mencegah serangan Rainbow Tables dengan membuat hash unik untuk password yang sama, sedangkan Cost Factor memperlambat komputasi tebakan brute-force',
        ['Salt mengompresi panjang password agar muat di kolom database VARCHAR(10)', 'Cost Factor menghitung biaya tagihan server komputasi awan', 'Salt mengubah password menjadi format angka biner otomatis'],
        'NIST & OWASP menjelaskan bahwa Cryptographic Salt acak per-user membatalkan precomputed rainbow tables, sementara tunable work factor (seperti cost factor pada Bcrypt/Argon2) memastikan komputasi tetap tahan banting terhadap kemajuan hardware.',
    ],
    [
        'Serangan "Server-Side Request Forgery" (SSRF) pada backend terjadi ketika',
        'Penyerang memanipulasi aplikasi backend untuk mengirimkan request HTTP tidak sah ke sistem internal atau layanan metadata cloud yang terisolasi dari publik',
        ['Pengguna mengirimkan form dengan format email yang salah', 'Penyerang mencuri file CSS statis dari Content Delivery Network', 'Browser pengguna gagal merender font antarmuka'],
        'OWASP Top 10 mencatat SSRF sebagai celah berbahaya di mana server dijadikan proxy oleh penyerang untuk mengakses internal network, AWS Instance Metadata Service (169.254.169.254), atau database internal tanpa firewall.',
    ],
    [
        'Dalam penulisan file "Dockerfile" untuk aplikasi backend, praktik terbaik "Multi-stage Builds" memberikan manfaat utama berupa',
        'Memisahkan lingkungan build (dengan compiler dan dependensi development) dari image runtime akhir, menghasilkan ukuran image produksi yang sangat kecil dan minim celah keamanan',
        ['Menjalankan container di lima server data center berbeda secara otomatis', 'Mengubah kode sumber PHP/Node.js menjadi file executable Windows (.exe)', 'Mempercepat kecepatan koneksi internet kabel fiber optic'],
        'Docker Best Practices Guide merekomendasikan Multi-stage builds untuk mengeliminasi build tools, source code compiler, dan package devDependencies dari image final produksi, mengurangi attack surface secara drastis.',
    ],
    [
        'Instruksi Dockerfile manakah yang digunakan untuk menetapkan bahwa container tidak boleh dijalankan dengan hak akses istimewa (root privileges) di lingkungan produksi',
        'USER nonroot (atau UID selain 0)',
        ['RUN chmod 777 -R /', 'EXPOSE 80/tcp', 'ENTRYPOINT ["root"]'],
        'CIS Docker Benchmark & Kubernetes Hardening Guide mensyaratkan container dijalankan sebagai non-root user (USER directive) untuk mencegah container breakout yang dapat memberikan penyerang akses root ke host kernel.',
    ],
    [
        'Web server "Nginx" yang difungsikan sebagai "Reverse Proxy" di depan klaster backend memberikan keuntungan arsitektural berupa',
        'Penyatuan SSL/TLS Termination, load balancing lalu lintas request, kompresi respons, dan penyediaan lapisan isolasi publik sebelum menyentuh aplikasi backend',
        ['Penyusunan kode backend otomatis tanpa perlu programmer', 'Penggantian seluruh tabel database SQL menjadi NoSQL', 'Pemberian lisensi sistem operasi gratis untuk server'],
        'Nginx Architecture Reference & High Performance Web Systems mencatat reverse proxy sebagai garda depan standar: menangani handshake kriptografi berat (SSL termination), caching statis, dan mendistribusikan beban trafik (Round Robin, Least Connections).',
    ],
    [
        'Dalam pipeline "CI/CD" (Continuous Integration / Continuous Deployment), tahap "Continuous Integration" didefinisikan sebagai praktik di mana',
        'Developer secara berkala menggabungkan perubahan kode ke repositori utama, yang secara otomatis diverifikasi melalui build dan automated testing untuk mendeteksi integrasi error sedini mungkin',
        ['Aplikasi dirilis ke toko aplikasi Google Play Store setiap hari Minggu', 'Developer menulis dokumentasi manual di Google Docs', 'Server database di-backup secara otomatis setiap pergantian bulan'],
        'Martin Fowler (Continuous Integration) merumuskan CI: integrasi kode frekuensi tinggi yang dipicu automated build & unit/integration test, menjamin kode di main branch selalu berada dalam kondisi yang dapat di-deploy (deployable state).',
    ],
    [
        'Dalam konsep pengujian backend "Test Pyramid" yang diperkenalkan oleh Mike Cohn, proporsi lapisan pengujian yang paling ideal dari dasar ke atas adalah',
        'Banyak Unit Tests di bagian dasar, jumlah sedang Integration Tests di tengah, dan sedikit End-to-End (E2E) Tests di puncak piramida',
        ['Hanya E2E Tests tanpa perlu menuliskan Unit Tests sama sekali', 'Banyak E2E Tests di dasar dan sedikit Unit Tests di puncak (Ice Cream Cone anti-pattern)', '100% Manual Testing tanpa automated test scripts'],
        'SWEBOK & Martin Fowler menjelaskan Test Pyramid: Unit Test cepat, terisolasi, dan murah dieksekusi sehingga menjadi mayoritas basis kode, sedangkan E2E test lambat, rapuh (brittle), dan mahal sehingga jumlahnya harus selektif.',
    ],
    [
        'Apa yang dimaksud dengan "Graceful Shutdown" pada server backend (seperti Node.js atau Go) saat menerima sinyal terminasi SIGTERM dari sistem operasi/Kubernetes',
        'Server berhenti menerima koneksi baru, menyelesaikan seluruh request dan transaksi aktif yang sedang berjalan, membersihkan resource (koneksi DB/Redis), lalu keluar secara bersih',
        ['Server langsung mematikan proses seketika itu juga tanpa menunggu query selesai', 'Server menghapus semua data di database sebelum dimatikan', 'Server mengirimkan pesan pemberitahuan ke semua kontak email pengguna'],
        'Kubernetes Pod Lifecycle & SRE Principles mewajibkan Graceful Shutdown: menangkap sinyal SIGTERM, memberi jeda waktu bagi in-flight requests untuk tuntas, mencegah data corruption atau HTTP 502 Bad Gateway pada klien.',
    ],
    [
        'Dalam pengawasan sistem produksi, format "Structured Logging" (seperti JSON logs) jauh lebih disukai daripada plain text log karena',
        'Log dalam format key-value terstruktur dapat dengan mudah diparsing, diindeks, dan difilter oleh log aggregation tools (seperti ELK Stack, Grafana Loki, atau Datadog)',
        ['JSON log memiliki ukuran file yang selalu lebih kecil dibanding plain text', 'JSON log tidak dapat dibaca oleh peretas yang berhasil menyusup ke server', 'JSON log secara otomatis menghapus pesan error yang memalukan developer'],
        '12-Factor App Methodology (Factor XI: Logs) & Cloud Native Computing Foundation (CNCF) merekomendasikan structured logging agar metrik kontekstual (trace_id, user_id, duration_ms) dapat di-query secara instan di sistem analitik.',
    ],
    [
        'Standar industri terdistribusi "Distributed Tracing" (seperti OpenTelemetry / Jaeger) menggunakan identifier khusus yang disematkan pada setiap request bernama',
        'Trace ID dan Span ID yang diteruskan melalui HTTP headers (W3C Trace Context)',
        ['Nomor seri kartu grafis server pemroses', 'Password root database lokal', 'Nomor port kabel ethernet data center'],
        'W3C Trace Context Specification merekomendasikan Trace ID (mengidentifikasi seluruh jalur perjalanan transaksi end-to-end lintas puluhan microservices) dan Span ID (mengidentifikasi unit kerja spesifik di satu service).',
    ],
    [
        'Dalam prinsip "The Twelve-Factor App", bagaimana konfigurasi aplikasi (seperti database credentials dan API keys) seharusnya disimpan dan dikelola',
        'Disimpan secara ketat di environment variables (lingkungan sistem operasi), bukan di-hardcode di dalam kode sumber aplikasi',
        ['Di-hardcode langsung di dalam controller class agar tidak mudah hilang', 'Disimpan di file teks publik yang dapat diakses melalui browser', 'Disimpan di repositori Git publik bersama dengan source code'],
        'The Twelve-Factor App (Factor III: Config) menetapkan pemisahan mutlak antara konfigurasi dan kode sumber: konfigurasi bervariasi antar lingkungan (dev, staging, prod) dan harus disuntikkan via environment variables.',
    ],
    [
        'Algoritma hashing kriptografi satu arah (seperti SHA-256) TIDAK aman jika digunakan untuk enkripsi data dua arah (misal nomor kartu kredit yang perlu dibaca kembali) karena',
        'Fungsi hash bersifat irreversibel (satu arah) dan tidak dirancang untuk didekripsi kembali; enkripsi dua arah wajib menggunakan algoritma Symmetric/Asymmetric Encryption (seperti AES-256-GCM)',
        ['SHA-256 hanya menghasilkan teks sepanjang 10 karakter', 'SHA-256 membutuhkan biaya lisensi tahunan ke penemunya', 'Algoritma hash hanya dapat dijalankan di komputer kuantum'],
        'NIST Cryptographic Standards menegaskan: Hash function adalah fungsi deterministik satu arah untuk verifikasi integritas data. Untuk kerahasiaan data yang harus dibaca ulang, standar mewajibkan authenticated encryption seperti AES-GCM.',
    ],
    [
        'Apa risiko keamanan utama jika backend mengembalikan pesan error database lengkap beserta stack trace (misal syntax error atau connection error) langsung ke respons klien publik',
        'Information Disclosure (kebocoran informasi internal arsitektur, nama tabel, versi library, dan path direktori yang mempermudah peretas melancarkan serangan tertarget)',
        ['Server backend akan otomatis kehabisan lisensi perangkat lunak', 'Database akan otomatis terkunci dan tidak dapat diakses lagi', 'Koneksi internet klien akan langsung terputus seketika'],
        'OWASP Top 10 (Security Misconfiguration) melarang eksposur stack trace internal ke end-user. Praktik terbaik adalah mencatat detail error di internal server log dan mengembalikan pesan generik yang aman ke klien (misal: "Internal Server Error").',
    ],
    [
        'Perbedaan peran antara "Authentication" (Autentikasi) dan "Authorization" (Otorisasi) dalam keamanan sistem backend adalah',
        'Autentikasi memverifikasi identitas pengguna ("Siapa Anda?"), sedangkan Otorisasi memverifikasi hak akses pengguna terhadap sumber daya tertentu ("Apa yang boleh Anda lakukan?")' ,
        ['Autentikasi dijalankan di database, sedangkan Otorisasi dijalankan di browser frontend', 'Autentikasi menggunakan protokol UDP, sedangkan Otorisasi menggunakan TCP', 'Autentikasi hanya dibutuhkan untuk admin, sedangkan Otorisasi untuk user biasa'],
        'NIST & Computer Security Resource Center (CSRC) mendefinisikan batas fundamental keamanan: Authentication adalah pembuktian identitas (via password, MFA, token), sedangkan Authorization adalah penegakan kebijakan izin hak akses (via RBAC/ABAC).',
    ],
];

// Gabungkan semua modul menjadi 100 soal
$index = 1;
foreach ([$archPatternsTopics, $apiProtocolTopics, $databaseTopics, $cacheQueueTopics, $secDevOpsTopics] as $module) {
    foreach ($module as $item) {
        $questions[] = $make($item[0], $item[1], $item[2], $item[3], $index++);
    }
}

return $questions;
