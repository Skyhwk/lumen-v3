<?php

/**
 * 100 soal ALGORITMA untuk assessment programmer (manager scope).
 * Bahasa Indonesia, skenario kontekstual, referensi jurnal/literatur.
 * Return: array of ['text','options','answer','explanation']
 */

$helpers = require __DIR__ . '/QuestionSeederHelpers.php';
$make = $helpers['makeQuestion'];

$questions = [];

// ── 1. Kompleksitas waktu — skenario sistem nyata (25) ─────────────────────
$complexities = [
    [
        'Sistem perpustakaan digital memindai daftar 50.000 buku secara berurutan untuk mencari ISBN. Metode ini setara dengan kompleksitas waktu',
        'O(n)',
        ['O(1)', 'O(log n)', 'O(n log n)'],
        'Pencarian linear mengecek setiap elemen paling banyak sekali. Penelitian JOCSTEC (2024) menunjukkan linear search tidak efisien untuk dataset besar dibanding binary search.',
    ],
    [
        'Portal akademik menyimpan NIM mahasiswa dalam array terurut. Pencarian NIM memakai metode bagi-dua (binary search). Kompleksitas waktu pencariannya',
        'O(log n)',
        ['O(n)', 'O(1)', 'O(n²)'],
        'Setiap iterasi mempersempit ruang pencarian menjadi setengah. JOCSTEC (2024) melaporkan efisiensi binary search hingga >90% lebih cepat dari linear search pada 10.000+ data.',
    ],
    [
        'Aplikasi absensi mengurutkan 500 nama karyawan dengan bubble sort. Kompleksitas rata-ratanya',
        'O(n²)',
        ['O(n)', 'O(log n)', 'O(n log n)'],
        'Dua loop bersarang membandingkan pasangan elemen. Cocok untuk data kecil, tidak ideal untuk skala produksi besar (Cormen et al., Introduction to Algorithms).',
    ],
    [
        'Tim data processing menggabungkan hasil sort sub-array dengan merge sort pada 100.000 record transaksi. Kompleksitas waktunya',
        'O(n log n)',
        ['O(n)', 'O(n²)', 'O(log n)'],
        'Merge sort memakai strategi divide-and-conquer dengan tahap merge linear. Standar industri untuk dataset besar yang membutuhkan stabilitas urutan.',
    ],
    [
        'Dashboard monitoring mengakses elemen ke-500 dari array berdasarkan index. Operasi akses ini',
        'O(1)',
        ['O(n)', 'O(log n)', 'O(n log n)'],
        'Array menyediakan akses langsung via index. JSIT (2024) mencatat akses random array sebagai O(1), ideal untuk lookup berindeks.',
    ],
    [
        'Antrian notifikasi real-time menambahkan pesan baru di depan antrian linked list. Kompleksitas insert di awal',
        'O(1)',
        ['O(n)', 'O(log n)', 'O(n²)'],
        'Cukup ubah pointer head tanpa menggeser seluruh elemen. JSIT (2024) menunjukkan linked list unggul untuk insert/delete di ujung.',
    ],
    [
        'Sistem inventory memasukkan item baru di tengah array dinamis (perlu menggeser elemen setelahnya). Kompleksitas insert di tengah',
        'O(n)',
        ['O(1)', 'O(log n)', 'O(n log n)'],
        'Elemen setelah posisi insert harus digeser. Trade-off array: akses cepat O(1), insert tengah mahal O(n).',
    ],
    [
        'Cache lookup session user di hash table tanpa collision berat. Kompleksitas rata-rata',
        'O(1)',
        ['O(n)', 'O(log n)', 'O(n²)'],
        'Hash map ideal memberikan lookup konstan rata-rata. Digunakan luas untuk cache dan dictionary (Cormen et al.).',
    ],
    [
        'Worst case hash table saat semua key masuk bucket yang sama (collision ekstrem). Kompleksitas lookup',
        'O(n)',
        ['O(1)', 'O(log n)', 'O(n log n)'],
        'Degenerasi menjadi pencarian linear di bucket. Desain hash function dan load factor kritis untuk menghindari ini.',
    ],
    [
        'Modul rekomendasi teman di social graph (10.000 node, 50.000 edge) menjalankan BFS. Kompleksitas waktunya',
        'O(V + E)',
        ['O(V × E)', 'O(V²)', 'O(E²)'],
        'Setiap vertex dan edge dikunjungi paling banyak sekali. BFS standar untuk shortest path pada graf tak berbobot.',
    ],
    [
        'Analisis dependency antar modul software (graf arah) dengan DFS adjacency list. Kompleksitas waktunya',
        'O(V + E)',
        ['O(V × E)', 'O(V²)', 'O(E²)'],
        'DFS adjacency list sama efisiennya dengan BFS: O(V+E). Digunakan untuk deteksi cycle dan topological sort.',
    ],
    [
        'Sorting 20.000 invoice dengan quick sort (pivot seimbang). Kompleksitas average case',
        'O(n log n)',
        ['O(n)', 'O(n²)', 'O(log n)'],
        'Partisi seimbang menghasilkan subarray log n level. Praktis cepat in-place untuk data umum.',
    ],
    [
        'Quick sort pada data sudah terurut dengan pivot selalu elemen minimum. Worst case complexity',
        'O(n²)',
        ['O(n log n)', 'O(n)', 'O(log n)'],
        'Partisi tidak seimbang → subarray hampir n-1 elemen. Randomized pivot atau introsort mengatasi kasus ini.',
    ],
    [
        'Priority queue job scheduler memasukkan task baru ke min-heap. Kompleksitas insert',
        'O(log n)',
        ['O(1)', 'O(n)', 'O(n log n)'],
        'Bubble up tinggi pohon log n. Heap efisien untuk antrian prioritas dinamis.',
    ],
    [
        'Worker mengambil task prioritas tertinggi dari max-heap (extract-max). Kompleksitas',
        'O(log n)',
        ['O(1)', 'O(n)', 'O(n²)'],
        'Replace root lalu heapify down. Operasi standar priority queue pada sistem antrian.',
    ],
    [
        'Nested loop membandingkan setiap produk dengan setiap promo (n × n). Kompleksitas',
        'O(n²)',
        ['O(n)', 'O(log n)', 'O(2n)'],
        'Iterasi kuadratik — pertumbuhan eksplosif saat n besar. Perlu optimasi (hash join, indexing) di skala produksi.',
    ],
    [
        'Loop tunggal validasi 1.000 form input dengan operasi O(1) per field. Kompleksitas total',
        'O(n)',
        ['O(1)', 'O(log n)', 'O(n²)'],
        'Linear terhadap jumlah input. Proporsional dan dapat diterima untuk validasi batch.',
    ],
    [
        'Algoritma binary search pattern: variabel dibagi dua setiap iterasi pada 1 juta record. Kompleksitas',
        'O(log n)',
        ['O(n)', 'O(1)', 'O(n log n)'],
        'Pola bagi-dua menghasilkan log₂(1.000.000) ≈ 20 iterasi. JOCSTEC (2024) mengkonfirmasi efisiensi pada array terurut.',
    ],
    [
        'Routing GPS sparse graph dengan Dijkstra + priority queue. Kompleksitas umum',
        'O((V + E) log V)',
        ['O(V²)', 'O(V + E)', 'O(E log E)'],
        'Relax edge dengan heap. Standar untuk shortest path non-negatif pada graf sparse.',
    ],
    [
        'Fibonacci rekursif naive tanpa memo untuk f(30). Kompleksitas',
        'O(2^n)',
        ['O(n)', 'O(n log n)', 'O(n²)'],
        'Pohon rekursi eksponensial karena submasalah dihitung berulang. Contoh klasik kebutuhan dynamic programming.',
    ],
    [
        'Fibonacci dengan memoization/tabulation. Kompleksitas',
        'O(n)',
        ['O(2^n)', 'O(n²)', 'O(log n)'],
        'Setiap submasalah dihitung sekali. DP mengubah eksponensial menjadi linear.',
    ],
    [
        'Lower bound teorema comparison-based sorting (merge sort, quick sort, heap sort). Tidak bisa lebih baik dari',
        'Ω(n log n)',
        ['Ω(n)', 'Ω(n²)', 'Ω(1)'],
        'Teorema batas bawah sort berbasis perbandingan. Dasar teori mengapa O(n log n) optimal untuk general sort.',
    ],
    [
        'Operasi push dan pop pada call stack saat eksekusi fungsi. Kompleksitas',
        'O(1)',
        ['O(n)', 'O(log n)', 'O(n²)'],
        'Stack LIFO: operasi di top selalu konstan. Fundamental untuk call stack dan undo.',
    ],
    [
        'Antrian print job (enqueue/dequeue) dengan linked list. Kompleksitas per operasi',
        'O(1)',
        ['O(n)', 'O(log n)', 'O(n²)'],
        'Pointer front/rear memungkinkan enqueue/dequeue konstan. Model FIFO round-robin scheduling.',
    ],
    [
        'Mencari gaji minimum dan maksimum dari 5.000 karyawan tanpa sorting (satu pass). Kompleksitas',
        'O(n)',
        ['O(1)', 'O(log n)', 'O(n log n)'],
        'Scan seluruh array sekali cukup. Tidak perlu sort O(n log n) jika hanya butuh min/max.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $c = $complexities[$n];
    $questions[] = $make($c[0], $c[1], $c[2], $c[3], $n);
}

// ── 2. Struktur data — skenario aplikasi (25) ────────────────────────────────
$dataStructures = [
    [
        'Sistem antrian tiket helpdesk: pelanggan dilayani sesuai urutan kedatangan. Struktur data paling tepat',
        'Queue (Antrian)',
        ['Stack (Tumpukan)', 'Heap', 'Graph'],
        'FIFO — First In First Out. Pelanggan pertama masuk dilayani pertama (model antrian nyata).',
    ],
    [
        'Fitur undo/redo di text editor: operasi terakhir dibatalkan dulu. Struktur data paling tepat',
        'Stack (Tumpukan)',
        ['Queue (Antrian)', 'Hash Map', 'Tree'],
        'LIFO — Last In First Out. Undo = pop dari stack operasi.',
    ],
    [
        'Cache lookup user session by token dengan akses cepat rata-rata. Struktur paling efisien',
        'Hash Map / Dictionary',
        ['Array', 'Linked List', 'Stack'],
        'Hash table O(1) rata-rata untuk key-value lookup. Standar session store in-memory.',
    ],
    [
        'Marketplace dengan 1.000 produk perlu range query harga dan pencarian terurut. Struktur ideal menurut penelitian AVL Tree (JRSIKOM, 2025)',
        'Balanced BST (mis. AVL Tree / Red-Black Tree)',
        ['Stack', 'Queue', 'Array tidak terurut'],
        'JRSIKOM (2025) melaporkan AVL Tree menjaga O(log n) untuk insert/search pada marketplace 1.000+ produk.',
    ],
    [
        'Job scheduler hospital yang selalu proses pasien gawat darurat duluan. Struktur paling tepat',
        'Heap / Priority Queue',
        ['Stack', 'Queue biasa', 'Array biasa'],
        'Heap root selalu min/max priority. Cocok untuk scheduling berbasis prioritas.',
    ],
    [
        'Model relasi many-to-many: mahasiswa ↔ mata kuliah di SIAKAD. Representasi paling natural',
        'Graph (Graf)',
        ['Stack', 'Queue', 'Single linked list'],
        'Node + edge memodelkan relasi kompleks antar entitas.',
    ],
    [
        'Tombol Back pada browser mengingat halaman sebelumnya. Struktur data di balik fitur ini',
        'Stack (Tumpukan)',
        ['Queue', 'Hash Map', 'Graph'],
        'Navigasi back = pop dari history stack. Forward = stack terpisah.',
    ],
    [
        'OS round-robin scheduling: proses CPU bergiliran time-slice. Struktur antrian proses',
        'Queue (Antrian)',
        ['Stack', 'Heap saja', 'Set'],
        'Proses masuk antrian dan dirotasi — model FIFO round-robin.',
    ],
    [
        'Deteksi cycle pada linked list (bug infinite loop pointer). Algoritma paling efisien',
        'Floyd cycle detection (dua pointer)',
        ['Brute force nested loop', 'Sort dulu', 'Hash map saja'],
        'Tortoise & hare: O(n) waktu, O(1) ruang. Klasik interview dan debugging pointer.',
    ],
    [
        'Implementasi LRU cache (Least Recently Used) pada API gateway. Kombinasi struktur standar',
        'Hash Map + Doubly Linked List',
        ['Stack saja', 'Array saja', 'Binary heap saja'],
        'O(1) get/put dengan eviction LRU. Pattern cache produksi (Redis, Memcached internal).',
    ],
    [
        'Autocomplete pencarian kota pada form alamat (prefix matching). Struktur data khusus',
        'Trie (Prefix Tree)',
        ['Stack', 'Queue', 'Heap'],
        'Trie efisien untuk prefix matching — dipakai search engine dan keyboard suggestion.',
    ],
    [
        'Deteksi connected components pada jaringan social (siapa terhubung dengan siapa). Struktur/algoritma',
        'Union-Find (Disjoint Set)',
        ['Sorting array', 'Stack parsing', 'Queue BFS saja'],
        'Union-Find efisien untuk grup dinamis yang sering di-merge.',
    ],
    [
        'Graf sparse (sedikit edge) dengan 10.000 vertex. Representasi paling hemat memori',
        'Adjacency list (daftar ketetanggaan)',
        ['Adjacency matrix selalu lebih baik', 'Keduanya sama', 'Matrix untuk sparse'],
        'Matrix O(V²), list O(V+E). Penelitian JSIT (2024) menekankan pemilihan representasi sesuai kepadatan graf.',
    ],
    [
        'Buffer streaming sensor IoT ukuran tetap: data baru menimpa data lama saat penuh. Struktur',
        'Circular buffer (ring buffer)',
        ['Random access index besar', 'Graph traversal', 'Sorting'],
        'Ring buffer overwrite oldest when full — umum di embedded dan streaming.',
    ],
    [
        'Sistem filter "mungkin pernah login" tanpa menyimpan semua email (hemat memori, toleransi false positive)',
        'Bloom filter',
        ['Hash table exact', 'Array sorted', 'BST'],
        'Probabilistic membership: bisa false positive, tidak false negative. Dipakai di database dan CDN.',
    ],
    [
        'Alternatif balanced tree dengan layered linked list, expected search O(log n)',
        'Skip list',
        ['Array biasa', 'Stack', 'Queue'],
        'Probabilistic structure — implementasi lebih sederhana dari AVL/red-black di beberapa sistem.',
    ],
    [
        'Editor kode dengan undo DAN redo. Implementasi paling tepat',
        'Dua stack (undo stack & redo stack)',
        ['Single queue', 'Hash map', 'BST'],
        'Undo pop dari undo stack, redo pop dari redo stack. Pattern standar aplikasi produktivitas.',
    ],
    [
        'Koleksi unique tag artikel blog (tidak boleh duplikat). Tipe data Python paling tepat',
        'set',
        ['list', 'tuple', 'dict'],
        'Set menjamin elemen unik dengan lookup O(1) rata-rata.',
    ],
    [
        'Key dictionary Python harus immutable/hashable. Tipe sequence yang bisa jadi key',
        'tuple',
        ['list', 'dict', 'set'],
        'Tuple immutable sehingga hashable; list mutable tidak bisa jadi dict key.',
    ],
    [
        'Insert frequent di tengah list 100.000 item (pointer sudah ada). Array vs Linked List',
        'Linked List lebih efisien (O(1) jika pointer ada)',
        ['Array selalu lebih efisien', 'Sama saja', 'Stack lebih cocok'],
        'JSIT (2024): array perlu shift O(n), linked list cukup ubah pointer jika posisi sudah diketahui.',
    ],
    [
        'ArrayList Java resize otomatis. Insert append amortized complexity',
        'O(1) amortized',
        ['O(n) selalu', 'O(log n)', 'O(n²)'],
        'Resize jarang terjadi sehingga biaya disebar — amortized analysis (Cormen et al.).',
    ],
    [
        'Index database MySQL/PostgreSQL umumnya memakai B-Tree karena',
        'Dioptimalkan untuk pembacaan block disk',
        ['Hanya untuk RAM kecil', 'Lebih lambat dari linked list', 'Tidak support range scan'],
        'Sitasi (2023) & CAI (2024): B-Tree mengurangi I/O disk dan mendukung range query — standar RDBMS.',
    ],
    [
        'In-order traversal BST pada katalog produk terurut harga. Hasil traversal',
        'Elemen terurut ascending (menaik)',
        ['Elemen terbalik saja', 'Level order', 'Urutan random'],
        'Left-root-right menghasilkan urutan sorted ascending.',
    ],
    [
        'Graf lengkap (complete graph) berarah vs tak berarah: V vertex, maksimum edge',
        'Tak berarah: V(V-1)/2, Berarah: V(V-1)',
        ['Sama untuk keduanya', 'V² untuk tak berarah', 'V untuk berarah'],
        'Rumus edge complete graph — dasar analisis kompleksitas graf.',
    ],
    [
        'Penelitian BST vs AVL (Journal Mediapublikasi, 2024) pada 100 data mahasiswa. Kesimpulan utama',
        'AVL Tree lebih stabil dan cepat karena self-balancing via rotasi',
        ['BST selalu lebih cepat', 'Linked list lebih efisien', 'Array tidak terurut optimal'],
        'Mediapublikasi (2024): BST tinggi pohon meningkat signifikan, AVL menjaga keseimbangan dan pencarian lebih konsisten.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $d = $dataStructures[$n];
    $questions[] = $make($d[0], $d[1], $d[2], $d[3], 100 + $n);
}

// ── 3. Tracing kode & logika — pseudocode Indonesia (25) ───────────────────
$tracingScenarios = [
    ['Validasi total nilai UTS 5 mata kuliah', 'sum', 5],
    ['Hitung jumlah subscriber ganda setiap bulan', 'power', null],
    ['Kalkulasi faktorial untuk kombinatorik', 'factorial', null],
    ['Menentukan FPB kapasitas ruang meeting', 'gcd', null],
    ['Cek NIM terdaftar di daftar peserta ujian', 'search', null],
];
for ($n = 1; $n <= 25; $n++) {
    $scenarioIdx = ($n - 1) % 5;
    $type = $scenarioIdx;
    if ($type === 0) {
        $limit = 3 + ($n % 8);
        $sum = 0;
        for ($i = 1; $i <= $limit; $i++) {
            $sum += $i;
        }
        $questions[] = $make(
            'Perhatikan pseudocode berikut untuk menghitung total nilai UTS. total = 0; untuk i = 1 sampai ' . $limit . ' lakukan total = total + i; selesai. Berapa nilai variabel total setelah algoritma selesai',
            (string) $sum,
            [(string) ($sum + 1), (string) ($sum - 1), (string) ($limit * 2)],
            'Jumlah deret 1+' . $limit . ' = n(n+1)/2 = ' . $sum . '. Referensi: Cormen et al., Introduction to Algorithms.',
            200 + $n
        );
    } elseif ($type === 1) {
        $times = 4 + ($n % 6);
        $x = 1;
        for ($i = 0; $i < $times; $i++) {
            $x *= 2;
        }
        $questions[] = $make(
            'Perhatikan pseudocode berikut. jumlah = 1; ulangi ' . $times . ' kali: jumlah = jumlah × 2; selesai. Berapa nilai variabel jumlah setelah algoritma selesai',
            (string) $x,
            [(string) ($x + 1), (string) ($times * 2), (string) pow(2, max(0, $times - 1))],
            'jumlah = 2^' . $times . ' = ' . $x . '. Pola pertumbuhan eksponensial.',
            210 + $n
        );
    } elseif ($type === 2) {
        $limit = 2 + (intdiv($n, 5) % 7);
        $fact = 1;
        for ($i = 2; $i <= $limit; $i++) {
            $fact *= $i;
        }
        $questions[] = $make(
            'Perhatikan pseudocode faktorial berikut. hasil = 1; untuk i = 2 sampai ' . $limit . ' lakukan hasil = hasil × i; selesai. Berapa nilai variabel hasil setelah algoritma selesai',
            (string) $fact,
            [(string) ($fact + $limit), (string) ($fact - 1), (string) ($limit * 2)],
            $limit . '! = ' . $fact . '.',
            220 + $n
        );
    } elseif ($type === 3) {
        $a = 12 + ($n % 20);
        $b = $a + 3 + ($n % 5);
        $origA = $a;
        $origB = $b;
        while ($b != 0) {
            $temp = $b;
            $b = $a % $b;
            $a = $temp;
        }
        $questions[] = $make(
            'Perhatikan algoritma Euclid untuk mencari FPB. a = ' . $origA . ', b = ' . $origB . '; selama b ≠ 0 lakukan: temp = b; b = a mod b; a = temp; selesai. Berapa nilai FPB(a, b) setelah algoritma selesai',
            (string) $a,
            [(string) ($a + 1), '1', (string) $origB],
            'Algoritma Euclid: FPB(' . $origA . ', ' . $origB . ') = ' . $a . '.',
            230 + $n
        );
    } else {
        $base = 1000 + ($n * 7);
        $arr = [$base, $base + 2, $base + 5, $base + 7];
        $target = $base + ($n % 2 === 0 ? 5 : 9999);
        $found = in_array($target, $arr, true);
        $correctAnswer = $found ? 'Ditemukan' : 'Tidak ditemukan';
        $wrongAnswers = $found
            ? ['Tidak ditemukan', 'Null', '0']
            : ['Ditemukan', 'Null', '0'];
        $questions[] = $make(
            'Perhatikan array berikut: [' . implode(', ', $arr) . ']. Nilai yang dicari: ' . $target . '. Jika dijalankan algoritma pencarian linear (cek satu per satu dari kiri ke kanan), apakah nilai target ditemukan',
            $correctAnswer,
            $wrongAnswers,
            'Pencarian linear memeriksa setiap elemen berurutan. Target ' . $target . ' ' . ($found ? 'ada' : 'tidak ada') . ' di array. Referensi: JOCSTEC (2024).',
            240 + $n
        );
    }
}

// ── 4. Pola algoritma — konteks nyata + referensi (25) ───────────────────────
$patterns = [
    [
        'Fungsi rekursif tanpa base case pada proses approval bertingkat. Risiko utama',
        'Stack overflow (rekursi tak terbatas)',
        ['Mempercepat I/O', 'Memory leak di database', 'Mengganti loop'],
        'Base case wajib menghentikan rekursi. Tanpa itu, call stack overflow — bug produksi umum.',
    ],
    [
        'Merge sort pada dataset log 1 juta baris di-split jadi sub-array. Paradigma algoritma',
        'Divide and Conquer (Bagi dan Taklukkan)',
        ['Greedy semata', 'Brute force', 'Randomized only'],
        'Bagi masalah, selesaikan submasalah, gabungkan hasil. Merge sort contoh klasik (Cormen et al.).',
    ],
    [
        'Algoritma greedy memilih langkah lokal optimal di routing ATM. Keterbatasannya',
        'Tidak selalu menghasilkan solusi optimal global',
        ['Tidak bisa memakai loop', 'Selalu O(1)', 'Hanya untuk string'],
        'Greedy lokal ≠ optimal global. Contoh counter: coin change dengan denom {1,3,4} target 6.',
    ],
    [
        'Optimasi overlapping subproblems pada perhitungan fibonacci dan knapsack. Teknik',
        'Dynamic Programming (memoization/tabulation)',
        ['Greedy saja', 'Brute force saja', 'Randomization'],
        'DP: overlapping subproblems + optimal substructure. Mengubah eksponensial jadi polinomial.',
    ],
    [
        'Deteksi palindrome pada username dan two-sum pada array terurut harga. Teknik efisien',
        'Two pointers (dua pointer)',
        ['Graph DFS', 'Hash collision', 'Heapify'],
        'Dua indeks bergerak koordinasi — O(n) pada array terurut.',
    ],
    [
        'Mencari substring panjang maksimum tanpa duplikat karakter (sliding window classic). Teknik',
        'Sliding window (jendela geser)',
        ['Sort global', 'DFS tree', 'Union find'],
        'Window expand/shrink O(n) — populer di string processing dan stream analytics.',
    ],
    [
        'Solver Sudoku dan N-Queens: coba, jika gagal batalkan. Paradigma',
        'Backtracking',
        ['Merge sort', 'Hash lookup', 'BFS shortest path'],
        'Coba kemungkinan, undo jika dead-end. Dasar constraint satisfaction problems.',
    ],
    [
        'Stable sort pada payroll: karyawan dengan gaji sama mempertahankan urutan input awal. Artinya',
        'Elemen equal mempertahankan urutan relatif awal',
        ['Selalu O(n)', 'In-place selalu', 'Tidak pakai perbandingan'],
        'Merge sort stable; quick sort default tidak. Penting untuk multi-key sorting.',
    ],
    [
        'Quick sort in-place pada array memory terbatas. Keuntungan space',
        'O(1) extra space (aside from recursion stack)',
        ['O(n) extra selalu', 'Tidak ubah array', 'Hanya linked list'],
        'In-place sorting hemat memori — trade-off vs merge sort O(n) auxiliary.',
    ],
    [
        'Sorting 1.000 nilai quiz (skor 0–100). Algoritma non-comparison paling cocok',
        'Counting sort',
        ['Quick sort saja', 'Merge sort saja', 'Bubble sort'],
        'Range integer kecil terbatas → O(n+k). Lebih cepat dari comparison sort untuk range sempit.',
    ],
    [
        'Sorting nomor invoice 8 digit. Radix sort mengurutkan berdasarkan',
        'Digit/kelompok digit dari LSD atau MSD',
        ['Hash value', 'Alamat pointer', 'Pivot random'],
        'Non-comparative sort — efisien untuk fixed-width keys.',
    ],
    [
        'Penjadwalan mata kuliah prasyarat (A harus sebelum B). Graph harus',
        'Directed Acyclic Graph (DAG) — tanpa cycle',
        ['Undirected graph', 'Graph with cycle', 'Tree saja'],
        'Cycle → tidak ada topological order valid. Dasar course prerequisite scheduling.',
    ],
    [
        'DP top-down fibonacci dengan cache. Pendekatan',
        'Memoization (rekursif + cache)',
        ['Bottom-up iteratif selalu', 'Tanpa cache', 'Greedy'],
        'Top-down: rekursi + simpan hasil submasalah.',
    ],
    [
        'DP bottom-up knapsack mengisi tabel iteratif. Pendekatan',
        'Tabulation (bottom-up)',
        ['Top-down rekursif', 'Randomized', 'Brute force eksponensial'],
        'Bottom-up: isi tabel dari submasalah kecil ke besar.',
    ],
    [
        'Precondition binary search pada daftar NIM terurut ascending. Syarat wajib',
        'Data harus sudah terurut (sorted)',
        ['Data unique saja', 'Data integer saja', 'Graph connected'],
        'JOCSTEC (2024): binary search hanya valid pada array terurut — prasyarat fundamental.',
    ],
    [
        'Kadane algorithm menemukan pada array profit harian',
        'Maximum subarray sum (jumlah subarray maksimum)',
        ['Shortest path', 'Minimum spanning tree', 'Topological order'],
        'DP O(n) untuk maximum subarray — classic coding interview & financial analytics.',
    ],
    [
        'Prefix sum array untuk laporan total penjualan minggu X–Y. Berapa kompleksitas query range sum setelah prefix sum dibangun',
        'O(1) per query (setelah build O(n))',
        ['O(n) per query selalu', 'O(log n) per query', 'O(n²) per query'],
        'prefix[i] = sum[0..i-1], range sum = prefix[r+1] - prefix[l].',
    ],
    [
        'Bit trick: n & (n-1) pada analisis bitmask. Efeknya',
        'Menghapus bit 1 paling kanan',
        ['Menghapus semua bit', 'Menambah 1', 'Negasi'],
        'Trick Brian Kernighan — hitung jumlah bit 1 efisien.',
    ],
    [
        'XOR semua elemen array [a,a,b,b,c] (duplikat cancel). Sisa',
        'Elemen unik c (jika hanya satu yang unik)',
        ['Elemen terbesar', 'Jumlah total', 'Elemen terkecil'],
        'a XOR a = 0, sisa elemen without pair.',
    ],
    [
        'Rekursi sangat dalam (100.000 level) di JavaScript/Java. Risiko',
        'Stack overflow — call stack terbatas',
        ['Heap overflow', 'Disk full', 'Cache miss'],
        'Call stack terbatas (~ribuan frame). Solusi: iterasi atau tail-call optimization.',
    ],
    [
        'DFS iteratif pada dependency graph modul. Struktur bantu',
        'Explicit stack (stack eksplisit)',
        ['Queue saja', 'Heap saja', 'Hash map saja'],
        'Simulasi call stack rekursif dengan stack manual.',
    ],
    [
        'BFS shortest path pada graf tak berbobot (jarak kantor). Properti benar',
        'First time reached = jarak terpendek',
        ['Selalu salah', 'Hanya untuk tree', 'Hanya untuk DAG'],
        'BFS layer-by-layer menjamin shortest path unweighted.',
    ],
    [
        'Dijkstra dengan edge weight negatif (cashback). Algoritma tidak cocok karena',
        'Negative edge weights merusak asumsi relax greedy',
        ['Non-negative weights', 'Priority queue', 'Sparse graph'],
        'Negative edge → gunakan Bellman-Ford. Dijkstra hanya non-negative.',
    ],
    [
        'Bellman-Ford menangani negative weights dan',
        'Deteksi negative cycle',
        ['Hanya non-negative', 'Hanya DAG', 'Unweighted only'],
        'Relax V-1 kali + deteksi cycle negatif — routing dengan penalty.',
    ],
    [
        'Dynamic array push: resize sesekali O(n) tapi rata-rata O(1). Analisis',
        'Amortized analysis — biaya resize disebar',
        ['O(1) worst every time', 'O(n) every push', 'O(log n)'],
        'Amortized O(1) — konsep fundamental analisis algoritma (Cormen et al.).',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $p = $patterns[$n];
    $questions[] = $make($p[0], $p[1], $p[2], $p[3], 300 + $n);
}

return array_slice($questions, 0, 100);
