<?php

/**
 * 100 soal ALGORITMA untuk assessment programmer (manager scope).
 * Return: array of ['text','options','answer','explanation']
 */

$rotate = static function (array $options, int $seed): array {
    $shift = abs($seed) % max(1, count($options));
    return array_merge(array_slice($options, $shift), array_slice($options, 0, $shift));
};

$make = static function (string $text, $correct, array $wrong, string $explanation, int $seed) use ($rotate): array {
    $options = $rotate(array_merge([(string) $correct], array_map('strval', array_slice($wrong, 0, 3))), $seed);
    return [
        'text' => $text,
        'options' => $options,
        'answer' => array_search((string) $correct, $options, true),
        'explanation' => $explanation,
    ];
};

$questions = [];

// ── 1. Big-O complexity (25) ───────────────────────────────────────────────
$complexities = [
    ['Linear search pada array n elemen', 'O(n)', ['O(1)', 'O(log n)', 'O(n log n)'], 'Setiap elemen dicek paling banyak sekali.'],
    ['Binary search pada array terurut', 'O(log n)', ['O(n)', 'O(1)', 'O(n²)'], 'Ruang pencarian dibelah dua setiap iterasi.'],
    ['Bubble sort rata-rata', 'O(n²)', ['O(n)', 'O(log n)', 'O(n log n)'], 'Dua loop bersarang membandingkan pasangan.'],
    ['Merge sort', 'O(n log n)', ['O(n)', 'O(n²)', 'O(log n)'], 'Divide and conquer dengan merge linear.'],
    ['Akses elemen array by index', 'O(1)', ['O(n)', 'O(log n)', 'O(n log n)'], 'Index langsung ke lokasi memori.'],
    ['Insert di awal linked list', 'O(1)', ['O(n)', 'O(log n)', 'O(n²)'], 'Cukup ubah pointer head.'],
    ['Insert di tengah array dinamis (shift elemen)', 'O(n)', ['O(1)', 'O(log n)', 'O(n log n)'], 'Elemen setelah posisi insert digeser.'],
    ['Hash table lookup rata-rata', 'O(1)', ['O(n)', 'O(log n)', 'O(n²)'], 'Hash map ideal tanpa collision berat.'],
    ['Hash table lookup worst case (banyak collision)', 'O(n)', ['O(1)', 'O(log n)', 'O(n log n)'], 'Semua key masuk bucket yang sama.'],
    ['BFS pada graf dengan V vertex dan E edge', 'O(V + E)', ['O(V × E)', 'O(V²)', 'O(E²)'], 'Setiap vertex dan edge dikunjungi sekali.'],
    ['DFS pada graf dengan V vertex dan E edge', 'O(V + E)', ['O(V × E)', 'O(V²)', 'O(E²)'], 'Sama seperti BFS untuk adjacency list.'],
    ['Quick sort average case', 'O(n log n)', ['O(n)', 'O(n²)', 'O(log n)'], 'Partisi seimbang mengurangi subarray.'],
    ['Quick sort worst case (pivot selalu min/max)', 'O(n²)', ['O(n log n)', 'O(n)', 'O(log n)'], 'Partisi tidak seimbang.'],
    ['Heap insert', 'O(log n)', ['O(1)', 'O(n)', 'O(n log n)'], 'Bubble up tinggi pohon log n.'],
    ['Heap extract-min/max', 'O(log n)', ['O(1)', 'O(n)', 'O(n²)'], 'Replace root lalu heapify down.'],
    ['Two nested loop n × n', 'O(n²)', ['O(n)', 'O(log n)', 'O(2n)'], 'Iterasi kuadratik.'],
    ['Loop n dengan operasi O(1) di dalam', 'O(n)', ['O(1)', 'O(log n)', 'O(n²)'], 'Linear terhadap n.'],
    ['Loop log n (bagi dua setiap iterasi)', 'O(log n)', ['O(n)', 'O(1)', 'O(n log n)'], 'Contoh binary search pattern.'],
    ['Dijkstra dengan priority queue (sparse)', 'O((V + E) log V)', ['O(V²)', 'O(V + E)', 'O(E log E)'], 'Relax edge dengan heap.'],
    ['Fibonacci rekursif naive f(n)', 'O(2^n)', ['O(n)', 'O(n log n)', 'O(n²)'], 'Pohon rekursi eksponensial.'],
    ['Fibonacci dengan memoization', 'O(n)', ['O(2^n)', 'O(n²)', 'O(log n)'], 'Setiap submasalah dihitung sekali.'],
    ['Sorting dengan comparison-based lower bound', 'Ω(n log n)', ['Ω(n)', 'Ω(n²)', 'Ω(1)'], 'Teorema batas bawah sort perbandingan.'],
    ['Stack push dan pop', 'O(1)', ['O(n)', 'O(log n)', 'O(n²)'], 'Operasi di top stack.'],
    ['Queue enqueue dan dequeue (linked list)', 'O(1)', ['O(n)', 'O(log n)', 'O(n²)'], 'Pointer front/rear.'],
    ['Mencari min/max tanpa sort (satu pass)', 'O(n)', ['O(1)', 'O(log n)', 'O(n log n)'], 'Scan seluruh array sekali.'],
];
for ($n = 0; $n < 25; $n++) {
    $c = $complexities[$n];
    $questions[] = $make(
        'Kompleksitas waktu (worst/average yang relevan) untuk: ' . $c[0],
        $c[1],
        $c[2],
        $c[3],
        $n
    );
}

