<?php

/**
 * 100 soal DATABASE untuk assessment programmer (manager scope).
 * Bahasa Indonesia, skenario kontekstual, referensi jurnal/literatur.
 * Return: array of ['text','options','answer','explanation']
 */

$helpers = require __DIR__ . '/QuestionSeederHelpers.php';
$make = $helpers['makeQuestion'];

$questions = [];

// ── 1. SQL dasar — skenario operasional (25) ─────────────────────────────────
$sqlBasics = [
    [
        'HR ingin menampilkan semua kolom karyawan dari tabel employees. Perintah SQL yang benar',
        'SELECT * FROM employees',
        ['GET ALL employees', 'FETCH employees', 'READ employees'],
        'SELECT * mengambil seluruh kolom. Dasar SQL ANSI — digunakan di semua RDBMS.',
    ],
    [
        'Filter karyawan dengan divisi "IT" dari 500 baris. Clause SQL untuk filter baris',
        'WHERE',
        ['FILTER', 'HAVING ONLY', 'IF'],
        'WHERE memfilter baris sebelum grouping. Clause paling sering dipakai di query produksi.',
    ],
    [
        'Laporan gabungan data order dan customer berdasarkan customer_id yang match. Join yang tepat',
        'INNER JOIN',
        ['CROSS JOIN saja', 'DELETE JOIN', 'UNION JOIN'],
        'INNER JOIN: hanya baris dengan key match di kedua tabel.',
    ],
    [
        'Daftar semua customer termasuk yang belum pernah order (order NULL). Join yang tepat',
        'LEFT JOIN',
        ['INNER JOIN', 'RIGHT JOIN saja', 'CROSS JOIN'],
        'LEFT JOIN mempertahankan semua baris tabel kiri — customer tanpa order tetap muncul dengan kolom order NULL.',
    ],
    [
        'Hitung jumlah karyawan aktif (termasuk baris dengan status NULL jika ada). Fungsi agregat yang tepat',
        'COUNT(*)',
        ['SUM(status)', 'TOTAL()', 'LENGTH(status)'],
        'COUNT(*) menghitung semua baris. COUNT(kolom) hanya menghitung baris where kolom tidak NULL.',
    ],
    [
        'Urutkan laporan penjualan dari tertinggi ke terendah. Clause yang benar',
        'ORDER BY total_penjualan DESC',
        ['SORT WITH', 'ARRANGE', 'GROUP SORT'],
        'ORDER BY mengatur urutan baris; DESC = descending.',
    ],
    [
        'Pagination API: ambil 20 produk halaman ke-3. Clause pembatas baris (MySQL)',
        'LIMIT 20 OFFSET 40',
        ['CAP 20', 'ROWNUM ONLY', 'TOP 3'],
        'LIMIT/OFFSET standar pagination — OFFSET = (page-1) × limit.',
    ],
    [
        'Daftar email unik customer (hilangkan duplikat). Keyword SQL',
        'DISTINCT',
        ['UNIQUE INDEX saja', 'FILTER NULL', 'JOIN tables'],
        'SELECT DISTINCT email — eliminasi baris duplikat di result set.',
    ],
    [
        'RIGHT JOIN pada tabel orders (kanan) dan customers (kiri). Hasilnya',
        'Semua baris dari tabel kanan (orders), baris kiri NULL jika tidak match',
        ['Semua baris dari tabel kiri saja', 'Hanya baris yang match di kedua tabel', 'Semua baris tanpa NULL'],
        'RIGHT JOIN = mirror LEFT JOIN — semua baris tabel kanan tetap ditampilkan.',
    ],
    [
        'FULL OUTER JOIN employees dan departments. Hasilnya',
        'Semua baris dari kedua tabel, kolom NULL jika tidak ada pasangan match',
        ['Hanya baris yang match di kedua tabel', 'Hanya baris dari tabel kiri', 'Hanya baris dari tabel kanan'],
        'FULL OUTER JOIN = gabungan LEFT JOIN + RIGHT JOIN.',
    ],
    [
        'CROSS JOIN produk × warna untuk katalog kombinasi. Hasilnya',
        'Cartesian product — semua kombinasi baris',
        ['Hanya matching rows', 'Sorted merge', 'Distinct only'],
        'n × m baris — hati-hati explosion pada tabel besar.',
    ],
    [
        'Perbedaan COUNT(*) vs COUNT(email) saat email bisa NULL',
        'COUNT(*) hitung semua baris; COUNT(email) abaikan NULL',
        ['Sama persis', 'COUNT(email) hitung NULL', 'COUNT(*) hanya PK'],
        'Perbedaan kritis saat kolom nullable — affect laporan akurasi.',
    ],
    [
        'AVG(nilai_ujian) pada kolom dengan beberapa NULL. Perilaku AVG',
        'NULL diabaikan dalam perhitungan rata-rata',
        ['NULL dihitung 0', 'Error', 'Return NULL selalu'],
        'Aggregate functions skip NULL — standar SQL.',
    ],
    [
        'Laporan total penjualan per region. Clause wajib bersama SUM()',
        'GROUP BY region',
        ['ORDER BY saja', 'LIMIT saja', 'DELETE'],
        'GROUP BY mengelompokkan baris untuk agregasi.',
    ],
    [
        'Filter karyawan dengan divisi IT, HR, atau Finance. Operator efisien',
        'WHERE divisi IN (\'IT\', \'HR\', \'Finance\')',
        ['LIKE saja', 'BETWEEN', 'CROSS JOIN'],
        'IN untuk match any value dalam list.',
    ],
    [
        'Filter gaji antara 5 juta dan 10 juta (inklusif). Operator',
        'BETWEEN 5000000 AND 10000000',
        ['EXCLUDE boundaries', 'NULL only', 'REVERSE'],
        'BETWEEN inclusive — boundary termasuk.',
    ],
    [
        'Cari customer nama diawali "PT". Pattern LIKE',
        'LIKE \'PT%\'',
        ['LIKE \'%PT\'', 'LIKE \'%PT%\' exact middle', 'Exact match'],
        '% wildcard suffix — prefix matching.',
    ],
    [
        'Cek kolom deleted_at yang NULL (soft delete). Syntax benar',
        'deleted_at IS NULL',
        ['deleted_at = NULL', 'deleted_at == NULL', 'NULL = deleted_at'],
        'SQL: IS NULL, bukan = NULL. Kesalahan umum junior developer.',
    ],
    [
        'Tampilkan nama jika ada, fallback "Anonim". Fungsi SQL',
        'COALESCE(nama, \'Anonim\')',
        ['SUM(nama)', 'RANDOM()', 'LAST(nama)'],
        'COALESCE return argumen non-NULL pertama.',
    ],
    [
        'Kategorikan gaji: "Tinggi" jika >10jt, else "Normal". SQL conditional',
        'CASE WHEN gaji > 10000000 THEN \'Tinggi\' ELSE \'Normal\' END',
        ['IF gaji ONLY', 'SWITCH gaji', 'GOTO'],
        'CASE WHEN — conditional expression di SQL.',
    ],
    [
        'Tambah karyawan baru ke tabel employees. Perintah',
        'INSERT INTO employees (...) VALUES (...)',
        ['UPDATE only', 'DELETE row', 'CREATE INDEX'],
        'INSERT menambah baris baru.',
    ],
    [
        'UPDATE employees SET gaji = gaji * 1.1 TANPA WHERE. Efeknya',
        'Semua baris terupdate — berbahaya di produksi',
        ['Zero rows', 'Hanya baris pertama', 'Hanya PK'],
        'Missing WHERE = full table update. Incident produksi umum.',
    ],
    [
        'TRUNCATE vs DELETE pada tabel log 10 juta baris. TRUNCATE',
        'Hapus semua baris cepat, reset auto-increment, minimal logging',
        ['Row-by-row sama DELETE', 'Bisa pakai WHERE', 'Drop table'],
        'TRUNCATE DDL-like — cepat untuk clear full table.',
    ],
    [
        'Query WHERE employee_id = ? lambat pada 1 juta baris. Solusi DBA',
        'CREATE INDEX idx_employee_id ON tabel(kolom)',
        ['DROP TABLE', 'ENCRYPT column', 'ADD FK saja'],
        'SIGARUDA Journal (2024): index B-Tree meningkatkan performa query hingga 84.5% pada SIAKAD.',
    ],
    [
        'View laporan_karyawan_aktif (query tersimpan, bukan duplikasi data). Definisi',
        'Virtual table — saved SELECT query',
        ['Physical copy all data', 'Replace table', 'Index type'],
        'View = stored query, data tetap di tabel sumber.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $item = $sqlBasics[$n];
    $questions[] = $make($item[0], $item[1], $item[2], $item[3], $n);
}

// ── 2. Normalisasi, key, relasi — studi kasus SIAKAD (25) ───────────────────
$normalization = [
    [
        'Primary Key (PK) pada tabel mahasiswa di SIAKAD berfungsi',
        'Mengidentifikasi setiap baris secara unik',
        ['Enkripsi data', 'Mempercepat disk', 'Mengganti index'],
        'PK unik per baris — fondasi integritas relasional (Codd, 1970).',
    ],
    [
        'Foreign Key order.customer_id → customers.id berfungsi',
        'Menjaga integritas referensial antar tabel',
        ['Primary sort key', 'Backup otomatis', 'Cache query'],
        'FK mencegah orphan record — order tanpa customer valid.',
    ],
    [
        '1NF (First Normal Form): kolom mata_pelajaran = "Matematika, Fisika" di satu sel. Masalahnya',
        'Melanggar atomicitas — nilai harus atomik, tidak repeating group',
        ['Tidak ada FK', 'Tidak ada index', 'Tidak ada NULL'],
        '1NF: satu nilai per sel. SIGARUDA (2024) menekankan normalisasi untuk integritas SIAKAD.',
    ],
    [
        '2NF eliminasi dependency parsial pada composite PK (order_id, product_id → product_name). Artinya',
        'Atribut non-key hanya depend on full PK, bukan sebagian',
        ['Transitive dependency', 'All NULLs', 'Duplicate tables'],
        'product_name depend on product_id saja → pindah ke tabel products.',
    ],
    [
        '3NF eliminasi transitive dependency (employee_id → dept_id → dept_name). Artinya',
        'Atribut non-key hanya depend langsung on PK, bukan on atribut non-key lain',
        ['Partial dependency', 'No PK', 'No FK'],
        'dept_name depend on dept_id (non-key) → pindah ke tabel departments.',
    ],
    [
        'Denormalization untuk dashboard read-heavy. Trade-off',
        'Baca lebih cepat, risiko anomali update dan redundansi',
        ['Selalu lebih baik', 'Tidak ada redundansi', 'Tidak perlu index'],
        'ResearchGate (2025): denormalization kurangi join tapi tingkatkan redundansi — perlu balance.',
    ],
    [
        'Relasi 1:N Order → OrderItems. FK diletakkan di',
        'OrderItems.order_id → Orders.id (sisi many)',
        ['Orders.item_id', 'Tabel terpisah tanpa FK', 'Reverse FK'],
        'Many side holds FK — pattern standar e-commerce.',
    ],
    [
        'Relasi M:N Mahasiswa ↔ MataKuliah. Solusi implementasi',
        'Junction table mahasiswa_matkul (mahasiswa_id, matkul_id)',
        ['Single FK saja', 'Duplicate column', 'No PK'],
        'Pivot/bridge table dengan 2 FK — pattern SIAKAD.',
    ],
    [
        'UNIQUE constraint vs PRIMARY KEY pada email karyawan',
        'PK implies UNIQUE + NOT NULL + satu PK per tabel; UNIQUE boleh multiple',
        ['Sama persis', 'UNIQUE allows multiple PK', 'PK allows duplicate'],
        'Email UNIQUE tapi bukan PK — alternate key.',
    ],
    [
        'Composite PK (tenant_id, user_id) pada multi-tenant SaaS. Artinya',
        'PK dari kombinasi dua kolom — unik per pasangan',
        ['PK auto increment saja', 'PK dari index', 'Tidak ada PK'],
        'Composite PK umum di multi-tenant architecture.',
    ],
    [
        'ON DELETE CASCADE parent categories → subcategories. Efeknya',
        'Hapus parent → child terkait ikut terhapus',
        ['Hapus child → parent', 'Block semua delete', 'Set NULL always'],
        'Cascade — hati-hati di produksi, bisa hapus ribuan row.',
    ],
    [
        'ON DELETE SET NULL pada optional FK manager_id. Efeknya',
        'Hapus manager → FK karyawan jadi NULL',
        ['Hapus karyawan', 'Hapus manager dan karyawan', 'Rollback otomatis'],
        'Orphan FK nullable — relasi opsional.',
    ],
    [
        'Weak entity (detail tagihan) depend on identifying relationship dengan invoice. Artinya',
        'Tidak bisa diidentifikasi tanpa entity owner',
        ['Always own PK only', 'No relationship', 'No attributes'],
        'Weak entity butuh owner — pattern billing systems.',
    ],
    [
        'Surrogate key id INT AUTO_INCREMENT vs natural key NIK',
        'Surrogate: artificial, no business meaning; Natural: meaningful unique business key',
        ['Email always PK', 'UUID always natural', 'Index name'],
        'Surrogate key umum di ORM; natural key untuk integrasi legacy.',
    ],
    [
        'Natural key contoh valid di Indonesia',
        'NIK, nomor invoice, email unik bisnis',
        ['Random UUID always natural', 'Auto increment always natural', 'Index name'],
        'Business-meaningful unique identifier.',
    ],
    [
        'Redundant data (nama_dept di tabel karyawan DAN departments). Risiko',
        'Update anomaly — data tidak konsisten antar tabel',
        ['Selalu lebih aman', 'Tidak affect write', 'Menghilangkan FK'],
        'Duplikasi → sync problem. arxiv (2025) ukur efek normalisasi pada redundancy.',
    ],
    [
        'Candidate key pada tabel karyawan (NIK, email, employee_code semua unique)',
        'Minimal set atribut yang bisa menjadi PK',
        ['Index non-unique', 'FK saja', 'View column'],
        'Superkey minimal — pilih satu jadi PK, sisanya alternate key.',
    ],
    [
        'Alternate key email (unique, bukan PK terpilih)',
        'Candidate key yang bukan PK terpilih',
        ['PK itu sendiri', 'FK saja', 'Index saja'],
        'Other unique identifiers selain PK.',
    ],
    [
        'Referential integrity violation: order.customer_id = 999 tapi customers.id tidak ada',
        'FK child tidak ada di parent PK — orphan reference',
        ['Duplicate PK', 'NULL in PK', 'Index missing'],
        'DB reject insert/update atau orphan data jika constraint disabled.',
    ],
    [
        'Cardinality 1:1 User ↔ UserProfile. Implementasi umum',
        'Shared PK atau FK UNIQUE di salah satu sisi',
        ['Many-to-many tanpa bridge', 'No FK', 'Duplicate table'],
        'Unique FK or same PK — pattern profile extension.',
    ],
    [
        'Schema vs Database di PostgreSQL',
        'Schema = namespace di dalam database',
        ['Sama persis', 'Schema = server', 'Database inside schema'],
        'PG: satu database, multiple schemas (public, hr, billing).',
    ],
    [
        'Nullable FK manager_id pada karyawan (belum punya manager)',
        'Relasi opsional — NULL = tidak ada relasi',
        ['Relasi wajib always', 'Tidak boleh join', 'PK nullable'],
        'Optional relationship pattern.',
    ],
    [
        'Junction table enrollment PK umum',
        'Composite (mahasiswa_id, matkul_id) atau surrogate id + UNIQUE constraint',
        ['Hanya FK tanpa PK', 'Tidak perlu PK', 'Hanya timestamp'],
        'Unique pair enrollment per semester.',
    ],
    [
        'Soft delete pattern deleted_at timestamp. Keuntungan',
        'Data tetap ada untuk audit/history, query filter deleted_at IS NULL',
        ['Hard DELETE always', 'TRUNCATE row one', 'DROP table'],
        'Logical delete — pattern Laravel Eloquent SoftDeletes.',
    ],
    [
        'Audit trail table append-only. Prinsip',
        'Insert-only log perubahan — tidak overwrite history',
        ['Overwrite history', 'No timestamp', 'No user id'],
        'Compliance dan forensik — immutable audit log.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $item = $normalization[$n];
    $questions[] = $make($item[0], $item[1], $item[2], $item[3], 100 + $n);
}

// ── 3. Index, transaksi, concurrency — penelitian performa (25) ──────────────
$transactions = [
    [
        'Pada transaksi transfer bank (debit A + credit B), apa arti prinsip Atomicity dalam ACID',
        'Semua operasi sukses seluruhnya, atau seluruh transaksi dibatalkan (rollback)',
        ['Hanya operasi debit yang wajib sukses', 'Tidak perlu rollback jika gagal', 'Transaksi boleh sebagian selesai'],
        'Atomicity = all or nothing. Referensi: literatur database transaction (ACID).',
    ],
    [
        'Setelah transfer saldo antar rekening, apa arti prinsip Consistency dalam ACID',
        'Database tetap memenuhi aturan bisnis dan constraint (saldo tidak negatif, FK valid)',
        ['Tidak boleh ada constraint', 'Hanya read-only selama transfer', 'FK tidak perlu dicek'],
        'Consistency = valid state before/after commit.',
    ],
    [
        'Dua transaksi concurrent mengupdate stok produk bersamaan. Apa arti prinsip Isolation dalam ACID',
        'Transaksi concurrent tidak saling mengganggu hasil masing-masing secara semantik',
        ['Tidak boleh ada locking sama sekali', 'Hanya satu user boleh akses database', 'Transaksi tidak diperlukan'],
        'Isolation level mengatur visibilitas perubahan antar transaksi.',
    ],
    [
        'Setelah commit transfer bank, apa arti prinsip Durability dalam ACID',
        'Data yang sudah commit tetap aman meskipun server crash/restart',
        ['Data hanya tersimpan di RAM', 'Commit bisa otomatis rollback setelah restart', 'Tidak perlu WAL/redo log'],
        'Durability dijamin oleh WAL/redo log ke storage permanen.',
    ],
    [
        'Index B-Tree pada kolom email untuk login. Apa manfaat utama penggunaan index tersebut',
        'Mempercepat pencarian/filter/sort pada kolom email',
        ['Membuat insert selalu lebih cepat tanpa trade-off', 'Mengganti fungsi Primary Key', 'Mengenkripsi kolom email'],
        'CAI Journal (2024): B-Tree indexing dapat speedup 2.60× vs full table scan.',
    ],
    [
        'Trade-off index pada tabel write-heavy (log insert 10K/detik)',
        'INSERT/UPDATE/DELETE lebih lambat + extra storage',
        ['No downside', 'Removes FK', 'Blocks SELECT'],
        'Index maintenance cost — jangan over-index tabel log.',
    ],
    [
        'Composite index (region, status). Query paling efektif',
        'WHERE region = ? AND status = ?',
        ['WHERE status = ? saja (tanpa region)', 'SELECT * always', 'ORDER BY RANDOM()'],
        'Leftmost prefix rule — index (a,b) butuh filter a dulu.',
    ],
    [
        'Covering index pada query SELECT id, name WHERE email = ?',
        'Index contains all columns query needs — index-only scan',
        ['Index on PK only', 'Full table scan', 'No index'],
        'Avoid table lookup — SIGARUDA (2024) shift ke index range scan.',
    ],
    [
        'Query lambat 2.9 detik. EXPLAIN ANALYZE menunjukkan Seq Scan. Langkah DBA',
        'Analisis execution plan, pertimbangkan index pada kolom filter',
        ['Backup database', 'Create user', 'Drop table'],
        'EXPLAIN ANALYZE — tool wajib DBA. Sitasi (2023) dokumentasi manfaat B-Tree.',
    ],
    [
        'Full table scan pada tabel 10.000 baris nilai (SIGARUDA, 2024) tanpa index',
        'Terjadi karena tidak ada index cocok — scan seluruh baris',
        ['Always bad', 'Never on small table', 'Always with PK'],
        'SIGARUDA: full scan 0.0029s → dengan index 0.0004s (84.5% faster).',
    ],
    [
        'Deadlock: Tx A lock row 1 tunggu row 2; Tx B lock row 2 tunggu row 1',
        'Circular wait — DB pilih victim dan rollback salah satu',
        ['Single tx only', 'Read only query', 'Index missing only'],
        'Deadlock detection — retry pattern di aplikasi.',
    ],
    [
        'Isolation READ COMMITTED (default PostgreSQL/MySQL)',
        'Lihat data committed, tidak dirty read',
        ['Lihat uncommitted', 'Serializable always', 'No phantom protection full'],
        'Most common default isolation level.',
    ],
    [
        'Isolation SERIALIZABLE untuk laporan keuangan kritis',
        'Strongest — seolah-olah transaksi serial',
        ['Allows dirty read', 'No locking', 'Fastest always'],
        'Strictest — trade-off performance.',
    ],
    [
        'Dirty read: Tx B baca perubahan Tx A yang belum commit lalu Tx A rollback',
        'Membaca data uncommitted yang bisa dibatalkan',
        ['Read committed only', 'Repeatable read', 'Serializable'],
        'Dirty read — prevented by READ COMMITTED+.',
    ],
    [
        'Non-repeatable read: query gaji karyawan X dua kali, hasil berbeda (update committed di antara)',
        'Same query twice, different result karena committed update',
        ['Dirty read', 'Phantom only', 'No concurrency'],
        'Repeatable read prevents this.',
    ],
    [
        'Phantom read: query range gaji 5-10jt dua kali, row baru muncul di antara',
        'Same range query, new rows appear (insert committed)',
        ['Same row value change only', 'Dirty read', 'Deadlock'],
        'Serializable prevents phantom reads.',
    ],
    [
        'Optimistic locking pada update stok e-commerce (version column)',
        'Check version/timestamp before update — fail if changed',
        ['Table lock always', 'No concurrency', 'DELETE only'],
        'Compare-and-swap pattern — cocok low contention.',
    ],
    [
        'Pessimistic locking SELECT ... FOR UPDATE pada seat booking',
        'Lock row until transaction commit — prevent double booking',
        ['No lock', 'Read uncommitted', 'Cache only'],
        'Row-level lock — pattern tiket/concert booking.',
    ],
    [
        'Connection pool Laravel/MySQL pada traffic tinggi',
        'Reuse connections — reduce connect overhead',
        ['One connection forever', 'No timeout', 'Replace DB server'],
        'Pool amortizes TCP+auth handshake cost.',
    ],
    [
        'N+1 query: 1 query posts + 100 query comments per post di loop',
        '1 query parent + N queries children — anti-pattern ORM',
        ['Single join always', 'No ORM issue', 'Index problem only'],
        'Classic Laravel Eloquent lazy load problem.',
    ],
    [
        'Fix N+1 pada Laravel: Post::with(\'comments\')->get()',
        'Eager load / JOIN / batch IN query',
        ['More lazy loads', 'Disable index', 'Full scan'],
        'with() batch load — best practice ORM.',
    ],
    [
        'Laravel migration php artisan migrate purpose',
        'Version-controlled schema changes — reproducible deploy',
        ['Runtime query cache', 'Replace backup', 'User auth'],
        'Infrastructure as code for database schema.',
    ],
    [
        'Read replica MySQL untuk report heavy',
        'Offload read traffic dari primary — eventual replication lag',
        ['Replace backup', 'Write scaling primary', 'No lag ever'],
        'Read scaling dengan trade-off consistency lag.',
    ],
    [
        'Sharding database e-commerce 100 juta user',
        'Partition data across multiple DB nodes horizontally',
        ['Single table only', 'Index on one server', 'Cache layer only'],
        'Horizontal partition — complexity tinggi.',
    ],
    [
        'CAP theorem pada distributed DB saat network partition',
        'Pilih Consistency atau Availability — partition tolerance wajib',
        ['All three always', 'No partition ever', 'Only consistency'],
        'Brewer\'s CAP — dasar arsitektur distributed systems.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $t = $transactions[$n];
    $questions[] = $make($t[0], $t[1], $t[2], $t[3], 200 + $n);
}

// ── 4. SQL lanjut & praktik produksi (25) ───────────────────────────────────
$sqlAdvanced = [
    [
        'Filter grup penjualan per region yang totalnya lebih dari 1 miliar. Clause SQL manakah yang benar',
        'HAVING SUM(penjualan) > 1000000000',
        ['WHERE SUM(penjualan) > 1000000000', 'FILTER SUM(penjualan)', 'GROUP SUM(penjualan)'],
        'HAVING memfilter hasil agregat setelah GROUP BY, bukan sebelum.',
    ],
    [
        'Urutkan laporan bulanan terbaru dulu. Syntax',
        'ORDER BY tahun DESC, bulan DESC',
        ['ORDER BY ASC ONLY', 'SORT DOWN', 'REVERSE BY'],
        'Multi-column ORDER BY — pattern laporan temporal.',
    ],
    [
        'Ingin menampilkan karyawan dengan gaji di atas rata-rata perusahaan. Pola query SQL manakah yang tepat',
        'Subquery: WHERE gaji > (SELECT AVG(gaji) FROM karyawan)',
        ['JOIN tanpa kondisi', 'SELECT AVG saja tanpa filter', 'DELETE baris di bawah rata-rata'],
        'Subquery di WHERE untuk filter berdasarkan agregat.',
    ],
    [
        'Gabung hasil query Q1 dan Q2 termasuk duplikat',
        'UNION ALL',
        ['UNION (dedupe)', 'INTERSECT only', 'EXCEPT only'],
        'UNION ALL tidak dedupe — lebih cepat jika duplikat OK.',
    ],
    [
        'Cek apakah customer pernah memiliki order. Apa hasil EXISTS jika subquery mengembalikan minimal 1 baris',
        'TRUE (benar) — customer pernah order',
        ['FALSE selalu', 'NULL selalu', 'Error karena subquery'],
        'EXISTS return boolean: true jika subquery punya minimal 1 baris.',
    ],
    [
        'Subquery correlated: SELECT * FROM emp e WHERE gaji > (SELECT AVG(gaji) FROM emp WHERE dept = e.dept)',
        'Inner query references outer row — depends on outer',
        ['No reference outer', 'Only in FROM', 'Replaces PK'],
        'Per-department average comparison.',
    ],
    [
        'Ranking penjual per region: ROW_NUMBER() OVER (PARTITION BY region ORDER BY total DESC)',
        'Unique sequential number per partition',
        ['Random number', 'Same number all rows', 'Only PK values'],
        'Window function — ranking without GROUP BY collapse.',
    ],
    [
        'RANK() vs DENSE_RANK() saat ada tie (2 juara 1, berikutnya juara 2 bukan 3)',
        'DENSE_RANK — no gap after ties',
        ['Gap after ties', 'Random order', 'Only one rank'],
        'DENSE_RANK(1,1,2) vs RANK(1,1,3).',
    ],
    [
        'CTE WITH monthly_sales AS (...) SELECT * FROM monthly_sales. Benefit',
        'Readable named subquery, reusable in statement',
        ['Always faster than joins', 'Replaces index', 'Only for DELETE'],
        'Common Table Expression — readability + recursion support.',
    ],
    [
        'Self JOIN employees e1 JOIN employees e2 ON e1.manager_id = e2.id',
        'Compare rows within same table — hierarchy',
        ['Cross database only', 'Delete duplicates always', 'Create index'],
        'Org chart: employee + manager from same table.',
    ],
    [
        'Anti-join: customer yang BELUM pernah order',
        'LEFT JOIN orders ON ... WHERE orders.id IS NULL',
        ['INNER JOIN only', 'CROSS JOIN', 'UNION ALL'],
        'Rows in left not in right — gap analysis.',
    ],
    [
        'Upsert MySQL: insert user atau update jika email sudah ada',
        'INSERT ... ON DUPLICATE KEY UPDATE',
        ['DELETE then INSERT always', 'TRUNCATE first', 'DROP TABLE'],
        'Atomic insert-or-update — sync/import pattern.',
    ],
    [
        'Upsert PostgreSQL conflict handling',
        'INSERT ... ON CONFLICT DO UPDATE',
        ['MERGE only SQL Server', 'REPLACE INTO MySQL only', 'UPDATE ONLY'],
        'ON CONFLICT — PG equivalent upsert.',
    ],
    [
        'Stored function vs procedure: function',
        'Returns value, callable in SELECT',
        ['No return value', 'Only DDL', 'Only trigger'],
        'Function for computation; procedure for action.',
    ],
    [
        'Trigger AFTER INSERT ON audit_log',
        'Executes automatically on INSERT event',
        ['Only manual CALL', 'On SELECT only', 'On index create'],
        'Event-driven — audit trail automation.',
    ],
    [
        'Materialized view vs regular view',
        'Materialized: physically stores query result (cached snapshot)',
        ['Virtual only always', 'No refresh', 'Session temp only'],
        'Refresh schedule for dashboard performance.',
    ],
    [
        'EXPLAIN shows Index Scan vs Seq Scan. Index Scan means',
        'Query uses index to find rows — faster on large table',
        ['Same as seq scan', 'Seq scan always faster', 'No index used'],
        'SIGARUDA: shift from seq scan to index range scan = 84.5% faster.',
    ],
    [
        'Partial index CREATE INDEX idx ON orders(status) WHERE status = \'pending\'',
        'Indexes subset of rows matching predicate',
        ['All rows always', 'Only PK', 'Only FK'],
        'Smaller index for hot subset — common optimization.',
    ],
    [
        'PostgreSQL VACUUM / ANALYZE maintenance',
        'Reclaim dead tuple space, update planner statistics',
        ['Encrypt data', 'Add FK', 'Drop column'],
        'DB housekeeping — prevent bloat and stale stats.',
    ],
    [
        'Prepared statement PDO Laravel DB::select(\'... WHERE id = ?\', [$id])',
        'Parse once, bind params — prevents SQL injection',
        ['Always slower', 'No param binding', 'Replace ORM'],
        'Param binding — security + performance. OWASP SQL injection prevention.',
    ],
    [
        'Laravel N+1 fix: Model::with([\'relation\'])->get()',
        'Eager loading — batch fetch associations',
        ['More lazy loads', 'Remove index', 'Raw SQL only always'],
        'with() / load() — Laravel best practice.',
    ],
    [
        'Database credentials di .env Laravel',
        'Tidak boleh di-commit ke repo publik — gunakan environment variables',
        ['Hardcoded in JS', 'Shared in chat', 'Logged every request'],
        '12-factor app: config via environment.',
    ],
    [
        'Read replica lag 2 detik pada dashboard real-time',
        'Replica 2 detik behind primary — eventual consistency',
        ['Replica ahead of primary', 'No replication', 'Primary reads replica'],
        'Design: critical read from primary, report from replica.',
    ],
    [
        'Two-phase commit (2PC) pada distributed transaction',
        'Coordinate commit across multiple DB nodes — prepare then commit',
        ['Single DB only always', 'UI validation', 'Git merge'],
        'Distributed transaction protocol — microservices challenge.',
    ],
    [
        'Strategi backup database produksi dengan aturan 3-2-1. Apa arti aturan 3-2-1 tersebut',
        '3 salinan data, 2 jenis media penyimpanan, 1 salinan disimpan offsite',
        ['3 backup per hari, 2 server, 1 admin', '3 tabel, 2 index, 1 replica', 'Hanya 1 salinan di laptop'],
        'Aturan 3-2-1 = best practice disaster recovery database.',
    ],
];
for ($n = 0; $n < 25; $n++) {
    $item = $sqlAdvanced[$n];
    $questions[] = $make($item[0], $item[1], $item[2], $item[3], 300 + $n);
}

return array_slice($questions, 0, 100);
