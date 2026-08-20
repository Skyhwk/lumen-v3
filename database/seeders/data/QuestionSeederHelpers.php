<?php

/**
 * Helper shared seeder soal: format pertanyaan jelas + rotasi opsi jawaban.
 */

$rotateOptions = static function (array $options, int $seed): array {
    $shift = abs($seed) % max(1, count($options));
    return array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
};

$clarifyQuestion = static function (string $text): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    // Sudah berupa pertanyaan lengkap — cukup pastikan diakhiri tanda tanya
    if (preg_match('/(manakah|berapa|apa arti|apa definisi|apa fungsi|apa tujuan|apa manfaat|apa risiko|apa keuntungan|apa perbedaan|apa yang|apakah|mengapa|bagaimana|kapan|siapa|pernyataan manakah|langkah manakah|mitigasi manakah|solusi manakah|tindakan manakah|prinsip manakah|teknik manakah|paradigma manakah|pendekatan manakah|jenis manakah|strategi manakah|model manakah|operator sql manakah|clause sql manakah|perintah sql manakah|perintah git manakah|fungsi sql manakah|fungsi agregat sql manakah)/iu', $text)) {
        return rtrim($text, '?') . '?';
    }

    $suffixMap = [
        'Metode ini setara dengan kompleksitas waktu' => 'Berapa kompleksitas waktu (Big-O) metode tersebut',
        'Kompleksitas waktu pencariannya' => 'Berapa kompleksitas waktu pencarian tersebut',
        'Kompleksitas rata-ratanya' => 'Berapa kompleksitas waktu rata-rata algoritma tersebut',
        'Kompleksitas waktunya' => 'Berapa kompleksitas waktu algoritma tersebut',
        'Operasi akses ini' => 'Berapa kompleksitas waktu operasi akses tersebut',
        'Kompleksitas insert di awal' => 'Berapa kompleksitas waktu insert di awal linked list',
        'Kompleksitas insert di tengah' => 'Berapa kompleksitas waktu insert di tengah array',
        'Kompleksitas rata-rata' => 'Berapa kompleksitas waktu rata-rata operasi tersebut',
        'Kompleksitas lookup' => 'Berapa kompleksitas waktu operasi lookup tersebut',
        'Kompleksitas average case' => 'Berapa kompleksitas waktu average case algoritma tersebut',
        'Worst case complexity' => 'Berapa kompleksitas waktu worst case algoritma tersebut',
        'Kompleksitas insert' => 'Berapa kompleksitas waktu operasi insert',
        'Kompleksitas total' => 'Berapa total kompleksitas waktu algoritma tersebut',
        'Kompleksitas umum' => 'Berapa kompleksitas waktu algoritma tersebut',
        'Kompleksitas per operasi' => 'Berapa kompleksitas waktu per operasi enqueue/dequeue',
        'Tidak bisa lebih baik dari' => 'Berapa batas bawah (lower bound) kompleksitas sorting berbasis perbandingan',
        'Struktur data paling tepat' => 'Struktur data manakah yang paling tepat digunakan',
        'Struktur paling efisien' => 'Struktur data manakah yang paling efisien',
        'Representasi paling natural' => 'Representasi data manakah yang paling natural',
        'Struktur data di balik fitur ini' => 'Struktur data manakah yang digunakan pada fitur tersebut',
        'Struktur antrian proses' => 'Struktur antrian manakah yang digunakan',
        'Algoritma paling efisien' => 'Algoritma manakah yang paling efisien',
        'Kombinasi struktur standar' => 'Kombinasi struktur data manakah yang menjadi standar industri',
        'Struktur data khusus' => 'Struktur data manakah yang dirancang khusus untuk kebutuhan ini',
        'Struktur/algoritma' => 'Struktur atau algoritma manakah yang paling tepat',
        'Representasi paling hemat memori' => 'Representasi graf manakah yang paling hemat memori',
        'Implementasi paling tepat' => 'Implementasi manakah yang paling tepat',
        'Tipe data Python paling tepat' => 'Tipe data Python manakah yang paling tepat',
        'Tipe sequence yang bisa jadi key' => 'Tipe data Python manakah yang boleh dijadikan key dictionary',
        'Array vs Linked List' => 'Manakah yang lebih efisien untuk kasus insert di tengah',
        'Insert append amortized complexity' => 'Berapa kompleksitas waktu amortized untuk operasi append',
        'Index database MySQL/PostgreSQL umumnya memakai B-Tree karena' => 'Apa alasan utama database MySQL/PostgreSQL memakai index B-Tree',
        'Hasil traversal' => 'Apa hasil in-order traversal BST pada katalog produk terurut harga',
        'maksimum edge' => 'Berapa jumlah edge maksimum pada graf lengkap (complete graph) tersebut',
        'Kesimpulan utama' => 'Apa kesimpulan utama dari penelitian perbandingan BST vs AVL Tree tersebut',
        'Risiko utama' => 'Apa risiko utama yang dapat terjadi',
        'Paradigma algoritma' => 'Paradigma algoritma manakah yang digunakan',
        'Keterbatasannya' => 'Apa keterbatan utama algoritma greedy dalam kasus ini',
        'Teknik efisien' => 'Teknik algoritma manakah yang paling efisien',
        'Teknik' => 'Teknik algoritma manakah yang paling tepat',
        'Paradigma' => 'Paradigma algoritma manakah yang digunakan',
        'Keuntungan space' => 'Apa keuntungan utama dari segi penggunaan memori (space complexity)',
        'Algoritma non-comparison paling cocok' => 'Algoritma sorting non-comparison manakah yang paling cocok',
        'Radix sort mengurutkan berdasarkan' => 'Radix sort mengurutkan data berdasarkan apa',
        'Graph harus' => 'Jenis graf seperti apa yang diperlukan agar topological sort valid',
        'Pendekatan' => 'Pendekatan dynamic programming manakah yang digunakan',
        'Syarat wajib' => 'Apa syarat wajib sebelum menjalankan binary search',
        'menemukan pada array profit harian' => 'Apa output yang dihasilkan Kadane algorithm pada array profit harian',
        'menjadi' => 'Berapa kompleksitas query setelah optimasi prefix sum',
        'Struktur bantu' => 'Struktur bantu manakah yang digunakan pada DFS iteratif',
        'Properti benar' => 'Pernyataan manakah yang benar tentang BFS pada graf tak berbobot',
        'Algoritma tidak cocok karena' => 'Mengapa algoritma Dijkstra tidak cocok jika ada edge berbobot negatif',
        'menangani negative weights dan' => 'Selain bobot negatif, kemampuan apa lagi yang dimiliki Bellman-Ford',
        'Analisis' => 'Jenis analisis kompleksitas manakah yang menjelaskan amortized O(1) pada dynamic array',
        'Perintah SQL yang benar' => 'Manakah perintah SQL yang benar',
        'Clause SQL untuk filter baris' => 'Clause SQL manakah yang digunakan untuk memfilter baris',
        'Join yang tepat' => 'Jenis JOIN manakah yang paling tepat',
        'Fungsi agregat yang tepat' => 'Fungsi agregat SQL manakah yang paling tepat',
        'Clause yang benar' => 'Clause SQL manakah yang benar',
        'Clause pembatas baris (MySQL)' => 'Clause MySQL manakah yang benar untuk pagination halaman ke-3 (20 baris per halaman)',
        'Keyword SQL' => 'Keyword SQL manakah yang digunakan',
        'Perilaku AVG' => 'Bagaimana fungsi AVG() menangani nilai NULL',
        'Clause wajib bersama SUM()' => 'Clause SQL manakah yang wajib digunakan bersama fungsi SUM()',
        'Operator efisien' => 'Operator SQL manakah yang paling efisien',
        'Operator' => 'Operator SQL manakah yang benar',
        'Pattern LIKE' => 'Pattern LIKE manakah yang benar',
        'Syntax benar' => 'Syntax SQL manakah yang benar untuk mengecek NULL',
        'Fungsi SQL' => 'Fungsi SQL manakah yang paling tepat',
        'SQL conditional' => 'Syntax SQL manakah yang benar untuk ekspresi kondisional',
        'Perintah' => 'Perintah SQL manakah yang benar',
        'Solusi DBA' => 'Langkah manakah yang paling tepat dilakukan DBA',
        'Definisi' => 'Apa definisi yang benar',
        'FK diletakkan di' => 'Di tabel manakah Foreign Key seharusnya diletakkan',
        'Solusi implementasi' => 'Solusi implementasi manakah yang benar',
        'Implementasi umum' => 'Implementasi manakah yang paling umum digunakan',
        'Query paling efektif' => 'Query manakah yang paling efektif memanfaatkan composite index tersebut',
        'Langkah DBA' => 'Langkah manakah yang paling tepat untuk DBA',
        'Terjadi karena' => 'Mengapa kondisi tersebut terjadi pada database',
        'Manfaat utama (CAI Journal, 2024)' => 'Apa manfaat utama penggunaan index B-Tree pada kolom pencarian',
        'Trade-off' => 'Apa trade-off utama dari strategi tersebut',
        'Benefit' => 'Apa manfaat utama dari fitur SQL tersebut',
        'Pattern' => 'Pola query SQL manakah yang paling tepat',
        'Violation' => 'Prinsip SOLID manakah yang dilanggar',
        'Prinsip' => 'Prinsip clean code manakah yang paling relevan',
        'Solution' => 'Solusi manakah yang paling tepat menurut prinsip DRY/SOLID',
        'flow aman' => 'Alur manakah yang paling aman',
        'purpose' => 'Apa tujuan utama dari praktik tersebut',
        'shows' => 'Informasi apa yang ditampilkan perintah tersebut',
        'artinya' => 'Apa arti dari konsep HTTP/REST tersebut',
        'means' => 'Apa arti HTTP status code tersebut',
        'typically' => 'Apa karakteristik utama metode HTTP tersebut',
        'idempotent artinya' => 'Apa arti HTTP GET bersifat idempotent',
        'strategy' => 'Strategi manakah yang paling tepat',
        'structure' => 'Apa struktur bagian-bagian JWT token',
        'role' => 'Apa peran komponen tersebut dalam arsitektur web',
        'function' => 'Apa fungsi utama komponen tersebut',
        'protects' => 'Apa yang dilindungi oleh HTTPS/TLS',
        'rule' => 'Praktik manakah yang benar untuk aturan tersebut',
        'should' => 'Praktik manakah yang benar',
        'must use' => 'Metode manakah yang wajib digunakan',
        'prevention best practice' => 'Praktik pencegahan manakah yang paling tepat',
        'prevention' => 'Langkah pencegahan manakah yang paling tepat',
        'mitigation' => 'Mitigasi manakah yang paling tepat',
        'first step' => 'Apa langkah pertama yang paling tepat',
        'characteristics' => 'Karakteristik manakah yang ideal',
        'focus' => 'Fokus manakah yang benar',
        'minimum for API' => 'Dokumentasi minimum manakah yang wajib ada untuk API',
        'benefit' => 'Apa manfaat utama dari praktik tersebut',
        'includes' => 'Apa yang seharusnya termasuk dalam Definition of Done tim',
        'Tindakan tepat' => 'Tindakan manakah yang paling tepat',
    ];

    foreach ($suffixMap as $suffix => $question) {
        if (substr($text, -strlen($suffix)) === $suffix) {
            $prefix = rtrim(substr($text, 0, -strlen($suffix)), '. ');
            $text = $prefix . '. ' . $question;
            break;
        }
    }

    // Kasus khusus: label ACID tanpa pertanyaan
    if (preg_match('/ACID:\s*(Atomicity|Consistency|Isolation|Durability)/iu', $text) && !preg_match('/\?/u', $text)) {
        if (preg_match('/ACID:\s*(\w+)/iu', $text, $m)) {
            $text = preg_replace('/ACID:\s*\w+.*$/iu', 'Apa arti prinsip ' . $m[1] . ' dalam ACID', $text);
        }
    }

    return rtrim($text, '?') . '?';
};

$normalizeOptions = static function (array $options): array {
    return array_map(static function ($option) {
        return trim(preg_replace('/\s+/u', ' ', (string) $option));
    }, $options);
};

$makeQuestion = static function (
    string $text,
    $correct,
    array $wrong,
    string $explanation,
    int $seed
) use ($rotateOptions, $clarifyQuestion, $normalizeOptions): array {
    $text = $clarifyQuestion($text);
    $options = $normalizeOptions($rotateOptions(
        array_merge([(string) $correct], array_map('strval', array_slice($wrong, 0, 3))),
        $seed
    ));

    return [
        'text' => $text,
        'options' => $options,
        'answer' => array_search((string) $correct, $options, true),
        'explanation' => trim($explanation),
    ];
};

return [
    'makeQuestion' => $makeQuestion,
];