// ── 2. Struktur data (25) ──────────────────────────────────────────────────
$dataStructures = [
    ['FIFO (First In First Out) paling cocok untuk', 'Queue', ['Stack', 'Heap', 'Graph'], 'Antrian: masuk pertama keluar pertama.'],
    ['LIFO (Last In First Out) paling cocok untuk', 'Stack', ['Queue', 'Hash Map', 'Tree'], 'Stack: undo, call stack.'],
    ['Lookup key-value dengan akses cepat rata-rata', 'Hash Map / Dictionary', ['Array', 'Linked List', 'Stack'], 'Hash table O(1) average.'],
    ['Menyimpan data terurut dan range query efisien', 'Balanced BST (mis. Red-Black Tree)', ['Stack', 'Queue', 'Array tidak terurut'], 'BST in-order terurut, O(log n) search.'],
    ['Prioritas dinamis (always get min/max cepat)', 'Heap / Priority Queue', ['Stack', 'Queue', 'Array biasa'], 'Heap root selalu min/max.'],
    ['Representasi relasi many-to-many antar entitas', 'Graph', ['Stack', 'Queue', 'Single linked list'], 'Node + edge model relasi.'],
    ['Menyimpan history navigasi browser (back)', 'Stack', ['Queue', 'Hash Map', 'Graph'], 'Back = pop stack.'],
    ['Job scheduling round-robin', 'Queue', ['Stack', 'Heap saja', 'Set'], 'Process masuk antrian bergiliran.'],
    ['Deteksi cycle pada linked list paling efisien', 'Floyd cycle detection (two pointers)', ['Brute force nested loop', 'Sort dulu', 'Hash map saja'], 'Tortoise & hare O(n) O(1) space.'],
    ['Implementasi LRU cache umumnya memakai', 'Hash Map + Doubly Linked List', ['Stack saja', 'Array saja', 'Binary heap saja'], 'O(1) get/put dengan eviction LRU.'],
    ['Prefix tree untuk autocomplete kata', 'Trie', ['Stack', 'Queue', 'Heap'], 'Trie efisien prefix matching.'],
    ['Union-Find (Disjoint Set) cocok untuk', 'Deteksi connected components', ['Sorting array', 'Stack parsing', 'Queue BFS saja'], 'Union-Find untuk grup dinamis.'],
    ['Adjacency list vs adjacency matrix: sparse graph', 'Adjacency list lebih hemat memori', ['Adjacency matrix selalu lebih baik', 'Keduanya sama', 'Adjacency matrix untuk sparse'], 'Matrix O(V²), list O(V+E).'],
    ['Deque (double-ended queue) memungkinkan', 'Insert/delete di kedua ujung O(1)', ['Hanya insert di rear', 'Hanya FIFO', 'Hanya LIFO'], 'Deque fleksibel dua ujung.'],
    ['Set matematis (unique elements) di Python', 'set', ['list', 'tuple', 'dict'], 'set menjamin unik.'],
    ['Immutable sequence di Python untuk hash key', 'tuple', ['list', 'dict', 'set'], 'Tuple hashable, list tidak.'],
    ['Array vs Linked List: insert di tengah sering', 'Linked List lebih efisien (jika pointer ada)', ['Array selalu lebih efisien', 'Sama saja', 'Stack lebih cocok'], 'Array perlu shift O(n).'],
    ['Dynamic array (ArrayList) resize amortized insert', 'O(1) amortized', ['O(n) selalu', 'O(log n)', 'O(n²)'], 'Resize jarang, amortized O(1).'],
    ['B-tree dipakai banyak DB index karena', 'Optimized for disk block reads', ['Hanya untuk RAM kecil', 'Lebih lambat dari linked list', 'Tidak support range scan'], 'Tinggi rendah, cocok I/O block.'],
    ['In-order traversal BST menghasilkan', 'Elemen terurut ascending', ['Elemen terbalik saja', 'Level order', 'Random order'], 'Left-root-right = sorted.'],
    ['Graph directed vs undirected: jumlah edge maksimum (V vertex)', 'Undirected: V(V-1)/2, Directed: V(V-1)', ['Sama untuk keduanya', 'V² untuk undirected', 'V untuk directed'], 'Rumus edge complete graph.'],
    ['Circular buffer cocok untuk', 'Stream data fixed-size ring', ['Random access index besar', 'Graph traversal', 'Sorting'], 'Overwrite oldest when full.'],
    ['Bloom filter trade-off', 'Probabilistic membership, bisa false positive', ['Never false positive', 'Exact count always', 'Sorted output'], 'Space efficient, no false negative.'],
    ['Skip list expected search time', 'O(log n)', ['O(n)', 'O(1)', 'O(n²)'], 'Probabilistic layered linked list.'],
    ['Memilih struktur untuk implementasi undo/redo editor', 'Two stacks (undo & redo)', ['Single queue', 'Hash map', 'BST'], 'Push/pop undo stack, redo terpisah.'],
];
for ($n = 0; $n < 25; $n++) {
    $d = $dataStructures[$n];
    $questions[] = $make($d[0], $d[1], $d[2], $d[3], 100 + $n);
}

