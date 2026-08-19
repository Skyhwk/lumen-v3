<?php

/**
 * 100 soal DATABASE untuk assessment programmer (manager scope).
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

// ── 1. SQL dasar & query (25) ──────────────────────────────────────────────
$sqlBasics = [
    ['Perintah SQL untuk mengambil semua kolom dari tabel employees adalah', 'SELECT * FROM employees', ['GET ALL employees', 'FETCH employees', 'READ employees'], 'SELECT * mengambil seluruh kolom.'],
    ['Clause SQL untuk filter baris berdasarkan kondisi adalah', 'WHERE', ['FILTER', 'HAVING ONLY', 'IF'], 'WHERE memfilter sebelum grouping.'],
    ['JOIN yang mengembalikan baris matching dari kedua tabel adalah', 'INNER JOIN', ['CROSS JOIN ONLY', 'OUTER DELETE', 'UNION JOIN'], 'INNER JOIN: intersection key match.'],
    ['LEFT JOIN mengembalikan', 'Semua baris kiri + match kanan (NULL jika tidak match)', ['Hanya baris match', 'Hanya baris kanan', 'Hanya baris tanpa match'], 'Left table preserved, right nullable.'],
    ['Aggregate function untuk menghitung jumlah baris non-NULL adalah', 'COUNT(column)', ['SUM(column) always', 'TOTAL()', 'LENGTH(column)'], 'COUNT(*) atau COUNT(col) hitung baris.'],
    ['Clause untuk mengurutkan hasil query adalah', 'ORDER BY', ['SORT WITH', 'ARRANGE', 'GROUP SORT'], 'ORDER BY mengatur urutan baris.'],
    ['Clause untuk membatasi jumlah baris result set adalah', 'LIMIT', ['TOP ONLY SQL Server', 'CAP', 'ROWNUM ALWAYS'], 'LIMIT/OFFSET atau FETCH.'],
    ['DISTINCT keyword fungsinya', 'Menghilangkan baris duplikat di result', ['Sort ascending', 'Filter NULL', 'Join tables'], 'SELECT DISTINCT unique rows.'],
    ['RIGHT JOIN equivalent logic', 'Semua baris kanan + match kiri (NULL jika tidak match)', ['Hanya baris kiri', 'Hanya match', 'Hapus NULL'], 'Mirror of LEFT JOIN.'],
    ['FULL OUTER JOIN mengembalikan', 'Semua baris dari kedua tabel, NULL jika tidak match', ['Hanya intersection', 'Hanya left', 'Hanya right'], 'Union of left + right join.'],
    ['CROSS JOIN menghasilkan', 'Cartesian product semua kombinasi baris', ['Hanya matching rows', 'Sorted merge', 'Distinct rows only'], 'Every row × every row.'],
    ['SELECT COUNT(*) vs COUNT(col): COUNT(*)', 'Hitung semua baris termasuk NULL', ['Hanya non-NULL col', 'Hanya distinct', 'Hanya PK'], 'COUNT(*) = row count.'],
    ['AVG() aggregate mengabaikan', 'NULL values dalam perhitungan', ['Zero values', 'Duplicate rows', 'Primary keys'], 'NULL excluded from average.'],
    ['GROUP BY digunakan bersama', 'Aggregate functions (SUM, COUNT, AVG)', ['ORDER BY saja', 'LIMIT saja', 'DELETE'], 'Group rows for aggregation.'],
    ['IN clause equivalent logic', 'Match any value dalam list', ['Range only', 'Pattern LIKE only', 'Join only'], 'WHERE col IN (...).'],
    ['BETWEEN operator inclusive artinya', 'Termasuk boundary min dan max', ['Exclude boundaries', 'NULL only', 'Reverse sort'], 'BETWEEN a AND b inclusive.'],
    ['LIKE \'A%\' pattern match', 'String diawali A', ['Diakhiri A', 'Mengandung A di tengah saja', 'Exact match'], '% wildcard suffix.'],
    ['IS NULL check benar ditulis', 'column IS NULL', ['column = NULL', 'column == NULL', 'NULL = column only'], 'SQL uses IS NULL, not = NULL.'],
    ['COALESCE(a, b, c) returns', 'First non-NULL argument', ['Last argument always', 'Sum of args', 'Random pick'], 'Null-coalescing function.'],
    ['CASE WHEN in SQL used for', 'Conditional expression in SELECT/WHERE', ['Only joins', 'Only DELETE', 'Only indexes'], 'SQL conditional logic.'],
    ['INSERT INTO ... VALUES adds', 'New row(s) to table', ['Updates existing only', 'Deletes row', 'Creates index'], 'INSERT adds data.'],
    ['UPDATE without WHERE clause', 'Updates ALL rows (dangerous)', ['Updates zero rows', 'Only first row', 'Only PK row'], 'Missing WHERE = full table update.'],
    ['DELETE vs TRUNCATE: TRUNCATE', 'Remove all rows fast, typically DDL-like reset', ['Row-by-row logged always same', 'Can use WHERE', 'Drops table'], 'TRUNCATE fast full clear.'],
    ['CREATE INDEX purpose', 'Speed up query on indexed column(s)', ['Encrypt column', 'Add FK', 'Backup table'], 'Index for lookup performance.'],
    ['View (virtual table) artinya', 'Saved query, no physical data copy', ['Duplicate all data', 'Replace table', 'Index type'], 'View = stored SELECT.'],
];
for ($n = 0; $n < 25; $n++) {
    $item = $sqlBasics[$n];
    $questions[] = $make($item[0], $item[1], $item[2], $item[3], $n);
}

// ── 2. Normalisasi, key, relasi (25) ─────────────────────────────────────────
$normalization = [
    ['Primary Key (PK) fungsinya', 'Uniquely identify each row', ['Encrypt data', 'Speed up disk', 'Replace index'], 'PK unik per baris.'],
    ['Foreign Key (FK) fungsinya', 'Referential integrity antar tabel', ['Primary sort key', 'Backup otomatis', 'Cache query'], 'FK link ke PK parent.'],
    ['1NF (First Normal Form) mensyaratkan', 'Atomic values, no repeating groups', ['No FK allowed', 'No index', 'No NULL'], 'Kolom atomik, tidak array di sel.'],
    ['2NF eliminasi', 'Partial dependency on composite PK', ['Transitive dependency', 'All NULLs', 'Duplicate tables'], 'Non-key depends on full PK.'],
    ['3NF eliminasi', 'Transitive dependency (non-key → non-key)', ['Partial dependency', 'No primary key', 'No foreign key'], 'Non-key hanya depend on PK.'],
    ['Denormalization trade-off', 'Read faster, write/anomaly risk higher', ['Always better', 'No redundancy', 'No index needed'], 'Redundansi untuk performa baca.'],
    ['One-to-Many: Order → OrderItems FK di', 'OrderItems.order_id → Orders.id', ['Orders.item_id', 'Tabel terpisah tanpa FK', 'OrderItems.id → Orders.id reverse'], 'Many side holds FK.'],
    ['Many-to-Many butuh', 'Junction/bridge table', ['Single FK saja', 'Duplicate column', 'No PK'], 'Pivot table dengan 2 FK.'],
    ['UNIQUE constraint vs PRIMARY KEY', 'PK implies UNIQUE + NOT NULL + one per table', ['Sama persis', 'UNIQUE allows multiple PK', 'PK allows duplicate'], 'PK lebih strict.'],
    ['Composite primary key artinya', 'PK dari kombinasi beberapa kolom', ['PK auto increment saja', 'PK dari index saja', 'Tidak ada PK'], 'Multi-column identifier.'],
    ['ON DELETE CASCADE artinya', 'Hapus parent → hapus child terkait', ['Hapus child → parent', 'Block semua delete', 'Set NULL always'], 'Cascade delete children.'],
    ['ON DELETE SET NULL artinya', 'Hapus parent → FK child jadi NULL', ['Hapus child', 'Hapus parent dan child', 'Rollback otomatis'], 'Orphan FK nullable.'],
    ['Entity-Relationship: weak entity', 'Depend on identifying relationship', ['Always has own PK only', 'No relationship', 'No attributes'], 'Identified via owner entity.'],
    ['Surrogate key contoh', 'Auto-increment id tanpa makna bisnis', ['Email as PK always', 'Composite natural key', 'Row hash'], 'Artificial PK (INT UUID).'],
    ['Natural key contoh', 'Email, NIK, nomor invoice (unique bisnis)', ['Random UUID always surrogate', 'Auto increment always natural', 'Index name'], 'Business meaningful unique.'],
    ['Redundant data risiko', 'Update anomaly, inconsistency', ['Selalu lebih aman', 'Tidak affect write', 'Menghilangkan FK'], 'Duplikasi → sync problem.'],
    ['Candidate key adalah', 'Minimal set bisa jadi PK', ['Index non-unique', 'FK saja', 'View column'], 'Superkey minimal.'],
    ['Alternate key adalah', 'Candidate key yang bukan PK terpilih', ['PK itu sendiri', 'FK saja', 'Index saja'], 'Other unique identifiers.'],
    ['Referential integrity violation', 'FK child tidak ada di parent PK', ['Duplicate PK', 'NULL in PK', 'Index missing'], 'Orphan FK reference.'],
    ['Cardinality 1:1 implementasi umum', 'Shared PK atau FK unique di salah satu sisi', ['Many-to-many tanpa bridge', 'No FK', 'Duplicate table'], 'Unique FK or same PK.'],
    ['Schema vs Database (PostgreSQL)', 'Schema namespace inside database', ['Sama persis', 'Schema = server', 'Database inside schema reverse'], 'PG: DB contains schemas.'],
    ['Nullable FK artinya', 'Relasi opsional', ['Relasi wajib always', 'Tidak boleh join', 'PK nullable'], 'Optional relationship.'],
    ['Junction table biasanya PK', 'Composite (fk1, fk2) atau surrogate id', ['Hanya kolom FK tanpa PK', 'Tidak perlu PK', 'Hanya timestamp'], 'Unique pair or surrogate.'],
    ['Soft delete pattern', 'Flag deleted_at / is_deleted, row tetap ada', ['Hard DELETE always', 'TRUNCATE row one', 'DROP table'], 'Logical delete preserve history.'],
    ['Audit trail table biasanya', 'Append-only log perubahan', ['Overwrite history', 'No timestamp', 'No user id'], 'Insert-only audit records.'],
];
for ($n = 0; $n < 25; $n++) {
    $item = $normalization[$n];
    $questions[] = $make($item[0], $item[1], $item[2], $item[3], 100 + $n);
}

// ── 3. Index, transaksi, concurrency (25) ──────────────────────────────────
$transactions = [
    ['ACID: Atomicity artinya', 'All or nothing — transaksi utuh atau rollback', ['Read uncommitted always', 'No rollback', 'Eventual consistency'], 'Atomic = indivisible.'],
    ['ACID: Consistency artinya', 'DB tetap valid rules/constraints setelah commit', ['No constraints', 'Read only', 'No FK'], 'Valid state before/after.'],
    ['ACID: Isolation artinya', 'Concurrent tx tidak saling ganggu semantik', ['No locking', 'Single user only', 'No transactions'], 'Isolation level controls visibility.'],
    ['ACID: Durability artinya', 'Commit data survive crash', ['Data in RAM only', 'Rollback after commit', 'No WAL'], 'Persisted to durable storage.'],
    ['Index primary benefit', 'Faster search/filter/sort on indexed columns', ['Slower writes always worth', 'Replace PK', 'Encrypt column'], 'B-tree/B+ index speed lookup.'],
    ['Index trade-off', 'Slower INSERT/UPDATE/DELETE + extra storage', ['No downside', 'Removes FK', 'Blocks SELECT'], 'Index maintenance cost.'],
    ['Composite index (a, b) paling efektif query', 'WHERE a = ? AND b = ?', ['WHERE b = ? saja (leftmost rule)', 'SELECT * always', 'ORDER BY random'], 'Leftmost prefix rule.'],
    ['Covering index artinya', 'Index contains all columns query needs', ['Index on PK only', 'Full table scan', 'No index'], 'Index-only scan possible.'],
    ['EXPLAIN / EXPLAIN ANALYZE berguna untuk', 'Analyze query execution plan', ['Backup database', 'Create user', 'Drop index only'], 'See scan type, cost, rows.'],
    ['Full table scan terjadi jika', 'No suitable index or small table', ['Always bad on tiny table', 'Never on large table', 'Always with PK'], 'Optimizer choice.'],
    ['Deadlock terjadi ketika', 'Two+ tx wait each other\'s locks', ['Single tx only', 'Read only query', 'Index missing only'], 'Circular wait → DB picks victim.'],
    ['Isolation READ COMMITTED (default many DB)', 'See committed data, no dirty read', ['See uncommitted', 'Serializable always', 'No phantom protection full'], 'Most common default.'],
    ['Isolation SERIALIZABLE', 'Strongest, as if serial execution', ['Allows dirty read', 'No locking', 'Fastest always'], 'Strictest isolation.'],
    ['Dirty read artinya', 'Read uncommitted data from other tx', ['Read committed only', 'Repeatable read', 'Serializable'], 'Uncommitted visible.'],
    ['Non-repeatable read artinya', 'Same query twice, different result (committed update)', ['Dirty read', 'Phantom only', 'No concurrency'], 'Row changed between reads.'],
    ['Phantom read artinya', 'Same range query, new rows appear', ['Same row value change only', 'Dirty read', 'Deadlock'], 'New matching rows inserted.'],
    ['Optimistic locking pakai', 'Version/timestamp column check on update', ['Table lock always', 'No concurrency', 'DELETE only'], 'Compare version before write.'],
    ['Pessimistic locking pakai', 'SELECT ... FOR UPDATE', ['No lock', 'Read uncommitted', 'Cache only'], 'Lock row for update.'],
    ['Connection pool benefit', 'Reuse connections, reduce overhead', ['One connection forever', 'No timeout', 'Replace DB server'], 'Pool amortizes connect cost.'],
    ['N+1 query problem', '1 query parent + N queries children', ['Single join always', 'No ORM issue', 'Index problem only'], 'Lazy load in loop.'],
    ['Fix N+1 typical solution', 'Eager load / JOIN / batch IN query', ['More lazy loads', 'Disable index', 'Full scan'], 'Fetch related in bulk.'],
    ['Database migration tool purpose', 'Version-controlled schema changes', ['Runtime query cache', 'Replace backup', 'User auth'], 'Laravel migrations etc.'],
    ['Replication read replica benefit', 'Offload read traffic from primary', ['Replace backup', 'Write scaling primary', 'No lag ever'], 'Read scaling, eventual lag.'],
    ['Sharding artinya', 'Partition data across multiple DB nodes', ['Single table only', 'Index on one server', 'Cache layer only'], 'Horizontal partition.'],
    ['CAP theorem: partition tolerance + consistency trade', 'Choose CP or AP under partition', ['All three always', 'No partition ever', 'Only consistency'], 'Distributed systems tradeoff.'],
];
for ($n = 0; $n < 25; $n++) {
    $t = $transactions[$n];
    $questions[] = $make($t[0], $t[1], $t[2], $t[3], 200 + $n);
}

// ── 4. SQL lanjut & praktik DB (25) ─────────────────────────────────────────
$sqlAdvanced = [
    ['HAVING clause dipakai untuk filter', 'Hasil agregat setelah GROUP BY', ['Baris sebelum GROUP BY', 'Sebelum WHERE', 'Sebelum FROM'], 'HAVING filter group, WHERE filter row.'],
    ['ORDER BY default ascending, untuk descending pakai', 'ORDER BY column DESC', ['ORDER BY column ASC ONLY', 'SORT DOWN', 'REVERSE BY'], 'DESC untuk urutan menurun.'],
    ['Subquery di WHERE sering dipakai untuk', 'Filter berdasarkan hasil query lain', ['Replace JOIN always', 'Create index', 'Backup table'], 'Nested SELECT for conditional filter.'],
    ['UNION vs UNION ALL: UNION ALL', 'Gabung semua baris termasuk duplikat', ['Hapus duplikat', 'Hanya intersection', 'Hanya first table'], 'UNION ALL tidak dedupe.'],
    ['EXISTS subquery returns true when', 'Subquery returns at least one row', ['Subquery returns zero rows always', 'Main query empty', 'Index missing'], 'EXISTS boolean check.'],
    ['Correlated subquery', 'References outer query row', ['No reference outer', 'Only in FROM', 'Replaces PK'], 'Inner query depends on outer.'],
    ['Window function ROW_NUMBER() assigns', 'Unique sequential number per partition/order', ['Random number', 'Same number all rows', 'Only PK values'], 'Ranking without collapse groups.'],
    ['RANK() vs DENSE_RANK(): DENSE_RANK', 'No gap after ties', ['Gap after ties', 'Random order', 'Only one rank'], 'Dense rank skips no numbers.'],
    ['CTE (WITH clause) benefit', 'Readable named subquery, reusable in statement', ['Faster than all joins', 'Replaces index', 'Only for DELETE'], 'Common Table Expression.'],
    ['Self JOIN used for', 'Compare rows within same table (e.g. hierarchy)', ['Cross database only', 'Delete duplicates always', 'Create index'], 'Table joined to itself.'],
    ['Anti-join pattern (find missing)', 'LEFT JOIN ... WHERE right.id IS NULL', ['INNER JOIN only', 'CROSS JOIN', 'UNION ALL'], 'Rows in left not in right.'],
    ['Upsert common pattern (MySQL)', 'INSERT ... ON DUPLICATE KEY UPDATE', ['DELETE then INSERT always', 'TRUNCATE first', 'DROP TABLE'], 'Insert or update on conflict.'],
    ['PostgreSQL upsert syntax', 'INSERT ... ON CONFLICT DO UPDATE', ['MERGE only', 'REPLACE INTO only MySQL', 'UPDATE ONLY'], 'ON CONFLICT handling.'],
    ['Stored procedure vs function: function', 'Returns value, callable in SELECT', ['No return value', 'Only DDL', 'Only trigger'], 'Function returns result.'],
    ['Trigger executes', 'Automatically on INSERT/UPDATE/DELETE event', ['Only manual CALL', 'On SELECT only', 'On index create'], 'Event-driven SQL code.'],
    ['Materialized view difference', 'Physically stores query result', ['Virtual only always', 'No refresh', 'Temporary table only session'], 'Cached physical snapshot.'],
    ['Query plan index scan vs seq scan', 'Index scan uses index; seq scan reads whole table', ['Same thing', 'Seq scan always faster', 'Index scan no index'], 'Optimizer chooses access path.'],
    ['Partial index indexes', 'Subset of rows matching WHERE predicate', ['All rows always', 'Only PK', 'Only FK'], 'Conditional index subset.'],
    ['Vacuum/optimize maintenance (PostgreSQL/MySQL)', 'Reclaim space, update statistics', ['Encrypt data', 'Add FK', 'Drop column'], 'DB maintenance housekeeping.'],
    ['Prepared statement benefit', 'Parse once, bind params — prevents SQL injection', ['Always slower', 'No param binding', 'Replace ORM'], 'Param binding security + perf.'],
    ['ORM N+1 solved by eager loading example', 'with(\'relation\') / include related', ['Lazy load more', 'Remove index', 'Raw SQL only always'], 'Batch load associations.'],
    ['Database connection string in .env should', 'Not be committed to public repo', ['Hardcoded in JS', 'Shared in chat', 'Logged on every request'], 'Credentials via environment.'],
    ['Read replica lag means', 'Replica slightly behind primary writes', ['Replica always ahead', 'No replication', 'Primary reads replica'], 'Eventual consistency delay.'],
    ['Two-phase commit (2PC) used for', 'Distributed transaction commit coordination', ['Single DB only always', 'UI validation', 'Git merge'], 'Prepare then commit across nodes.'],
    ['Backup strategy 3-2-1 rule', '3 copies, 2 media types, 1 offsite', ['1 copy on laptop', 'No offsite', 'Only cloud cache'], 'Disaster recovery best practice.'],
];
for ($n = 0; $n < 25; $n++) {
    $item = $sqlAdvanced[$n];
    $questions[] = $make($item[0], $item[1], $item[2], $item[3], 300 + $n);
}

return array_slice($questions, 0, 100);