// ── 3. Tracing kode & logika (25) ──────────────────────────────────────────
for ($n = 1; $n <= 25; $n++) {
    $type = $n % 5;
    if ($type === 0) {
        $sum = 0;
        for ($i = 1; $i <= $n; $i++) {
            $sum += $i;
        }
        $wrong = [(string) ($sum + 1), (string) ($sum - 1), (string) ($n * 2)];
        $questions[] = $make(
            'Pseudocode: sum = 0; for i = 1 to ' . $n . ' do sum = sum + i; end. Nilai sum akhir adalah',
            (string) $sum,
            $wrong,
            'Jumlah 1+' . $n . ' = n(n+1)/2 = ' . $sum . '.',
            200 + $n
        );
    } elseif ($type === 1) {
        $x = 1;
        for ($i = 0; $i < $n; $i++) {
            $x *= 2;
        }
        $questions[] = $make(
            'Pseudocode: x = 1; repeat ' . $n . ' times: x = x * 2; end. Nilai x akhir adalah',
            (string) $x,
            [(string) ($x + 1), (string) ($n * 2), (string) pow(2, $n - 1)],
            'x = 2^' . $n . ' = ' . $x . '.',
            210 + $n
        );
    } elseif ($type === 2) {
        $limit = 2 + (intdiv($n, 5) % 7);
        $fact = 1;
        for ($i = 2; $i <= $limit; $i++) {
            $fact *= $i;
        }
        $questions[] = $make(
            'Pseudocode: fact = 1; for i = 2 to ' . $limit . ' do fact = fact * i; end. Nilai fact adalah',
            (string) $fact,
            [(string) ($fact + $limit), (string) ($fact - 1), (string) ($limit * 2)],
            'Faktorial ' . $limit . '! = ' . $fact . '.',
            220 + $n
        );
    } elseif ($type === 3) {
        $a = $n;
        $b = $n + 3;
        while ($b != 0) {
            $temp = $b;
            $b = $a % $b;
            $a = $temp;
        }
        $questions[] = $make(
            'Pseudocode Euclidean GCD: a=' . $n . ', b=' . ($n + 3) . '; while b≠0 swap a,b = b, a mod b; end. GCD(a,b) =',
            (string) $a,
            [(string) ($a + 1), '1', (string) ($n + 3)],
            'Algoritma Euclid: GCD(' . $n . ',' . ($n + 3) . ') = ' . $a . '.',
            230 + $n
        );
    } else {
        $base = 2 + ($n * 3);
        $arr = [$base, $base + 2, $base + 5, $base + 7];
        $target = $base + ($n % 2 === 0 ? 5 : 99);
        $found = in_array($target, $arr, true) ? 'true' : 'false';
        $questions[] = $make(
            'Array = [' . implode(', ', $arr) . ']. Fungsi linearSearch(arr, ' . $target . ') mengembalikan',
            $found,
            [$found === 'true' ? 'false' : 'true', 'null', '0'],
            'Linear search cek setiap elemen; ' . $target . ' ' . ($found === 'true' ? 'ada' : 'tidak ada') . ' di array.',
            240 + $n
        );
    }
}

// ── 4. Rekursi, sorting, dan pola algoritma (25) ───────────────────────────
$patterns = [
    ['Base case penting pada rekursi untuk', 'Mencegah infinite recursion', ['Mempercepat I/O', 'Menambah memory leak', 'Mengganti loop'], 'Tanpa base case, stack overflow.'],
    ['Divide and conquer contoh klasik', 'Merge Sort', ['Bubble Sort', 'Selection Sort saja', 'Linear Search'], 'Bagi, selesaikan sub, gabung.'],
    ['Greedy algorithm tidak selalu', 'Menghasilkan solusi optimal global', ['Memakai loop', 'Memakai rekursi', 'O(n log n)'], 'Greedy lokal ≠ optimal global.'],
    ['Dynamic Programming memakai', 'Overlapping subproblems + optimal substructure', ['Hanya greedy', 'Hanya brute force', 'Randomization'], 'Memo/tabulation submasalah.'],
    ['Two pointers technique cocok untuk', 'Array terurut, palindrome check', ['Graph shortest path', 'Hash collision', 'Heapify'], 'Dua indeks bergerak koordinasi.'],
    ['Sliding window efisien untuk', 'Subarray/substring dengan constraint', ['Sort global', 'DFS tree', 'Union find'], 'Window expand/shrink O(n).'],
    ['Backtracking contoh', 'N-Queens, Sudoku solver', ['Merge sort', 'Hash lookup', 'BFS shortest path'], 'Coba, undo jika gagal.'],
    ['Stable sort artinya', 'Elemen equal mempertahankan urutan relatif awal', ['Selalu O(n)', 'In-place selalu', 'Tidak pakai perbandingan'], 'Stabilitas untuk equal keys.'],
    ['In-place sort artinya', 'O(1) extra space (aside from small stack)', ['O(n) extra selalu', 'Tidak ubah array', 'Hanya linked list'], 'Contoh: quicksort in-place.'],
    ['Counting sort cocok jika', 'Range key integer kecil terbatas', ['Key string panjang', 'Data streaming tak terbatas', 'Graph edge list'], 'O(n+k) dengan k range kecil.'],
    ['Radix sort mengurutkan berdasarkan', 'Digit/digit-group dari LSD atau MSD', ['Hash value', 'Pointer address', 'Random pivot'], 'Non-comparative sort.'],
    ['Topological sort hanya valid pada', 'Directed Acyclic Graph (DAG)', ['Undirected graph', 'Graph with cycle', 'Tree saja'], 'Cycle → no topological order.'],
    ['Memoization vs tabulation: memoization', 'Top-down rekursif + cache', ['Bottom-up iteratif selalu', 'Tanpa cache', 'Hanya greedy'], 'Top-down DP.'],
    ['Tabulation biasanya', 'Bottom-up iteratif isi tabel', ['Top-down rekursif', 'Randomized', 'Brute force exponential'], 'Bottom-up DP.'],
    ['Binary search precondition', 'Data terurut (sorted)', ['Data unique saja', 'Data integer saja', 'Graph connected'], 'Butuh monotonic order.'],
    ['Kadane algorithm menemukan', 'Maximum subarray sum', ['Shortest path', 'Minimum spanning tree', 'Topological order'], 'DP O(n) subarray max.'],
    ['Prefix sum array mempercepat', 'Range sum query O(1) after O(n) build', ['Sorting O(1)', 'Graph BFS O(1)', 'Hash delete O(1)'], 'prefix[i] = sum[0..i-1].'],
    ['Bit manipulation: n & (n-1) menghapus', 'Bit 1 paling kanan', ['Semua bit', 'Bit ternewest', 'Sign bit saja'], 'Trick clear lowest set bit.'],
    ['XOR semua elemen duplikat cancel → sisa', 'Elemen unik (jika satu unik)', ['Elemen terbesar', 'Elemen terkecil', 'Jumlah total'], 'a XOR a = 0.'],
    ['Recursion depth limit di JS/Java stack', 'Stack overflow jika terlalu dalam', ['Heap overflow', 'Disk full', 'Cache miss saja'], 'Call stack terbatas.'],
    ['Iterative DFS bisa memakai', 'Explicit stack', ['Queue saja', 'Heap saja', 'Hash map saja'], 'Simulasi call stack.'],
    ['BFS shortest path pada unweighted graph', 'Benar (first time reached = shortest)', ['Salah selalu', 'Hanya tree', 'Hanya DAG'], 'BFS layer = distance.'],
    ['Dijkstra tidak cocok dengan', 'Negative edge weights', ['Non-negative weights', 'Priority queue', 'Sparse graph'], 'Negative edge break Dijkstra.'],
    ['Bellman-Ford menangani', 'Negative weights (deteksi negative cycle)', ['Hanya non-negative', 'Hanya DAG', 'Unweighted only'], 'Relax V-1 kali.'],
    ['Amortized analysis contoh: dynamic array push', 'O(1) amortized meski resize O(n) sesekali', ['O(1) worst every time', 'O(n) every push', 'O(log n)'], 'Biaya resize disebar.'],
];
for ($n = 0; $n < 25; $n++) {
    $p = $patterns[$n];
    $questions[] = $make($p[0], $p[1], $p[2], $p[3], 300 + $n);
}

return array_slice($questions, 0, 100);
